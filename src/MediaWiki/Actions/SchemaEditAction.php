<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Actions;

use Article;
use EntitySchema\DataAccess\EditConflict;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Presentation\InputValidator;
use EntitySchema\Services\SchemaConverter\SchemaConverter;
use FormAction;
use HTMLForm;
use IContextSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserOptionsLookup;
use RuntimeException;
use Status;
use Wikibase\Repo\CopyrightMessageBuilder;
use Wikibase\Repo\Specials\SpecialPageCopyrightView;

/**
 * Edit a EntitySchema via the mediawiki editing action
 *
 * @license GPL-2.0-or-later
 */
class SchemaEditAction extends FormAction {

	public const FIELD_SCHEMA_TEXT = 'schema-text';
	public const FIELD_BASE_REV = 'base-rev';
	public const FIELD_EDIT_SUMMARY = 'edit-summary';
	public const FIELD_IGNORE_EMPTY_SUMMARY = 'ignore-blank-summary';

	private InputValidator $inputValidator;
	private string $submitMsgKey;
	private UserOptionsLookup $userOptionsLookup;
	private SpecialPageCopyrightView $copyrightView;

	public function __construct(
		Article $article,
		IContextSource $context,
		InputValidator $inputValidator,
		bool $editSubmitButtonLabelPublish,
		UserOptionsLookup $userOptionsLookup,
		string $dataRightsUrl,
		string $dataRightsText
	) {
		parent::__construct( $article, $context );
		$this->inputValidator = $inputValidator;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->submitMsgKey = $editSubmitButtonLabelPublish ? 'publishchanges' : 'savechanges';
		$this->copyrightView = new SpecialPageCopyrightView(
			new CopyrightMessageBuilder(),
			$dataRightsUrl,
			$dataRightsText
		);
	}

	public function show(): void {
		parent::show();

		$output = $this->getOutput();
		$output->clearSubtitle();
		$output->addModules( [
			'ext.EntitySchema.action.edit',
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
		$request = $this->getContext()->getRequest();
		$output = $this->getOutput();
		$context = $this->getContext();
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$currentRevision = $revisionStore->getKnownCurrentRevision( $context->getTitle() );
		if ( !$currentRevision ) {
			return Status::newFatal( $this->msg( 'entityschema-error-schemadeleted' ) );
		}

		$content = $currentRevision->getContent( SlotRecord::MAIN );
		if ( !$content instanceof EntitySchemaContent ) {
			return Status::newFatal( $this->msg( 'entityschema-error-schemadeleted' ) );
		}

		$user = $this->getUser();
		if (
			$data['edit-summary'] === ''
			&& $this->userOptionsLookup->getOption( $user, 'forceeditsummary' ) === '1'
			&& !$request->getBool( self::FIELD_IGNORE_EMPTY_SUMMARY )
		) {
			return $output->wrapWikiMsg( "<div id='mw-missingsummary'>\n$1\n</div>",
				[ 'missingsummary', $this->msg( $this->submitMsgKey )->text() ] );
		}
		$updaterFactory = new MediaWikiPageUpdaterFactory( $user );
		$id = new EntitySchemaId( $this->getTitle()->getText() );
		$watchListUpdater = new WatchlistUpdater( $user, NS_ENTITYSCHEMA_JSON );
		$schemaUpdater = new MediaWikiRevisionEntitySchemaUpdater(
			$updaterFactory,
			$watchListUpdater,
			MediaWikiServices::getInstance()->getRevisionLookup(),
			MediaWikiServices::getInstance()->getLanguageFactory()
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

	protected function alterForm( HTMLForm $form ): void {
		$form->suppressDefaultSubmit();
		$request = $this->getContext()->getRequest();
		if ( $request->getVal( 'wpedit-summary' ) === '' ) {
			$form->addHiddenField( self::FIELD_IGNORE_EMPTY_SUMMARY, true );
		}

		$form->addFields( [ [
			'type' => 'info',
			'default' => $this->getCopyrightHTML(),
			'raw' => true,
		] ] );
		if ( $this->getUser()->isAnon() ) {
			$form->addFields( [ [
				'type' => 'info',
				'default' => $this->msg(
					'entityschema-anonymouseditwarning'
				)->parse(),
				'raw' => true,
			] ] );
		}

		$form->addButton( [
			'name' => 'wpSave',
			'value' => $this->msg( $this->submitMsgKey )->text(),
			'label' => $this->msg( $this->submitMsgKey )->text(),
			'attribs' => [ 'accessKey' => $this->msg( 'accesskey-save' )->plain() ],
			'flags' => [ 'primary', 'progressive' ],
			'type' => 'submit',
		] );
		$form->setValidationErrorMessage( [ [ 'entityschema-error-one-more-message-available' ] ] );
	}

	/**
	 * @return string HTML
	 */
	private function getCopyrightHTML() {
		return $this->copyrightView
			->getHtml( $this->getLanguage(), $this->submitMsgKey );
	}

	protected function getFormFields(): array {
		$context = $this->getContext();
		$revisionStore = MediaWikiServices::getInstance()->getRevisionStore();
		$currentRevRecord = $revisionStore->getKnownCurrentRevision( $context->getTitle() );
		if ( !$currentRevRecord ) {
			throw new RuntimeException( $this->msg( 'entityschema-error-schemadeleted' )->text() );
		}

		/** @var EntitySchemaContent $content */
		$content = $currentRevRecord->getContent( SlotRecord::MAIN );
		// @phan-suppress-next-line PhanUndeclaredMethod
		$schemaText = ( new SchemaConverter() )->getSchemaText( $content->getText() );
		$baseRev = $this->getContext()->getRequest()->getInt(
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

	protected function usesOOUI(): bool {
		return true;
	}

	/**
	 * Do something exciting on successful processing of the form.  This might be to show
	 * a confirmation message (watch, rollback, etc) or to redirect somewhere else (edit,
	 * protect, etc).
	 */
	public function onSuccess(): void {
		$redirectParams = $this->getRequest()->getVal( 'redirectparams', '' );
		$this->getOutput()->redirect( $this->getTitle()->getFullURL( $redirectParams ) );
	}

	/**
	 * Return the name of the action this object responds to
	 *
	 * @return string Lowercase name
	 */
	public function getName(): string {
		return 'edit';
	}

	public function getRestriction(): string {
		return $this->getName();
	}

}
