<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Content;

use EntitySchema\Services\SchemaConverter\SchemaConverter;
use JsonContent;

/**
 * Represents the content of a EntitySchema page
 *
 * @license GPL-2.0-or-later
 */
class EntitySchemaContent extends JsonContent {

	public const CONTENT_MODEL_ID = 'EntitySchema';

	public function __construct( string $text, string $modelId = self::CONTENT_MODEL_ID ) {
		parent::__construct( $text, $modelId );
	}

	public function getTextForSearchIndex(): string {
		$converter = new SchemaConverter();
		$schemaData = $converter->getFullViewSchemaData( $this->getText(), [] );
		$textForSearchIndex = '';

		foreach ( $schemaData->nameBadges as $nameBadge ) {
			if ( $nameBadge->label ) {
				$textForSearchIndex .= $nameBadge->label . "\n";
			}
			if ( $nameBadge->description ) {
				$textForSearchIndex .= $nameBadge->description . "\n";
			}
			if ( $nameBadge->aliases ) {
				$textForSearchIndex .= implode( ', ', $nameBadge->aliases ) . "\n";
			}
		}
		return $textForSearchIndex . $schemaData->schemaText;
	}

}
