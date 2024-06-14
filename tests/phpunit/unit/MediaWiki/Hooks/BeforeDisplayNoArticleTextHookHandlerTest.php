<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\MediaWiki\Hooks;

use Article;
use EntitySchema\MediaWiki\Hooks\BeforeDisplayNoArticleTextHookHandler;
use EntitySchema\Tests\Unit\EntitySchemaUnitTestCaseTrait;
use Language;
use MediaWiki\Context\IContextSource;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\BeforeDisplayNoArticleTextHookHandler
 */
class BeforeDisplayNoArticleTextHookHandlerTest extends MediaWikiUnitTestCase {
	use EntitySchemaUnitTestCaseTrait;

	public function testDoesNothingForOtherNamespaces(): void {
		$hookHandler = new BeforeDisplayNoArticleTextHookHandler( true );
		$title = Title::makeTitle( NS_MEDIAWIKI, 'M1' );
		$mockArticle = $this->getMockArticle( $title, $this->never() );
		$result = $hookHandler->onBeforeDisplayNoArticleText( $mockArticle );
		$this->assertTrue( $result );
	}

	public function testDoesNothingIfRepoDisabled(): void {
		$hookHandler = new BeforeDisplayNoArticleTextHookHandler( false );
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, 'M1' );
		$mockArticle = $this->getMockArticle( $title, $this->never() );
		$result = $hookHandler->onBeforeDisplayNoArticleText( $mockArticle );
		$this->assertTrue( $result );
	}

	public function testReplacesStandardMessageForNoArticle(): void {
		$hookHandler = new BeforeDisplayNoArticleTextHookHandler( true );
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
