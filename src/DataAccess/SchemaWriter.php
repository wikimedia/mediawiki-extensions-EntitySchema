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
	 * @param string $schemaContent
	 *
	 * @return SchemaId id of inserted Schema
	 */
	public function insertSchema(
		$language,
		$label,
		$description,
		array $aliases,
		$schemaContent
	): SchemaId;

	/**
	 * Update a Schema with new content. This will remove existing schema content.
	 *
	 * @param SchemaId $id
	 * @param string[] $labels
	 * @param string[] $descriptions
	 * @param string[] $aliasGroups
	 * @param string $schemaContent
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
		$schemaContent,
		Message $message = null
	);

	/**
	 * @param SchemaId $id
	 * @param string   $langCode
	 * @param string   $label
	 * @param string   $description
	 * @param string[] $aliases
	 */
	public function updateSchemaNameBadge(
		SchemaId $id,
		$langCode,
		$label,
		$description,
		array $aliases
	);

	/**
	 * @param SchemaId $id
	 * @param string $schemaContent
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 */
	public function updateSchemaContent(
		SchemaId $id,
		$schemaContent
	);

}
