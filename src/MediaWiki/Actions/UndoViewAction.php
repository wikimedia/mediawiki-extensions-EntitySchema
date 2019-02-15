<?php

namespace Wikibase\Schema\MediaWiki\Actions;

use DifferenceEngine;
use Html;
use IContextSource;
use Linker;
use OOUI\ButtonInputWidget;
use OOUI\ButtonWidget;
use OOUI\FieldLayout;
use OOUI\HtmlSnippet;
use OOUI\TextInputWidget;
use Page;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaSlotDiffRenderer;

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

		$diffHTML = $this->slotDiffRenderer->renderSchemaDiff( $diffStatus->getValue() );
		$diffEngine = new DifferenceEngine();
		$diffHTML = $diffEngine->localiseLineNumbers( $diffHTML );
		$this->displayUndoDiff( $diffHTML );

		$this->showConfirmationForm( $req->getInt( 'undo' ) );
	}

	/**
	 * Shows a form that can be used to confirm the requested undo/restore action.
	 *
	 * @param int $undidRevision
	 */
	private function showConfirmationForm( $undidRevision = 0 ) {
		$req = $this->getRequest();

		$args = [
			'action' => 'submit',
		];

		if ( $req->getInt( 'undo' ) ) {
			$args['undo'] = $req->getInt( 'undo' );
		}

		if ( $req->getInt( 'undoafter' ) ) {
			$args['undoafter'] = $req->getInt( 'undoafter' );
		}

		$actionUrl = $this->getTitle()->getLocalURL( $args );

		$this->getOutput()->addHTML( Html::openElement( 'div', [ 'style' => 'margin-top: 1em;' ] ) );

		$this->getOutput()->addHTML( Html::openElement( 'form', [
			'id' => 'undo',
			'name' => 'undo',
			'method' => 'post',
			'action' => $actionUrl,
			'enctype' => 'multipart/form-data',
		] ) );

		$this->getOutput()->addHTML( "<div class='editOptions'>\n" );

		$labelText = $this->msg( 'wikibaseschema-summary-generated' )->text();
		$this->getOutput()->addHTML( $this->getSummaryInput( $labelText ) );
		$this->getOutput()->addHTML( "<div class='editButtons'>\n" );
		$this->getOutput()->addHTML( $this->getEditButton() . "\n" );
		$this->getOutput()->addHTML( $this->getCancelLink() );
		$this->getOutput()->addHTML( '</div>' ); // editButtons
		$this->getOutput()->addHTML( '</div>' ); // editOptions

		$hidden = [
			'wpEditToken' => $this->getUser()->getEditToken(),
			'wpBaseRev' => $this->getTitle()->getLatestRevID(),
		];
		if ( !empty( $undidRevision ) ) {
			$hidden['wpUndidRevision'] = $undidRevision;
		}
		foreach ( $hidden as $name => $value ) {
			$this->getOutput()->addHTML( "\n" . Html::hidden( $name, $value ) . "\n" );
		}

		$this->getOutput()->addHTML( Html::closeElement( 'form' ) );
		$this->getOutput()->addHTML( Html::closeElement( 'div' ) );
	}

	/**
	 * Generate standard summary input and label (wgSummary), compatible to EditPage.
	 *
	 * @param string $labelText The html to place inside the label
	 *
	 * @return string HTML
	 */
	private function getSummaryInput( $labelText ) {
		$inputAttrs = [
				'name' => 'wpSummary',
				'maxLength' => 200,
				'size' => 60,
				'spellcheck' => 'true',
			] + Linker::tooltipAndAccesskeyAttribs( 'summary' );
		return ( new FieldLayout(
			new TextInputWidget( $inputAttrs ),
			[
				'label' => new HtmlSnippet( $labelText ),
				'align' => 'top',
				'id' => 'wpSummaryLabel',
				'classes' => [ 'mw-summary' ],
			]
		) )->toString();
	}

	/**
	 * Returns a cancel link back to viewing the entity's page
	 *
	 * @return string
	 */
	private function getCancelLink() {
		return ( new ButtonWidget( [
			'id' => 'mw-editform-cancel',
			'href' => $this->getContext()->getTitle()->getLocalURL(),
			'label' => $this->msg( 'cancel' )->text(),
			'framed' => false,
			'flags' => 'destructive'
		] ) )->toString();
	}

	/**
	 * @return string HTML
	 */
	private function getEditButton() {
		global $wgEditSubmitButtonLabelPublish;
		$msgKey = $wgEditSubmitButtonLabelPublish ? 'publishchanges' : 'savearticle';
		return ( new ButtonInputWidget( [
			'name' => 'wpSave',
			'value' => $this->msg( $msgKey )->text(),
			'label' => $this->msg( $msgKey )->text(),
			'accessKey' => $this->msg( 'accesskey-save' )->plain(),
			'flags' => [ 'primary', 'progressive' ],
			'type' => 'submit',
			'title' => $this->msg( 'tooltip-save' )->text()
				. ' [' . $this->msg( 'accesskey-save' )->text() . ']',
		] ) )->toString();
	}

	private function displayUndoDiff( $diff ) {
		$tableClass = 'diff diff-contentalign-' .
			htmlspecialchars( $this->getTitle()->getPageLanguage()->alignStart() );

		$this->getOutput()->addHTML( Html::openElement( 'table', [ 'class' => $tableClass ] ) );

		$this->getOutput()->addHTML( '<colgroup>'
			. '<col class="diff-marker"><col class="diff-content">'
			. '<col class="diff-marker"><col class="diff-content">'
			. '</colgroup>' );
		$this->getOutput()->addHTML( Html::openElement( 'tbody' ) );

		$old = $this->msg( 'wikibaseschema-undo-old-revision' )->parse();
		$new = $this->msg( 'yourtext' )->parse();

		$this->getOutput()->addHTML( Html::openElement( 'tr', [ 'style' => 'vertical-align: top;' ] ) );
		$this->getOutput()->addHTML(
			Html::rawElement( 'td', [ 'colspan' => '2' ],
				Html::rawElement( 'div', [ 'id' => 'mw-diff-otitle1' ], $old )
			)
		);
		$this->getOutput()->addHTML(
			Html::rawElement( 'td', [ 'colspan' => '2' ],
				Html::rawElement( 'div', [ 'id' => 'mw-diff-ntitle1' ], $new )
			)
		);
		$this->getOutput()->addHTML( Html::closeElement( 'tr' ) );

		$this->getOutput()->addHTML( $diff );

		$this->getOutput()->addHTML( Html::closeElement( 'tbody' ) );
		$this->getOutput()->addHTML( Html::closeElement( 'table' ) );

		$this->showDiffStyle();
	}

	/**
	 * Add style sheets and supporting JS for diff display.
	 */
	private function showDiffStyle() {
		$this->getOutput()->addModuleStyles( 'mediawiki.diff.styles' );
	}

}
