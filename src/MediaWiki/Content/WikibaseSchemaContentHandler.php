<?php

namespace Wikibase\Schema\MediaWiki\Content;

use JsonContentHandler;
use Wikibase\Schema\MediaWiki\SchemaEditAction;
use Wikibase\Schema\MediaWiki\SchemaSubmitAction;

/**
 * Content handler for the Wikibase Schema content
 */
class WikibaseSchemaContentHandler extends JsonContentHandler {

	public function __construct( $modelId = WikibaseSchemaContent::CONTENT_MODEL_ID ) {
		parent::__construct( $modelId );
	}

	protected function getContentClass() {
		return WikibaseSchemaContent::class;
	}

	public function getActionOverrides() {
		return [
			'edit' => SchemaEditAction::class,
			'submit' => SchemaSubmitAction::class,
		];
	}

	public function supportsDirectApiEditing() {
		return false;
	}

}
