<?php

namespace LessQL;

/**
 * Represents the result of a SQL statement
 * May contain rows, the number of affected rows, and an insert id
 */
class Result implements \IteratorAggregate, \Countable, \JsonSerializable {

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

		$this->insertId = $this->statement->getDatabase()->getPdo()
			->lastInsertId( /*$this->statement->getInsertSequence()*/ );
	}

	/**
	 *
	 */
	function first() {
		return $this->count > 0 ? $this->rows[ 0 ] : null;
	}

	/**
	 *
	 */
	function affected() {
		return $this->affected;
	}

	/**
	 *
	 */
	function getInsertId() {
		return $this->insertId;
	}

	/**
	 *
	 */
	function createRow( $data ) {
		return $this->statement->getDatabase()->createRow( $data, array(
			'result' => $this
		) );
	}

	/**
	 *
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
