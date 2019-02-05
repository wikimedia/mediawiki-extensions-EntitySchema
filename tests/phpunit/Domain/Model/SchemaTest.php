<?php

namespace Wikibase\Schema\Tests\Domain\Model;

use MediaWikiTestCase;
use Wikibase\Schema\Domain\Model\Schema;
use Wikibase\Schema\Domain\Model\SchemaId;

/**
 * Class SchemaTest
 *
 * @covers \Wikibase\Schema\Domain\Model\Schema
 *
 * @license GPL-2.0-or-later
 */
class SchemaTest extends MediaWikiTestCase {

	public function testEmptySchemaHasDefaults() {
		$schema = new Schema();

		$this->assertSame( '', $schema->getLabel( 'en' )->getText() );
		$this->assertSame( '', $schema->getDescription( 'en' )->getText() );
		$this->assertSame( [], $schema->getAliasGroup( 'en' )->getAliases() );
		$this->assertSame( '', $schema->getSchema() );
		$this->assertNull( $schema->getId() );
	}

	public function testSettingAndRetrieving() {
		$testShEx = <<<'SCHEMA'
PREFIX wdt: <http://www.wikidata.org/prop/direct/>
PREFIX wd: <http://www.wikidata.org/entity/>

:human {
  wdt:P31 [wd:Q5]
}
SCHEMA;
		$testId = new SchemaId( 'O100' );

		$schema = new Schema();

		$schema->setLabel( 'en', 'testlabel' );
		$schema->setDescription( 'en', 'testDescription' );
		$schema->setAliasGroup( 'en', [ 'testlabel', 'foobar' ] );
		$schema->setSchema( $testShEx );
		$schema->setId( $testId );

		$this->assertSame( 'testlabel', $schema->getLabel( 'en' )->getText() );
		$this->assertSame( 'testDescription', $schema->getDescription( 'en' )->getText() );
		$this->assertSame( [ 'testlabel', 'foobar' ], $schema->getAliasGroup( 'en' )->getAliases() );
		$this->assertSame( $testShEx, $schema->getSchema() );
		$this->assertSame( $testId, $schema->getId() );
	}

}
