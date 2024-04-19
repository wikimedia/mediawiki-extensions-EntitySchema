<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use EntitySchema\Wikibase\Search\EntitySchemaSearchHelper;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use MediaWiki\Request\WebRequest;
use RequestContext;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseRepoEntitySearchHelperCallbacksHandler {

	private EntitySchemaSearchHelperFactory $factory;

	public function __construct(
		EntitySchemaSearchHelperFactory $factory
	) {
		$this->factory = $factory;
	}

	public function onWikibaseRepoEntitySearchHelperCallbacks( array &$callbacks ): void {
		$callbacks[EntitySchemaSearchHelper::ENTITY_TYPE] = function ( WebRequest $request ) {
			// TODO would be nice if Wikibase injected the context for us
			// ($request is unfortunately not very useful)
			return $this->factory->newForContext( RequestContext::getMain() );
		};
	}

}
