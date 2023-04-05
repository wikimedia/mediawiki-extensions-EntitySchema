<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Formatters;

use DataValues\StringValue;
use EntitySchema\DataAccess\LabelLookup;
use InvalidArgumentException;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleFactory;
use TitleValue;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\Lib\Formatters\SnakFormat;
use Wikibase\Lib\Formatters\SnakFormatter;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaFormatter implements ValueFormatter {

	private string $format;
	private LinkRenderer $linkRenderer;

	private LabelLookup $labelLookup;

	private FormatterOptions $options;
	private TitleFactory $titleFactory;

	public function __construct(
		string $format,
		FormatterOptions $options,
		LinkRenderer $linkRenderer,
		LabelLookup $labelLookup,
		TitleFactory $titleFactory
	) {
		$this->format = $format;
		$this->linkRenderer = $linkRenderer;
		$this->labelLookup = $labelLookup;
		$this->options = $options;
		$this->titleFactory = $titleFactory;
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
		$linkTarget = new TitleValue( NS_ENTITYSCHEMA_JSON, $entitySchemaId );
		$schemaPageIdentity = $this->titleFactory->newFromText( $entitySchemaId, NS_ENTITYSCHEMA_JSON );

		if ( $schemaPageIdentity === null ) {
			return $this->linkRenderer->makePreloadedLink(
				$linkTarget,
				$entitySchemaId
			);
		}

		$requestedLanguageCode = $this->options->getOption( ValueFormatter::OPT_LANG );
		$labelTerm = $this->labelLookup->getLabelForTitle(
			$schemaPageIdentity,
			$requestedLanguageCode
		);

		if ( $labelTerm ) {
			$linkHtml = $this->linkRenderer->makePreloadedLink(
				$linkTarget,
				$labelTerm->getText(),
				'',
				[
					'lang' => $labelTerm->getLanguageCode(),
				]
			);

			return $linkHtml;
		}

		return $this->linkRenderer->makePreloadedLink(
			$linkTarget,
			$entitySchemaId
		);
	}
}
