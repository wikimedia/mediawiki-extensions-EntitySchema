<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseRepoSearchableEntityScopesHookHandler {

	public function onWikibaseRepoSearchableEntityScopes( array &$searchableEntityScopes ): void {
		if ( !array_key_exists( EntitySchemaSearchHelperFactory::ENTITY_TYPE, $searchableEntityScopes ) ) {
			$searchableEntityScopes[EntitySchemaSearchHelperFactory::ENTITY_TYPE] = NS_ENTITYSCHEMA_JSON;
		}
	}

}
