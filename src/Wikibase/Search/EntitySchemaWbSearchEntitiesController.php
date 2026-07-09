<?php declare( strict_types=1 );

namespace EntitySchema\Wikibase\Search;

use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\WbSearchEntitiesController;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\WbSearchEntitiesRequest;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\WbSearchEntitiesResponse;

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
	public function search( WbSearchEntitiesRequest $request ): WbSearchEntitiesResponse {
		$results = $this->searchHelperFactory->newForLanguage( $request->resultLanguage )->getRankedSearchResults(
			$request->text,
			$request->searchLanguageCode,
			EntitySchemaValue::TYPE,
			$request->offset + $request->limit + 1,
			$request->strictLanguage,
			$request->profileContext
		);

		return new WbSearchEntitiesResponse(
			array_slice( $results, $request->offset, $request->limit ),
			count( $results ) > $request->offset + $request->limit
		);
	}
}
