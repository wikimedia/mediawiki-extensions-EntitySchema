<?php

declare( strict_types = 1 );

namespace EntitySchema\MediaWiki;

use MediaWiki\Content\Content;
use MediaWiki\Context\IContextSource;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use StatusValue;

/**
 * @license GPL-2.0-or-later
 */
class HookRunner implements EditFilterMergedContentHook {

	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @param IContextSource $context
	 * @param Content $content
	 * @param StatusValue $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minoredit
	 * @return bool|void
	 */
	public function onEditFilterMergedContent(
		IContextSource $context,
		Content $content,
		StatusValue $status,
		$summary,
		User $user,
		$minoredit
	) {
		$status = Status::wrap( $status );  // Status::wrap() takes references to all internal variables
		return $this->hookContainer->run(
			'EditFilterMergedContent',
			[ $context, $content, $status, $summary, $user, $minoredit ]
		);
	}
}
