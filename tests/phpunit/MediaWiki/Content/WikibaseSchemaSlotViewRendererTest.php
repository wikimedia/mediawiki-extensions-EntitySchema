<?php

namespace Wikibase\Schema\Tests\MediaWiki\Content;

use Language;
use MediaWikiTestCase;
use ParserOutput;
use SpecialPage;
use Title;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaSlotViewRenderer;
use Wikibase\Schema\Services\SchemaDispatcher\FullViewSchemaData;
use Wikibase\Schema\Services\SchemaDispatcher\NameBadge;

/**
 * @covers \Wikibase\Schema\MediaWiki\Content\WikibaseSchemaSlotViewRenderer
 *
 * @license GPL-2.0-or-later
 */
class WikibaseSchemaSlotViewRendererTest extends MediaWikiTestCase {

	/**
	 * @dataProvider provideSchemaDataAndHtmlFragments
	 */
	public function testFillParserOutput( FullViewSchemaData $schemaData, array $fragments ) {
		$renderer = new WikibaseSchemaSlotViewRenderer( 'en' );

		$parserOutput = new ParserOutput();
		$renderer->fillParserOutput(
			$schemaData,
			Title::makeTitle( NS_WBSCHEMA_JSON, 'O16354758' ),
			$parserOutput
		);
		$html = $parserOutput->getText();

		foreach ( $fragments as $fragment ) {
			$this->assertContains( $fragment, $html );
		}
	}

	public function provideSchemaDataAndHtmlFragments() {
		$emptySchemaText = '';

		yield 'description, user language' => [
			new FullViewSchemaData( [
				'en' => new NameBadge( '', 'test', [] ),
			], $emptySchemaText ),
			[ '<td class="wbschema-description" lang="en">test</td>' ],
		];

		yield 'description, other language' => [
			new FullViewSchemaData( [
				'simple' => new NameBadge( '', 'test', [] ),
			], $emptySchemaText ),
			[ '<td class="wbschema-description" lang="en-simple">test</td>' ],
		];

		yield 'description, no HTML injection' => [
			new FullViewSchemaData( [
				'en' => new NameBadge( '', '<script>alert("description XSS")</script>', [] ),
			], $emptySchemaText ),
			// exact details of escaping beyond this (> vs &gt;) don’t matter
			[ '<td class="wbschema-description" lang="en">&lt;script' ],
		];

		$emptyNameBadges = [ 'en' => new NameBadge( '', '', [] ) ];

		yield 'schema text' => [
			new FullViewSchemaData( $emptyNameBadges, '_:empty {}' ),
			[ '<pre id="wbschema-schema-text" class="wbschema-schema-text">_:empty {}</pre>' ],
		];

		yield 'schema text, no HTML injection' => [
			new FullViewSchemaData( $emptyNameBadges, '<script>alert("schema XSS")</script>' ),
			// exact details of escaping beyond this (> vs &gt;) don’t matter
			[ '<pre id="wbschema-schema-text" class="wbschema-schema-text">&lt;script' ],
		];

		yield 'multilingual descriptions' => [
			new FullViewSchemaData( [
				'en' => new NameBadge( '', 'english description', [] ),
				'de' => new NameBadge( '', 'deutsche Beschreibung', [] ),
			], $emptySchemaText ),
			[
				'<td class="wbschema-description" lang="en">english description</td>',
				'<td class="wbschema-description" lang="de">deutsche Beschreibung</td>',
			]
		];

		yield 'description edit link' => [
			new FullViewSchemaData( [
				'en' => new NameBadge( '', 'english description', [] ),
			], $emptySchemaText ),
			[
				SpecialPage::getTitleValueFor(
					'SetSchemaLabelDescriptionAliases',
					'O16354758/en'
				)->getText(),
				':O16354758&amp;action=edit'
			]
		];
	}

	public function testFillParserOutput_differentLanguage() {
		$schemaData = new FullViewSchemaData( [
			'en' => new NameBadge( 'label', 'description', [ 'alias' ] ),
		], '' );
		$renderer = new WikibaseSchemaSlotViewRenderer(
			'qqx' // use (message-key) instead of real translations
		);
		$this->setMwGlobals( 'wgLang', Language::factory( 'en' ) );

		$parserOutput = new ParserOutput();
		$renderer->fillParserOutput(
			$schemaData,
			Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' ),
			$parserOutput
		);
		$html = $parserOutput->getText();

		$this->assertContains( '(wikibaseschema-namebadge-header-language-code)', $html );
		$this->assertNotContains( 'language code', $html );
	}

	/**
	 * @dataProvider provideLabelsAndHeadings
	 */
	public function testFillParserOutput_heading( $label, $expected ) {
		$schemaData = new FullViewSchemaData( [
			'en' => new NameBadge( $label, 'description', [ 'alias' ] ),
		], '' );
		$renderer = new WikibaseSchemaSlotViewRenderer( 'en' );

		$parserOutput = new ParserOutput();
		$renderer->fillParserOutput(
			$schemaData,
			Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' ),
			$parserOutput
		);
		$html = $parserOutput->getDisplayTitle();

		$this->assertContains( $expected, $html );
	}

	public function provideLabelsAndHeadings() {
		yield 'simple case' => [
			'english label',
			'english label',
		];

		yield 'no HTML injection' => [
			'<script>alert("english label")</script>',
			'&lt;script',
		];

		yield 'fallback if label missing' => [
			'',
			'No label defined',
		];
	}

}
