<?php

namespace LessQL;

/**
 * PDO connection wrapper
 * Initializes connection settings and manages (nested) transactions
 *
 * Forces exactly one Connection instance per PDO instance
 */
class Connection {

	/**
	 * Get Connection instance for PDO instance
	 *
	 * @param \PDO
	 * @return Transactions
	 */
	static function get( $pdo ) {
		foreach ( self::$instances as $instance ) {
			list( $pdo_, $conn ) = $instance;
			if ( $pdo_ === $pdo ) return $conn;
		}
		$conn = new self( $pdo );
		self::$instances[] = array( $pdo, $conn );
		return $conn;
	}

	/** @var array */
	static protected $instances = array();

	//

	/**
	 * Constructor
	 *
	 * @param \PDO $pdo
	 */
	protected function __construct( $pdo ) {
		$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		$this->pdo = $pdo;
	}

	/**
	 * Initialize connection once and return PDO instance
	 *
	 * Sets connection settings etc.
	 *
	 * @return \PDO
	 */
	function init( $context = null ) {

		if ( $this->init ) return $this->pdo;

		// disable backslash escapes
		try {
			$this->pdo->exec( "SET sql_mode='NO_BACKSLASH_ESCAPES'" );
		} catch ( \PDOException $ex ) {
			if ( $context ) $context->emit( 'init', $ex );
		}

		// enable standard strings (double quotes are used for identifiers)
		try {
			$this->pdo->exec( "SET standard_conforming_strings=on" );
		} catch ( \PDOException $ex ) {
			if ( $context ) $context->emit( 'init', $ex );
		}

		$this->init = true;

		return $this->pdo;

	}

	/**
	 * Get PDO driver name
	 *
	 * @return string
	 */
	function getDriver() {
		return $this->pdo->getAttribute( \PDO::ATTR_DRIVER_NAME );
	}

	/**
	 * @param callable $t The transaction body
	 * @return mixed The return value of $t
	 */
	function runTransaction( $t, $context = null ) {

		if ( !is_callable( $t ) ) {
			throw new Exception( 'Transaction must be callable' );
		}

		if ( $this->activeTransaction ) return $t( $context );

		$this->init();
		$this->pdo->beginTransaction();
		$this->activeTransaction = true;

		try {
			$return = $t( $context );
			$this->pdo->commit();
			$this->activeTransaction = false;
			return $return;
		} catch ( \Exception $ex ) {
			$this->activeTransaction = false;
			$this->pdo->rollBack();
			throw $ex;
		}

	}

	/** @var \PDO */
	protected $pdo;

	/** @var bool */
	protected $init = false;

	/** @var bool */
	protected $activeTransaction = false;

}
