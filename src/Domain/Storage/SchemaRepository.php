<?php

namespace Wikibase\Schema\Domain\Storage;

use Wikibase\Schema\Domain\Model\Schema;
use Wikibase\Schema\Domain\Model\SchemaId;

/**
 * @license GPL-2.0-or-later
 */
interface SchemaRepository {

	/**
	 * @param Schema $schema
	 */
	public function storeSchema( Schema $schema );

	/**
	 * @param SchemaId $id
	 * @return Schema
	 */
	public function loadSchema( SchemaId $id ): Schema;

}
