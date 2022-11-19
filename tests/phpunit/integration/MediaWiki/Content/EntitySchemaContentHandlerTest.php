<?php

namespace EntitySchema\Tests\Integration\MediaWiki\Content;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use MediaWikiIntegrationTestCase;
use ParserOptions;
use Title;

/**
 * @covers \EntitySchema\MediaWiki\Content\EntitySchemaContentHandler
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaContentHandlerTest extends MediaWikiIntegrationTestCase {

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
		$html = $parserOutput->getText();

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
		$html = $parserOutput->getText();

		$this->assertSame( '', $html );
	}

	/**
	 * @dataProvider provideShExSimpleUrlsAndExpectedLinks
	 */
	public function testGetParserOutput_schemaCheckLink( $shExSimpleUrl, $expected ) {
		$content = new EntitySchemaContent( json_encode( [
			'labels' => [ 'en' => 'label' ],
			'descriptions' => [ 'en' => 'description' ],
			'aliases' => [ 'en' => [ 'alias' ] ],
			'schemaText' => 'Some text must be present for link to show',
			'serializationVersion' => '3.0',
		] ) );
		$this->setMwGlobals( 'wgEntitySchemaShExSimpleUrl', $shExSimpleUrl );

		$contentRenderer = $this->getServiceContainer()->getContentRenderer();
		$parserOutput = $contentRenderer->getParserOutput( $content, Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ) );
		$html = $parserOutput->getText();

		if ( $expected === false ) {
			$this->assertStringNotContainsString( 'entityschema-check-entities', $html );
		} else {
			$this->assertStringContainsString( $expected, $html );
		}
	}

	public function provideShExSimpleUrlsAndExpectedLinks() {
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
}
