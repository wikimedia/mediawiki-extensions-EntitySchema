<?php

namespace Wikibase\Schema\MediaWiki\Actions;

use FormAction;
use HTMLForm;
use IContextSource;
use MediaWiki\Revision\SlotRecord;
use Page;
use RuntimeException;
use Status;
use Wikibase\Schema\DataAccess\EditConflict;
use Wikibase\Schema\DataAccess\MediaWikiPageUpdaterFactory;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter;
use Wikibase\Schema\DataAccess\WatchlistUpdater;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Presentation\InputValidator;
use Wikibase\Schema\Services\SchemaConverter\SchemaConverter;

/**
 * Edit a Wikibase Schema via the mediawiki editing action
 */
class SchemaEditAction extends FormAction {

	/* public */ const FIELD_SCHEMA_TEXT = 'schema-text';
	/* public */ const FIELD_BASE_REV = 'base-rev';

	private $inputValidator;

	public function __construct(
		Page $page,
		InputValidator $inputValidator,
		IContextSource $context = null
	) {
		$this->inputValidator = $inputValidator;
		parent::__construct( $page, $context );
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

		$user = $this->getUser();
		$updaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$id = new SchemaId( $this->getTitle()->getText() );
		$watchListUpdater = new WatchlistUpdater( $user, NS_WBSCHEMA_JSON );
		$schemaWriter = new MediaWikiRevisionSchemaWriter( $updaterFactory, $this, $watchListUpdater );
		try {
			$schemaWriter->updateSchemaText(
				$id,
				$data[self::FIELD_SCHEMA_TEXT],
				(int)$data[self::FIELD_BASE_REV]
			);
		} catch ( EditConflict $e ) {
			return Status::newFatal( 'wikibaseschema-error-schematext-conflict' );
		} catch ( RunTimeException $e ) {
			return Status::newFatal( 'wikibaseschema-error-schemaupdate-failed' );
		}

		return Status::newGood();
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setValidationErrorMessage( [ [ 'wikibaseschema-error-one-more-message-available' ] ] );
	}

	protected function getFormFields() {
		$currentRevRecord = $this->context->getWikiPage()->getRevisionRecord();
		if ( !$currentRevRecord ) {
			throw new RuntimeException( $this->msg( 'wikibaseschema-error-schemadeleted' ) );
		}

		/** @var WikibaseSchemaContent $content */
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
				'label-message' => 'wikibaseschema-editpage-schema-inputlabel',
				'validation-callback' => [ $this->inputValidator, 'validateSchemaTextLength' ],
			],
			self::FIELD_BASE_REV => [
				'type' => 'hidden',
				'default' => $baseRev,
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
