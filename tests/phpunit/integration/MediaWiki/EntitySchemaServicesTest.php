<?php
declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki;

use EntitySchema\MediaWiki\EntitySchemaServices;
use MediaWiki\Tests\ExtensionServicesTestBase;

/**
 * @covers \EntitySchema\MediaWiki\EntitySchemaServices
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaServicesTest extends ExtensionServicesTestBase {

	protected string $className = EntitySchemaServices::class;

	protected string $serviceNamePrefix = 'EntitySchema.';

}
