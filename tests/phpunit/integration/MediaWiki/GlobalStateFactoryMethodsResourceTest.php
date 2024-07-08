<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\MediaWiki;

use MediaWiki\Tests\ExtensionJsonTestBase;

/**
 * Test to assert that factory methods of hook service classes (and similar services)
 * don't access the database or do http requests (which would be a performance issue).
 *
 * @group EntitySchema
 * @group EntitySchemaClient
 *
 * @license GPL-2.0-or-later
 * @coversNothing
 */
class GlobalStateFactoryMethodsResourceTest extends ExtensionJsonTestBase {

	protected string $extensionJsonPath = __DIR__ . '/../../../../extension.json';

	protected ?string $serviceNamePrefix = 'EntitySchema.';

}
