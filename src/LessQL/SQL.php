<?php

namespace LessQL;

/**
 * Represents an arbitrary SQL fragment with bound params.
 * Can be prepared and executed.
 *
 * Immutable
 */
class SQL implements \IteratorAggregate, \Countable, \JsonSerializable {

	/**
	 * Constructor
	 *
	 * @param Context $context
	 */
	function __construct( $context, $sql, $params = array() ) {
		$this->context = $context;
		$this->sql = $sql;
		$this->params = $params;
	}

	/**
	 * @param array $params
	 * @return SQL
	 */
	function bind( $params, $value = null ) {
		if ( !is_array( $params ) ) {
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
		if ( $params !== null ) return $this->bind( $params )->prepare();
		return $this->context->createPrepared( $this );
	}

	/**
	 * Execute statement and return result
	 *
	 * @param array $params
	 * @return Result
	 */
	function exec( $params = null ) {
		if ( $params !== null ) return $this->bind( $params )->exec();
		if ( $this->eager ) return $this->eager->exec();
		return $this->context->exec( $this, $params );
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

		$schema = $this->context->getStructure();
		$fullName = $name;
		$name = preg_replace( '/List$/', '', $fullName );
		$table = $schema->getAlias( $name );
		$back = $name === $fullName;

		if ( $back ) {
			$query = $this->context->query( $table )->referencing( $this );
		} else {
			$query = $this->context->query( $table )->referencedBy( $this );
		}

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
		$before = (string) @$this->params[ 'select' ] === '*' ? array() : $this->params[ 'select' ];
		return $this->bind( array(
			'select' => array_merge( $before, $this->quoteIdentifier( func_get_args() ) )
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
			'where' => $this->context->where( $condition, $params, @$this->params[ 'where' ] )
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
			'where' => $this->context->whereNot( $key, $value, @$this->params[ 'where' ] )
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
			'orderBy' => $this->context->orderBy( $column, $direction, @$this->params[ 'orderBy' ] )
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
			'limit' => $this->context->limit( $count, $offset )
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
	function referencedBy( $other ) {
		$clone = clone $this;
		$clone->eager = $this->context->createEager( $this, $other, true );
		return $clone;
	}

	/**
	 *
	 */
	function referencing( $other ) {
		$clone = clone $this;
		$clone->eager = $this->context->createEager( $this, $other );
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
	 *
	 */
	function late() {
		$clone = clone $this;
		$clone->eager = null;
		return $clone;
	}

	/**
	 *
	 */
	function update( $data ) {
		return $this->exec()->update( $data );
	}

	/**
	 *
	 */
	function delete() {
		return $this->exec()->delete();
	}

	/**
	 * Return primary table of this fragment
	 *
	 * @return string|null
	 */
	function getTable() {
		$this->resolve();
		return $this->table;
	}

	/**
	 * @return Context
	 */
	function getContext() {
		return $this->context;
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
		try {
			return $this->resolve()->sql;
		} catch ( \Exception $ex ) {
			var_dump( $ex );
		}
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
	 * Return SQL fragment with resolved params and tables rewritten
	 *
	 * @return SQL
	 */
	function resolve( $offset = 0 ) {

		if ( $this->resolved ) return $this->resolved;

		$context = $this->context;
		$resolved = '';
		$params = array();
		$tokens = $this->getTokens();
		$count = count( $tokens );
		$q = 0;

		for ( $i = 0; $i < $count; ++$i ) {

			foreach ( $params as $key => $value ) {
				if ( $m = preg_match( '/p(\d+)/', $key ) ) {
					$offset = max( $offset, intval( $m[ 1 ] ) + 1 );
				}
			}

			list( $type, $string ) = $tokens[ $i ];
			$r = null;
			$key = substr( $string, 1 );

			switch ( $type ) {
			case self::TOKEN_QUESTION_MARK:
				if ( array_key_exists( $q, $this->params ) ) {
					$params[ 'p' . $offset ] = $this->params[ $q ];
					$r = ':p' . $offset;
				}
				++$q;
				break;

			case self::TOKEN_COLON_MARKER:
				if ( array_key_exists( $key, $this->params ) ) {
					$params[ 'p' . $offset ] = $this->params[ $key ];
					$r = ':p' . $offset;
				}
				break;

			case self::TOKEN_DOUBLE_QUESTION_MARK:
				if ( array_key_exists( $q, $this->params ) ) {
					$r = $context->quoteValue( $this->params[ $q ] );
				}
				++$q;
				break;

			case self::TOKEN_DOUBLE_COLON_MARKER:
				$key = substr( $key, 1 );
				if ( array_key_exists( $key, $this->params ) ) {
					$r = $context->quoteValue( $this->params[ $key ] );
				}
				break;

			case self::TOKEN_AMPERSAND_MARKER:
				if ( !$this->table ) {
					$this->table = $key;
				}
				$r = $context->quoteIdentifier( $context->getStructure()->rewrite( $key ) );
				break;
			}

			if ( $r instanceof SQL ) {
				if ( !$this->table && $r->getTable() ) {
					$this->table = $r->getTable();
				}
				$r = $r->resolve( $offset );
				$params = array_merge( $r->getParams(), $params );
			}

			$resolved .= $r === null ? $string : $r;

		}

		$this->resolved = $context( $resolved, $params );
		$this->resolved->resolved = $this->resolved;

		return $this->resolved;

	}

	/**
	 * This is probably a non-standard, insane hack. Works great though.
	 * TODO Needs rigorous testing
	 *
	 * @return array
	 */
	function getTokens() {

		if ( $this->tokens ) return $this->tokens;

		static $tokenizeRx;

		if ( !isset( $tokenizeRx ) ) {

			$rx = array(
				'(\s+)',                        // 1 whitespace
				'((?:--|#)[^\n]*)',             // 2 single line comment
				'(/\*.*?\*/)',                  // 3 c style comment
				"('(?:[^']|'')*')",             // 4 single quote quoted
				'("(?:[^"]|"")*")',             // 5 double quote quoted
				'(`(?:[^`]|``)*`)',             // 6 backtick quoted
				'(\[(?:[^\]])*\])',             // 7 bracket quoted
				'(\?\?)',                       // 8 double question mark
				'(\?)',                         // 9 question mark
				'(::[a-zA-Z_$][a-zA-Z0-9_$]*)', // 10 double colon marker
				'(:[a-zA-Z_$][a-zA-Z0-9_$]*)',  // 11 colon marker
				'(&[a-zA-Z_$][a-zA-Z0-9_$]*)',  // 12 ampersand marker
				'(\S)'                          // 13 other
			);

			$tokenizeRx = '(^' . implode( '|', $rx ) . ')s';

		}

		$this->tokens = array();
		$sql = $this->sql;
		$buffer = '';

		while ( preg_match( $tokenizeRx, $sql, $match ) ) {
			for ( $type = 1; strlen( $match[ $type ] ) === 0; ++$type );
			$string = $match[ 0 ];

			if ( $type === self::TOKEN_OTHER ) {
				$buffer .= $string;
			} else {
				if ( $buffer !== '' ) {
					$this->tokens[] = array( self::TOKEN_OTHER, $buffer );
					$buffer = '';
				}
				$this->tokens[] = array( $type, $string );
			}

			$sql = substr( $sql, strlen( $string ) );
		}

		if ( $buffer !== '' ) {
			$this->tokens[] = array( self::TOKEN_OTHER, $buffer );
		}

		return $this->tokens;

	}

	/**
	 *
	 */
	function __clone() {
		$this->resolved = null;
		$this->table = null;
	}

	//

	const TOKEN_WHITESPACE = 1;
	const TOKEN_LINE_COMMENT = 2;
	const TOKEN_C_COMMENT = 3;

	const TOKEN_SINGLE_QUOTED = 4;
	const TOKEN_DOUBLE_QUOTED = 5;
	const TOKEN_BACKTICK_QUOTED = 6;
	const TOKEN_BRACKET_QUOTED = 7;

	const TOKEN_DOUBLE_QUESTION_MARK = 8;
	const TOKEN_QUESTION_MARK = 9;
	const TOKEN_DOUBLE_COLON_MARKER = 10;
	const TOKEN_COLON_MARKER = 11;
	const TOKEN_AMPERSAND_MARKER = 12;

	const TOKEN_OTHER = 13;

	/** @var Context */
	protected $context;

	/** @var string */
	protected $sql;

	/** @var array */
	protected $params;

	/** @var Eager|null */
	protected $eager;

	/** @var array */
	protected $tokens;

	/** @var SQL */
	protected $resolved;

	/** @var string */
	protected $table;

}
