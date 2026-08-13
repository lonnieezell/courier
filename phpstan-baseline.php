<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'rawMessage' => 'PHPDoc tag @var with type Myth\\Courier\\Services\\DripServiceInterface is not subtype of type Myth\\Courier\\Services\\DripService.',
	'identifier' => 'varTag.type',
	'count' => 1,
	'path' => __DIR__ . '/src/Commands/ProcessDrips.php',
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
	'rawMessage' => 'Access to an undefined property CodeIgniter\\Database\\ConnectionInterface::$DBDriver.',
	'identifier' => 'property.notFound',
	'count' => 2,
	'path' => __DIR__ . '/src/Database/Migrations/2026-08-12-120002_AddBlastDedupeIndexToCourierSends.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Access to an undefined property CodeIgniter\\Database\\ConnectionInterface::$DBPrefix.',
	'identifier' => 'property.notFound',
	'count' => 4,
	'path' => __DIR__ . '/src/Database/Migrations/2026-08-12-120002_AddBlastDedupeIndexToCourierSends.php',
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
	'rawMessage' => 'Method Myth\\Courier\\Models\\DripEnrollmentModel::claimDue() should return list<Myth\\Courier\\DTO\\DripEnrollmentDTO> but returns list<object{id: int, contact_id: int, campaign_id: int, current_step: int, next_send_at: string|null, status: Myth\\Courier\\Enums\\EnrollmentStatus, created_at: string|null, updated_at: string|null, retry_count: int, locked_at: string|null}>.',
	'identifier' => 'return.type',
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
	'rawMessage' => 'Only booleans are allowed in an if condition, mixed given.',
	'identifier' => 'if.condNotBoolean',
	'count' => 1,
	'path' => __DIR__ . '/src/Models/DripStepModel.php',
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
	'rawMessage' => 'Property Myth\\Courier\\Services\\ContactService::$enrollmentModel is never read, only written.',
	'identifier' => 'property.onlyWritten',
	'count' => 1,
	'path' => __DIR__ . '/src/Services/ContactService.php',
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
	'path' => __DIR__ . '/src/Views/tests/test_data_layout.php',
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
	'path' => __DIR__ . '/src/Views/tests/test_styled_layout.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Direct assignment of non-empty-array to $_SERVER[\'argv\'] is not allowed.',
	'identifier' => 'codeigniter.superglobalsOffsetAssign',
	'count' => 1,
	'path' => __DIR__ . '/tests/Commands/Courier/CampaignsCommandTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Direct assignment of non-empty-array to $_SERVER[\'argv\'] is not allowed.',
	'identifier' => 'codeigniter.superglobalsOffsetAssign',
	'count' => 1,
	'path' => __DIR__ . '/tests/Commands/Courier/ContactsCommandTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Direct assignment of non-empty-array to $_SERVER[\'argv\'] is not allowed.',
	'identifier' => 'codeigniter.superglobalsOffsetAssign',
	'count' => 1,
	'path' => __DIR__ . '/tests/Commands/Courier/DripsCommandTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Call to method PHPUnit\\Framework\\Assert::assertTrue() with true will always evaluate to true.',
	'identifier' => 'method.alreadyNarrowedType',
	'count' => 1,
	'path' => __DIR__ . '/tests/Commands/Courier/ProcessDripsTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Direct assignment of non-empty-array to $_SERVER[\'argv\'] is not allowed.',
	'identifier' => 'codeigniter.superglobalsOffsetAssign',
	'count' => 1,
	'path' => __DIR__ . '/tests/Commands/Courier/SegmentsCommandTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Direct assignment of non-empty-array to $_SERVER[\'argv\'] is not allowed.',
	'identifier' => 'codeigniter.superglobalsOffsetAssign',
	'count' => 1,
	'path' => __DIR__ . '/tests/Commands/Courier/TagsCommandTest.php',
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
	'rawMessage' => 'Method Tests\\Integration\\EndToEndTest::makeDripCampaign() should return Myth\\Courier\\DTO\\CampaignDTO but returns list<object{id: int, name: string, subject: string, type: Myth\\Courier\\Enums\\CampaignType, view: string|null, layout: string|null, status: Myth\\Courier\\Enums\\CampaignStatus, segment_id: int|null, tag_filter: stdClass|null, from_name: string, from_email: string, scheduled_at: string|null, sent_at: string|null, created_at: string|null, updated_at: string|null, source_file: string|null, mailable: string|null}>|object{id: int, name: string, subject: string, type: Myth\\Courier\\Enums\\CampaignType, view: string|null, layout: string|null, status: Myth\\Courier\\Enums\\CampaignStatus, segment_id: int|null, tag_filter: stdClass|null, from_name: string, from_email: string, scheduled_at: string|null, sent_at: string|null, created_at: string|null, updated_at: string|null, source_file: string|null, mailable: string|null}|null.',
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
	'rawMessage' => 'Parameter #1 $enrollment of method Myth\\Courier\\Models\\DripEnrollmentModel::advance() expects Myth\\Courier\\DTO\\DripEnrollmentDTO, list<object{id: int, contact_id: int, campaign_id: int, current_step: int, next_send_at: string|null, status: Myth\\Courier\\Enums\\EnrollmentStatus, created_at: string|null, updated_at: string|null, retry_count: int, locked_at: string|null}>|object{id: int, contact_id: int, campaign_id: int, current_step: int, next_send_at: string|null, status: Myth\\Courier\\Enums\\EnrollmentStatus, created_at: string|null, updated_at: string|null, retry_count: int, locked_at: string|null}|null given.',
	'identifier' => 'argument.type',
	'count' => 6,
	'path' => __DIR__ . '/tests/Models/Courier/DripEnrollmentModelTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #1 $enrollment of method Myth\\Courier\\Models\\DripEnrollmentModel::recordFailure() expects Myth\\Courier\\DTO\\DripEnrollmentDTO, list<object{id: int, contact_id: int, campaign_id: int, current_step: int, next_send_at: string|null, status: Myth\\Courier\\Enums\\EnrollmentStatus, created_at: string|null, updated_at: string|null, retry_count: int, locked_at: string|null}>|object{id: int, contact_id: int, campaign_id: int, current_step: int, next_send_at: string|null, status: Myth\\Courier\\Enums\\EnrollmentStatus, created_at: string|null, updated_at: string|null, retry_count: int, locked_at: string|null}|null given.',
	'identifier' => 'argument.type',
	'count' => 5,
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
	'count' => 2,
	'path' => __DIR__ . '/tests/Services/Courier/CampaignServiceTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot access property $message_id on list<object{id: int, contact_id: int, campaign_id: int|null, drip_step_id: int|null, status: Myth\\Courier\\Enums\\SendStatus, message_id: string|null, open_token: string|null, sent_at: string|null, opened_at: string|null, clicked_at: string|null, created_at: string|null, updated_at: string|null, unsubscribe_token: string|null, unsubscribe_token_expires_at: string|null}>.',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/tests/Services/Courier/MailerServiceTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot access property $sent_at on list<object{id: int, contact_id: int, campaign_id: int|null, drip_step_id: int|null, status: Myth\\Courier\\Enums\\SendStatus, message_id: string|null, open_token: string|null, sent_at: string|null, opened_at: string|null, clicked_at: string|null, created_at: string|null, updated_at: string|null, unsubscribe_token: string|null, unsubscribe_token_expires_at: string|null}>.',
	'identifier' => 'property.nonObject',
	'count' => 1,
	'path' => __DIR__ . '/tests/Services/Courier/MailerServiceTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Cannot access property $status on list<object{id: int, contact_id: int, campaign_id: int|null, drip_step_id: int|null, status: Myth\\Courier\\Enums\\SendStatus, message_id: string|null, open_token: string|null, sent_at: string|null, opened_at: string|null, clicked_at: string|null, created_at: string|null, updated_at: string|null, unsubscribe_token: string|null, unsubscribe_token_expires_at: string|null}>.',
	'identifier' => 'property.nonObject',
	'count' => 4,
	'path' => __DIR__ . '/tests/Services/Courier/MailerServiceTest.php',
];
$ignoreErrors[] = [
	'rawMessage' => 'Parameter #2 $campaign of method Myth\\Courier\\Services\\MailerService::send() expects Myth\\Courier\\DTO\\CampaignDTO, list<object{id: int, name: string, subject: string, type: Myth\\Courier\\Enums\\CampaignType, view: string|null, layout: string|null, status: Myth\\Courier\\Enums\\CampaignStatus, segment_id: int|null, tag_filter: stdClass|null, from_name: string, from_email: string, scheduled_at: string|null, sent_at: string|null, created_at: string|null, updated_at: string|null, source_file: string|null, mailable: string|null}> given.',
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
