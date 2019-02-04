<?php

namespace Wikibase\Schema\UseCases\CreateSchema;

use Wikibase\Schema\Domain\Model\Schema;
use Wikibase\Schema\Domain\Model\SchemaId;
use Wikibase\Schema\Domain\Storage\SchemaRepository;
use Wikibase\Schema\SqlIdGenerator;

/**
 * @license GPL-2.0-or-later
 */
class CreateSchemaUseCase {

	/** @var SchemaRepository */
	private $schemaRepository;
	/** @var SqlIdGenerator */
	private $idGenerator;

	public function __construct( SchemaRepository $schemaRepository, SqlIdGenerator $idGenerator ) {
		$this->schemaRepository = $schemaRepository;
		$this->idGenerator = $idGenerator;
	}

	public function createSchema( CreateSchemaRequest $request ): CreateSchemaResponse {
		$schema = $this->newSchemaFromRequest( $request );

		$id = new SchemaId( 'O' . $this->idGenerator->getNewId() );
		$schema->setId( $id );

		$this->schemaRepository->storeSchema( $schema );

		return CreateSchemaResponse::newSuccessResponse( $id );
	}

	private function newSchemaFromRequest( CreateSchemaRequest $request ): Schema {
		$schema = new Schema();
		$schema->setLabel( $request->getLanguageCode(), $request->getLabel() );
		$schema->setDescription( $request->getLanguageCode(), $request->getDescription() );
		$schema->setAliases( $request->getLanguageCode(), $request->getAliases() );
		$schema->setSchema( $request->getSchema() );

		return $schema;
	}

}
