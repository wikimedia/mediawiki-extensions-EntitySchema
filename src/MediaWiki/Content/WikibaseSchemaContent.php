<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Html;
use JsonContent;
use ParserOptions;
use ParserOutput;
use Title;

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
			$output->setText( $this->schemaJsonToHtml(
				json_decode( $this->getText(), true )
			) );
		} else {
			$output->setText( '' );
		}
	}

	private function schemaJsonToHtml( array $schema ) {
		$schema = array_merge( [
			'labels' => [
				'en' => '',
			],
			'descriptions' => [
				'en' => '',
			],
			'aliases' => [
				'en' => [],
			],
			'schema' => '',
		], $schema );

		return Html::element(
				'h1',
				[
					'id' => 'wbschema-title-label'
				],
				$schema[ 'labels' ][ 'en' ]
			) .
			Html::element(
				'abstract',
				[
					'id' => 'wbschema-heading-description'
				],
				$schema[ 'descriptions' ][ 'en' ]
			) .
			Html::element(
				'p',
				[
					'id' => 'wbschema-heading-aliases'
				],
				implode( ' | ', $schema[ 'aliases' ][ 'en' ] )
			)
			. Html::element(
				'pre',
				[
					'id' => 'wbschema-schema-shexc'
				],
				$schema[ 'schema' ]
			);
	}

	public function setNativeData( $data ) {
		$this->mText = $data;
	}

}
