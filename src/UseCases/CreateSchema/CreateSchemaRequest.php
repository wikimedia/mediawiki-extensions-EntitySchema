<?php

namespace Wikibase\Schema\UseCases\CreateSchema;

/**
 * @license GPL-2.0-or-later
 */
class CreateSchemaRequest {

	private $languageCode;
	private $label = '';
	private $description = '';
	private $aliases = [];
	/** @var string */
	private $schema = '';

	/**
	 * @return string
	 */
	public function getLanguageCode() {
		return $this->languageCode;
	}

	/**
	 * @param string $languageCode
	 */
	public function setLanguageCode( $languageCode ) {
		$this->languageCode = $languageCode;
	}

	/**
	 * @return string
	 */
	public function getLabel() {
		return $this->label;
	}

	/**
	 * @param string $label
	 */
	public function setLabel( $label ) {
		$this->label = $label;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @param string $description
	 */
	public function setDescription( $description ) {
		$this->description = $description;
	}

	/**
	 * @return string[]
	 */
	public function getAliases() {
		return $this->aliases;
	}

	public function setAliases( array $aliases ) {
		$this->aliases = $aliases;
	}

	/**
	 * @return string
	 */
	public function getSchema() {
		return $this->schema;
	}

	/**
	 * @param string $schema
	 */
	public function setSchema( $schema ) {
		$this->schema = $schema;
	}

}
