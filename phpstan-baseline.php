<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'rawMessage' => 'PHPDoc tag @var with type Myth\\Courier\\Services\\DripServiceInterface is not subtype of type Myth\\Courier\\Services\\DripService.',
	'identifier' => 'varTag.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Commands/ProcessDrips.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\CampaignModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 1,
	'path' => __DIR__ . '/src/Commands/SendCampaign.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $campaign of method Myth\\Courier\\Services\\CampaignService::prepareBatch() expects Myth\\Courier\\DTO\\CampaignDTO, stdClass given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Commands/SendCampaign.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $campaign of method Myth\\Courier\\Services\\CampaignService::resolveAudienceChunked() expects Myth\\Courier\\DTO\\CampaignDTO, stdClass given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Commands/SendCampaign.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function config with Myth\\Courier\\Config\\Courier::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 5,
	'path' => __DIR__ . '/src/Config/Services.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\CampaignModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 3,
	'path' => __DIR__ . '/src/Config/Services.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\ContactModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 4,
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
	'count' => 2,
	'path' => __DIR__ . '/src/Config/Services.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\DripStepModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 2,
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
	'rawMessage' => 'Call to function config with Myth\\Courier\\Config\\Courier::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 2,
	'path' => __DIR__ . '/src/Controllers/CourierController.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\EventModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 3,
	'path' => __DIR__ . '/src/Controllers/CourierController.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\LinkModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 1,
	'path' => __DIR__ . '/src/Controllers/CourierController.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\SendModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 2,
	'path' => __DIR__ . '/src/Controllers/CourierController.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $row of method CodeIgniter\\Model::insert() expects array<int|string, bool|float|int|object|string|null>|object|null, array<string, array<string, string>|int|string|null> given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Controllers/CourierController.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $row of method CodeIgniter\\Model::insert() expects array<int|string, bool|float|int|object|string|null>|object|null, array<string, array<string, string|null>|string> given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Controllers/CourierController.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Unsafe usage of new static() in abstract class Myth\\Courier\\DTO\\BaseDTO in static method fromObject().',
	'identifier' => 'new.staticInAbstractClassStaticMethod',
	'count' => 1,
	'path' => __DIR__ . '/src/DTO/BaseDTO.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Unsafe usage of new static().',
	'identifier' => 'new.static',
	'count' => 1,
	'path' => __DIR__ . '/src/DTO/BaseDTO.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to an undefined method CodeIgniter\\Database\\ConnectionInterface::fieldExists().',
	'identifier' => 'method.notFound',
	'count' => 1,
	'path' => __DIR__ . '/src/Database/Migrations/2026-05-18-120010_AddViewToCourierCampaigns.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Access to an undefined property CodeIgniter\\Database\\ConnectionInterface::$DBDriver.',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/src/Database/Migrations/2026-05-22-120001_AlterCourierSendsDropClickToken.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Access to an undefined property CodeIgniter\\Database\\ConnectionInterface::$DBPrefix.',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/src/Database/Migrations/2026-05-22-120001_AlterCourierSendsDropClickToken.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Access to an undefined property CodeIgniter\\Database\\ConnectionInterface::$DBDriver.',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/src/Database/Migrations/2026-05-22-120003_AlterCourierEventsAddLinkId.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Access to an undefined property CodeIgniter\\Database\\ConnectionInterface::$DBDriver.',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/src/Database/Migrations/2026-05-22-120004_AlterCourierEventsNullableSendId.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Access to an undefined property CodeIgniter\\Database\\ConnectionInterface::$DBDriver.',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/src/Database/Migrations/2026-05-26-120004_AlterCourierDripStepsPositionToSmallint.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Access to an undefined property CodeIgniter\\Database\\ConnectionInterface::$DBDriver.',
	'identifier' => 'property.notFound',
	'count' => 1,
	'path' => __DIR__ . '/src/Database/Migrations/2026-05-26-120005_AlterCourierSendsAddUnsubscribeToken.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Access to an undefined property CodeIgniter\\Database\\ConnectionInterface::$DBPrefix.',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/src/Database/Migrations/2026-05-26-120005_AlterCourierSendsAddUnsubscribeToken.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to an undefined method CodeIgniter\\Database\\ConnectionInterface::fieldExists().',
	'identifier' => 'method.notFound',
	'count' => 4,
	'path' => __DIR__ . '/src/Database/Migrations/2026-06-29-120001_AddMailableToCampaignsAndDripSteps.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Access to an undefined property CodeIgniter\\Database\\ConnectionInterface::$DBDriver.',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/src/Database/Migrations/2026-06-29-120002_AlterCourierSendsCampaignIdNullable.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function config with Myth\\Courier\\Config\\Courier::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 2,
	'path' => __DIR__ . '/src/Helpers/courier_helper.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to internal function _courier_success_response().',
	'identifier' => 'function.internal',
	'count' => 3,
	'path' => __DIR__ . '/src/Helpers/courier_helper.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to internal function _courier_visible_fields().',
	'identifier' => 'function.internal',
	'count' => 1,
	'path' => __DIR__ . '/src/Helpers/courier_helper.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Only booleans are allowed in a negated boolean, mixed given.',
	'identifier' => 'booleanNot.exprNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Helpers/courier_helper.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Only booleans are allowed in a ternary operator condition, mixed given.',
	'identifier' => 'ternary.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Helpers/courier_helper.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Only booleans are allowed in ||, mixed given on the left side.',
	'identifier' => 'booleanOr.leftNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Helpers/courier_helper.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Short ternary operator is not allowed. Use null coalesce operator if applicable or consider using long ternary.',
	'identifier' => 'ternary.shortNotAllowed',
	'count' => 4,
	'path' => __DIR__ . '/src/Helpers/courier_helper.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Only booleans are allowed in an if condition, mixed given.',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/CampaignModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Only booleans are allowed in an if condition, mixed given.',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/ContactModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\DripStepModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/DripEnrollmentModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Only booleans are allowed in an if condition, mixed given.',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/DripEnrollmentModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Models\\DripEnrollmentModel::claimDue() should return list<Myth\\Courier\\DTO\\DripEnrollmentDTO> but returns list<object{id: int, contact_id: int, campaign_id: int, current_step: int, next_send_at: string|null, status: Myth\\Courier\\Enums\\EnrollmentStatus, created_at: string|null, updated_at: string|null, retry_count: int, locked_at: string|null}>.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/DripEnrollmentModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Only booleans are allowed in an if condition, mixed given.',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/DripStepModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Models\\LinkModel::findByToken() should return Myth\\Courier\\DTO\\LinkDTO|null but returns stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/LinkModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Only booleans are allowed in an if condition, mixed given.',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/LinkModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Only booleans are allowed in an if condition, mixed given.',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/SegmentModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function config with Myth\\Courier\\Config\\Courier::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/SendModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Models\\SendModel::createPending() should return Myth\\Courier\\DTO\\SendDTO but returns list<stdClass>|stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/SendModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Models\\SendModel::findByOpenToken() should return Myth\\Courier\\DTO\\SendDTO|null but returns stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/SendModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Models\\SendModel::findByUnsubscribeToken() should return Myth\\Courier\\DTO\\SendDTO|null but returns stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/SendModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Models\\SendModel::findLatestPendingByEmail() should return Myth\\Courier\\DTO\\SendDTO|null but returns stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/SendModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Only booleans are allowed in an if condition, mixed given.',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/SendModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Only booleans are allowed in an if condition, mixed given.',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/TagModel.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Services\\CampaignService::addDripStep() should return Myth\\Courier\\DTO\\DripStepDTO but returns stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/CampaignService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Services\\CampaignService::create() should return Myth\\Courier\\DTO\\CampaignDTO but returns stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/CampaignService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $contact of method Myth\\Courier\\Services\\MailerService::send() expects Myth\\Courier\\DTO\\ContactDTO, stdClass given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/CampaignService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #2 $campaign of method Myth\\Courier\\Services\\MailerService::send() expects Myth\\Courier\\DTO\\CampaignDTO, stdClass|null given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/CampaignService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to function model with Myth\\Courier\\Models\\CampaignModel::class is discouraged.',
	'identifier' => 'codeigniter.factoriesClassConstFetch',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/ContactService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Services\\ContactService::getContact() should return Myth\\Courier\\DTO\\ContactDTO|null but returns stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/ContactService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Services\\ContactService::subscribe() should return Myth\\Courier\\DTO\\ContactDTO but returns list<stdClass>|stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/ContactService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Property Myth\\Courier\\Services\\ContactService::$enrollmentModel is never read, only written.',
	'identifier' => 'property.onlyWritten',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/ContactService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot call method isFileBased() on stdClass.',
	'identifier' => 'method.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/src/Services/DripService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Services\\DripService::enroll() should return Myth\\Courier\\DTO\\DripEnrollmentDTO|null but returns list<stdClass>|stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/DripService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Services\\DripService::getEnrollmentStatus() should return Myth\\Courier\\DTO\\DripEnrollmentDTO|null but returns stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/DripService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'PHPDoc tag @var with type object is not subtype of type stdClass|null.',
	'identifier' => 'varTag.type',
	'count' => 2,
	'path' => __DIR__ . '/src/Services/DripService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $contact of method Myth\\Courier\\Services\\MailerService::sendStep() expects Myth\\Courier\\DTO\\ContactDTO, stdClass given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/DripService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $enrollment of method Myth\\Courier\\Models\\DripEnrollmentModel::advance() expects Myth\\Courier\\DTO\\DripEnrollmentDTO, stdClass given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/DripService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $enrollment of method Myth\\Courier\\Models\\DripEnrollmentModel::recordFailure() expects Myth\\Courier\\DTO\\DripEnrollmentDTO, stdClass given.',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/src/Services/DripService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #3 $campaign of method Myth\\Courier\\Services\\MailerService::sendStep() expects Myth\\Courier\\DTO\\CampaignDTO|null, stdClass|null given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/DripService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Strict comparison using === between object and null will always evaluate to false.',
	'identifier' => 'identical.alwaysFalse',
	'count' => 2,
	'path' => __DIR__ . '/src/Services/DripService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Short ternary operator is not allowed. Use null coalesce operator if applicable or consider using long ternary.',
	'identifier' => 'ternary.shortNotAllowed',
	'count' => 3,
	'path' => __DIR__ . '/src/Services/MailerService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Generator expects value type list<Myth\\Courier\\DTO\\ContactDTO>, non-empty-list<stdClass> given.',
	'identifier' => 'generator.valueType',
	'count' => 3,
	'path' => __DIR__ . '/src/Services/SegmentService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Services\\SegmentService::resolve() should return list<Myth\\Courier\\DTO\\ContactDTO> but returns list<stdClass>.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/SegmentService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Services\\SegmentService::resolveBySegmentAndTagSlugs() should return list<Myth\\Courier\\DTO\\ContactDTO> but returns list<stdClass>.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/SegmentService.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Myth\\Courier\\Services\\SegmentService::resolveByTagSlugs() should return list<Myth\\Courier\\DTO\\ContactDTO> but returns list<stdClass>.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/SegmentService.php',
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
	'rawMessage' => 'Variable $content might not be defined.',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/src/Views/tests/test_data_layout.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Variable $content might not be defined.',
	'identifier' => 'variable.undefined',
	'count' => 1,
	'path' => __DIR__ . '/src/Views/tests/test_styled_layout.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to method PHPUnit\\Framework\\Assert::assertTrue() with true will always evaluate to true.',
	'identifier' => 'method.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/tests/Commands/Courier/ProcessDripsTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to method PHPUnit\\Framework\\Assert::assertSame() with \'suppressed\' and \'suppressed\' will always evaluate to true.',
	'identifier' => 'method.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/tests/Enums/SendStatusTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to method PHPUnit\\Framework\\Assert::assertSame() with Myth\\Courier\\Enums\\SendStatus::Suppressed and Myth\\Courier\\Enums\\SendStatus::Suppressed will always evaluate to true.',
	'identifier' => 'method.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/tests/Enums/SendStatusTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot access property $id on list<stdClass>.',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/tests/Integration/EndToEndTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot access property $status on list<stdClass>.',
	'identifier' => 'property.nonObject',
	'count' => 2,
	'path' => __DIR__ . '/tests/Integration/EndToEndTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Method Tests\\Integration\\EndToEndTest::makeDripCampaign() should return Myth\\Courier\\DTO\\CampaignDTO but returns list<stdClass>|stdClass|null.',
	'identifier' => 'return.type',
	'count' => 1,
	'path' => __DIR__ . '/tests/Integration/EndToEndTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to method PHPUnit\\Framework\\Assert::assertInstanceOf() with \'Myth\\\\Postal\\\\Email\' and Myth\\Postal\\Email will always evaluate to true.',
	'identifier' => 'method.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/tests/Mailables/CourierMailableTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to method PHPUnit\\Framework\\Assert::assertInstanceOf() with \'Myth\\\\Postal\\\\Mailable\' and Myth\\Courier\\Mailables\\CourierMailable will always evaluate to true.',
	'identifier' => 'method.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/tests/Mailables/CourierMailableTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $enrollment of method Myth\\Courier\\Models\\DripEnrollmentModel::advance() expects Myth\\Courier\\DTO\\DripEnrollmentDTO, list<stdClass>|stdClass|null given.',
	'identifier' => 'argument.type',
	'count' => 4,
	'path' => __DIR__ . '/tests/Models/Courier/DripEnrollmentModelTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $enrollment of method Myth\\Courier\\Models\\DripEnrollmentModel::recordFailure() expects Myth\\Courier\\DTO\\DripEnrollmentDTO, list<stdClass>|stdClass|null given.',
	'identifier' => 'argument.type',
	'count' => 3,
	'path' => __DIR__ . '/tests/Models/Courier/DripEnrollmentModelTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to method PHPUnit\\Framework\\Assert::assertInstanceOf() with \'Myth\\\\Postal\\\\SuppressionListInterface\' and Myth\\Courier\\Postal\\CourierSuppressionList will always evaluate to true.',
	'identifier' => 'method.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/tests/Postal/CourierSuppressionListTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to method PHPUnit\\Framework\\Assert::assertInstanceOf() with \'Myth\\\\Postal\\\\UnsubscribeUrlInterface\' and Myth\\Courier\\Postal\\CourierUnsubscribeUrl will always evaluate to true.',
	'identifier' => 'method.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/tests/Postal/CourierUnsubscribeUrlTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Instanceof between Myth\\Courier\\Enums\\SendStatus and BackedEnum will always evaluate to true.',
	'identifier' => 'instanceof.alwaysTrue',
	'count' => 1,
	'path' => __DIR__ . '/tests/Services/Courier/CampaignServiceTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $row of method CodeIgniter\\Model::insert() expects array<int|string, bool|float|int|object|string|null>|object|null, array<string, array|string> given.',
	'identifier' => 'argument.type',
	'count' => 1,
	'path' => __DIR__ . '/tests/Services/Courier/CampaignServiceTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $row of method CodeIgniter\\Model::insert() expects array<int|string, bool|float|int|object|string|null>|object|null, array<string, list<array<string, string>>|string> given.',
	'identifier' => 'argument.type',
	'count' => 2,
	'path' => __DIR__ . '/tests/Services/Courier/CampaignServiceTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot access property $current_step on list<stdClass>.',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/tests/Services/Courier/DripIntegrationTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot access property $status on list<stdClass>.',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/tests/Services/Courier/DripIntegrationTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot access property $message_id on list<stdClass>.',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/tests/Services/Courier/MailerServiceTest.php',
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
	'count' => 4,
	'path' => __DIR__ . '/tests/Services/Courier/MailerServiceTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #2 $campaign of method Myth\\Courier\\Services\\MailerService::send() expects Myth\\Courier\\DTO\\CampaignDTO, list<stdClass> given.',
	'identifier' => 'argument.type',
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
