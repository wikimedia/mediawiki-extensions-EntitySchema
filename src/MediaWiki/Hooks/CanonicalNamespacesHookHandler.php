<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use MediaWiki\Hook\CanonicalNamespacesHook;
use MediaWiki\MediaWikiServices;
use Wikibase\Lib\WikibaseSettings;

/**
 * @license GPL-2.0-or-later
 */
class CanonicalNamespacesHookHandler implements CanonicalNamespacesHook {

	/**
	 * Hook to register the default namespace names.
	 *
	 * @param array &$namespaces
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/CanonicalNamespaces
	 */
	public function onCanonicalNamespaces( &$namespaces ) {
		// XXX: ExtensionProcessor should define an extra config object for every extension.
		$config = MediaWikiServices::getInstance()->getMainConfig();

		// Do not register ES namespaces when the repo is not enabled.
		if ( !WikibaseSettings::isRepoEnabled() || !$config->get( 'EntitySchemaIsRepo' ) ) {
			return;
		}

		$namespaces[NS_ENTITYSCHEMA_JSON] = 'EntitySchema';
		$namespaces[NS_ENTITYSCHEMA_JSON_TALK] = 'EntitySchema_talk';
		// all other data about the namespaces lives in extension.json, this only enables them
	}
}
