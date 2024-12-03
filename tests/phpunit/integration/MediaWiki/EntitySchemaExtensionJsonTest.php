<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\MediaWiki;

use MediaWiki\Tests\ExtensionJsonTestBase;

/**
 * Tests to assert that factory methods of hook service classes (and similar services)
 * don't access the database or do http requests (which would be a performance issue),
 * and to ensure that hook handler implementations comply with established naming
 * conventions
 *
 * @group EntitySchema
 * @group EntitySchemaClient
 *
 * @license GPL-2.0-or-later
 * @coversNothing
 */
class EntitySchemaExtensionJsonTest extends ExtensionJsonTestBase {

	protected string $extensionJsonPath = __DIR__ . '/../../../../extension.json';

	protected ?string $serviceNamePrefix = 'EntitySchema.';

	private function camelizeHookName( string $name ) {
		$name = preg_replace( '/[^a-zA-Z0-9]+/', ' ', $name );
		return str_replace( ' ', '', ucwords( $name ) );
	}

	public function testHookHandlersAreNamedHookHandler() {
		$extension = $this->getExtensionJson();
		foreach ( $extension['Hooks'] as $hookName => $hookDetails ) {
			if ( !is_string( $hookDetails ) ) {
				continue;
			}
			$this->assertArrayHasKey(
				$hookDetails,
				$extension['HookHandlers'],
				'Hook ' . $hookName . ' must reference a HookHandler'
			);
			$this->assertSame(
				$this->camelizeHookName( $hookName ),
				$hookDetails,
				'Hook handler must be named after the hook'
			);
		}

		foreach ( $extension['HookHandlers'] as $handlerName => $handlerDetails ) {
			$this->assertArrayHasKey(
				'class',
				$handlerDetails,
				'HookHandler ' . $handlerName . ' must define "class" element'
			);
			$klass = $handlerDetails['class'];
			$this->assertSame(
				$handlerName . 'HookHandler',
				array_slice( explode( '\\', $klass ), -1, 1 )[0],
				'HookHandler ' . $klass . ' should be named "' . $handlerName . 'HookHandler"'
			);
		}
	}

}
