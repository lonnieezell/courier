<?php

declare(strict_types=1);

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
        foreach ($slugs as $slug) {
            $tag = $this->tagModel->where('slug', $slug)->first();

            if ($tag === null) {
                $label = ucwords(str_replace(['-', '_'], ' ', $slug));
                $id    = $this->tagModel->insert(['slug' => $slug, 'label' => $label]);
                $tag   = $this->tagModel->find($id);
            }

            $exists = $this->contactTagModel
                ->where('contact_id', $contactId)
                ->where('tag_id', $tag->id)
                ->countAllResults();

            if ($exists === 0) {
                $this->contactTagModel->insert([
                    'contact_id' => $contactId,
                    'tag_id'     => $tag->id,
                ]);
            }
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
        foreach ($slugs as $slug) {
            $tag = $this->tagModel->where('slug', $slug)->first();

            if ($tag === null) {
                continue;
            }

            $this->contactTagModel
                ->where('contact_id', $contactId)
                ->where('tag_id', $tag->id)
                ->delete();
        }
    }

    public function getContact(string $email): ?object
    {
        return $this->contactModel->where('email', $email)->first();
    }
}
