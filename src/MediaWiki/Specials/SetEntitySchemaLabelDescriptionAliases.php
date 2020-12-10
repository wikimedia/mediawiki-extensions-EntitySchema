<?php

namespace EntitySchema\MediaWiki\Specials;

use EntitySchema\DataAccess\EditConflict;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionSchemaUpdater;
use EntitySchema\DataAccess\WatchlistUpdater;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\Presentation\InputValidator;
use EntitySchema\Services\SchemaConverter\NameBadge;
use EntitySchema\Services\SchemaConverter\SchemaConverter;
use Html;
use HTMLForm;
use InvalidArgumentException;
use Language;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MWException;
use OutputPage;
use RuntimeException;
use SpecialPage;
use Status;
use Title;
use UserBlockedError;
use WebRequest;
use WikiPage;

/**
 * Page for editing label, description and aliases of a Schema
 *
 * @license GPL-2.0-or-later
 */
class SetEntitySchemaLabelDescriptionAliases extends SpecialPage {

	public const FIELD_ID = 'ID';
	public const FIELD_LANGUAGE = 'languagecode';
	public const FIELD_DESCRIPTION = 'description';
	public const FIELD_LABEL = 'label';
	public const FIELD_ALIASES = 'aliases';
	public const FIELD_BASE_REV = 'base-rev';
	private const SUBMIT_SELECTION_NAME = 'submit-selection';
	private const SUBMIT_EDIT_NAME = 'submit-edit';

	/** @var string */
	private $htmlFormProvider;

	public function __construct( $htmlFormProvider = HTMLForm::class ) {
		parent::__construct(
			'SetEntitySchemaLabelDescriptionAliases'
		);

		$this->htmlFormProvider = $htmlFormProvider;
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );

		$request = $this->getRequest();
		$id = $this->getIdFromSubpageOrRequest( $subPage, $request );
		$language = $this->getLanguageFromSubpageOrRequestOrUI( $subPage, $request );

		if ( $this->isSelectionDataValid( $id, $language ) ) {
			$baseRevId = $request->getInt( self::FIELD_BASE_REV );
			$this->displayEditForm( new SchemaId( $id ), $language, $baseRevId );
			return;
		}

		$this->displaySchemaLanguageSelectionForm( $id, $language );
	}

	public function submitEditFormCallback( $data ) {
		$updaterFactory = new MediaWikiPageUpdaterFactory( $this->getContext()->getUser() );
		$watchListUpdater = new WatchlistUpdater( $this->getUser(), NS_ENTITYSCHEMA_JSON );
		try {
			$id = new SchemaId( $data[self::FIELD_ID] );
		} catch ( InvalidArgumentException $e ) {
			return Status::newFatal( 'entityschema-error-schemaupdate-failed' );
		}
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id->getId() );
		$this->checkBlocked( $title );
		$aliases = array_map( 'trim', explode( '|', $data[self::FIELD_ALIASES] ) );
		$schemaUpdater = new MediaWikiRevisionSchemaUpdater(
			$updaterFactory,
			$watchListUpdater,
			MediaWikiServices::getInstance()->getRevisionLookup()
		);

		try {
			$schemaUpdater->updateSchemaNameBadge(
				$id,
				$data[self::FIELD_LANGUAGE],
				$data[self::FIELD_LABEL],
				$data[self::FIELD_DESCRIPTION],
				$aliases,
				(int)$data[self::FIELD_BASE_REV]
			);
		} catch ( EditConflict $e ) {
			return Status::newFatal( 'entityschema-error-namebadge-conflict' );
		} catch ( RuntimeException $e ) {
			return Status::newFatal( 'entityschema-error-schemaupdate-failed' );
		}

		return Status::newGood( $title->getFullURL() );
	}

	public function getDescription() {
		return $this->msg( 'entityschema-special-setlabeldescriptionaliases' )->text();
	}

	private function getIdFromSubpageOrRequest( $subpage, WebRequest $request ) {
		$subpageParts = array_filter( explode( '/', $subpage, 2 ) );
		if ( count( $subpageParts ) > 0 ) {
			return $subpageParts[0];
		}
		return $request->getText( self::FIELD_ID ) ?: null;
	}

	private function getLanguageFromSubpageOrRequestOrUI( $subpage, WebRequest $request ) {
		$subpageParts = array_filter( explode( '/', $subpage, 2 ) );
		if ( count( $subpageParts ) === 2 ) {
			return $subpageParts[1];
		}

		return $request->getText( self::FIELD_LANGUAGE ) ?: $this->getLanguage()->getCode();
	}

	private function displaySchemaLanguageSelectionForm( $defaultId, $defaultLanguage ) {
		$formDescriptor = $this->getSchemaSelectionFormFields( $defaultId, $defaultLanguage );

		$form = $this->htmlFormProvider::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setSubmitName( self::SUBMIT_SELECTION_NAME )
			->setSubmitID( 'entityschema-special-schema-id-submit' )
			->setSubmitTextMsg( 'entityschema-special-id-submit' )
			->setTitle( $this->getPageTitle() );
		$form->prepareForm();
		$submitStatus = $form->tryAuthorizedSubmit();
		$form->displayForm( $submitStatus ?: Status::newGood() );
	}

	private function displayEditForm( SchemaId $id, $langCode, $baseRevId ) {
		$output = $this->getOutput();
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id->getId() );
		$schemaNameBadge = $this->getSchemaNameBadge( $title, $langCode, $baseRevId );
		$formDescriptor = $this->getEditFormFields( $id, $langCode, $schemaNameBadge, $baseRevId );

		$form = $this->htmlFormProvider::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setSubmitName( self::SUBMIT_EDIT_NAME )
			->setSubmitID( 'entityschema-special-schema-id-submit' )
			->setSubmitTextMsg( 'entityschema-special-id-submit' )
			->setValidationErrorMessage( [ [
				'entityschema-error-possibly-multiple-messages-available'
			] ] );
		$form->prepareForm();

		if ( !$this->isSecondForm() ) {
			$form->setSubmitCallback( [ $this, 'submitEditFormCallback' ] );

			$submitStatus = $form->tryAuthorizedSubmit();
			if ( $submitStatus && $submitStatus->isGood() ) {
				$output->redirect(
					$submitStatus->getValue()
				);
				return;
			}
		}

		$output->addModules( [
			'ext.EntitySchema.special.setSchemaLabelDescriptionAliases.edit'
		] );
		$output->addJsConfigVars(
			'wgEntitySchemaNameBadgeMaxSizeChars',
			intval( $this->getConfig()->get( 'EntitySchemaNameBadgeMaxSizeChars' ) )
		);
		$this->displayWarnings( $output );
		$form->displayForm( $submitStatus ?? Status::newGood() );
		$this->displayCopyright( $output );
	}

	/**
	 * Check if the second form is requested.
	 *
	 * @return bool
	 */
	private function isSecondForm() {
		return $this->getContext()->getRequest()->getCheck( self::SUBMIT_SELECTION_NAME );
	}

	/**
	 * Gets the Schema NameBadge (label, desc, aliases) by interface language
	 *
	 * @param Title $title instance of Title for a specific Schema
	 * @param string $langCode
	 * @param int &$revId the revision from which to load data, or 0 to load the latest revision
	 * of $title, in which case &$revId will be replaced with that revision's ID
	 *
	 * @return NameBadge
	 * @throws MWException
	 */
	private function getSchemaNameBadge( Title $title, $langCode, &$revId ) {
		if ( $revId > 0 ) {
			$revision = MediaWikiServices::getInstance()->getRevisionLookup()
				->getRevisionById( $revId );
			if ( $revision->getPageId() !== $title->getArticleID() ) {
				throw new MWException( 'revision does not match title' );
			}
		} else {
			$wikiPage = WikiPage::factory( $title );
			$revision = $wikiPage->getRevisionRecord();
			$revId = $revision->getId();
		}
		// @phan-suppress-next-line PhanUndeclaredMethod
		$schema = $revision->getContent( SlotRecord::MAIN )->getText();
		$converter = new SchemaConverter();
		return $converter->getMonolingualNameBadgeData( $schema, $langCode );
	}

	private function getSchemaSelectionFormFields( $defaultId, $defaultLanguage ) {
		$inputValidator = InputValidator::newFromGlobalState();
		return [
			self::FIELD_ID => [
				'name' => self::FIELD_ID,
				'type' => 'text',
				'id' => 'entityschema-special-schema-id',
				'required' => true,
				'default' => $defaultId ?: '',
				'placeholder-message' => 'entityschema-special-id-placeholder',
				'label-message' => 'entityschema-special-id-inputlabel',
				'validation-callback' => [
					$inputValidator,
					'validateIDExists'
				],
			],
			self::FIELD_LANGUAGE => [
				'name' => self::FIELD_LANGUAGE,
				'type' => 'text',
				'id' => 'entityschema-language-code',
				'required' => true,
				'default' => $defaultLanguage,
				'label-message' => 'entityschema-special-language-inputlabel',
				'validation-callback' => [
					$inputValidator,
					'validateLangCodeIsSupported'
				],
			],
		];
	}

	private function getEditFormFields(
		SchemaId $id,
		$badgeLangCode,
		NameBadge $nameBadge,
		$baseRevId
	) {
		$label = $nameBadge->label;
		$description = $nameBadge->description;
		$aliases = implode( '|', $nameBadge->aliases );
		$uiLangCode = $this->getLanguage()->getCode();
		$langName = Language::fetchLanguageName( $badgeLangCode, $uiLangCode );
		$inputValidator = InputValidator::newFromGlobalState();
		return [
			'notice' => [
				'type' => 'info',
				'raw' => true,
				'default' => $this->buildLanguageAndSchemaNotice( $langName, $label, $id ),
			],
			self::FIELD_ID => [
				'name' => self::FIELD_ID,
				'type' => 'hidden',
				'id' => 'entityschema-id',
				'required' => true,
				'default' => $id->getId(),
			],
			self::FIELD_LANGUAGE => [
				'name' => self::FIELD_LANGUAGE,
				'type' => 'hidden',
				'id' => 'entityschema-language-code',
				'required' => true,
				'default' => $badgeLangCode,
			],
			self::FIELD_LABEL => [
				'name' => self::FIELD_LABEL,
				'type' => 'text',
				'id' => 'entityschema-title-label',
				'default' => $label,
				'placeholder-message' => $this->msg( 'entityschema-label-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'entityschema-special-label',
				'validation-callback' => [
					$inputValidator,
					'validateStringInputLength'
				],
			],
			self::FIELD_DESCRIPTION => [
				'name' => self::FIELD_DESCRIPTION,
				'type' => 'text',
				'default' => $description,
				'id' => 'entityschema-heading-description',
				'placeholder-message' => $this->msg( 'entityschema-description-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'entityschema-special-description',
				'validation-callback' => [
					$inputValidator,
					'validateStringInputLength'
				],
			],
			self::FIELD_ALIASES => [
				'name' => self::FIELD_ALIASES,
				'type' => 'text',
				'default' => $aliases,
				'id' => 'entityschema-heading-aliases',
				'placeholder-message' => $this->msg( 'entityschema-aliases-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'entityschema-special-aliases',
				'validation-callback' => [
					$inputValidator,
					'validateAliasesLength'
				],
			],
			self::FIELD_BASE_REV => [
				'name' => self::FIELD_BASE_REV,
				'type' => 'hidden',
				'required' => true,
				'default' => $baseRevId,
			],
		];
	}

	/**
	 * Validate ID and Language Code values
	 *
	 * @param string|null $id ID of the Schema
	 * @param string|null $language language code of the Schema
	 *
	 * @return bool
	 */
	private function isSelectionDataValid( $id, $language ) {
		if ( $id === null || $language === null ) {
			return false;
		}
		$inputValidator = InputValidator::newFromGlobalState();

		return $inputValidator->validateIDExists( $id ) === true &&
			$inputValidator->validateLangCodeIsSupported( $language ) === true;
	}

	private function displayCopyright( OutputPage $output ) {
		$output->addHTML( $this->getCopyrightHTML() );
	}

	private function displayWarnings( OutputPage $output ) {
		foreach ( $this->getWarnings() as $warning ) {
			$output->addHTML( Html::rawElement( 'div', [ 'class' => 'warning' ], $warning ) );
		}
	}

	/**
	 * Build the info message atop of the second form
	 *
	 * @return string HTML
	 */
	private function buildLanguageAndSchemaNotice( $langName, $label, SchemaId $schemaId ) {
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $schemaId->getId() );
		return $this->msg( 'entityschema-special-setlabeldescriptionaliases-info' )
			->params( $langName )
			->params( $this->getSchemaDisplayLabel( $label, $schemaId ) )
			->params( $title->getPrefixedText() )
			->parse();
	}

	private function getSchemaDisplayLabel( $label, SchemaId $schemaId ) {
		if ( !$label ) {
			return $schemaId->getId();
		}

		return $label . ' ' . $this->msg( 'parentheses' )->params( $schemaId->getId() )->escaped();
	}

	/**
	 * @return string HTML
	 */
	private function getCopyrightHTML() {
		return $this->msg( 'entityschema-newschema-copyright' )
			->params(
				$this->msg( 'entityschema-special-id-submit' )->text(),
				$this->msg( 'copyrightpage' )->text(),
				// FIXME: make license configurable
				'[https://creativecommons.org/publicdomain/zero/1.0/ Creative Commons CC0 License]'
			)->parse();
	}

	private function getWarnings(): array {
		if ( $this->getUser()->isAnon() ) {
			return [
				$this->msg(
					'entityschema-anonymouseditwarning'
				)->parse(),
			];
		}

		return [];
	}

	protected function getGroupName() {
		return 'wikibase';
	}

	private function checkBlocked( LinkTarget $title ) {
		if ( MediaWikiServices::getInstance()->getPermissionManager()
			->isBlockedFrom( $this->getUser(), $title )
		) {
			throw new UserBlockedError( $this->getUser()->getBlock() );
		}
	}

}
