<?php

namespace Wikibase\Repo\Tests\Store\Sql;

use MediaWiki\MediaWikiServices;
use MediaWikiTestCase;
use Wikibase\Schema\SqlIdGenerator;

/**
 * @covers \Wikibase\Schema\SqlIdGenerator
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

}
