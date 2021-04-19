<?php

namespace EntitySchema\MediaWiki\Content;

use Config;
use EntitySchema\MediaWiki\SpecificLanguageMessageLocalizer;
use EntitySchema\Services\SchemaConverter\FullViewSchemaData;
use EntitySchema\Services\SchemaConverter\NameBadge;
use ExtensionRegistry;
use Html;
use LanguageCode;
use Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;
use MessageLocalizer;
use ParserOutput;
use SpecialPage;
use SyntaxHighlight;
use Title;

/**
 * @license GPL-2.0-or-later
 */
class EntitySchemaSlotViewRenderer {

	/** @var MessageLocalizer */
	private $messageLocalizer;

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var Config */
	private $config;

	/** @var bool */
	private $useSyntaxHighlight;

	/**
	 * @param string $languageCode The language in which to render the view.
	 */
	public function __construct(
		$languageCode,
		LinkRenderer $linkRenderer = null,
		Config $config = null,
		bool $useSyntaxHighlight = null
	) {
		$this->messageLocalizer = new SpecificLanguageMessageLocalizer( $languageCode );
		$this->linkRenderer = $linkRenderer ?: MediaWikiServices::getInstance()->getLinkRenderer();
		$this->config = $config ?: MediaWikiServices::getInstance()->getMainConfig();
		if ( $useSyntaxHighlight === null ) {
			$useSyntaxHighlight = ExtensionRegistry::getInstance()->isLoaded( 'SyntaxHighlight' );
		}
		$this->useSyntaxHighlight = $useSyntaxHighlight;
	}

	private function msg( $key ) {
		return $this->messageLocalizer->msg( $key );
	}

	public function fillParserOutput(
		FullViewSchemaData $schemaData,
		Title $title,
		ParserOutput $output
	) {
		$output->addModules( 'ext.EntitySchema.action.view.trackclicks' );
		$output->addModuleStyles( 'ext.EntitySchema.view' );
		if ( $this->useSyntaxHighlight ) {
			$output->addModuleStyles( 'ext.pygments' );
		}
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
				'dir' => 'auto'
			],
			$nameBadge->description
		);
		$aliases = Html::element(
			'td',
			[
				'class' => 'entityschema-aliases',
				'lang' => $bcp47,
				'dir' => 'auto'
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

	private function renderSchemaSection( Title $title, $schemaText ) {
		$schemaSectionContent = $schemaText
			? $this->renderSchemaTextLinks( $title ) . $this->renderSchemaText( $schemaText )
			: $this->renderSchemaAddTextLink( $title );
		return Html::rawElement( 'div', [
			'id' => 'entityschema-schema-view-section',
			'class' => 'entityschema-section',
		],
			$schemaSectionContent
		);
	}

	private function renderSchemaText( $schemaText ) {
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

	private function renderSchemaTextLinks( Title $title ) {
		return Html::rawElement(
			'div',
			[
				'class' => 'entityschema-schema-text-links',
			],
			$this->renderSchemaCheckLink( $title ) .
			$this->renderSchemaEditLink( $title )
		);
	}

	private function renderSchemaCheckLink( Title $title ) {
		$url = $this->config->get( 'EntitySchemaShExSimpleUrl' );
		if ( !$url ) {
			return '';
		}

		$schemaTextTitle = SpecialPage::getTitleFor( 'EntitySchemaText', $title->getText() );
		$url = wfAppendQuery( $url, [
			'schemaURL' => $schemaTextTitle->getFullURL()
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
				'id' => 'entityschema-edit-schema-text',
				'class' => 'entityschema-edit-button',
			],
			$this->linkRenderer->makeKnownLink(
				$title,
				$this->msg( 'entityschema-add-schema-text' )->text(),
				[ 'class' => 'add-icon' ],
				[ 'action' => 'edit' ]
			)
		);
	}

	private function renderSchemaEditLink( Title $title ) {
		return Html::rawElement(
			'span',
			[
				'id' => 'entityschema-edit-schema-text',
				'class' => 'entityschema-edit-button',
			],
			$this->linkRenderer->makeKnownLink(
				$title,
				$this->msg( 'entityschema-edit' )->text(),
				[ 'class' => 'edit-icon' ],
				[ 'action' => 'edit' ]
			)
		);
	}

	private function renderHeading( NameBadge $nameBadge, Title $title ) {
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
				->plaintextParams( $title->getText() )
				->text()
		);

		return Html::rawElement(
			'span',
			[ 'class' => 'entityschema-title' ],
			$label . ' ' . $id
		);
	}

}
