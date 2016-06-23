<?php

namespace LessQL;

/**
 * A conditional expression fragment
 */
class Conditional extends Fragment {

	function __construct( $db, $list = array(), $params = array() ) {
		$this->list = $list;
		parent::__construct( $db, $this->build(), $params );
	}

	function with( $condition, $params = array() ) {

		// conditions in key-value array
		if ( is_array( $condition ) ) {
			$new = $this;
			foreach ( $condition as $c => $params ) {
				$new = $new->where( $c, $params );
			}
			return $new;
		}

		$list = $this->list;

		// shortcut for basic "column is (in) value"
		if ( preg_match( '/^[a-z0-9_.`"]+$/i', $condition ) ) {
			$list[] = $this->db->is( $condition, $params );
			return new self( $this->db, $list, $this->prefix );
		}

		if ( !is_array( $params ) ) {
			$params = func_get_args();
			array_shift( $params );
		}

		$list[] = $condition;
		return new self( $this->db, $list, array_merge( $this->params, $params ) );
	}

	function not( $column, $value = null ) {

		// conditions in key-value array
		if ( is_array( $condition ) ) {
			$new = $this;
			foreach ( $condition as $c => $params ) {
				$new = $new->not( $c, $params );
			}
			return $new;
		}

		// shortcut for basic "column is not (in) value"
		if ( preg_match( '/^[a-z0-9_.`"]+$/i', $condition ) ) {
			$list[] = $this->db->isNot( $condition, $params );
			return new self( $this->db, $list, $this->prefix );
		}

		if ( !is_array( $params ) ) {
			$params = func_get_args();
			array_shift( $params );
		}

		$list[] = $condition;
		return new self( $this->db, $list, array_merge( $this->params, $params ) );

	}

	protected function build() {
		if ( empty( $this->list ) ) return '1=1';
		return '(' . implode( ') AND (', $this->list ) . ')';
	}

	protected $list = array();

}
