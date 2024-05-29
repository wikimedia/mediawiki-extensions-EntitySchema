<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Content\EntitySchemaContentHandler;
use MediaWiki\Content\Hook\ContentHandlerForModelIDHook;
use MediaWiki\Content\IContentHandlerFactory;

/**
 * @license GPL-2.0-or-later
 */
class ContentHandlerForModelIDHookHandler implements ContentHandlerForModelIDHook {

	private IContentHandlerFactory $contentHandlerFactory;
	private bool $entitySchemaIsRepo;

	public function __construct(
		IContentHandlerFactory $contentHandlerFactory,
		bool $entitySchemaIsRepo
	) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
		$this->contentHandlerFactory = $contentHandlerFactory;
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
		$handler = new EntitySchemaContentHandler( $modelName, $this->contentHandlerFactory );
	}

}
