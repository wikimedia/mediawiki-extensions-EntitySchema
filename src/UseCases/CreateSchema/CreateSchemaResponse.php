<?php

namespace Wikibase\Schema\UseCases\CreateSchema;

use Wikibase\Schema\Domain\Model\SchemaId;

/**
 * @license GPL-2.0-or-later
 */
class CreateSchemaResponse {

	/** @var SchemaId */
	private $id;

	public static function newSuccessResponse( SchemaId $id ) {
		$response = new self();
		$response->id = $id;
		return $response;
	}

	// ToDO add failure response

	public function getId(): SchemaId {
		return $this->id;
	}

}
