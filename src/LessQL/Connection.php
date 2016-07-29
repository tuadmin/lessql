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
	 * @return Connection
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
	 * @param EventEmitter $emitter
	 * @return \PDO
	 */
	function init( $emitter = null ) {

		if ( $this->init ) return $this->pdo;

		// mysql
		try {
			$this->pdo->exec( "SET sql_mode=(SELECT CONCAT(@@sql_mode,',NO_BACKSLASH_ESCAPES,ANSI_QUOTES'))" );
		} catch ( \Exception $ex ) {
			if ( $emitter ) $emitter->emit( 'init', $ex );
		}

		// postgres
		try {
			$this->pdo->exec( "SET standard_conforming_strings=on" );
		} catch ( \Exception $ex ) {
			if ( $emitter ) $emitter->emit( 'init', $ex );
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

		if ( $this->activeTransaction ) {
			try {
				return call_user_func( $t, $context );
			} catch ( \Exception $ex ) {
				$this->nestedException = $this->nestedException ?: $ex;
				throw $ex;
			}
		}

		$this->init();
		$this->pdo->beginTransaction();
		$this->activeTransaction = true;

		try {
			$return = call_user_func( $t, $context );
			if ( $this->nestedException ) {
				throw new Exception(
					'Must roll back, nested transaction failed: ' .
			 		$this->nestedException->getMessage()
				);
			}
			$this->pdo->commit();
			$this->activeTransaction = false;
			return $return;
		} catch ( \Exception $ex ) {
			$this->activeTransaction = false;
			$this->nestedException = null;
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

	/** @var \Exception|null */
	protected $nestedException = null;

}
