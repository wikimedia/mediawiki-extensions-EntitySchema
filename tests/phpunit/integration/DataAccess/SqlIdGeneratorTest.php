<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\DataAccess;

use EntitySchema\DataAccess\SqlIdGenerator;
use MediaWikiIntegrationTestCase;
use RuntimeException;
use Wikimedia\Rdbms\DBReadOnlyError;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\InsertQueryBuilder;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * @covers \EntitySchema\DataAccess\SqlIdGenerator
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class SqlIdGeneratorTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'WikibaseRepository' );
	}

	public function testGetNewId() {
		$generator = new SqlIdGenerator(
			$this->getServiceContainer()->getDBLoadBalancer(),
			'entityschema_id_counter'
		);

		$id1 = $generator->getNewId();
		$this->assertIsInt( $id1 );
		$id2 = $generator->getNewId();
		$this->assertSame( $id1 + 1, $id2 );
		$id3 = $generator->getNewId();
		$this->assertSame( $id2 + 1, $id3 );
	}

	public function testIdsSkipped() {
		$loadbalancer = $this->getServiceContainer()->getDBLoadBalancer();
		$db = $loadbalancer->getConnection( DB_PRIMARY );
		$currentId = $db->newSelectQueryBuilder()->select( [ 'id_value' ] )
			->from( 'entityschema_id_counter' )
			->caller( __METHOD__ )
			->fetchRow();

		$currentId = $currentId->id_value ?? 0;

		$testGenerator = new SqlIdGenerator(
			$this->getServiceContainer()->getDBLoadBalancer(),
			'entityschema_id_counter',
			[ $currentId + 1, $currentId + 2 ]
		);
		$actualId = $testGenerator->getNewId();

		$this->assertSame( $currentId + 3, $actualId, 'SqlIdGenerator should skipped provided IDs' );
	}

	public function testExceptionReadOnlyDB() {
		$database = $this->createMock( IDatabase::class );
		$database->method( 'insert' )
			->willThrowException( new DBReadOnlyError( $database, 'read-only for test' ) );
		$database->method( 'newSelectQueryBuilder' )
			->willReturn(
				new SelectQueryBuilder( $database )
			);
		$database->method( 'newInsertQueryBuilder' )
			->willReturn(
				new InsertQueryBuilder( $database )
			);
		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $database );
		$generator = new SqlIdGenerator(
			$loadBalancer,
			'entityschema_id_counter'
		);

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'read-only for test' );
		$generator->getNewId();
	}

}
