<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseRepoControllersHookHandler {
	private bool $entitySchemaIsRepo;
	private ?EntitySchemaSearchHelperFactory $factory;

	public function __construct(
		bool $entitySchemaIsRepo,
		?EntitySchemaSearchHelperFactory $factory
	) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
		$this->factory = $factory;
		if ( $entitySchemaIsRepo ) {
			Assert::parameterType(
				EntitySchemaSearchHelperFactory::class,
				$factory,
				'$factory'
			);
		}
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
