<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\MediaWiki\Content;

use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\MediaWiki\Content\EntitySchemaSlotViewRenderer;
use EntitySchema\Services\Converter\FullViewEntitySchemaData;
use EntitySchema\Services\Converter\NameBadge;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\MultiConfig;
use MediaWiki\Page\LinkCache;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWikiIntegrationTestCase;
use Wikibase\DataModel\Term\TermFallback;
use Wikibase\Lib\LanguageNameLookupFactory;

/**
 * @covers \EntitySchema\MediaWiki\Content\EntitySchemaSlotViewRenderer
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaSlotViewRendererTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
		$this->setService( 'LinkCache', $this->createMock( LinkCache::class ) );
	}

	/**
	 * @dataProvider provideSchemaDataAndHtmlFragments
	 */
	public function testFillParserOutput( FullViewEntitySchemaData $schemaData, array $fragments ) {
		$renderer = new EntitySchemaSlotViewRenderer(
			'en',
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class ),
			null,
			null,
			null,
			false
		);

		$parserOutput = new ParserOutput();
		$renderer->fillParserOutput(
			$schemaData,
			PageReferenceValue::localReference( NS_ENTITYSCHEMA_JSON, 'E16354758' ),
			$parserOutput
		);
		$html = $parserOutput->getRawText();

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
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class ),
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
		$html = $parserOutput->getRawText();

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
	public function testFillParserOutput_heading(
		string $languageCode,
		string $englishLabel,
		array $fallbackChain,
		string $expectedLabel,
		string $expectedHtmlTitle
	) {
		$schemaData = new FullViewEntitySchemaData( [
			'en' => new NameBadge( $englishLabel, 'description', [ 'alias' ] ),
			'de' => new NameBadge( '', 'description', [ 'alias' ] ),
		], '' );
		$labelLookupMock = $this->createMock( LabelLookup::class );
		if ( $languageCode === 'en' && $englishLabel !== '' ) {
			$labelLookupMock->method( 'getLabelForSchemaData' )
				->willReturn( new TermFallback( 'en', $englishLabel, 'en', null ) );
		} elseif ( in_array( $languageCode, $fallbackChain ) && $englishLabel !== '' ) {
			$labelLookupMock->method( 'getLabelForSchemaData' )
				->willReturn( new TermFallback( $languageCode, $englishLabel, 'en', null ) );
		} else {
			$labelLookupMock->method( 'getLabelForSchemaData' )
				->willReturn( null );
		}
		$renderer = new EntitySchemaSlotViewRenderer(
			$languageCode,
			$labelLookupMock,
			$this->createMock( LanguageNameLookupFactory::class ),
			null,
			null,
			null,
			false
		);

		$parserOutput = new ParserOutput();
		$renderer->fillParserOutput(
			$schemaData,
			PageReferenceValue::localReference( NS_ENTITYSCHEMA_JSON, 'E1' ),
			$parserOutput
		);
		$this->assertEquals(
			$expectedHtmlTitle,
			$parserOutput->getExtensionData( 'entityschema-meta-tags' )['title']
		);
		$html = $parserOutput->getDisplayTitle();

		$this->assertStringContainsString( $expectedLabel, $html );
	}

	public static function provideLabelsAndHeadings(): iterable {
		yield 'simple case' => [
			'en',
			'english label',
			[],
			'english label',
			'english label (E1)',
		];

		yield 'no HTML injection' => [
			'en',
			'<script>alert("english label")</script>',
			[],
			'&lt;script',
			'<script>alert("english label")</script> (E1)',
		];

		yield 'empty label message if label missing' => [
			'en',
			'',
			[],
			'No label defined',
			'No label defined (E1)',
		];

		yield 'fallback label if available' => [
			'de',
			'english label',
			[ 'de', 'en' ],
			'english label',
			'english label (E1)',
		];
	}

	public function testFillParserOutput_checkEntitiesAgainstSchemaLink() {
		$schemaData = new FullViewEntitySchemaData(
			[ 'en' => new NameBadge( '', '', [] ) ],
			'schema text'
		);
		$renderer = new EntitySchemaSlotViewRenderer(
			'qqx',
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class ),
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
		$html = $parserOutput->getRawText();

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
		$renderer = new EntitySchemaSlotViewRenderer(
			'qqx',
			$this->createMock( LabelLookup::class ),
			$this->createMock( LanguageNameLookupFactory::class )
		);

		$parserOutput = new ParserOutput();
		$renderer->fillParserOutput(
			$schemaData,
			PageReferenceValue::localReference( NS_ENTITYSCHEMA_JSON, 'E12345' ),
			$parserOutput
		);
		$html = $parserOutput->getRawText();

		$this->assertStringContainsString( 'mw-highlight', $html );
		$this->assertStringContainsString( 'mw-highlight-lang-shex', $html );
	}

}
