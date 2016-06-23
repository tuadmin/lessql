<?php

namespace LessQL;

/**
 * Represents the result of a SQL statement
 * May contain rows, the number of affected rows, and an insert id
 */
class Result implements \IteratorAggregate, \Countable, \JsonSerializable {

	/**
	 * Constructor, only for internal use
	 */
	function __construct( $statement, $source ) {
		$this->statement = $statement;

		if ( is_array( $source ) ) {
			$this->rows = $source;
		} else {
			$this->rows = $source->fetchAll( \PDO::FETCH_ASSOC );
			$this->affected = $source->rowCount();
		}

		$this->rows = array_map( array( $this, 'createRow' ), $this->rows );
		$this->count = count( $this->rows );

		$this->insertId = $statement->getDatabase()->getPdo()
			->lastInsertId( /*$statement->getInsertSequence()*/ );
	}

	/**
	 * Return first row in result, if any
	 *
	 * @return Row
	 */
	function first() {
		return $this->count > 0 ? $this->rows[ 0 ] : null;
	}

	/**
	 * Return number of affected rows
	 *
	 * @return int
	 */
	function affected() {
		return $this->affected;
	}

	/**
	 * Return inserted id
	 *
	 * @return int|string
	 */
	function getInsertId() {
		return $this->insertId;
	}

	/**
	 * Create row (internal use only)
	 */
	protected function createRow( $data ) {
		return $this->statement->getDatabase()->createRow( $this->getPrimaryTable(), $data );
	}

	/**
	 * Get primary table of statement
	 */
	function getPrimaryTable() {
		return $this->statement->getPrimaryTable();
	}

	//

	/**
	 * IteratorAggregate
	 *
	 * @return \ArrayIterator
	 */
	function getIterator() {
		return new \ArrayIterator( $this->rows );
	}

	/**
	 * Countable
	 */
	function count() {
		return $this->count;
	}

	/**
	 * JsonSerializable
	 */
	function jsonSerialize() {
		return $this->rows;
	}

	//

	/** @var Fragment */
	protected $statement;

	/** @var array */
	protected $rows = array();

	/** @var int */
	protected $count = 0;

	/** @var int */
	protected $affected = 0;

	/** @var mixed */
	protected $insertId;

}
