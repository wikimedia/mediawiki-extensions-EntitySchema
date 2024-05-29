<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use MediaWiki\Hook\SidebarBeforeOutputHook;
use Skin;
use Wikibase\DataAccess\EntitySource;
use Wikimedia\Assert\Assert;

/**
 * @license GPL-2.0-or-later
 */
class SidebarHookHandler implements SidebarBeforeOutputHook {

	private bool $entitySchemaIsRepo;
	private ?EntitySource $localEntitySource;

	public function __construct(
		bool $entitySchemaIsRepo,
		?EntitySource $localEntitySource
	) {
		$this->entitySchemaIsRepo = $entitySchemaIsRepo;
		if ( $entitySchemaIsRepo ) {
			Assert::parameterType( EntitySource::class, $localEntitySource, '$localEntitySource' );
		}
		$this->localEntitySource = $localEntitySource;
	}

	/**
	 * Add Concept URI link to the toolbox section of the sidebar.
	 *
	 * @param Skin $skin
	 * @param string[] &$sidebar
	 * @return void
	 */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		if ( !$this->entitySchemaIsRepo ) {
			return;
		}

		$conceptUriLink = $this->buildConceptUriLink( $skin );

		if ( $conceptUriLink === null ) {
			return;
		}

		$sidebar['TOOLBOX']['wb-concept-uri'] = $conceptUriLink;
	}

	/**
	 * Build concept URI link for the sidebar toolbox.
	 *
	 * @param Skin $skin
	 * @return string[]|null Array of link elements or Null if link cannot be created.
	 */
	public function buildConceptUriLink( Skin $skin ): ?array {
		$title = $skin->getTitle();

		if ( $title === null || !$title->inNamespace( NS_ENTITYSCHEMA_JSON ) ) {
			return null;
		}

		$baseConceptUri = $this->localEntitySource->getConceptBaseUri();

		return [
			'id' => 't-wb-concept-uri',
			'text' => $skin->msg( 'wikibase-concept-uri' )->text(),
			'href' => $baseConceptUri . $title->getText(),
			'title' => $skin->msg( 'wikibase-concept-uri-tooltip' )->text(),
		];
	}

}
