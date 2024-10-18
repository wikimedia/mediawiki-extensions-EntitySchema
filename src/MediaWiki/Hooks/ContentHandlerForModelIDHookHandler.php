<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;
use MediaWiki\Content\Hook\ContentHandlerForModelIDHook;

/**
 * @license GPL-2.0-or-later
 */
class ContentHandlerForModelIDHookHandler implements ContentHandlerForModelIDHook {

	private bool $entitySchemaIsRepo;

	public function __construct(
		bool $entitySchemaIsRepo
	) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
	}

	/**
	 * @inheritDoc
	 */
	public function onContentHandlerForModelID( $modelName, &$handler ): void {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}
		if ( $modelName !== 'EntitySchema' ) {
			return;
		}
		$handler = new EntitySchemaContentHandler( $modelName );
	}

}
