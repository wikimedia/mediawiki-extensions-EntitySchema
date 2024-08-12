<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Services\Diff;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpChange;
use Diff\DiffOp\DiffOpRemove;
use Diff\Patcher\PatcherException;
use EntitySchema\Services\Converter\FullArrayEntitySchemaData;
use EntitySchema\Services\Diff\EntitySchemaPatcher;
use PHPUnit\Framework\TestCase;

/**
 * @license GPL-2.0-or-later
 *
 * @group EntitySchemaClient
 *
 * @covers \EntitySchema\Services\Diff\EntitySchemaPatcher
 * @covers \EntitySchema\Services\Diff\AliasGroupListPatcher
 */
class EntitySchemaPatcherTest extends TestCase {

	public static function provideValidSchemaPatches(): iterable {

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
				'schemaText' => '',
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
				'schemaText' => 'schema for test',
			],
		];

		yield 'restore schema' => [
			[ 'schemaText' => '' ],
			new Diff( [
				'schemaText' => new DiffOpAdd( 'test schema' ),
			], true ),
			[
				'labels' => [],
				'descriptions' => [],
				'aliases' => [],
				'schemaText' => 'test schema',
			],
		];

		yield 'remove schema' => [
			[ 'schemaText' => 'test schema' ],
			new Diff( [
				'schemaText' => new DiffOpRemove( 'test schema' ),
			], true ),
			[
				'labels' => [],
				'descriptions' => [],
				'aliases' => [],
				'schemaText' => '',
			],
		];
	}

	/**
	 * @dataProvider provideValidSchemaPatches
	 */
	public function testPatchSchema( array $currentSchema, Diff $patch, array $expected ) {
		$schemaPatcher = new EntitySchemaPatcher();

		$actualPatched = $schemaPatcher->patchSchema( new FullArrayEntitySchemaData( $currentSchema ), $patch );

		$this->assertEquals( $expected, $actualPatched->data );
	}

	public static function provideInvalidSchemaPatches(): iterable {
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
	 * @dataProvider provideInvalidSchemaPatches
	 */
	public function testPatchSchemaFailure( array $currentSchema, Diff $patch ) {
		$schemaPatcher = new EntitySchemaPatcher();

		$this->expectException( PatcherException::class );

		$schemaPatcher->patchSchema( new FullArrayEntitySchemaData( $currentSchema ), $patch );
	}

}
