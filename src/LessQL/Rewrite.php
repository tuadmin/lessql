<?php

namespace LessQL;

class Rewrite {

	public static function params( $db, $statement ) {

		$tokens = $statement->tokens();
		$params = $statement->params();
		$output = '';

		foreach ( $tokens as $token ) {
			list( $type, $token ) = $token;

			if ( $type === self::TOKEN_MARKER ) {
				$first = $token{ 0 };
				$name = substr( $token, 1 );

				$param = $params[ $name ];
				if ( is_array( $param ) ) {
					$output .=
						implode( ',', array_map( array( $db, 'quoteIdentifier' ), $param ) );
				} else {
					$output .= $db->quoteIdentifier( $param );
				}
				unset( $params[ $name ] );
			} else {
				$output .= $token;
			}

		}

		return new Statement( $output, $params );

	}

}
