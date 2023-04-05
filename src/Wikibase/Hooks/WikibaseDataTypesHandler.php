<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use Config;
use EntitySchema\Wikibase\Formatters\EntitySchemaFormatter;
use MediaWiki\Linker\LinkRenderer;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseDataTypesHandler {

	private LinkRenderer $linkRenderer;
	public Config $settings;

	public function __construct( LinkRenderer $linkRenderer, Config $settings ) {
		$this->linkRenderer = $linkRenderer;
		$this->settings = $settings;
	}

	public function onWikibaseRepoDataTypes( array &$dataTypeDefinitions ): void {
		if ( !$this->settings->get( 'EntitySchemaEnableDatatype' ) ) {
			return;
		}
		$dataTypeDefinitions['PT:entity-schema'] = [
			'value-type' => 'string',
			'formatter-factory-callback' => function ( $format ) {
				return new EntitySchemaFormatter(
					$format,
					$this->linkRenderer
				);
			},
		];
	}
}
