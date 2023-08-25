<?php

declare( strict_types = 1 );

namespace EntitySchema\Services\Diff;

use Diff\DiffOp\AtomicDiffOp;
use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOp;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpChange;
use Diff\DiffOp\DiffOpRemove;
use Diff\Patcher\PatcherException;
use EntitySchema\Services\Converter\FullArraySchemaData;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaPatcher {

	/**
	 * @param FullArraySchemaData $baseSchema
	 * @param Diff $patch
	 *
	 * @return FullArraySchemaData
	 *
	 * @throws PatcherException throws exception if patch cannot be applied
	 */
	public function patchSchema( FullArraySchemaData $baseSchema, Diff $patch ): FullArraySchemaData {
		$patchedSchema = $this->patchFingerprint( $baseSchema->data, $patch );

		$patchedSchema['schemaText'] = $this->patchString(
			$baseSchema->data['schemaText'] ?? '',
			$patch['schemaText'] ?? null
		);

		return new FullArraySchemaData( $patchedSchema );
	}

	private function patchFingerprint( array $baseSchema, Diff $patch ): array {
		$aliasGroupPatcher = new AliasGroupListPatcher();

		$patchedSchema = [
			'labels' => $this->patchTermlist(
				$baseSchema['labels'] ?? [],
				$patch['labels'] ?? null
			),
			'descriptions' => $this->patchTermlist(
				$baseSchema['descriptions'] ?? [],
				$patch['descriptions'] ?? null
			),
			'aliases' => $aliasGroupPatcher->patchAliasGroupList(
				$baseSchema['aliases'] ?? [],
				$patch['aliases'] ?? null
			),
		];

		return $patchedSchema;
	}

	private function patchTermlist( array $terms, Diff $patch = null ): array {
		if ( $patch === null ) {
			return $terms;
		}
		foreach ( $patch as $lang => $diffOp ) {
			$terms = $this->patchTerm( $terms, $lang, $diffOp );
		}
		return $terms;
	}

	private function patchTerm( array $terms, string $lang, AtomicDiffOp $diffOp ): array {
		switch ( true ) {
			case $diffOp instanceof DiffOpAdd:
				if ( !empty( $terms[$lang] ) ) {
					throw new PatcherException( 'Term already exists' );
				}
				$terms[$lang] = $diffOp->getNewValue();
				break;

			case $diffOp instanceof DiffOpChange:
				if ( empty( $terms[$lang] )
					|| $terms[$lang] !== $diffOp->getOldValue()
				) {
					throw new PatcherException( 'Term had been changed' );
				}
				$terms[$lang] = $diffOp->getNewValue();
				break;

			case $diffOp instanceof DiffOpRemove:
				if ( !empty( $terms[$lang] )
					&& $terms[$lang] !== $diffOp->getOldValue()
				) {
					throw new PatcherException( 'Term had been changed' );
				}
				unset( $terms[$lang] );
				break;

			default:
				throw new PatcherException( 'Invalid terms diff' );
		}

		return $terms;
	}

	/**
	 * @param string $base
	 * @param DiffOp|null $diffOp
	 *
	 * @return string
	 */
	private function patchString( string $base, DiffOp $diffOp = null ): string {
		switch ( true ) {
			case $diffOp instanceof DiffOpAdd:
				$from = '';
				$to = $diffOp->getNewValue();
				break;
			case $diffOp instanceof DiffOpRemove:
				$from = $diffOp->getOldValue();
				$to = '';
				break;
			case $diffOp instanceof DiffOpChange:
				$from = $diffOp->getOldValue();
				$to = $diffOp->getNewValue();
				break;
			default:
				$from = $to = null;
		}
		if ( $from !== $to ) {
			$ok = wfMerge(
				$from,
				$to,
				$base,
				$result
			);
			if ( !$ok ) {
				throw new PatcherException( 'Patching the Schema failed because it has been changed.' );
			}
			return trim( $result );
		}

		return $base;
	}

}
