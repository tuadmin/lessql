<?php

namespace LessQL;

/**
 * Transaction manager, allows for nested transactions
 */
class Transactions {

	/**
	 * Constructor
	 *
	 * @param \PDO $pdo
	 */
	function __construct( $pdo ) {
		$this->pdo = $pdo;
	}

	/**
	 * @param callable $t The transaction body
	 * @return mixed The return value of $t
	 */
	function run( $t, $db = null ) {

		if ( !is_callable( $t ) ) {
			throw new Exception( 'Transaction must be callable' );
		}

		if ( $this->active ) return $t( $db );

		$this->pdo->beginTransaction();
		$this->active = true;

		try {
			$return = $t( $db );
			$this->pdo->commit();
			$this->active = false;
			return $return;
		} catch ( \Exception $ex ) {
			$this->pdo->rollBack();
			$this->active = false;
			throw $ex;
		}

	}

	/** @var bool */
	protected $active = false;

}
