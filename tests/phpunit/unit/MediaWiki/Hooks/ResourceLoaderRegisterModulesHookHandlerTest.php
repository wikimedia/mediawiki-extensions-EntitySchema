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
			'tmpEnableMobileEditingUIBetaFeature' => false,
		] );
		$resourceLoader = $this->createMock( ResourceLoader::class );
		$resourceLoader->expects( $this->once() )->method( 'register' );
		( new ResourceLoaderRegisterModulesHookHandler( true, $settings ) )
			->onResourceLoaderRegisterModules( $resourceLoader );
	}

	public static function provideSettingsAndModuleExpectation() {
		yield 'module registered if tmpMobileEditingUI set' => [ true, false ];
		yield 'module registered if tmpEnableMobileEditingUIBetaFeature set' => [ false, true ];
		yield 'module registered if both set' => [ true, true ];
	}

	/**
	 * @dataProvider provideSettingsAndModuleExpectation
	 */
	public function testRegistersWbuiModuleIfWbuiFeatureEnabled(
		bool $tmpMobileEditingUI,
		bool $tmpEnableMobileEditingUIBetaFeature
	): void {
		$settings = new SettingsArray( [
			'tmpMobileEditingUI' => $tmpMobileEditingUI,
			'tmpEnableMobileEditingUIBetaFeature' => $tmpEnableMobileEditingUIBetaFeature,
		] );
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
