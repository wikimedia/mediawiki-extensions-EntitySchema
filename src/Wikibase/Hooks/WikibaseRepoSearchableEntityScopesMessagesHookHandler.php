<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseRepoSearchableEntityScopesMessagesHookHandler {

	public const ENTITY_SCHEMA_SCOPE_MESSAGE = 'wikibase-scoped-search-entity-schema-scope-name';

	public function onWikibaseRepoSearchableEntityScopesMessages( array &$messages ): void {
		if ( !array_key_exists( EntitySchemaSearchHelperFactory::ENTITY_TYPE, $messages ) ) {
			$messages[EntitySchemaSearchHelperFactory::ENTITY_TYPE] = self::ENTITY_SCHEMA_SCOPE_MESSAGE;
		}
	}

}
