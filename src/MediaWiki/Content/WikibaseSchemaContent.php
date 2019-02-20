<?php

namespace Wikibase\Schema\MediaWiki\Content;

use Html;
use JsonContent;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use ParserOutput;
use Title;
use Wikibase\Schema\Domain\Model\Schema;
use Wikibase\Schema\Services\SchemaDispatcher\SchemaDispatcher;

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
			$output->addModuleStyles( 'ext.WikibaseSchema.view' );
			$schemaData = ( new SchemaDispatcher() )
				->getMonolingualSchemaData( $this->getText(), 'en' );
			$output->setText(
				$this->renderNameBadge( $schemaData->nameBadge ) .
				$this->renderSchemaSection( $title, $schemaData->schema )
			);
		} else {
			$output->setText( '' );
		}
	}

	private function renderNameBadge( $nameBadge ) {
		return Html::element(
				'h1',
				[
					'id' => 'wbschema-title-label',
				],
				$nameBadge->label
			) .
			Html::element(
				'abstract',
				[
					'id' => 'wbschema-heading-description',
				],
				$nameBadge->description
			) .
			Html::element(
				'p',
				[
					'id' => 'wbschema-heading-aliases',
				],
				implode( ' | ', $nameBadge->aliases )
			);
	}

	private function renderSchemaSection( Title $title , $schemaContent ) {
		return Html::rawElement( 'div', [
			'id' => 'wbschema-schema-view-section',
			'class' => 'wbschema-section',
		],
			$this->renderSchema( $schemaContent ) .
			$this->renderSchemaEditLink( $title )
		);
	}

	private function renderSchema( $schemaContent ) {
		return Html::element(
				'pre',
				[
					'id' => 'wbschema-schema-shexc',
					'class' => 'wbschema-shexc',
				],
				$schemaContent
			);
	}

	private function renderSchemaEditLink( Title $title ) {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		return Html::rawElement(
			'div',
			[
				'id' => 'wbschema-edit-shexc',
				'class' => 'wbschema-edit-button',
			],
			$linkRenderer->makeLink( $title, 'edit', [ 'class' => 'edit-icon' ], [ 'action' => 'edit' ] )
		);
	}

}
