<?php

declare(strict_types=1);

namespace Myth\Courier\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Enums\UnsubscribeResult;
use Myth\Courier\Models\EventModel;
use Myth\Courier\Models\LinkModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Webhooks\WebhookDriverInterface;

/**
 * Handles tracking pixels, click redirects, and unsubscribes.
 *
 * Register in the host application's app/Config/Routes.php:
 *
 *   $routes->group('courier', ['namespace' => 'Myth\Courier\Controllers'], static function ($routes): void {
 *       $routes->get('open/(:segment)',        'CourierController::open/$1');
 *       $routes->get('click/(:segment)',       'CourierController::click/$1');
 *       $routes->get('unsubscribe/(:segment)', 'CourierController::unsubscribe/$1');
 *   });
 */
class CourierController extends Controller
{
    /**
     * Returns a 1×1 transparent tracking GIF and records the open event.
     * Always returns the GIF — never 404 — so broken pixels stay silent.
     */
    public function open(string $token): ResponseInterface
    {
        $sendModel = model(SendModel::class);
        $send      = $sendModel->findByOpenToken($token);

        if ($send !== null) {
            if ($send->opened_at === null) {
                $sendModel->update($send->id, ['opened_at' => date('Y-m-d H:i:s')]);
            }

            model(EventModel::class)->insert(['send_id' => $send->id, 'type' => 'open']);
        }

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', 'image/gif')
            ->setHeader('Cache-Control', 'no-store')
            ->setHeader('Pragma', 'no-cache')
            ->setBody(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true));
    }

    /**
     * Looks up the destination URL from courier_links by token, records the click event,
     * and redirects. The URL is never supplied by the caller — it is stored server-side
     * at send time to prevent open-redirect phishing via token reuse.
     */
    public function click(string $token): ResponseInterface
    {
        $link = model(LinkModel::class)->findByToken($token);

        if ($link === null) {
            return redirect()->to('/');
        }

        $scheme = parse_url($link->url, PHP_URL_SCHEME);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return redirect()->to('/');
        }

        $sendModel = model(SendModel::class);
        $send      = $sendModel->find($link->send_id);

        if ($send !== null && $send->clicked_at === null) {
            $sendModel->update($send->id, ['clicked_at' => date('Y-m-d H:i:s')]);
        }

        $trackIp  = config(CourierConfig::class)->trackIpAddress;
        $metadata = $trackIp ? ['ip' => $this->request->getIPAddress()] : null;

        model(EventModel::class)->insert([
            'send_id'  => $link->send_id,
            'link_id'  => $link->id,
            'type'     => 'click',
            'metadata' => $metadata,
        ]);

        return redirect()->to($link->url);
    }

    /**
     * Handles form submissions from courier_form().
     * CSRF protection is applied automatically by CI4 for POST routes when the csrf filter is active.
     */
    public function capture(): ResponseInterface
    {
        helper('courier');

        return courier_capture($this->request);
    }

    /**
     * Receives SNS/SES webhook notifications (bounces, complaints, subscription confirmations).
     *
     * Requires a WebhookDriverInterface class configured in Config\Courier::$webhookDriver.
     * The host app must exempt POST /courier/webhook from CSRF in Config\Security::$CSRFExcludeURIs.
     */
    public function webhook(): ResponseInterface
    {
        $driverClass = config(CourierConfig::class)->webhookDriver;

        if ($driverClass === '' || ! class_exists($driverClass)) {
            return $this->response->setStatusCode(400)->setBody('Webhook driver not configured.');
        }

        /** @var WebhookDriverInterface $driver */
        $driver = new $driverClass();

        if (! $driver->verifySignature($this->request)) {
            return $this->response->setStatusCode(403)->setBody('Signature verification failed.');
        }

        if ($driver->isSubscriptionConfirmation($this->request)) {
            $driver->confirmSubscription($this->request);

            return $this->response->setStatusCode(200)->setBody('Confirmed.');
        }

        $eventModel     = model(EventModel::class);
        $contactService = service('contactService');

        foreach ($driver->parseEvents($this->request) as $event) {
            $email     = $event['email'];
            $type      = $event['type'];
            $messageId = $event['message_id'] ?? null;

            if ($type === 'bounce') {
                $contactService->suppress($email, ContactStatus::Bounced);
            } elseif ($type === 'complaint') {
                $contactService->suppress($email, ContactStatus::Complained);
            }

            $eventModel->insert([
                'type'     => $type,
                'metadata' => ['email' => $email, 'message_id' => $messageId],
            ]);
        }

        return $this->response->setStatusCode(200)->setBody('OK');
    }

    /**
     * Unsubscribes the contact identified by the token and renders a result view.
     */
    public function unsubscribe(string $token): ResponseInterface
    {
        $result = service('contactService')->unsubscribeByToken($token);

        return match ($result) {
            UnsubscribeResult::Success => $this->response
                ->setBody(view('\Myth\Courier\Views\courier/unsubscribe_success')),
            UnsubscribeResult::Expired => $this->response
                ->setStatusCode(410)
                ->setBody(view('\Myth\Courier\Views\courier/unsubscribe_expired')),
            UnsubscribeResult::NotFound => $this->response
                ->setStatusCode(404)
                ->setBody(view('\Myth\Courier\Views\courier/unsubscribe_invalid')),
        };
    }
}
