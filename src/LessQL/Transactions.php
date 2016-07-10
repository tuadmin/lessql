<?php

namespace LessQL;

/**
 * Transaction manager, allows for nested transactions
 */
class Transactions {

	/**
	 *
	 */
	function __construct( $pdo ) {
		$this->pdo = $pdo;
	}

	/**
	 *
	 */
	function run( $fn, $db = null ) {

		if ( !is_callable( $fn ) ) {
			throw new Exception( 'Transaction must be callable' );
		}

		if ( $this->active ) return $fn( $db );

		$this->pdo->beginTransaction();
		$this->active = true;

		try {
			$return = $fn( $db );
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
