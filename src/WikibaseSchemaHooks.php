<?php

namespace Wikibase\Schema;

use DatabaseUpdater;
use SkinTemplate;

/**
 * Hooks utilized by the WikibaseSchema extension
 */
final class WikibaseSchemaHooks {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onCreateDBSchema( DatabaseUpdater $updater ) {
		$updater->addExtensionTable(
			'wbschema_id_counter',
			__DIR__ . '/../sql/WikibaseSchema.sql'
		);

		$updater->modifyExtensionField(
			'page',
			'page_namespace',
			__DIR__ . '/../sql/patch-move-page-namespace.sql'
		);
	}

	public static function onExtensionTypes( array &$extTypes ) {
		$extTypes['wikibase'] = 'Wikibase';
	}

	public static function onSkinTemplateNavigation( SkinTemplate &$skinTemplate, array &$links ) {
		$title = $skinTemplate->getRelevantTitle();
		if ( !$title->inNamespace( NS_WBSCHEMA_JSON ) ) {
			return true;
		}

		if ( $title->getLength() != 0 ) {
			return true;
		}

		unset( $links['views']['edit'] );
	}

}
