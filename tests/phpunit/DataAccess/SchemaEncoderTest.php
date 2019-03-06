<?php

namespace phpunit\DataAccess;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit4And6Compat;
use Wikibase\Schema\DataAccess\SchemaEncoder;
use Wikibase\Schema\Domain\Model\SchemaId;

/**
 * @covers \Wikibase\Schema\DataAccess\SchemaEncoder
 *
 * @license GPL-2.0-or-later
 */
class SchemaEncoderTest extends TestCase {

	use PHPUnit4And6Compat;

	public function testGetPersistentRepresentation() {
		$id = 'O1';
		$language = 'en';
		$label = 'englishLabel';
		$description = 'englishDescription';
		$aliases = [ 'englishAlias' ];
		$schemaText = '#some schema about goats';
		$expectedJson = [
			'id' => $id,
			'serializationVersion' => '2.0',
			'labels' => [
				$language => $label,
			],
			'descriptions' => [
				$language => $description,
			],
			'aliases' => [
				$language => $aliases,
			],
			'schema' => $schemaText,
			'type' => 'ShExC',
		];

		$actualJson = SchemaEncoder::getPersistentRepresentation(
			new SchemaId( $id ),
			[ $language => $label ],
			[ $language => $description ],
			[ $language => $aliases ],
			$schemaText
		);

		$this->assertSame( $expectedJson, json_decode( $actualJson, true ) );
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
		$this->setExpectedException(
			InvalidArgumentException::class,
			$expectedMessage
		);

		SchemaEncoder::getPersistentRepresentation(
			new SchemaId( 'O1' ),
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
			'language, label, description and schemaContent must be strings',
		];

		yield 'invalid type (descriptions)' => [
			$validLabels,
			[ 'en' => 1 ],
			$validAliasGroups,
			$validSchemaText,
			'language, label, description and schemaContent must be strings',
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
			'language, label, description and schemaContent must be strings',
		];

		yield 'aliases not unique' => [
			$validLabels,
			$validDescriptions,
			[ 'en' => [ 'alias A', 'alias B', 'alias A' ] ],
			$validSchemaText,
			'aliases must be unique',
		];
	}

}
