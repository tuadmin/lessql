<?php

namespace LessQL;

/**
 * Represents an eager loading policy
 */
class Eager {

	/**
	 *
	 */
	function __construct( $query, $key, $value, $parentTable, $parentKey, $single ) {
		$this->query = $query;
		$this->key = $key;
		$this->value = $value;
		$this->parentTable = $parentTable;
		$this->parentKey = $parentKey;
		$this->single = $single;
	}

	/**
	 *
	 */
	function exec() {
		$db = $this->query->getDatabase();
		$eager = $this->query->where( $this->key, $db->getKnownKeys( $this->parentTable, $this->parentKey ) )->exec();
		$rows = array();
		foreach ( $eager as $row ) {
			if ( $row[ $this->key ] === $this->value ) {
				$rows[] = $row;
			}
		}
		return $db->createResult( $this->query, $rows );
	}

	/**
	 *
	 */
	function via( $key ) {
		$clone = clone $this;
		if ( $this->single ) {
			$clone->key = $key;
		} else {
			$clone->parentKey = $key;
		}
		return $clone;
	}

	/** @var SQL */
	protected $query;

	/** @var string */
	protected $key;

	/** @var mixed */
	protected $value;

	/** @var string */
	protected $parentTable;

	/** @var string */
	protected $parentKey;

	/** @var boolean */
	protected $single;

}
