<?php

namespace LessQL;

/**
 * A LIMIT fragment
 */
class Limit extends Fragment {

	function __construct( $db, $count = null, $offset = null ) {
		if ( $count !== null ) {
			if ( $offset !== null ) {
				parent::__construct( $db, 'LIMIT ' . intval( $count ) . ' OFFSET ' . intval( $offset ) );
			} else {
				parent::__construct( $db, 'LIMIT ' . intval( $count ) );
			}
		} else {
			parent::__construct( $db, '' );
		}
	}

}
