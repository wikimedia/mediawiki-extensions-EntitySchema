<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Specials;

use EntitySchema\DataAccess\EntitySchemaStatus;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaUpdater;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\EntitySchemaRedirectTrait;
use EntitySchema\Presentation\InputValidator;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use EntitySchema\Services\Converter\NameBadge;
use InvalidArgumentException;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\TempUser\TempUserConfig;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\CopyrightMessageBuilder;
use Wikibase\Repo\Specials\SpecialPageCopyrightView;

/**
 * Page for editing label, description and aliases of a Schema
 *
 * @license GPL-2.0-or-later
 */
class SetEntitySchemaLabelDescriptionAliases extends SpecialPage {

	use EntitySchemaRedirectTrait;

	public const FIELD_ID = 'ID';
	public const FIELD_LANGUAGE = 'languagecode';
	public const FIELD_DESCRIPTION = 'description';
	public const FIELD_LABEL = 'label';
	public const FIELD_ALIASES = 'aliases';
	public const FIELD_BASE_REV = 'base-rev';
	private const SUBMIT_SELECTION_NAME = 'submit-selection';
	private const SUBMIT_EDIT_NAME = 'submit-edit';

	private string $htmlFormProvider;

	private SpecialPageCopyrightView $copyrightView;

	private TempUserConfig $tempUserConfig;

	public function __construct(
		TempUserConfig $tempUserConfig,
		SettingsArray $repoSettings,
		string $htmlFormProvider = HTMLForm::class
	) {
		parent::__construct(
			'SetEntitySchemaLabelDescriptionAliases',
			'edit'
		);

		$this->htmlFormProvider = $htmlFormProvider;
		$this->copyrightView = new SpecialPageCopyrightView(
			new CopyrightMessageBuilder(),
			$repoSettings->getSetting( 'dataRightsUrl' ),
			$repoSettings->getSetting( 'dataRightsText' )
		);
		$this->tempUserConfig = $tempUserConfig;
	}

	/** @inheritDoc */
	public function execute( $subPage ): void {
		parent::execute( $subPage );

		$request = $this->getRequest();
		$subPage = $subPage ?: '';
		$id = $this->getIdFromSubpageOrRequest( $subPage, $request );
		$language = $this->getLanguageFromSubpageOrRequestOrUI( $subPage, $request );

		if ( $this->isSelectionDataValid( $id, $language ) ) {
			$baseRevId = $request->getInt( self::FIELD_BASE_REV );
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable isSelectionDataValid() guarantees $id !== null
			$this->displayEditForm( new EntitySchemaId( $id ), $language, $baseRevId );
			return;
		}

		$this->displaySchemaLanguageSelectionForm( $id, $language );
	}

	public function submitEditFormCallback( array $data ): Status {
		try {
			$id = new EntitySchemaId( $data[self::FIELD_ID] );
		} catch ( InvalidArgumentException ) {
			return Status::newFatal( 'entityschema-error-schemaupdate-failed' );
		}
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id->getId() );
		$this->checkBlocked( $title );
		$aliases = array_map( 'trim', explode( '|', $data[self::FIELD_ALIASES] ) );
		$schemaUpdater = MediaWikiRevisionEntitySchemaUpdater::newFromContext( $this->getContext() );

		$status = $schemaUpdater->updateSchemaNameBadge(
			$id,
			$data[self::FIELD_LANGUAGE],
			$data[self::FIELD_LABEL],
			$data[self::FIELD_DESCRIPTION],
			$aliases,
			(int)$data[self::FIELD_BASE_REV]
		);
		$status->replaceMessage( 'edit-conflict', 'entityschema-error-namebadge-conflict' );
		return $status;
	}

	public function getDescription(): Message {
		return $this->msg( 'entityschema-special-setlabeldescriptionaliases' );
	}

	private function getIdFromSubpageOrRequest( string $subpage, WebRequest $request ): ?string {
		$subpageParts = array_filter( explode( '/', $subpage, 2 ) );
		if ( count( $subpageParts ) > 0 ) {
			return $subpageParts[0];
		}
		return $request->getText( self::FIELD_ID ) ?: null;
	}

	private function getLanguageFromSubpageOrRequestOrUI( string $subpage, WebRequest $request ): string {
		$subpageParts = array_filter( explode( '/', $subpage, 2 ) );
		if ( count( $subpageParts ) === 2 ) {
			return $subpageParts[1];
		}

		return $request->getText( self::FIELD_LANGUAGE ) ?: $this->getLanguage()->getCode();
	}

	private function displaySchemaLanguageSelectionForm( ?string $defaultId, string $defaultLanguage ): void {
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

	private function displayEditForm( EntitySchemaId $id, string $langCode, int $baseRevId ): void {
		$output = $this->getOutput();
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $id->getId() );
		$schemaNameBadge = $this->getSchemaNameBadge( $title, $langCode, $baseRevId );
		$formDescriptor = $this->getEditFormFields( $id, $langCode, $schemaNameBadge, $baseRevId );

		$form = $this->htmlFormProvider::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setSubmitName( self::SUBMIT_EDIT_NAME )
			->setSubmitID( 'entityschema-special-schema-id-submit' )
			->setSubmitTextMsg( 'entityschema-special-id-submit' )
			->setValidationErrorMessage( [ [
				'entityschema-error-possibly-multiple-messages-available',
			] ] );
		$form->prepareForm();

		if ( !$this->isSecondForm() ) {
			$form->setSubmitCallback( [ $this, 'submitEditFormCallback' ] );

			$submitStatus = $form->tryAuthorizedSubmit();
			if ( $submitStatus && $submitStatus->isGood() ) {
				// wrap it, in case HTMLForm turned it into a generic Status
				$submitStatus = EntitySchemaStatus::wrap( $submitStatus );
				$this->redirectToEntitySchema( $submitStatus );
				return;
			}
		}

		$output->addModules( [
			'ext.EntitySchema.special.setEntitySchemaLabelDescriptionAliases.edit',
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
	 */
	private function isSecondForm(): bool {
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
	 */
	private function getSchemaNameBadge( Title $title, string $langCode, int &$revId ): NameBadge {
		if ( $revId > 0 ) {
			$revision = MediaWikiServices::getInstance()->getRevisionLookup()
				->getRevisionById( $revId );
			if ( $revision->getPageId() !== $title->getArticleID() ) {
				throw new InvalidArgumentException( 'revision does not match title' );
			}
		} else {
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
			$revision = $wikiPage->getRevisionRecord();
			$revId = $revision->getId();
		}
		// @phan-suppress-next-line PhanUndeclaredMethod
		$schema = $revision->getContent( SlotRecord::MAIN )->getText();
		$converter = new EntitySchemaConverter();
		return $converter->getMonolingualNameBadgeData( $schema, $langCode );
	}

	private function getSchemaSelectionFormFields( ?string $defaultId, string $defaultLanguage ): array {
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
					'validateIDExists',
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
					'validateLangCodeIsSupported',
				],
			],
		];
	}

	private function getEditFormFields(
		EntitySchemaId $id,
		string $badgeLangCode,
		NameBadge $nameBadge,
		int $baseRevId
	): array {
		$label = $nameBadge->label;
		$description = $nameBadge->description;
		$aliases = implode( '|', $nameBadge->aliases );
		$uiLangCode = $this->getLanguage()->getCode();
		$langName = MediaWikiServices::getInstance()->getLanguageNameUtils()
			->getLanguageName( $badgeLangCode, $uiLangCode );
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
					'validateStringInputLength',
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
					'validateStringInputLength',
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
					'validateAliasesLength',
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
	private function isSelectionDataValid( ?string $id, ?string $language ): bool {
		if ( $id === null || $language === null ) {
			return false;
		}
		$inputValidator = InputValidator::newFromGlobalState();

		return $inputValidator->validateIDExists( $id ) === true &&
			$inputValidator->validateLangCodeIsSupported( $language ) === true;
	}

	private function displayCopyright( OutputPage $output ): void {
		$output->addHTML( $this->copyrightView
			->getHtml( $this->getLanguage(), 'entityschema-special-id-submit' ) );
	}

	private function displayWarnings( OutputPage $output ): void {
		foreach ( $this->getWarnings() as $warning ) {
			$output->addHTML( Html::rawElement( 'div', [ 'class' => 'warning' ], $warning ) );
		}
	}

	/**
	 * Build the info message atop of the second form
	 *
	 * @return string HTML
	 */
	private function buildLanguageAndSchemaNotice(
		string $langName,
		string $label,
		EntitySchemaId $entitySchemaId
	): string {
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $entitySchemaId->getId() );
		return $this->msg( 'entityschema-special-setlabeldescriptionaliases-info' )
			->params( $langName )
			->params( $this->getSchemaDisplayLabel( $label, $entitySchemaId ) )
			->params( $title->getPrefixedText() )
			->parse();
	}

	private function getSchemaDisplayLabel( string $label, EntitySchemaId $entitySchemaId ): string {
		if ( !$label ) {
			return $entitySchemaId->getId();
		}

		return $label . ' ' . $this->msg( 'parentheses' )->params( $entitySchemaId->getId() )->escaped();
	}

	private function getWarnings(): array {
		if ( $this->getUser()->isAnon() && !$this->tempUserConfig->isEnabled() ) {
			return [
				$this->msg(
					'entityschema-anonymouseditwarning'
				)->parse(),
			];
		}

		return [];
	}

	protected function getGroupName(): string {
		return 'wikibase';
	}

	private function checkBlocked( LinkTarget $title ): void {
		$errors = MediaWikiServices::getInstance()->getPermissionManager()
			->getPermissionErrors( $this->getRestriction(), $this->getUser(), $title );
		if ( $errors !== [] ) {
			throw new PermissionsError( $this->getRestriction(), $errors );
		}
	}

}
