<?php

declare( strict_types = 1 );

namespace EntitySchema\Wikibase\Hooks;

/**
 * @license GPL-2.0-or-later
 */
class WikibaseRepoWbui2025InitResourceDependenciesHookHandler {

	public function onWikibaseRepoWbui2025InitResourceDependenciesHook( array &$dependencies ): void {
		$dependencies[] = 'entitySchema.wbui2025.entityViewInit';
	}

}
