<?php

declare( strict_types = 1 );

namespace EntitySchema\Presentation;

use EntitySchema\MediaWiki\Content\EntitySchemaSlotDiffRenderer;
use MediaWiki\Diff\DifferenceEngine;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Message\Message;

/**
 * @license GPL-2.0-or-later
 */
class DiffRenderer {

	private MessageLocalizer $msgLocalizer;
	private EntitySchemaSlotDiffRenderer $slotDiffRenderer;

	public function __construct(
		MessageLocalizer $msgLocalizer,
		EntitySchemaSlotDiffRenderer $slotDiffRenderer
	) {
		$this->msgLocalizer = $msgLocalizer;
		$this->slotDiffRenderer = $slotDiffRenderer;
	}

	public function renderSchemaDiffTable( string $diffRowsHTML, Message $leftSideHeading ): string {
		$diffEngine = new DifferenceEngine();
		return $diffEngine->addHeader(
			$this->slotDiffRenderer->localizeDiff( $diffRowsHTML ),
			$leftSideHeading->parse(),
			$this->msgLocalizer->msg( 'yourtext' )->parse()
		);
	}

}
