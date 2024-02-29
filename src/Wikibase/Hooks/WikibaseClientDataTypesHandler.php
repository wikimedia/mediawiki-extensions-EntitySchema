<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use EntitySchema\Wikibase\DataValues\EntitySchemaValueParser;
use MediaWiki\Config\Config;
use ValueFormatters\ValueFormatter;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseClientDataTypesHandler {

	private Config $settings;

	public function __construct(
		Config $settings
	) {
		$this->settings = $settings;
	}

	public function onWikibaseClientDataTypes( array &$dataTypeDefinitions ) {
		if ( !$this->settings->get( 'EntitySchemaEnableDatatype' ) ) {
			return;
		}
		$dataTypeDefinitions = array_merge(
			$dataTypeDefinitions,
			[
				'PT:entity-schema' => [
					'value-type' => 'wikibase-entityid',
					'parser-factory-callback' => fn () => new EntitySchemaValueParser(),
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
