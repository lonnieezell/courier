<?php

declare(strict_types=1);

namespace Myth\Courier\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CaptureThrottleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $config = config('Courier');

        if ($config->captureRateLimit === 0) {
            return null;
        }

        $throttler = service('throttler');

        if (! $throttler->check('courier_capture_' . $request->getIPAddress(), $config->captureRateLimit, MINUTE)) {
            $isAjax = $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

            if ($isAjax) {
                return service('response')
                    ->setStatusCode(429)
                    ->setJSON(['success' => false, 'errors' => ['rate_limit' => 'Too many requests. Please try again later.']]);
            }

            return redirect()->back()->with('courier_errors', ['rate_limit' => 'Too many requests. Please try again later.']);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
