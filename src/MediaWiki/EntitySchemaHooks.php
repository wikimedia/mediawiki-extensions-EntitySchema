<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki;

use DatabaseUpdater;
use SkinTemplate;

/**
 * Hooks utilized by the EntitySchema extension
 *
 * @license GPL-2.0-or-later
 */
final class EntitySchemaHooks {

	public static function onCreateDBSchema( DatabaseUpdater $updater ): void {
		$updater->addExtensionTable(
			'entityschema_id_counter',
			dirname( __DIR__, 2 ) . "/sql/{$updater->getDB()->getType()}/tables-generated.sql"
		);
	}

	public static function onSkinTemplateNavigationUniversal( SkinTemplate $skinTemplate, array &$links ): void {
		$title = $skinTemplate->getRelevantTitle();
		if ( !$title->inNamespace( NS_ENTITYSCHEMA_JSON ) ) {
			return;
		}

		unset( $links['views']['edit'] );
	}
}
