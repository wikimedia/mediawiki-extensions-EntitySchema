<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use MediaWiki\Content\Hook\ContentModelCanBeUsedOnHook;

/**
 * @license GPL-2.0-or-later
 */
class ContentModelCanBeUsedOnHookHandler implements ContentModelCanBeUsedOnHook {

	private bool $entitySchemaIsRepo;

	public function __construct( bool $entitySchemaIsRepo ) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
	}

	/**
	 * @inheritDoc
	 */
	public function onContentModelCanBeUsedOn( $contentModel, $title, &$ok ): ?bool {
		if ( !$this->entitySchemaIsRepo ) {
			return null;
		}

		if (
			$title->getNamespace() === NS_ENTITYSCHEMA_JSON &&
			$contentModel !== EntitySchemaContent::CONTENT_MODEL_ID
		) {
			$ok = false;
			// skip other hooks
			return false;
		}
		// the other direction is guarded by EntitySchemaContentHandler::canBeUsedOn()
		return null;
	}
}
