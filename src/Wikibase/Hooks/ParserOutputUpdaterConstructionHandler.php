<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

use EntitySchema\Wikibase\ParserOutputUpdater\EntitySchemaStatementDataUpdater;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Repo\ParserOutput\CompositeStatementDataUpdater;

/**
 * @license GPL-2.0-or-later
 */
class ParserOutputUpdaterConstructionHandler {

	private PropertyDataTypeLookup $propertyDataTypeLookup;

	public function __construct( PropertyDataTypeLookup $propertyDataTypeLookup ) {
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
	}

	/**
	 * Callback for the WikibaseRepoOnParserOutputUpdaterConstruction hook.
	 */
	public function onWikibaseRepoOnParserOutputUpdaterConstruction(
		CompositeStatementDataUpdater $statementUpdater,
		array &$entityUpdaters
	): void {
		$statementUpdater->addUpdater(
			new EntitySchemaStatementDataUpdater(
				$this->propertyDataTypeLookup
			)
		);
	}
}
