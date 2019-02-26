<?php

namespace Wikibase\Schema\MediaWiki\Actions;

use MediaWiki\MediaWikiServices;
use Message;
use PermissionsError;
use ReadOnlyError;
use RuntimeException;
use Status;
use UserBlockedError;
use Wikibase\Schema\DataAccess\MediaWikiPageUpdaterFactory;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter;
use Wikibase\Schema\DataAccess\SqlIdGenerator;
use Wikibase\Schema\DataAccess\WatchlistUpdater;
use Wikibase\Schema\Domain\Model\SchemaId;

/**
 * @license GPL-2.0-or-later
 */
class UndoSubmitAction extends AbstractUndoAction {

	public function getName() {
		return 'submit';
	}

	public function getRestriction() {
		return 'edit';
	}

	public function show() {
		$permStatus = $this->checkPermissions();
		if ( !$permStatus->isOK() ) {
			$this->showUndoErrorPage( $permStatus );
			return;
		}

		$undoStatus = $this->undo();
		if ( !$undoStatus->isOK() ) {
			$this->showUndoErrorPage( $undoStatus );
		}

		$this->getOutput()->redirect(
			$this->getTitle()->getFullURL()
		);
	}

	/**
	 * @throws ReadOnlyError
	 * @throws UserBlockedError
	 * @throws PermissionsError
	 */
	private function checkPermissions(): Status {
		$method = $this->getContext()->getRequest()->getMethod();
		if ( $method !== 'POST' ) {
			return Status::newFatal( 'wikibaseschema-error-not-post' );
		}

		if ( $this->getUser()->isBlockedFrom( $this->getTitle() ) ) {
			throw new UserBlockedError( $this->getUser()->getBlock() );
		}

		if ( wfReadOnly() ) {
			throw new ReadOnlyError;
		}

		if ( !$this->getUser()->isAllowed( $this->getRestriction() ) ) {
			throw new PermissionsError( $this->getRestriction() );
		}

		return Status::newGood();
	}

	private function undo(): Status {
		$req = $this->context->getRequest();

		$diffStatus = $this->getDiffFromRequest( $req );
		if ( !$diffStatus->isOK() ) {
			return $diffStatus;
		}

		$patchStatus = $this->tryPatching( $diffStatus->getValue() );
		if ( !$patchStatus->isOK() ) {
			return $patchStatus;
		}

		return $this->storePatchedSchema( $patchStatus->getValue() );
	}

	private function storePatchedSchema( $patchedSchema ): Status {
		$schemaWriter = new MediawikiRevisionSchemaWriter(
			new MediaWikiPageUpdaterFactory( $this->getUser() ),
			$this,
			new WatchlistUpdater( $this->getUser(), NS_WBSCHEMA_JSON ),
			new SqlIdGenerator(
				MediaWikiServices::getInstance()->getDBLoadBalancer(),
				'wbschema_id_counter'
			)
		);

		$submitMessage = $this->createSummaryMessageForUndoRev(
			$this->context->getRequest()->getText( 'wpSummary' ),
			$this->context->getRequest()->getInt( 'undo' )
			);

		try {
			$schemaWriter->updateSchema(
				new SchemaId( $this->getTitle()->getTitleValue()->getText() ),
				'en',
				isset( $patchedSchema['labels'] ) ? $patchedSchema['labels']['en'] : '',
				isset( $patchedSchema['descriptions'] ) ? $patchedSchema['descriptions']['en'] : '',
				isset( $patchedSchema['aliases'] ) ? $patchedSchema['aliases']['en'] : [],
				$patchedSchema['schema'] ?? '',
				$submitMessage
			);
		} catch ( RuntimeException $e ) {
			return Status::newFatal( 'wikibaseschema-error-saving-failed', $e->getMessage() );
		}

		return Status::newGood();
	}

	private function createSummaryMessageForUndoRev( $userSummary, $undoRevId ): Message {
		$revToBeUndone = MediaWikiServices::getInstance()
			->getRevisionStore()
			->getRevisionById( $undoRevId );
		$user = $revToBeUndone->getUser();
		return $this->msg( 'wikibaseschema-summary-undo' )
			->params( $revToBeUndone->getId() )
			->params( $user )
			->plaintextParams( $userSummary );
	}

}
