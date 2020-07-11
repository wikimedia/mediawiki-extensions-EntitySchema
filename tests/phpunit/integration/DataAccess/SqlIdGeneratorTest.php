<?php

namespace EntitySchema\Tests\Integration\DataAccess;

use DBReadOnlyError;
use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use RuntimeException;
use EntitySchema\DataAccess\SqlIdGenerator;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @covers \EntitySchema\DataAccess\SqlIdGenerator
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class SqlIdGeneratorTest extends MediaWikiTestCase {

	protected function setUp() : void {
		parent::setUp();
		$this->tablesUsed[] = 'entityschema_id_counter';
	}

	public function testGetNewId() {
		$generator = new SqlIdGenerator(
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
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
		$loadbalancer = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$db = $loadbalancer->getConnection( DB_MASTER );
		$currentId = $db->selectRow(
			'entityschema_id_counter',
			'id_value',
			[],
			__METHOD__
		);

		$currentId = $currentId->id_value ?? 0;

		$testGenerator = new SqlIdGenerator(
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
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
