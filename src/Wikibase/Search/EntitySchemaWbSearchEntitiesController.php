<?php declare( strict_types=1 );

namespace EntitySchema\Wikibase\Search;

use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\WbSearchEntitiesController;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\WbSearchEntitiesRequest;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaWbSearchEntitiesController implements WbSearchEntitiesController {

	public function __construct(
		private readonly EntitySchemaSearchHelperFactory $searchHelperFactory
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function search( WbSearchEntitiesRequest $request ): array {
		return $this->searchHelperFactory->newForLanguage( $request->resultLanguage )->getRankedSearchResults(
			$request->text,
			$request->searchLanguageCode,
			EntitySchemaValue::TYPE,
			$request->limit,
			$request->strictLanguage,
			$request->profileContext
		);
	}
}
