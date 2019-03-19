<?php

namespace Wikibase\Schema\MediaWiki;

use Diff\DiffOp\Diff\Diff;
use Diff\Patcher\PatcherException;
use DomainException;
use Status;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Services\Diff\SchemaDiffer;
use Wikibase\Schema\Services\Diff\SchemaPatcher;
use Wikibase\Schema\Services\SchemaConverter\SchemaConverter;

/**
 * @license GPL-2.0-or-later
 */
final class UndoHandler {

	/**
	 * @throws DomainException
	 */
	public function validateContentIds(
		WikibaseSchemaContent $undoFromContent,
		WikibaseSchemaContent $undoToContent,
		WikibaseSchemaContent $baseContent = null
	): SchemaId {
		$converter = new SchemaConverter();
		$firstID = $converter->getSchemaID( $undoFromContent->getText() );
		if ( $firstID !== $converter->getSchemaID( $undoToContent->getText() )
		) {
			throw new DomainException( 'ID must be the same for all contents' );
		}

		if ( $baseContent !== null &&
			$firstID !== $converter->getSchemaID( $baseContent->getText() )
		) {
			throw new DomainException( 'ID must be the same for all contents' );
		}

		return new SchemaId( $firstID );
	}

	public function getDiffFromContents(
		WikibaseSchemaContent $undoFromContent,
		WikibaseSchemaContent $undoToContent
	): Status {

		$differ = new SchemaDiffer();
		$converter = new SchemaConverter();
		$diff = $differ->diffSchemas(
			$converter->getFullArraySchemaData( $undoFromContent->getText() ),
			$converter->getFullArraySchemaData( $undoToContent->getText() )
		);

		return Status::newGood( $diff );
	}

	public function tryPatching( Diff $diff, WikibaseSchemaContent $baseContent ): Status {

		$patcher = new SchemaPatcher();
		$converter = new SchemaConverter();

		try {
			$patchedSchema = $patcher->patchSchema(
				$converter->getFullArraySchemaData( $baseContent->getText() ),
				$diff
			);
		} catch ( PatcherException $e ) {
			// show error here
			return Status::newFatal( 'wikibaseschema-undo-cannot-apply-patch' );
		}

		return Status::newGood( $patchedSchema );
	}

}
