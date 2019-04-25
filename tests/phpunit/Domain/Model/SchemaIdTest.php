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
		$expected = 'E1';

		$schemaId = new SchemaId( $expected );
		$actual = $schemaId->getId();

		$this->assertSame( $expected, $actual );
	}

	public function  provideInvalidIds() {
		yield 'missing prefix' => [ '1' ];
		yield 'missing number' => [ 'E' ];
		yield 'malformed number' => [ 'E01' ];
		yield 'trailing newline' => [ "E1\n" ];
		yield 'extra whitespace' => [ ' E1 ' ];
		yield 'sub-ID' => [ 'E1-R1' ];
		yield 'local repository' => [ ':E1' ]; // this is not a Wikibase entity (ID),
		yield 'foreign repository' => [ 'other:E1' ]; // federation is not supported
	}

	/**
	 * @dataProvider provideInvalidIds
	 * @expectedException InvalidArgumentException
	 */
	public function testConstructorRejectsInvalidId( $id ) {
		new SchemaId( $id );
	}

}
