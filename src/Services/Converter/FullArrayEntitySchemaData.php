<?php

declare( strict_types = 1 );

namespace EntitySchema\Services\Converter;

/**
 * The full data of an EntitySchema, represented as a recursive array.
 *
 * 'labels' is an associative array from language code to label in that language;
 * 'descriptions' is an associative array from language code to description in that language;
 * 'aliases' is an associative array from language code to list of aliases in that language;
 * 'schemaText' is the schema text (string).
 *
 * Labels, descriptions and aliases are absent in languages where they are not defined,
 * whereas the schema string is always present (possibly as the empty string).
 *
 * @license GPL-2.0-or-later
 */
class FullArrayEntitySchemaData {

	public array $data;

	public function __construct( array $data ) {
		$this->data = $data;
	}

}
