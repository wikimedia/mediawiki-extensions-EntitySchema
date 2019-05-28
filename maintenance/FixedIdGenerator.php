<?php

namespace EntitySchema\Maintenance;

use EntitySchema\Domain\Storage\IdGenerator;

/**
 * Returns a constant fixed id
 *
 * This is inteded to be used in maintenance scripts to create some predefined initial EntitySchemas
 *
 * @license GPL-2.0-or-later
 */
class FixedIdGenerator implements IdGenerator {

	private $fixId;

	public function __construct( $fixId ) {
		$this->fixId = $fixId;
	}

	/**
	 * @return int
	 */
	public function getNewId() {
		return $this->fixId;
	}

}
