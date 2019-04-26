<?php

namespace EntitySchema\Domain\Storage;

use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
interface IdGenerator {

	/**
	 * @return int
	 *
	 * @throws RuntimeException
	 */
	public function getNewId();

}
