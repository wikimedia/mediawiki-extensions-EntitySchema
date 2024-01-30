<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\DataValues;

use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use EntitySchema\Wikibase\DataValues\EntitySchemaValueParser;
use PHPUnit\Framework\TestCase;
use ValueParsers\ParseException;

/**
 * @covers \EntitySchema\Wikibase\DataValues\EntitySchemaValueParser
 * @license GPL-2.0-or-later
 */
class EntitySchemaValueParserTest extends TestCase {

	public function testParseStringFails() {
		$this->expectException( ParseException::class );
		( new EntitySchemaValueParser() )->parse( 'test' );
	}

	public function testParseInvalidArrayFails() {
		$this->expectException( ParseException::class );
		( new EntitySchemaValueParser() )->parse( [
			'test' => 'undefined',
		] );
	}

	public function testParseUnexpectedValueStructureFails() {
		$this->expectException( ParseException::class );
		( new EntitySchemaValueParser() )->parse( [
			'value' => [ 'nothing' => 'test' ],
		] );
	}

	public function testParseInvalidIDType() {
		$this->expectException( ParseException::class );
		( new EntitySchemaValueParser() )->parse( [
			'value' => [ 'id' => [ 123 ] ],
		] );
	}

	public function testParseValidIdType() {
		$result = ( new EntitySchemaValueParser() )->parse( [
			'value' => [ 'id' => 'E12' ],
		] );
		$this->assertInstanceOf( EntitySchemaValue::class, $result );
		$this->assertSame( 'E12', $result->getValue()->getSchemaId() );
	}

	public function testInvalidValueType() {
		$this->expectException( ParseException::class );
		( new EntitySchemaValueParser() )->parse( [
			'value' => new EntitySchemaId( 'E12' ),
		] );
	}

	public function testParseValidValueType() {
		$result = ( new EntitySchemaValueParser() )->parse( [
			'value' => 'E12',
		] );
		$this->assertInstanceOf( EntitySchemaValue::class, $result );
		$this->assertSame( 'E12', $result->getValue()->getSchemaId() );
	}
}
