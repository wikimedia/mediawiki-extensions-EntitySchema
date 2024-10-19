<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Content;

use EntitySchema\MediaWiki\Content\EntitySchemaSlotViewRenderer;
use EntitySchema\Services\Converter\FullViewEntitySchemaData;
use EntitySchema\Services\Converter\NameBadge;
use MediaWiki\Cache\LinkCache;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\MultiConfig;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWikiIntegrationTestCase;

/**
 * @covers \EntitySchema\MediaWiki\Content\EntitySchemaSlotViewRenderer
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaSlotViewRendererTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
		$this->setService( 'LinkCache', $this->createMock( LinkCache::class ) );
	}

	/**
	 * @dataProvider provideSchemaDataAndHtmlFragments
	 */
	public function testFillParserOutput( FullViewEntitySchemaData $schemaData, array $fragments ) {
		$renderer = new EntitySchemaSlotViewRenderer( 'en', null, null, null, false );

		$parserOutput = new ParserOutput();
		$renderer->fillParserOutput(
			$schemaData,
			PageReferenceValue::localReference( NS_ENTITYSCHEMA_JSON, 'E16354758' ),
			$parserOutput
		);
		$html = $parserOutput->getText();

		foreach ( $fragments as $fragment ) {
			$this->assertStringContainsString( $fragment, $html );
		}
	}

	public static function provideSchemaDataAndHtmlFragments(): iterable {
		$emptySchemaText = '';

		yield 'description, user language' => [
			new FullViewEntitySchemaData( [
				'en' => new NameBadge( '', 'test', [] ),
			], $emptySchemaText ),
			[ '<td class="entityschema-description" lang="en" dir="auto">test</td>' ],
		];

		yield 'description, other language' => [
			new FullViewEntitySchemaData( [
				'simple' => new NameBadge( '', 'test', [] ),
			], $emptySchemaText ),
			[ '<td class="entityschema-description" lang="en-simple" dir="auto">test</td>' ],
		];

		yield 'description, no HTML injection' => [
			new FullViewEntitySchemaData( [
				'en' => new NameBadge( '', '<script>alert("description XSS")</script>', [] ),
			], $emptySchemaText ),
			// exact details of escaping beyond this (> vs &gt;) don’t matter
			[ '<td class="entityschema-description" lang="en" dir="auto">&lt;script' ],
		];

		$emptyNameBadges = [ 'en' => new NameBadge( '', '', [] ) ];

		yield 'schema text' => [
			new FullViewEntitySchemaData( $emptyNameBadges, '_:empty {}' ),
			[
				'<pre id="entityschema-schema-text" class="entityschema-schema-text">_:empty {}</pre>',
			],
		];

		yield 'schema text, no HTML injection' => [
			new FullViewEntitySchemaData( $emptyNameBadges, '<script>alert("schema XSS")</script>' ),
			// exact details of escaping beyond this (> vs &gt;) don’t matter
			[ '<pre id="entityschema-schema-text" class="entityschema-schema-text">&lt;script' ],
		];

		yield 'multilingual descriptions' => [
			new FullViewEntitySchemaData( [
				'en' => new NameBadge( '', 'english description', [] ),
				'de' => new NameBadge( '', 'deutsche Beschreibung', [] ),
			], $emptySchemaText ),
			[
				'<td class="entityschema-description" lang="en" dir="auto">english description</td>',
				'<td class="entityschema-description" lang="de" dir="auto">deutsche Beschreibung</td>',
			],
		];

		yield 'description edit link' => [
			new FullViewEntitySchemaData( [
				'en' => new NameBadge( '', 'english description', [] ),
			], $emptySchemaText ),
			[
				SpecialPage::getTitleValueFor(
					'SetEntitySchemaLabelDescriptionAliases',
					'E16354758/en'
				)->getText(),
				':E16354758&amp;action=edit',
			],
		];

		yield 'edit schema link' => [
			new FullViewEntitySchemaData( [
				'en' => new NameBadge( '', 'english description', [] ),
			], 'some schema text' ),
			[ '>edit</', 'action=edit' ],
		];

		yield 'add schema link' => [
			new FullViewEntitySchemaData( [
				'en' => new NameBadge( '', 'english description', [] ),
			], $emptySchemaText ),
			[ '>add Schema text</', 'action=edit' ],
		];
	}

	public function testFillParserOutput_differentLanguage() {
		$schemaData = new FullViewEntitySchemaData( [
			'en' => new NameBadge( 'label', 'description', [ 'alias' ] ),
		], '' );
		$renderer = new EntitySchemaSlotViewRenderer(
			'qqx', // use (message-key) instead of real translations
			null,
			null,
			null,
			false
		);
		$this->setUserLang( 'en' );

		$parserOutput = new ParserOutput();
		$renderer->fillParserOutput(
			$schemaData,
			PageReferenceValue::localReference( NS_ENTITYSCHEMA_JSON, 'E1' ),
			$parserOutput
		);
		$html = $parserOutput->getText();

		// the "not contains" assertions below may be broken by unrelated changes in the future,
		// especially the "Edit" one (could be part of some Special:EditSomething URL, for example);
		// feel free to just remove them in that case if that seems appropriate
		$this->assertStringContainsString( '(entityschema-namebadge-header-language-code)', $html );
		$this->assertStringNotContainsString( 'language code', $html );
		$this->assertStringContainsString( '(entityschema-edit)', $html );
		$this->assertStringNotContainsString( 'Edit', $html );
	}

	/**
	 * @dataProvider provideLabelsAndHeadings
	 */
	public function testFillParserOutput_heading( string $label, string $expected ) {
		$schemaData = new FullViewEntitySchemaData( [
			'en' => new NameBadge( $label, 'description', [ 'alias' ] ),
		], '' );
		$renderer = new EntitySchemaSlotViewRenderer( 'en', null, null, null, false );

		$parserOutput = new ParserOutput();
		$renderer->fillParserOutput(
			$schemaData,
			PageReferenceValue::localReference( NS_ENTITYSCHEMA_JSON, 'E1' ),
			$parserOutput
		);
		$html = $parserOutput->getDisplayTitle();

		$this->assertStringContainsString( $expected, $html );
	}

	public static function provideLabelsAndHeadings(): iterable {
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

	public function testFillParserOutput_checkEntitiesAgainstSchemaLink() {
		$schemaData = new FullViewEntitySchemaData(
			[ 'en' => new NameBadge( '', '', [] ) ],
			'schema text'
		);
		$renderer = new EntitySchemaSlotViewRenderer(
			'qqx',
			null,
			new MultiConfig( [
				new HashConfig( [ 'EntitySchemaShExSimpleUrl' => 'http://my.test?foo=bar#fragment' ] ),
				$this->getServiceContainer()->getMainConfig(),
			] ),
			null,
			false
		);

		$parserOutput = new ParserOutput();
		$renderer->fillParserOutput(
			$schemaData,
			PageReferenceValue::localReference( NS_ENTITYSCHEMA_JSON, 'E12345' ),
			$parserOutput
		);
		$html = $parserOutput->getText();

		$this->assertStringContainsString(
			' href="http://my.test?foo=bar&amp;schemaURL=',
			$html
		);
		$this->assertStringContainsString(
			'E12345#fragment"',
			$html
		);
		$this->assertStringContainsString( '(entityschema-check-entities)', $html );
	}

	public function testFillParserOutput_SyntaxHighlight() {
		$this->markTestSkippedIfExtensionNotLoaded( 'SyntaxHighlight' );

		$schemaData = new FullViewEntitySchemaData(
			[ 'en' => new NameBadge( '', '', [] ) ],
			'schema text'
		);
		$renderer = new EntitySchemaSlotViewRenderer( 'qqx' );

		$parserOutput = new ParserOutput();
		$renderer->fillParserOutput(
			$schemaData,
			PageReferenceValue::localReference( NS_ENTITYSCHEMA_JSON, 'E12345' ),
			$parserOutput
		);
		$html = $parserOutput->getText();

		$this->assertStringContainsString( 'mw-highlight', $html );
		$this->assertStringContainsString( 'mw-highlight-lang-shex', $html );
	}

}
