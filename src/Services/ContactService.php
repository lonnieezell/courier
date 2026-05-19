<?php

declare(strict_types=1);

/**
 * This file is part of YourVendor/YourPackage.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Myth\Courier\Services;

use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Enums\EnrollmentStatus;
use Myth\Courier\Exceptions\CourierValidationException;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\ContactTagModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\TagModel;

class ContactService
{
    private ?DripServiceInterface $dripService = null;

    public function __construct(
        private readonly ContactModel $contactModel,
        private readonly TagModel $tagModel,
        private readonly DripEnrollmentModel $enrollmentModel,
        private readonly ContactTagModel $contactTagModel,
    ) {
    }

    public function setDripService(DripServiceInterface $dripService): void
    {
        $this->dripService = $dripService;
    }

    /**
     * Subscribes a contact. Creates a new row or re-subscribes an unsubscribed
     * contact. Throws CourierValidationException for missing email or contacts
     * in bounced/complained status.
     *
     * @param array<string, mixed> $data
     * @param list<string>         $tags
     */
    public function subscribe(array $data, array $tags = [], ?int $dripCampaignId = null): object
    {
        if (! isset($data['email']) || $data['email'] === '') {
            throw new CourierValidationException('The email field is required.');
        }

        $contact = $this->contactModel->where('email', $data['email'])->first();

        if ($contact !== null) {
            if ($contact->status === ContactStatus::Unsubscribed) {
                $this->contactModel->update($contact->id, [
                    'status'          => ContactStatus::Subscribed,
                    'subscribed_at'   => date('Y-m-d H:i:s'),
                    'unsubscribed_at' => null,
                ]);
                $contact = $this->contactModel->find($contact->id);
            } elseif ($contact->status === ContactStatus::Bounced || $contact->status === ContactStatus::Complained) {
                throw new CourierValidationException(
                    "Contact cannot be re-subscribed: status is {$contact->status->value}",
                );
            }
        } else {
            $insertData = array_merge($data, ['subscribed_at' => date('Y-m-d H:i:s')]);
            $id         = $this->contactModel->insert($insertData);
            $contact    = $this->contactModel->find($id);
        }

        if ($tags !== []) {
            $this->applyTags($contact->id, $tags);
        }

        if ($dripCampaignId !== null && $this->dripService !== null) {
            $this->dripService->enroll($contact->id, $dripCampaignId);
        }

        return $contact;
    }

    /**
     * Unsubscribes a contact by their unique token.
     * Also cancels all active drip enrollments.
     */
    public function unsubscribeByToken(string $token): bool
    {
        $contact = $this->contactModel->where('unsubscribe_token', $token)->first();

        if ($contact === null) {
            return false;
        }

        $this->contactModel->update($contact->id, [
            'status'          => ContactStatus::Unsubscribed,
            'unsubscribed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->enrollmentModel
            ->where('contact_id', $contact->id)
            ->where('status', EnrollmentStatus::Active->value)
            ->set('status', EnrollmentStatus::Cancelled)
            ->update();

        return true;
    }

    /**
     * Finds or creates tags by slug and links them to the contact.
     * Silently ignores slugs already linked.
     *
     * @param list<string> $slugs
     */
    public function applyTags(int $contactId, array $slugs): void
    {
        if ($slugs === []) {
            return;
        }

        // Fetch all matching tags in one query
        $found      = $this->tagModel->whereIn('slug', $slugs)->findAll();
        $tagsBySlug = array_combine(array_column($found, 'slug'), $found);

        // Insert any tags that don't exist yet
        foreach ($slugs as $slug) {
            if (! isset($tagsBySlug[$slug])) {
                $label             = ucwords(str_replace(['-', '_'], ' ', $slug));
                $id                = $this->tagModel->insert(['slug' => $slug, 'label' => $label]);
                $tagsBySlug[$slug] = $this->tagModel->find($id);
            }
        }

        // Determine which associations already exist (one query)
        $allTagIds = array_map(static fn (object $t): int => $t->id, $tagsBySlug);
        $linked    = $this->contactTagModel
            ->select('tag_id')
            ->where('contact_id', $contactId)
            ->whereIn('tag_id', $allTagIds)
            ->findAll();
        $linkedTagIds = array_column($linked, 'tag_id');

        // Batch-insert only the missing associations
        $toInsert = [];

        foreach ($tagsBySlug as $tag) {
            if (! in_array($tag->id, $linkedTagIds, true)) {
                $toInsert[] = ['contact_id' => $contactId, 'tag_id' => $tag->id];
            }
        }

        if ($toInsert !== []) {
            $this->contactTagModel->insertBatch($toInsert);
        }
    }

    /**
     * Removes tag associations from a contact by slug.
     * Tags that don't exist or aren't applied are silently ignored.
     *
     * @param list<string> $slugs
     */
    public function removeTags(int $contactId, array $slugs): void
    {
        if ($slugs === []) {
            return;
        }

        $tags = $this->tagModel->whereIn('slug', $slugs)->findAll();

        if ($tags === []) {
            return;
        }

        $tagIds = array_column($tags, 'id');

        $this->contactTagModel
            ->where('contact_id', $contactId)
            ->whereIn('tag_id', $tagIds)
            ->delete();
    }

    public function getContact(string $email): ?object
    {
        return $this->contactModel->where('email', $email)->first();
    }
}
