<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit;

/**
 * @license GPL-2.0-or-later
 */
trait EntitySchemaUnitTestCaseTrait {

	/** @beforeClass */
	public static function namespaceConstantSetUpBeforeClass(): void {
		if ( !defined( 'NS_ENTITYSCHEMA_JSON' ) ) {
			// defined through extension.json, which is not loaded in unit tests
			define( 'NS_ENTITYSCHEMA_JSON', 640 );
		}
	}

}
