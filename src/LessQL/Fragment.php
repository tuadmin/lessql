<?php

namespace LessQL;

class Fragment {

	function __construct( $db, $sql, $params = array() ) {
		$this->db = $db;
		$this->sql = $sql;
		$this->params = $params;
	}

	function bind( $params ) {
		$clone = clone $this;
		$clone->params = array_merge( $clone->params, $params );
		return $clone;
	}

	function prepare( $params = null ) {
		if ( $params ) return $this->bind( $params )->prepare();

		return $this->db->prepared( $this );
	}

	function exec( $params = null ) {
		if ( $params ) return $this->bind( $params )->exec();

		$resolved = $this->resolve();
		$pdoStatement = $this->db->pdo()->prepare( (string) $resolved );
		$pdoStatement->execute( $resolved->params() );
		return $this->result( $pdoStatement );
	}

	function result( $source ) {
		return $this->db->result( $this, $source );
	}

	/**
	 * Return fragment with params resolved and inserted into the fragment text
	 */
	function resolve() {

		$resolved = '';
		$tokens = $this->tokens( true );
		$count = count( $tokens );
		$unset = array();

		for ( $i = 0; $i < $count; ++$i ) {

			list( $type, $text ) = $tokens[ $i ];
			$p = null;
			$key = null;

			if ( $type === self::TOKEN_MARKER ) {

				$prefix = $text{ 0 };
				$key = substr( $text, 1 );
				$param = @$this->params[ $key ];

				switch ( $prefix ) {
				case '&':
					$p = $this->db->quoteIdentifier( $param );
					break;
				case ':':
					// only insert non-preparable params here
					if ( is_array( $param ) ) {
						$p = $this->db->quote( $param );
					} else if ( $param instanceof Fragment ) {
						$p = $param->resolve();
					}
					break;
				}

			}

			if ( $p ) {
				$unset[] = $key;
				$resolved .= $p;
			} else {
				$resolved .= $text;
			}

		}

		$params = $this->params;
		foreach ( $unset as $key ) unset( $params[ $key ] );

		return $this->db->fragment( $resolved, $params );

	}

	//

	function params() {
		return $this->params;
	}

	function db() {
		return $this->db;
	}

	function equals( $other ) {
		return $this->tokens() === $other->tokens();
	}

	//

	/**
	 * This is probably a non-standard, insane hack. Works great though.
	 * TODO Needs rigorous testing
	 */
	function tokens( $whitespace = false ) {

		if ( $this->tokens ) {
			return $whitespace ? $this->tokens : $this->cleanTokens;
		}

		static $tokenizeRx;

		if ( !isset( $tokenizeRx ) ) {

			$rx = array(

				'(`(?:[^`\\\\]++|\\\\.|``)*+`)',      // 1 backtick delimited
				"('(?:[^'\\\\]++|\\\\.|'')*+')",      // 2 single quote delimited
				'("(?:[^"\\\\]++|\\\\.|"")*+")',      // 3 double quote delimited
				'(\[(?:[^\]\\\\]++|\\\\.)*+\])',      // 4 bracket delimited

				'((?:--|#)[^\n]*)',                   // 5 single line comments
				'(/\*.*?\*/)',                        // 6 c style comments

				'([:&\?][a-zA-Z_$][a-zA-Z0-9_$]*)',   // 7 parameter markers
				'([a-zA-Z_$][a-zA-Z0-9_$]*)',         // 8 identifiers and keywords
				'(\d+(\.\d+)*)',                      // 9 numbers
				'(<>|<=>|>=|<=|==|!=|<<|>>|\|\||&&)', // 10 multi char operators
				'([^\s])',                            // 11 single char token (operators, punctuation, etc.)

				'(\s+)'                               // 12 whitespace

			);

			$tokenizeRx = '(^' . implode( '|', $rx ) . ')';

		}

		$this->tokens = array();
		$this->cleanTokens = array();
		$sql = $this->sql;

		while ( preg_match( $tokenizeRx, $sql, $match ) ) {
			for ( $type = 1; strlen( $match[ $type ] ) === 0; ++$type );
			$text = $match[ 0 ];

			switch ( $type ) {
			case self::TOKEN_WHITESPACE:
			case self::TOKEN_COMMENT_LINE:
			case self::TOKEN_COMMENT_C:
				break;
			case self::TOKEN_WORD:
				$this->cleanTokens[] = array( $type, strtolower( $text ) );
				break;
			default:
				$this->cleanTokens[] = array( $type, $text );
			}

			$this->tokens[] = array( $type, $text );
			$sql = substr( $sql, strlen( $text ) );
		}

		return $whitespace ? $this->tokens : $this->cleanTokens;

	}

	function __toString() {
		return $this->sql;
	}

	const TOKEN_BACKTICK_QUOTED = 1;
	const TOKEN_SINGLE_QUOTED = 2;
	const TOKEN_DOUBLE_QUOTED = 3;
	const TOKEN_BRACKET_QUOTED = 4;
	const TOKEN_COMMENT_LINE = 5;
	const TOKEN_COMMENT_C = 6;
	const TOKEN_MARKER = 7;
	const TOKEN_WORD = 8;
	const TOKEN_NUMBER = 9;
	const TOKEN_OPERATOR = 10;
	const TOKEN_CHARACTER = 12;
	const TOKEN_WHITESPACE = 13;

	protected $db;
	protected $sql;
	protected $params;
	protected $tokens;
	protected $cleanTokens;

}
