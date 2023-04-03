<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use Config;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseDataTypesHandler {

	public Config $settings;

	public function __construct( Config $settings ) {
		$this->settings = $settings;
	}

	public function onWikibaseRepoDataTypes( array &$dataTypeDefinitions ): void {
		if ( !$this->settings->get( 'EntitySchemaEnableDatatype' ) ) {
			return;
		}
		$dataTypeDefinitions['PT:entity-schema'] = [
			'value-type' => 'string',
		];
	}
}
