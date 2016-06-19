<?php

namespace LessQL;

class Context {

	function __construct( $db ) {

	}

	function exec( $statement ) {

	}

	function prepare( $sql, $params ) {
		return new Prepared( $this, $this->rewrite( $sql, $params ) );
	}

	function result( $source ) {
		return new Result( $this, $source );
	}

	function pdo() {
		return $this->db->pdo();
	}

	/**
	 * Get column, used by result if row is parent
	 *
	 * @param string $key
	 * @return array
	 */
	function getLocalKeys( $key ) {

		if ( isset( $this[ $key ] ) ) {

			return array( $this[ $key ] );

		}

		return array();

	}

	/**
	 * Get global keys of parent result, or column if row is root
	 *
	 * @param string $key
	 * @return array
	 */
	function getGlobalKeys( $key ) {

		$result = $this->getResult();

		if ( $result ) return $result->getGlobalKeys( $key );

		return $this->getLocalKeys( $key );

	}

	/**
	 * Get value from cache
	 *
	 * @param $key
	 * @return mixed
	 */
	function getCache( $key ) {

		return isset( $this->_cache[ $key ] ) ? $this->_cache[ $key ] : null;

	}

	/**
	 * Set cache value
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	function setCache( $key, $value ) {

		$this->_cache[ $key ] = $value;

	}

}
