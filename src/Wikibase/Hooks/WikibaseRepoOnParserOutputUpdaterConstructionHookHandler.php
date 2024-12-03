<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use EntitySchema\Wikibase\ParserOutputUpdater\EntitySchemaStatementDataUpdater;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Repo\ParserOutput\CompositeStatementDataUpdater;
use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseRepoOnParserOutputUpdaterConstructionHookHandler {

	private bool $entitySchemaIsRepo;
	private ?PropertyDataTypeLookup $propertyDataTypeLookup;

	public function __construct(
		bool $entitySchemaIsRepo,
		?PropertyDataTypeLookup $propertyDataTypeLookup
	) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
		if ( $entitySchemaIsRepo ) {
			Assert::parameterType( PropertyDataTypeLookup::class, $propertyDataTypeLookup, '$propertyDataTypeLookup' );
		}
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
	}

	/**
	 * Callback for the WikibaseRepoOnParserOutputUpdaterConstruction hook.
	 */
	public function onWikibaseRepoOnParserOutputUpdaterConstruction(
		CompositeStatementDataUpdater $statementUpdater,
		array &$entityUpdaters
	): void {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}
		$statementUpdater->addUpdater(
			new EntitySchemaStatementDataUpdater(
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
				$this->propertyDataTypeLookup
			)
		);
	}
}
