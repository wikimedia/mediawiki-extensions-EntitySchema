<?php

namespace Wikibase\Schema\DataModel;

/**
 * @license GPL-2.0-or-later
 */
interface SchemaRepository {

	/**
	 * @param Schema $schema
	 *
	 * @return string
	 */
	public function storeSchema( Schema $schema );

}
