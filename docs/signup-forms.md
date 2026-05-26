# Signup Forms

Courier's helper layer makes wiring up signup forms a one-liner. Drop a form anywhere in your views, handle the submission with a single function call, or mix and match for full control over your markup.

## Loading the helper

Load it in any controller or view:

```php
<?php
helper('courier');
```

To load it automatically on every request, add it to your `Config/Autoload.php`:

```php
<?php
public $helpers = ['courier'];
```

---

## Drop-in form

`courier_form()` returns a complete, self-contained HTML form that posts to Courier's built-in capture endpoint:

```php
<?php
echo courier_form('homepage-signup');
```

That's all you need for a working email capture form. Add options to control behaviour:

```php
<?php
echo courier_form('homepage-signup', [
    'tags'     => ['newsletter'],       // apply these tags on subscribe
    'drip'     => 3,                    // enroll in drip campaign #3
    'redirect' => '/welcome',           // where to send the user on success
    'fields'   => ['first_name'],       // extra fields: 'first_name', 'last_name'
    'button'   => 'Join the list',      // submit button label
    'class'    => 'signup-form',        // CSS class on the <form> element
]);
```

The `$source` argument is a slug identifying where this form lives — `'homepage-signup'`, `'blog-sidebar'`, etc. It's stored on the contact record so you can trace where subscribers came from.

The generated HTML looks like this:

```html
<form action="/courier/capture" method="POST" class="signup-form">
  <input type="hidden" name="csrf_test_name" value="...">
  <input type="hidden" name="courier_source" value="homepage-signup">
  <input type="hidden" name="courier_tags" value='["newsletter"]'>
  <input type="hidden" name="courier_redirect" value="/welcome">
  <input type="text" name="courier_hp" style="display:none" tabindex="-1" autocomplete="off">
  <input type="email" name="email" required placeholder="Your email">
  <input type="text" name="first_name" placeholder="First name">
  <button type="submit">Join the list</button>
</form>
```

!!! note "CSRF protection"
    The form includes a CSRF token automatically via CI4's `csrf_field()`. Make sure the CI4 CSRF filter is active in your app — it's on by default for POST routes.

---

## Custom form layout

If you need to embed the capture fields inside your own existing form, use `courier_form_open()` and `courier_form_close()` instead:

```php
<?php
echo courier_form_open('footer-cta', [
    'tags'     => ['newsletter'],
    'redirect' => '/subscribed',
]);
?>

<div class="form-row">
    <input type="email" name="email" required placeholder="Your email address">
    <button type="submit">Subscribe</button>
</div>

<?php echo courier_form_close(); ?>
```

`courier_form_open()` outputs the `<form>` tag, CSRF field, and all the hidden Courier fields. `courier_form_close()` outputs `</form>`. Everything in between is yours.

---

## Handling the submission yourself

If you'd rather own the controller method — to add custom logic, extra validation, or a different redirect — use `courier_capture()`:

```php
<?php

use CodeIgniter\HTTP\ResponseInterface;

class MarketingController extends BaseController
{
    public function newsletter(): ResponseInterface
    {
        helper('courier');

        return courier_capture($this->request, [
            'tags'     => ['newsletter'],
            'drip'     => 5,
            'redirect' => '/thank-you',
        ]);
    }
}
```

`courier_capture()` reads the POST body, validates the email, subscribes the contact, and returns a ready-made redirect or JSON response. The `$defaults` array lets you lock in values server-side so POST data can't override them:

| Key | Type | Description |
|-----|------|-------------|
| `tags` | `array` | Merged with any tags from POST |
| `drip` | `int` | Overrides the POST `courier_drip_id` value |
| `redirect` | `string` | Overrides the POST `courier_redirect` value |
| `source` | `string` | Overrides the POST `courier_source` value |
| `ajax` | `bool` | Forces JSON responses regardless of the request type |

Your controller's route doesn't need to be `POST /courier/capture` — point `courier_form()` at your own endpoint by constructing the form manually with `courier_form_open()`, or just use the built-in capture endpoint and skip the custom controller entirely.

### Error handling

On validation failure, `courier_capture()` returns automatically:

- **Non-AJAX:** `redirect()->back()->withInput()->with('courier_errors', $errors)`
- **AJAX:** `{"success": false, "errors": {"email": "A valid email is required."}}` with HTTP 422

In your view, render errors like this:

```php
<?php if ($courierErrors = session('courier_errors')): ?>
    <p class="error"><?= esc($courierErrors['email']) ?></p>
<?php endif ?>
```

---

## AJAX / fetch() forms

Set `ajax => true` to get JSON responses instead of redirects. The `courier_redirect` hidden field is also omitted from the form:

```php
<?php
echo courier_form('popup-signup', [
    'tags' => ['trial'],
    'ajax' => true,
]);
```

Pair it with a small fetch handler:

```js
document.querySelector('[data-courier-ajax]').addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await fetch(e.target.action, {
        method: 'POST',
        body: new FormData(e.target),
    });
    const data = await res.json();
    if (data.success) {
        // show success message
    } else {
        // show data.errors.email
    }
});
```

Courier detects AJAX requests via the `X-Requested-With: XMLHttpRequest` header. If you're using `axios`, set the header once globally and the `ajax` flag becomes optional:

```js
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

Success response:

```json
{"success": true, "message": "Subscribed successfully."}
```

Validation error response (HTTP 422):

```json
{"success": false, "errors": {"email": "A valid email is required."}}
```

---

## Unsubscribe links

Email layouts receive `$unsubscribeUrl` automatically. If you need the URL somewhere else — a custom template or a transactional email — use `courier_unsubscribe_url()` with the send record:

```php
<?php
// $send is a SendDTO returned by SendModel::createPending()
$url = courier_unsubscribe_url($send);
// https://yoursite.com/courier/unsubscribe/abc123token
```

The base URL comes from `$trackingHost` in your Courier config, falling back to CI4's `base_url()`. The token in the URL is per-send and expires after `$unsubscribeTokenExpireDays` days.

---

## Security

Courier validates and sanitises the data it receives before acting on it. A few things worth knowing:

**Redirect safety.** The `courier_redirect` field in POST data is restricted to relative paths. If an absolute URL (`https://...`) is submitted — whether by a tampered form or a crafted POST request — Courier replaces it with `/`. Values passed via `$defaults['redirect']` in your own controller are subject to the same check.

**Tag slugs.** Tags submitted via `courier_tags` are validated before reaching the database. Only lowercase alphanumeric slugs (with `-` and `_` separators, up to 64 characters) are accepted. Anything else is silently dropped. Valid format: `newsletter`, `beta-users`, `plan_pro`.

**Re-subscribe behaviour.** If a contact previously unsubscribed, submitting their email re-subscribes them silently. Contacts with `bounced` or `complained` status cannot be re-subscribed; `courier_capture()` returns a validation error in that case.

**Email enumeration.** Submitting an email address that's already subscribed returns the same success response as a new subscription — no error, no distinction. This prevents attackers from probing your contact list by watching for `422` vs `200` responses.

### Rate limiting

The `/courier/capture` endpoint is rate-limited to **15 requests per IP per minute** by default. When the limit is exceeded:

- **Standard form submission** — redirects back with a `courier_errors` flash message containing a `rate_limit` key
- **AJAX request** — returns HTTP `429` with `{"success": false, "errors": {"rate_limit": "Too many requests..."}}`

Render the rate-limit error the same way you'd render any other capture error:

```php
<?php if ($courierErrors = session('courier_errors')): ?>
    <?php if (isset($courierErrors['rate_limit'])): ?>
        <p class="error"><?= esc($courierErrors['rate_limit']) ?></p>
    <?php endif ?>
<?php endif ?>
```

Tune or disable the limit in your `Config/Courier.php`:

```php
<?php
public int $captureRateLimit = 30; // raise the limit
public int $captureRateLimit = 0;  // disable entirely
```

See [`$captureRateLimit`](configuration.md#captureRateLimit) in the configuration reference.

### Bot detection (honeypot)

Every form generated by `courier_form()` or `courier_form_open()` includes a hidden `courier_hp` field that real users never see. If a bot fills it in, Courier returns a silent success response without saving anything — the bot gets no signal that it was detected.

The honeypot is on by default. To disable it:

```php
<?php
public bool $honeypot = false;
```

!!! note
    If you use `courier_form_open()` to embed Courier fields inside your own form markup, the honeypot field is rendered automatically — you don't need to add it yourself. If you're posting to `/courier/capture` with a fully hand-rolled form, just make sure you don't submit a `courier_hp` field, or disable the honeypot in config.

---

## Next steps

- [Contacts](contacts.md) — how `ContactService` manages the full contact lifecycle
- [Drip Sequences](drip-sequences.md) — setting up the drip campaigns you pass to `drip`
- [Tracking](tracking.md) — how the built-in capture endpoint fits into Courier's route group
