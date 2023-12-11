<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Model\EntitySchemaId;
use InvalidArgumentException;
use MediaWiki\CommentStore\CommentStoreComment;
use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
interface EntitySchemaUpdater {

	/**
	 * Update a Schema with new content. This will remove existing schema content.
	 *
	 * @param EntitySchemaId $id
	 * @param string[] $labels
	 * @param string[] $descriptions
	 * @param string[][] $aliasGroups
	 * @param string $schemaText
	 * @param int $baseRevId
	 * @param CommentStoreComment $summary
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 */
	public function overwriteWholeSchema(
		EntitySchemaId $id,
		array $labels,
		array $descriptions,
		array $aliasGroups,
		string $schemaText,
		int $baseRevId,
		CommentStoreComment $summary
	): void;

	/**
	 * @param EntitySchemaId $id
	 * @param string   $langCode
	 * @param string   $label
	 * @param string   $description
	 * @param string[] $aliases
	 * @param int      $baseRevId
	 */
	public function updateSchemaNameBadge(
		EntitySchemaId $id,
		string $langCode,
		string $label,
		string $description,
		array $aliases,
		int $baseRevId
	): void;

	/**
	 * @param EntitySchemaId $id
	 * @param string $schemaText
	 * @param int $baseRevId id of the base revision for detecting edit conflicts.
	 * @param string|null $userSummary
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws EditConflict if another revision has been saved after $baseRevId
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 */
	public function updateSchemaText(
		EntitySchemaId $id,
		string $schemaText,
		int $baseRevId,
		?string $userSummary = null
	): void;

}
