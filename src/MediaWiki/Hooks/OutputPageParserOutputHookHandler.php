<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\ParserOutput;

/**
 * @license GPL-2.0-or-later
 */
class OutputPageParserOutputHookHandler implements OutputPageParserOutputHook {

	/**
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$meta = $parserOutput->getExtensionData( 'entityschema-meta-tags' );
		if ( $meta ) {
			$outputPage->setProperty(
				'entityschema-meta-tags',
				$meta
			);
		}
	}
}
