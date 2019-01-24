<?php

namespace Wikibase\Schema\MediaWiki\Specials;

use CommentStoreComment;
use Html;
use HTMLForm;
use HTMLTextAreaField;
use HTMLTextField;
use MediaWiki\MediaWikiServices;
use OutputPage;
use SpecialPage;
use Status;
use Title;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\SqlIdGenerator;
use WikiPage;

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
	const FIELD_SCHEMA_SHEXC = 'schema-shexc';

	public function __construct(
		$name = '',
		$restriction = '',
		$listed = true,
		$function = false,
		$file = '',
		$includable = false
	) {
		parent::__construct(
			'NewSchema',
			'createpage'
		);
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );

		// fixme: check permissions

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
				$submitStatus->getValue()->getFullURL()
			);
			return;
		}

		$this->displayBeforeForm( $this->getOutput() );

		$form->displayForm( Status::newGood() );
	}

	public function submitCallback( $data, HTMLForm $form ) {
		// TODO: no form data validation??

		// FIXME: inject this
		$idGenerator = new SqlIdGenerator(
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			'wbschema_id_counter'
		);
		$id = 'O' . $idGenerator->getNewId();
		$title = Title::makeTitle( NS_WBSCHEMA_JSON, $id );
		$wikipage = WikiPage::factory( $title );
		$updater = $wikipage->newPageUpdater( $this->getContext()->getUser() );

		$dataToSave = [
			'labels' => [
				'en' => $data[ self::FIELD_LABEL ],
			],
			'descriptions' => [
				'en' => $data[ self::FIELD_DESCRIPTION ],
			],
			'aliases' => [
				'en' => array_filter( array_map( 'trim', explode( '|', $data[ self::FIELD_ALIASES ] ) ) ),
			],
			'schema' => $data[ self::FIELD_SCHEMA_SHEXC ],
		];

		$updater->setContent( 'main', new WikibaseSchemaContent( json_encode( $dataToSave ) ) );
		$updater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'abc' )
		);

//		if ( !$saveStatus->isGood() ) {
//			return $saveStatus;
//		}

		return Status::newGood( $title ); // fixme add redirect here!
	}

	public function getDescription() {
		return $this->msg( 'special-newschema' )->text();
	}

	protected function getGroupName() {
		return 'wikibase';
	}

	private function getFormFields(): array {
		return [
			self::FIELD_LABEL => [
				'name' => self::FIELD_LABEL,
				'class' => HTMLTextField::class,
				'id' => 'wbschema-newschema-label',
				'required' => true,
				'placeholder-message' => 'wikibaseschema-label-edit-placeholder',
				'label-message' => 'wikibaseschema-newschema-label',
			],
			self::FIELD_DESCRIPTION => [
				'name' => self::FIELD_DESCRIPTION,
				'class' => HTMLTextField::class,
				'id' => 'wbschema-newschema-description',
				'placeholder-message' => 'wikibaseschema-description-edit-placeholder',
				'label-message' => 'wikibaseschema-newschema-description',
			],
			self::FIELD_ALIASES => [
				'name' => self::FIELD_ALIASES,
				'class' => HTMLTextField::class,
				'id' => 'wbschema-newschema-aliases',
				'placeholder-message' => 'wikibaseschema-aliases-edit-placeholder',
				'label-message' => 'wikibaseschema-newschema-aliases',
			],
			self::FIELD_SCHEMA_SHEXC => [
				'name' => self::FIELD_SCHEMA_SHEXC,
				'class' => HTMLTextAreaField::class,
				'id' => 'wbschema-newschema-schema-shexc',
				'placeholder' => "<human> {\n  wdt:P31 [wd:Q5]\n}",
				'label-message' => 'wikibaseschema-newschema-schema-shexc',
				'useeditfont' => true,
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

}
