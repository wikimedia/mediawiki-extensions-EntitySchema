<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase;

use MediaWiki\Config\Config;

/**
 * @license GPL-2.0-or-later
 */
class FeatureConfiguration {

	private Config $config;

	public function __construct(
		Config $config
	) {
		$this->config = $config;
	}

	public function entitySchemaDataTypeEnabled(): bool {
		return defined( 'MW_QUIBBLE_CI' ) || $this->config->get( 'EntitySchemaEnableDatatype' );
	}
}
