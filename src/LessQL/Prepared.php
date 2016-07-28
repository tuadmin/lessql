<?php

namespace LessQL;

/**
 * Represents a prepared SQL statement
 *
 * Immutable
 */
class Prepared implements \IteratorAggregate, \Countable, \JsonSerializable {

	/**
	 * @param SQL $statement
	 */
	function __construct( $statement ) {
		$this->statement = $statement;
		$this->pdoStatement = $this->statement->getContext()->getPdo()
			->prepare( (string) $statement );
	}

	/**
	 * Execute statement and return result
	 *
	 * @param array $params
	 * @return Result
	 */
	function exec( $params = array() ) {
		$context = $this->statement->getContext();

		try {
			$context->emit( 'exec', $this->statement );
			$this->pdoStatement->execute( $params );
			$sequence = $context->getStructure()->getSequence( $this->statement->getTable() );
			$insertId = $context->lastInsertId( $sequence );
			return $context->createResult(
				$this->statement,
				$this->pdoStatement,
				$insertId
			);
		} catch ( \Exception $ex ) {
			$context->emit( 'error', $this->statement );
			throw $ex;
		}
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
