<?php

declare( strict_types = 1 );

namespace phpunit\unit\MediaWiki\Hooks;

use Article;
use EntitySchema\MediaWiki\Hooks\BeforeDisplayNoArticleTextHookHandler;
use IContextSource;
use Language;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;
use OutputPage;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\BeforeDisplayNoArticleTextHookHandler
 */
class BeforeDisplayNoArticleTextHookHandlerTest extends MediaWikiUnitTestCase {
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( !defined( 'NS_ENTITYSCHEMA_JSON' ) ) {
			// defined through extension.json, which is not loaded in unit tests
			define( 'NS_ENTITYSCHEMA_JSON', 640 );
		}
	}

	public function testDoesNothingForOtherNamespaces(): void {
		$hookHandler = new BeforeDisplayNoArticleTextHookHandler();
		$title = Title::makeTitle( NS_MEDIAWIKI, 'M1' );
		$mockArticle = $this->getMockArticle( $title, $this->never() );
		$result = $hookHandler->onBeforeDisplayNoArticleText( $mockArticle );
		$this->assertTrue( $result );
	}

	public function testReplacesStandardMessageForNoArticle(): void {
		$hookHandler = new BeforeDisplayNoArticleTextHookHandler();
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'M1' );
		$mockArticle = $this->getMockArticle( $title, $this->once() );
		$result = $hookHandler->onBeforeDisplayNoArticleText( $mockArticle );
		$this->assertFalse( $result );
	}

	private function getMockArticle( Title $title, $expectedWrapWikiMsgCalls ): Article {
		return $this->createConfiguredMock( Article::class, [
			'getContext' => $this->getMockContext( $expectedWrapWikiMsgCalls ),
			'getTitle' => $title,
		] );
	}

	private function getMockContext( $expectedWrapWikiMsgCalls ): IContextSource {
		return $this->createConfiguredMock( IContextSource::class, [
			'getLanguage' => $this->getMockLanguage(),
			'getOutput' => $this->getMockPageOutput( $expectedWrapWikiMsgCalls ),
		] );
	}

	private function getMockLanguage(): Language {
		return $this->createConfiguredMock( Language::class, [
			'getDir' => 'ltr',
			'getHtmlCode' => 'en',
		] );
	}

	private function getMockPageOutput( $expectedWrapWikiMsgCalls ): OutputPage {
		$mockPageOutput = $this->getMockBuilder( OutputPage::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'wrapWikiMsg' ] )
			->getMock();
		$mockPageOutput->expects( $expectedWrapWikiMsgCalls )->method( 'wrapWikiMsg' );
		return $mockPageOutput;
	}
}
