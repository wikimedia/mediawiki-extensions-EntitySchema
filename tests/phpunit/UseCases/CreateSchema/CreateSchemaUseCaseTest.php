<?php

namespace Wikibase\Schema\Tests\UseCases\CreateSchema;

use MediaWikiTestCase;
use Wikibase\Schema\Domain\Storage\IdGenerator;
use Wikibase\Schema\Domain\Storage\SchemaRepository;
use Wikibase\Schema\UseCases\CreateSchema\CreateSchemaRequest;
use Wikibase\Schema\UseCases\CreateSchema\CreateSchemaUseCase;

/**
 * @covers \Wikibase\Schema\UseCases\CreateSchema\CreateSchemaUseCase
 * @covers \Wikibase\Schema\UseCases\CreateSchema\CreateSchemaRequest
 * @covers \Wikibase\Schema\UseCases\CreateSchema\CreateSchemaResponse
 *
 * @license GPL-2.0-or-later
 */
class CreateSchemaUseCaseTest extends MediaWikiTestCase {

	public function testGivenValidRequest_idIsReturned() {
		$schemaRepository = $this->getMockBuilder( SchemaRepository::class )
			->getMock();
		$schemaRepository->expects( $this->once() )
			->method( 'storeSchema' );
		$idGenerator = $this->getMockBuilder( IdGenerator::class )
			->disableOriginalConstructor()
			->getMock();
		$idGenerator->expects( $this->once() )
			->method( 'getNewId' )
			->willReturn( 1 );
		$usecase = new CreateSchemaUseCase(
			$schemaRepository,
			$idGenerator
		);
		$request = new CreateSchemaRequest();
		$request->setLanguageCode( 'en' );
		$request->setLabel( 'testlabel' );

		$actualResponse = $usecase->createSchema( $request );

		$this->assertSame( 'O1', $actualResponse->getId()->getId() );
	}

}
