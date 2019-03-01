<?php

namespace Wikibase\Schema\MediaWiki\Actions;

use EditAction;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use PermissionsError;
use ReadOnlyError;
use Status;
use UserBlockedError;
use WebRequest;

/**
 * @license GPL-2.0-or-later
 */
abstract class AbstractRestoreAction extends EditAction {

	public function getRestriction() {
		return $this->getName();
	}

	protected function getRevisionFromRequest( WebRequest $req ): Status {
		$restoreID = $req->getText( 'restore' );
		$revStore = MediaWikiServices::getInstance()->getRevisionStore();
		$revToRestore = $revStore->getRevisionById( $restoreID );
		if ( $revToRestore === null || $revToRestore->isDeleted( RevisionRecord::DELETED_TEXT ) ) {
			return Status::newFatal( $this->msg( 'wikibaseschema-restore-bad-revisions' ) );
		}

		if ( $revToRestore->getPageId() !== $this->getTitle()->getArticleID() ) {
			return Status::newFatal( $this->msg( 'wikibaseschema-error-wrong-page-revisions' ) );
		}

		return Status::newGood( $revToRestore );
	}

	/**
	 * @throws ReadOnlyError
	 * @throws UserBlockedError
	 * @throws PermissionsError
	 */
	protected function checkPermissions() {
		if ( $this->getUser()->isBlockedFrom( $this->getTitle() ) ) {
			throw new UserBlockedError( $this->getUser()->getBlock() );
		}

		if ( wfReadOnly() ) {
			throw new ReadOnlyError;
		}

		if ( !$this->getUser()->isAllowed( $this->getRestriction() ) ) {
			throw new PermissionsError( $this->getRestriction() );
		}
	}

	/**
	 * Output an error page showing the given status
	 *
	 * @param Status $status The status to report.
	 */
	protected function showRestoreErrorPage( Status $status ) {
		$this->getOutput()->prepareErrorPage(
			$this->msg( 'wikibaseschema-restore-heading-failed' ),
			$this->msg( 'errorpagetitle' )
		);

		$this->getOutput()->addHTML( $status->getMessage()->parse() );

		$this->getOutput()->returnToMain( null, $this->getTitle() );
	}

}
