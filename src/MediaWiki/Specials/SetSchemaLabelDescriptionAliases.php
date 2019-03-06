<?php

namespace Wikibase\Schema\MediaWiki\Specials;

use Html;
use HTMLForm;
use Language;
use SpecialPage;
use OutputPage;
use UserBlockedError;
use Wikibase\Schema\Domain\Model\SchemaId;
use Status;
use Title;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter;
use Wikibase\Schema\DataAccess\MediaWikiPageUpdaterFactory;
use Wikibase\Schema\Services\SchemaDispatcher\NameBadge;
use Wikibase\Schema\Services\SchemaDispatcher\SchemaDispatcher;
use Wikibase\Schema\DataAccess\WatchlistUpdater;
use RuntimeException;
use InvalidArgumentException;
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
	const SUBMIT_SELECTION_NAME = 'submit-selection';
	const SUBMIT_EDIT_NAME = 'submit-edit';

	public function __construct() {
		parent::__construct(
			'SetSchemaLabelDescriptionAliases'
		);
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );

		$id = $this->getContext()->getRequest()->getText( self::FIELD_ID ) ?: null;
		$language = $this->getContext()->getLanguage()->getCode() ?: null;

		$schemaId = $this->validateSchemaSelectionFormData( $id, $language );

		if ( !$schemaId ) {
			$this->displaySchemaLanguageSelectionForm();
		} else {
			$this->displayEditForm( $schemaId );
		}
	}

	public function submitSelectionCallback( $data ) {
		$status = Status::newGood();

		if ( !$this->validateSchemaSelectionFormData(
				$data[ self::FIELD_ID ],
				$data[ self::FIELD_LANGUAGE ]
			)
		) {
			$status->fatal( 'wikibaseschema-special-setlabeldescriptionaliases-warning' );
		}
		return $status;
	}

	public function submitEditFormCallback( $data ) {
		$updaterFactory = new MediaWikiPageUpdaterFactory( $this->getContext()->getUser() );
		$watchListUpdater = new WatchlistUpdater( $this->getUser(), NS_WBSCHEMA_JSON );
		try {
			$id = new SchemaId( $data[ self::FIELD_ID ] );
		} catch ( InvalidArgumentException $e ) {
			return Status::newFatal( 'wikibaseschema-error-schemaupdate-failed' );
		}
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $id->getId() );
		$this->checkBlocked( $title );
		$aliases = array_map( 'trim', explode( '|', $data[ self::FIELD_ALIASES ] ) );
		$schemaWriter = new MediaWikiRevisionSchemaWriter( $updaterFactory, $this, $watchListUpdater );
		try {
			$schemaWriter->updateSchemaNameBadge(
				$id,
				'en',
				$data[ self::FIELD_LABEL ],
				$data[ self::FIELD_DESCRIPTION ],
				$aliases
			);
		} catch ( RunTimeException $e ) {
			return Status::newFatal( 'wikibaseschema-error-schemaupdate-failed' );
		}

		return Status::newGood( $title->getFullURL() );
	}

	public function getDescription() {
		return $this->msg( 'wikibaseschema-special-setlabeldescriptionaliases' )->text();
	}

	private function displaySchemaLanguageSelectionForm() {
		$formDescriptor = $this->getSchemaSelectionFormFields();

		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setSubmitName( self::SUBMIT_SELECTION_NAME )
			->setSubmitID( 'wbschema-special-schema-id-submit' )
			->setSubmitTextMsg( 'wikibaseschema-special-id-submit' )
			->setSubmitCallback( [ $this, 'submitSelectionCallback' ] );
		$form->prepareForm();
		$submitStatus = $form->tryAuthorizedSubmit();
		$this->displayBeforeForm( $this->getOutput() );
		$form->displayForm( $submitStatus ?: Status::newGood() );
	}

	private function displayEditForm( SchemaId $id ) {
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $id->getId() );
		$schemaNameBadge = $this->getSchemaNameBadge( $title );
		$formDescriptor = $this->getEditFormFields( $id, $schemaNameBadge );

		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setSubmitName( self::SUBMIT_EDIT_NAME )
			->setSubmitID( 'wbschema-special-schema-id-submit' )
			->setSubmitTextMsg( 'wikibaseschema-special-id-submit' );
		$form->prepareForm();

		if ( !$this->isSecondForm() ) {
			$form->setSubmitCallback( [ $this, 'submitEditFormCallback' ] );

			$submitStatus = $form->tryAuthorizedSubmit();
			if ( $submitStatus && $submitStatus->isGood() ) {
				$this->getOutput()->redirect(
					$submitStatus->getValue()
				);
				return;
			}
		}

		$form->displayForm( $submitStatus ?? Status::newGood() );
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
	 *
	 * @return NameBadge
	 */
	private function getSchemaNameBadge( Title $title ) {
		$wikiPage = WikiPage::factory( $title );
		// @phan-suppress-next-line PhanUndeclaredMethod
		$schema = $wikiPage->getContent()->getText();
		$dispatcher = new SchemaDispatcher();
		$schemaNameBadge = $dispatcher->getMonolingualNameBadgeData( $schema, 'en' );

		return $schemaNameBadge;
	}

	private function getSchemaSelectionFormFields() {
		return [
			self::FIELD_ID => [
				'name' => self::FIELD_ID,
				'type' => 'text',
				'id' => 'wbschema-special-schema-id',
				'required' => true,
				'default' => '',
				'placeholder-message' => 'wikibaseschema-special-id-placeholder',
				'label-message' => 'wikibaseschema-special-id-inputlabel',
			],
			self::FIELD_LANGUAGE => [
				'name' => self::FIELD_LANGUAGE,
				'type' => 'text',
				'id' => 'wbschema-language-code',
				'required' => true,
				'default' => 'en',
				'label-message' => 'wikibaseschema-special-language-inputlabel',
			]
		];
	}

	private function getEditFormFields( SchemaId $id, NameBadge $nameBadge ) {
		$label = $nameBadge->label;
		$description = $nameBadge->description;
		$aliases = implode( '|', $nameBadge->aliases );
		$langCode = $this->getLanguage()->getCode();
		// FIXME: T216145: change this 'en' to the chosen language from step 1
		$langName = Language::fetchLanguageName( 'en', $langCode );
		return [
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
				'default' => 'en', // FIXME: T216145: change this 'en' to the chosen language from step 1
			],
			self::FIELD_LABEL => [
				'name' => self::FIELD_LABEL,
				'type' => 'text',
				'id' => 'wbschema-title-label',
				'default' => $label,
				'placeholder-message' => $this->msg( 'wikibaseschema-label-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'wikibaseschema-special-label',
			],
			self::FIELD_DESCRIPTION => [
				'name' => self::FIELD_DESCRIPTION,
				'type' => 'text',
				'default' => $description,
				'id' => 'wbschema-heading-description',
				'placeholder-message' => $this->msg( 'wikibaseschema-description-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'wikibaseschema-special-description',
			],
			self::FIELD_ALIASES => [
				'name' => self::FIELD_ALIASES,
				'type' => 'text',
				'default' => $aliases,
				'id' => 'wbschema-heading-aliases',
				'placeholder-message' => $this->msg( 'wikibaseschema-aliases-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'wikibaseschema-special-aliases',
			]
		];
	}

	/**
	 * Validate ID and Language Code values
	 *
	 * @param string|null $id, ID of the Schema
	 * @param string|null $language, two letter language code of the Schema
	 *
	 * @return SchemaId|bool SchemaId if valid, false otherwise
	 */
	private function validateSchemaSelectionFormData( $id, $language ) {
		if ( $language === null || $id === null ) {
			return false;
		}
		try {
			$id = new SchemaId( $id );
		} catch ( InvalidArgumentException $e ) {
			return false;
		}
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $id->getId() );
		if ( !$title->exists() ) {
			return false;
		}
		return $id;
	}

	private function displayBeforeForm( OutputPage $output ) {
		$output->addHTML( $this->getCopyrightHTML() );

		foreach ( $this->getWarnings() as $warning ) {
			$output->addHTML( Html::rawElement( 'div', [ 'class' => 'warning' ], $warning ) );
		}
	}

	/**
	 * @return string HTML
	 */
	private function getCopyrightHTML() {
		return $this->msg( 'wikibaseschema-newschema-copyright' )
			->params(
				$this->msg( 'wikibaseschema-newschema-submit' )->text(),
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
