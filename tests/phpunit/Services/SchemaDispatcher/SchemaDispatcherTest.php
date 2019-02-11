<?php

namespace Wikibase\Schema\Tests\Services\SchemaDispatcher;

use MediaWikiTestCase;
use Wikibase\Schema\Services\SchemaDispatcher\FullViewSchemaData;
use Wikibase\Schema\Services\SchemaDispatcher\MonolingualSchemaData;
use Wikibase\Schema\Services\SchemaDispatcher\NameBadge;
use Wikibase\Schema\Services\SchemaDispatcher\SchemaDispatcher;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \Wikibase\Schema\Services\SchemaDispatcher\SchemaDispatcher
 */
class SchemaDispatcherTest extends MediaWikiTestCase {

	public function validFullViewDataProvider() {
		yield 'schema in interface language only' => [
			json_encode(
				[
					'labels' => [
						'en' => 'english testlabel',
					],
					'descriptions' => [
						'en' => 'english testdescription',
					],
					'aliases' => [
						'en' => [ 'english', 'test alias' ],
					],
					'schema' => 'abc',
					'serializationVersion' => '2.0',
				]
			),
			new FullViewSchemaData(
				[
					'en' => new NameBadge(
						'english testlabel',
						'english testdescription',
						[ 'english', 'test alias' ]
					),
				],
				'abc'
			),
		];

		yield 'schema in interface and another language' => [
			json_encode(
				[
					'labels' => [
						'en' => 'english testlabel',
						'de' => 'deutsche Testbezeichnung',
					],
					'descriptions' => [
						'en' => 'english testdescription',
						'de' => 'deutsche Testbeschreibung',
					],
					'aliases' => [
						'en' => [ 'english', 'test alias' ],
						'de' => [ 'deutsch', 'Testalias' ],
					],
					'schema' => 'abc',
					'serializationVersion' => '2.0',
				]
			),
			new FullViewSchemaData(
				[
					'en' => new NameBadge(
						'english testlabel',
						'english testdescription',
						[ 'english', 'test alias' ]
					),
					'de' => new NameBadge(
						'deutsche Testbezeichnung',
						'deutsche Testbeschreibung',
						[ 'deutsch', 'Testalias' ]
					),
				],
				'abc'
			),
		];

		yield 'schema not in interface' => [
			json_encode(
				[
					'labels' => [
						'de' => 'deutsche Testbezeichnung',
					],
					'descriptions' => [
						'de' => 'deutsche Testbeschreibung',
					],
					'aliases' => [
						'de' => [ 'deutsch', 'Testalias' ],
					],
					'schema' => 'abc',
					'serializationVersion' => '2.0',
				]
			),
			new FullViewSchemaData(
				[
					'en' => new NameBadge(
						'',
						'',
						[]
					),
					'de' => new NameBadge(
						'deutsche Testbezeichnung',
						'deutsche Testbeschreibung',
						[ 'deutsch', 'Testalias' ]
					),
				],
				'abc'
			),
		];

		yield 'serializationVersion 1.0' => [
			json_encode(
				[
					'labels' => [
						'en' => [
							'language' => 'en',
							'value' => 'english testlabel',
						],
					],
					'descriptions' => [
						'en' => [
							'language' => 'en',
							'value' => 'english testdescription',
						],
					],
					'aliases' => [
						'en' => [
							[
								'language' => 'en',
								'value' => 'english',
							],
							[
								'language' => 'en',
								'value' => 'test alias',
							],
						],
					],
					'schema' => 'abc',
					'serializationVersion' => '1.0',
				]
			),
			new FullViewSchemaData(
				[
					'en' => new NameBadge(
						'english testlabel',
						'english testdescription',
						[ 'english', 'test alias' ]
					),
				],
				'abc'
			),
		];
	}

	/**
	 * @dataProvider validFullViewDataProvider
	 *
	 * @param string $schemaJSON
	 * @param FullViewSchemaData $expectedSchemaData
	 */
	public function testFullViewSchemaData( $schemaJSON, FullViewSchemaData $expectedSchemaData ) {
		$dispatcher = new SchemaDispatcher();

		$actualSchema = $dispatcher->getFullViewSchemaData( $schemaJSON, 'en' );

		$this->assertType( FullViewSchemaData::class, $actualSchema );
		$this->assertEquals( $expectedSchemaData, $actualSchema );
	}

	public function validMonoLingualDataProvider() {
		yield 'schema in requested language' => [
			json_encode(
				[
					'labels' => [
						'en' => 'english testlabel',
					],
					'descriptions' => [
						'en' => 'english testdescription',
					],
					'aliases' => [
						'en' => [ 'english', 'test alias' ],
					],
					'schema' => 'abc',
					'serializationVersion' => '2.0',
				]
			),
			new MonolingualSchemaData(
				new NameBadge(
					'english testlabel',
					'english testdescription',
					[ 'english', 'test alias' ]
				),
				'abc'
			),
		];

		yield 'schema not in requested language' => [
			json_encode(
				[
					'labels' => [
						'de' => 'deutsche Testbezeichnung',
					],
					'descriptions' => [
						'de' => 'deutsche Testbeschreibung',
					],
					'aliases' => [
						'de' => [ 'deutsch', 'Testalias' ],
					],
					'schema' => 'abc',
					'serializationVersion' => '2.0',
				]
			),
			new MonolingualSchemaData(
				new NameBadge(
					'',
					'',
					[]
				),
				'abc'
			),
		];
	}

	/**
	 * @dataProvider validMonoLingualDataProvider
	 *
	 * @param string $schemaJSON
	 * @param MonolingualSchemaData $expectedSchemaData
	 */
	public function testMonolingualSchemaData( $schemaJSON, $expectedSchemaData ) {
		$dispatcher = new SchemaDispatcher();
		$actualSchema = $dispatcher->getMonolingualSchemaData( $schemaJSON, 'en' );
		$this->assertType( MonolingualSchemaData::class, $actualSchema );
		$this->assertEquals( $expectedSchemaData, $actualSchema );
	}

}
