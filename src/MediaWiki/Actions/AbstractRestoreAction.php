<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Actions;

use MediaWiki\Actions\EditAction;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Exception\ReadOnlyError;
use MediaWiki\Exception\UserBlockedError;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\WebRequest;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Status\Status;

/**
 * @license GPL-2.0-or-later
 */
abstract class AbstractRestoreAction extends EditAction {

	public function getRestriction(): string {
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
	protected function checkPermissions(): void {
		$services = MediaWikiServices::getInstance();
		$pm = $services->getPermissionManager();
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

		if ( $services->getReadOnlyMode()->isReadOnly() ) {
			throw new ReadOnlyError;
		}
	}

	/**
	 * Output an error page showing the given status
	 *
	 * @param Status $status The status to report.
	 */
	protected function showRestoreErrorPage( Status $status ): void {
		$this->getOutput()->prepareErrorPage();
		$this->getOutput()->setPageTitleMsg(
			$this->msg( 'entityschema-restore-heading-failed' )
		);
		$this->getOutput()->setHTMLTitle(
			$this->msg( 'errorpagetitle' )
		);

		$this->getOutput()->addHTML( Html::errorBox( $status->getMessage()->parse() ) );

		$this->getOutput()->returnToMain( null, $this->getTitle() );
	}

}
