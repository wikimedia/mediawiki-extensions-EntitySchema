<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

/**
 * @license GPL-2.0-or-later
 */
interface EntitySchemaInserter {

	/**
	 * @param string $language
	 * @param string $label
	 * @param string $description
	 * @param string[] $aliases
	 * @param string $schemaText
	 *
	 * @return EntitySchemaStatus
	 */
	public function insertSchema(
		string $language,
		string $label,
		string $description,
		array $aliases,
		string $schemaText
	): EntitySchemaStatus;

}
