<?php

declare( strict_types = 1 );

namespace EntitySchema\DataAccess;

use MediaWiki\Status\Status;
use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
class HookRunnerFailureException extends RuntimeException {

	private Status $status;

	public function __construct( Status $status ) {
		parent::__construct( $status->getWikiText() );
		$this->status = $status;
	}

	public function getStatus(): Status {
		return $this->status;
	}

}
