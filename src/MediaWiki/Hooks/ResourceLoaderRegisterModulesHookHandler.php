<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use Wikibase\Lib\SettingsArray;

/**
 * @license GPL-2.0-or-later
 */
class ResourceLoaderRegisterModulesHookHandler implements ResourceLoaderRegisterModulesHook {

	private bool $entitySchemaIsRepo;
	private ?SettingsArray $settings;

	public function __construct( bool $entitySchemaIsRepo, ?SettingsArray $settings ) {
		$this->settings = $settings;
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
	}

	public function onResourceLoaderRegisterModules( ResourceLoader $rl ): void {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}
		$localBasePath = dirname( __DIR__, 3 ) . '/resources/';

		$rl->register( [
			'ext.EntitySchema.view' => [
				'styles' => [
					'viewEntitySchema.less',
				],
				'localBasePath' => $localBasePath,
				'remoteExtPath' => 'EntitySchema/resources',
			],
			'ext.EntitySchema.special.setEntitySchemaLabelDescriptionAliases.edit' => [
				'scripts' => [
					'special.setEntitySchemaLabelDescriptionAliases.edit.js',
				],
				'dependencies' => [
					'oojs-ui-widgets',
					'mediawiki.widgets.visibleLengthLimit',
				],
				'localBasePath' => $localBasePath,
				'remoteExtPath' => 'EntitySchema/resources',
			],
			'ext.EntitySchema.special.newEntitySchema' => [
				'scripts' => [
					'special.newEntitySchema.js',
				],
				'dependencies' => [
					'oojs-ui-widgets',
					'mediawiki.widgets.visibleLengthLimit',
				],
				'localBasePath' => $localBasePath,
				'remoteExtPath' => 'EntitySchema/resources',
			],
			'ext.EntitySchema.action.edit' => [
				'scripts' => [
					'action.edit.js',
				],
				'dependencies' => [
					'oojs-ui-widgets',
					'mediawiki.widgets.visibleLengthLimit',
				],
				'localBasePath' => $localBasePath,
				'remoteExtPath' => 'EntitySchema/resources',
			],
			'ext.EntitySchema.action.view.trackclicks' => [
				'scripts' => [
					'action.view.trackclicks.js',
				],
				'localBasePath' => $localBasePath,
				'remoteExtPath' => 'EntitySchema/resources',
			],
			'ext.EntitySchema.experts.EntitySchema' => [
				'scripts' => [
					'experts.EntitySchema.js',
				],
				'dependencies' => [
					'jquery.valueview.Expert',
					'wikibase.experts.Entity',
				],
				'localBasePath' => $localBasePath,
				'remoteExtPath' => 'EntitySchema/resources',
			],
		] );

		if ( $this->settings ) {
			// temporarily register this RL module only if the feature flag for mobile editing or its beta feature are
			// enabled, so that wikis without either feature flag don't even pay the small cost of loading the module
			// *definition* (when the feature stabilizes, this should move into repo/resources/Resources.php: T395783)
			if (
				$this->settings->getSetting( 'tmpMobileEditingUI' ) ||
				$this->settings->getSetting( 'tmpEnableMobileEditingUIBetaFeature' )
			) {
				$rl->register( [ 'entitySchema.wbui2025.entityViewInit' =>
					[
						'localBasePath' => $localBasePath,
						'remoteExtPath' => 'EntitySchema/resources',
						'packageFiles' => [
							'entitySchema.wbui2025.entityViewInit.js',
						],
						'dependencies' => [
							'wikibase.wbui2025.lib',
						],
					],
				] );
			}
		}
	}

}
