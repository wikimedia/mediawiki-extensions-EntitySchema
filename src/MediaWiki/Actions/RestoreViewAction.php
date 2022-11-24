<?php

namespace EntitySchema\MediaWiki\Actions;

use Article;
use Diff\DiffOp\Diff\Diff;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\MediaWiki\Content\EntitySchemaSlotDiffRenderer;
use EntitySchema\Presentation\ConfirmationFormRenderer;
use EntitySchema\Presentation\DiffRenderer;
use EntitySchema\Services\Diff\SchemaDiffer;
use EntitySchema\Services\SchemaConverter\SchemaConverter;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use Status;

/**
 * @license GPL-2.0-or-later
 */
final class RestoreViewAction extends AbstractRestoreAction {

	/** @var EntitySchemaSlotDiffRenderer */
	private $slotDiffRenderer;

	public function __construct(
		Article $article,
		IContextSource $context,
		EntitySchemaSlotDiffRenderer $slotDiffRenderer
	) {
		parent::__construct( $article, $context );
		$this->slotDiffRenderer = $slotDiffRenderer;
	}

	public function show() {
		$this->checkPermissions();

		$req = $this->getContext()->getRequest();
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
				'entityschema-restore-heading',
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

		/** @var EntitySchemaContent $contentToRestore */
		$contentToRestore = $revToRestore->getContent( SlotRecord::MAIN );

		/** @var EntitySchemaContent $baseContent */
		$baseContent = MediaWikiServices::getInstance()->getRevisionStore()
			->getRevisionById( $this->getTitle()->getLatestRevID() )
			->getContent( SlotRecord::MAIN );

		$differ = new SchemaDiffer();
		$converter = new SchemaConverter();
		$diff = $differ->diffSchemas(
		// @phan-suppress-next-line PhanUndeclaredMethod
			$converter->getFullArraySchemaData( $baseContent->getText() ),
			// @phan-suppress-next-line PhanUndeclaredMethod
			$converter->getFullArraySchemaData( $contentToRestore->getText() )
		);

		if ( $diff->isEmpty() ) {
			return Status::newFatal( 'entityschema-restore-empty' );
		}

		return Status::newGood( $diff );
	}

}
