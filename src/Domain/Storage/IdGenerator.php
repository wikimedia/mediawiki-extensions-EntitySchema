<?php

namespace Wikibase\Schema\Domain\Storage;

/**
 * @license GPL-2.0-or-later
 */
interface IdGenerator {

	/**
	 * @return int
	 */
	public function getNewId();

}
