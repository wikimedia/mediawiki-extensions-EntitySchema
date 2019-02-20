<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Html;
use JsonContent;
use ParserOptions;
use ParserOutput;
use Title;
use Wikibase\Schema\Domain\Model\Schema;
use Wikibase\Schema\Services\SchemaDispatcher\MonolingualSchemaData;
use Wikibase\Schema\Services\SchemaDispatcher\SchemaDispatcher;

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
			$output->setText( $this->schemaSerializationToHtml(
				( new SchemaDispatcher() )
					->getMonolingualSchemaData( $this->getText(), 'en' )
			) );
		} else {
			$output->setText( '' );
		}
	}

	private function schemaSerializationToHtml( MonolingualSchemaData $schemaData ) {
		return Html::element(
				'h1',
				[
					'id' => 'wbschema-title-label',
				],
				$schemaData->nameBadge->label
			) .
			Html::element(
				'abstract',
				[
					'id' => 'wbschema-heading-description',
				],
				$schemaData->nameBadge->description
			) .
			Html::element(
				'p',
				[
					'id' => 'wbschema-heading-aliases',
				],
				implode( ' | ', $schemaData->nameBadge->aliases )
			)
			. Html::element(
				'pre',
				[
					'id' => 'wbschema-schema-shexc',
				],
				$schemaData->schema
			);
	}

}
