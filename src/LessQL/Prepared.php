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
		$this->statement = $statement->resolve();
		$this->pdoStatement = $this->statement->getDatabase()->getPdo()
			->prepare( (string) $statement );
	}

	/**
	 * @param array $params
	 * @return Result
	 */
	function exec( $params = array() ) {
		$this->pdoStatement->execute( array_merge( $this->statement->getParams(), $params ) );
		return $this->statement->createResult( $this->pdoStatement );
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
