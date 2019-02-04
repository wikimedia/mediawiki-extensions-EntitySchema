<?php

namespace Wikibase\Schema\Tests\Domain\Model;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Wikibase\Schema\Domain\Model\SchemaId;

/**
 * @covers Wikibase\Schema\Domain\Model\SchemaId
 *
 * @license GPL-2.0-or-later
 */
class SchemaIdTest extends TestCase {

	public function testConstructorAndGetter() {
		$expected = 'O1';

		$schemaId = new SchemaId( $expected );
		$actual = $schemaId->getId();

		$this->assertSame( $expected, $actual );
	}

	public function  provideInvalidIds() {
		yield 'missing prefix' => [ '1' ];
		yield 'missing number' => [ 'O' ];
		yield 'malformed number' => [ 'O01' ];
		yield 'extra whitespace' => [ ' O1 ' ];
		yield 'sub-ID' => [ 'O1-R1' ];
		yield 'local repository' => [ ':O1' ]; // this is not a Wikibase entity (ID),
		yield 'foreign repository' => [ 'other:O1' ]; // federation is not supported
	}

	/**
	 * @dataProvider provideInvalidIds
	 * @expectedException InvalidArgumentException
	 */
	public function testConstructorRejectsInvalidId( $id ) {
		new SchemaId( $id );
	}

}
