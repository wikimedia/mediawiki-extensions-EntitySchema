<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Formatters;

use DataValues\StringValue;
use EntitySchema\Wikibase\Formatters\EntitySchemaFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWikiUnitTestCase;
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

		$sut = new EntitySchemaFormatter( $format, $linkRenderer );

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
	public function testHtmlLinks( string $format ): void {
		$fakeLinkHtml = '<a>E123</a>';
		$linkRenderer = $this->createMock( LinkRenderer::class );
		$linkRenderer->expects( $this->once() )
			->method( 'makePreloadedLink' )
			->with(
				$this->callback( static function ( LinkTarget $title ) {
					return $title->getNamespace() === NS_ENTITYSCHEMA_JSON
						&& $title->getText() === 'E123';
				} ),
				'E123'
			)
			->willReturn( $fakeLinkHtml );

		$sut = new EntitySchemaFormatter( $format, $linkRenderer );

		$this->assertSame( $fakeLinkHtml, $sut->format( new StringValue( 'E123' ) ) );
	}
}
