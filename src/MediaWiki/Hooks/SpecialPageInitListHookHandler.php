<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Specials\EntitySchemaText;
use EntitySchema\MediaWiki\Specials\NewEntitySchema;
use EntitySchema\MediaWiki\Specials\SetEntitySchemaLabelDescriptionAliases;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;

/**
 * @license GPL-2.0-or-later
 */
class SpecialPageInitListHookHandler implements SpecialPage_initListHook {

	private bool $entitySchemaIsRepo;

	public function __construct( bool $entitySchemaIsRepo ) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
	}

	/**
	 * @inheritDoc
	 */
	public function onSpecialPage_initList( &$list ): void {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}
		$list['NewEntitySchema'] = [
			'class' => NewEntitySchema::class,
			'services' => [
				'TempUserConfig',
				'WikibaseRepo.Settings',
				'EntitySchema.IdGenerator',
			],
		];
		$list['EntitySchemaText'] = [
			'class' => EntitySchemaText::class,
		];
		$list['SetEntitySchemaLabelDescriptionAliases'] = [
			'class' => SetEntitySchemaLabelDescriptionAliases::class,
			'services' => [
				'TempUserConfig',
				'WikibaseRepo.Settings',
			],
		];
	}

}
