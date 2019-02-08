<?php

namespace Wikibase\Schema\Tests\DataAccess;

use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use RuntimeException;
use Wikibase\Schema\DataAccess\SqlIdGenerator;
use Wikimedia\Rdbms\LoadBalancerSingle;

/**
 * @covers \Wikibase\Schema\DataAccess\SqlIdGenerator
 *
 * @group Database
 *
 * @license GPL-2.0-or-later
 */
class SqlIdGeneratorTest extends MediaWikiTestCase {

	public function tearDown() {
		$this->db->setLBInfo( 'readOnlyMode', false );
		parent::tearDown();
	}

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
		$this->markTestSkipped( 'requires I481553fac4' );

		$generator = new SqlIdGenerator(
			new LoadBalancerSingle( [
				'connection' => $this->db,
				'readOnlyReason' => 'read-only for test',
			] ),
			'wbschema_id_counter'
		);

		$generator->getNewId();
	}

}
