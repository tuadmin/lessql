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
	function __construct( $statement, $source, $insertId = null ) {
		$this->statement = $statement;

		if ( is_array( $source ) ) {
			$this->rows = $source;
		} else {
			$this->rows = $source->fetchAll( \PDO::FETCH_ASSOC );
			$this->affected = $source->rowCount();
		}

		$this->rows = array_map( array( $this, 'createRow' ), $this->rows );
		$this->count = count( $this->rows );
		$this->insertId = $insertId;
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

	function getTable() {
		return $this->statement->getTable();
	}

	function getKeys( $key ) {

		$keys = array();

		foreach ( $this->rows as $row ) {
			if ( $row->__isset( $key ) ) {
				$keys[] = $row->__get( $key );
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Create row (internal use only)
	 */
	protected function createRow( $data ) {
		return $this->statement->getDatabase()
			->createRow( $this->statement->getTable(), $data )
			->setClean();
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

	/** @var SQL */
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
