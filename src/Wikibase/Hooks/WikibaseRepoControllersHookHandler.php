<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseRepoControllersHookHandler {
	private bool $entitySchemaIsRepo;

	public function __construct(
		bool $entitySchemaIsRepo,
	) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
	}

	public function onWikibaseRepoControllers( array &$controllersDefinitions ): void {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}

		$controllersDefinitions = array_merge(
			$controllersDefinitions,
			require __DIR__ . '/../../../WikibaseEntitySchema.controllers.php'
		);
	}
}
