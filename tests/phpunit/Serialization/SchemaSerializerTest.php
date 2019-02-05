<?php

namespace Wikibase\Schema\Tests\Serialization;

use MediaWikiTestCase;
use Wikibase\Schema\Domain\Model\Schema;
use Wikibase\Schema\Serialization\SerializerFactory;

/**
 * @covers \Wikibase\Schema\Serialization\SchemaSerializer
 *
 * @license GPL-2.0-or-later
 */
class SchemaSerializerTest extends MediaWikiTestCase {

	public function testDeserialization() {
		$testShExC = 'PREFIX wdt: <http://www.wikidata.org/prop/direct/>
PREFIX wd: <http://www.wikidata.org/entity/>

:humans {
  wdt:P31 [wd:Q5]
}';
		$testLabel = 'testlabel';
		$testdescription = 'A test description';
		$langcode = 'en';
		$testAlias1 = 'alias1';
		$testAlias2 = 'alias2';

		$testSchema = new Schema();
		$testSchema->setLabel( $langcode, $testLabel );
		$testSchema->setDescription( $langcode, $testdescription );
		$testSchema->setAliasGroup( $langcode, [ $testAlias1, $testAlias2 ] );
		$testSchema->setSchema( $testShExC );

		$expectedSerialization = [
			'labels' => [
				$langcode => [
					'language' => $langcode,
					'value' => $testLabel,
				],
			],
			'descriptions' => [
				$langcode => [
					'language' => $langcode,
					'value' => $testdescription,
				],
			],
			'aliases' => [
				$langcode => [
					[
						'language' => $langcode,
						'value' => $testAlias1,
					],
					[
						'language' => $langcode,
						'value' => $testAlias2,
					],
				],
			],
			'schema' => $testShExC,
			'type' => 'ShExC',
			'serializationVersion' => '1.0',
		];

		$serializer = SerializerFactory::newSchemaSerializer();
		$actualSerialization = $serializer->serialize( $testSchema );

		$this->assertArrayEquals( $expectedSerialization, $actualSerialization );
	}

}
