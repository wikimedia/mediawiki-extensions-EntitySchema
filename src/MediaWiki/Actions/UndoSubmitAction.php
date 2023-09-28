<?php

namespace EntitySchema\MediaWiki\Actions;

use CommentStoreComment;
use EntitySchema\DataAccess\MediaWikiRevisionSchemaUpdater;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\Services\SchemaConverter\FullArraySchemaData;
use MediaWiki\MediaWikiServices;
use PermissionsError;
use ReadOnlyError;
use RuntimeException;
use Status;
use UserBlockedError;

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
			return Status::newFatal( 'entityschema-error-not-post' );
		}

		$services = MediaWikiServices::getInstance();
		$pm = $services->getPermissionManager();

		$permissionErrors = $pm->getPermissionErrors(
			$this->getRestriction(),
			$this->getUser(),
			$this->getTitle()
		);
		if ( $permissionErrors !== [] ) {
			throw new PermissionsError( $this->getRestriction(), $permissionErrors );
		}

		if ( $services->getReadOnlyMode()->isReadOnly() ) {
			throw new ReadOnlyError;
		}

		return Status::newGood();
	}

	private function undo(): Status {
		$req = $this->getContext()->getRequest();

		$diffStatus = $this->getDiffFromRequest( $req );
		if ( !$diffStatus->isOK() ) {
			return $diffStatus;
		}

		$patchStatus = $this->tryPatching( $diffStatus->getValue() );
		if ( !$patchStatus->isOK() ) {
			return $patchStatus;
		}

		return $this->storePatchedSchema( ...$patchStatus->getValue() );
	}

	private function storePatchedSchema( FullArraySchemaData $patchedSchema, int $baseRevId ): Status {
		$schemaUpdater = MediaWikiRevisionSchemaUpdater::newFromContext( $this->getContext() );

		$summary = $this->createSummaryCommentForUndoRev(
			$this->getContext()->getRequest()->getText( 'wpSummary' ),
			$this->getContext()->getRequest()->getInt( 'undo' )
			);

		try {
			$schemaUpdater->overwriteWholeSchema(
				new SchemaId( $this->getTitle()->getTitleValue()->getText() ),
				$patchedSchema->data['labels'],
				$patchedSchema->data['descriptions'],
				$patchedSchema->data['aliases'],
				$patchedSchema->data['schemaText'],
				$baseRevId,
				$summary
			);
		} catch ( RuntimeException $e ) {
			return Status::newFatal( 'entityschema-error-saving-failed', $e->getMessage() );
		}

		return Status::newGood();
	}

	private function createSummaryCommentForUndoRev( $userSummary, $undoRevId ): CommentStoreComment {
		$revToBeUndone = MediaWikiServices::getInstance()
			->getRevisionStore()
			->getRevisionById( $undoRevId );
		$userName = $revToBeUndone->getUser()->getName();
		$autoComment = MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UNDO
			. ':' . $undoRevId
			. ':' . $userName;
		return CommentStoreComment::newUnsavedComment(
			'/* ' . $autoComment . ' */' . $userSummary,
			[
				'key' => MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_UNDO,
				'summary' => $userSummary,
				'undoRevId' => $undoRevId,
				'userName' => $userName
			]
		);
	}

}
