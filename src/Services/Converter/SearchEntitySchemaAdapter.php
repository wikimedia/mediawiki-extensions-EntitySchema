<?php

declare( strict_types = 1 );

namespace EntitySchema\Services\Converter;

use Wikibase\DataModel\Term\AliasesProvider;
use Wikibase\DataModel\Term\AliasGroupList;
use Wikibase\DataModel\Term\DescriptionsProvider;
use Wikibase\DataModel\Term\LabelsProvider;
use Wikibase\DataModel\Term\TermList;

/**
 * @license GPL-2.0-or-later
 */
class SearchEntitySchemaAdapter implements LabelsProvider, DescriptionsProvider, AliasesProvider {

	private TermList $labels;
	private TermList $descriptions;
	private AliasGroupList $aliases;

	public function __construct( TermList $labels, TermList $descriptions, AliasGroupList $aliases ) {
		$this->labels = $labels;
		$this->descriptions = $descriptions;
		$this->aliases = $aliases;
	}

	public function getLabels(): TermList {
		return $this->labels;
	}

	public function getDescriptions(): TermList {
		return $this->descriptions;
	}

	public function getAliasGroups(): AliasGroupList {
		return $this->aliases;
	}

}
