<?php

namespace EntitySchema\DataAccess;

use CommentStoreComment;
use EntitySchema\Domain\Model\SchemaId;
use InvalidArgumentException;
use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
interface SchemaUpdater {

	/**
	 * Update a Schema with new content. This will remove existing schema content.
	 *
	 * @param SchemaId $id
	 * @param string[] $labels
	 * @param string[] $descriptions
	 * @param string[] $aliasGroups
	 * @param string $schemaText
	 * @param int $baseRevId
	 * @param CommentStoreComment $summary
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 */
	public function overwriteWholeSchema(
		SchemaId $id,
		array $labels,
		array $descriptions,
		array $aliasGroups,
		$schemaText,
		$baseRevId,
		CommentStoreComment $summary
	);

	/**
	 * @param SchemaId $id
	 * @param string   $langCode
	 * @param string   $label
	 * @param string   $description
	 * @param string[] $aliases
	 * @param int      $baseRevId
	 */
	public function updateSchemaNameBadge(
		SchemaId $id,
		$langCode,
		$label,
		$description,
		array $aliases,
		$baseRevId
	);

	/**
	 * @param SchemaId $id
	 * @param string $schemaText
	 * @param int $baseRevId id of the base revision for detecting edit conflicts.
	 * @param string|null $userSummary
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws EditConflict if another revision has been saved after $baseRevId
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 */
	public function updateSchemaText(
		SchemaId $id,
		$schemaText,
		$baseRevId,
		$userSummary = null
	);

}
