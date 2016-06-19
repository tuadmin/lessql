<?php

namespace LessQL;

/**
 * Represents a prepared SQL statement
 */
class Prepared {

	/**
	 * @param Fragment $statement
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

	/** @var Fragment */
	protected $statement;

	/** @var \PDOStatement */
	protected $pdoStatement;

}
