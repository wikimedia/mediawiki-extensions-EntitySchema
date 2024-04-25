<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Formatters;

use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use EntitySchema\Wikibase\Formatters\EntitySchemaFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiUnitTestCase;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Term\TermFallback;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Lib\LanguageNameLookupFactory;

/**
 * @covers \EntitySchema\Wikibase\Formatters\EntitySchemaFormatter
 * @license GPL-2.0-or-later
 */
class EntitySchemaFormatterTest extends MediaWikiUnitTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( !defined( 'NS_ENTITYSCHEMA_JSON' ) ) {
			// defined through extension.json, which is not loaded in unit tests
			define( 'NS_ENTITYSCHEMA_JSON', 640 );
		}
	}

	public static function provideTextFormats(): iterable {
		return [
			[ SnakFormatter::FORMAT_PLAIN, '[[EntitySchema:E123]]' ],
			[ SnakFormatter::FORMAT_WIKI, 'E123' ],
		];
	}

	/**
	 * @dataProvider provideTextFormats
	 */
	public function testUnhandledFormats( string $format, string $expectedResult ): void {
		$linkRenderer = $this->createMock( LinkRenderer::class );
		$linkRenderer->expects( $this->never() )
			->method( $this->anything() );
		$options = new FormatterOptions( [ ValueFormatter::OPT_LANG => 'en' ] );
		$mockLabelLookup = $this->createMock( LabelLookup::class );
		$mockLabelLookup->expects( $this->never() )
			->method( $this->anything() );
		$mockTitleFactory = $this->createMock( TitleFactory::class );
		$mockTitleFactory->expects( $this->never() )
			->method( $this->anything() );
		$languageNameLookupFactory = $this->createMock( LanguageNameLookupFactory::class );
		$languageNameLookupFactory->expects( $this->never() )
			->method( $this->anything() );
		$sut = new EntitySchemaFormatter(
			$format,
			$options,
			$linkRenderer,
			$mockLabelLookup,
			$mockTitleFactory,
			$languageNameLookupFactory
		);

		$this->assertSame(
			$expectedResult,
			$sut->format( new EntitySchemaValue( new EntitySchemaId( 'E123' ) ) )
		);
	}

	public static function provideHtmlCases(): iterable {
		return [
			[ SnakFormatter::FORMAT_HTML ],
			[ SnakFormatter::FORMAT_HTML_VERBOSE ],
		];
	}

	/**
	 * @dataProvider provideHtmlCases
	 */
	public function testHtmlNoLabel( string $format ): void {
		$schemaId = 'E123';
		$options = new FormatterOptions( [ ValueFormatter::OPT_LANG => 'en' ] );
		$stubPageIdentity = $this->createStub( Title::class );
		$mockTitleFactory = $this->createMock( TitleFactory::class );
		$mockTitleFactory->expects( $this->once() )
			->method( 'newFromText' )
			->with( $schemaId, NS_ENTITYSCHEMA_JSON )
			->willReturn( $stubPageIdentity );
		$stubLanguageNameLookupFactory = $this->createStub( LanguageNameLookupFactory::class );
		$mockLabelLookup = $this->createMock( LabelLookup::class );
		$mockLabelLookup->expects( $this->once() )
			->method( 'getLabelForTitle' )
			->with(
				$stubPageIdentity,
				'en'
			)
			->willReturn( null );
		$fakeLinkHtml = '<a>E123</a>';
		$linkRenderer = $this->createMock( LinkRenderer::class );
		$linkRenderer->expects( $this->once() )
			->method( 'makePreloadedLink' )
			->with(
				$this->callback( $this->getCallbackToAssertLinkTarget( $schemaId ) ),
				$schemaId
			)
			->willReturn( $fakeLinkHtml );

		$sut = new EntitySchemaFormatter(
			$format,
			$options,
			$linkRenderer,
			$mockLabelLookup,
			$mockTitleFactory,
			$stubLanguageNameLookupFactory
		);

		$this->assertSame( $fakeLinkHtml, $sut->format( new EntitySchemaValue( new EntitySchemaId( $schemaId ) ) ) );
	}

	/**
	 * @dataProvider provideHtmlCases
	 */
	public function testHtmlWithLabel( string $format ): void {
		$schemaId = 'E1234';
		$englishLabel = 'English Label';
		$langCode = 'en';
		$options = new FormatterOptions( [ ValueFormatter::OPT_LANG => $langCode ] );
		$stubPageIdentity = $this->createStub( Title::class );
		$mockTitleFactory = $this->createMock( TitleFactory::class );
		$mockTitleFactory->expects( $this->once() )
			->method( 'newFromText' )
			->with( $schemaId, NS_ENTITYSCHEMA_JSON )
			->willReturn( $stubPageIdentity );
		$stubLanguageNameLookupFactory = $this->createStub( LanguageNameLookupFactory::class );
		$mockLabelLookup = $this->createMock( LabelLookup::class );
		$mockLabelLookup->expects( $this->once() )
			->method( 'getLabelForTitle' )
			->with(
				$stubPageIdentity,
				'en'
			)
			->willReturn( new TermFallback( $langCode, $englishLabel, $langCode, null ) );
		$fakeLinkHtml = '<a>English Label</a>';
		$linkRenderer = $this->createMock( LinkRenderer::class );
		$linkRenderer->expects( $this->once() )
			->method( 'makePreloadedLink' )
			->with(
				$this->callback( $this->getCallbackToAssertLinkTarget( $schemaId ) ),
				$englishLabel,
				'',
				[ 'lang' => $langCode ]
			)
			->willReturn( $fakeLinkHtml );

		$sut = new EntitySchemaFormatter(
			$format,
			$options,
			$linkRenderer,
			$mockLabelLookup,
			$mockTitleFactory,
			$stubLanguageNameLookupFactory
		);

		$this->assertSame( $fakeLinkHtml, $sut->format( new EntitySchemaValue( new EntitySchemaId( $schemaId ) ) ) );
	}

	/**
	 * @dataProvider provideHtmlCases
	 */
	public function testHtmlWithFallbackLabel( string $format ): void {
		$schemaId = 'E1234';
		$englishLabel = 'English Label';
		$langCode = 'en';
		$requestLangCode = 'de';
		$options = new FormatterOptions( [ ValueFormatter::OPT_LANG => $requestLangCode ] );
		$stubPageIdentity = $this->createStub( Title::class );
		$mockTitleFactory = $this->createMock( TitleFactory::class );
		$mockTitleFactory->expects( $this->once() )
			->method( 'newFromText' )
			->with( $schemaId, NS_ENTITYSCHEMA_JSON )
			->willReturn( $stubPageIdentity );
		$stubLanguageNameLookupFactory = $this->createStub( LanguageNameLookupFactory::class );
		$mockLabelLookup = $this->createMock( LabelLookup::class );
		$mockLabelLookup->expects( $this->once() )
			->method( 'getLabelForTitle' )
			->with(
				$stubPageIdentity,
				$requestLangCode
			)
			->willReturn( new TermFallback( $requestLangCode, $englishLabel, $langCode, null ) );
		$fakeLinkHtml = '<a>English Label</a>';
		$linkRenderer = $this->createMock( LinkRenderer::class );
		$linkRenderer->expects( $this->once() )
			->method( 'makePreloadedLink' )
			->with(
				$this->callback( $this->getCallbackToAssertLinkTarget( $schemaId ) ),
				$englishLabel,
				'',
				[ 'lang' => $langCode ]
			)
			->willReturn( $fakeLinkHtml );

		$sut = new EntitySchemaFormatter(
			$format,
			$options,
			$linkRenderer,
			$mockLabelLookup,
			$mockTitleFactory,
			$stubLanguageNameLookupFactory
		);

		// expect that LanguageFallbackIndicator adds some element after the main HTML,
		// without asserting its exact contents
		$this->assertStringStartsWith(
			$fakeLinkHtml . "\u{00A0}<",
			$sut->format( new EntitySchemaValue( new EntitySchemaId( $schemaId ) ) ) );
	}

	private function getCallbackToAssertLinkTarget( string $expectedText ): callable {
		return static function ( LinkTarget $title ) use ( $expectedText ) {
			return $title->getNamespace() === NS_ENTITYSCHEMA_JSON
				&& $title->getText() === $expectedText;
		};
	}
}
