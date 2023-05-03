<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\ParserOutputUpdater;

use DataValues\StringValue;
use EntitySchema\Wikibase\ParserOutputUpdater\EntitySchemaStatementDataUpdater;
use MediaWiki\Linker\LinkTarget;
use MediaWikiUnitTestCase;
use ParserOutput;
use Wikibase\DataModel\Entity\NumericPropertyId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Tests\NewStatement;

/**
 * @covers \EntitySchema\Wikibase\ParserOutputUpdater\EntitySchemaStatementDataUpdater
 * @license GPL-2.0-or-later
 */
class EntitySchemaStatementDataUpdaterTest extends MediaWikiUnitTestCase {

	public function provideProcessStatement(): iterable {
		$propertyIdString = new NumericPropertyId( 'P42' );
		$propertyIdNonExistent = new NumericPropertyId( 'P404' );
		$propertyIdEntitySchema = new NumericPropertyId( 'P73505550' );

		$stringValue = new StringValue( 'E123' );
		$stringValue2 = new StringValue( 'E321' );
		$stringSnak = new PropertyValueSnak(
			$propertyIdString,
			$stringValue
		);
		$entitySchemaSnak = new PropertyValueSnak(
			$propertyIdEntitySchema,
			new StringValue( 'E123' )
		);
		$referenceString = new Reference( [ $stringSnak ] );
		$referenceEntitySchema = new Reference( [ $entitySchemaSnak ] );

		return [
			'string main snak' => [
				[],
				[
					NewStatement::forProperty( $propertyIdString )
						->withValue( $stringValue )
						->build(),
				],
			],
			'main snak with non-existent property' => [
				[],
				[
					NewStatement::forProperty( $propertyIdNonExistent )
						->withValue( $stringValue )
						->build(),
				],
			],
			'string main snak and qualifier' => [
				[],
				[
					NewStatement::forProperty( $propertyIdString )
						->withValue( $stringValue )
						->withQualifier( $propertyIdString, $stringValue )
						->build(),
				],
			],
			'string main snak, qualifier and reference' => [
				[],
				[
					NewStatement::forProperty( $propertyIdString )
						->withValue( $stringValue )
						->withQualifier( $propertyIdString, $stringValue )
						->withReference( $referenceString )
						->build(),
				],
			],
			'entity schema id as main snak' => [
				[ 'E123' ],
				[
					NewStatement::forProperty( $propertyIdEntitySchema )
						->withValue( $stringValue )
						->build(),
				],
			],
			'no value main snak' => [
				[],
				[
					NewStatement::noValueFor( $propertyIdEntitySchema )->build(),
				],
			],
			'entity schema id in qualifier' => [
				[ 'E321' ],
				[
					NewStatement::forProperty( $propertyIdString )
						->withValue( $stringValue )
						->withQualifier( $propertyIdEntitySchema, $stringValue2 )
						->build(),
				],
			],
			'same entity schema id in main snak and qualifier' => [
				[ 'E123' ],
				[
					NewStatement::forProperty( $propertyIdEntitySchema )
						->withValue( $stringValue )
						->withQualifier( $propertyIdEntitySchema, $stringValue )
						->build(),
				],
			],
			'entity schema id in reference' => [
				[ 'E123' ],
				[
					NewStatement::noValueFor( $propertyIdEntitySchema )
						->withReference( $referenceEntitySchema )
						->build(),
				],
			],
			'entity schema ids in both main snak and reference' => [
				[ 'E123', 'E321' ],
				[
					NewStatement::forProperty( $propertyIdEntitySchema )
						->withValue( $stringValue )
						->withQualifier( $propertyIdEntitySchema, $stringValue2 )
						->build(),
				],
			],
			'multiple statements' => [
				[ 'E123', 'E321' ],
				[
					NewStatement::noValueFor( $propertyIdEntitySchema )->build(),
					NewStatement::forProperty( $propertyIdString )
						->withValue( $stringValue )
						->build(),
					NewStatement::forProperty( $propertyIdEntitySchema )
						->withValue( $stringValue )
						->withQualifier( $propertyIdEntitySchema, $stringValue2 )
						->build(),
				],
			],
		];
	}

	/**
	 * @dataProvider provideProcessStatement
	 */
	public function testProcessStatement( array $expected, array $statements ): void {
		$entitySchemaStatementDataUpdater = new EntitySchemaStatementDataUpdater(
			$this->newPropertyDataTypeLookup()
		);

		$collectedIds = [];
		$parserOutput = $this->createStub( ParserOutput::class );
		$parserOutput->expects( $this->exactly( count( $expected ) ) )
			->method( 'addLink' )
			->with( $this->isInstanceOf( LinkTarget::class ) )
			->willReturnCallback( function ( LinkTarget $linkTarget ) use ( &$collectedIds ) {
				$this->assertSame( NS_ENTITYSCHEMA_JSON, $linkTarget->getNamespace() );
				$collectedIds[] = $linkTarget->getDBkey();
			} );

		foreach ( $statements as $statement ) {
			$entitySchemaStatementDataUpdater->processStatement( $statement );
		}
		$entitySchemaStatementDataUpdater->updateParserOutput( $parserOutput );
		$this->assertSame( $expected, $collectedIds );
	}

	private function newPropertyDataTypeLookup(): PropertyDataTypeLookup {
		return new class implements PropertyDataTypeLookup {

			public function getDataTypeIdForProperty( PropertyId $propertyId ) {
				if ( $propertyId->getSerialization() === 'P73505550' ) {
					return 'entity-schema';
				}
				if ( $propertyId->getSerialization() === 'P404' ) {
					throw new PropertyDataTypeLookupException( $propertyId );
				}
				return 'string';
			}

		};
	}
}
