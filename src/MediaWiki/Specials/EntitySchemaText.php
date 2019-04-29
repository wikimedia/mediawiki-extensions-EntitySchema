<?php

namespace EntitySchema\MediaWiki\Specials;

use HttpError;
use InvalidArgumentException;
use SpecialPage;
use Title;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\MediaWiki\Content\WikibaseSchemaContent;
use EntitySchema\Services\SchemaConverter\SchemaConverter;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaText extends SpecialPage {

	public function __construct() {
		parent::__construct(
			'EntitySchemaText',
			'read'
		);
	}

	public function execute( $subPage ) {
		parent::execute( $subPage );
		$schemaId = $this->getIdFromSubpage( $subPage );
		if ( !$schemaId ) {
			$this->getOutput()->addWikiMsg( 'entityschema-schematext-text' );
			$this->getOutput()->returnToMain();
			return;
		}
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $schemaId->getId() );

		if ( !$title->exists() ) {
			throw new HttpError( 404, $this->getOutput()->msg(
				'entityschema-schematext-missing', $subPage
			) );
		}

		$this->sendContentSchemaText( WikiPage::factory( $title )->getContent(), $schemaId );
	}

	public function getDescription() {
		return $this->msg( 'special-schematext' )->text();
	}

	protected function getGroupName() {
		return 'wikibase';
	}

	private function sendContentSchemaText( WikibaseSchemaContent $schemaContent, SchemaId $id ) {
		$converter = new SchemaConverter();
		$schemaText = $converter->getSchemaText( $schemaContent->getText() );
		$out = $this->getOutput();
		$out->disable();
		$webResponse = $out->getRequest()->response();
		$webResponse->header( 'Content-Type: text/shex; charset=UTF-8' );
		$webResponse->header( 'Content-Disposition:  attachment; filename="' . $id->getId() . '.shex"' );
		// The data here is always public, so allow anyone to access it (similar to Special:EntityData)
		$webResponse->header( 'Access-Control-Allow-Origin: *' );

		ob_clean(); // remove anything that might already be in the output buffer.
		echo $schemaText;
	}

	/**
	 * @param string $subPage
	 *
	 * @return false|SchemaId
	 */
	private function getIdFromSubpage( $subPage ) {
		if ( !$subPage ) {
			return false;
		}
		try {
			$schemaId = new SchemaId( $subPage );
		} catch ( InvalidArgumentException $e ) {
			return false;
		}
		return $schemaId;
	}

}
