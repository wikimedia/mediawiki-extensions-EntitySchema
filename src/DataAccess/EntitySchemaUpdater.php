<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Model\EntitySchemaId;
use MediaWiki\CommentStore\CommentStoreComment;

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
	 */
	public function overwriteWholeSchema(
		EntitySchemaId $id,
		array $labels,
		array $descriptions,
		array $aliasGroups,
		string $schemaText,
		int $baseRevId,
		CommentStoreComment $summary
	): EntitySchemaStatus;

	/**
	 * @param EntitySchemaId $id
	 * @param string $langCode
	 * @param string $label
	 * @param string $description
	 * @param string[] $aliases
	 * @param int $baseRevId
	 */
	public function updateSchemaNameBadge(
		EntitySchemaId $id,
		string $langCode,
		string $label,
		string $description,
		array $aliases,
		int $baseRevId
	): EntitySchemaStatus;

	/**
	 * @param EntitySchemaId $id
	 * @param string $schemaText
	 * @param int $baseRevId id of the base revision for detecting edit conflicts.
	 * @param string|null $userSummary
	 */
	public function updateSchemaText(
		EntitySchemaId $id,
		string $schemaText,
		int $baseRevId,
		?string $userSummary = null
	): EntitySchemaStatus;

}
