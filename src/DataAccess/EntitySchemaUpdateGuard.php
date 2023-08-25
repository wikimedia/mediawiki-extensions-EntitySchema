<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use Diff\Patcher\PatcherException;
use EntitySchema\Services\Converter\EntitySchemaConverter;
use EntitySchema\Services\Converter\FullArrayEntitySchemaData;
use EntitySchema\Services\Converter\PersistenceEntitySchemaData;
use EntitySchema\Services\Diff\EntitySchemaDiffer;
use EntitySchema\Services\Diff\EntitySchemaPatcher;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaUpdateGuard {

	private EntitySchemaConverter $schemaConverter;
	private EntitySchemaDiffer $schemaDiffer;
	private EntitySchemaPatcher $schemaPatcher;

	public function __construct() {
		$this->schemaConverter = new EntitySchemaConverter();
		$this->schemaDiffer = new EntitySchemaDiffer();
		$this->schemaPatcher = new EntitySchemaPatcher();
	}

	/**
	 * @param RevisionRecord $baseRevision The revision that the user’s data is based on.
	 * @param RevisionRecord $parentRevision The parent revision returned by the PageUpdater.
	 * @param callable $schemaUpdate Function accepting a FullArraySchemaData object,
	 * updating it with the user’s data.
	 * @return PersistenceEntitySchemaData|null The data that should be stored,
	 * or null if there is nothing to do.
	 * @throws EditConflict
	 */
	public function guardSchemaUpdate(
		RevisionRecord $baseRevision,
		RevisionRecord $parentRevision,
		callable $schemaUpdate
	): ?PersistenceEntitySchemaData {
		$baseData = $this->schemaConverter->getFullArraySchemaData(
			// @phan-suppress-next-line PhanUndeclaredMethod
			$baseRevision->getContent( SlotRecord::MAIN )->getText()
		);

		$updateData = clone $baseData;
		$schemaUpdate( $updateData );

		$this->cleanupData( $baseData );
		$this->cleanupData( $updateData );
		$diff = $this->schemaDiffer->diffSchemas( $baseData, $updateData );

		if ( $diff->isEmpty() ) {
			return null;
		}

		if ( $baseRevision->getId() === $parentRevision->getId() ) {
			return $this->array2persistence( $updateData );
		}

		$parentData = $this->schemaConverter->getFullArraySchemaData(
			// @phan-suppress-next-line PhanUndeclaredMethod
			$parentRevision->getContent( SlotRecord::MAIN )->getText()
		);
		try {
			$patchedData = $this->schemaPatcher->patchSchema( $parentData, $diff );
		} catch ( PatcherException $e ) {
			throw new EditConflict( $e->getMessage(), $e->getCode(), $e );
		}

		return $this->array2persistence( $patchedData );
	}

	private function cleanupData( FullArrayEntitySchemaData $data ): void {
		EntitySchemaCleaner::cleanupParameters(
			$data->data['labels'],
			$data->data['descriptions'],
			$data->data['aliases'],
			$data->data['schemaText']
		);
	}

	// TODO this is very silly
	private function array2persistence( FullArrayEntitySchemaData $arrayData ): PersistenceEntitySchemaData {
		$persistenceData = new PersistenceEntitySchemaData();
		$persistenceData->labels = $arrayData->data['labels'];
		$persistenceData->descriptions = $arrayData->data['descriptions'];
		$persistenceData->aliases = $arrayData->data['aliases'];
		$persistenceData->schemaText = $arrayData->data['schemaText'];
		return $persistenceData;
	}

}
