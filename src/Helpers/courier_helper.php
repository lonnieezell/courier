<?php

declare(strict_types=1);

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Myth\Courier\DTO\SendDTO;
use Myth\Courier\Exceptions\ContactAlreadySubscribedException;
use Myth\Courier\Exceptions\CourierValidationException;

if (! function_exists('courier_form')) {
    /**
     * Renders a complete self-contained HTML signup form.
     *
     * Usage:
     *   echo courier_form('homepage-signup', [
     *       'tags'     => ['newsletter'],
     *       'drip'     => 5,
     *       'redirect' => '/thank-you',
     *       'fields'   => ['first_name', 'last_name'],
     *       'button'   => 'Subscribe',
     *       'class'    => 'signup-form',
     *       'ajax'     => false,
     *   ]);
     *
     * @param array<string, mixed> $options
     */
    function courier_form(string $source, array $options = []): string
    {
        return courier_form_open($source, $options)
            . _courier_visible_fields($options)
            . '</form>';
    }
}

if (! function_exists('courier_form_open')) {
    /**
     * Returns the opening <form> tag and hidden fields only.
     * Use when embedding Courier capture inside your own form layout.
     *
     * @param array<string, mixed> $options
     */
    function courier_form_open(string $source, array $options = []): string
    {
        $tags     = $options['tags'] ?? [];
        $drip     = $options['drip'] ?? null;
        $redirect = $options['redirect'] ?? '/';
        $class    = $options['class'] ?? '';
        $ajax     = $options['ajax'] ?? false;

        $action    = base_url('courier/capture');
        $classAttr = $class !== '' ? ' class="' . esc($class, 'attr') . '"' : '';
        $ajaxAttr  = $ajax ? ' data-courier-ajax="1"' : '';

        $html = '<form action="' . $action . '" method="POST"' . $classAttr . $ajaxAttr . ">\n";
        $html .= csrf_field() . "\n";
        $html .= '<input type="hidden" name="courier_source" value="' . esc($source, 'attr') . '">' . "\n";

        if (config('Courier')->honeypot) {
            $html .= '<input type="text" name="courier_hp" style="display:none" tabindex="-1" autocomplete="off">' . "\n";
        }

        if ($tags !== []) {
            $html .= '<input type="hidden" name="courier_tags" value=\'' . json_encode($tags) . '\'>' . "\n";
        }

        if ($drip !== null) {
            $html .= '<input type="hidden" name="courier_drip_id" value="' . (int) $drip . '">' . "\n";
        }

        if (! $ajax) {
            $html .= '<input type="hidden" name="courier_redirect" value="' . esc($redirect, 'attr') . '">' . "\n";
        }

        return $html;
    }
}

if (! function_exists('courier_form_close')) {
    /**
     * Returns the closing </form> tag.
     */
    function courier_form_close(): string
    {
        return '</form>';
    }
}

if (! function_exists('courier_capture')) {
    /**
     * Backend helper: handles the full subscribe flow from a controller method.
     *
     * Usage:
     *   public function newsletter(): ResponseInterface
     *   {
     *       return courier_capture($this->request, [
     *           'tags'     => ['newsletter'],
     *           'drip'     => 5,
     *           'redirect' => '/thank-you',
     *       ]);
     *   }
     *
     * @param array<string, mixed> $defaults
     */
    function courier_capture(
        IncomingRequest $request,
        array $defaults = [],
    ): ResponseInterface {
        $email     = $request->getPost('email') ?? '';
        $firstName = $request->getPost('first_name') ?? '';
        $lastName  = $request->getPost('last_name') ?? '';
        $source    = $request->getPost('courier_source') ?? '';
        $tagsRaw   = $request->getPost('courier_tags') ?? '[]';
        $dripPost  = $request->getPost('courier_drip_id');
        $redirect  = $request->getPost('courier_redirect') ?? '/';

        $postTags = json_decode($tagsRaw, true);
        $postTags = is_array($postTags) ? $postTags : [];
        $postTags = array_values(array_filter(
            $postTags,
            static fn ($v): bool => is_string($v)
                && $v !== ''
                && strlen($v) <= 64
                && preg_match('/^[a-z0-9][a-z0-9\-_]*$/', $v) === 1,
        ));

        // $defaults take precedence for drip/redirect; tags are merged
        if (isset($defaults['source'])) {
            $source = $defaults['source'];
        }

        $drip = isset($defaults['drip']) ? (int) $defaults['drip'] : ($dripPost !== null ? (int) $dripPost : null);

        if (isset($defaults['redirect'])) {
            $redirect = $defaults['redirect'];
        }

        // Reject absolute URLs to prevent open redirect attacks
        if (str_starts_with($redirect, 'http://') || str_starts_with($redirect, 'https://')) {
            $redirect = '/';
        }

        $tags   = array_values(array_unique(array_merge($postTags, $defaults['tags'] ?? [])));
        $isAjax = ($defaults['ajax'] ?? false) || $request->isAJAX();

        if (config('Courier')->honeypot && ($request->getPost('courier_hp') ?? '') !== '') {
            return _courier_success_response($isAjax, $redirect);
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors = ['email' => 'A valid email is required.'];

            if ($isAjax) {
                return service('response')
                    ->setStatusCode(422)
                    ->setJSON(['success' => false, 'errors' => $errors]);
            }

            return redirect()->back()->withInput()->with('courier_errors', $errors);
        }

        $contactData = array_filter([
            'email'      => $email,
            'first_name' => $firstName ?: null,
            'last_name'  => $lastName ?: null,
            'source'     => $source ?: null,
        ]);

        try {
            service('contactService')->subscribe($contactData, $tags, $drip);
        } catch (ContactAlreadySubscribedException) {
            // Silent success — do not reveal whether the address is already subscribed
            return _courier_success_response($isAjax, $redirect);
        } catch (CourierValidationException $e) {
            $errors = ['email' => $e->getMessage()];

            if ($isAjax) {
                return service('response')
                    ->setStatusCode(422)
                    ->setJSON(['success' => false, 'errors' => $errors]);
            }

            return redirect()->back()->withInput()->with('courier_errors', $errors);
        }

        return _courier_success_response($isAjax, $redirect);
    }
}

if (! function_exists('courier_unsubscribe_url')) {
    /**
     * Returns the full unsubscribe URL for a send.
     * Uses the per-send token so the link expires after the configured number of days.
     */
    function courier_unsubscribe_url(SendDTO $send): string
    {
        $config = config('Courier');
        $base   = rtrim($config->trackingHost ?: base_url(), '/');

        return $base . '/courier/unsubscribe/' . $send->unsubscribe_token;
    }
}

if (! function_exists('_courier_success_response')) {
    /**
     * @internal
     */
    function _courier_success_response(bool $isAjax, string $redirect): ResponseInterface
    {
        if ($isAjax) {
            return service('response')
                ->setStatusCode(200)
                ->setJSON(['success' => true, 'message' => 'Subscribed successfully.']);
        }

        return redirect()->to($redirect);
    }
}

if (! function_exists('_courier_visible_fields')) {
    /**
     * @param array<string, mixed> $options
     *
     * @internal
     */
    function _courier_visible_fields(array $options): string
    {
        $fields = $options['fields'] ?? [];
        $button = $options['button'] ?? 'Subscribe';

        $html = '<input type="email" name="email" required placeholder="Your email">' . "\n";

        if (in_array('first_name', $fields, true)) {
            $html .= '<input type="text" name="first_name" placeholder="First name">' . "\n";
        }

        if (in_array('last_name', $fields, true)) {
            $html .= '<input type="text" name="last_name" placeholder="Last name">' . "\n";
        }

        return $html . ('<button type="submit">' . esc($button) . "</button>\n");
    }
}
