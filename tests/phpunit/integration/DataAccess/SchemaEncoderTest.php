<?php

namespace EntitySchema\Tests\Integration\DataAccess;

use InvalidArgumentException;
use MediaWikiTestCase;
use EntitySchema\DataAccess\SchemaEncoder;
use EntitySchema\Domain\Model\SchemaId;

/**
 * @covers \EntitySchema\DataAccess\SchemaEncoder
 *
 * @license GPL-2.0-or-later
 */
class SchemaEncoderTest extends MediaWikiTestCase {

	/**
	 * @dataProvider provideValidArguments
	 */
	public function testGetPersistentRepresentation_valid(
		$id,
		array $labels,
		array $descriptions,
		array $aliasGroups,
		$schemaText,
		array $expected
	) {
		$actual = SchemaEncoder::getPersistentRepresentation(
			new SchemaId( $id ),
			$labels,
			$descriptions,
			$aliasGroups,
			$schemaText
		);

		$this->assertSame( $expected, json_decode( $actual, true ) );
	}

	public function provideValidArguments() {
		$id = 'E1';
		$language = 'en';
		$label = 'englishLabel';
		$description = 'englishDescription';
		$aliases = [ 'englishAlias' ];
		$schemaText = '#some schema about goats';

		yield [
			$id,
			[ $language => $label ],
			[ $language => $description ],
			[ $language => $aliases ],
			$schemaText,
			[
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => [
					$language => $label,
				],
				'descriptions' => [
					$language => $description,
				],
				'aliases' => [
					$language => $aliases,
				],
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			],
		];

		$duplicateAliases = [ 'foo', 'bar', 'foo', 'baz', 'qux', 'bar', 'foo' ];
		$distinctAliases = [ 'foo', 'bar', 'baz', 'qux' ];

		yield [
			$id,
			[ $language => $label ],
			[ $language => $description ],
			[ $language => $duplicateAliases ],
			$schemaText,
			[
				'id' => $id,
				'serializationVersion' => '3.0',
				'labels' => [
					$language => $label,
				],
				'descriptions' => [
					$language => $description,
				],
				'aliases' => [
					$language => $distinctAliases,
				],
				'schemaText' => $schemaText,
				'type' => 'ShExC',
			],
		];
	}

	/**
	 * @dataProvider provideInvalidArguments
	 */
	public function testGetPersistentRepresentation_invalid(
		array $labels,
		array $descriptions,
		array $aliasGroups,
		$schemaText,
		$expectedMessage
	) {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( $expectedMessage );

		SchemaEncoder::getPersistentRepresentation(
			new SchemaId( 'E1' ),
			$labels,
			$descriptions,
			$aliasGroups,
			$schemaText
		);
	}

	public function provideInvalidArguments() {
		$validLabels = [ 'en' => 'valid label' ];
		$validDescriptions = [ 'en' => 'valid description' ];
		$validAliasGroups = [ 'en' => [ 'valid alias', 'another valid alias' ] ];
		$validSchemaText = '# valid schema text';

		yield 'invalid language code (labels)' => [
			[ 'invalid' => 'invalid label' ],
			$validDescriptions,
			$validAliasGroups,
			$validSchemaText,
			'language codes must be valid',
		];

		yield 'invalid language code (descriptions)' => [
			$validLabels,
			[ 'invalid' => 'invalid description' ],
			$validAliasGroups,
			$validSchemaText,
			'language codes must be valid',
		];

		yield 'invalid language code (aliases)' => [
			$validLabels,
			$validDescriptions,
			[ 'invalid' => [ 'invalid alias' ] ],
			$validSchemaText,
			'language codes must be valid',
		];

		yield 'invalid type (labels)' => [
			[ 'en' => 1 ],
			$validDescriptions,
			$validAliasGroups,
			$validSchemaText,
			'language, label, description and schemaText must be strings',
		];

		yield 'invalid type (descriptions)' => [
			$validLabels,
			[ 'en' => 1 ],
			$validAliasGroups,
			$validSchemaText,
			'language, label, description and schemaText must be strings',
		];

		yield 'invalid type (aliases)' => [
			$validLabels,
			$validDescriptions,
			[ 'en' => 'invalid alias' ],
			$validSchemaText,
			'aliases must be an array of strings',
		];

		yield 'invalid type (aliases)' => [
			$validLabels,
			$validDescriptions,
			[ 'en' => [ 1 ] ],
			$validSchemaText,
			'aliases must be an array of strings',
		];

		yield 'invalid type (aliases)' => [
			$validLabels,
			$validDescriptions,
			[ 'en' => [ 'invalid' => 'alias' ] ],
			$validSchemaText,
			'aliases must be an array of strings',
		];

		yield 'invalid type (text)' => [
			$validLabels,
			$validDescriptions,
			$validAliasGroups,
			1,
			'language, label, description and schemaText must be strings',
		];
	}

	public function testSchemaTooLongException() {
		$this->setMwGlobals( 'wgEntitySchemaSchemaTextMaxSizeBytes', 5 );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'is longer than' );

		SchemaEncoder::getPersistentRepresentation(
			new SchemaId( 'E1' ),
			[],
			[],
			[],
			'123456'
		);
	}

	public function testLabelTooLongException() {
		$this->setMwGlobals( 'wgEntitySchemaNameBadgeMaxSizeChars', 5 );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'is longer than' );

		SchemaEncoder::getPersistentRepresentation(
			new SchemaId( 'E1' ),
			[ 'en' => 'label too long' ],
			[],
			[],
			''
		);
	}

	public function testDescriptionTooLongException() {
		$this->setMwGlobals( 'wgEntitySchemaNameBadgeMaxSizeChars', 5 );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'is longer than' );

		SchemaEncoder::getPersistentRepresentation(
			new SchemaId( 'E1' ),
			[],
			[ 'en' => 'description too long' ],
			[],
			''
		);
	}

	public function testAliasesTooLongException() {
		$this->setMwGlobals( 'wgEntitySchemaNameBadgeMaxSizeChars', 5 );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'is longer than' );

		SchemaEncoder::getPersistentRepresentation(
			new SchemaId( 'E1' ),
			[],
			[],
			[ 'en' => [ 'alias', 'too', 'long' ] ],
			''
		);
	}

	public function testParamsAreCleaned() {
		$actualJSON = SchemaEncoder::getPersistentRepresentation(
			new SchemaId( 'E1' ),
			[
				'en' => '         	testLabel﻿   ',
				'de' => '    ',
				'pt' => '',
			],
			[ 'en' => "  \v\t  testDescription﻿ \r\n  " ],
			[
				'en' => [ '  test ​ ', '   aliases  ', '   ', ' 0 ' ],
				'de' => [ '   ' ],
				'pt' => [ '' ],
				'la' => [],
			],
			'  a b ﻿  '
		);

		$this->assertJson( $actualJSON );
		$actualRepresentation = json_decode( $actualJSON, true );

		$this->assertSame( [ 'en' => 'testLabel' ], $actualRepresentation['labels'] );
		$this->assertSame( [ 'en' => 'testDescription' ], $actualRepresentation['descriptions'] );
		$this->assertSame( [ 'en' => [ 'test', 'aliases', '0' ] ], $actualRepresentation['aliases'] );
		$this->assertSame( 'a b', $actualRepresentation['schemaText'] );
	}

	public function testIgnoresKeyOrder() {
		$json1 = SchemaEncoder::getPersistentRepresentation(
			new SchemaId( 'E1' ),
			[
				'en' => 'English label',
				'de' => 'deutsche Beschriftung',
			],
			[
				'en' => 'English description',
				'de' => 'deutsche Beschreibung',
			],
			[
				'en' => [ 'English', 'alias' ],
				'de' => [ 'deutscher', 'Alias' ],
			],
			'schema text'
		);
		$json2 = SchemaEncoder::getPersistentRepresentation(
			new SchemaId( 'E1' ),
			[
				'de' => 'deutsche Beschriftung',
				'en' => 'English label',
			],
			[
				'de' => 'deutsche Beschreibung',
				'en' => 'English description',
			],
			[
				'de' => [ 'deutscher', 'Alias' ],
				'en' => [ 'English', 'alias' ],
			],
			'schema text'
		);

		$this->assertSame( $json1, $json2 );
	}

}
