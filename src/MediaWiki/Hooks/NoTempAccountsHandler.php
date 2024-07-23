<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use MediaWiki\MainConfigNames;
use MediaWiki\Settings\SettingsBuilder;

/**
 * @license GPL-2.0-or-later
 */
class NoTempAccountsHandler {

	public static function disableTempAccountsInCI( array $info, SettingsBuilder $settingsBuilder ): void {
		if ( defined( 'MW_QUIBBLE_CI' ) ) {
			// temporary hack to unbreak CI (T356148, T370577)
			$settingsBuilder->putConfigValue( MainConfigNames::AutoCreateTempUser, [ 'enabled' => false ] );
		}
	}

}
