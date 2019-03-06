<?php

namespace Wikibase\Schema\Tests\Services\SchemaDispatcher;

use MediaWikiTestCase;
use Wikibase\Schema\Services\SchemaDispatcher\FullViewSchemaData;
use Wikibase\Schema\Services\SchemaDispatcher\MonolingualSchemaData;
use Wikibase\Schema\Services\SchemaDispatcher\NameBadge;
use Wikibase\Schema\Services\SchemaDispatcher\PersistenceSchemaData;
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

	public function validMonoLingualNameBadgeDataProvider() {
		$expectedNameBadgeData = new NameBadge(
			'english testlabel',
			'english testdescription',
			[ 'english', 'test alias' ]
		);
		yield 'namebadge in requested language' => [
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
			$expectedNameBadgeData
		];
	}

	/**
	 * @dataProvider validMonoLingualNameBadgeDataProvider
	 *
	 * @param string $schemaJSON
	 * @param NameBadge $expectedNameBadgeData
	 */
	public function testMonolingualNameBadgeData( $schemaJSON, $expectedNameBadgeData ) {
		$dispatcher = new SchemaDispatcher();
		$actualNameBadge = $dispatcher->getMonolingualNameBadgeData( $schemaJSON, 'en' );
		$this->assertType( NameBadge::class, $actualNameBadge );
		$this->assertEquals( $expectedNameBadgeData, $actualNameBadge );
	}

	public function provideFullArraySchemaData() {
		yield 'single language' => [
			[
				'labels' => [
					'en' => 'english test label',
				],
				'descriptions' => [
					'en' => 'english test description',
				],
				'aliases' => [
					'en' => [ 'english test alias' ],
				],
				'schema' => 'test schema',
				'serializationVersion' => '2.0',
			],
			[
				'labels' => [
					'en' => 'english test label',
				],
				'descriptions' => [
					'en' => 'english test description',
				],
				'aliases' => [
					'en' => [ 'english test alias' ],
				],
				'schemaText' => 'test schema',
			],
		];

		yield 'multiple languages' => [
			[
				'labels' => [
					'en' => 'english test label',
				],
				'descriptions' => [
					'de' => 'deutsche Testbeschreibung',
				],
				'aliases' => [
					'pt' => [ 'alias de teste em português' ],
				],
				'schema' => 'test schema',
				'serializationVersion' => '2.0',
			],
			[
				'labels' => [
					'en' => 'english test label',
				],
				'descriptions' => [
					'de' => 'deutsche Testbeschreibung',
				],
				'aliases' => [
					'pt' => [ 'alias de teste em português' ],
				],
				'schemaText' => 'test schema',
			],
		];

		yield 'multiple languages with extra empty entries' => [
			[
				'labels' => [
					'en' => 'english test label',
					'de' => '',
					'pt' => '',
				],
				'descriptions' => [
					'en' => '',
					'de' => 'deutsche Testbeschreibung',
					'pt' => '',
				],
				'aliases' => [
					'en' => [],
					'de' => [],
					'pt' => [ 'alias de teste em português' ],
				],
				'schema' => 'test schema',
				'serializationVersion' => '2.0',
			],
			[
				'labels' => [
					'en' => 'english test label',
				],
				'descriptions' => [
					'de' => 'deutsche Testbeschreibung',
				],
				'aliases' => [
					'pt' => [ 'alias de teste em português' ],
				],
				'schemaText' => 'test schema',
			],
		];

		yield 'doesn’t just remove the serialization version' => [
			[
				'labels' => [
					'en' => [
						'language' => 'en',
						'value' => 'english test label',
					],
				],
				'descriptions' => [
					'en' => [
						'language' => 'en',
						'value' => 'english test description',
					],
				],
				'aliases' => [
					'en' => [
						[
							'language' => 'en',
							'value' => 'english test alias',
						],
					],
				],
				'schema' => 'test schema',
				'serializationVersion' => '1.0',
			],
			[
				'labels' => [
					'en' => 'english test label',
				],
				'descriptions' => [
					'en' => 'english test description',
				],
				'aliases' => [
					'en' => [ 'english test alias' ],
				],
				'schemaText' => 'test schema',
			],
		];
	}

	/**
	 * @dataProvider provideFullArraySchemaData
	 */
	public function testFullArraySchemaData(
		array $schema,
		array $expectedSchemaData
	) {
		$schemaJSON = json_encode( $schema );
		$dispatcher = new SchemaDispatcher();

		$actualSchemaData = $dispatcher->getFullArraySchemaData( $schemaJSON )->data;

		$this->assertSame( $expectedSchemaData, $actualSchemaData );
	}

	public function providePersistenceSchemaData() {
		$expectedSchemaData = new PersistenceSchemaData();
		$expectedSchemaData->labels = [ 'en' => 'english test label' ];
		$expectedSchemaData->descriptions = [ 'en' => 'english test description' ];
		$expectedSchemaData->aliases = [ 'en' => [ 'english test alias' ] ];
		$expectedSchemaData->schemaText = 'test schema';
		yield 'single language' => [
			[
				'labels' => [
					'en' => 'english test label',
				],
				'descriptions' => [
					'en' => 'english test description',
				],
				'aliases' => [
					'en' => [ 'english test alias' ],
				],
				'schema' => 'test schema',
				'serializationVersion' => '2.0',
			],
			$expectedSchemaData,
		];

		$expectedSchemaData = new PersistenceSchemaData();
		$expectedSchemaData->labels = [
			'de' => 'deutsche Testbezeichnung',
			'en' => 'english test label'
		];
		$expectedSchemaData->descriptions = [ 'de' => 'deutsche Testbeschreibung', ];
		$expectedSchemaData->aliases = [ 'pt' => [ 'alias de teste em português' ], ];
		$expectedSchemaData->schemaText = 'test schema';
		yield 'multiple languages' => [
			[
				'labels' => [
					'en' => 'english test label',
					'de' => 'deutsche Testbezeichnung',
				],
				'descriptions' => [
					'de' => 'deutsche Testbeschreibung',
				],
				'aliases' => [
					'pt' => [ 'alias de teste em português' ],
				],
				'schema' => 'test schema',
				'serializationVersion' => '2.0',
			],
			$expectedSchemaData,
		];
	}

	/**
	 * @dataProvider providePersistenceSchemaData
	 */
	public function testPersistenceSchemaData(
		array $schema,
		PersistenceSchemaData $expectedSchemaData
	) {
		$schemaJSON = json_encode( $schema );
		$dispatcher = new SchemaDispatcher();

		$actualSchemaData = $dispatcher->getPersistenceSchemaData( $schemaJSON );

		$this->assertEquals( $expectedSchemaData, $actualSchemaData );
	}

	public function provideSerializationsWithId() {
		yield [
			[
				'id' => 'O123',
				'serializationVersion' => '2.0',
			],
			'O123'
		];
	}

	/**
	 * @dataProvider provideSerializationsWithId
	 */
	public function testGetID(
		array $schema,
		$expectedID
	) {
		$schemaJSON = json_encode( $schema );
		$dispatcher = new SchemaDispatcher();

		$actualSchemaId = $dispatcher->getSchemaID( $schemaJSON );

		$this->assertSame( $expectedID, $actualSchemaId );
	}

}
