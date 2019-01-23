<?php

namespace Wikibase\Schema\MediaWiki\Specials;

use CommentStoreComment;
use HTMLForm;
use HTMLTextField;
use MediaWiki\MediaWikiServices;
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

//		$out = $this->getOutput();
//		$this->displayBeforeForm( $out ); // fixme: add copyright etc.

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
		$title = Title::makeTitle( 12300, $id );
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
			'schema' => '',
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
		];
	}

}
