<?php

namespace LessQL;

/**
 * Represents an eager loading policy. Internal
 */
class Eager {

	/**
	 *
	 */
	function __construct( $query, $other, $back = false ) {
		$this->query = $query;
		$this->other = $other instanceof SQL ? $other->exec() : $other;
		$this->back = $back;

		$table = $query->getTable();
		$otherTable = $other->getTable();
		$schema = $query->getDatabase()->getSchema();

		if ( $back ) {
			$this->key = $schema->getPrimary( $table );
			$this->otherKey = $schema->getReference( $otherTable, $table );
		} else {
			$this->key = $schema->getBackReference( $table, $otherTable );
			$this->otherKey = $schema->getPrimary( $otherTable );
		}
	}

	/**
	 *
	 */
	function exec() {
		$db = $this->query->getDatabase();
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
	 *
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
