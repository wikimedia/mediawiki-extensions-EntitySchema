<?php

namespace Wikibase\Schema\MediaWiki\Actions;

use Diff\DiffOp\Diff\Diff;
use DomainException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use Status;
use ViewAction;
use WebRequest;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\MediaWiki\UndoHandler;

/**
 * @license GPL-2.0-or-later
 */
abstract class AbstractUndoAction extends ViewAction {

	public function getRestriction() {
		return $this->getName();
	}

	protected function getDiffFromRequest( WebRequest $req ): Status {

		$revStore = MediaWikiServices::getInstance()->getRevisionStore();

		$newerRevision = $revStore->getRevisionById( $req->getInt( 'undo' ) );
		$olderRevision = $revStore->getRevisionById( $req->getInt( 'undoafter' ) );

		if ( $newerRevision === null || $olderRevision === null ) {
			return Status::newFatal( 'wikibaseschema-undo-bad-revisions' );
		}

		/** @var WikibaseSchemaContent $undoFromContent */
		$undoFromContent = $newerRevision->getContent( SlotRecord::MAIN );
		/** @var WikibaseSchemaContent $undoToContent */
		$undoToContent = $olderRevision->getContent( SlotRecord::MAIN );
		$undoHandler = new UndoHandler();
		try {
			$undoHandler->validateContentIds( $undoFromContent, $undoToContent );
		} catch ( DomainException $e ) {
			return Status::newFatal( 'wikibaseschema-error-inconsistent-id' );
		}

		return $undoHandler->getDiffFromContents( $undoFromContent, $undoToContent );
	}

	/**
	 * Try applying the diff to the latest revision of this page.
	 *
	 * @param Diff $diff
	 *
	 * @return Status contains array of the patched schema data and the revision that was patched
	 */
	protected function tryPatching( Diff $diff ): Status {
		$revStore = MediaWikiServices::getInstance()->getRevisionStore();
		$baseRevId = $this->getTitle()->getLatestRevID();
		/** @var WikibaseSchemaContent $baseContent */
		$baseContent = $revStore
			->getRevisionById( $baseRevId )
			->getContent( SlotRecord::MAIN );
		$undoHandler = new UndoHandler();
		$status = $undoHandler->tryPatching( $diff, $baseContent );
		if ( $status->isGood() ) {
			return Status::newGood( [ $status->getValue(), $baseRevId ] );
		}

		return $status;
	}

	/**
	 * Output an error page showing the given status
	 *
	 * @param Status $status The status to report.
	 */
	protected function showUndoErrorPage( Status $status ) {
		$this->getOutput()->prepareErrorPage(
			$this->msg( 'wikibaseschema-undo-heading-failed' ),
			$this->msg( 'errorpagetitle' )
		);

		$this->getOutput()->addHTML( $status->getMessage()->parse() );

		$this->getOutput()->returnToMain();
	}

}
