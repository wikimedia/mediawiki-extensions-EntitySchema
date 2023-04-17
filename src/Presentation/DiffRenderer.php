<?php

declare( strict_types = 1 );

namespace EntitySchema\Presentation;

use DifferenceEngine;
use Message;
use MessageLocalizer;

/**
 * @license GPL-2.0-or-later
 */
class DiffRenderer {

	private MessageLocalizer $msgLocalizer;

	public function __construct( MessageLocalizer $msgLocalizer ) {
		$this->msgLocalizer = $msgLocalizer;
	}

	public function renderSchemaDiffTable( string $diffRowsHTML, Message $leftSideHeading ): string {
		$diffEngine = new DifferenceEngine();
		return $diffEngine->addHeader(
			$diffEngine->localiseLineNumbers( $diffRowsHTML ),
			$leftSideHeading->parse(),
			$this->msgLocalizer->msg( 'yourtext' )->parse()
		);
	}

}
