<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Specials;

use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\SchemaConverter\EntitySchemaConverter;
use HttpError;
use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use SpecialPage;

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

	public function execute( $subPage ): void {
		parent::execute( $subPage );
		$entitySchemaId = $this->getIdFromSubpage( $subPage );
		if ( !$entitySchemaId ) {
			$this->getOutput()->addWikiMsg( 'entityschema-schematext-text' );
			$this->getOutput()->returnToMain();
			return;
		}
		$title = Title::makeTitle( NS_ENTITYSCHEMA_JSON, $entitySchemaId->getId() );

		if ( !$title->exists() ) {
			throw new HttpError( 404, $this->getOutput()->msg(
				'entityschema-schematext-missing', $subPage
			) );
		}

		$this->sendContentSchemaText(
			// @phan-suppress-next-line PhanTypeMismatchArgumentSuperType
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title )->getContent(),
			$entitySchemaId
		);
	}

	public function getDescription(): string {
		return $this->msg( 'special-schematext' )->text();
	}

	protected function getGroupName(): string {
		return 'wikibase';
	}

	private function sendContentSchemaText( EntitySchemaContent $schemaContent, EntitySchemaId $id ): void {
		$converter = new EntitySchemaConverter();
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

	private function getIdFromSubpage( ?string $subPage ): ?EntitySchemaId {
		if ( !$subPage ) {
			return null;
		}
		try {
			$entitySchemaId = new EntitySchemaId( $subPage );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
		return $entitySchemaId;
	}

}
