<?php

namespace Wikibase\Schema\MediaWiki\Actions;

use Diff\DiffOp\Diff\Diff;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use Page;
use Status;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaSlotDiffRenderer;
use Wikibase\Schema\Presentation\ConfirmationFormRenderer;
use Wikibase\Schema\Services\Diff\SchemaDiffer;
use Wikibase\Schema\Presentation\DiffRenderer;
use Wikibase\Schema\Services\SchemaDispatcher\SchemaDispatcher;

/**
 * @license GPL-2.0-or-later
 */
final class RestoreViewAction extends AbstractRestoreAction {

	private $slotDiffRenderer;

	public function __construct(
		Page $page,
		WikibaseSchemaSlotDiffRenderer $slotDiffRenderer,
		IContextSource $context = null
	) {
		parent::__construct( $page, $context );
		$this->slotDiffRenderer = $slotDiffRenderer;
	}

	public function show() {
		$this->checkPermissions();

		$req = $this->context->getRequest();
		$revStatus = $this->getRevisionFromRequest( $req );
		if ( !$revStatus->isOK() ) {
			$this->showRestoreErrorPage( $revStatus );
			return;
		}

		$diffStatus = $this->getDiffFromRevision( $revStatus->getValue() );
		if ( !$diffStatus->isOK() ) {
			$this->showRestoreErrorPage( $diffStatus );
			return;
		}

		$this->showRestorePreview( $diffStatus->getValue(), $req->getInt( 'restore' ) );
	}

	private function showRestorePreview( Diff $diff, $restoredRevID ) {
		$this->getOutput()->enableOOUI();
		$this->getOutput()->setPageTitle(
			$this->msg(
				'wikibaseschema-restore-heading',
				$this->getTitle()->getTitleValue()->getText()
			)
		);

		$diffRenderer = new DiffRenderer( $this );
		$diffHTML = $diffRenderer->renderSchemaDiffTable(
			$this->slotDiffRenderer->renderSchemaDiffRows( $diff ),
			$this->msg( 'currentrev' )
		);

		$this->getOutput()->addHTML( $diffHTML );
		$this->getOutput()->addModuleStyles( 'mediawiki.diff.styles' );

		$confFormRenderer = new ConfirmationFormRenderer( $this );
		$confFormHTML = $confFormRenderer->showUndoRestoreConfirmationForm(
			[
				'restore' => $restoredRevID,
			],
			'restore',
			$this->getTitle(),
			$this->getUser()
		);

		$this->getOutput()->addHTML( $confFormHTML );
	}

	private function getDiffFromRevision( RevisionRecord $revToRestore ): Status {

		/** @var WikibaseSchemaContent $contentToRestore */
		$contentToRestore = $revToRestore->getContent( SlotRecord::MAIN );

		/** @var WikibaseSchemaContent $baseContent */
		$baseContent = MediaWikiServices::getInstance()->getRevisionStore()
			->getRevisionById( $this->getTitle()->getLatestRevID() )
			->getContent( SlotRecord::MAIN );

		$differ = new SchemaDiffer();
		$dispatcher = new SchemaDispatcher();
		$diff = $differ->diffSchemas(
		// @phan-suppress-next-line PhanUndeclaredMethod
			$dispatcher->getFullArraySchemaData( $baseContent->getText() ),
			// @phan-suppress-next-line PhanUndeclaredMethod
			$dispatcher->getFullArraySchemaData( $contentToRestore->getText() )
		);

		if ( $diff->isEmpty() ) {
			return Status::newFatal( 'wikibaseschema-restore-empty' );
		}

		return Status::newGood( $diff );
	}

}
