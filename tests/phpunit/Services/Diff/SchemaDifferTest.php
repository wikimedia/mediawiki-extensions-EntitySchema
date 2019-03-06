<?php

namespace Wikibase\Schema\Tests\Services\Diff;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpChange;
use Diff\DiffOp\DiffOpRemove;
use PHPUnit\Framework\TestCase;
use Wikibase\Schema\Services\Diff\SchemaDiffer;
use Wikibase\Schema\Services\SchemaDispatcher\FullArraySchemaData;

/**
 * @covers Wikibase\Schema\Services\Diff\SchemaDiffer
 *
 * @license GPL-2.0-or-later
 */
class SchemaDifferTest extends TestCase {

	public function provideSchemaDiffs() {
		yield 'blank' => [
			[],
			[],
			new Diff( [], true ),
		];

		yield 'add label' => [
			[],
			[
				'labels' => [
					'en' => 'testlabel'
				]
			],
			new Diff( [
				'labels' => new Diff( [
					'en' => new DiffOpAdd( 'testlabel' ),
				], true ),
			], true )
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

		yield 'no change' => [
			$schemaEn,
			$schemaEn,
			new Diff( [], true ),
		];

		yield 'changes, removals and additions' => [
			$schemaEn,
			[
				'labels' => [
					'en' => 'updated label',
				],
				'descriptions' => [
					'de' => 'Testbeschreibung',
				],
				'aliases' => [
					'en' => [ 'test alias', 'test alias 3' ],
					'de' => [ 'Testalias' ],
				],
				'schemaText' => 'updated schema',
			],
			new Diff( [
				'labels' => new Diff( [
					'en' => new DiffOpChange( 'test label', 'updated label' ),
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
				'schemaText' => new DiffOpChange( 'test schema', 'updated schema' ),
			], true )
		];

		yield 'change from empty schema counts as addition (not change)' => [
			[ 'schemaText' => '' ],
			[ 'schemaText' => 'test schema' ],
			new Diff( [
				'schemaText' => new DiffOpAdd( 'test schema' ),
			], true ),
		];

		yield 'change to empty schema counts as removal (not change)' => [
			[ 'schemaText' => 'test schema' ],
			[ 'schemaText' => '' ],
			new Diff( [
				'schemaText' => new DiffOpRemove( 'test schema' ),
			], true ),
		];

		yield 'change order of aliases' => [
			$schemaEn,
			[
				'labels' => [
					'en' => 'test label',
				],
				'descriptions' => [
					'en' => 'test description',
				],
				'aliases' => [
					'en' => [ 'test alias 2', 'test alias' ],
				],
				'schemaText' => 'test schema',
			],
			new Diff( [], true )
		];
	}

	/**
	 * @dataProvider provideSchemaDiffs
	 */
	public function testDiffSchemas( array $from, array $to, Diff $expected ) {
		$from = new FullArraySchemaData( $from );
		$to = new FullArraySchemaData( $to );
		$schemaDiffer = new SchemaDiffer();

		$actual = $schemaDiffer->diffSchemas( $from, $to );

		$this->assertSame( $expected->toArray(), $actual->toArray() );
	}

}
