<?php

declare(strict_types=1);

namespace Myth\Courier\Config;

use CodeIgniter\Config\BaseService;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\ContactTagModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\SegmentModel;
use Myth\Courier\Models\SendModel;
use Myth\Courier\Models\TagModel;
use Myth\Courier\Services\CampaignService;
use Myth\Courier\Services\ContactService;
use Myth\Courier\Services\DripService;
use Myth\Courier\Services\MailerService;
use Myth\Courier\Services\SegmentService;
use Myth\Courier\Services\TemplateService;

class Services extends BaseService
{
    /**
     * Manages the contact lifecycle: subscribe, unsubscribe, tag application, and re-subscription.
     * Also wires DripService so that unsubscribing a contact automatically cancels active drip enrollments.
     */
    public static function contactService(bool $getShared = true): ContactService
    {
        if ($getShared) {
            return static::getSharedInstance('contactService');
        }

        $service = new ContactService(
            model(ContactModel::class),
            model(TagModel::class),
            model(DripEnrollmentModel::class),
            model(ContactTagModel::class),
        );
        $service->setDripService(static::dripService(false));

        return $service;
    }

    /**
     * Resolves audience segments into contact lists.
     * Use to target campaigns by saved segment rules or tag combinations.
     */
    public static function segmentService(bool $getShared = true): SegmentService
    {
        if ($getShared) {
            return static::getSharedInstance('segmentService');
        }

        return new SegmentService(
            model(ContactModel::class),
            model(SegmentModel::class),
        );
    }

    /**
     * Renders email body views and layouts using CI4's view() system.
     * Used internally by MailerService; inject directly when previewing templates.
     */
    public static function templateService(bool $getShared = true): TemplateService
    {
        if ($getShared) {
            return static::getSharedInstance('templateService');
        }

        return new TemplateService();
    }

    /**
     * Sends individual emails, wraps links with click-tracking tokens, and logs delivery status.
     * Used internally by CampaignService and DripService; inject directly for one-off sends.
     */
    public static function mailerService(bool $getShared = true): MailerService
    {
        if ($getShared) {
            return static::getSharedInstance('mailerService');
        }

        return new MailerService(
            static::templateService(),
            model(SendModel::class),
            model(CampaignModel::class),
        );
    }

    /**
     * Creates and schedules blast campaigns, resolves their audience, and sends batches of emails.
     * Use to create campaigns, schedule sends, and retrieve per-campaign delivery statistics.
     */
    public static function campaignService(bool $getShared = true): CampaignService
    {
        if ($getShared) {
            return static::getSharedInstance('campaignService');
        }

        return new CampaignService(
            model(CampaignModel::class),
            model(DripStepModel::class),
            static::segmentService(),
            static::mailerService(),
            model(SendModel::class),
            model(ContactModel::class),
            config(CourierConfig::class),
        );
    }

    /**
     * Manages drip sequence enrollments: enroll contacts, process due steps, and cancel enrollments.
     * The courier:process-drips spark command calls processDue() on this service each cron tick.
     */
    public static function dripService(bool $getShared = true): DripService
    {
        if ($getShared) {
            return static::getSharedInstance('dripService');
        }

        return new DripService(
            model(DripEnrollmentModel::class),
            model(DripStepModel::class),
            model(CampaignModel::class),
            static::mailerService(),
            model(ContactModel::class),
            config(CourierConfig::class),
        );
    }
}
