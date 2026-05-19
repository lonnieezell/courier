<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use CodeIgniter\Email\Email;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Enums\SendStatus;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\SendModel;
use Psr\Log\LoggerAwareTrait;

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
    ) {
        $this->config = config(CourierConfig::class);
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
     */
    public function send(object $contact, object $campaign, object $sendLog, ?string $subject = null): bool
    {
        $layout = $campaign->layout ?? $this->config->defaultLayout;
        $subject ??= $campaign->subject;

        $data = [
            'contact' => $contact,
            'subject' => $subject,
        ];

        // Render HTML body (placeholders replaced after link wrapping)
        $html = $this->templateService->render($campaign->view, $layout ?: null, $data);

        // Wrap tracked links before injecting unsubscribe/pixel placeholders
        $html = $this->wrapLinks($html, $sendLog->click_token);

        // Inject per-send values
        $base           = $this->trackingBase();
        $unsubscribeUrl = $base . '/unsubscribe/' . $contact->unsubscribe_token;
        $trackingPixel  = '<img src="' . $base . '/open/' . $sendLog->open_token . '" width="1" height="1" alt="">';

        $html = str_replace('{courier_unsubscribe_url}', $unsubscribeUrl, $html);
        $html = str_replace('{courier_tracking_pixel}', $trackingPixel, $html);

        // Plain-text alt from the content-only view (no layout, no pixel, no link wrapping)
        $plainText = $this->templateService->renderText($campaign->view, $data);
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

            return true;
        }

        $this->sendModel->update($sendLog->id, ['status' => SendStatus::Failed]);

        return false;
    }

    /**
     * Rewrites all http/https href attributes through the click-tracking redirect,
     * excluding the {courier_unsubscribe_url} placeholder.
     */
    public function wrapLinks(string $html, string $clickToken): string
    {
        $base = $this->trackingBase();

        return (string) preg_replace_callback(
            '/href=(["\'])(https?:\/\/[^"\']+)\1/i',
            static function (array $m) use ($base, $clickToken): string {
                $quote       = $m[1];
                $originalUrl = $m[2];
                $redirect    = $base . '/click/' . $clickToken . '?url=' . urlencode($originalUrl);

                return 'href=' . $quote . $redirect . $quote;
            },
            $html,
        );
    }

    /**
     * Convenience wrapper for drip step sends.
     * Loads the campaign, creates the pending send record, then calls send().
     */
    public function sendStep(object $contact, object $dripStep): bool
    {
        $campaign = $this->campaignModel->find($dripStep->campaign_id);
        $sendLog  = $this->sendModel->createPending($contact->id, $campaign->id, $dripStep->id);

        return $this->send($contact, $campaign, $sendLog, $dripStep->subject);
    }
}
