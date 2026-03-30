<?php declare( strict_types=1 );

use EntitySchema\MediaWiki\EntitySchemaServices;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use EntitySchema\Wikibase\Search\EntitySchemaWbSearchEntitiesController;
use Wikibase\Repo\ControllerRegistry;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\WbSearchEntitiesController;

/**
 * Controller callback definitions for EntitySchema.
 *
 * @note Avoid instantiating objects here! Use callbacks (closures) instead.
 *
 * @license GPL-2.0-or-later
 */
return [
	EntitySchemaValue::TYPE => [
		ControllerRegistry::WB_SEARCH_ENTITIES_CONTROLLER => static function (): WbSearchEntitiesController {
			return new EntitySchemaWbSearchEntitiesController(
				EntitySchemaServices::getEntitySchemaSearchHelperFactory(),
			);
		},
	],
];
