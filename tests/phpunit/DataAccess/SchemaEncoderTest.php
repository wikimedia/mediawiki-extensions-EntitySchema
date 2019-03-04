<?php

namespace phpunit\DataAccess;

use PHPUnit\Framework\TestCase;
use Wikibase\Schema\DataAccess\SchemaEncoder;
use Wikibase\Schema\Domain\Model\SchemaId;

/**
 * @covers \Wikibase\Schema\DataAccess\SchemaEncoder
 *
 * @license GPL-2.0-or-later
 */
class SchemaEncoderTest extends TestCase {

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

}
