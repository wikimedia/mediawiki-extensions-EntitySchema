<?php

namespace Wikibase\Schema\DataAccess;

use InvalidArgumentException;
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
	 * @param SchemaId $id
	 * @param string $language
	 * @param string $label
	 * @param string $description
	 * @param string[] $aliases
	 * @param string $schemaContent
	 *
	 * @throws InvalidArgumentException if bad parameters are passed
	 * @throws RuntimeException if Schema to update does not exist or saving fails
	 *
	 * Update a Schema with new content. This will remove existing schema content.
	 */
	public function updateSchema(
		SchemaId $id,
		$language,
		$label,
		$description,
		array $aliases,
		$schemaContent
	);

}
