<?php

namespace EntitySchema\MediaWiki\Actions;

use EditAction;
use Html;
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
		return 'edit';
	}

	protected function getRevisionFromRequest( WebRequest $req ): Status {
		$restoreID = $req->getInt( 'restore' );
		$revStore = MediaWikiServices::getInstance()->getRevisionStore();
		$revToRestore = $revStore->getRevisionById( $restoreID );
		if ( $revToRestore === null || $revToRestore->isDeleted( RevisionRecord::DELETED_TEXT ) ) {
			return Status::newFatal( $this->msg( 'entityschema-restore-bad-revisions' ) );
		}

		if ( $revToRestore->getPageId() !== $this->getTitle()->getArticleID() ) {
			return Status::newFatal( $this->msg( 'entityschema-error-wrong-page-revisions' ) );
		}

		return Status::newGood( $revToRestore );
	}

	/**
	 * @throws ReadOnlyError
	 * @throws UserBlockedError
	 * @throws PermissionsError
	 */
	protected function checkPermissions() {
		$pm = MediaWikiServices::getInstance()->getPermissionManager();
		$checkReplica = !$this->getRequest()->wasPosted();

		$permissionErrors = $pm->getPermissionErrors(
			$this->getRestriction(),
			$this->getUser(),
			$this->getTitle(),
			$checkReplica ? $pm::RIGOR_FULL : $pm::RIGOR_SECURE
		);
		if ( $permissionErrors !== [] ) {
			throw new PermissionsError( $this->getRestriction(), $permissionErrors );
		}

		if ( wfReadOnly() ) {
			throw new ReadOnlyError;
		}
	}

	/**
	 * Output an error page showing the given status
	 *
	 * @param Status $status The status to report.
	 */
	protected function showRestoreErrorPage( Status $status ) {
		$this->getOutput()->prepareErrorPage(
			$this->msg( 'entityschema-restore-heading-failed' ),
			$this->msg( 'errorpagetitle' )
		);

		$this->getOutput()->addHTML( Html::errorBox( $status->getMessage()->parse() ) );

		$this->getOutput()->returnToMain( null, $this->getTitle() );
	}

}
