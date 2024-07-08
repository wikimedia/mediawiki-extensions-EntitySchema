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
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Term\TermFallback;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Lib\LanguageNameLookupFactory;

/**
 * @covers \EntitySchema\Wikibase\Formatters\EntitySchemaFormatter
 * @group EntitySchemaClient
 * @license GPL-2.0-or-later
 */
class EntitySchemaFormatterTest extends MediaWikiIntegrationTestCase {

	/**
	 * @var LinkRenderer|MockObject
	 */
	private $mockLinkRenderer;

	/**
	 * @var LabelLookup|MockObject
	 */
	private $mockLabelLookup;

	/**
	 * @var TitleFactory|MockObject
	 */
	private $mockTitleFactory;

	public function setUp(): void {
		$this->mockLinkRenderer = $this->createMock( LinkRenderer::class );
		$this->mockLabelLookup = $this->createMock( LabelLookup::class );
		$this->mockTitleFactory = $this->createMock( TitleFactory::class );
	}

	public static function provideTextFormats(): iterable {
		return [
			[ SnakFormatter::FORMAT_PLAIN, 'E984', 'English Label' ],
			[ SnakFormatter::FORMAT_WIKI, '[[EntitySchema:E984]]', '[[EntitySchema:E984|English Label]]' ],
		];
	}

	private function registerTitleWithLabel(
		string $schemaId,
		string $requestLangCode,
		?TermFallback $label
	): void {
		$stubPageIdentity = $this->createMock( Title::class );
		$stubPageIdentity->expects( $this->any() )
			->method( 'getText' )
			->willReturn( $schemaId );
		$this->mockTitleFactory->expects( $this->once() )
			->method( 'newFromText' )
			->with( $schemaId, NS_ENTITYSCHEMA_JSON )
			->willReturn( $stubPageIdentity );
		$this->mockLabelLookup->expects( $this->once() )
			->method( 'getLabelForTitle' )
			->with(
				$stubPageIdentity,
				$requestLangCode
			)
			->willReturn( $label );
	}

	private function createFormatter(
		string $format,
		FormatterOptions $options,
		bool $stubLanguageNameLookupFactory = true
	): EntitySchemaFormatter {
		$languageNameLookupFactory = $this->createStub( LanguageNameLookupFactory::class );
		if ( $stubLanguageNameLookupFactory === false ) {
			$languageNameLookupFactory = $this->createMock( LanguageNameLookupFactory::class );
			$languageNameLookupFactory->expects( $this->never() )
				->method( $this->anything() );
		}
		return new EntitySchemaFormatter(
			$format,
			$options,
			$this->mockLinkRenderer,
			$this->mockLabelLookup,
			$this->mockTitleFactory,
			$languageNameLookupFactory
		);
	}

	/**
	 * @dataProvider provideTextFormats
	 */
	public function testTextFormatsNoLabel(
		string $format,
		string $expectedResultNoLabel,
		string $expectedResultLabel
	): void {
		$schemaId = 'E984';
		$this->mockLinkRenderer->expects( $this->never() )
			->method( $this->anything() );
		$options = new FormatterOptions( [ ValueFormatter::OPT_LANG => 'en' ] );
		$this->registerTitleWithLabel( $schemaId, 'en', null );
		$sut = $this->createFormatter( $format, $options, false );

		$this->assertSame(
			$expectedResultNoLabel,
			$sut->format( new EntitySchemaValue( new EntitySchemaId( $schemaId ) ) )
		);
	}

	/**
	 * @dataProvider provideTextFormats
	 */
	public function testTextFormatsWithLabel(
		string $format,
		string $expectedResultNoLabel,
		string $expectedResultLabel
	): void {
		$schemaId = 'E984';
		$englishLabel = 'English Label';
		$langCode = 'en';
		$options = new FormatterOptions( [ ValueFormatter::OPT_LANG => $langCode ] );
		$this->registerTitleWithLabel( $schemaId, 'en',
			new TermFallback( $langCode, $englishLabel, $langCode, null ) );

		$sut = $this->createFormatter( $format, $options, false );

		$this->assertSame(
			$expectedResultLabel,
			$sut->format( new EntitySchemaValue( new EntitySchemaId( $schemaId ) ) )
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
		$this->registerTitleWithLabel( $schemaId, 'en', null );
		$fakeLinkHtml = '<a>E123</a>';
		$this->mockLinkRenderer->expects( $this->once() )
			->method( 'makePreloadedLink' )
			->with(
				$this->callback( $this->getCallbackToAssertLinkTarget( $schemaId ) ),
				$schemaId
			)
			->willReturn( $fakeLinkHtml );

		$sut = $this->createFormatter( $format, $options );

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
		$this->registerTitleWithLabel( $schemaId, 'en',
			new TermFallback( $langCode, $englishLabel, $langCode, null ) );
		$fakeLinkHtml = '<a>English Label</a>';
		$this->mockLinkRenderer->expects( $this->once() )
			->method( 'makePreloadedLink' )
			->with(
				$this->callback( $this->getCallbackToAssertLinkTarget( $schemaId ) ),
				$englishLabel,
				'',
				[ 'lang' => $langCode ]
			)
			->willReturn( $fakeLinkHtml );

		$sut = $this->createFormatter( $format, $options );

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
		$this->registerTitleWithLabel( $schemaId, $requestLangCode,
			new TermFallback( $requestLangCode, $englishLabel, $langCode, null ) );

		$fakeLinkHtml = '<a>English Label</a>';
		$this->mockLinkRenderer->expects( $this->once() )
			->method( 'makePreloadedLink' )
			->with(
				$this->callback( $this->getCallbackToAssertLinkTarget( $schemaId ) ),
				$englishLabel,
				'',
				[ 'lang' => $langCode ]
			)
			->willReturn( $fakeLinkHtml );

		$sut = $this->createFormatter( $format, $options );

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
