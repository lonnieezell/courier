<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use CodeIgniter\Email\Email;
use CodeIgniter\Events\Events;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\DTO\CampaignDTO;
use Myth\Courier\DTO\ContactDTO;
use Myth\Courier\DTO\DripStepDTO;
use Myth\Courier\DTO\SendDTO;
use Myth\Courier\Enums\SendStatus;
use Myth\Courier\Events\CourierEvents;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\LinkModel;
use Myth\Courier\Models\SendModel;
use Psr\Log\LoggerAwareTrait;
use Throwable;

/**
 * Renders and delivers individual emails, recording delivery status in SendModel.
 */
class MailerService
{
    use LoggerAwareTrait;

    private CourierConfig $config;

    public function __construct(
        private readonly TemplateService $templateService,
        private readonly SendModel $sendModel,
        private readonly CampaignModel $campaignModel,
        private ?Email $email = null,
        private readonly LinkModel $linkModel = new LinkModel(),
    ) {
        $this->config = config(CourierConfig::class);
        $this->config->validate();
        $this->email ??= service('email');
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
     */
    public function send(ContactDTO $contact, CampaignDTO $campaign, SendDTO $sendLog, ?string $subject = null, ?string $bodyView = null): bool
    {
        $layout = $campaign->layout ?? $this->config->defaultLayout;
        $subject ??= $campaign->subject;
        $viewPath = $bodyView ?? $campaign->view;

        $data = [
            'contact' => $contact,
            'subject' => $subject,
        ];

        $html             = $this->templateService->render($viewPath, $layout ?: null, $data);
        [$html, $linkMap] = $this->wrapLinks($html);
        $this->linkModel->insertLinks($sendLog->id, $linkMap);

        $base           = $this->trackingBase();
        $unsubscribeUrl = $sendLog->unsubscribe_token !== null
            ? $base . '/unsubscribe/' . $sendLog->unsubscribe_token
            : '';
        $trackingPixel = '<img src="' . $base . '/open/' . $sendLog->open_token . '" width="1" height="1" alt="">';

        $html = str_replace('{courier_unsubscribe_url}', $unsubscribeUrl, $html);
        $html = str_replace('{courier_tracking_pixel}', $trackingPixel, $html);

        $plainText = $this->templateService->renderText($viewPath, $data);
        $plainText .= "\n\nUnsubscribe: " . $unsubscribeUrl;

        if ($this->config->testMode) {
            log_message('info', '[Courier] testMode: would send to {email} subject "{subject}"', [
                'email'   => $contact->email,
                'subject' => $subject,
            ]);

            $this->sendModel->update($sendLog->id, [
                'status'  => SendStatus::Sent,
                'sent_at' => date('Y-m-d H:i:s'),
            ]);

            try {
                Events::trigger(CourierEvents::EMAIL_SENT, $sendLog);
            } catch (Throwable $e) {
                log_message('error', 'courier:email.sent listener error: ' . $e->getMessage());
            }

            return true;
        }

        $fromName  = $campaign->from_name ?: $this->config->fromName;
        $fromEmail = $campaign->from_email ?: $this->config->fromEmail;

        $this->email->clear();
        $this->email->setFrom($fromEmail, $fromName);
        $this->email->setTo($contact->email);
        $this->email->setSubject($subject);
        $this->email->setMessage($html);
        $this->email->setAltMessage($plainText);

        if ($this->email->send(false)) {
            $update = [
                'status'  => SendStatus::Sent,
                'sent_at' => date('Y-m-d H:i:s'),
            ];

            $messageId = $this->email->getMessageID();
            if ($messageId !== '') {
                $update['message_id'] = $messageId;
            }

            $this->sendModel->update($sendLog->id, $update);

            try {
                Events::trigger(CourierEvents::EMAIL_SENT, $sendLog);
            } catch (Throwable $e) {
                log_message('error', 'courier:email.sent listener error: ' . $e->getMessage());
            }

            return true;
        }

        $this->sendModel->update($sendLog->id, ['status' => SendStatus::Failed]);

        try {
            Events::trigger(CourierEvents::EMAIL_FAILED, $sendLog);
        } catch (Throwable $e) {
            log_message('error', 'courier:email.failed listener error: ' . $e->getMessage());
        }

        return false;
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
     * Uses the step's view for the body so each step can have its own template.
     */
    public function sendStep(ContactDTO $contact, DripStepDTO $dripStep, ?CampaignDTO $campaign = null): bool
    {
        $campaign ??= $this->campaignModel->find($dripStep->campaign_id);

        if ($campaign === null) {
            return false;
        }

        $sendLog = $this->sendModel->createPending($contact->id, $campaign->id, $dripStep->id);

        return $this->send($contact, $campaign, $sendLog, $dripStep->subject, $dripStep->view !== '' ? $dripStep->view : null);
    }
}
