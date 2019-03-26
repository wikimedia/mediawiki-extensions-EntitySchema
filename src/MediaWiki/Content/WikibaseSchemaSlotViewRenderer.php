<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Config;
use Html;
use LanguageCode;
use Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MessageLocalizer;
use ParserOutput;
use SpecialPage;
use Title;
use Wikibase\Schema\MediaWiki\SpecificLanguageMessageLocalizer;
use Wikibase\Schema\Services\SchemaConverter\FullViewSchemaData;
use Wikibase\Schema\Services\SchemaConverter\NameBadge;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseSchemaSlotViewRenderer {

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var Config */
	private $config;

	/**
	 * @param string $languageCode The language in which to render the view.
	 */
	public function __construct(
		$languageCode,
		LinkRenderer $linkRenderer = null,
		Config $config = null
	) {
		$this->messageLocalizer = new SpecificLanguageMessageLocalizer( $languageCode );
		$this->linkRenderer = $linkRenderer ?: MediaWikiServices::getInstance()->getLinkRenderer();
		$this->config = $config ?: MediaWikiServices::getInstance()->getMainConfig();
	}

	private function msg( $key ) {
		return $this->messageLocalizer->msg( $key );
	}

	public function fillParserOutput(
		FullViewSchemaData $schemaData,
		Title $title,
		ParserOutput $output
	) {
		$output->addModules( 'ext.WikibaseSchema.action.view.trackclicks' );
		$output->addModuleStyles( 'ext.WikibaseSchema.view' );
		$output->setText(
			$this->renderNameBadges( $title, $schemaData->nameBadges ) .
			$this->renderSchemaSection( $title, $schemaData->schemaText )
		);
		$output->setDisplayTitle(
			$this->renderHeading( reset( $schemaData->nameBadges ), $title )
		);
	}

	private function renderNameBadges( Title $title, array $nameBadges ) {
		$html = Html::openElement( 'table', [ 'class' => 'wikitable' ] );
		$html .= $this->renderNameBadgeHeader();
		$html .= Html::openElement( 'tbody' );
		foreach ( $nameBadges as $langCode => $nameBadge ) {
			$html .= "\n";
			$html .= $this->renderNameBadge( $nameBadge, $langCode, $title->getText() );
		}
		$html .= Html::closeElement( 'tbody' );
		$html .= Html::closeElement( 'table' );
		return $html;
	}

	private function renderNameBadgeHeader() {
		$tableHeaders = '';
		// message keys:
		// wikibaseschema-namebadge-header-language-code
		// wikibaseschema-namebadge-header-label
		// wikibaseschema-namebadge-header-description
		// wikibaseschema-namebadge-header-aliases
		// wikibaseschema-namebadge-header-edit
		foreach ( [ 'language-code', 'label', 'description', 'aliases', 'edit' ] as $key ) {
			$tableHeaders .= Html::element(
				'th',
				[],
				$this->msg( 'wikibaseschema-namebadge-header-' . $key )
					->parse()
			);
		}

		return Html::rawElement( 'thead', [], Html::rawElement(
			'tr',
			[],
			$tableHeaders
		) );
	}

	private function renderNameBadge( NameBadge $nameBadge, $languageCode, $schemaId ) {
		$language = Html::element(
			'td',
			[],
			$languageCode
		);
		$bcp47 = LanguageCode::bcp47( $languageCode ); // 'simple' => 'en-simple' etc.
		$label = Html::element(
			'td',
			[
				'class' => 'wbschema-label',
				'lang' => $bcp47,
			],
			$nameBadge->label
		);
		$description = Html::element(
			'td',
			[
				'class' => 'wbschema-description',
				'lang' => $bcp47,
			],
			$nameBadge->description
		);
		$aliases = Html::element(
			'td',
			[
				'class' => 'wbschema-aliases',
				'lang' => $bcp47,
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

	private function renderNameBadgeEditLink( $schemaId, $langCode ) {
		$specialPageTitleValue = SpecialPage::getTitleValueFor(
			'SetSchemaLabelDescriptionAliases',
			$schemaId . '/' . $langCode
		);

		return Html::rawElement(
			'td',
			[
				'class' => 'wbschema-edit-button',
			],
			$this->linkRenderer->makeKnownLink(
				$specialPageTitleValue,
				$this->msg( 'wikibaseschema-edit' ),
				[ 'class' => 'edit-icon' ]
			)
		);
	}

	private function renderSchemaSection( Title $title, $schemaText ) {
		$schemaSectionContent = $schemaText
			? $this->renderSchemaTextLinks( $title ) . $this->renderSchemaText( $schemaText )
			: $this->renderSchemaAddTextLink( $title );
		return Html::rawElement( 'div', [
			'id' => 'wbschema-schema-view-section',
			'class' => 'wbschema-section',
		],
			$schemaSectionContent
		);
	}

	private function renderSchemaText( $schemaText ) {
		return Html::element(
			'pre',
			[
				'id' => 'wbschema-schema-text',
				'class' => 'wbschema-schema-text',
			],
			$schemaText
		);
	}

	private function renderSchemaTextLinks( Title $title ) {
		return Html::rawElement(
			'div',
			[
				'class' => 'wbschema-schema-text-links',
			],
			$this->renderSchemaCheckLink( $title ) .
			$this->renderSchemaEditLink( $title )
		);
	}

	private function renderSchemaCheckLink( Title $title ) {
		$url = $this->config->get( 'WBSchemaShExSimpleUrl' );
		if ( !$url ) {
			return '';
		}

		$schemaTextTitle = SpecialPage::getTitleFor( 'SchemaText', $title->getText() );
		$separator = strpos( $url, '?' ) === false ? '?' : '&';
		$url .= $separator . 'schemaURL=' . wfUrlencode( $schemaTextTitle->getFullURL() );

		return $this->makeExternalLink(
			$url,
			wfMessage( 'wikibaseschema-check-entities' )->inContentLanguage()->parse(),
			false, // link text already escaped in ->parse()
			'',
			[ 'class' => 'wbschema-check-schema' ]
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
	) {
		return Html::rawElement(
			'span',
			[ 'class' => 'mw-parser-output' ],
			Linker::makeExternalLink( $url, $text, $escape, $linktype, $attribs, $title )
		);
	}

	private function renderSchemaAddTextLink( Title $title ) {
		return Html::rawElement(
			'span',
			[
				'id' => 'wbschema-edit-schema-text',
				'class' => 'wbschema-edit-button',
			],
			$this->linkRenderer->makeKnownLink(
				$title,
				$this->msg( 'wikibaseschema-add-schema-text' ),
				[ 'class' => 'add-icon' ],
				[ 'action' => 'edit' ]
			)
		);
	}

	private function renderSchemaEditLink( Title $title ) {
		return Html::rawElement(
			'span',
			[
				'id' => 'wbschema-edit-schema-text',
				'class' => 'wbschema-edit-button',
			],
			$this->linkRenderer->makeKnownLink(
				$title,
				$this->msg( 'wikibaseschema-edit' ),
				[ 'class' => 'edit-icon' ],
				[ 'action' => 'edit' ]
			)
		);
	}

	private function renderHeading( NameBadge $nameBadge, Title $title ) {
		if ( $nameBadge->label !== '' ) {
			$label = Html::element(
				'span',
				[ 'class' => 'wbschema-title-label' ],
				$nameBadge->label
			);
		} else {
			$label = Html::element(
				'span',
				[ 'class' => 'wbschema-title-label-empty' ],
				$this->msg( 'wikibaseschema-label-empty' )
					->text()
			);
		}

		$id = Html::element(
			'span',
			[ 'class' => 'wbschema-title-id' ],
			$this->msg( 'parentheses' )
				->plaintextParams( $title->getText() )
				->text()
		);

		return Html::rawElement(
			'span',
			[ 'class' => 'wbschema-title' ],
			$label . ' ' . $id
		);
	}

}
