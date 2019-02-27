<?php

namespace Wikibase\Schema\MediaWiki;

use Diff\DiffOp\Diff\Diff;
use Diff\Patcher\PatcherException;
use Status;
use Wikibase\Schema\MediaWiki\Content\WikibaseSchemaContent;
use Wikibase\Schema\Services\Diff\SchemaDiffer;
use Wikibase\Schema\Services\Diff\SchemaPatcher;
use Wikibase\Schema\Services\SchemaDispatcher\SchemaDispatcher;

/**
 * @license GPL-2.0-or-later
 */
final class UndoHandler {

	public function getDiffFromContents(
		WikibaseSchemaContent $undoFromContent,
		WikibaseSchemaContent $undoToContent
	): Status {

		$differ = new SchemaDiffer();
		$dispatcher = new SchemaDispatcher();
		$diff = $differ->diffSchemas(
			$dispatcher->getFullArraySchemaData( $undoFromContent->getText() ),
			$dispatcher->getFullArraySchemaData( $undoToContent->getText() )
		);

		return Status::newGood( $diff );
	}

	public function tryPatching( Diff $diff, WikibaseSchemaContent $baseContent ): Status {

		$patcher = new SchemaPatcher();
		$dispatcher = new SchemaDispatcher();

		try {
			$patchedSchema = $patcher->patchSchema(
				$dispatcher->getFullArraySchemaData( $baseContent->getText() ),
				$diff
			);
		} catch ( PatcherException $e ) {
			// show error here
			return Status::newFatal( 'wikibaseschema-undo-cannot-apply-patch' );
		}

		return Status::newGood( $patchedSchema );
	}

}
