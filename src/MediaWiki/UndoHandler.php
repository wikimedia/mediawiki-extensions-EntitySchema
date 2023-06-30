<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki;

use Diff\DiffOp\Diff\Diff;
use Diff\Patcher\PatcherException;
use DomainException;
use EntitySchema\Domain\Model\SchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\Diff\SchemaDiffer;
use EntitySchema\Services\Diff\SchemaPatcher;
use EntitySchema\Services\SchemaConverter\SchemaConverter;
use Status;

/**
 * @license GPL-2.0-or-later
 */
final class UndoHandler {

	/**
	 * @throws DomainException
	 */
	public function validateContentIds(
		EntitySchemaContent $undoFromContent,
		EntitySchemaContent $undoToContent,
		EntitySchemaContent $baseContent = null
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
		EntitySchemaContent $undoFromContent,
		EntitySchemaContent $undoToContent
	): Status {

		$differ = new SchemaDiffer();
		$converter = new SchemaConverter();
		$diff = $differ->diffSchemas(
			$converter->getFullArraySchemaData( $undoFromContent->getText() ),
			$converter->getFullArraySchemaData( $undoToContent->getText() )
		);

		return Status::newGood( $diff );
	}

	public function tryPatching( Diff $diff, EntitySchemaContent $baseContent ): Status {

		$patcher = new SchemaPatcher();
		$converter = new SchemaConverter();

		try {
			$patchedSchema = $patcher->patchSchema(
				$converter->getFullArraySchemaData( $baseContent->getText() ),
				$diff
			);
		} catch ( PatcherException $e ) {
			// show error here
			return Status::newFatal( 'entityschema-undo-cannot-apply-patch' );
		}

		return Status::newGood( $patchedSchema );
	}

}
