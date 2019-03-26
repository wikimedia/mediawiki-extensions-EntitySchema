<?php

namespace Wikibase\Schema\DataAccess;

use InvalidArgumentException;
use Message;
use RuntimeException;
use Wikibase\Schema\Domain\Model\SchemaId;

/**
 * @license GPL-2.0-or-later
 */
interface SchemaWriter {

	/**
	 * @param string $language
	 * @param string $label
	 * @param string $description
	 * @param string[] $aliases
	 * @param string $schemaText
	 *
	 * @return SchemaId id of inserted Schema
	 */
	public function insertSchema(
		$language,
		$label,
		$description,
		array $aliases,
		$schemaText
	): SchemaId;

	/**
	 * Update a Schema with new content. This will remove existing schema content.
	 *
	 * @param SchemaId $id
	 * @param string[] $labels
	 * @param string[] $descriptions
	 * @param string[] $aliasGroups
	 * @param string $schemaText
	 * @param Message|null $message
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
		Message $message = null
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
