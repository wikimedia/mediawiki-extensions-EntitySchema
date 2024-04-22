<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Validators;

use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Tests\Unit\EntitySchemaUnitTestCaseTrait;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator;
use InvalidArgumentException;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiUnitTestCase;

/**
 * @covers \EntitySchema\Wikibase\Validators\EntitySchemaExistsValidator
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaExistsValidatorTest extends MediaWikiUnitTestCase {
	use EntitySchemaUnitTestCaseTrait;

	public function testValidateExisting(): void {
		$id = 'E123';
		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'makeTitleSafe' )
			->with( NS_ENTITYSCHEMA_JSON, $id )
			->willReturn(
				$this->createConfiguredMock( Title::class, [ 'exists' => true ] )
			);

		$validator = new EntitySchemaExistsValidator( $titleFactory );
		$result = $validator->validate( new EntitySchemaValue( new EntitySchemaId( $id ) ) );

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
		$result = $validator->validate( new EntitySchemaValue( new EntitySchemaId( $id ) ) );

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
		$this->expectException( InvalidArgumentException::class );
		$validator->validate( new EntitySchemaValue( new EntitySchemaId( $id ) ) );
	}

}
