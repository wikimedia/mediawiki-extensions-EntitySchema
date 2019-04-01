<?php

namespace Wikibase\Schema\Tests\DataAccess;

use Wikibase\Schema\DataAccess\SchemaCleaner;

/**
 * @license GPL-2.0-or-later
 * @covers \Wikibase\Schema\DataAccess\SchemaCleaner
 */
class SchemaCleanerTest extends \MediaWikiTestCase {

	public function provideTestData() {
		yield 'well formed data' => [
			[ 'en' => 'en label' ],
			[ 'en' => 'en description' ],
			[ 'en' => [ 'en', 'aliases' ] ],
			'schema text',
			[ 'en' => 'en label' ],
			[ 'en' => 'en description' ],
			[ 'en' => [ 'en', 'aliases' ] ],
			'schema text',
		];

		yield 'trim strange whitespaces' => [
			[ 'en' => '         	testLabel﻿   ' ],
			[ 'en' => "  \v\t  testDescription﻿ \r\n  " ],
			[ 'en' => [ '  test ​ ', '   aliases  ', '   ', ' 0 ' ] ],
			'  a b ﻿  ',
			[ 'en' => 'testLabel' ],
			[ 'en' => 'testDescription' ],
			[ 'en' => [ 'test', 'aliases', '0' ] ],
			'a b',
		];

		yield 'remove empty elements' => [
			[
				'en' => 'actual label',
				'de' => '    ',
				'pt' => '',
			],
			[
				'en' => 'actual description',
				'de' => '    ',
				'pt' => '',
			],
			[
				'en' => [ 'actual', 'alias' ],
				'de' => [ '   ' ],
				'pt' => [ '' ],
				'la' => [],
			],
			'schema text',
			[ 'en' => 'actual label' ],
			[ 'en' => 'actual description' ],
			[ 'en' => [ 'actual', 'alias' ] ],
			'schema text',
		];
	}

	/**
	 * @dataProvider provideTestData
	 */
	public function testCleanupParameters(
		array $labels,
		array $descriptions,
		array $aliasGroups,
		$schemaText,
		array $expectedLabels,
		array $expectedDescriptions,
		array $expectedAliasGroups,
		$expectedSchemaText
	) {

		SchemaCleaner::cleanupParameters(
			$labels,
			$descriptions,
			$aliasGroups,
			$schemaText
		);
		$this->assertSame( $expectedLabels, $labels );
		$this->assertSame( $expectedDescriptions, $descriptions );
		$this->assertSame( $expectedAliasGroups, $aliasGroups );
		$this->assertSame( $expectedSchemaText, $schemaText );
	}

}
