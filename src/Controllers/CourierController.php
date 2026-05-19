<?php

declare(strict_types=1);

namespace Myth\Courier\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Myth\Courier\Models\EventModel;
use Myth\Courier\Models\SendModel;

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
     * Records the click event and redirects to the target URL.
     * Rejects non-http(s) URLs to prevent open redirects to dangerous schemes.
     */
    public function click(string $token): ResponseInterface
    {
        $sendModel = model(SendModel::class);
        $send      = $sendModel->findByClickToken($token);

        if ($send === null) {
            return redirect()->to('/');
        }

        $url = urldecode($this->request->getGet('url') ?? '');

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return redirect()->to('/');
        }

        if ($send->clicked_at === null) {
            $sendModel->update($send->id, ['clicked_at' => date('Y-m-d H:i:s')]);
        }

        model(EventModel::class)->insert([
            'send_id'  => $send->id,
            'type'     => 'click',
            'metadata' => json_encode(['url' => $url, 'ip' => $this->request->getIPAddress()]),
        ]);

        return redirect()->to($url);
    }

    /**
     * Unsubscribes the contact identified by the token and renders a result view.
     */
    public function unsubscribe(string $token): ResponseInterface
    {
        $ok = service('contactService')->unsubscribeByToken($token);

        if ($ok) {
            return $this->response->setBody(view('\Myth\Courier\Views\courier/unsubscribe_success'));
        }

        return $this->response
            ->setStatusCode(404)
            ->setBody(view('\Myth\Courier\Views\courier/unsubscribe_invalid'));
    }
}
