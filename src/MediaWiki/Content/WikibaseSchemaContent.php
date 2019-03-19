<?php

namespace Wikibase\Schema\MediaWiki\Content;

use JsonContent;
use ParserOptions;
use ParserOutput;
use Title;
use Wikibase\Schema\Services\SchemaConverter\SchemaConverter;

/**
 * Represents the content of a Wikibase Schema page
 */
class WikibaseSchemaContent extends JsonContent {

	const CONTENT_MODEL_ID = 'WikibaseSchema';

	public function __construct( $text, $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct( $text, $modelId );
	}

	protected function fillParserOutput(
		Title $title,
		$revId,
		ParserOptions $options,
		$generateHtml,
		/** @noinspection ReferencingObjectsInspection */
		ParserOutput &$output
	) {

		if ( $generateHtml && $this->isValid() ) {
			$languageCode = $options->getUserLang();
			$renderer = new WikibaseSchemaSlotViewRenderer( $languageCode );
			$renderer->fillParserOutput(
				( new SchemaConverter() )
					->getFullViewSchemaData( $this->getText(), [ $languageCode ] ),
				$title,
				$output
			);
		} else {
			$output->setText( '' );
		}
	}

}
