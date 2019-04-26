<?php

namespace EntitySchema\Tests\DataAccess;

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

	public function testGetNewId() {
		$generator = new SqlIdGenerator(
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			'wbschema_id_counter'
		);

		$id1 = $generator->getNewId();
		$this->assertInternalType( 'int', $id1 );
		$id2 = $generator->getNewId();
		$this->assertSame( $id1 + 1, $id2 );
		$id3 = $generator->getNewId();
		$this->assertSame( $id2 + 1, $id3 );
	}

	/**
	 * @expectedException RuntimeException
	 * @expectedExceptionMessage read-only for test
	 */
	public function testExceptionReadOnlyDB() {
		$database = $this->createMock( IDatabase::class );
		$database->method( 'insert' )
			->willThrowException( new DBReadOnlyError( $database, 'read-only for test' ) );
		$loadBalancer = $this->createMock( ILoadBalancer::class );
		$loadBalancer->method( 'getConnection' )
			->willReturn( $database );

		$generator = new SqlIdGenerator(
			$loadBalancer,
			'wbschema_id_counter'
		);

		$generator->getNewId();
	}

}
