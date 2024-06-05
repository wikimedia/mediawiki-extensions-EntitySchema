<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\DataValues;

use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use EntitySchema\Wikibase\DataValues\EntitySchemaValueParser;
use PHPUnit\Framework\TestCase;
use ValueParsers\ParseException;

/**
 * @covers \EntitySchema\Wikibase\DataValues\EntitySchemaValueParser
 * @license GPL-2.0-or-later
 */
class EntitySchemaValueParserTest extends TestCase {

	public function testParseInvalidString() {
		$this->expectException( ParseException::class );
		( new EntitySchemaValueParser() )->parse( 'test' );
	}

	public function testParseStringSucceeds() {
		$result = ( new EntitySchemaValueParser() )->parse( 'E12' );
		$this->assertInstanceOf( EntitySchemaValue::class, $result );
		$this->assertSame( 'E12', $result->getValue()->getSchemaId() );
	}

	public function testParseInvalidType() {
		$this->expectException( ParseException::class );
		( new EntitySchemaValueParser() )
			->parse( [ 'id' => 'E123' ] );
	}

}
