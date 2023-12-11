<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\ParserOutputUpdater;

use MediaWiki\Title\TitleValue;
use ParserOutput;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Repo\ParserOutput\StatementDataUpdater;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaStatementDataUpdater implements StatementDataUpdater {

	/** @var bool[] */
	private array $entitySchemaIdSerializations = [];

	private PropertyDataTypeLookup $propertyDataTypeLookup;

	public function __construct(
		PropertyDataTypeLookup $propertyDataTypeLookup
	) {
		$this->propertyDataTypeLookup = $propertyDataTypeLookup;
	}

	public function processStatement( Statement $statement ): void {
		foreach ( $statement->getAllSnaks() as $snak ) {
			if ( !( $snak instanceof PropertyValueSnak ) ) {
				continue;
			}

			try {
				$dataTypeId = $this->propertyDataTypeLookup->getDataTypeIdForProperty(
					$snak->getPropertyId()
				);
			} catch ( PropertyDataTypeLookupException $e ) {
				$dataTypeId = null;
			}

			if ( $dataTypeId !== 'entity-schema' ) {
				continue;
			}
			$this->entitySchemaIdSerializations[$snak->getDataValue()->getValue()] = true;
		}
	}

	public function updateParserOutput( ParserOutput $parserOutput ): void {
		foreach ( array_keys( $this->entitySchemaIdSerializations ) as $entitySchemaIdSerialization ) {
			// Due to our data validator we can be sure that the id is a well formed db key.
			$parserOutput->addLink(
				new TitleValue( NS_ENTITYSCHEMA_JSON, $entitySchemaIdSerialization )
			);
		}
	}

}
