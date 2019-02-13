<?php

namespace Wikibase\Schema\Services\Diff;

use Diff\Differ\MapDiffer;
use Diff\DiffOp\Diff\Diff;

/**
 * Computes the difference between two schemas, which are represented as arrays.
 * The difference is represented as an associative {@link Diff} with the following operations:
 *
 * - labels: an associative {@link Diff} where the keys are language codes
 *   and the values are {@link AtomicDiffOp}s for label addition, removal or change.
 * - descriptions: an associative Diff where the keys are language codes
 *   and the values are {@link AtomicDiffOp}s for description addition, removal or change.
 * - aliases: an associative {@link Diff} where the keys are language codes
 *   and the values are non-associative {@link Diff}s
 *   containing {@link DiffOpAdd}s and {@link DiffOpRemove}s.
 *   (A “change” to an alias appears as a removal+addition pair.)
 * - schema: a single {@link AtomicDiffOp} for schema addition, removal or change.
 *   (Empty schema strings are considered absent. No fine-grained diffing on the text occurs.)
 *
 * @license GPL-2.0-or-later
 */
class SchemaDiffer {

	private $recursiveMapDiffer;

	public function __construct() {
		$this->recursiveMapDiffer = new MapDiffer( true );
	}

	public function diffSchemas( array $from, array $to ): Diff {
		if ( array_key_exists( 'schema', $from ) && $from['schema'] === '' ) {
			unset( $from['schema'] );
		}
		if ( array_key_exists( 'schema', $to ) && $to['schema'] === '' ) {
			unset( $to['schema'] );
		}

		$diff = $this->recursiveMapDiffer->doDiff( $from, $to );

		return new Diff( $diff, true );
	}

}
