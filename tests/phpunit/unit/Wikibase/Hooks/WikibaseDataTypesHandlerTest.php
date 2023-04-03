<?php

declare( strict_types = 1 );

namespace phpunit\unit\Wikibase\Hooks;

use EntitySchema\Wikibase\Hooks\WikibaseDataTypesHandler;
use HashConfig;
use MediaWikiUnitTestCase;

/**
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseDataTypesHandler
 * @license GPL-2.0-or-later
 */
class WikibaseDataTypesHandlerTest extends MediaWikiUnitTestCase {

	public function testOnWikibaseRepoDataTypes(): void {
		$settings = new HashConfig( [
			'EntitySchemaEnableDatatype' => true,
		] );

		$sut = new WikibaseDataTypesHandler( $settings );

		$dataTypeDefinitions = [ 'PT:wikibase-item' => [] ];
		$sut->onWikibaseRepoDataTypes( $dataTypeDefinitions );

		$this->assertSame( [
			'PT:wikibase-item' => [],
			'PT:entity-schema' => [
				'value-type' => 'string',
			],
		], $dataTypeDefinitions );
	}

	public function testOnWikibaseRepoDataTypesDoesNothingWhenDisabled(): void {
		$settings = new HashConfig( [
			'EntitySchemaEnableDatatype' => false,
		] );

		$sut = new WikibaseDataTypesHandler( $settings );

		$dataTypeDefinitions = [ 'PT:wikibase-item' => [] ];
		$sut->onWikibaseRepoDataTypes( $dataTypeDefinitions );

		$this->assertSame( [ 'PT:wikibase-item' => [] ], $dataTypeDefinitions );
	}
}
