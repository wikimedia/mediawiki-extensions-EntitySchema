<?php

namespace Wikibase\Schema\UseCases\CreateSchema;

/**
 * @license GPL-2.0-or-later
 */
class CreateSchemaResponse {

	private $id;

	public static function newSuccessResponse( $id ) {
		$response = new self();
		$response->id = $id;
		return $response;
	}

	// ToDO add failure response

	public function getId() {
		return $this->id;
	}

}
