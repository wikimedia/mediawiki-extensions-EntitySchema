<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Content;

use CirrusSearch\CirrusSearch;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use SearchEngine;
use Wikibase\DataModel\Term\DescriptionsProvider;
use Wikibase\DataModel\Term\LabelsProvider;
use Wikibase\Lib\LanguageNameLookupFactory;
use Wikibase\Search\Elastic\Fields\AllLabelsField;
use Wikibase\Search\Elastic\Fields\DescriptionsField;
use Wikibase\Search\Elastic\Fields\DescriptionsProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\LabelsField;
use Wikibase\Search\Elastic\Fields\LabelsProviderFieldDefinitions;
use Wikibase\Search\Elastic\Fields\WikibaseDescriptionsIndexField;
use Wikibase\Search\Elastic\Fields\WikibaseLabelsIndexField;
use Wikimedia\ObjectFactory\ObjectFactory;
use WikiPage;

/**
 * @covers \EntitySchema\MediaWiki\Content\EntitySchemaContentHandler
 *
 * @license GPL-2.0-or-later
 * @group Database
 */
class EntitySchemaContentHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
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
		$contentHandler = new EntitySchemaContentHandler(
			'EntitySchema',
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class ),
			$this->createMock( ObjectFactory::class ),
			$this->createMock( HookContainer::class ),
			null,
			null
		);

		$fields = $contentHandler->getFieldsForSearchIndex(
			$this->createMock( SearchEngine::class ) );

		$this->assertSame( [], $fields );
	}

	public function testGetFieldsForSearchIndex_WikibaseCirrusSearch(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );
		$configFactory = $this->getServiceContainer()->getConfigFactory();
		$labelsFieldDefinitions = new LabelsProviderFieldDefinitions(
			[ 'en' ], $configFactory,
		);
		$descriptionsFieldDefinitions = new DescriptionsProviderFieldDefinitions(
			[ 'en' ],
			$configFactory
		);
		$contentHandler = new EntitySchemaContentHandler(
			'EntitySchema',
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class ),
			$this->createMock( ObjectFactory::class ),
			$this->createMock( HookContainer::class ),
			$labelsFieldDefinitions,
			$descriptionsFieldDefinitions
		);

		$fields = $contentHandler->getFieldsForSearchIndex(
			$this->createMock( CirrusSearch::class ) );

		// the exact fields are mostly internal to WikibaseCirrusSearch,
		// but we need these three fields to exist
		$this->assertArrayHasKey( LabelsField::NAME, $fields );
		$this->assertArrayHasKey( AllLabelsField::NAME, $fields );
		$this->assertArrayHasKey( DescriptionsField::NAME, $fields );
	}

	public function testGetFieldsForSearchIndex_otherSearchEngine(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );
		$configFactory = $this->getServiceContainer()->getConfigFactory();
		$labelsFieldDefinitions = new LabelsProviderFieldDefinitions(
			[ 'en' ],
			$configFactory,
		);
		$descriptionsFieldDefinitions = new DescriptionsProviderFieldDefinitions(
			[ 'en' ],
			$configFactory
		);
		$contentHandler = new EntitySchemaContentHandler(
			'EntitySchema',
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class ),
			$this->createMock( ObjectFactory::class ),
			$this->createMock( HookContainer::class ),
			$labelsFieldDefinitions,
			$descriptionsFieldDefinitions
		);

		$fields = $contentHandler->getFieldsForSearchIndex(
			$this->createMock( SearchEngine::class ) );

		$this->assertSame( [], $fields );
	}

	public static function provideWikiPageAndRevisionFactory(): iterable {
		yield 'no revision given, use WikiPage content' => [
			static function ( self $self, EntitySchemaContent $content ) {
				$wikiPage = $self->createConfiguredMock( WikiPage::class, [
					'getContent' => $content,
				] );
				$revision = null;
				return [ $wikiPage, $revision ];
			},
		];

		yield 'revision given, don’t use WikiPage content' => [
			static function ( self $self, EntitySchemaContent $content ) {
				// parent::getDataForSearchIndex() requires that both objects return the same page ID
				$pageId = 1;
				$wikiPage = $self->createConfiguredMock( WikiPage::class, [
					'getId' => $pageId,
				] );
				$revision = $self->createConfiguredMock( RevisionRecord::class, [
					'getPageId' => $pageId,
					'getContent' => $content,
				] );
				return [ $wikiPage, $revision ];
			},
		];
	}

	/** @dataProvider provideWikiPageAndRevisionFactory */
	public function testGetDataForSearchIndex( callable $wikiPageAndRevisionFactory ): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseCirrusSearch' );
		$content = new EntitySchemaContent( json_encode( [
			'labels' => [ 'en' => 'label' ],
			'descriptions' => [ 'en' => 'description' ],
			'aliases' => [ 'en' => [ 'alias' ] ],
			'schemaText' => 'schema text',
			'serializationVersion' => '3.0',
		] ) );
		[ $wikiPage, $revision ] = $wikiPageAndRevisionFactory( $this, $content );
		$labelsField = $this->createMock( WikibaseLabelsIndexField::class );
		$labelsField->expects( $this->once() )
			->method( 'getLabelsIndexedData' )
			->willReturnCallback( fn ( LabelsProvider $l ) => $l->getLabels()->toTextArray() );
		$labelsFieldDefinitions = $this->createMock( LabelsProviderFieldDefinitions::class );
		$labelsFieldDefinitions->expects( $this->once() )
			->method( 'getFields' )
			->willReturn( [ 'labelsField' => $labelsField, 'no field' => null ] );
		$descriptionsField = $this->createMock( WikibaseDescriptionsIndexField::class );
		$descriptionsField->expects( $this->once() )
			->method( 'getDescriptionsIndexedData' )
			->willReturnCallback( fn ( DescriptionsProvider $d ) => $d->getDescriptions()->toTextArray() );
		$descriptionsFieldDefinitions = $this->createMock( DescriptionsProviderFieldDefinitions::class );
		$descriptionsFieldDefinitions->expects( $this->once() )
			->method( 'getFields' )
			->willReturn( [ 'descriptionsField' => $descriptionsField, 'no field' => null ] );
		$contentHandler = new EntitySchemaContentHandler(
			'EntitySchema',
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class ),
			$this->createMock( ObjectFactory::class ),
			$this->createMock( HookContainer::class ),
			$labelsFieldDefinitions,
			$descriptionsFieldDefinitions
		);

		$fieldsData = $contentHandler->getDataForSearchIndex(
			$wikiPage,
			$this->createMock( ParserOutput::class ),
			$this->createMock( SearchEngine::class ),
			$revision
		);

		$this->assertArrayHasKey( 'labelsField', $fieldsData );
		$this->assertSame( [ 'en' => 'label' ], $fieldsData['labelsField'] );
		$this->assertArrayHasKey( 'descriptionsField', $fieldsData );
		$this->assertSame( [ 'en' => 'description' ], $fieldsData['descriptionsField'] );
		$this->assertArrayNotHasKey( 'no field', $fieldsData );
	}
}
