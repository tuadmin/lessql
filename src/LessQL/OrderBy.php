<?php

namespace LessQL;

/**
 * An ORDER BY fragment
 */
class OrderBy extends Fragment {

	function __construct( $db, $list = array() ) {
		$this->list = $list;
		parent::__construct( $db, $this->build() );
	}

	function with( $column, $direction = 'ASC' ) {
		if ( !preg_match( '/^asc|desc$/i', $direction ) ) {
			throw new \LogicException( 'Invalid ORDER BY direction: ' + $direction );
		}
		$list = $this->list;
		$list[] = $db( '&column ' . $direction, array( 'column' => $column ) )->resolve();
		return new self( $this->db, $list );
	}

	protected function build() {
		if ( empty( $this->list ) ) return '';
		return 'ORDER BY ' . implode( ', ', $this->list );
	}

	protected $list = array();
}
