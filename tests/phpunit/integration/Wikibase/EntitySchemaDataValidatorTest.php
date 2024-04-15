<?php

declare( strict_types=1 );

namespace EntitySchema\Tests\Integration\Wikibase;

use DataValues\StringValue;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\Wikibase\Hooks\WikibaseDataTypesHandler;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use MediaWiki\Config\HashConfig;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use ValueValidators\Result;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Lib\LanguageNameLookupFactory;
use Wikibase\Repo\Validators\CompositeValidator;

/**
 * Integration test for the validator factory callback of the EntitySchema data type.
 * Most of the test cases here involve Wikibase’s own string validators;
 * there is also some basic testing in the {@link WikibaseDataTypesHandlerTest} unit test.
 *
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseDataTypesHandler
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaDataValidatorTest extends MediaWikiIntegrationTestCase {

	public function testOnWikibaseRepoDataTypesValidatorValid(): void {
		$settings = new HashConfig( [
			'EntitySchemaEnableDatatype' => true,
		] );
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );
		$existsValidator = $this->createStub( EntitySchemaExistsValidator::class );
		$existsValidator->method( 'validate' )->willReturn( Result::newSuccess() );
		$stubDatabaseEntitySource = $this->createStub( DatabaseEntitySource::class );
		$handler = new WikibaseDataTypesHandler(
			$stubLinkRenderer,
			$settings,
			$this->createStub( TitleFactory::class ),
			$this->createStub( LanguageNameLookupFactory::class ),
			$stubDatabaseEntitySource,
			$existsValidator,
			$this->createStub( LabelLookup::class )
		);
		$dataTypeDefinitions = [];
		$handler->onWikibaseRepoDataTypes( $dataTypeDefinitions );
		$validator = new CompositeValidator(
			$dataTypeDefinitions['PT:entity-schema']['validator-factory-callback']()
		);

		$result = $validator->validate( new StringValue( 'E1' ) );

		$this->assertTrue( $result->isValid() );
	}

	/** @dataProvider provideInvalidValue */
	public function testOnWikibaseRepoDataTypesValidatorInvalid( $invalidValue ): void {
		$settings = new HashConfig( [
			'EntitySchemaEnableDatatype' => true,
		] );
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );
		$existsValidator = $this->createMock( EntitySchemaExistsValidator::class );
		$existsValidator->expects( $this->never() )->method( 'validate' );
		$stubDatabaseEntitySource = $this->createStub( DatabaseEntitySource::class );
		$handler = new WikibaseDataTypesHandler(
			$stubLinkRenderer,
			$settings,
			$this->createStub( TitleFactory::class ),
			$this->createStub( LanguageNameLookupFactory::class ),
			$stubDatabaseEntitySource,
			$existsValidator,
			$this->createStub( LabelLookup::class )
		);
		$dataTypeDefinitions = [];
		$handler->onWikibaseRepoDataTypes( $dataTypeDefinitions );
		$validator = new CompositeValidator(
			$dataTypeDefinitions['PT:entity-schema']['validator-factory-callback']()
		);

		$result = $validator->validate( $invalidValue );

		$this->assertFalse( $result->isValid() );
	}

	public static function provideInvalidValue(): iterable {
		yield 'non-DataValue' => [ 'E1' ];
		yield 'non-StringValue' => [ new ItemId( 'Q1' ) ];
		yield 'empty StringValue' => [ new StringValue( '' ) ];
		yield 'non-E-ID' => [ new StringValue( 'Q1' ) ];
		yield 'lowercase E' => [ new StringValue( 'e1' ) ];
		yield 'missing number' => [ new StringValue( 'E' ) ];
		yield 'number zero' => [ new StringValue( 'E0' ) ];
		yield 'negative number' => [ new StringValue( 'E-1' ) ];
		yield 'decimal number' => [ new StringValue( 'E1.0' ) ];
		yield 'number too long' => [ new StringValue( 'E12345678901234567890' ) ];
	}

}
