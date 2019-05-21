<?php

namespace EntitySchema\Presentation;

use Html;
use Linker;
use MessageLocalizer;
use OOUI\ButtonInputWidget;
use OOUI\ButtonWidget;
use OOUI\FieldLayout;
use OOUI\HtmlSnippet;
use OOUI\TextInputWidget;
use Title;
use User;

/**
 * @license GPL-2.0-or-later
 */
class ConfirmationFormRenderer {

	private $msgLocalizer;

	public function __construct( MessageLocalizer $msgLocalizer ) {
		$this->msgLocalizer = $msgLocalizer;
	}

	/**
	 * Shows a form that can be used to confirm the requested undo/restore action.
	 *
	 * @param array $args
	 * @param string $formName
	 * @param Title $title
	 * @param User $user
	 * @param int $undidRevision
	 *
	 * @return string
	 */
	public function showUndoRestoreConfirmationForm(
		array $args,
		$formName,
		Title $title,
		User $user,
		$undidRevision = 0
	) {
		$args = array_merge(
			[
				'action' => 'submit',
			],
			$args
		);

		$actionUrl = $title->getLocalURL( $args );

		$formHTML = '';

		$formHTML .= Html::openElement( 'div', [ 'style' => 'margin-top: 1em;' ] );

		$formHTML .= Html::openElement( 'form', [
			'id' => $formName,
			'name' => $formName,
			'method' => 'post',
			'action' => $actionUrl,
			'enctype' => 'multipart/form-data',
		] );

		$formHTML .= "<div class='editOptions'>\n";

		$labelText = $this->msgLocalizer->msg( 'entityschema-summary-generated' )->escaped();
		$formHTML .= $this->getSummaryInput( $labelText );
		$formHTML .= "<div class='editButtons'>\n";
		$formHTML .= $this->getEditButton() . "\n";
		$formHTML .= $this->getCancelLink( $title );
		$formHTML .= '</div>'; // editButtons
		$formHTML .= '</div>'; // editOptions

		$hidden = [
			'wpEditToken' => $user->getEditToken(),
			'wpBaseRev' => $title->getLatestRevID(),
		];
		if ( !empty( $undidRevision ) ) {
			$hidden['wpUndidRevision'] = $undidRevision;
		}
		foreach ( $hidden as $name => $value ) {
			$formHTML .= "\n" . Html::hidden( $name, $value ) . "\n";
		}

		$formHTML .= Html::closeElement( 'form' );
		$formHTML .= Html::closeElement( 'div' );

		return $formHTML;
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
	private function getCancelLink( Title $title ) {
		return ( new ButtonWidget( [
			'id' => 'mw-editform-cancel',
			'href' => $title->getLocalURL(),
			'label' => $this->msgLocalizer->msg( 'cancel' )->text(),
			'framed' => false,
			'flags' => 'destructive',
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
			'value' => $this->msgLocalizer->msg( $msgKey )->text(),
			'label' => $this->msgLocalizer->msg( $msgKey )->text(),
			'accessKey' => $this->msgLocalizer->msg( 'accesskey-save' )->plain(),
			'flags' => [ 'primary', 'progressive' ],
			'type' => 'submit',
			'title' => $this->msgLocalizer->msg( 'tooltip-save' )->text()
				. ' [' . $this->msgLocalizer->msg( 'accesskey-save' )->text() . ']',
		] ) )->toString();
	}

}
