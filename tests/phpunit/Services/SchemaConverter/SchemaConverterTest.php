<?php

namespace Wikibase\Schema\Tests\Services\SchemaConverter;

use MediaWikiTestCase;
use Wikibase\Schema\Services\SchemaConverter\FullViewSchemaData;
use Wikibase\Schema\Services\SchemaConverter\NameBadge;
use Wikibase\Schema\Services\SchemaConverter\PersistenceSchemaData;
use Wikibase\Schema\Services\SchemaConverter\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \Wikibase\Schema\Services\SchemaConverter\SchemaConverter
 */
class SchemaConverterTest extends MediaWikiTestCase {

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
					'schemaText' => 'abc',
					'serializationVersion' => '3.0',
				]
			),
			[ 'en' ],
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

		yield 'schema in preferred languages and other language' => [
			json_encode(
				[
					'labels' => [
						'en' => 'english testlabel',
						'de' => 'deutsche Testbezeichnung',
						'fr' => 'libellé de test français',
					],
					'descriptions' => [
						'en' => 'english testdescription',
						'de' => 'deutsche Testbeschreibung',
						'fr' => 'description de test français',
					],
					'aliases' => [
						'en' => [ 'english', 'test alias' ],
						'de' => [ 'deutsch', 'Testalias' ],
						'fr' => [ 'français', 'test alias' ],
					],
					'schemaText' => 'abc',
					'serializationVersion' => '3.0',
				]
			),
			[ 'en', 'de' ],
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
					'fr' => new NameBadge(
						'libellé de test français',
						'description de test français',
						[ 'français', 'test alias' ]
					),
				],
				'abc'
			),
		];

		yield 'schema not in preferred languages' => [
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
					'schemaText' => 'abc',
					'serializationVersion' => '3.0',
				]
			),
			[ 'en', 'ru' ],
			new FullViewSchemaData(
				[
					'en' => new NameBadge(
						'',
						'',
						[]
					),
					'ru' => new NameBadge(
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

		yield 'serializationVersion 2.0' => [
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
			[ 'en' ],
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
			[ 'en' ],
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
	 * @param string[] $preferredLanguages
	 * @param FullViewSchemaData $expectedSchemaData
	 */
	public function testFullViewSchemaData(
		$schemaJSON,
		array $preferredLanguages,
		FullViewSchemaData $expectedSchemaData
	) {
		$converter = new SchemaConverter();

		$actualSchema = $converter->getFullViewSchemaData( $schemaJSON, $preferredLanguages );

		$this->assertType( FullViewSchemaData::class, $actualSchema );
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
					'schemaText' => 'abc',
					'serializationVersion' => '3.0',
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
		$converter = new SchemaConverter();
		$actualNameBadge = $converter->getMonolingualNameBadgeData( $schemaJSON, 'en' );
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
				'schemaText' => 'test schema',
				'serializationVersion' => '3.0',
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
				'schemaText' => 'test schema',
				'serializationVersion' => '3.0',
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
				'schemaText' => 'test schema',
				'serializationVersion' => '3.0',
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

		yield 'serialization version 2.0' => [
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

		yield 'serialization version 1.0' => [
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
		$converter = new SchemaConverter();

		$actualSchemaData = $converter->getFullArraySchemaData( $schemaJSON )->data;

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
				'schemaText' => 'test schema',
				'serializationVersion' => '3.0',
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
				'schemaText' => 'test schema',
				'serializationVersion' => '3.0',
			],
			$expectedSchemaData,
		];
		yield 'serialization version 2.0' => [
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
		yield 'serialization version 1.0' => [
			[
				'labels' => [
					'en' => [
						'language' => 'en',
						'value' => 'english test label',
					],
					'de' => [
						'language' => 'de',
						'value' => 'deutsche Testbezeichnung',
					],
				],
				'descriptions' => [
					'de' => [
						'language' => 'de',
						'value' => 'deutsche Testbeschreibung',
					],
				],
				'aliases' => [
					'pt' => [ [
						'language' => 'pt',
						'value' => 'alias de teste em português'
					] ],
				],
				'schema' => 'test schema',
				'serializationVersion' => '1.0',
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
		$converter = new SchemaConverter();

		$actualSchemaData = $converter->getPersistenceSchemaData( $schemaJSON );

		$this->assertEquals( $expectedSchemaData, $actualSchemaData );
	}

	public function provideSerializationsWithId() {
		yield 'serialization version 3.0' => [
			[
				'id' => 'E123',
				'serializationVersion' => '3.0',
			],
			'E123',
		];

		yield 'serialization version 2.0' => [
			[
				'id' => 'E123',
				'serializationVersion' => '2.0',
			],
			'E123',
		];

		yield 'serialization version 1.0' => [
			[
				'id' => 'E123',
				'serializationVersion' => '1.0',
			],
			'E123',
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
		$converter = new SchemaConverter();

		$actualSchemaId = $converter->getSchemaID( $schemaJSON );

		$this->assertSame( $expectedID, $actualSchemaId );
	}

}
