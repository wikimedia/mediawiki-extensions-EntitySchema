<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Actions;

use CommentStoreComment;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Services\Converter\FullArrayEntitySchemaData;
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

	public function getName(): string {
		return 'submit';
	}

	public function getRestriction(): string {
		return 'edit';
	}

	public function show(): void {
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

		[ $patchedSchema, $baseRevId ] = $patchStatus->getValue();
		return $this->storePatchedSchema( $patchedSchema, $baseRevId );
	}

	private function storePatchedSchema( FullArrayEntitySchemaData $patchedSchema, int $baseRevId ): Status {
		$schemaUpdater = MediaWikiRevisionEntitySchemaUpdater::newFromContext( $this->getContext() );

		$summary = $this->createSummaryCommentForUndoRev(
			$this->getContext()->getRequest()->getText( 'wpSummary' ),
			$this->getContext()->getRequest()->getInt( 'undo' )
			);

		try {
			$schemaUpdater->overwriteWholeSchema(
				new EntitySchemaId( $this->getTitle()->getTitleValue()->getText() ),
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

	private function createSummaryCommentForUndoRev( string $userSummary, int $undoRevId ): CommentStoreComment {
		$revToBeUndone = MediaWikiServices::getInstance()
			->getRevisionStore()
			->getRevisionById( $undoRevId );
		$userName = $revToBeUndone->getUser()->getName();
		$autoComment = MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UNDO
			. ':' . $undoRevId
			. ':' . $userName;
		return CommentStoreComment::newUnsavedComment(
			'/* ' . $autoComment . ' */' . $userSummary,
			[
				'key' => MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_UNDO,
				'summary' => $userSummary,
				'undoRevId' => $undoRevId,
				'userName' => $userName,
			]
		);
	}

}
