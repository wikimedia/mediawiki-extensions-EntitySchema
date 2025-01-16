<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Content;

use EntitySchema\DataAccess\LabelLookup;
use EntitySchema\MediaWiki\SpecificLanguageMessageLocalizer;
use EntitySchema\Services\Converter\FullViewEntitySchemaData;
use EntitySchema\Services\Converter\NameBadge;
use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\Language\LanguageCode;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\SyntaxHighlight\SyntaxHighlight;
use MediaWiki\Title\TitleFormatter;
use MessageLocalizer;
use Wikibase\Lib\LanguageFallbackIndicator;
use Wikibase\Lib\LanguageNameLookupFactory;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaSlotViewRenderer {

	private MessageLocalizer $messageLocalizer;

	private LinkRenderer $linkRenderer;

	private Config $config;

	private TitleFormatter $titleFormatter;

	private ?SyntaxHighlight $syntaxHighlight;

	private string $dir;

	private string $currentLangCode;

	private LabelLookup $labelLookup;

	private LanguageNameLookupFactory $languageNameLookupFactory;

	/**
	 * @param string $languageCode The language in which to render the view.
	 */
	public function __construct(
		string $languageCode,
		LabelLookup $labelLookup,
		LanguageNameLookupFactory $languageNameLookupFactory,
		?LinkRenderer $linkRenderer = null,
		?Config $config = null,
		?TitleFormatter $titleFormatter = null,
		?bool $useSyntaxHighlight = null
	) {
		$this->messageLocalizer = new SpecificLanguageMessageLocalizer( $languageCode );
		$this->linkRenderer = $linkRenderer ?: MediaWikiServices::getInstance()->getLinkRenderer();
		$this->config = $config ?: MediaWikiServices::getInstance()->getMainConfig();
		$this->titleFormatter = $titleFormatter ?: MediaWikiServices::getInstance()->getTitleFormatter();
		if ( $useSyntaxHighlight === null ) {
			$useSyntaxHighlight = ExtensionRegistry::getInstance()->isLoaded( 'SyntaxHighlight' );
		}
		$this->syntaxHighlight = $useSyntaxHighlight ?
			MediaWikiServices::getInstance()->getService( 'SyntaxHighlight.SyntaxHighlight' ) :
			null;
		$this->dir = MediaWikiServices::getInstance()->getLanguageFactory()
			->getLanguage( $languageCode )->getDir();
		$this->currentLangCode = $languageCode;
		$this->labelLookup = $labelLookup;
		$this->languageNameLookupFactory = $languageNameLookupFactory;
	}

	private function msg( string $key ): Message {
		return $this->messageLocalizer->msg( $key );
	}

	public function fillParserOutput(
		FullViewEntitySchemaData $schemaData,
		PageReference $page,
		ParserOutput $parserOutput
	): void {
		$parserOutput->addModules( [ 'ext.EntitySchema.action.view.trackclicks' ] );
		$parserOutput->addModuleStyles( [ 'ext.EntitySchema.view' ] );
		if ( $this->syntaxHighlight ) {
			$parserOutput->addModuleStyles( [ 'ext.pygments' ] );
		}
		$parserOutput->setText(
			$this->renderNameBadges( $page, $schemaData->nameBadges ) .
			$this->renderSchemaSection( $page, $schemaData->schemaText )
		);
		[ $headingHtml, $headingText ] = $this->renderHeadingToHtmlAndText( $schemaData, $page );
		$parserOutput->setExtensionData( 'entityschema-meta-tags', [ 'title' => $headingText ] );
		$parserOutput->setDisplayTitle( $headingHtml );
	}

	private function renderNameBadges( PageReference $page, array $nameBadges ): string {
		$html = Html::openElement( 'table', [ 'class' => 'wikitable' ] );
		$html .= $this->renderNameBadgeHeader();
		$html .= Html::openElement( 'tbody' );
		if ( !array_key_exists( $this->currentLangCode, $nameBadges ) ) {
			$html .= "\n";
			$html .= $this->renderNameBadge(
				new NameBadge( '', '', [] ),
				$this->currentLangCode,
				$page->getDBkey()
			);
		}
		foreach ( $nameBadges as $langCode => $nameBadge ) {
			$html .= "\n";
			$html .= $this->renderNameBadge(
				$nameBadge,
				$langCode,
				$page->getDBkey()
			);
		}
		$html .= Html::closeElement( 'tbody' );
		$html .= Html::closeElement( 'table' );
		return $html;
	}

	private function renderNameBadgeHeader(): string {
		$tableHeaders = '';
		// message keys:
		// entityschema-namebadge-header-language-code
		// entityschema-namebadge-header-label
		// entityschema-namebadge-header-description
		// entityschema-namebadge-header-aliases
		// entityschema-namebadge-header-edit
		foreach ( [ 'language-code', 'label', 'description', 'aliases', 'edit' ] as $key ) {
			$tableHeaders .= Html::rawElement(
				'th',
				[],
				$this->msg( 'entityschema-namebadge-header-' . $key )
					->parse()
			);
		}

		return Html::rawElement( 'thead', [], Html::rawElement(
			'tr',
			[],
			$tableHeaders
		) );
	}

	private function renderNameBadge( NameBadge $nameBadge, string $languageCode, string $schemaId ): string {
		$language = Html::element(
			'td',
			[],
			$languageCode
		);
		$bcp47 = LanguageCode::bcp47( $languageCode ); // 'simple' => 'en-simple' etc.
		$label = Html::element(
			'td',
			[
				'class' => 'entityschema-label',
				'lang' => $bcp47,
				'dir' => 'auto',
			],
			$nameBadge->label
		);
		$description = Html::element(
			'td',
			[
				'class' => 'entityschema-description',
				'lang' => $bcp47,
				'dir' => 'auto',
			],
			$nameBadge->description
		);
		$aliases = Html::element(
			'td',
			[
				'class' => 'entityschema-aliases',
				'lang' => $bcp47,
				'dir' => 'auto',
			],
			implode( ' | ', $nameBadge->aliases )
		);
		$editLink = $this->renderNameBadgeEditLink( $schemaId, $languageCode );
		return Html::rawElement(
			'tr',
			[],
			$language . $label . $description . $aliases . $editLink
		);
	}

	private function renderNameBadgeEditLink( string $schemaId, string $langCode ): string {
		$specialPageTitleValue = SpecialPage::getTitleValueFor(
			'SetEntitySchemaLabelDescriptionAliases',
			$schemaId . '/' . $langCode
		);

		return Html::rawElement(
			'td',
			[
				'class' => 'entityschema-edit-button',
			],
			$this->linkRenderer->makeKnownLink(
				$specialPageTitleValue,
				$this->msg( 'entityschema-edit' )->text(),
				[ 'class' => 'edit-icon' ]
			)
		);
	}

	private function renderSchemaSection( PageReference $page, string $schemaText ): string {
		$schemaSectionContent = $schemaText
			? $this->renderSchemaTextLinks( $page ) . $this->renderSchemaText( $schemaText )
			: $this->renderSchemaAddTextLink( $page );
		return Html::rawElement( 'div', [
			'id' => 'entityschema-schema-view-section',
			'class' => 'entityschema-section',
			'dir' => 'ltr',
		],
			$schemaSectionContent
		);
	}

	private function renderSchemaText( string $schemaText ): string {
		$attribs = [
			'id' => 'entityschema-schema-text',
			'class' => 'entityschema-schema-text',
		];

		if ( $this->syntaxHighlight ) {
			$highlighted = $this->syntaxHighlight->syntaxHighlight( $schemaText, 'shex' );

			if ( $highlighted->isOK() ) {
				return Html::rawElement(
					'div',
					$attribs,
					$highlighted->getValue()
				);
			}
		}

		return Html::element(
			'pre',
			$attribs,
			$schemaText
		);
	}

	private function renderSchemaTextLinks( PageReference $page ): string {
		return Html::rawElement(
			'div',
			[
				'class' => 'entityschema-schema-text-links',
				'dir' => $this->dir,
			],
			$this->renderSchemaCheckLink( $page ) .
			$this->renderSchemaEditLink( $page )
		);
	}

	private function renderSchemaCheckLink( PageReference $page ): string {
		$url = $this->config->get( 'EntitySchemaShExSimpleUrl' );
		if ( !$url ) {
			return '';
		}

		$schemaTextTitle = SpecialPage::getTitleFor( 'EntitySchemaText', $page->getDBkey() );
		$url = wfAppendQuery( $url, [
			'schemaURL' => $schemaTextTitle->getFullURL(),
		] );

		return $this->makeExternalLink(
			$url,
			$this->msg( 'entityschema-check-entities' )->parse(),
			false, // link text already escaped in ->parse()
			'',
			[ 'class' => 'entityschema-check-schema' ]
		);
	}

	/**
	 * Wrapper around {@see Linker::makeExternalLink} ensuring that the external link style
	 * is applied even though our whole output does not have class="mw-parser-output"
	 */
	private function makeExternalLink(
		$url,
		$text,
		$escape = true,
		$linktype = '',
		$attribs = [],
		$title = null
	): string {
		return Html::rawElement(
			'span',
			[ 'class' => 'mw-parser-output' ],
			Linker::makeExternalLink( $url, $text, $escape, $linktype, $attribs, $title )
		);
	}

	private function renderSchemaAddTextLink( PageReference $page ): string {
		return Html::rawElement(
			'span',
			[
				'id' => 'entityschema-edit-schema-text',
				'class' => 'entityschema-edit-button',
			],
			$this->linkRenderer->makeKnownLink(
				$page,
				$this->msg( 'entityschema-add-schema-text' )->text(),
				[ 'class' => 'add-icon' ],
				[ 'action' => 'edit' ]
			)
		);
	}

	private function renderSchemaEditLink( PageReference $page ): string {
		return Html::rawElement(
			'span',
			[
				'id' => 'entityschema-edit-schema-text',
				'class' => 'entityschema-edit-button',
			],
			$this->linkRenderer->makeKnownLink(
				$page,
				$this->msg( 'entityschema-edit' )->text(),
				[ 'class' => 'edit-icon' ],
				[ 'action' => 'edit' ]
			)
		);
	}

	private function renderHeadingToHtmlAndText( FullViewEntitySchemaData $schemaData, PageReference $page ): array {
		$label = $this->labelLookup->getLabelForSchemaData( $schemaData, $this->currentLangCode );
		if ( $label !== null ) {
			$labelElement = Html::element(
				'span', [
					'class' => 'entityschema-title-label',
					'lang' => $label->getActualLanguageCode(),
					'dir' => MediaWikiServices::getInstance()->getLanguageFactory()
						->getLanguage( $label->getActualLanguageCode() )->getDir(),
				],
				$label->getText()
			);
			$labelText = $label->getText();
			$languageFallbackIndicator = new LanguageFallbackIndicator(
				$this->languageNameLookupFactory->getForLanguageCode( $this->currentLangCode )
			);
			$languageFallbackIndicatorElement = $languageFallbackIndicator->getHtml( $label );
		} else {
			$labelText = $this->msg( 'entityschema-label-empty' )->text();
			$labelElement = Html::element(
				'span',
				[ 'class' => 'entityschema-title-label-empty' ],
				$labelText
			);
			$languageFallbackIndicatorElement = '';
		}

		$idText = $this->msg( 'parentheses' )
			->plaintextParams( $this->titleFormatter->getText( $page ) )
			->text();
		$idElement = Html::element(
			'span',
			[ 'class' => 'entityschema-title-id' ],
			$idText
		);

		$htmlTitle = Html::rawElement(
			'span',
			[ 'class' => 'entityschema-title' ],
			$labelElement . $languageFallbackIndicatorElement . ' ' . $idElement
		);
		$textTitle = $labelText . ' ' . $idText;
		return [ $htmlTitle, $textTitle ];
	}

}
