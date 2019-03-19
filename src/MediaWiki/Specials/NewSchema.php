<?php

namespace Wikibase\Schema\MediaWiki\Specials;

use Html;
use HTMLForm;
use Language;
use MediaWiki\MediaWikiServices;
use OutputPage;
use SpecialPage;
use Status;
use Title;
use UserBlockedError;
use Wikibase\Schema\DataAccess\MediaWikiPageUpdaterFactory;
use Wikibase\Schema\DataAccess\MediaWikiRevisionSchemaWriter;
use Wikibase\Schema\DataAccess\SqlIdGenerator;
use Wikibase\Schema\DataAccess\WatchlistUpdater;
use Wikibase\Schema\Presentation\InputValidator;

/**
 * Page for creating a new Wikibase Schema.
 *
 * @license GPL-2.0-or-later
 */
class NewSchema extends SpecialPage {

	/* public */
	const FIELD_DESCRIPTION = 'description';
	/* public */
	const FIELD_LABEL = 'label';
	/* public */
	const FIELD_ALIASES = 'aliases';
	/* public */
	const FIELD_SCHEMA_TEXT = 'schema-text';
	/* public */
	const FIELD_LANGUAGE = 'languagecode';

	public function __construct() {
		parent::__construct(
			'NewSchema',
			'createpage'
		);
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->checkPermissions();
		$this->checkBlocked( $subPage );
		$this->checkReadOnly();

		$form = HTMLForm::factory( 'ooui', $this->getFormFields(), $this->getContext() )
			->setSubmitName( 'submit' )
			->setSubmitID( 'wbschema-newschema-submit' )
			->setSubmitTextMsg( 'wikibaseschema-newschema-submit' )
			->setSubmitCallback( [ $this, 'submitCallback' ] );
		$form->prepareForm();

		/** @var Status|false $submitStatus `false` if form was not submitted */
		$submitStatus = $form->tryAuthorizedSubmit();

		if ( $submitStatus && $submitStatus->isGood() ) {
			$this->getOutput()->redirect(
				$submitStatus->getValue()
			);
			return;
		}

		$this->displayBeforeForm( $this->getOutput() );

		$form->displayForm( $submitStatus ?: Status::newGood() );
	}

	public function submitCallback( $data, HTMLForm $form ) {
		// TODO: no form data validation??

		$idGenerator = new SqlIdGenerator(
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			'wbschema_id_counter'
		);

		$pageUpdaterFactory = new MediaWikiPageUpdaterFactory( $this->getUser() );

		$schemaWriter = new MediawikiRevisionSchemaWriter(
			$pageUpdaterFactory,
			$this,
			new WatchlistUpdater( $this->getUser(), NS_WBSCHEMA_JSON ),
			$idGenerator
		);
		$newId = $schemaWriter->insertSchema(
			$data[self::FIELD_LANGUAGE],
			$data[self::FIELD_LABEL],
			$data[self::FIELD_DESCRIPTION],
			array_filter( array_map( 'trim', explode( '|', $data[self::FIELD_ALIASES] ) ) ),
			$data[self::FIELD_SCHEMA_TEXT]
		);

		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $newId->getId() );

		return Status::newGood( $title->getFullURL() );
	}

	public function getDescription() {
		return $this->msg( 'special-newschema' )->text();
	}

	protected function getGroupName() {
		return 'wikibase';
	}

	private function getFormFields(): array {
		$langCode = $this->getLanguage()->getCode();
		$langName = Language::fetchLanguageName( $langCode, $langCode );
		return [
			self::FIELD_LABEL => [
				'name' => self::FIELD_LABEL,
				'type' => 'text',
				'id' => 'wbschema-newschema-label',
				'required' => true,
				'default' => '',
				'placeholder-message' => $this->msg( 'wikibaseschema-label-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'wikibaseschema-newschema-label',
			],
			self::FIELD_DESCRIPTION => [
				'name' => self::FIELD_DESCRIPTION,
				'type' => 'text',
				'default' => '',
				'id' => 'wbschema-newschema-description',
				'placeholder-message' => $this->msg( 'wikibaseschema-description-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'wikibaseschema-newschema-description',
			],
			self::FIELD_ALIASES => [
				'name' => self::FIELD_ALIASES,
				'type' => 'text',
				'default' => '',
				'id' => 'wbschema-newschema-aliases',
				'placeholder-message' => $this->msg( 'wikibaseschema-aliases-edit-placeholder' )
					->params( $langName ),
				'label-message' => 'wikibaseschema-newschema-aliases',
			],
			self::FIELD_SCHEMA_TEXT => [
				'name' => self::FIELD_SCHEMA_TEXT,
				'type' => 'textarea',
				'default' => '',
				'id' => 'wbschema-newschema-schema-text',
				'placeholder' => "<human> {\n  wdt:P31 [wd:Q5]\n}",
				'label-message' => 'wikibaseschema-newschema-schema-shexc',
				'validation-callback' => [
					InputValidator::newFromGlobalState(),
					'validateSchemaTextLength'
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
			)
			->parse();
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

	/**
	 * Checks if the user is blocked from this page,
	 * and if they are, throws a {@link UserBlockedError}.
	 *
	 * @throws UserBlockedError
	 */
	protected function checkBlocked( $subPage ) {
		if ( $this->getUser()->isBlockedFrom( $this->getPageTitle( $subPage ) ) ) {
			throw new UserBlockedError( $this->getUser()->getBlock() );
		}
	}

}
