<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\Hooks\PageHistoryLineEndingHandler;
use HistoryPager;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Tests\Unit\FakeQqxMessageLocalizer;
use MediaWiki\Title\Title;
use MediaWikiUnitTestCase;
use stdClass;
use User;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \EntitySchema\MediaWiki\Hooks\PageHistoryLineEndingHandler
 */
class PageHistoryLineEndingHandlerTest extends MediaWikiUnitTestCase {

	public function testAddsLinkToRevisionHTML(): void {
		$title = $this->getEntitySchemaTitle();
		$revisionId = 5;
		$user = $this->createStub( User::class );
		$sut = new PageHistoryLineEndingHandler(
			$this->getMockLinkRenderer( $title, $revisionId ),
			$this->getMockPermissionManager( true, $user, $title ),
			$this->getMockRevisionStore(
				$this->getMockRevisionRecord( $revisionId, false )
			)
		);

		$historyActionStub = $this->getHistoryActionMock( $title, $user );
		$html = '<b>Lorem Ipsum</b>';
		$row = new stdClass();
		$classes = [];
		$attribs = [];
		$sut->onPageHistoryLineEnding( $historyActionStub, $row, $html, $classes, $attribs );

		$this->assertSame( '<b>Lorem Ipsum</b> (parentheses: <a>link</a>)', $html );
	}

	public function testDoesNothingForDifferentContentModel(): void {
		$sut = new PageHistoryLineEndingHandler(
			$this->getMockLinkRenderer( null ),
			$this->getMockPermissionManager( null ),
			$this->getMockRevisionStore( null )
		);

		$title = $this->getNonEntitySchemaTitle();
		$historyActionStub = $this->getHistoryActionMock( $title );
		$row = new stdClass();
		$html = '<b>Lorem Ipsum</b>';
		$classes = [];
		$attribs = [];

		$sut->onPageHistoryLineEnding( $historyActionStub, $row, $html, $classes, $attribs );

		$this->assertSame( '<b>Lorem Ipsum</b>', $html );
	}

	public function testDoesNothingForLatestRevision(): void {
		$revisionId = 8;
		$sut = new PageHistoryLineEndingHandler(
			$this->getMockLinkRenderer( null ),
			$this->getMockPermissionManager( null ),
			$this->getMockRevisionStore(
				$this->getMockRevisionRecord( $revisionId )
			)
		);

		$title = $this->getEntitySchemaTitle( $revisionId );
		$historyActionStub = $this->getHistoryActionMock( $title );
		$row = new stdClass();
		$html = '<b>Lorem Ipsum</b>';
		$classes = [];
		$attribs = [];

		$sut->onPageHistoryLineEnding( $historyActionStub, $row, $html, $classes, $attribs );

		$this->assertSame( '<b>Lorem Ipsum</b>', $html );
	}

	public function testDoesNothingForDeletedRevision(): void {
		$sut = new PageHistoryLineEndingHandler(
			$this->getMockLinkRenderer( null ),
			$this->getMockPermissionManager( null ),
			$this->getMockRevisionStore(
				$this->getMockRevisionRecord( 5, true )
			),
		);

		$title = $this->getEntitySchemaTitle();
		$historyActionStub = $this->getHistoryActionMock( $title );
		$row = new stdClass();
		$html = '<b>Lorem Ipsum</b>';
		$classes = [];
		$attribs = [];

		$sut->onPageHistoryLineEnding( $historyActionStub, $row, $html, $classes, $attribs );

		$this->assertSame( '<b>Lorem Ipsum</b>', $html );
	}

	public function testDoesNothingIfUserCannotEdit(): void {
		$user = $this->createStub( User::class );
		$title = $this->getEntitySchemaTitle();
		$sut = new PageHistoryLineEndingHandler(
			$this->getMockLinkRenderer( null ),
			$this->getMockPermissionManager( false, $user, $title ),
			$this->getMockRevisionStore(
				$this->getMockRevisionRecord( 5, false )
			)
		);

		$historyActionStub = $this->getHistoryActionMock( $title, $user );
		$row = new stdClass();
		$html = '<b>Lorem Ipsum</b>';
		$classes = [];
		$attribs = [];

		$sut->onPageHistoryLineEnding( $historyActionStub, $row, $html, $classes, $attribs );

		$this->assertSame( '<b>Lorem Ipsum</b>', $html );
	}

	private function getMockRevisionStore( ?RevisionRecord $revisionRecordToReturn ): RevisionStore {
		$revisionStore = $this->createMock( RevisionStore::class );
		if ( $revisionRecordToReturn === null ) {
			$revisionStore->expects( $this->never() )
				->method( 'newRevisionFromRow' );
		} else {
			$revisionStore->expects( $this->once() )
				->method( 'newRevisionFromRow' )
				->willReturn( $revisionRecordToReturn );
		}
		return $revisionStore;
	}

	private function getMockPermissionManager(
		?bool $canEdit,
		User $expectedUser = null,
		Title $expectedTitle = null
	): PermissionManager {
		$permissionManager = $this->createMock( PermissionManager::class );
		if ( $canEdit === null ) {
			$permissionManager
				->expects( $this->never() )
				->method( 'quickUserCan' );
		} else {
			$permissionManager
				->expects( $this->once() )
				->method( 'quickUserCan' )
				->with(
					'edit',
					$expectedUser,
					$expectedTitle
				)
				->willReturn( $canEdit );
		}
		return $permissionManager;
	}

	private function getEntitySchemaTitle( $latestRevisionId = 99999 ): Title {
		$stubTitle = $this->createStub( Title::class );
		$stubTitle->method( 'getLatestRevID' )->willReturn( $latestRevisionId );
		$stubTitle->method( 'getContentModel' )->willReturn( EntitySchemaContent::CONTENT_MODEL_ID );
		return $stubTitle;
	}

	private function getNonEntitySchemaTitle(): Title {
		$stubTitle = $this->createStub( Title::class );
		$stubTitle->method( 'getContentModel' )->willReturn( 'wikibase-item' );
		return $stubTitle;
	}

	private function getMockRevisionRecord( int $revId, bool $isDeleted = null ): RevisionRecord {
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getId' )->willReturn( $revId );
		if ( $isDeleted === null ) {
			$revisionRecord->expects( $this->never() )->method( 'isDeleted' );
		} else {
			$revisionRecord
				->expects( $this->once() )
				->method( 'isDeleted' )
				->with( RevisionRecord::DELETED_TEXT )
				->willReturn( $isDeleted );
		}
		return $revisionRecord;
	}

	private function getHistoryActionMock( Title $title, User $user = null ) {
		$historyActionStub = $this->createStub( HistoryPager::class );
		$historyActionStub->method( 'getTitle' )->willReturn( $title );
		if ( $user ) {
			$historyActionStub->method( 'getUser' )->willReturn( $user );
		}
		$historyActionStub->method( 'msg' )->willReturnCallback(
			[ new FakeQqxMessageLocalizer(), 'msg' ]
		);
		return $historyActionStub;
	}

	private function getMockLinkRenderer( ?Title $title, int $revisionId = null ): LinkRenderer {
		$linkRendererMock = $this->createMock( LinkRenderer::class );
		if ( $title === null ) {
			$linkRendererMock->expects( $this->never() )->method( 'makeKnownLink' );
		} else {
			$linkRendererMock
				->expects( $this->once() )
				->method( 'makeKnownLink' )
				->with(
					$this->equalTo( $title ),
					$this->equalTo( '(entityschema-restoreold)' ),
					$this->equalTo( [] ),
					$this->equalTo(
						[
							'action' => 'edit',
							'restore' => $revisionId,
						]
					)
				)
				->willReturn( '<a>link</a>' );
		}
		return $linkRendererMock;
	}
}
