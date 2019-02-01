<?php

namespace Wikibase\Schema\MediaWiki;

use CommentStoreComment;
use Deserializers\Exceptions\DeserializationException;
use FormAction;
use IContextSource;
use Page;
use RuntimeException;
use Status;
use ViewAction;
use Wikibase\Schema\Deserializers\DeserializerFactory;
use Wikibase\Schema\Domain\Model\Schema;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;

/**
 * Edit a Wikibase Schema via the mediawiki editing action
 */
class SchemaEditAction extends FormAction {

	public static function getEditOrViewAction( Page $page, IContextSource $context = null ) {
		if ( $page->getRevision() === null ) {
			return new ViewAction( $page, $context );
		}

		// ToDo: check redirect?
		// !$page->isRedirect()

		return new self( $page, $context );
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
		if ( !$content instanceof WikibaseSchemaContent ) {
			return Status::newFatal( $this->msg( 'wikibaseschema-error-schemadeleted' ) );
		}

		$schema = $this->formDataToSchema( $data );
		$content->setContentFromSchema( $schema );

		$updater = $this->page->getPage()->newPageUpdater( $this->context->getUser() );
		$updater->setContent( 'main', $content );
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'FIXME in SchemaEditAction::onSubmit' )
		);

		return Status::newGood();
	}

	protected function getFormFields() {
		/** @var WikibaseSchemaContent $content */
		$content = $this->getContext()->getWikiPage()->getContent();
		if ( !$content ) {
			throw new RuntimeException( $this->msg( 'wikibaseschema-error-schemadeleted' ) );
		}

		$deserializer = DeserializerFactory::newSchemaDeserializer();
		$serializedContent = json_decode( $content->getText(), true );
		try {
			$schema = $deserializer->deserialize( $serializedContent );
		} catch ( DeserializationException $e ) {
			// FIXME remove this try catch by 2019-02-11 !
			return [
				'warning' => [
					'type' => 'info',
					'default' => 'FIXME: Please remove this workaround in SchemaEditAction::getFormFields!',
					'cssclass' => 'warning',
				],
				'label' => [
					'type' => 'text',
					'default' => $serializedContent['labels']['en'],
					'label-message' => 'wikibaseschema-editpage-label-inputlabel',
					'placeholder-message' => 'wikibaseschema-label-edit-placeholder',
				],
				'description' => [
					'type' => 'text',
					'default' => $serializedContent['descriptions']['en'],
					'label-message' => 'wikibaseschema-editpage-description-inputlabel',
					'placeholder-message' => 'wikibaseschema-description-edit-placeholder',
				],
				'aliases' => [
					'type' => 'text',
					'default' => implode( ' | ', $serializedContent['aliases']['en'] ),
					'label-message' => 'wikibaseschema-editpage-aliases-inputlabel',
					'placeholder-message' => 'wikibaseschema-aliases-edit-placeholder',
				],
				'schema' => [
					'type' => 'textarea',
					'default' => $serializedContent['schema'],
					'label-message' => 'wikibaseschema-editpage-schema-inputlabel',
				],
			];
		}

		return [
			'label' => [
				'type' => 'text',
				'default' => $schema->getLabel( 'en' )->getText(),
				'label-message' => 'wikibaseschema-editpage-label-inputlabel',
				'placeholder-message' => 'wikibaseschema-label-edit-placeholder',
			],
			'description' => [
				'type' => 'text',
				'default' => $schema->getDescription( 'en' )->getText(),
				'label-message' => 'wikibaseschema-editpage-description-inputlabel',
				'placeholder-message' => 'wikibaseschema-description-edit-placeholder',
			],
			'aliases' => [
				'type' => 'text',
				'default' => implode( ' | ', $schema->getAliasGroup( 'en' )->getAliases() ),
				'label-message' => 'wikibaseschema-editpage-aliases-inputlabel',
				'placeholder-message' => 'wikibaseschema-aliases-edit-placeholder',
			],
			'schema' => [
				'type' => 'textarea',
				'default' => $schema->getSchema(),
				'label-message' => 'wikibaseschema-editpage-schema-inputlabel',
			],
		];
	}

	protected function usesOOUI() {
		return true;
	}

	private function formDataToSchema( array $formData ) {
		$schema = new Schema();
		$schema->setLabel( 'en', $formData['label'] );
		$schema->setDescription( 'en', $formData['description'] );
		$schema->setAliases(
			'en',
			array_filter( array_map( 'trim', explode( '|', $formData['aliases'] ) ) )
		);
		$schema->setSchema( $formData['schema'] );
		return $schema;
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

	public function getRestriction() {
		return $this->getName();
	}

}
