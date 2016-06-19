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
			$this->rows = $source->fetchAll();
			$this->affected = $source->rowCount();
		}

		$this->rows = array_map( array( $this->statement, 'createRow' ), $this->rows );
		$this->count = count( $this->rows );

		if ( $this->statement->hasInsertId() ) {
			$this->insertId = $this->pdo()->lastInsertId( $this->statement->getInsertSequence() );
		}
	}

	//

	function affected() {
		return $this->affected;
	}

	function insertId() {
		return $this->insertId;
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

	//

	function count() {
		return $this->count;
	}

	//

	function jsonSerialize() {
		return $this->rows;
	}

	//

	protected $statement;
	protected $rows = array();
	protected $count = 0;
	protected $affected = 0;
	protected $insertId;

}
