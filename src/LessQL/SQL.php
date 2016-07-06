<?php

namespace LessQL;

/**
 * Represents an arbitrary SQL fragment with bound params.
 * Can be prepared and executed.
 */
class SQL implements \IteratorAggregate, \Countable, \JsonSerializable {

	/**
	 * Constructor
	 */
	function __construct( $db, $sql, $params = array() ) {
		$this->db = $db;
		$this->sql = $sql;
		$this->params = $params;
	}

	/**
	 * @param array $params
	 * @return SQL
	 */
	function bind( $params, $value = null ) {
		if ( count( func_get_args() ) > 1 ) {
			return $this->bind( array( $params => $value ) );
		}
		$clone = clone $this;
		$clone->params = array_merge( $clone->params, $params );
		return $clone;
	}

	/**
	 * @param array $params
	 * @return Prepared
	 */
	function prepare( $params = null ) {
		if ( $params ) return $this->bind( $params )->prepare();

		return $this->db->createPrepared( $this );
	}

	/**
	 * Execute statement and return result
	 *
	 * @param array $params
	 * @return Result
	 */
	function exec( $params = null ) {
		if ( $params ) return $this->bind( $params )->exec();
		if ( $this->eager ) return $this->eager->exec();
		return $this->db->exec( $this );
	}

	/**
	 * Execute statement and return result
	 *
	 * @param array $params
	 * @return Result
	 */
	function __invoke( $params = null ) {
		return $this->exec( $params );
	}

	/**
	 * Execute and return first row in result, if any
	 */
	function first() {
		return $this->exec()->first();
	}

	/**
	 * Executed and return affected rows, if any
	 */
	function affected() {
		return $this->exec()->affected();
	}

	/**
	 * Execute and return inserted id, if any
	 */
	function getInsertId() {
		return $this->exec()->getInsertId();
	}

	/**
	 * Query referenced table. Suffix "List" gets many rows
	 *
	 * @param string $name
	 * @param string|array|null $where
	 * @param array $params
	 * @return SQL
	 */
	function __call( $name, $args ) {
		var_dump( $name );
		array_unshift( $args, $name );
		return call_user_func_array( array( $this, 'query' ), $args );
	}

	/**
	 * Query referenced table. Suffix "List" gets many rows
	 *
	 * @param string $name
	 * @param string|array|null $where
	 * @param array $params
	 * @return SQL
	 */
	function query( $name, $where = null, $params = array() ) {

		$schema = $this->db->getSchema();
		$fullName = $name;
		$name = preg_replace( '/List$/', '', $fullName );
		$table = $schema->getAlias( $name );
		$single = $name === $fullName;

		if ( $single ) {
			$key = $schema->getPrimary( $table );
			$parentKey = $schema->getReference( $this->getTable(), $name );
		} else {
			$key = $schema->getBackReference( $this->getTable(), $name );
			$parentKey = $schema->getPrimary( $this->getTable() );
		}

		$query = $this->db->query( $table )->eager(
			$key,
			$this->exec()->getKeys( $parentKey ),
			$this->getTable(),
			$parentKey,
			$single
		);

		if ( $where !== null ) return $query->where( $where, $params );
		return $query;

	}

	//

	/**
	 * Add a SELECT expression
	 *
	 * @param string|array $expr
	 * @param string|array $params
	 * @return Result
	 */
	function select( $expr ) {
		$before = (string) @$this->params[ '_select' ] === '*' ? array() : $this->params[ '_select' ];
		return $this->bind( array(
			'_select' => array_merge( $before, func_get_args() )
		) );
	}

	/**
	 * Add a WHERE condition (multiple are combined with AND)
	 *
	 * @param string|array $condition
	 * @param string|array $params
	 * @return Result
	 */
	function where( $condition, $params = array() ) {
		return $this->bind( array(
			'_where' => $this->db->where( $condition, $params, @$this->params[ '_where' ] )
		) );
	}

	/**
	 * Add a "$column is not $value" condition to WHERE (multiple are combined with AND)
	 *
	 * @param string|array $column
	 * @param string|array|null $value
	 * @return $this
	 */
	function whereNot( $key, $value = null ) {
		return $this->bind( array(
			'_where' => $this->db->whereNot( $key, $value, @$this->params[ '_where' ] )
		) );
	}

	/**
	 * Add an ORDER BY column and direction
	 *
	 * @param string $column
	 * @param string $direction
	 * @return $this
	 */
	function orderBy( $column, $direction = "ASC" ) {
		return $this->bind( array(
			'_orderBy' => $this->db->orderBy( $column, $direction, @$this->params[ '_orderBy' ] )
		) );
	}

	/**
	 * Set a result limit and optionally an offset
	 *
	 * @param int $count
	 * @param int|null $offset
	 * @return $this
	 */
	function limit( $count = null, $offset = null ) {
		return $this->bind( array(
			'_limit' => $this->db->limit( $count, $offset )
		) );
	}

	/**
	 * Set a paged limit
	 * Pages start at 1
	 *
	 * @param int $pageSize
	 * @param int $page
	 * @return $this
	 */
	function paged( $pageSize, $page ) {
		return $this->limit( $pageSize, ( $page - 1 ) * $pageSize );
	}

	/**
	 *
	 */
	function eager( $key, $value, $parentTable, $parentKey, $single ) {
		$clone = clone $this;
		$clone->eager = $this->db->createEager( $clone, $key, $value, $parentTable, $parentKey, $single );
		return $clone;
	}

	/**
	 *
	 */
	function via( $key ) {
		$clone = clone $this;
		$clone->eager = $this->eager->via( $key );
		return $clone;
	}

	/**
	 * Return primary table of this fragment
	 *
	 * @return string|null
	 */
	function getTable() {
		return @$this->params[ 'table' ];
	}

	/**
	 * @return Database
	 */
	function getDatabase() {
		return $this->db;
	}

	/**
	 * Get fragment params
	 *
	 * @return array
	 */
	function getParams() {
		return $this->params;
	}

	/**
	 * Get SQL string of this fragment
	 *
	 * @return string
	 */
	function __toString() {
		return $this->sql;
	}

	//

	/**
	 * IteratorAggregate
	 *
	 * @return \ArrayIterator
	 */
	function getIterator() {
		return $this->exec()->getIterator();
	}

	/**
	 * Countable
	 */
	function count() {
		return $this->exec()->count();
	}

	/**
	 * JsonSerializable
	 */
	function jsonSerialize() {
		return $this->exec()->jsonSerialize();
	}

	//

	/**
	 * Return SQL fragment with resolved params
	 *
	 * @return SQL
	 */
	function resolve() {

		$resolved = '';
		$tokens = $this->getTokens( true );
		$count = count( $tokens );
		$unset = array();

		for ( $i = 0; $i < $count; ++$i ) {

			list( $type, $string ) = $tokens[ $i ];
			$p = null;
			$key = null;

			if ( $type === self::TOKEN_MARKER ) {

				$prefix = $string{ 0 };
				$key = substr( $string, 1 );

				if ( array_key_exists( $key, $this->params ) ) {

					$param = $this->params[ $key ];

					switch ( $prefix ) {
					case '&':
						$p = $this->db->quoteIdentifier( $param );
						break;
					case ':':
						// only resolve non-preparable params here
						if ( is_array( $param ) ) {
							$p = $this->db->quoteValue( $param );
						} else if ( $param instanceof SQL ) {
							$p = $param->resolve();
						}
						break;
					}

				} else if ( $prefix === '&' ) {
					var_dump( $key );
					throw new Exception( 'Undefined parameter ' + $key );
				}

			}

			if ( $p !== null ) {
				$unset[] = $key;
				$resolved .= $p;
			} else {
				$resolved .= $string;
			}

		}

		$params = $this->params;
		foreach ( $unset as $key ) unset( $params[ $key ] );

		return $this->db->createSQL( $resolved, $params );

	}

	/**
	 * This is probably a non-standard, insane hack. Works great though.
	 * TODO Needs rigorous testing
	 *
	 * @param boolean $whitespace Set to also get whitespace tokens
	 * @return array
	 */
	function getTokens( $whitespace = false ) {

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
			$string = $match[ 0 ];

			switch ( $type ) {
			case self::TOKEN_WHITESPACE:
			case self::TOKEN_COMMENT_LINE:
			case self::TOKEN_COMMENT_C:
				break;
			case self::TOKEN_WORD:
			case self::TOKEN_BACKTICK_QUOTED:
			case self::TOKEN_DOUBLE_QUOTED:
			case self::TOKEN_BRACKET_QUOTED:
				$this->cleanTokens[] = array( $type, strtolower( $string ) );
				break;
			default:
				$this->cleanTokens[] = array( $type, $string );
			}

			$this->tokens[] = array( $type, $string );
			$sql = substr( $sql, strlen( $string ) );
		}

		return $whitespace ? $this->tokens : $this->cleanTokens;

	}

	//

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

	/** @var Database */
	protected $db;

	/** @var string */
	protected $sql;

	/** @var array */
	protected $params;

	/** @var array */
	protected $tokens;

	/** @var array */
	protected $cleanTokens;

	/** @var Eager|null */
	protected $eager;

	/** @var string */
	protected $parent;

}
