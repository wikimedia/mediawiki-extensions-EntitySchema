<?php

namespace Wikibase\Schema\Services\Diff;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpChange;
use Diff\DiffOp\DiffOpRemove;
use Diff\Patcher\PatcherException;

/**
 * Copied and adjusted from wikibase/data-model;
 * originally authored by Thiemo Kreuz and Jeroen De Dauw
 *
 * @license GPL-2.0-or-later
 */
class AliasGroupListPatcher {

	/**
	 * @throws PatcherException
	 */
	public function patchAliasGroupList( array $groups, Diff $patch = null ): array {
		if ( $patch === null ) {
			return $groups;
		}
		foreach ( $patch as $lang => $diffOp ) {
			$groups = $this->applyAliasGroupDiff( $groups, $lang, $diffOp );
		}

		return $groups;
	}

	/**
	 * @param array $groups
	 * @param string $lang
	 * @param Diff $patch
	 *
	 * @return array
	 */
	private function applyAliasGroupDiff( array $groups, $lang, Diff $patch ) {
		$hasLang = !empty( $groups[$lang] );

		if ( $hasLang || !$this->containsOperationsOnOldValues( $patch ) ) {
			$aliases = $hasLang ? $groups[$lang] : [];
			$aliases = $this->getPatchedAliases( $aliases, $patch );
			$groups[$lang] = $aliases;
		}

		return $groups;
	}

	/**
	 * @param Diff $diff
	 *
	 * @return bool
	 */
	private function containsOperationsOnOldValues( Diff $diff ) {
		return $diff->getChanges() !== []
			|| $diff->getRemovals() !== [];
	}

	/**
	 * @see ListPatcher
	 *
	 * @param string[] $aliases
	 * @param Diff $patch
	 *
	 * @throws PatcherException
	 * @return string[]
	 */
	private function getPatchedAliases( array $aliases, Diff $patch ) {
		foreach ( $patch as $diffOp ) {
			switch ( true ) {
				case $diffOp instanceof DiffOpAdd:
					$aliases[] = $diffOp->getNewValue();
					break;

				case $diffOp instanceof DiffOpChange:
					$key = array_search( $diffOp->getOldValue(), $aliases, true );
					if ( $key !== false ) {
						unset( $aliases[$key] );
						$aliases[] = $diffOp->getNewValue();
					}
					break;

				case $diffOp instanceof DiffOpRemove:
					$key = array_search( $diffOp->getOldValue(), $aliases, true );
					if ( $key !== false ) {
						unset( $aliases[$key] );
					}
					break;

				default:
					throw new PatcherException( 'Invalid aliases diff' );
			}
		}

		return array_values( $aliases );
	}

}
