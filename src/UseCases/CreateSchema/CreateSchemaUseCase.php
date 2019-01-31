<?php

namespace Wikibase\Schema\UseCases\CreateSchema;

use Wikibase\Schema\DataModel\Schema;
use Wikibase\Schema\DataModel\SchemaRepository;

/**
 * @license GPL-2.0-or-later
 */
class CreateSchemaUseCase {

	private $schemaRepository;

	public function __construct( SchemaRepository $schemaRepository ) {
		$this->schemaRepository = $schemaRepository;
	}

	public function createSchema( CreateSchemaRequest $request ): CreateSchemaResponse {
		$schema = $this->newSchemaFromRequest( $request );
		$id = $this->schemaRepository->storeSchema( $schema );
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
