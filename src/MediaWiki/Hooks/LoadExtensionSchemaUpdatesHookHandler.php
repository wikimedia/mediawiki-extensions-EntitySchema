<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use DatabaseUpdater;
use ExtensionRegistry;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * @license GPL-2.0-or-later
 */
class LoadExtensionSchemaUpdatesHookHandler implements LoadExtensionSchemaUpdatesHook {

	/**
	 * This hook is called during database installation and updates.
	 * @param DatabaseUpdater $updater DatabaseUpdater subclass
	 * @return void
	 */
	public function onLoadExtensionSchemaUpdates( $updater ): void {
		global $wgEntitySchemaIsRepo;

		// Do not create ES tables when the repo is not enabled.
		if ( !$wgEntitySchemaIsRepo || !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			return;
		}

		$updater->addExtensionTable(
			'entityschema_id_counter',
			dirname( __DIR__, 3 ) . "/sql/{$updater->getDB()->getType()}/tables-generated.sql"
		);
	}
}
