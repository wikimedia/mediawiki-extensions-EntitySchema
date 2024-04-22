<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Formatters;

use DataValues\StringValue;
use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\Domain\Model\EntitySchemaId;
use EntitySchema\Wikibase\DataValues\EntitySchemaValue;
use InvalidArgumentException;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleValue;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use Wikibase\Lib\Formatters\SnakFormat;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Lib\LanguageFallbackIndicator;
use Wikibase\Lib\LanguageNameLookupFactory;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaFormatter implements ValueFormatter {

	private string $format;
	private LinkRenderer $linkRenderer;

	private LabelLookup $labelLookup;

	private FormatterOptions $options;
	private TitleFactory $titleFactory;
	private LanguageNameLookupFactory $languageNameLookupFactory;

	public function __construct(
		string $format,
		FormatterOptions $options,
		LinkRenderer $linkRenderer,
		LabelLookup $labelLookup,
		TitleFactory $titleFactory,
		LanguageNameLookupFactory $languageNameLookupFactory
	) {
		$this->format = $format;
		$this->linkRenderer = $linkRenderer;
		$this->labelLookup = $labelLookup;
		$this->options = $options;
		$this->titleFactory = $titleFactory;
		$this->languageNameLookupFactory = $languageNameLookupFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function format( $value ) {
		if ( $value instanceof StringValue ) {
			$value = new EntitySchemaValue( new EntitySchemaId( $value->getValue() ) );
		}
		if ( !( $value instanceof EntitySchemaValue ) ) {
			throw new InvalidArgumentException( '$value must be a EntitySchemaValue' );
		}

		$entitySchemaId = $value->getSchemaId();
		$snakFormat = new SnakFormat();

		switch ( $snakFormat->getBaseFormat( $this->format ) ) {
			case SnakFormatter::FORMAT_HTML:
				return $this->makeHtmlLink( $entitySchemaId );
			case SnakFormatter::FORMAT_WIKI:
				return $this->makeWikiLink( $entitySchemaId );
			case SnakFormatter::FORMAT_PLAIN:
				return $this->makePlainText( $entitySchemaId );
			default:
				return $entitySchemaId;
		}
	}

	private function makePlainText( string $entitySchemaId ): string {
		$schemaPageIdentity = $this->titleFactory->newFromText( $entitySchemaId, NS_ENTITYSCHEMA_JSON );
		if ( $schemaPageIdentity === null ) {
			return $entitySchemaId;
		}
		$requestedLanguageCode = $this->options->getOption( ValueFormatter::OPT_LANG );
		$labelTerm = $this->labelLookup->getLabelForTitle(
			$schemaPageIdentity,
			$requestedLanguageCode
		);

		if ( $labelTerm ) {
			return $labelTerm->getText();
		}
		return $schemaPageIdentity->getText();
	}

	private function makeWikiLink( string $entitySchemaId ): string {
		$schemaPageIdentity = $this->titleFactory->newFromText( $entitySchemaId, NS_ENTITYSCHEMA_JSON );
		if ( $schemaPageIdentity === null ) {
			return "[[EntitySchema:$entitySchemaId]]";
		}
		$requestedLanguageCode = $this->options->getOption( ValueFormatter::OPT_LANG );
		$labelTerm = $this->labelLookup->getLabelForTitle(
			$schemaPageIdentity,
			$requestedLanguageCode
		);

		$linkTitle = 'EntitySchema:' . $entitySchemaId;
		if ( $labelTerm ) {
			return '[[' . $linkTitle . '|' . wfEscapeWikiText( $labelTerm->getText() ) . ']]';
		}
		return '[[' . $linkTitle . ']]';
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
					'lang' => $labelTerm->getActualLanguageCode(),
				]
			);
			$languageFallbackIndicator = new LanguageFallbackIndicator(
				$this->languageNameLookupFactory->getForLanguageCode( $requestedLanguageCode )
			);
			$fallbackHtml = $languageFallbackIndicator->getHtml( $labelTerm );

			return $linkHtml . $fallbackHtml;
		}

		return $this->linkRenderer->makePreloadedLink(
			$linkTarget,
			$entitySchemaId
		);
	}
}
