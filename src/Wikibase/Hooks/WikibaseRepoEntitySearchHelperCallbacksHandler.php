<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use EntitySchema\Wikibase\Search\EntitySchemaSearchHelper;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\WebRequest;
use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseRepoEntitySearchHelperCallbacksHandler {

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

	/**
	 * @inheritDoc
	 */
	public function onWikibaseRepoEntitySearchHelperCallbacks( array &$callbacks ): void {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}
		$callbacks[EntitySchemaSearchHelper::ENTITY_TYPE] = function ( WebRequest $request ) {
			// TODO would be nice if Wikibase injected the context for us
			// ($request is unfortunately not very useful)
			return $this->factory->newForContext( RequestContext::getMain() );
		};
	}

}
