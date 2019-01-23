<?php

namespace Wikibase\Schema\MediaWiki;

use CommentStoreComment;
use FormAction;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;

/**
 * Edit a Wikibase Schema via the mediawiki editing action
 */
class SchemaEditAction extends FormAction {

	protected function getFormFields() {

		/** @var WikibaseSchemaContent $content */
		$content = $this->getContext()->getWikiPage()->getContent();
		if ( $content ) {
			// FIXME: handle this better
			$schema = json_decode( $content->getText(), true );
		} else {
			$schema = [
				'description' => [
					'en' => '',
				],
				'schema' => '',
			];
		}

		return [
			'description' => [
				'type' => 'text',
				'default' => $schema[ 'description' ][ 'en' ],
			],
			'schema' => [
				'type' => 'textarea',
				'default' => $schema[ 'schema' ],
			],
		];
	}

	/**
	 * Process the form on POST submission.
	 *
	 * If you don't want to do anything with the form, just return false here.
	 *
	 * This method will be passed to the HTMLForm as a submit callback (see
	 * HTMLForm::setSubmitCallback) and must return as documented for HTMLForm::trySubmit.
	 *
	 * @see HTMLForm::setSubmitCallback()
	 * @see HTMLForm::trySubmit()
	 *
	 * @param array $data
	 *
	 * @return bool|string|array|Status Must return as documented for HTMLForm::trySubmit
	 */
	public function onSubmit( $data ) {
		/**
		 * @var $content WikibaseSchemaContent
		 */
		$content = $this->getContext()->getWikiPage()->getContent();
		$dataToSave = json_encode( $this->formDataToSchemaArray( $data ) );
		if ( $content ) {
			$content->setNativeData( $dataToSave );
		} else {
			$content = new WikibaseSchemaContent( $dataToSave );
		}

		$updater = $this->page->getPage()->newPageUpdater( $this->context->getUser() );
		$updater->setContent( 'main', $content );
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'FIXME in SchemaEditAction::onSubmit' )
		);

		return true;
	}

	private function formDataToSchemaArray( array $formData ) {
		return [
			'schema' => $formData[ 'schema' ],
			'description' => [
				'en' => $formData[ 'description' ],
			],
		];
	}

	/**
	 * Do something exciting on successful processing of the form.  This might be to show
	 * a confirmation message (watch, rollback, etc) or to redirect somewhere else (edit,
	 * protect, etc).
	 */
	public function onSuccess() {
		$redirectParams = $this->getRequest()->getVal( 'redirectparams', '' );
		$this->getOutput()->redirect( $this->getTitle()->getFullURL( $redirectParams ) );
	}

	/**
	 * Return the name of the action this object responds to
	 *
	 * @since 1.17
	 *
	 * @return string Lowercase name
	 */
	public function getName() {
		return 'edit';
	}

}
