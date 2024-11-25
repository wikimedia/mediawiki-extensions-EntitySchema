<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Content;

use CirrusSearch\CirrusSearch;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use SearchEngine;
use Wikibase\Search\Elastic\Fields\AllLabelsField;
use Wikibase\Search\Elastic\Fields\LabelsField;
use Wikibase\Search\Elastic\Fields\LabelsProviderFieldDefinitions;

/**
 * @covers \EntitySchema\MediaWiki\Content\EntitySchemaContentHandler
 *
 * @license GPL-2.0-or-later
 * @group Database
 */
class EntitySchemaContentHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	public function testGetParserOutput_usesUserLangAndSplitsParserCache() {
		$content = new EntitySchemaContent( json_encode( [
			'serializationVersion' => '3.0',
		] ) );
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' );
		$parserOptions = new ParserOptions(
			$this->getTestUser()->getUser(),
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' )
		);
		$this->setUserLang( 'en' );

		$contentRenderer = $this->getServiceContainer()->getContentRenderer();
		$parserOutput = $contentRenderer->getParserOutput( $content, $title, null, $parserOptions );
		$html = $parserOutput->getRawText();

		$this->assertStringContainsString( '(entityschema-namebadge-header-label)', $html );
		$this->assertContains( 'userlang', $parserOutput->getUsedOptions() );
	}

	public function testGetParserOutput_noHtml() {
		$content = new EntitySchemaContent( json_encode( [
			'serializationVersion' => '3.0',
		] ) );
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' );

		$contentRenderer = $this->getServiceContainer()->getContentRenderer();
		$parserOutput = $contentRenderer->getParserOutput( $content, $title, null, null, false );
		$html = $parserOutput->getRawText();

		$this->assertSame( '', $html );
	}

	/**
	 * @dataProvider provideShExSimpleUrlsAndExpectedLinks
	 */
	public function testGetParserOutput_schemaCheckLink( ?string $shExSimpleUrl, $expected ) {
		$content = new EntitySchemaContent( json_encode( [
			'labels' => [ 'en' => 'label' ],
			'descriptions' => [ 'en' => 'description' ],
			'aliases' => [ 'en' => [ 'alias' ] ],
			'schemaText' => 'Some text must be present for link to show',
			'serializationVersion' => '3.0',
		] ) );
		$this->overrideConfigValue( 'EntitySchemaShExSimpleUrl', $shExSimpleUrl );

		$contentRenderer = $this->getServiceContainer()->getContentRenderer();
		$parserOutput = $contentRenderer->getParserOutput( $content, Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ) );
		$html = $parserOutput->getRawText();

		if ( $expected === false ) {
			$this->assertStringNotContainsString( 'entityschema-check-entities', $html );
		} else {
			$this->assertStringContainsString( $expected, $html );
		}
	}

	public static function provideShExSimpleUrlsAndExpectedLinks(): iterable {
		yield 'not configured, no link' => [ null, false ];

		yield 'no query parameters, append ?' => [
			'http://a.test/doc/shex-simple.html',
			'http://a.test/doc/shex-simple.html?schemaURL=',
		];

		yield 'query parameters, append &' => [
			'http://a.test/doc/shex-simple.html?data=Endpoint: http://a.test/sparql',
			'http://a.test/doc/shex-simple.html?data=Endpoint: http://a.test/sparql&amp;schemaURL=',
		];
	}

	public function testGetFieldsForSearchIndex_noFieldDefinitions(): void {
		$contentHandler = new EntitySchemaContentHandler( 'EntitySchema', null );

		$fields = $contentHandler->getFieldsForSearchIndex(
			$this->createMock( SearchEngine::class ) );

		$this->assertSame( [], $fields );
	}

	public function testGetFieldsForSearchIndex_WikibaseCirrusSearch(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );
		$fieldDefinitions = new LabelsProviderFieldDefinitions(
			[ 'en' ],
			$this->getServiceContainer()->getConfigFactory(),
		);
		$contentHandler = new EntitySchemaContentHandler( 'EntitySchema', $fieldDefinitions );

		$fields = $contentHandler->getFieldsForSearchIndex(
			$this->createMock( CirrusSearch::class ) );

		// the exact fields are mostly internal to WikibaseCirrusSearch,
		// but we need these two fields to exist
		$this->assertArrayHasKey( LabelsField::NAME, $fields );
		$this->assertArrayHasKey( AllLabelsField::NAME, $fields );
	}

	public function testGetFieldsForSearchIndex_otherSearchEngine(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );
		$fieldDefinitions = new LabelsProviderFieldDefinitions(
			[ 'en' ],
			$this->getServiceContainer()->getConfigFactory(),
		);
		$contentHandler = new EntitySchemaContentHandler( 'EntitySchema', $fieldDefinitions );

		$fields = $contentHandler->getFieldsForSearchIndex(
			$this->createMock( SearchEngine::class ) );

		$this->assertSame( [], $fields );
	}
}
