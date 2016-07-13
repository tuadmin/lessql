<?php

namespace LessQL;

/**
 * Represents an eager loading policy. Internal
 *
 * Immutable
 */
class Eager {

	/**
	 * Constructor. Internal
	 *
	 * @param SQL $query
	 * @param Row|Result|SQL $other
	 * @param bool $back
	 */
	function __construct( $query, $other, $back = false ) {
		$this->query = $query;
		$this->other = $other instanceof SQL ? $other->exec() : $other;
		$this->back = $back;

		$table = $query->getTable();
		$otherTable = $other->getTable();
		$structure = $query->getContext()->getStructure();

		if ( $back ) {
			$this->key = $structure->getPrimary( $table );
			$this->otherKey = $structure->getReference( $otherTable, $table );
		} else {
			$this->key = $structure->getBackReference( $table, $otherTable );
			$this->otherKey = $structure->getPrimary( $otherTable );
		}
	}

	/**
	 * Internal
	 *
	 * @return Result
	 */
	function exec() {
		$db = $this->query->getContext();
		$eager = $this->query->late()->where(
			$this->key,
			$db->getKnownKeys( $this->other->getTable(), $this->otherKey )
		);
		$otherKeys = $this->other->getKeys( $this->otherKey );
		$rows = array();
		foreach ( $eager as $row ) {
			if ( in_array( $row[ $this->key ], $otherKeys ) ) {
				$rows[] = $row;
			}
		}
		return $db->createResult( $this->query, $rows );
	}

	/**
	 * Internal
	 *
	 * @param string $key
	 * @return Eager
	 */
	function via( $key ) {
		$clone = clone $this;
		if ( $this->back ) {
			$clone->otherKey = $key;
		} else {
			$clone->key = $key;
		}
		return $clone;
	}

	/** @var SQL */
	protected $query;

	/** @var Result|Row */
	protected $other;

	/** @var boolean */
	protected $back;

	/** @var string */
	protected $key;

	/** @var string */
	protected $otherKey;

}
