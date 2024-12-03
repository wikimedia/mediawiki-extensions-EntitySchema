<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use ValueFormatters\ValueFormatter;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseClientDataTypesHandler {

	public function onWikibaseClientDataTypes( array &$dataTypeDefinitions ) {
		$dataTypeDefinitions = array_merge(
			$dataTypeDefinitions,
			[
				'PT:entity-schema' => [
					'value-type' => 'wikibase-entityid',
					'deserializer-builder' => EntitySchemaValue::class,
					'formatter-factory-callback' => fn () => new class implements ValueFormatter {
						/** @inheritDoc */
						public function format( $value ) {
							return "Entity schema not supported yet ({$value->getSchemaId()})";
						}
					},
				],
			]
		);
	}

}
