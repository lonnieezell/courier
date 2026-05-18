<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\CampaignModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 2,
	'path' => __DIR__ . '/src/Config/Services.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\ContactModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 2,
	'path' => __DIR__ . '/src/Config/Services.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\ContactTagModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 1,
	'path' => __DIR__ . '/src/Config/Services.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\DripEnrollmentModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 1,
	'path' => __DIR__ . '/src/Config/Services.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\DripStepModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 1,
	'path' => __DIR__ . '/src/Config/Services.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\SegmentModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 1,
	'path' => __DIR__ . '/src/Config/Services.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\SendModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 2,
	'path' => __DIR__ . '/src/Config/Services.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\TagModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 1,
	'path' => __DIR__ . '/src/Config/Services.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to an undefined method CodeIgniter\\Database\\ConnectionInterface::fieldExists().',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/src/Database/Migrations/2026-05-18-120010_AddViewToCourierCampaigns.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function config with Myth\\Courier\\Config\\Courier::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/MailerService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to protected method getMessageID() of class CodeIgniter\\Email\\Email.',
	'identifier' => 'method.protected',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/MailerService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot access property $id on list<stdClass>.',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/MailerService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #2 $campaign of method Myth\\Courier\\Services\\MailerService::send() expects object, list<stdClass> given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/MailerService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Short ternary operator is not allowed. Use null coalesce operator if applicable or consider using long ternary.',
	'identifier' => 'ternary.shortNotAllowed',
	'count' => 3,
	'path' => __DIR__ . '/src/Services/MailerService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Variable $content might not be defined.',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/src/Views/courier/layouts/default.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Variable $content might not be defined.',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/src/Views/courier/layouts/plain.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Variable $content might not be defined.',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/src/Views/tests/test_layout.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $row of method CodeIgniter\\Model::insert() expects array<int|string, bool|float|int|object|string|null>|object|null, array<string, array|string> given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/tests/Services/Courier/CampaignServiceTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot access property $sent_at on list<stdClass>.',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/tests/Services/Courier/MailerServiceTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot access property $status on list<stdClass>.',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/tests/Services/Courier/MailerServiceTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Variable $content might not be defined.',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/tests/_support/Views/test_layout.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
