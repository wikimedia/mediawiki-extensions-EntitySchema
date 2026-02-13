<?php

declare( strict_types=1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\Wikibase\Hooks\WikibaseRepoWbui2025InitResourceDependenciesHookHandler;
use PHPUnit\Framework\TestCase;

/**
 * @license GPL-2.0-or-later
 * @covers \EntitySchema\Wikibase\Hooks\WikibaseRepoWbui2025InitResourceDependenciesHookHandler
 */
class WikibaseRepoWbui2025InitResourceDependenciesHookHandlerTest extends TestCase {

	public function testDependencyIsAddedToArray(): void {
		$dependencies = [ 'test.dependency' ];
		( new WikibaseRepoWbui2025InitResourceDependenciesHookHandler() )
			->onWikibaseRepoWbui2025InitResourceDependenciesHook( $dependencies );
		$this->assertContains( 'entitySchema.wbui2025.entityViewInit', $dependencies );
		$this->assertContains( 'test.dependency', $dependencies );
	}

}
