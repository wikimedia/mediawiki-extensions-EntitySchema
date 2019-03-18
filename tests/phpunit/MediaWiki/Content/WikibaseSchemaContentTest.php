<?php

namespace Wikibase\Schema\Tests\MediaWiki\Content;

use Language;
use MediaWikiTestCase;
use ParserOptions;
use Title;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;

/**
 * @covers \Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent
 *
 * @license GPL-2.0-or-later
 */
class WikibaseSchemaContentTest extends MediaWikiTestCase {

	public function testGetParserOutput_usesUserLangAndSplitsParserCache() {
		$content = new WikibaseSchemaContent( json_encode( [
			'serializationVersion' => '3.0',
		] ) );
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' );
		$parserOptions = new ParserOptions( null, Language::factory( 'qqx' ) );
		$this->setMwGlobals( 'wgLang', Language::factory( 'en' ) );

		$parserOutput = $content->getParserOutput( $title, null, $parserOptions );
		$html = $parserOutput->getText();

		$this->assertContains( '(wikibaseschema-namebadge-header-label)', $html );
		$this->assertContains( 'userlang', $parserOutput->getUsedOptions() );
	}

	public function testGetParserOutput_noHtml() {
		$content = new WikibaseSchemaContent( json_encode( [
			'serializationVersion' => '3.0',
		] ) );
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' );

		$parserOutput = $content->getParserOutput( $title, null, null, false );
		$html = $parserOutput->getText();

		$this->assertEmpty( $html );
	}

	/**
	 * @dataProvider provideShExSimpleUrlsAndExpectedLinks
	 */
	public function testGetParserOutput_schemaCheckLink( $shExSimpleUrl, $expected ) {
		$content = new WikibaseSchemaContent( json_encode( [
			'labels' => [ 'en' => 'label' ],
			'descriptions' => [ 'en' => 'description' ],
			'aliases' => [ 'en' => [ 'alias' ] ],
			'schemaText' => 'Some text must be present for link to show',
			'serializationVersion' => '3.0',
		] ) );
		$this->setMwGlobals( 'wgWBSchemaShExSimpleUrl', $shExSimpleUrl );

		$parserOutput = $content->getParserOutput( Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' ) );
		$html = $parserOutput->getText();

		if ( $expected === false ) {
			$this->assertNotContains( 'wikibaseschema-check-entities', $html );
		} else {
			$this->assertContains( $expected, $html );
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
