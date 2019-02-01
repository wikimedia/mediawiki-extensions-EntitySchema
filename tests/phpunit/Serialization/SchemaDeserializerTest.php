<?php

namespace Wikibase\Schema\Tests\Serialization;

use MediaWikiTestCase;
use Wikibase\Schema\Serialization\DeserializerFactory;

/**
 * @covers \Wikibase\Schema\Serialization\SchemaDeserializer
 *
 * @license GPL-2.0-or-later
 */
class SchemaDeserializerTest extends MediaWikiTestCase {

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

		$testSerialization = [
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

		$deserializer = DeserializerFactory::newSchemaDeserializer();
		$actualSchema = $deserializer->deserialize( $testSerialization );

		$this->assertSame( $testLabel, $actualSchema->getLabel( $langcode )->getText() );
		$this->assertSame( $testdescription, $actualSchema->getDescription( $langcode )->getText() );
		$this->assertSame(
			[ $testAlias1, $testAlias2 ],
			$actualSchema->getAliasGroup( $langcode )->getAliases()
		);
		$this->assertSame( $testShExC, $actualSchema->getSchema() );
	}

}
