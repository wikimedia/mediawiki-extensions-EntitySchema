<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki;

use SkinTemplate;

/**
 * Hooks utilized by the EntitySchema extension
 *
 * @license GPL-2.0-or-later
 */
final class EntitySchemaHooks {

	public static function onSkinTemplateNavigationUniversal( SkinTemplate $skinTemplate, array &$links ): void {
		$title = $skinTemplate->getRelevantTitle();
		if ( !$title->inNamespace( NS_ENTITYSCHEMA_JSON ) ) {
			return;
		}

		unset( $links['views']['edit'] );
	}
}
