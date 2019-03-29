<?php

namespace Wikibase\Schema\MediaWiki\Specials;

use Html;
use HTMLForm;
use InvalidArgumentException;
use Language;
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
use Wikibase\Schema\DataAccess\EditConflict;
use Wikibase\Schema\DataAccess\MediaWikiPageUpdaterFactory;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaUpdater;
use Wikibase\Schema\DataAccess\WatchlistUpdater;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\Presentation\InputValidator;
use Wikibase\Schema\Services\SchemaConverter\NameBadge;
use Wikibase\Schema\Services\SchemaConverter\SchemaConverter;
use WikiPage;

/**
 * Page for editing label, description and aliases of a Schema
 *
 * @license GPL-2.0-or-later
 */
class SetSchemaLabelDescriptionAliases extends SpecialPage {

	const FIELD_ID = 'ID';
	const FIELD_LANGUAGE = 'languagecode';
	const FIELD_DESCRIPTION = 'description';
	const FIELD_LABEL = 'label';
	const FIELD_ALIASES = 'aliases';
	const FIELD_BASE_REV = 'base-rev';
	const SUBMIT_SELECTION_NAME = 'submit-selection';
	const SUBMIT_EDIT_NAME = 'submit-edit';

	private $htmlFormProvider;

	public function __construct( $htmlFormProvider = HTMLForm::class ) {
		parent::__construct(
			'SetSchemaLabelDescriptionAliases'
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
		$watchListUpdater = new WatchlistUpdater( $this->getUser(), NS_WBSCHEMA_JSON );
		try {
			$id = new SchemaId( $data[self::FIELD_ID] );
		} catch ( InvalidArgumentException $e ) {
			return Status::newFatal( 'wikibaseschema-error-schemaupdate-failed' );
		}
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $id->getId() );
		$this->checkBlocked( $title );
		$aliases = array_map( 'trim', explode( '|', $data[self::FIELD_ALIASES] ) );
		$schemaUpdater = new MediaWikiRevisionSchemaUpdater( $updaterFactory, $this, $watchListUpdater );
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
			return Status::newFatal( 'wikibaseschema-error-namebadge-conflict' );
		} catch ( RunTimeException $e ) {
			return Status::newFatal( 'wikibaseschema-error-schemaupdate-failed' );
		}

		return Status::newGood( $title->getFullURL() );
	}

	public function getDescription() {
		return $this->msg( 'wikibaseschema-special-setlabeldescriptionaliases' )->text();
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

		$formProvider = $this->htmlFormProvider; // FIXME: PHP7: inline this variable!
		$form = $formProvider::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setSubmitName( self::SUBMIT_SELECTION_NAME )
			->setSubmitID( 'wbschema-special-schema-id-submit' )
			->setSubmitTextMsg( 'wikibaseschema-special-id-submit' )
			->setTitle( $this->getPageTitle() );
		$form->prepareForm();
		$submitStatus = $form->tryAuthorizedSubmit();
		$form->displayForm( $submitStatus ?: Status::newGood() );
	}

	private function displayEditForm( SchemaId $id, $langCode, $baseRevId ) {
		$output = $this->getOutput();
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $id->getId() );
		$schemaNameBadge = $this->getSchemaNameBadge( $title, $langCode, $baseRevId );
		$formDescriptor = $this->getEditFormFields( $id, $langCode, $schemaNameBadge, $baseRevId );

		$formProvider = $this->htmlFormProvider; // FIXME: PHP7: inline this variable!
		$form = $formProvider::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setSubmitName( self::SUBMIT_EDIT_NAME )
			->setSubmitID( 'wbschema-special-schema-id-submit' )
			->setSubmitTextMsg( 'wikibaseschema-special-id-submit' )
			->setValidationErrorMessage( [ [
				'wikibaseschema-error-possibly-multiple-messages-available'
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
			'ext.WikibaseSchema.special.setSchemaLabelDescriptionAliases.edit'
		] );
		$output->addJsConfigVars(
			'wgWBSchemaNameBadgeMaxSizeChars',
			intval( $this->getConfig()->get( 'WBSchemaNameBadgeMaxSizeChars' ) )
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
	 * @throws \MWException
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
				'id' => 'wbschema-special-schema-id',
				'required' => true,
				'default' => $defaultId ?: '',
				'placeholder-message' => 'wikibaseschema-special-id-placeholder',
				'label-message' => 'wikibaseschema-special-id-inputlabel',
				'validation-callback' => [
					$inputValidator,
					'validateIDExists'
				],
			],
			self::FIELD_LANGUAGE => [
				'name' => self::FIELD_LANGUAGE,
				'type' => 'text',
				'id' => 'wbschema-language-code',
				'required' => true,
				'default' => $defaultLanguage,
				'label-message' => 'wikibaseschema-special-language-inputlabel',
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
				'id' => 'wbschema-id',
				'required' => true,
				'default' => $id->getId(),
			],
			self::FIELD_LANGUAGE => [
				'name' => self::FIELD_LANGUAGE,
				'type' => 'hidden',
				'id' => 'wbschema-language-code',
				'required' => true,
				'default' => $badgeLangCode,
			],
			self::FIELD_LABEL => [
				'name' => self::FIELD_LABEL,
				'type' => 'text',
				'id' => 'wbschema-title-label',
				'default' => $label,
				'placeholder-message' => $this->msg( 'wikibaseschema-label-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'wikibaseschema-special-label',
				'validation-callback' => [
					$inputValidator,
					'validateStringInputLength'
				],
			],
			self::FIELD_DESCRIPTION => [
				'name' => self::FIELD_DESCRIPTION,
				'type' => 'text',
				'default' => $description,
				'id' => 'wbschema-heading-description',
				'placeholder-message' => $this->msg( 'wikibaseschema-description-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'wikibaseschema-special-description',
				'validation-callback' => [
					$inputValidator,
					'validateStringInputLength'
				],
			],
			self::FIELD_ALIASES => [
				'name' => self::FIELD_ALIASES,
				'type' => 'text',
				'default' => $aliases,
				'id' => 'wbschema-heading-aliases',
				'placeholder-message' => $this->msg( 'wikibaseschema-aliases-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'wikibaseschema-special-aliases',
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
		if (
			$inputValidator->validateIDExists( $id ) !== true
			|| $inputValidator->validateLangCodeIsSupported( $language ) !== true
		) {
			return false;
		}
		return true;
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
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $schemaId->getId() );
		return $this->msg( 'wikibaseschema-special-setlabeldescriptionaliases-info' )
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
		return $this->msg( 'wikibaseschema-newschema-copyright' )
			->params(
				$this->msg( 'wikibaseschema-special-id-submit' )->text(),
				$this->msg( 'copyrightpage' )->text(),
				// FIXME: make license configurable
				'[https://creativecommons.org/publicdomain/zero/1.0/ Creative Commons CC0 License]'
			)->parse();
	}

	private function getWarnings(): array {
		if ( $this->getUser()->isAnon() ) {
			return [
				$this->msg(
					'wikibaseschema-anonymouseditwarning'
				)->parse(),
			];
		}

		return [];
	}

	protected function getGroupName() {
		return 'wikibase';
	}

	private function checkBlocked( Title $title ) {
		if ( $this->getUser()->isBlockedFrom( $title ) ) {
			throw new UserBlockedError( $this->getUser()->getBlock() );
		}
	}

}
