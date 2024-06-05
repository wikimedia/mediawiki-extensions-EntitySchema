<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\DataValues;

use DataValues\IllegalValueException;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use Generator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \EntitySchema\Wikibase\DataValues\EntitySchemaValue
 * @license GPL-2.0-or-later
 */
class EntitySchemaValueTest extends TestCase {

	public function testNewFromArrayWithValidValue(): void {
		$this->assertEquals(
			new EntitySchemaValue( new EntitySchemaId( 'E123' ) ),
			EntitySchemaValue::newFromArray( [ 'id' => 'E123' ] )
		);
	}

	/**
	 * @dataProvider invalidValueProvider
	 */
	public function testNewFromArrayWithInvalidValue( /* mixed */ $value ): void {
		$this->expectException( IllegalValueException::class );
		EntitySchemaValue::newFromArray( $value );
	}

	public static function invalidValueProvider(): Generator {
		yield 'not an array' => [ 'E123' ];
		yield 'missing "id" key' => [ [ 'schema' => 'E123' ] ];
		yield '"id" value not a string' => [ [ 'id' => 123 ] ];
		yield '"id" value not a schema ID' => [ [ 'id' => 'X123' ] ];
	}

}
