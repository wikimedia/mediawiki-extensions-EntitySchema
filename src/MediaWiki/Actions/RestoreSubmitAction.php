<?php

namespace Wikibase\Schema\MediaWiki\Actions;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use Message;
use RuntimeException;
use Status;
use Wikibase\Schema\DataAccess\MediaWikiPageUpdaterFactory;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter;
use Wikibase\Schema\DataAccess\SqlIdGenerator;
use Wikibase\Schema\DataAccess\WatchlistUpdater;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Services\SchemaDispatcher\PersistenceSchemaData;
use Wikibase\Schema\Services\SchemaDispatcher\SchemaDispatcher;

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

		$revStatus = $this->getRevisionFromRequest( $this->context->getRequest() );
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
			return Status::newFatal( 'wikibaseschema-error-not-post' );
		}

		return Status::newGood();
	}

	private function checkCurrentRevison(): Status {
		$req = $this->context->getRequest();

		if ( $this->getTitle()->getLatestRevID() !== (int)$req->getText( 'wpBaseRev' ) ) {
			return Status::newFatal( $this->msg( 'wikibaseschema-restore-changed' ) );
		}

		return Status::newGood();
	}

	private function restore( RevisionRecord $revToRestore ): Status {
		/** @var WikibaseSchemaContent $contentToRestore */
		$contentToRestore = $revToRestore->getContent( SlotRecord::MAIN );

		$dispatcher = new SchemaDispatcher();

		$submitMessage = $this->createSummaryMessageForRestore(
		// @phan-suppress-next-line PhanUndeclaredMethod
			$this->context->getRequest()->getText( 'wpSummary' ),
			$revToRestore
		);

		return $this->storeRestoredSchema(
			$dispatcher->getPersistenceSchemaData(
			// @phan-suppress-next-line PhanUndeclaredMethod
				$contentToRestore->getText()
			),
			$submitMessage
		);
	}

	private function storeRestoredSchema(
		PersistenceSchemaData $persistenceSchemaData,
		Message $submitMessage
	): Status {

		$schemaWriter = new MediawikiRevisionSchemaWriter(
			new MediaWikiPageUpdaterFactory( $this->getUser() ),
			$this,
			new WatchlistUpdater( $this->getUser(), NS_WBSCHEMA_JSON ),
			new SqlIdGenerator(
				MediaWikiServices::getInstance()->getDBLoadBalancer(),
				'wbschema_id_counter'
			)
		);

		try {
			$schemaWriter->overwriteWholeSchema(
				new SchemaId( $this->getTitle()->getTitleValue()->getText() ),
				$persistenceSchemaData->labels,
				$persistenceSchemaData->descriptions,
				$persistenceSchemaData->aliases,
				$persistenceSchemaData->schemaText,
				$submitMessage
			);
		} catch ( RuntimeException $e ) {
			return Status::newFatal( 'wikibaseschema-error-saving-failed', $e->getMessage() );
		}

		return Status::newGood();
	}

	/**
	 * @param string $userSummary
	 * @param RevisionRecord $revToBeRestored
	 *
	 * @return Message
	 */
	private function createSummaryMessageForRestore(
		$userSummary,
		RevisionRecord $revToBeRestored
	): Message {
		return $this->msg( 'wikibaseschema-summary-restore' )
			->params( $revToBeRestored->getId() )
			->params( $revToBeRestored->getUser() )
			->plaintextParams( $userSummary );
	}

}
