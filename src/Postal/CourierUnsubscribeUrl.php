<?php

declare(strict_types=1);

namespace Myth\Courier\Postal;

use Myth\Courier\Models\SendModel;
use Myth\Postal\Address;
use Myth\Postal\UnsubscribeUrlInterface;

/**
 * Resolves the per-recipient unsubscribe URL postal injects into the
 * List-Unsubscribe header. Bound into postal via Config\Mailer::$unsubscribeUrl.
 */
class CourierUnsubscribeUrl implements UnsubscribeUrlInterface
{
    public function __construct(private readonly SendModel $sends = new SendModel())
    {
    }

    public function urlFor(Address $recipient): string
    {
        $send = $this->sends->findLatestPendingByEmail($recipient->email);

        if ($send === null) {
            return '';
        }

        helper('courier');

        return courier_unsubscribe_url($send);
    }

    /**
     * Courier always supports RFC 8058 one-click unsubscribe.
     */
    public function isOneClick(): bool
    {
        return true;
    }
}
