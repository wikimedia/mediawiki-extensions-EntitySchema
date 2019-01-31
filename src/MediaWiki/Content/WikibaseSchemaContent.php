<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Html;
use JsonContent;
use ParserOptions;
use ParserOutput;
use Title;
use Wikibase\Schema\DataModel\Schema;
use Wikibase\Schema\Deserializers\DeserializerFactory;
use Wikibase\Schema\Serializers\SerializerFactory;

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
				json_decode( $this->getText(), true )
			) );
		} else {
			$output->setText( '' );
		}
	}

	private function schemaSerializationToHtml( array $schemaSerialization ) {
		$deserializer = DeserializerFactory::newSchemaDeserializer();
		$schema = $deserializer->deserialize( $schemaSerialization );

		return Html::element(
				'h1',
				[
					'id' => 'wbschema-title-label',
				],
				$schema->getLabel( 'en' )->getText()
			) .
			Html::element(
				'abstract',
				[
					'id' => 'wbschema-heading-description',
				],
				$schema->getDescription( 'en' )->getText()
			) .
			Html::element(
				'p',
				[
					'id' => 'wbschema-heading-aliases',
				],
				implode( ' | ', $schema->getAliasGroup( 'en' )->getAliases() )
			)
			. Html::element(
				'pre',
				[
					'id' => 'wbschema-schema-shexc',
				],
				$schema->getSchema()
			);
	}

	/**
	 * @param Schema $schema
	 */
	public function setContentFromSchema( Schema $schema ) {
		$serializer = SerializerFactory::newSchemaSerializer();
		$this->mText = json_encode( $serializer->serialize( $schema ) );
	}

}
