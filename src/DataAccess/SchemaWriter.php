<?php

namespace Wikibase\Schema\DataAccess;

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

}
