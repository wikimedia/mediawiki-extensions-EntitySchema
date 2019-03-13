<?php

namespace Wikibase\Schema\Tests\MediaWiki\Content;

use Language;
use MediaWikiTestCase;
use ParserOptions;
use Title;
use SpecialPage;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;

/**
 * @covers \Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent
 *
 * @license GPL-2.0-or-later
 */
class WikibaseSchemaContentTest extends MediaWikiTestCase {

	/**
	 * @dataProvider provideJsonAndHtmlFragments
	 */
	public function testGetParserOutput( array $json, array $fragments ) {
		$text = json_encode( $json );
		$content = new WikibaseSchemaContent( $text );

		$parserOutput = $content->getParserOutput(
			Title::makeTitle( NS_WBSCHEMA_JSON, 'O16354758' )
		);
		$html = $parserOutput->getText();

		foreach ( $fragments as $fragment ) {
			$this->assertContains( $fragment, $html );
		}
	}

	public function provideJsonAndHtmlFragments() {
		return [
			[
				[
					'descriptions' => [
						'en' => 'test',
					],
					'schemaText' => '',
					'serializationVersion' => '3.0',
				],
				[ '<td class="wbschema-description" lang="en">test</td>' ],
			],
			[
				[
					'descriptions' => [
						'simple' => 'test',
					],
					'schemaText' => '',
					'serializationVersion' => '3.0',
				],
				[ '<td class="wbschema-description" lang="en-simple">test</td>' ],
			],
			[
				[
					'descriptions' => [
						'en' => '<script>alert("description XSS")</script>',
					],
					'schemaText' => '',
					'serializationVersion' => '3.0',
				],
				// exact details of escaping beyond this (> vs &gt;) don’t matter
				[ '<td class="wbschema-description" lang="en">&lt;script' ],
			],
			[
				[
					'descriptions' => [
						'en' => 'test',
					],
					'schemaText' => '_:empty {}',
					'serializationVersion' => '3.0',
				],
				[ '<pre id="wbschema-schema-text" class="wbschema-schema-text">_:empty {}</pre>' ],
			],
			[
				[
					'descriptions' => [
						'en' => 'test',
					],
					'schemaText' => '<script>alert("schema XSS")</script>',
					'serializationVersion' => '3.0',
				],
				// exact details of escaping beyond this (> vs &gt;) don’t matter
				[ '<pre id="wbschema-schema-text" class="wbschema-schema-text">&lt;script' ],
			],
			[
				[
					'descriptions' => [
						'en' => 'english description',
						'de' => 'deutsche Beschreibung',
					],
					'schemaText' => '',
					'serializationVersion' => '3.0',
				],
				[
					'<td class="wbschema-description" lang="en">english description</td>',
					'<td class="wbschema-description" lang="de">deutsche Beschreibung</td>',
				]
			],
			[
				[
					'descriptions' => [
						'en' => 'english description',
					],
					'schemaText' => '',
					'serializationVersion' => '3.0',
				],
				[
					SpecialPage::getTitleValueFor(
						'SetSchemaLabelDescriptionAliases',
						'O16354758/en'
					)->getText(),
					':O16354758&amp;action=edit'
				]
			],
		];
	}

	public function testGetParserOutput_differentLanguage() {
		$content = new WikibaseSchemaContent( json_encode( [
			'labels' => [ 'en' => 'label' ],
			'descriptions' => [ 'en' => 'description' ],
			'aliases' => [ 'en' => [ 'alias' ] ],
			'schemaText' => '',
			'serializationVersion' => '3.0',
		] ) );
		$this->setMwGlobals( 'wgLang', Language::factory( 'en' ) );

		$parserOutput = $content->getParserOutput(
			Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' ),
			null,
			new ParserOptions(
				null,
				Language::factory( 'qqx' ) // use (message-key) instead of real translations
			)
		);
		$html = $parserOutput->getText();

		$this->assertContains( '(wikibaseschema-namebadge-header-language-code)', $html );
		$this->assertNotContains( 'language code', $html );
	}

	/**
	 * @dataProvider provideLabelsAndHeadings
	 */
	public function testGetParserOutput_heading( $label, $expected ) {
		$content = new WikibaseSchemaContent( json_encode( [
			'labels' => [ 'en' => $label ],
			'descriptions' => [ 'en' => 'description' ],
			'aliases' => [ 'en' => [ 'alias' ] ],
			'schemaText' => '',
			'serializationVersion' => '3.0',
		] ) );

		$parserOutput = $content->getParserOutput( Title::makeTitle( NS_WBSCHEMA_JSON, 'O1' ) );
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
