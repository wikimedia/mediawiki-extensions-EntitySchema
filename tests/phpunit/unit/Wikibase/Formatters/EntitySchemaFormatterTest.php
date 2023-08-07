<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Formatters;

use DataValues\StringValue;
use EntitySchema\DataAccess\EntitySchemaTerm;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\Wikibase\Formatters\EntitySchemaFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiUnitTestCase;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\Lib\Formatters\SnakFormatter;

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

	public static function provideUnhandledFormats(): iterable {
		return [
			[ SnakFormatter::FORMAT_PLAIN ],
			[ SnakFormatter::FORMAT_WIKI ],
		];
	}

	/**
	 * @dataProvider provideUnhandledFormats
	 */
	public function testUnhandledFormats( string $format ): void {
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
		$sut = new EntitySchemaFormatter( $format, $options, $linkRenderer, $mockLabelLookup, $mockTitleFactory );

		$this->assertSame( 'E123', $sut->format( new StringValue( 'E123' ) ) );
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

		$sut = new EntitySchemaFormatter( $format, $options, $linkRenderer, $mockLabelLookup, $mockTitleFactory );

		$this->assertSame( $fakeLinkHtml, $sut->format( new StringValue( $schemaId ) ) );
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
		$mockLabelLookup = $this->createMock( LabelLookup::class );
		$mockLabelLookup->expects( $this->once() )
			->method( 'getLabelForTitle' )
			->with(
				$stubPageIdentity,
				'en'
			)
			->willReturn( new EntitySchemaTerm( $langCode, $englishLabel ) );
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

		$sut = new EntitySchemaFormatter( $format, $options, $linkRenderer, $mockLabelLookup, $mockTitleFactory );

		$this->assertSame( $fakeLinkHtml, $sut->format( new StringValue( $schemaId ) ) );
	}

	private function getCallbackToAssertLinkTarget( string $expectedText ): callable {
		return static function ( LinkTarget $title ) use ( $expectedText ) {
			return $title->getNamespace() === NS_ENTITYSCHEMA_JSON
				&& $title->getText() === $expectedText;
		};
	}
}
