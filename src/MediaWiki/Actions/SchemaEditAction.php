<?php

namespace EntitySchema\MediaWiki\Actions;

use FormAction;
use HTMLForm;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use Page;
use RuntimeException;
use Status;
use EntitySchema\DataAccess\EditConflict;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionSchemaUpdater;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Presentation\InputValidator;
use EntitySchema\Services\SchemaConverter\SchemaConverter;

/**
 * Edit a EntitySchema via the mediawiki editing action
 */
class SchemaEditAction extends FormAction {

	/* public */ const FIELD_SCHEMA_TEXT = 'schema-text';
	/* public */ const FIELD_BASE_REV = 'base-rev';
	/* public */ const FIELD_EDIT_SUMMARY = 'edit-summary';

	private $inputValidator;
	private $submitMsgKey;

	public function __construct(
		Page $page,
		InputValidator $inputValidator,
		$wgEditSubmitButtonLabelPublish,
		IContextSource $context = null
	) {
		$this->inputValidator = $inputValidator;
		parent::__construct( $page, $context );
		$this->submitMsgKey = $wgEditSubmitButtonLabelPublish ? 'publishchanges' : 'savechanges';
	}

	public function show() {
		parent::show();

		$output = $this->getOutput();
		$output->clearSubtitle();
		$output->addModules( [
			'ext.EntitySchema.action.edit'
		] );
		$output->addJsConfigVars(
			'wgEntitySchemaSchemaTextMaxSizeBytes',
			intval( $this->getContext()->getConfig()->get( 'EntitySchemaSchemaTextMaxSizeBytes' ) )
		);
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
		 * @var $content EntitySchemaContent
		 */
		$content = $this->getContext()->getWikiPage()->getContent();
		if ( !$content instanceof EntitySchemaContent ) {
			return Status::newFatal( $this->msg( 'entityschema-error-schemadeleted' ) );
		}

		$user = $this->getUser();
		$updaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$id = new SchemaId( $this->getTitle()->getText() );
		$watchListUpdater = new WatchlistUpdater( $user, NS_ENTITYSCHEMA_JSON );
		$schemaUpdater = new MediaWikiRevisionSchemaUpdater(
			$updaterFactory,
			$watchListUpdater,
			MediaWikiServices::getInstance()->getRevisionLookup()
		);

		try {
			$schemaUpdater->updateSchemaText(
				$id,
				$data[self::FIELD_SCHEMA_TEXT],
				(int)$data[self::FIELD_BASE_REV],
				trim( $data[self::FIELD_EDIT_SUMMARY] )
			);
		} catch ( EditConflict $e ) {
			return Status::newFatal( 'entityschema-error-schematext-conflict' );
		} catch ( RuntimeException $e ) {
			return Status::newFatal( 'entityschema-error-schemaupdate-failed' );
		}

		return Status::newGood();
	}

	protected function alterForm( HTMLForm $form ) {
		$form->suppressDefaultSubmit();
		$form->addButton( [
			'name' => 'wpSave',
			'value' => $this->msg( $this->submitMsgKey )->text(),
			'label' => $this->msg( $this->submitMsgKey )->text(),
			'attribs' => [ 'accessKey' => $this->msg( 'accesskey-save' )->plain() ],
			'flags' => [ 'primary', 'progressive' ],
			'type' => 'submit'
		] );
		$form->setValidationErrorMessage( [ [ 'entityschema-error-one-more-message-available' ] ] );
	}

	protected function getFormFields() {
		$currentRevRecord = $this->context->getWikiPage()->getRevisionRecord();
		if ( !$currentRevRecord ) {
			throw new RuntimeException( $this->msg( 'entityschema-error-schemadeleted' ) );
		}

		/** @var EntitySchemaContent $content */
		$content = $currentRevRecord->getContent( SlotRecord::MAIN );
		// @phan-suppress-next-line PhanUndeclaredMethod
		$schemaText = ( new SchemaConverter() )->getSchemaText( $content->getText() );
		$baseRev = $this->context->getRequest()->getInt(
			'wp' . self::FIELD_BASE_REV,
			$currentRevRecord->getId()
		);

		return [
			self::FIELD_SCHEMA_TEXT => [
				'type' => 'textarea',
				'default' => $schemaText,
				'label-message' => 'entityschema-editpage-schema-inputlabel',
				'validation-callback' => [ $this->inputValidator, 'validateSchemaTextLength' ],
			],
			self::FIELD_BASE_REV => [
				'type' => 'hidden',
				'default' => $baseRev,
			],
			self::FIELD_EDIT_SUMMARY => [
				'type' => 'text',
				'default' => '',
				'label-message' => 'entityschema-summary-generated',
			],
		];
	}

	protected function usesOOUI() {
		return true;
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
	 * @return string Lowercase name
	 */
	public function getName() {
		return 'edit';
	}

	public function getRestriction() {
		return $this->getName();
	}

}
