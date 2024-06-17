<?php

declare( strict_types = 1 );

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace EntitySchema\MediaWiki;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;

/**
 * Hooks utilized by the EntitySchema extension
 *
 * @license GPL-2.0-or-later
 */
final class EntitySchemaHooks implements SkinTemplateNavigation__UniversalHook {

	private bool $entitySchemaIsRepo;

	public function __construct( bool $entitySchemaIsRepo ) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
	}

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
