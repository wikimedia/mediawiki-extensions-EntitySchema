<?php

namespace Wikibase\Schema\Tests\Services\Diff;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpChange;
use Diff\DiffOp\DiffOpRemove;
use Diff\Patcher\PatcherException;
use PHPUnit\Framework\TestCase;
use Wikibase\Schema\Services\Diff\SchemaPatcher;
use Wikibase\Schema\Services\SchemaDispatcher\FullArraySchemaData;

/**
 * @license GPL-2.0-or-later
 *
 * @covers \Wikibase\Schema\Services\Diff\SchemaPatcher
 * @covers \Wikibase\Schema\Services\Diff\AliasGroupListPatcher
 */
class SchemaPatcherTest extends TestCase {

	use \PHPUnit4And6Compat;

	public function provideValidSchemaPatches() {

		yield 'restore label' => [
			[],
			new Diff( [
				'labels' => new Diff( [
					'en' => new DiffOpAdd( 'testlabel' ),
				], true ),
			], true ),
			[
				'labels' => [
					'en' => 'testlabel',
				],
				'descriptions' => [],
				'aliases' => [],
				'schema' => '',
			],
		];

		$schemaEn = [
			'labels' => [
				'en' => 'test label',
			],
			'descriptions' => [
				'en' => 'test description',
			],
			'aliases' => [
				'en' => [ 'test alias', 'test alias 2' ],
			],
			'schemaText' => 'test schema',
		];

		yield 'changes, removals and additions' => [
			$schemaEn,
			new Diff( [
				'labels' => new Diff( [
					'en' => new DiffOpChange( 'test label', 'label for test' ),
				], true ),
				'descriptions' => new Diff( [
					'en' => new DiffOpRemove( 'test description' ),
					'de' => new DiffOpAdd( 'Testbeschreibung' ),
				], true ),
				'aliases' => new Diff( [
					'en' => new Diff( [
						new DiffOpAdd( 'test alias 3' ),
						new DiffOpRemove( 'test alias 2' ),
					], false ),
					'de' => new Diff( [
						new DiffOpAdd( 'Testalias' ),
					], false ),
				], true ),
				'schemaText' => new DiffOpChange( 'test schema', 'schema for test' ),
			], true ),
			[
				'labels' => [
					'en' => 'label for test',
				],
				'descriptions' => [
					'de' => 'Testbeschreibung',
				],
				'aliases' => [
					'en' => [ 'test alias', 'test alias 3' ],
					'de' => [ 'Testalias' ],
				],
				'schema' => 'schema for test',
			],
		];

		yield 'restore schema' => [
			[ 'schema' => '' ],
			new Diff( [
				'schemaText' => new DiffOpAdd( 'test schema' ),
			], true ),
			[
				'labels' => [],
				'descriptions' => [],
				'aliases' => [],
				'schema' => 'test schema',
			],
		];

		yield 'remove schema' => [
			[ 'schema' => 'test schema' ],
			new Diff( [
				'schemaText' => new DiffOpRemove( 'test schema' ),
			], true ),
			[
				'labels' => [],
				'descriptions' => [],
				'aliases' => [],
				'schema' => '',
			],
		];
	}

	/**
	 * @dataProvider provideValidSchemaPatches
	 */
	public function testPatchSchema( $currentSchema, $patch, $expected ) {
		$schemaPatcher = new SchemaPatcher();

		$actualPatched = $schemaPatcher->patchSchema( new FullArraySchemaData( $currentSchema ), $patch );

		$this->assertEquals( $expected, $actualPatched );
	}

	public function providInvalidSchemaPatches() {
		yield 'restore existing schema' => [
			[ 'schemaText' => 'I exist!' ],
			new Diff( [
				'schemaText' => new DiffOpAdd( 'test schema' ),
			], true ),
		];

		yield 'existing label' => [
			[
				'labels' => [
					'en' => 'existing label',
				],
			],
			new Diff( [
				'labels' => new Diff( [
					'en' => new DiffOpAdd( 'testlabel' ),
				], true ),
			], true ),
		];

		yield 'remove changed description' => [
			[
				'descriptions' => [
					'en' => 'I am not the original anymore',
				],
			],
			new Diff( [
				'descriptions' => new Diff( [
					'en' => new DiffOpRemove( 'original description' ),
				], true ),
			], true ),
		];

		yield 'try to revert changed label' => [
			[
				'labels' => [
					'en' => 'actual current label',
				],
			],
			new Diff( [
				'labels' => new Diff( [
					'en' => new DiffOpChange( 'unwanted label', 'value to which to revert' ),
				], true ),
			] ),
		];
	}

	/**
	 * @dataProvider providInvalidSchemaPatches
	 */
	public function testPatchSchemaFailure( $currentSchema, $patch ) {
		$schemaPatcher = new SchemaPatcher();

		$this->expectException( PatcherException::class );

		$schemaPatcher->patchSchema( new FullArraySchemaData( $currentSchema ), $patch );
	}

}
