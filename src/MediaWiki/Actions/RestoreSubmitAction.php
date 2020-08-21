<?php

namespace EntitySchema\MediaWiki\Actions;

use CommentStoreComment;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionSchemaUpdater;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\SchemaConverter\PersistenceSchemaData;
use EntitySchema\Services\SchemaConverter\SchemaConverter;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use RuntimeException;
use Status;

/**
 * @license GPL-2.0-or-later
 */
final class RestoreSubmitAction extends AbstractRestoreAction {

	public function getName() {
		return 'submit';
	}

	public function show() {
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
		}

		$this->getOutput()->redirect(
			$this->getTitle()->getFullURL()
		);
	}

	private function checkMethod() {
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

	private function restore( RevisionRecord $revToRestore ): Status {
		/** @var EntitySchemaContent $contentToRestore */
		$contentToRestore = $revToRestore->getContent( SlotRecord::MAIN );

		$converter = new SchemaConverter();

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
		PersistenceSchemaData $persistenceSchemaData,
		$baseRevId,
		CommentStoreComment $summary
	): Status {

		$schemaUpdater = new MediaWikiRevisionSchemaUpdater(
			new MediaWikiPageUpdaterFactory( $this->getUser() ),
			new WatchlistUpdater( $this->getUser(), NS_ENTITYSCHEMA_JSON ),
			MediaWikiServices::getInstance()->getRevisionLookup()
		);

		try {
			$schemaUpdater->overwriteWholeSchema(
				new SchemaId( $this->getTitle()->getTitleValue()->getText() ),
				$persistenceSchemaData->labels,
				$persistenceSchemaData->descriptions,
				$persistenceSchemaData->aliases,
				$persistenceSchemaData->schemaText,
				$baseRevId,
				$summary
			);
		} catch ( RuntimeException $e ) {
			return Status::newFatal( 'entityschema-error-saving-failed', $e->getMessage() );
		}

		return Status::newGood();
	}

	/**
	 * @param string $userSummary
	 * @param RevisionRecord $revToBeRestored
	 *
	 * @return CommentStoreComment
	 */
	private function createSummaryMessageForRestore(
		$userSummary,
		RevisionRecord $revToBeRestored
	): CommentStoreComment {
		$revId = $revToBeRestored->getId();
		$userName = $revToBeRestored->getUser()->getName();
		$autoComment = MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_RESTORE
			. ':' . $revId
			. ':' . $userName;
		return CommentStoreComment::newUnsavedComment(
			'/* ' . $autoComment . ' */' . $userSummary,
			[
				'key' => MediaWikiRevisionSchemaUpdater::AUTOCOMMENT_RESTORE,
				'revId' => $revId,
				'userName' => $userName,
				'summary' => $userSummary
			]
		);
	}

}
