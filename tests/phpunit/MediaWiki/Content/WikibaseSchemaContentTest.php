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

}
