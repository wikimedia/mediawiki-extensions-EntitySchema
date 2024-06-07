<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use MediaWiki\Hook\CanonicalNamespacesHook;
use MediaWiki\Hook\NamespaceIsMovableHook;
use MediaWiki\MediaWikiServices;
use Wikibase\Lib\WikibaseSettings;

/**
 * @license GPL-2.0-or-later
 */
class NamespaceRegistrationHandler implements CanonicalNamespacesHook, NamespaceIsMovableHook {

	private array $immovableNamespaces = [];

	public static function setConstants() {
		if ( !defined( 'NS_ENTITYSCHEMA_JSON' ) ) {
			define( 'NS_ENTITYSCHEMA_JSON', 640 );
		}
		if ( !defined( 'NS_ENTITYSCHEMA_JSON_TALK' ) ) {
			define( 'NS_ENTITYSCHEMA_JSON_TALK', 641 );
		}
	}

	/**
	 * Hook to register the default namespace names.
	 *
	 * @param array &$namespaces
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/CanonicalNamespaces
	 */
	public function onCanonicalNamespaces( &$namespaces ) {
		// XXX: ExtensionProcessor should define an extra config object for every extension.
		$config = MediaWikiServices::getInstance()->getMainConfig();

		// Do not register ES namespaces when the repo is not enabled.
		if ( !WikibaseSettings::isRepoEnabled() || !$config->get( 'EntitySchemaIsRepo' ) ) {
			return;
		}

		$entitySchemaNamespaceName = 'EntitySchema';
		$namespaces = $this->registerNamespace(
			$namespaces,
			NS_ENTITYSCHEMA_JSON,
			$entitySchemaNamespaceName,
			false,
			true,
			'EntitySchema',
			false
		);

		$talkNamespaceName = $entitySchemaNamespaceName . '_talk';
		$namespaces = $this->registerNamespace(
			$namespaces,
			NS_ENTITYSCHEMA_JSON_TALK,
			$talkNamespaceName,
			true,
			false,
			'wikitext'
		);
	}

	/**
	 * @throws \RuntimeException If namespace ID is already registered with another name
	 */
	private function registerNamespace(
		array $namespaces,
		int $namespaceId,
		string $namespaceName,
		bool $subpages,
		bool $content,
		string $defaultContentModel,
		?bool $movable = null
	): array {
		global $wgNamespacesWithSubpages;
		global $wgContentNamespaces;
		global $wgNamespaceContentModels;
		if (
			isset( $namespaces[$namespaceId] ) &&
			$namespaces[$namespaceId] !== $namespaceName
		) {
			throw new \RuntimeException(
				"Tried to register `$namespaceName` namespace with ID `$namespaceId`, " .
				"but ID was already occupied by `{$namespaces[$namespaceId]} namespace`"
			);
		}

		$namespaces[$namespaceId] = $namespaceName;
		if ( $subpages ) {
			$wgNamespacesWithSubpages[$namespaceId] = true;
		}
		if ( $content ) {
			$wgContentNamespaces[] = $namespaceId;
		}
		$wgNamespaceContentModels[$namespaceId] = $defaultContentModel;
		if ( $movable === false ) {
			$this->immovableNamespaces[] = $namespaceId;
		}

		return $namespaces;
	}

	/**
	 * @inheritDoc
	 */
	public function onNamespaceIsMovable( $index, &$result ) {
		if ( in_array( $index, $this->immovableNamespaces ) ) {
			$result = false;
		}
	}
}
