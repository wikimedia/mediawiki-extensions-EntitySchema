<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

/**
 * @license GPL-2.0-or-later
 */
class ResourceLoaderRegisterModulesHookHandler implements ResourceLoaderRegisterModulesHook {

	private bool $entitySchemaIsRepo;

	public function __construct( bool $entitySchemaIsRepo ) {
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
	}

}
