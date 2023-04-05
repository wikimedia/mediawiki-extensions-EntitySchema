<?php

declare( strict_types = 1 );

namespace phpunit\unit\Wikibase\Hooks;

use EntitySchema\Wikibase\Formatters\EntitySchemaFormatter;
use EntitySchema\Wikibase\Hooks\WikibaseDataTypesHandler;
use HashConfig;
use MediaWiki\Linker\LinkRenderer;
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
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );

		$sut = new WikibaseDataTypesHandler( $stubLinkRenderer, $settings );

		$dataTypeDefinitions = [ 'PT:wikibase-item' => [] ];
		$sut->onWikibaseRepoDataTypes( $dataTypeDefinitions );

		$this->assertArrayHasKey( 'PT:wikibase-item', $dataTypeDefinitions );
		$this->assertArrayHasKey( 'PT:entity-schema', $dataTypeDefinitions );
		$this->assertInstanceOf(
			EntitySchemaFormatter::class,
			$dataTypeDefinitions['PT:entity-schema']['formatter-factory-callback']( 'html' )
		);
	}

	public function testOnWikibaseRepoDataTypesDoesNothingWhenDisabled(): void {
		$settings = new HashConfig( [
			'EntitySchemaEnableDatatype' => false,
		] );
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );

		$sut = new WikibaseDataTypesHandler( $stubLinkRenderer, $settings );

		$dataTypeDefinitions = [ 'PT:wikibase-item' => [] ];
		$sut->onWikibaseRepoDataTypes( $dataTypeDefinitions );

		$this->assertSame( [ 'PT:wikibase-item' => [] ], $dataTypeDefinitions );
	}
}
