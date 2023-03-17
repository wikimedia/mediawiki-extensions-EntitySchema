<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Domain\Model;

use EntitySchema\Domain\Model\SchemaId;
use InvalidArgumentException;
use MediaWikiUnitTestCase;

/**
 * @covers EntitySchema\Domain\Model\SchemaId
 *
 * @license GPL-2.0-or-later
 */
class SchemaIdTest extends MediaWikiUnitTestCase {

	public function testConstructorAndGetter(): void {
		$expected = 'E1';

		$schemaId = new SchemaId( $expected );
		$actual = $schemaId->getId();

		$this->assertSame( $expected, $actual );
	}

	public function provideInvalidIds(): iterable {
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
	 */
	public function testConstructorRejectsInvalidId( string $id ): void {
		$this->expectException( InvalidArgumentException::class );
		new SchemaId( $id );
	}

}
