<?php

declare( strict_types = 1 );

namespace EntitySchema\Domain\Storage;

use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
interface IdGenerator {

	/**
	 * @throws RuntimeException
	 */
	public function getNewId(): int;

}
