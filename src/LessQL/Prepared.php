<?php

namespace LessQL;

/**
 * Represents a prepared SQL statement
 */
class Prepared implements \IteratorAggregate, \Countable, \JsonSerializable {

	/**
	 * @param SQL $statement
	 */
	function __construct( $statement ) {
		$this->statement = $statement;
		$this->pdoStatement = $this->statement->getDatabase()->getPdo()
			->prepare( (string) $statement );
	}

	/**
	 * Execute statement and return result
	 *
	 * @param array $params
	 * @return Result
	 */
	function exec( $params = array() ) {
		// TODO
	}

	/**
	 * Execute statement and return result
	 *
	 * @param array $params
	 * @return Result
	 */
	function __invoke( $params = null ) {
		return $this->exec( $params );
	}

	/**
	 * IteratorAggregate
	 *
	 * @return \ArrayIterator
	 */
	function getIterator() {
		return $this->exec()->getIterator();
	}

	/**
	 * Countable
	 *
	 * @return int
	 */
	function count() {
		return $this->exec()->count();
	}

	/**
	 * JsonSerializable
	 *
	 * @return array
	 */
	function jsonSerialize() {
		return $this->exec()->jsonSerialize();
	}

	/** @var SQL */
	protected $statement;

	/** @var \PDOStatement */
	protected $pdoStatement;

}
