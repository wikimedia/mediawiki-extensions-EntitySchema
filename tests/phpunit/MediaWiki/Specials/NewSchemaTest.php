<?php

namespace Wikibase\Schema\Tests\MediaWiki\Specials;

use MediaWikiTestCase;
use PermissionsError;
use ReadOnlyError;
use ReadOnlyMode;
use Wikibase\Schema\MediaWiki\Specials\NewSchema;

/**
 * @covers \Wikibase\Schema\MediaWiki\Specials\NewSchema
 *
 * @license GPL-2.0-or-later
 */
class NewSchemaTest extends MediaWikiTestCase {

	/**
	 * @expectedException ReadOnlyError
	 */
	public function testReadOnly() {
		$readOnlyMode = $this->getMockBuilder( ReadOnlyMode::class )
			->disableOriginalConstructor()
			->getMock();
		$readOnlyMode->method( 'isReadOnly' )->willReturn( true );
		$this->setService( 'ReadOnlyMode', $readOnlyMode );
		$special = new NewSchema();

		$special->run( null );
	}

	/**
	 * @expectedException PermissionsError
	 */
	public function testNoRights() {
		global $wgGroupPermissions;

		$groupPermissions = $wgGroupPermissions;
		$groupPermissions['*']['createpage'] = false;
		$this->setMwGlobals( 'wgGroupPermissions', $groupPermissions );
		$special = new NewSchema();

		$special->run( null );
	}

}
