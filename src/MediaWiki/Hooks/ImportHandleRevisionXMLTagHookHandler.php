<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki\Hooks;

use EntitySchema\MediaWiki\Content\EntitySchemaContent;
use MediaWiki\Hook\ImportHandleRevisionXMLTagHook;
use RuntimeException;

/**
 * @license GPL-2.0-or-later
 */
class ImportHandleRevisionXMLTagHookHandler implements ImportHandleRevisionXMLTagHook {

	/**
	 * @inheritDoc
	 */
	public function onImportHandleRevisionXMLTag( $reader, $pageInfo, $revisionInfo ): void {
		if (
			array_key_exists( 'model', $revisionInfo ) &&
			$revisionInfo['model'] === EntitySchemaContent::CONTENT_MODEL_ID
		) {
			throw new RuntimeException(
				'To avoid ID conflicts, the import of Schemas is not supported.'
			);
		}
	}
}
