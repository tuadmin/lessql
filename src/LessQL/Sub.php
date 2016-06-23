<?php

namespace LessQL;

/**
 * Represents a sub statement parameter
 */
class Sub {

	/**
	 * @param Fragment $statement
	 */
	function __construct( $statement ) {
		$this->statement = $statement;
	}

	/**
	 * Get $key values of the statement's rows
	 *
	 * @param string $key
	 * @return array
	 */
	function getKeys( $key ) {

		if ( count( $this->rows ) > 0 && !$this->rows[ 0 ]->hasProperty( $key ) ) {

			throw new \LogicException( '"' . $key . '" does not exist in "' . $this->table . '" result' );

		}

		$keys = array();

		foreach ( $this->rows as $row ) {
			if ( $row->__isset( $key ) ) {
				$keys[] = $row->__get( $key );
			}
		}

		return array_values( array_unique( $keys ) );

	}

	/** @var Fragment */
	protected $statement;

}
