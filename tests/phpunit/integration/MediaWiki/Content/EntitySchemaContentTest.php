<?php

namespace EntitySchema\Tests\Integration\MediaWiki\Content;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use Language;
use MediaWikiTestCase;
use ParserOptions;
use Title;

/**
 * @covers \EntitySchema\MediaWiki\Content\EntitySchemaContent
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaContentTest extends MediaWikiTestCase {

	public function testGetParserOutput_usesUserLangAndSplitsParserCache() {
		$content = new EntitySchemaContent( json_encode( [
			'serializationVersion' => '3.0',
		] ) );
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' );
		$parserOptions = new ParserOptions( null, Language::factory( 'qqx' ) );
		$this->setMwGlobals( 'wgLang', Language::factory( 'en' ) );

		$parserOutput = $content->getParserOutput( $title, null, $parserOptions );
		$html = $parserOutput->getText();

		$this->assertStringContainsString( '(entityschema-namebadge-header-label)', $html );
		$this->assertContains( 'userlang', $parserOutput->getUsedOptions() );
	}

	public function testGetParserOutput_noHtml() {
		$content = new EntitySchemaContent( json_encode( [
			'serializationVersion' => '3.0',
		] ) );
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' );

		$parserOutput = $content->getParserOutput( $title, null, null, false );
		$html = $parserOutput->getText();

		$this->assertEmpty( $html );
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

		$parserOutput = $content->getParserOutput( Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'E1' ) );
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

	public function testGetTextForSearchIndex() {
		$content = new EntitySchemaContent( json_encode( [
			'labels' => [ 'de' => 'german label', 'en' => 'english label' ],
			'descriptions' => [ 'en' => 'english description' ],
			'aliases' => [ 'en' => [ 'first', 'second' ] ],
			'schemaText' => 'Schema text for search index',
			'serializationVersion' => '3.0',
			'type' => 'ShExC',
		] ) );
		$actualSearchIndexText = $content->getTextForSearchIndex();

		$expectedText = <<<TEXT
german label
english label
english description
first, second
Schema text for search index
TEXT;

		$this->assertSame( $expectedText, $actualSearchIndexText );
	}

}
