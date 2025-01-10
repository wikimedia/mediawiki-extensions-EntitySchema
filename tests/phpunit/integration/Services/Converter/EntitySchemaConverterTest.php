<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Services\Converter;

use EntitySchema\Services\Converter\EntitySchemaConverter;
use EntitySchema\Services\Converter\FullViewEntitySchemaData;
use EntitySchema\Services\Converter\NameBadge;
use EntitySchema\Services\Converter\PersistenceEntitySchemaData;
use MediaWikiIntegrationTestCase;

/**
 * @license GPL-2.0-or-later
 *
 * @group EntitySchemaClient
 *
 * @covers \EntitySchema\Services\Converter\EntitySchemaConverter
 * @covers \EntitySchema\Services\Converter\SearchEntitySchemaAdapter
 */
class EntitySchemaConverterTest extends MediaWikiIntegrationTestCase {

	public static function validFullViewDataProvider(): iterable {
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
			new FullViewEntitySchemaData(
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
			new FullViewEntitySchemaData(
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
			new FullViewEntitySchemaData(
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
			new FullViewEntitySchemaData(
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
	 * @param FullViewEntitySchemaData $expectedSchemaData
	 */
	public function testFullViewSchemaData(
		string $schemaJSON,
		FullViewEntitySchemaData $expectedSchemaData
	) {
		$converter = new EntitySchemaConverter();

		$actualSchema = $converter->getFullViewSchemaData( $schemaJSON );

		$this->assertInstanceOf( FullViewEntitySchemaData::class, $actualSchema );
		$this->assertEquals( $expectedSchemaData, $actualSchema );
	}

	public static function validMonoLingualNameBadgeDataProvider(): iterable {
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
			$expectedNameBadgeData,
		];
	}

	/**
	 * @dataProvider validMonoLingualNameBadgeDataProvider
	 */
	public function testMonolingualNameBadgeData( string $schemaJSON, NameBadge $expectedNameBadgeData ) {
		$converter = new EntitySchemaConverter();
		$actualNameBadge = $converter->getMonolingualNameBadgeData( $schemaJSON, 'en' );
		$this->assertInstanceOf( NameBadge::class, $actualNameBadge );
		$this->assertEquals( $expectedNameBadgeData, $actualNameBadge );
	}

	public static function provideFullArraySchemaData(): iterable {
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
		$converter = new EntitySchemaConverter();

		$actualSchemaData = $converter->getFullArraySchemaData( $schemaJSON )->data;

		$this->assertSame( $expectedSchemaData, $actualSchemaData );
	}

	public static function providePersistenceSchemaData(): iterable {
		$expectedSchemaData = new PersistenceEntitySchemaData();
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

		$expectedSchemaData = new PersistenceEntitySchemaData();
		$expectedSchemaData->labels = [
			'de' => 'deutsche Testbezeichnung',
			'en' => 'english test label',
		];
		$expectedSchemaData->descriptions = [ 'de' => 'deutsche Testbeschreibung' ];
		$expectedSchemaData->aliases = [ 'pt' => [ 'alias de teste em português' ] ];
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
						'value' => 'alias de teste em português',
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
		PersistenceEntitySchemaData $expectedSchemaData
	) {
		$schemaJSON = json_encode( $schema );
		$converter = new EntitySchemaConverter();

		$actualSchemaData = $converter->getPersistenceSchemaData( $schemaJSON );

		$this->assertEquals( $expectedSchemaData, $actualSchemaData );
	}

	public static function provideSerializationsWithId(): iterable {
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
		string $expectedID
	) {
		$schemaJSON = json_encode( $schema );
		$converter = new EntitySchemaConverter();

		$actualSchemaId = $converter->getSchemaID( $schemaJSON );

		$this->assertSame( $expectedID, $actualSchemaId );
	}

	public function testGetSearchEntitySchemaAdapter(): void {
		$schema = [
			'id' => 'E123',
			'serializationVersion' => '3.0',
			'labels' => [
				'en' => 'en: label, description, aliases',
				'de' => 'de: label, description, no aliases',
				'he' => 'he: label, no description, aliases',
				// pt: no label, description, aliases
				// ar: no label, description, no aliases
				// fa: no label, no description, aliases
			],
			'descriptions' => [
				'en' => 'en: label, description, aliases',
				'de' => 'de: label, description, no aliases',
				// he: label, no description, aliases
				'pt' => 'pt: no label, description, aliases',
				'ar' => 'ar: no label, description, no aliases',
				// fa: no label, no description, aliases
			],
			'aliases' => [
				'en' => [ 'en', 'label', 'description', 'aliases' ],
				// de: label, description, no aliases
				'he' => [ 'he', 'label', 'no description', 'aliases' ],
				'pt' => [ 'pt', 'label', 'description', 'no aliases' ],
				// ar: no label, description, no aliases
				'fa' => [ 'fa', 'no label', 'no description', 'aliases' ],
			],
			'schemaText' => '',
		];
		$schemaJson = json_encode( $schema );
		$converter = new EntitySchemaConverter();

		$adapter = $converter->getSearchEntitySchemaAdapter( $schemaJson );

		$labels = $adapter->getLabels();
		$this->assertSame( [
			'en' => 'en: label, description, aliases',
			'de' => 'de: label, description, no aliases',
			'he' => 'he: label, no description, aliases',
		], $labels->toTextArray() );
		$this->assertFalse( $labels->hasTermForLanguage( 'pt' ) );
		$this->assertFalse( $labels->hasTermForLanguage( 'ar' ) );
		$this->assertFalse( $labels->hasTermForLanguage( 'fa' ) );

		$descriptions = $adapter->getDescriptions();
		$this->assertSame( [
			'en' => 'en: label, description, aliases',
			'de' => 'de: label, description, no aliases',
			'pt' => 'pt: no label, description, aliases',
			'ar' => 'ar: no label, description, no aliases',
		], $descriptions->toTextArray() );
		$this->assertFalse( $descriptions->hasTermForLanguage( 'he' ) );
		$this->assertFalse( $descriptions->hasTermForLanguage( 'fa' ) );

		$aliases = $adapter->getAliasGroups();
		$this->assertSame( [
			'en' => [ 'en', 'label', 'description', 'aliases' ],
			'he' => [ 'he', 'label', 'no description', 'aliases' ],
			'pt' => [ 'pt', 'label', 'description', 'no aliases' ],
			'fa' => [ 'fa', 'no label', 'no description', 'aliases' ],
		], $aliases->toTextArray() );
		$this->assertFalse( $aliases->hasGroupForLanguage( 'de' ) );
		$this->assertFalse( $aliases->hasGroupForLanguage( 'ar' ) );
	}

}
