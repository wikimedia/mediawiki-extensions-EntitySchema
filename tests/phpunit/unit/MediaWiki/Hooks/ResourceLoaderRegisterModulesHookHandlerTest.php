<?php

declare( strict_types = 1 );

namespace EntitySchema\Tests\Unit\Wikibase\Hooks;

use EntitySchema\MediaWiki\Hooks\ResourceLoaderRegisterModulesHookHandler;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWikiUnitTestCase;
use Wikibase\Lib\SettingsArray;

/**
 * @covers \EntitySchema\MediaWiki\Hooks\ResourceLoaderRegisterModulesHookHandler
 * @license GPL-2.0-or-later
 */
class ResourceLoaderRegisterModulesHookHandlerTest extends MediaWikiUnitTestCase {

	public function testOnResourceLoaderRegisterModules() {
		$handler = new ResourceLoaderRegisterModulesHookHandler( true, null );

		$rlModules = [];
		$rl = $this->createMock( ResourceLoader::class );
		$rl->expects( $this->once() )
			->method( 'register' )
			->willReturnCallback( static function ( array $modules ) use ( &$rlModules ) {
				$rlModules = array_merge( $rlModules, $modules );
			} );
		$handler->onResourceLoaderRegisterModules( $rl );

		$this->assertCount( 6, $rlModules );
		$this->assertArrayHasKey( 'ext.EntitySchema.experts.EntitySchema', $rlModules );
	}

	public function testOnResourceLoaderRegisterModules_client() {
		$handler = new ResourceLoaderRegisterModulesHookHandler( false, null );

		$rl = $this->createMock( ResourceLoader::class );
		$rl->expects( $this->never() )
			->method( 'register' );

		$handler->onResourceLoaderRegisterModules( $rl );
	}

	public function testRegistersNoModulesIfWbuiFeatureDisabled(): void {
		$settings = new SettingsArray( [
			'tmpMobileEditingUI' => false,
		] );
		$resourceLoader = $this->createMock( ResourceLoader::class );
		$resourceLoader->expects( $this->once() )->method( 'register' );
		( new ResourceLoaderRegisterModulesHookHandler( true, $settings ) )
			->onResourceLoaderRegisterModules( $resourceLoader );
	}

	public function testRegistersWbuiModuleIfWbuiFeatureEnabled(): void {
		$settings = new SettingsArray( [ 'tmpMobileEditingUI' => true ] );
		$resourceLoader = $this->createMock( ResourceLoader::class );
		$firstCall = true;
		$resourceLoader
			->expects( $this->exactly( 2 ) )
			->method( 'register' )
			->willReturnCallback( function ( $modules ) use ( &$firstCall ){
				if ( $firstCall ) {
					$firstCall = false;
					return false;
				}
				$this->assertArrayHasKey( 'entitySchema.wbui2025.entityViewInit', $modules );
				return false;
			} );
		( new ResourceLoaderRegisterModulesHookHandler( true, $settings ) )
			->onResourceLoaderRegisterModules( $resourceLoader );
	}

}
