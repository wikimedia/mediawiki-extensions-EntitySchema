<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Actions;

use EntitySchema\DataAccess\EntitySchemaStatus;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\EntitySchemaRedirectTrait;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use EntitySchema\Services\Converter\PersistenceEntitySchemaData;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;

/**
 * @license GPL-2.0-or-later
 */
final class RestoreSubmitAction extends AbstractRestoreAction {

	use EntitySchemaRedirectTrait;

	public function getName(): string {
		return 'submit';
	}

	public function show(): void {
		$checkMethodStatus = $this->checkMethod();
		if ( !$checkMethodStatus->isOK() ) {
			$this->showRestoreErrorPage( Status::newFatal( $checkMethodStatus ) );
		}

		$this->checkPermissions();

		$currentRevStatus = $this->checkCurrentRevison();
		if ( !$currentRevStatus->isOK() ) {
			$this->showRestoreErrorPage( $currentRevStatus );
			return;
		}

		$revStatus = $this->getRevisionFromRequest( $this->getContext()->getRequest() );
		if ( !$revStatus->isOK() ) {
			$this->showRestoreErrorPage( $revStatus );
			return;
		}

		$restoreStatus = $this->restore( $revStatus->getValue() );
		if ( !$restoreStatus->isOK() ) {
			$this->showRestoreErrorPage( $restoreStatus );
			return;
		}

		$this->redirectToEntitySchema( $restoreStatus );
	}

	private function checkMethod(): Status {
		if ( !$this->getContext()->getRequest()->wasPosted() ) {
			return Status::newFatal( 'entityschema-error-not-post' );
		}

		return Status::newGood();
	}

	private function checkCurrentRevison(): Status {
		$req = $this->getContext()->getRequest();

		if ( $this->getTitle()->getLatestRevID() !== (int)$req->getText( 'wpBaseRev' ) ) {
			return Status::newFatal( $this->msg( 'entityschema-restore-changed' ) );
		}

		return Status::newGood();
	}

	private function restore( RevisionRecord $revToRestore ): EntitySchemaStatus {
		/** @var EntitySchemaContent $contentToRestore */
		$contentToRestore = $revToRestore->getContent( SlotRecord::MAIN );

		$converter = new EntitySchemaConverter();

		$summary = $this->createSummaryMessageForRestore(
			$this->getContext()->getRequest()->getText( 'wpSummary' ),
			$revToRestore
		);

		return $this->storeRestoredSchema(
			$converter->getPersistenceSchemaData(
			// @phan-suppress-next-line PhanUndeclaredMethod
				$contentToRestore->getText()
			),
			$this->getContext()->getRequest()->getInt( 'wpBaseRev' ),
			$summary
		);
	}

	private function storeRestoredSchema(
		PersistenceEntitySchemaData $persistenceSchemaData,
		int $baseRevId,
		CommentStoreComment $summary
	): EntitySchemaStatus {

		$schemaUpdater = MediaWikiRevisionEntitySchemaUpdater::newFromContext( $this->getContext() );

		return $schemaUpdater->overwriteWholeSchema(
			new EntitySchemaId( $this->getTitle()->getTitleValue()->getText() ),
			$persistenceSchemaData->labels,
			$persistenceSchemaData->descriptions,
			$persistenceSchemaData->aliases,
			$persistenceSchemaData->schemaText,
			$baseRevId,
			$summary
		);
	}

	private function createSummaryMessageForRestore(
		string $userSummary,
		RevisionRecord $revToBeRestored
	): CommentStoreComment {
		$revId = $revToBeRestored->getId();
		$userName = $revToBeRestored->getUser()->getName();
		$autoComment = MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_RESTORE
			. ':' . $revId
			. ':' . $userName;
		return CommentStoreComment::newUnsavedComment(
			'/* ' . $autoComment . ' */' . $userSummary,
			[
				'key' => MediaWikiRevisionEntitySchemaUpdater::AUTOCOMMENT_RESTORE,
				'revId' => $revId,
				'userName' => $userName,
				'summary' => $userSummary,
			]
		);
	}

}
