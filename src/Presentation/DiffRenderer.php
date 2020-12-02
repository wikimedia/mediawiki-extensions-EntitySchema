<?php

namespace EntitySchema\Presentation;

use DifferenceEngine;
use Message;
use MessageLocalizer;

/**
 * @license GPL-2.0-or-later
 */
class DiffRenderer {

	/** @var MessageLocalizer */
	private $msgLocalizer;

	public function __construct( MessageLocalizer $msgLocalizer ) {
		$this->msgLocalizer = $msgLocalizer;
	}

	public function renderSchemaDiffTable( $diffRowsHTML, Message $leftSideHeading ) {
		$diffEngine = new DifferenceEngine();
		return $diffEngine->addHeader(
			$diffEngine->localiseLineNumbers( $diffRowsHTML ),
			$leftSideHeading->parse(),
			$this->msgLocalizer->msg( 'yourtext' )->parse()
		);
	}

}
