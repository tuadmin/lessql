<?php

namespace LessQL;

class Context {

	function __construct( $db ) {

	}

	function rewrite( $statement ) {
		return $statement;
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

}
