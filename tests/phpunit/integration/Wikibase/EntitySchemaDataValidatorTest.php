<?php

declare( strict_types=1 );

namespace EntitySchema\Tests\Integration\Wikibase;

use DataValues\StringValue;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use EntitySchema\Wikibase\Hooks\WikibaseDataTypesHandler;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use MediaWiki\Config\HashConfig;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use ValueValidators\Result;
use ValueValidators\ValueValidator;
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

	private function createValidator( bool $validatesSuccessfully = true ): ValueValidator {
		$settings = new HashConfig( [
			'EntitySchemaEnableDatatype' => true,
		] );
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );
		$existsValidator = $this->createStub( EntitySchemaExistsValidator::class );
		if ( $validatesSuccessfully ) {
			$existsValidator->method( 'validate' )->willReturn( Result::newSuccess() );
		} else {
			$existsValidator->expects( $this->never() )->method( 'validate' );
		}
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
		return new CompositeValidator(
			$dataTypeDefinitions['PT:entity-schema']['validator-factory-callback']()
		);
	}

	public function testOnWikibaseRepoDataTypesValidatorValid(): void {
		$validator = $this->createValidator();

		$result = $validator->validate( new EntitySchemaValue( new EntitySchemaId( 'E1' ) ) );

		$this->assertTrue( $result->isValid() );
	}

	/** @dataProvider provideInvalidValue */
	public function testOnWikibaseRepoDataTypesValidatorInvalid( $invalidValue ): void {
		$validator = $this->createValidator( false );

		$result = $validator->validate( $invalidValue );

		$this->assertFalse( $result->isValid() );
	}

	public static function provideInvalidValue(): iterable {
		yield 'non-DataValue' => [ 'E1' ];
		yield 'non-StringValue' => [ new ItemId( 'Q1' ) ];
		yield 'empty StringValue' => [ new StringValue( '' ) ];
		yield 'valid ID but presented as string' => [ new StringValue( 'E1' ) ];
	}

}
