<?php

declare( strict_types = 1 );

namespace phpunit\unit\Wikibase\Hooks;

use DataValues\StringValue;
use EntitySchema\Wikibase\Formatters\EntitySchemaFormatter;
use EntitySchema\Wikibase\Hooks\WikibaseDataTypesHandler;
use HashConfig;
use MediaWiki\Linker\LinkRenderer;
use MediaWikiUnitTestCase;
use Wikibase\Repo\ValidatorBuilders;
use Wikibase\Repo\Validators\CompositeValidator;

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

		$sut = new WikibaseDataTypesHandler( $stubLinkRenderer, $settings, $stubValidatorBuilders );

		$dataTypeDefinitions = [ 'PT:wikibase-item' => [] ];
		$sut->onWikibaseRepoDataTypes( $dataTypeDefinitions );

		$this->assertArrayHasKey( 'PT:wikibase-item', $dataTypeDefinitions );
		$this->assertArrayHasKey( 'PT:entity-schema', $dataTypeDefinitions );
		$this->assertInstanceOf(
			EntitySchemaFormatter::class,
			$dataTypeDefinitions['PT:entity-schema']['formatter-factory-callback']( 'html' )
		);
	}

	public function testOnWikibaseRepoDataTypesDoesNothingWhenDisabled(): void {
		$settings = new HashConfig( [
			'EntitySchemaEnableDatatype' => false,
		] );
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );
		$stubValidatorBuilders = $this->createStub( ValidatorBuilders::class );

		$sut = new WikibaseDataTypesHandler( $stubLinkRenderer, $settings, $stubValidatorBuilders );

		$dataTypeDefinitions = [ 'PT:wikibase-item' => [] ];
		$sut->onWikibaseRepoDataTypes( $dataTypeDefinitions );

		$this->assertSame( [ 'PT:wikibase-item' => [] ], $dataTypeDefinitions );
	}

	/**
	 * Basic test for validating an EntitySchema ID value based on the pattern.
	 * Further test cases, especially invalid ones, require integration with Wikibase
	 * (instead of stubbing ValidatorBuilders) and are tested in {@link EntitySchemaDataValidatorTest}.
	 *
	 * @dataProvider provideValuesWithValidity
	 */
	public function testOnWikibaseRepoDataTypesValidator( string $value, bool $isValid ): void {
		$settings = new HashConfig( [
			'EntitySchemaEnableDatatype' => true,
		] );
		$stubLinkRenderer = $this->createStub( LinkRenderer::class );
		$validatorBuilders = $this->createConfiguredMock( ValidatorBuilders::class, [
			'buildStringValidators' => [],
		] );
		$handler = new WikibaseDataTypesHandler( $stubLinkRenderer, $settings, $validatorBuilders );
		$dataTypeDefinitions = [];
		$handler->onWikibaseRepoDataTypes( $dataTypeDefinitions );
		$validator = new CompositeValidator(
			$dataTypeDefinitions['PT:entity-schema']['validator-factory-callback']()
		);

		$result = $validator->validate( new StringValue( $value ) );

		$this->assertSame( $isValid, $result->isValid() );
	}

	public static function provideValuesWithValidity(): iterable {
		yield 'valid' => [ 'E1', true ];
		yield 'invalid' => [ '', false ];
	}

}
