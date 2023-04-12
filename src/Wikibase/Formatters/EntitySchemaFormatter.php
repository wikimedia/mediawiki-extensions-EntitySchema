<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Formatters;

use DataValues\StringValue;
use InvalidArgumentException;
use MediaWiki\Linker\LinkRenderer;
use TitleValue;
use ValueFormatters\ValueFormatter;
use Wikibase\Lib\Formatters\SnakFormat;
use Wikibase\Lib\Formatters\SnakFormatter;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaFormatter implements ValueFormatter {

	private string $format;
	private LinkRenderer $linkRenderer;

	public function __construct( string $format, LinkRenderer $linkRenderer ) {

		$this->format = $format;
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * @inheritDoc
	 */
	public function format( $value ) {
		if ( !( $value instanceof StringValue ) ) {
			throw new InvalidArgumentException( '$value must be a StringValue' );
		}

		$entitySchemaId = $value->getValue();
		$snakFormat = new SnakFormat();

		switch ( $snakFormat->getBaseFormat( $this->format ) ) {
			case SnakFormatter::FORMAT_HTML:
				return $this->makeHtmlLink( $entitySchemaId );
			// TODO: case SnakFormatter::FORMAT_WIKI:
			default:
				return $entitySchemaId;
		}
	}

	private function makeHtmlLink( string $entitySchemaId ): string {
		$title = new TitleValue( NS_ENTITYSCHEMA_JSON, $entitySchemaId );

		return $this->linkRenderer->makePreloadedLink(
			$title,
			$entitySchemaId
		);
	}
}
