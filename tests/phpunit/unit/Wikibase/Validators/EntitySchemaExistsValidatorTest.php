<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Validators;

use DataValues\StringValue;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use MediaWiki\Title\TitleFactory;
use MediaWikiUnitTestCase;
use Title;

/**
 * @covers \EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaExistsValidatorTest extends MediaWikiUnitTestCase {

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( !defined( 'NS_ENTITYSCHEMA_JSON' ) ) {
			// defined through extension.json, which is not loaded in unit tests
			define( 'NS_ENTITYSCHEMA_JSON', 640 );
		}
	}

	public function testValidateExisting(): void {
		$id = 'E123';
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'makeTitleSafe' )
			->with( NS_ENTITYSCHEMA_JSON, $id )
			->willReturn(
				$this->createConfiguredMock( Title::class, [ 'exists' => true ] )
			);

		$validator = new EntitySchemaExistsValidator( $titleFactory );
		$result = $validator->validate( new StringValue( $id ) );

		$this->assertTrue( $result->isValid() );
	}

	public function testValidateNotExisting(): void {
		$id = 'E123';
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'makeTitleSafe' )
			->with( NS_ENTITYSCHEMA_JSON, $id )
			->willReturn(
				$this->createConfiguredMock( Title::class, [ 'exists' => false ] )
			);

		$validator = new EntitySchemaExistsValidator( $titleFactory );
		$result = $validator->validate( new StringValue( $id ) );

		$this->assertFalse( $result->isValid() );
		$error = $result->getErrors()[0];
		$this->assertSame( 'no-such-entity-schema', $error->getCode() );
		$this->assertSame( [ $id ], $error->getParameters() );
	}

	public function testValidateInvalid(): void {
		$id = '#';
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'makeTitleSafe' )
			->with( NS_ENTITYSCHEMA_JSON, $id )
			->willReturn( null );

		$validator = new EntitySchemaExistsValidator( $titleFactory );
		$result = $validator->validate( new StringValue( $id ) );

		$this->assertFalse( $result->isValid() );
		$error = $result->getErrors()[0];
		$this->assertSame( 'no-such-entity-schema', $error->getCode() );
		$this->assertSame( [ $id ], $error->getParameters() );
	}

}
