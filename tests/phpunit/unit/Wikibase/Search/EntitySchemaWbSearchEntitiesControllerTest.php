<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Search;

use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use EntitySchema\Wikibase\Search\EntitySchemaSearchHelperFactory;
use EntitySchema\Wikibase\Search\EntitySchemaWbSearchEntitiesController;
use PHPUnit\Framework\TestCase;
use Wikibase\DataModel\Term\Term;
use Wikibase\Lib\Interactors\TermSearchResult;
use Wikibase\Repo\Api\EntitySearchHelper;
use Wikibase\Repo\Domains\Search\Domain\Model\User;
use Wikibase\Repo\Domains\Search\Infrastructure\Controllers\WbSearchEntitiesRequest;

/**
 * @covers \EntitySchema\Wikibase\Search\EntitySchemaWbSearchEntitiesController
 * @license GPL-2.0-or-later
 */
class EntitySchemaWbSearchEntitiesControllerTest extends TestCase {

	public function testOverFetchesAndReturnsRequestedPageWithHasMore(): void {
		// offset 0 + limit 5 + 1 = 6 requested; the 6th row signals "more".
		$overFetched = array_map( fn ( int $i ) => $this->newTermSearchResult( "E$i" ), range( 1, 6 ) );

		$response = $this->newController( 6, $overFetched )
			->search( $this->newRequest( 5, 0 ) );

		$this->assertSame( array_slice( $overFetched, 0, 5 ), $response->results );
		$this->assertTrue( $response->hasMore );
	}

	public function testAppliesOffset(): void {
		// offset 2 + limit 5 + 1 = 8 requested; page is rows [2..6], row 7 signals "more".
		$overFetched = array_map( fn ( int $i ) => $this->newTermSearchResult( "E$i" ), range( 1, 8 ) );

		$response = $this->newController( 8, $overFetched )
			->search( $this->newRequest( 5, 2 ) );

		$this->assertSame( array_slice( $overFetched, 2, 5 ), $response->results );
		$this->assertTrue( $response->hasMore );
	}

	public function testHasMoreIsFalseWhenNoExtraResults(): void {
		// Fewer than the over-fetch limit come back, so there is no further page.
		$results = array_map( fn ( int $i ) => $this->newTermSearchResult( "E$i" ), range( 1, 5 ) );

		$response = $this->newController( 6, $results )
			->search( $this->newRequest( 5, 0 ) );

		$this->assertSame( $results, $response->results );
		$this->assertFalse( $response->hasMore );
	}

	private function newController( int $expectedLimit, array $results ): EntitySchemaWbSearchEntitiesController {
		$searchHelper = $this->createMock( EntitySearchHelper::class );
		$searchHelper->expects( $this->once() )
			->method( 'getRankedSearchResults' )
			->with( 'query', 'en', EntitySchemaValue::TYPE, $expectedLimit, false, null )
			->willReturn( $results );

		$factory = $this->createMock( EntitySchemaSearchHelperFactory::class );
		$factory->method( 'newForLanguage' )->with( 'en' )->willReturn( $searchHelper );

		return new EntitySchemaWbSearchEntitiesController( $factory );
	}

	private function newRequest( int $limit, int $offset ): WbSearchEntitiesRequest {
		return new WbSearchEntitiesRequest(
			'query', 'en', 'en', $limit, false, null, User::newAnonymous(), $offset
		);
	}

	private function newTermSearchResult( string $id ): TermSearchResult {
		return new TermSearchResult( new Term( 'en', $id ), 'label', null, null, null, [ 'id' => $id ] );
	}

}
