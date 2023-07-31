<?php
declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\MediaWiki\Hooks;

use EntitySchema\DataAccess\EntitySchemaTerm;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\MediaWiki\Hooks\HtmlPageLinkRendererEndHookHandler;
use Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;
use RequestContext;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\HtmlPageLinkRendererEndHookHandler
 *
 * @license GPL-2.0-or-later
 */
class HtmlPageLinkRendererEndHookHandlerTest extends MediaWikiUnitTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( !defined( 'NS_ENTITYSCHEMA_JSON' ) ) {
			// defined through extension.json, which is not loaded in unit tests
			define( 'NS_ENTITYSCHEMA_JSON', 640 );
		}
	}

	public static function scenarioProvider(): iterable {
		$originalText = 'E1';
		yield 'no Label' => [
			null,
			true,
			true,
			false,
			false,
			$originalText,
			$originalText,
		];

		$customText = 'add Schema text';
		yield 'with custom initial title text' => [
			'label from lookup',
			true,
			true,
			false,
			false,
			$customText,
			$customText,
		];

		yield 'with Label' => [
			'label from lookup',
			true,
			true,
			false,
			false,
			$originalText,
			'(wikibase-itemlink: label from lookup, (wikibase-itemlink-id-wrapper: E1))',
		];

		yield 'renders for a link in a comment' => [
			'label from lookup',
			true,
			false,
			null,
			true,
			$originalText,
			'(wikibase-itemlink: label from lookup, (wikibase-itemlink-id-wrapper: E1))',
		];

		yield 'not in schema namespace' => [
			'label from lookup',
			false,
			true,
			false,
			false,
			$originalText,
			$originalText,
		];

		yield 'not on special page and not a link in a comment' => [
			'label from lookup',
			true,
			false,
			null,
			false,
			$originalText,
			$originalText,
		];

		yield 'on BadTitle special page' => [
			'label from lookup',
			true,
			true,
			true,
			false,
			$originalText,
			$originalText,
		];
	}

	/**
	 * @dataProvider scenarioProvider
	 */
	public function testHookHandling(
		?string $labelReturnedByLookup,
		bool $targetIsInSchemaNamespace,
		bool $isOnSpecialPage,
		?bool $specialPageIsBadTitle,
		bool $isForLinkInComment,
		string $initialText,
		string $expectedText
	): void {
		$stubLabelLookup = $this->createStub( LabelLookup::class );
		$stubLabelLookup->method( 'getLabelForTitle' )
			->willReturn( $labelReturnedByLookup ? new EntitySchemaTerm( 'en', $labelReturnedByLookup ) : null );

		$stubLinkRenderer = $this->createStub( LinkRenderer::class );
		$stubLinkRenderer->method( 'isForComment' )->willReturn( $isForLinkInComment );

		$requestContext = $this->getRequestContext( $isOnSpecialPage, $specialPageIsBadTitle );

		$target = $this->createStub( Title::class );
		$target->method( 'inNamespace' )->willReturn( $targetIsInSchemaNamespace );
		$target->method( 'getText' )->willReturn( 'E1' );

		$hookHandler = new HtmlPageLinkRendererEndHookHandler(
			$stubLabelLookup,
			$requestContext
		);

		$textReference = $initialText;
		$extraAttribsReference = [];
		$htmlReference = null;
		$returnValue = $hookHandler->onHtmlPageLinkRendererEnd(
			$stubLinkRenderer,
			$target,
			true,
			$textReference,
			$extraAttribsReference,
			$htmlReference
		);

		$this->assertSame( $expectedText, $textReference );
		$this->assertTrue( $returnValue );
		$this->assertSame( [], $extraAttribsReference );
		$this->assertNull( $htmlReference );
	}

	private function getRequestContext(
		bool $isOnSpecialPage,
		?bool $specialPageIsBadTitle
	): RequestContext {
		$stubRequestContext = $this->createStub( RequestContext::class );
		$stubRequestContext->method( 'hasTitle' )->willReturn( true );
		$stubOutputTitle = $this->getOutputPageTitle( $isOnSpecialPage, $specialPageIsBadTitle );
		$stubRequestContext->method( 'getTitle' )->willReturn( $stubOutputTitle );
		$stubLanguage = $this->createStub( Language::class );
		$stubLanguage->method( 'getCode' )->willReturn( 'en' );
		$stubRequestContext->method( 'getLanguage' )->willReturn( $stubLanguage );
		$fakeMessageLocalizer = new FakeQqxMessageLocalizer();
		$stubRequestContext->method( 'msg' )->willReturnCallback(
			static function ( string $key, ...$params ) use ( $fakeMessageLocalizer ) {
				return $fakeMessageLocalizer->msg( $key, ...$params );
			}
		);

		return $stubRequestContext;
	}

	private function getOutputPageTitle( bool $isOnSpecialPage, ?bool $specialPageIsBadTitle ): Title {
		$stubOutputTitle = $this->createMock( Title::class );

		$stubOutputTitle->method( 'isSpecialPage' )->willReturn( $isOnSpecialPage );
		if ( $specialPageIsBadTitle === null ) {
			$stubOutputTitle->expects( $this->never() )->method( 'isSpecial' );
		} else {
			$stubOutputTitle->method( 'isSpecial' )->willReturn( $specialPageIsBadTitle );
		}

		return $stubOutputTitle;
	}

}
