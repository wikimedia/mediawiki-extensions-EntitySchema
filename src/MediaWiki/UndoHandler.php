<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki;

use Diff\DiffOp\Diff\Diff;
use Diff\Patcher\PatcherException;
use DomainException;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use EntitySchema\Services\Converter\FullArrayEntitySchemaData;
use EntitySchema\Services\Diff\EntitySchemaDiffer;
use EntitySchema\Services\Diff\EntitySchemaPatcher;
use MediaWiki\Status\Status;

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
		?EntitySchemaContent $baseContent = null
	): EntitySchemaId {
		$converter = new EntitySchemaConverter();
		$firstID = $converter->getSchemaID( $undoFromContent->getText() );
		if ( $firstID === null ||
			$firstID !== $converter->getSchemaID( $undoToContent->getText() )
		) {
			throw new DomainException( 'ID must be the same for all contents' );
		}

		if ( $baseContent !== null &&
			$firstID !== $converter->getSchemaID( $baseContent->getText() )
		) {
			throw new DomainException( 'ID must be the same for all contents' );
		}

		return new EntitySchemaId( $firstID );
	}

	/** @return Status<Diff> */
	public function getDiffFromContents(
		EntitySchemaContent $undoFromContent,
		EntitySchemaContent $undoToContent
	): Status {

		$differ = new EntitySchemaDiffer();
		$converter = new EntitySchemaConverter();
		$diff = $differ->diffSchemas(
			$converter->getFullArraySchemaData( $undoFromContent->getText() ),
			$converter->getFullArraySchemaData( $undoToContent->getText() )
		);

		return Status::newGood( $diff );
	}

	/** @return Status<FullArrayEntitySchemaData> */
	public function tryPatching( Diff $diff, EntitySchemaContent $baseContent ): Status {

		$patcher = new EntitySchemaPatcher();
		$converter = new EntitySchemaConverter();

		try {
			$patchedSchema = $patcher->patchSchema(
				$converter->getFullArraySchemaData( $baseContent->getText() ),
				$diff
			);
		} catch ( PatcherException ) {
			// show error here
			return Status::newFatal( 'entityschema-undo-cannot-apply-patch' );
		}

		return Status::newGood( $patchedSchema );
	}

}
