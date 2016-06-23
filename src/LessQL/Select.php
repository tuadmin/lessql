<?php

namespace LessQL;

/**
 * A SELECT expression fragment. Just the selected expressions
 */
class Select extends Fragment {

	function __construct( $db, $list = array() ) {
		$this->list = $list;
		parent::__construct( $db, $this->build() );
	}

	function with( $expr ) {
		if ( !preg_match( '/^asc|desc$/i', $direction ) ) {
			throw new \LogicException( 'Invalid ORDER BY direction: ' + $direction );
		}
		$list = $this->list;
		$list[] = $db( '&expr', array( 'expr' => $expr ) )->resolve();
		return new self( $this->db, $list );
	}

	protected function build() {
		if ( empty( $this->list ) ) return '*';
		return implode( ', ', $this->list );
	}

	protected $list = array();

}
