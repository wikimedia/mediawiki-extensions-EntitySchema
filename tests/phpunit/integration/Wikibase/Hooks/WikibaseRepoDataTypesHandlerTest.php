<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Integration\Wikibase\Hooks;

use DataValues\DataValue;
use DataValues\StringValue;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use EntitySchema\Wikibase\Formatters\EntitySchemaFormatter;
use EntitySchema\Wikibase\Hooks\WikibaseRepoDataTypesHandler;
use EntitySchema\Wikibase\Rdf\EntitySchemaRdfBuilder;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use ValueFormatters\FormatterOptions;
use ValueValidators\Result;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\Lib\LanguageNameLookupFactory;
use Wikibase\Repo\Rdf\RdfVocabulary;
use Wikibase\Repo\Validators\CompositeValidator;
use Wikimedia\Purtle\RdfWriter;

/**
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseRepoDataTypesHandler
 * @license GPL-2.0-or-later
 */
class WikibaseRepoDataTypesHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'WikibaseRepository' ) ) {
			$this->markTestSkipped( 'WikibaseRepo not enabled' );
		}
	}

	public function testOnWikibaseRepoDataTypes(): void {
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );
		$stubExistsValidator = $this->createStub( EntitySchemaExistsValidator::class );
		$stubDatabaseEntitySource = $this->createStub( DatabaseEntitySource::class );

		$sut = new WikibaseRepoDataTypesHandler(
			$stubLinkRenderer,
			$this->createStub( TitleFactory::class ),
			true,
			$this->createStub( LanguageNameLookupFactory::class ),
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

	/**
	 * Basic test for validating an EntitySchema ID value.
	 * Further test cases, especially invalid ones, are tested in {@link EntitySchemaDataValidatorTest}.
	 *
	 * @dataProvider provideValuesWithValidity
	 */
	public function testOnWikibaseRepoDataTypesValidator(
		DataValue $value,
		Result $existenceResult,
		bool $isValid
	): void {
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );
		$stubExistsValidator = $this->createStub( EntitySchemaExistsValidator::class );
		$stubExistsValidator->method( 'validate' )
			->willReturn( $existenceResult );
		$stubDatabaseEntitySource = $this->createStub( DatabaseEntitySource::class );

		$handler = new WikibaseRepoDataTypesHandler(
			$stubLinkRenderer,
			$this->createStub( TitleFactory::class ),
			true,
			$this->createStub( LanguageNameLookupFactory::class ),
			$stubDatabaseEntitySource,
			$stubExistsValidator,
			$this->createStub( LabelLookup::class )
		);
		$dataTypeDefinitions = [];
		$handler->onWikibaseRepoDataTypes( $dataTypeDefinitions );
		$validator = new CompositeValidator(
			$dataTypeDefinitions['PT:entity-schema']['validator-factory-callback']()
		);

		$result = $validator->validate( $value );

		$this->assertSame( $isValid, $result->isValid() );
		if ( !$isValid && $value === '' ) {
			$errors = $result->getErrors();
			$this->assertCount( 1, $errors );
			$this->assertSame( 'illegal-entity-schema-title', $errors[0]->getCode() );
		}
	}

	public static function provideValuesWithValidity(): iterable {
		yield 'valid, existing' => [
			new EntitySchemaValue( new EntitySchemaId( 'E1' ) ),
			Result::newSuccess(),
			true,
		];
		yield 'invalid, no pattern match' => [
			new StringValue( 'E2' ),
			Result::newError( [] ),
			false,
		];
		yield 'invalid, does not exist' => [
			new EntitySchemaValue( new EntitySchemaId( 'E1' ) ),
			Result::newError( [] ),
			false,
		];
	}

}
