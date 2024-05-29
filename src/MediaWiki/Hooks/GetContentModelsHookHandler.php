<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use MediaWiki\Content\Hook\GetContentModelsHook;

/**
 * @license GPL-2.0-or-later
 */
class GetContentModelsHookHandler implements GetContentModelsHook {

	private bool $entitySchemaIsRepo;

	public function __construct( bool $entitySchemaIsRepo ) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
	}

	/**
	 * @inheritDoc
	 */
	public function onGetContentModels( &$models ) {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}
		$models[] = 'EntitySchema';
	}

}
