<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use CodeIgniter\Events\Events;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\DTO\CampaignDTO;
use Myth\Courier\DTO\ContactDTO;
use Myth\Courier\DTO\DripStepDTO;
use Myth\Courier\DTO\SendDTO;
use Myth\Courier\Enums\SendStatus;
use Myth\Courier\Events\CourierEvents;
use Myth\Courier\Mailables\CourierMailable;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\LinkModel;
use Myth\Courier\Models\SendModel;
use Myth\Postal\Email;
use Throwable;

/**
 * Renders and delivers individual emails through postal's mailer pipeline,
 * recording delivery status in SendModel.
 */
class MailerService
{
    public function __construct(
        private readonly TemplateService $templateService,
        private readonly SendModel $sendModel,
        private readonly CampaignModel $campaignModel,
        private readonly CourierConfig $config,
        private readonly LinkModel $linkModel = new LinkModel(),
    ) {
        $this->config->validate();
    }

    private function trackingBase(): string
    {
        $host = $this->config->trackingHost !== ''
            ? rtrim($this->config->trackingHost, '/')
            : rtrim(base_url(), '/');

        return $host . '/courier';
    }

    /**
     * Renders and sends one email for a contact+campaign pair.
     *
     * The caller must pre-create $sendLog via SendModel::createPending().
     * Pass $subject to override $campaign->subject (used by drip steps).
     * Pass $bodyView to override $campaign->view (used by drip steps with per-step views).
     * Pass $bodyMailable to override $campaign->mailable (used by drip steps).
     *
     * A Mailable (campaign- or step-level) takes precedence over a view.
     */
    public function send(ContactDTO $contact, CampaignDTO $campaign, SendDTO $sendLog, ?string $subject = null, ?string $bodyView = null, ?string $bodyMailable = null): bool
    {
        $subject ??= $campaign->subject;

        // A step that supplies its own view/mailable does not fall back to the
        // campaign mailable; a campaign-level send (no $bodyView) does.
        $mailableClass = $bodyMailable ?? ($bodyView === null ? $campaign->mailable : null);

        if ($mailableClass !== null) {
            $email     = $this->renderMailable($mailableClass, $contact, $campaign);
            $html      = $email->htmlBody ?? '';
            $plainText = $email->textBody ?? '';
        } else {
            $layout   = $campaign->layout ?? $this->config->defaultLayout;
            $viewPath = $bodyView ?? $campaign->view;
            $data     = ['contact' => $contact, 'subject' => $subject];

            $email     = new Email();
            $html      = $this->templateService->render($viewPath, $layout ?: null, $data);
            $plainText = $this->templateService->renderText($viewPath, $data);
        }

        $html = $this->applyTracking($html, $sendLog);
        $plainText .= "\n\nUnsubscribe: " . $this->unsubscribeUrl($sendLog);

        if ($mailableClass === null) {
            $fromName  = $campaign->from_name ?: $this->config->fromName;
            $fromEmail = $campaign->from_email ?: $this->config->fromEmail;
            $email->from($fromEmail, $fromName)->to($contact->email)->subject($subject);
        }

        $email->html($html)->text($plainText);

        return $this->deliver($email, $contact->email, $subject, $sendLog);
    }

    /**
     * Sends a one-off transactional Mailable to a contact, recording it as a
     * campaign-less send. Suppression and tracking are applied as for campaign
     * sends.
     */
    public function sendMailable(CourierMailable $mailable, ContactDTO $contact): bool
    {
        $sendLog = $this->sendModel->createPending($contact->id, null, null);

        $mailable->contact = $contact;
        $email             = $mailable->render();

        $html = $this->applyTracking($email->htmlBody ?? '', $sendLog);
        $email->html($html);

        return $this->deliver($email, $contact->email, $email->subject, $sendLog);
    }

    /**
     * Builds the postal Email for a Mailable class, injecting the recipient
     * contact and originating campaign before it composes itself.
     *
     * @param class-string<CourierMailable> $mailableClass
     */
    private function renderMailable(string $mailableClass, ContactDTO $contact, ?CampaignDTO $campaign): Email
    {
        $mailable           = new $mailableClass();
        $mailable->contact  = $contact;
        $mailable->campaign = $campaign;

        return $mailable->render();
    }

    /**
     * Rewrites tracked links, persists the link map, and expands Courier's HTML
     * placeholders (unsubscribe URL and open-tracking pixel) for one send.
     */
    private function applyTracking(string $html, SendDTO $sendLog): string
    {
        [$html, $linkMap] = $this->wrapLinks($html);
        $this->linkModel->insertLinks($sendLog->id, $linkMap);

        $pixel = '<img src="' . $this->trackingBase() . '/open/' . $sendLog->open_token . '" width="1" height="1" alt="">';

        return str_replace(
            ['{courier_unsubscribe_url}', '{courier_tracking_pixel}'],
            [$this->unsubscribeUrl($sendLog), $pixel],
            $html,
        );
    }

    /**
     * Returns the per-send unsubscribe URL, or an empty string when the send
     * carries no unsubscribe token.
     */
    private function unsubscribeUrl(SendDTO $sendLog): string
    {
        return $sendLog->unsubscribe_token !== null
            ? $this->trackingBase() . '/unsubscribe/' . $sendLog->unsubscribe_token
            : '';
    }

    /**
     * Short-circuits in testMode (logging instead of sending); otherwise hands
     * the composed message to postal and records the outcome.
     */
    private function deliver(Email $email, string $recipient, string $subject, SendDTO $sendLog): bool
    {
        if ($this->config->testMode) {
            log_message('info', '[Courier] testMode: would send to {email} subject "{subject}"', [
                'email'   => $recipient,
                'subject' => $subject,
            ]);

            $this->sendModel->update($sendLog->id, [
                'status'  => SendStatus::Sent,
                'sent_at' => date('Y-m-d H:i:s'),
            ]);
            $this->fireEvent(CourierEvents::EMAIL_SENT, $sendLog);

            return true;
        }

        $result = service('mailer')->send($email);

        if ($result->success) {
            $update = [
                'status'  => SendStatus::Sent,
                'sent_at' => date('Y-m-d H:i:s'),
            ];

            if ($result->messageId !== null && $result->messageId !== '') {
                $update['message_id'] = $result->messageId;
            }

            $this->sendModel->update($sendLog->id, $update);
            $this->fireEvent(CourierEvents::EMAIL_SENT, $sendLog);

            return true;
        }

        if ($result->cancelled) {
            $this->sendModel->update($sendLog->id, ['status' => SendStatus::Suppressed]);

            return false;
        }

        $this->sendModel->update($sendLog->id, ['status' => SendStatus::Failed]);
        $this->fireEvent(CourierEvents::EMAIL_FAILED, $sendLog);

        return false;
    }

    /**
     * Fires a Courier email lifecycle event, swallowing listener exceptions so a
     * misbehaving listener cannot abort the send.
     */
    private function fireEvent(string $event, SendDTO $sendLog): void
    {
        try {
            Events::trigger($event, $sendLog);
        } catch (Throwable $e) {
            log_message('error', $event . ' listener error: ' . $e->getMessage());
        }
    }

    /**
     * Rewrites all http/https href attributes through per-link click-tracking tokens,
     * excluding the {courier_unsubscribe_url} placeholder.
     *
     * Returns the rewritten HTML and a token→url map for the caller to persist.
     *
     * @return array{string, array<string, string>}
     */
    public function wrapLinks(string $html): array
    {
        $base    = $this->trackingBase();
        $linkMap = [];

        $result = (string) preg_replace_callback(
            '/href=(["\'])(https?:\/\/[^"\']+)\1/i',
            static function (array $m) use ($base, &$linkMap): string {
                $quote           = $m[1];
                $originalUrl     = $m[2];
                $token           = bin2hex(random_bytes(16));
                $linkMap[$token] = $originalUrl;

                return 'href=' . $quote . $base . '/click/' . $token . $quote;
            },
            $html,
        );

        return [$result, $linkMap];
    }

    /**
     * Convenience wrapper for drip step sends.
     * Loads the campaign, creates the pending send record, then calls send().
     * Uses the step's view or mailable for the body so each step can have its own template.
     */
    public function sendStep(ContactDTO $contact, DripStepDTO $dripStep, ?CampaignDTO $campaign = null): bool
    {
        $campaign ??= $this->campaignModel->find($dripStep->campaign_id);

        if ($campaign === null) {
            return false;
        }

        $sendLog = $this->sendModel->createPending($contact->id, $campaign->id, $dripStep->id);

        return $this->send(
            $contact,
            $campaign,
            $sendLog,
            $dripStep->subject,
            $dripStep->view !== '' ? $dripStep->view : null,
            $dripStep->mailable,
        );
    }
}
