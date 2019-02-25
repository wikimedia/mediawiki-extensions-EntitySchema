<?php

namespace Wikibase\Schema\MediaWiki\Actions;

use IContextSource;
use Page;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaSlotDiffRenderer;
use Wikibase\Schema\Services\RenderDiffHelper\RenderDiffHelper;

/**
 * @license GPL-2.0-or-later
 */
class UndoViewAction extends AbstractUndoAction {

	private $slotDiffRenderer;

	public function __construct(
		Page $page,
		WikibaseSchemaSlotDiffRenderer $slotDiffRenderer,
		IContextSource $context = null
	) {
		parent::__construct( $page, $context );
		$this->slotDiffRenderer = $slotDiffRenderer;
	}

	public function getName() {
		return 'edit';
	}

	public function show() {
		$this->getOutput()->enableOOUI();

		$this->getOutput()->setPageTitle(
			$this->msg(
				'wikibaseschema-undo-heading',
				$this->getTitle()->getTitleValue()->getText()
			)
		);

		$req = $this->context->getRequest();
		$diffStatus = $this->getDiffFromRequest( $req );
		if ( !$diffStatus->isOK() ) {
			$this->showUndoErrorPage( $diffStatus );
			return;
		}

		$patchStatus = $this->tryPatching( $diffStatus->getValue() );
		if ( !$patchStatus->isOK() ) {
			$this->showUndoErrorPage( $patchStatus );
			return;
		}

		$this->displayUndoDiff( $diffStatus->getValue() );
		$this->showConfirmationForm( $req->getInt( 'undo' ) );
	}

	/**
	 * Shows a form that can be used to confirm the requested undo/restore action.
	 *
	 * @param int $undidRevision
	 */
	private function showConfirmationForm( $undidRevision = 0 ) {
		$req = $this->getRequest();
		$helper = new RenderDiffHelper( $this );
		$confFormHTML = $helper->showConfirmationForm(
			[
				'undo' => $req->getInt( 'undo' ),
				'undoafter' => $req->getInt( 'undoafter' ),
			],
			'restore',
			$this->getTitle(),
			$this->getUser(),
			$undidRevision
		);

		$this->getOutput()->addHTML( $confFormHTML );
	}

	private function displayUndoDiff( $diff ) {

		$helper = new RenderDiffHelper( $this );
		$this->getOutput()->addHTML(
			$helper->renderSchemaDiffTable(
				$this->slotDiffRenderer->renderSchemaDiffRows( $diff ),
				$this->msg( 'wikibaseschema-undo-old-revision' )
			)
		);

		$this->getOutput()->addModuleStyles( 'mediawiki.diff.styles' );
	}

}
