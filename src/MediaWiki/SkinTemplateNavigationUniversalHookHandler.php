<?php

declare( strict_types = 1 );

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace EntitySchema\MediaWiki;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;

/**
 * Implementation of the `SkinTemplateNavigation__UniversalHook` handler
 *
 * @license GPL-2.0-or-later
 */
final class SkinTemplateNavigationUniversalHookHandler implements SkinTemplateNavigation__UniversalHook {

	private bool $entitySchemaIsRepo;

	public function __construct( bool $entitySchemaIsRepo ) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
	}

	/** @inheritDoc */
	public function onSkinTemplateNavigation__Universal( $skinTemplate, &$links ): void {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}

		$title = $skinTemplate->getRelevantTitle();
		if ( !$title->inNamespace( NS_ENTITYSCHEMA_JSON ) ) {
			return;
		}

		unset( $links['views']['edit'] );
	}
}
