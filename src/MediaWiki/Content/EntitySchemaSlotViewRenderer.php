<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Content;

use Config;
use EntitySchema\MediaWiki\SpecificLanguageMessageLocalizer;
use EntitySchema\Services\Converter\FullViewSchemaData;
use EntitySchema\Services\Converter\NameBadge;
use ExtensionRegistry;
use Html;
use LanguageCode;
use Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\SyntaxHighlight\SyntaxHighlight;
use Message;
use MessageLocalizer;
use ParserOutput;
use SpecialPage;
use TitleFormatter;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaSlotViewRenderer {

	private MessageLocalizer $messageLocalizer;

	private LinkRenderer $linkRenderer;

	private Config $config;

	private TitleFormatter $titleFormatter;

	private bool $useSyntaxHighlight;

	/**
	 * @param string $languageCode The language in which to render the view.
	 */
	public function __construct(
		string $languageCode,
		LinkRenderer $linkRenderer = null,
		Config $config = null,
		TitleFormatter $titleFormatter = null,
		bool $useSyntaxHighlight = null
	) {
		$this->messageLocalizer = new SpecificLanguageMessageLocalizer( $languageCode );
		$this->linkRenderer = $linkRenderer ?: MediaWikiServices::getInstance()->getLinkRenderer();
		$this->config = $config ?: MediaWikiServices::getInstance()->getMainConfig();
		$this->titleFormatter = $titleFormatter ?: MediaWikiServices::getInstance()->getTitleFormatter();
		if ( $useSyntaxHighlight === null ) {
			$useSyntaxHighlight = ExtensionRegistry::getInstance()->isLoaded( 'SyntaxHighlight' );
		}
		$this->useSyntaxHighlight = $useSyntaxHighlight;
	}

	private function msg( string $key ): Message {
		return $this->messageLocalizer->msg( $key );
	}

	public function fillParserOutput(
		FullViewSchemaData $schemaData,
		PageReference $page,
		ParserOutput $parserOutput
	): void {
		$parserOutput->addModules( [ 'ext.EntitySchema.action.view.trackclicks' ] );
		$parserOutput->addModuleStyles( [ 'ext.EntitySchema.view' ] );
		if ( $this->useSyntaxHighlight ) {
			$parserOutput->addModuleStyles( [ 'ext.pygments' ] );
		}
		$parserOutput->setText(
			$this->renderNameBadges( $page, $schemaData->nameBadges ) .
			$this->renderSchemaSection( $page, $schemaData->schemaText )
		);
		$parserOutput->setDisplayTitle(
			$this->renderHeading( reset( $schemaData->nameBadges ), $page )
		);
	}

	private function renderNameBadges( PageReference $page, array $nameBadges ): string {
		$html = Html::openElement( 'table', [ 'class' => 'wikitable' ] );
		$html .= $this->renderNameBadgeHeader();
		$html .= Html::openElement( 'tbody' );
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
		],
			$schemaSectionContent
		);
	}

	private function renderSchemaText( string $schemaText ): string {
		$attribs = [
			'id' => 'entityschema-schema-text',
			'class' => 'entityschema-schema-text',
			'dir' => 'ltr',
		];

		if ( $this->useSyntaxHighlight ) {
			$highlighted = SyntaxHighlight::highlight( $schemaText, 'shex' );

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

		// @phan-suppress-next-line SecurityCheck-DoubleEscaped False positive
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

	private function renderHeading( NameBadge $nameBadge, PageReference $page ): string {
		if ( $nameBadge->label !== '' ) {
			$label = Html::element(
				'span',
				[ 'class' => 'entityschema-title-label' ],
				$nameBadge->label
			);
		} else {
			$label = Html::element(
				'span',
				[ 'class' => 'entityschema-title-label-empty' ],
				$this->msg( 'entityschema-label-empty' )
					->text()
			);
		}

		$id = Html::element(
			'span',
			[ 'class' => 'entityschema-title-id' ],
			$this->msg( 'parentheses' )
				->plaintextParams( $this->titleFormatter->getText( $page ) )
				->text()
		);

		return Html::rawElement(
			'span',
			[ 'class' => 'entityschema-title' ],
			$label . ' ' . $id
		);
	}

}
