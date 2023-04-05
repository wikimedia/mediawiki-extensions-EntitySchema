<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use DataValues\StringValue;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\Wikibase\Formatters\EntitySchemaFormatter;
use EntitySchema\Wikibase\Hooks\WikibaseDataTypesHandler;
use EntitySchema\Wikibase\Rdf\EntitySchemaRdfBuilder;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use HashConfig;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleFactory;
use MediaWikiUnitTestCase;
use ValueFormatters\FormatterOptions;
use ValueValidators\Result;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\ValidatorBuilders;
use Wikibase\Repo\Validators\CompositeValidator;
use Wikimedia\Purtle\RdfWriter;

/**
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseDataTypesHandler
 * @license GPL-2.0-or-later
 */
class WikibaseDataTypesHandlerTest extends MediaWikiUnitTestCase {

	public function testOnWikibaseRepoDataTypes(): void {
		$settings = new HashConfig( [
			'EntitySchemaEnableDatatype' => true,
		] );
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );
		$stubValidatorBuilders = $this->createStub( ValidatorBuilders::class );
		$stubExistsValidator = $this->createStub( EntitySchemaExistsValidator::class );
		$stubDatabaseEntitySource = $this->createStub( DatabaseEntitySource::class );

		$sut = new WikibaseDataTypesHandler(
			$stubLinkRenderer,
			$settings,
			$this->createStub( TitleFactory::class ),
			$stubValidatorBuilders,
			$stubDatabaseEntitySource,
			$stubExistsValidator,
			$this->createStub( LabelLookup::class )
		);

		$dataTypeDefinitions = [ 'PT:wikibase-item' => [] ];
		$sut->onWikibaseRepoDataTypes( $dataTypeDefinitions );

		$this->assertArrayHasKey( 'PT:wikibase-item', $dataTypeDefinitions );
		$this->assertArrayHasKey( 'PT:entity-schema', $dataTypeDefinitions );
		$this->assertInstanceOf(
			EntitySchemaFormatter::class,
			$dataTypeDefinitions['PT:entity-schema']['formatter-factory-callback']( 'html', new FormatterOptions() )
		);
		$this->assertInstanceOf(
			EntitySchemaRdfBuilder::class,
			$dataTypeDefinitions['PT:entity-schema']['rdf-builder-factory-callback'](
				null,
				$this->createStub( RdfVocabulary::class ),
				$this->createStub( RdfWriter::class )
			)
		);
	}

	public function testOnWikibaseRepoDataTypesDoesNothingWhenDisabled(): void {
		$settings = new HashConfig( [
			'EntitySchemaEnableDatatype' => false,
		] );
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );
		$stubValidatorBuilders = $this->createStub( ValidatorBuilders::class );
		$stubExistsValidator = $this->createStub( EntitySchemaExistsValidator::class );
		$stubDatabaseEntitySource = $this->createStub( DatabaseEntitySource::class );

		$sut = new WikibaseDataTypesHandler(
			$stubLinkRenderer,
			$settings,
			$this->createStub( TitleFactory::class ),
			$stubValidatorBuilders,
			$stubDatabaseEntitySource,
			$stubExistsValidator,
			$this->createStub( LabelLookup::class )
		);

		$dataTypeDefinitions = [ 'PT:wikibase-item' => [] ];
		$sut->onWikibaseRepoDataTypes( $dataTypeDefinitions );

		$this->assertSame( [ 'PT:wikibase-item' => [] ], $dataTypeDefinitions );
	}

	/**
	 * Basic test for validating an EntitySchema ID value.
	 * Further test cases, especially invalid ones, require integration with Wikibase
	 * (instead of stubbing ValidatorBuilders) and are tested in {@link EntitySchemaDataValidatorTest}.
	 *
	 * @dataProvider provideValuesWithValidity
	 */
	public function testOnWikibaseRepoDataTypesValidator(
		string $value,
		Result $existenceResult,
		bool $isValid
	): void {
		$settings = new HashConfig( [
			'EntitySchemaEnableDatatype' => true,
		] );
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );
		$validatorBuilders = $this->createConfiguredMock( ValidatorBuilders::class, [
			'buildStringValidators' => [],
		] );
		$stubExistsValidator = $this->createStub( EntitySchemaExistsValidator::class );
		$stubExistsValidator->method( 'validate' )
			->willReturn( $existenceResult );
		$stubDatabaseEntitySource = $this->createStub( DatabaseEntitySource::class );

		$handler = new WikibaseDataTypesHandler(
			$stubLinkRenderer,
			$settings,
			$this->createStub( TitleFactory::class ),
			$validatorBuilders,
			$stubDatabaseEntitySource,
			$stubExistsValidator,
			$this->createStub( LabelLookup::class )
		);
		$dataTypeDefinitions = [];
		$handler->onWikibaseRepoDataTypes( $dataTypeDefinitions );
		$validator = new CompositeValidator(
			$dataTypeDefinitions['PT:entity-schema']['validator-factory-callback']()
		);

		$result = $validator->validate( new StringValue( $value ) );

		$this->assertSame( $isValid, $result->isValid() );
		if ( !$isValid && $value === '' ) {
			$errors = $result->getErrors();
			$this->assertCount( 1, $errors );
			$this->assertSame( 'illegal-entity-schema-title', $errors[0]->getCode() );
		}
	}

	public static function provideValuesWithValidity(): iterable {
		yield 'valid, existing' => [ 'E1', Result::newSuccess(), true ];
		yield 'invalid, no pattern match' => [ '', Result::newError( [] ), false ];
		yield 'invalid, does not exist' => [ 'E1', Result::newError( [] ), false ];
	}

}
