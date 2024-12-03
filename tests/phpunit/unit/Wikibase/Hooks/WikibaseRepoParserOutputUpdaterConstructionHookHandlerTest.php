<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\Wikibase\Hooks\WikibaseRepoOnParserOutputUpdaterConstructionHookHandler;
use EntitySchema\Wikibase\ParserOutputUpdater\EntitySchemaStatementDataUpdater;
use MediaWikiUnitTestCase;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Repo\ParserOutput\CompositeStatementDataUpdater;

/**
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseRepoOnParserOutputUpdaterConstructionHookHandler
 * @license GPL-2.0-or-later
 */
class WikibaseRepoParserOutputUpdaterConstructionHookHandlerTest extends MediaWikiUnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !class_exists( CompositeStatementDataUpdater::class ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	public function testOnWikibaseRepoOnParserOutputUpdaterConstruction() {
		$handler = new WikibaseRepoOnParserOutputUpdaterConstructionHookHandler(
			true,
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
