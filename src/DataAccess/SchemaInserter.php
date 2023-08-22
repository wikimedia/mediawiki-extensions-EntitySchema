<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use EntitySchema\Domain\Model\EntitySchemaId;

/**
 * @license GPL-2.0-or-later
 */
interface SchemaInserter {

	/**
	 * @param string $language
	 * @param string $label
	 * @param string $description
	 * @param string[] $aliases
	 * @param string $schemaText
	 *
	 * @return EntitySchemaId id of inserted Schema
	 */
	public function insertSchema(
		string $language,
		string $label,
		string $description,
		array $aliases,
		string $schemaText
	): EntitySchemaId;

}
