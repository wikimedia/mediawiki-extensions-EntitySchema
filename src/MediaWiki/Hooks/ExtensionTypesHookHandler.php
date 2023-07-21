<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use MediaWiki\Hook\ExtensionTypesHook;

/**
 * @license GPL-2.0-or-later
 */
class ExtensionTypesHookHandler implements ExtensionTypesHook {

	/**
	 * @inheritDoc
	 */
	public function onExtensionTypes( &$extTypes ) {
		if ( !isset( $extTypes['wikibase'] ) ) {
			$extTypes['wikibase'] = 'Wikibase';
		}
	}
}
