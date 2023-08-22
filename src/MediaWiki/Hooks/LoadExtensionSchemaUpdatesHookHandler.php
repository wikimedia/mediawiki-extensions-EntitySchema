<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use DatabaseUpdater;
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
		$updater->addExtensionTable(
			'entityschema_id_counter',
			dirname( __DIR__, 3 ) . "/sql/{$updater->getDB()->getType()}/tables-generated.sql"
		);
	}
}
