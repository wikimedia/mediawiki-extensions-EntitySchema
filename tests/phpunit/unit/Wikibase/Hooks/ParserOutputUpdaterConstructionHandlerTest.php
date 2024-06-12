<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\Wikibase\Hooks\ParserOutputUpdaterConstructionHandler;
use EntitySchema\Wikibase\ParserOutputUpdater\EntitySchemaStatementDataUpdater;
use MediaWikiUnitTestCase;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Repo\ParserOutput\CompositeStatementDataUpdater;

/**
 * @covers \EntitySchema\Wikibase\Hooks\ParserOutputUpdaterConstructionHandler
 * @license GPL-2.0-or-later
 */
class ParserOutputUpdaterConstructionHandlerTest extends MediaWikiUnitTestCase {

	public function testOnWikibaseRepoOnParserOutputUpdaterConstruction() {
		$handler = new ParserOutputUpdaterConstructionHandler(
			$this->createMock( PropertyDataTypeLookup::class )
		);

		$statementUpdater = $this->createMock( CompositeStatementDataUpdater::class );
		$statementUpdater->expects( $this->once() )
			->method( 'addUpdater' )
			->with( $this->isInstanceOf( EntitySchemaStatementDataUpdater::class ) );

		$entityUpdaters = [];
		$handler->onWikibaseRepoOnParserOutputUpdaterConstruction(
			$statementUpdater,
			$entityUpdaters
		);
		$this->assertSame( [], $entityUpdaters );
	}
}
