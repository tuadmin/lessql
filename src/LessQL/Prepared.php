<?php

namespace LessQL;

class Prepared {

	function __construct( $statement ) {
		$this->statement = $statement;
		$this->pdoStatement = $this->statement->pdo()->prepare( (string) $statement );
	}

	function exec( $params = array() ) {
		$this->pdoStatement->execute( $params );
		return $this->statement->createResult( $this->pdoStatement );
	}

	protected $statement;
	protected $pdoStatement;

}
