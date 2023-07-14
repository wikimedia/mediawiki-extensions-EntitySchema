<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use MediaWiki\Hook\TitleGetRestrictionTypesHook;
use MediaWiki\Title\Title;

/**
 * @license GPL-2.0-or-later
 */
class TitleGetRestrictionTypesHookHandler implements TitleGetRestrictionTypesHook {

	/**
	 * Handler for the TitleGetRestrictionTypes hook.
	 *
	 * Implemented to prevent people from protecting pages from being
	 * created or moved in a Schema namespace (which is pointless).
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/TitleGetRestrictionTypes
	 *
	 * @param Title $title
	 * @param string[] &$types The types of protection available
	 */
	public function onTitleGetRestrictionTypes( $title, &$types ): void {
		if ( $title->getNamespace() === NS_ENTITYSCHEMA_JSON ) {
			// Remove create and move protection for Schema namespaces
			$types = array_diff( $types, [ 'create', 'move' ] );
		}
	}
}
