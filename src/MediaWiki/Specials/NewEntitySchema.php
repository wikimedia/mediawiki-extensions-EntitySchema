<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Specials;

use EntitySchema\DataAccess\EntitySchemaStatus;
use EntitySchema\DataAccess\MediaWikiPageUpdaterFactory;
use EntitySchema\DataAccess\MediaWikiRevisionEntitySchemaInserter;
use EntitySchema\Domain\Storage\IdGenerator;
use EntitySchema\MediaWiki\EntitySchemaRedirectTrait;
use EntitySchema\MediaWiki\EntitySchemaServices;
use EntitySchema\Presentation\InputValidator;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\TempUser\TempUserConfig;
use PermissionsError;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\CopyrightMessageBuilder;
use Wikibase\Repo\Specials\SpecialPageCopyrightView;

/**
 * Page for creating a new EntitySchema.
 *
 * @license GPL-2.0-or-later
 */
class NewEntitySchema extends SpecialPage {

	use EntitySchemaRedirectTrait;

	public const FIELD_DESCRIPTION = 'description';

	public const FIELD_LABEL = 'label';

	public const FIELD_ALIASES = 'aliases';

	public const FIELD_SCHEMA_TEXT = 'schema-text';

	public const FIELD_LANGUAGE = 'languagecode';

	private IdGenerator $idGenerator;

	private SpecialPageCopyrightView $copyrightView;

	private TempUserConfig $tempUserConfig;

	private MediaWikiPageUpdaterFactory $pageUpdaterFactory;

	public function __construct(
		TempUserConfig $tempUserConfig,
		SettingsArray $repoSettings,
		IdGenerator $idGenerator,
		MediaWikiPageUpdaterFactory $pageUpdaterFactory
	) {
		parent::__construct(
			'NewEntitySchema',
			'createpage'
		);
		$this->idGenerator = $idGenerator;
		$this->copyrightView = new SpecialPageCopyrightView(
			new CopyrightMessageBuilder(),
			$repoSettings->getSetting( 'dataRightsUrl' ),
			$repoSettings->getSetting( 'dataRightsText' )
		);
		$this->tempUserConfig = $tempUserConfig;
		$this->pageUpdaterFactory = $pageUpdaterFactory;
	}

	public function execute( $subPage ): void {
		parent::execute( $subPage );

		$this->checkPermissionsWithSubpage( $subPage );
		$this->checkReadOnly();

		$form = HTMLForm::factory( 'ooui', $this->getFormFields(), $this->getContext() )
			->setSubmitName( 'submit' )
			->setSubmitID( 'entityschema-newschema-submit' )
			->setSubmitTextMsg( 'entityschema-newschema-submit' )
			->setValidationErrorMessage( [ [
				'entityschema-error-possibly-multiple-messages-available',
			] ] )
			->setSubmitCallback( [ $this, 'submitCallback' ] );
		$form->prepareForm();

		/** @var Status|false $submitStatus `false` if form was not submitted */
		$submitStatus = $form->tryAuthorizedSubmit();

		if ( $submitStatus && $submitStatus->isGood() ) {
			// wrap it, in case HTMLForm turned it into a generic Status
			$submitStatus = EntitySchemaStatus::wrap( $submitStatus );
			$this->redirectToEntitySchema( $submitStatus );
			return;
		}

		$this->addJavaScript();
		$this->displayBeforeForm( $this->getOutput() );

		$form->displayForm( $submitStatus ?: Status::newGood() );
	}

	public function submitCallback( array $data, HTMLForm $form ): Status {
		// TODO: no form data validation??

		$services = MediaWikiServices::getInstance();
		$schemaInserter = new MediaWikiRevisionEntitySchemaInserter(
			$this->pageUpdaterFactory,
			EntitySchemaServices::getWatchlistUpdater( $services ),
			$this->idGenerator,
			$this->getContext(),
			$services->getLanguageFactory(),
			$services->getHookContainer()
		);
		return $schemaInserter->insertSchema(
			$data[self::FIELD_LANGUAGE],
			$data[self::FIELD_LABEL],
			$data[self::FIELD_DESCRIPTION],
			array_filter( array_map( 'trim', explode( '|', $data[self::FIELD_ALIASES] ) ) ),
			$data[self::FIELD_SCHEMA_TEXT]
		);
	}

	public function getDescription(): Message {
		return $this->msg( 'special-newschema' );
	}

	protected function getGroupName(): string {
		return 'wikibase';
	}

	private function getFormFields(): array {
		$langCode = $this->getLanguage()->getCode();
		$langName = MediaWikiServices::getInstance()->getLanguageNameUtils()
			->getLanguageName( $langCode, $langCode );
		$inputValidator = InputValidator::newFromGlobalState();
		return [
			self::FIELD_LABEL => [
				'name' => self::FIELD_LABEL,
				'type' => 'text',
				'id' => 'entityschema-newschema-label',
				'required' => true,
				'default' => '',
				'placeholder-message' => $this->msg( 'entityschema-label-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'entityschema-newschema-label',
				'validation-callback' => [
					$inputValidator,
					'validateStringInputLength',
				],
			],
			self::FIELD_DESCRIPTION => [
				'name' => self::FIELD_DESCRIPTION,
				'type' => 'text',
				'default' => '',
				'id' => 'entityschema-newschema-description',
				'placeholder-message' => $this->msg( 'entityschema-description-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'entityschema-newschema-description',
				'validation-callback' => [
					$inputValidator,
					'validateStringInputLength',
				],
			],
			self::FIELD_ALIASES => [
				'name' => self::FIELD_ALIASES,
				'type' => 'text',
				'default' => '',
				'id' => 'entityschema-newschema-aliases',
				'placeholder-message' => $this->msg( 'entityschema-aliases-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'entityschema-newschema-aliases',
				'validation-callback' => [
					$inputValidator,
					'validateAliasesLength',
				],
			],
			self::FIELD_SCHEMA_TEXT => [
				'name' => self::FIELD_SCHEMA_TEXT,
				'type' => 'textarea',
				'default' => '',
				'id' => 'entityschema-newschema-schema-text',
				'placeholder' => "<human> {\n  wdt:P31 [wd:Q5]\n}",
				'label-message' => 'entityschema-newschema-schema-shexc',
				'validation-callback' => [
					$inputValidator,
					'validateSchemaTextLength',
				],
				'useeditfont' => true,
			],
			self::FIELD_LANGUAGE => [
				'name' => self::FIELD_LANGUAGE,
				'type' => 'hidden',
				'default' => $langCode,
			],
		];
	}

	private function displayBeforeForm( OutputPage $output ): void {
		$output->addHTML( $this->getCopyrightHTML() );

		foreach ( $this->getWarnings() as $warning ) {
			$output->addHTML( Html::rawElement( 'div', [ 'class' => 'warning' ], $warning ) );
		}
	}

	/**
	 * @return string HTML
	 */
	private function getCopyrightHTML() {
		return $this->copyrightView
			->getHtml( $this->getLanguage(), 'entityschema-newschema-submit' );
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

	private function addJavaScript(): void {
		$output = $this->getOutput();
		$output->addModules( [
			'ext.EntitySchema.special.newEntitySchema',
		] );
		$output->addJsConfigVars( [
			'wgEntitySchemaSchemaTextMaxSizeBytes' =>
				intval( $this->getConfig()->get( 'EntitySchemaSchemaTextMaxSizeBytes' ) ),
			'wgEntitySchemaNameBadgeMaxSizeChars' =>
				intval( $this->getConfig()->get( 'EntitySchemaNameBadgeMaxSizeChars' ) ),
		] );
	}

	/**
	 * Checks if the user has permissions to perform this page’s action,
	 * and throws a {@link PermissionsError} if they don’t.
	 *
	 * @throws PermissionsError
	 */
	protected function checkPermissionsWithSubpage( ?string $subPage ): void {
		$pm = MediaWikiServices::getInstance()->getPermissionManager();
		$checkReplica = !$this->getRequest()->wasPosted();
		$permissionErrors = $pm->getPermissionErrors(
			$this->getRestriction(),
			$this->getUser(),
			$this->getPageTitle( $subPage ),
			$checkReplica ? $pm::RIGOR_FULL : $pm::RIGOR_SECURE,
			[
				'ns-specialprotected', // ignore “special pages cannot be edited”
			]
		);
		if ( $permissionErrors !== [] ) {
			// reindex $permissionErrors:
			// the ignoreErrors param (ns-specialprotected) may have left holes,
			// but PermissionsError expects $errors[0] to exist
			$permissionErrors = array_values( $permissionErrors );
			throw new PermissionsError( $this->getRestriction(), $permissionErrors );
		}
	}

}
