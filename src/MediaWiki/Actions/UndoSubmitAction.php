<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Actions;

use EntitySchema\DataAccess\EntitySchemaStatus;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\EntitySchemaRedirectTrait;
use EntitySchema\Services\Converter\FullArrayEntitySchemaData;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\MediaWikiServices;
use MediaWiki\Status\Status;
use PermissionsError;
use ReadOnlyError;
use UserBlockedError;

/**
 * @license GPL-2.0-or-later
 */
class UndoSubmitAction extends AbstractUndoAction {

	use EntitySchemaRedirectTrait;

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
			return;
		}

		$this->redirectToEntitySchema( $undoStatus );
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

	private function undo(): EntitySchemaStatus {
		$req = $this->getContext()->getRequest();

		$diffStatus = $this->getDiffFromRequest( $req );
		if ( !$diffStatus->isOK() ) {
			return EntitySchemaStatus::wrap( $diffStatus );
		}

		$patchStatus = $this->tryPatching( $diffStatus->getValue() );
		if ( !$patchStatus->isOK() ) {
			return EntitySchemaStatus::wrap( $patchStatus );
		}

		[ $patchedSchema, $baseRevId ] = $patchStatus->getValue();
		return $this->storePatchedSchema( $patchedSchema, $baseRevId );
	}

	private function storePatchedSchema(
		FullArrayEntitySchemaData $patchedSchema,
		int $baseRevId
	): EntitySchemaStatus {
		$schemaUpdater = MediaWikiRevisionEntitySchemaUpdater::newFromContext( $this->getContext() );

		$summary = $this->createSummaryCommentForUndoRev(
			$this->getContext()->getRequest()->getText( 'wpSummary' ),
			$this->getContext()->getRequest()->getInt( 'undo' )
			);

		return $schemaUpdater->overwriteWholeSchema(
			new EntitySchemaId( $this->getTitle()->getTitleValue()->getText() ),
			$patchedSchema->data['labels'],
			$patchedSchema->data['descriptions'],
			$patchedSchema->data['aliases'],
			$patchedSchema->data['schemaText'],
			$baseRevId,
			$summary
		);
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
