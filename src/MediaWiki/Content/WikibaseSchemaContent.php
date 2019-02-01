<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Deserializers\Exceptions\DeserializationException;
use Html;
use JsonContent;
use ParserOptions;
use ParserOutput;
use Title;
use Wikibase\Schema\Domain\Model\Schema;
use Wikibase\Schema\Serialization\DeserializerFactory;
use Wikibase\Schema\Serialization\SerializerFactory;

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
		try {
			$schema = $deserializer->deserialize( $schemaSerialization );
		} catch ( DeserializationException $e ) {
			// FIXME remove this try catch by 2019-02-11 !
			return HTML::element( 'h1', [], 'We changed the schema. Please go to edit and resave!' )
				. HTML::element( 'div', [ 'class' => 'warning' ],
					'FIXME: Remove this workaround in WikibaseSchemaContent::schemaSerializationToHtml' );
		}
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
