<?php

declare(strict_types=1);

namespace Tests\Controllers\Courier;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\Models\ContactModel;
use ReflectionObject;

/**
 * @internal
 */
final class UnsubscribePostRouteTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $refresh   = true;
    protected $namespace = 'Myth\Courier';

    public function testRoutesRegisterPostUnsubscribe(): void
    {
        $routes = Services::routes(false);
        require dirname(__DIR__, 3) . '/src/Config/Routes.php';

        // Read the raw verb buckets directly: getRoutes() lazily reloads the
        // app's default routes file, which discards this isolated collection.
        $property = (new ReflectionObject($routes))->getProperty('routes');
        $property->setAccessible(true);
        $postRoutes = $property->getValue($routes)['POST'] ?? [];

        $matched = false;

        foreach (array_keys($postRoutes) as $pattern) {
            if (str_contains($pattern, 'courier/unsubscribe')) {
                $matched = true;
                break;
            }
        }

        $this->assertTrue($matched, 'Expected a POST courier/unsubscribe route to be registered.');
    }

    public function testPostUnsubscribeMarksContactUnsubscribed(): void
    {
        config(CourierConfig::class)->testMode = true;

        $contactModel = new ContactModel();
        $contactId    = (int) $contactModel->insert(['email' => 'oneclick@example.com']);
        $contact      = $contactModel->find($contactId);

        $routes = [
            ['POST', 'courier/unsubscribe/(:segment)', '\Myth\Courier\Controllers\CourierController::unsubscribe/$1'],
        ];

        $result = $this->withRoutes($routes)->post('courier/unsubscribe/' . $contact->unsubscribe_token);

        $result->assertStatus(200);
        $this->assertSame('unsubscribed', $contactModel->find($contactId)->status->value);
    }
}
