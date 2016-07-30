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
	function __construct( $context, $sql, array $params = array() ) {
		$this->context = $context;
		$this->sql = $sql;
		$this->params = $params;
	}

	/**
	 * Return a new SQL fragment with the given parameter(s)
	 *
	 * @param array|string $params
	 * @param mixed $value
	 * @return SQL
	 */
	function bind( $params, $value = null ) {
		if ( !is_array( $params ) ) {
			return $this->bind( array( $params => $value ) );
		}
		$clone = clone $this;
		foreach ( $params as $key => $value ) {
			$clone->params[ $key ] = $value;
		}
		return $clone;
	}

	/**
	 * Return resolved SQL containing prepared PDO statement
	 *
	 * @param array $params
	 * @return SQL
	 */
	function prepare() {
		if ( $this->pdoStatement ) return $this;
		$prepared = $this->resolve();
		$prepared->pdoStatement = $this->getContext()->getPdo()
			->prepare( (string) $prepared );
		return $prepared;
	}

	/**
	 * Execute statement and return result
	 *
	 * @param array $params
	 * @return Result
	 */
	function exec( $params = null ) {
		if ( $params !== null ) return $this->bind( $params )->exec();
		if ( $this->referencedBy || $this->referencing ) return $this->execRef();
		return $this->getContext()->exec( $this );
	}

	/**
	 * Execute statement using referencedBy/referencing.
	 * Eagerly loaded. Internal
	 *
	 * @return Result
	 */
	protected function execRef() {

		$other = $this->referencedBy ?: $this->referencing;
		$context = $this->getContext();
		$structure = $context->getStructure();
		$table = $this->getTable();
		$otherTable = $other->getTable();
		$via = $this->via;

		if ( $this->referencedBy ) {
			$key = $structure->getPrimary( $table );
			$otherKey = $via ?: $structure->getReference( $otherTable, $table );
		} else {
			$key = $via ?: $structure->getBackReference( $table, $otherTable );
			$otherKey = $structure->getPrimary( $otherTable );
		}

		$eager = clone $this;
		$eager->referencedBy = null;
		$eager->referencing = null;
		$eager->via = null;

		if ( $other instanceof SQL ) $other->exec();

		$eager = $eager->where(
			$key,
			$context->getKnownKeys( $other->getTable(), $otherKey )
		);

		$otherKeys = $other->getKeys( $otherKey );
		$rows = array();
		foreach ( $eager as $row ) {
			if ( in_array( $row[ $key ], $otherKeys ) ) {
				$rows[] = $row;
			}
		}

		return $context->createResult( $this, $rows );

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
	 *
	 * @return Row|null
	 */
	function first() {
		return $this->exec()->first();
	}

	/**
	 * Executed and return affected rows, if any
	 *
	 * @return int
	 */
	function affected() {
		return $this->exec()->affected();
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

		$structure = $this->getContext()->getStructure();
		if ( !$structure->hasTableOrAlias( $name ) ) {
			throw new Exception( 'Unknown table/alias: ' . $name );
		}

		array_unshift( $args, $name );
		return call_user_func_array( array( $this, 'query' ), $args );
	}

	/**
	 * Query referenced table. Suffix "List" gets many rows
	 *
	 * @param string $name
	 * @param string|array $where
	 * @param array $params
	 * @return SQL
	 */
	function query( $name, $where = array(), $params = array() ) {
		return $this->getContext()->queryRef( $this, $name, $where, $params );
	}

	//

	/**
	 * Add a SELECT expression
	 *
	 * @param string|SQL $expr
	 * @return SQL
	 */
	function select( $expr ) {
		$before = (string) @$this->params[ 'select' ];
		if ( !$before || (string) $before === '*' ) {
			$before = '';
		} else {
			$before .= ', ';
		}

		return $this->bind( array(
			'select' => $this->context->createSQL(
				$before . $this->context->quoteIdentifier( func_get_args() )
			)
		) );
	}

	/**
	 * Add a WHERE condition (multiple are combined with AND)
	 *
	 * @param string|array $condition
	 * @param mixed|array $params
	 * @return SQL
	 */
	function where( $condition, $params = array() ) {
		return $this->bind( array(
			'where' => $this->context->where( $condition, $params, @$this->params[ 'where' ] )
		) );
	}

	/**
	 * Return new SQL with a "$column is not $value" condition added to WHERE (multiple are combined with AND)
	 *
	 * @param string|array $column
	 * @param mixed $value
	 * @return SQL
	 */
	function whereNot( $key, $value = null ) {
		return $this->bind( array(
			'where' => $this->context->whereNot( $key, $value, @$this->params[ 'where' ] )
		) );
	}

	/**
	 * Return new SQL with added ORDER BY column and direction
	 *
	 * @param string $column
	 * @param string $direction
	 * @return SQL
	 */
	function orderBy( $column, $direction = "ASC" ) {
		return $this->bind( array(
			'orderBy' => $this->context->orderBy( $column, $direction, @$this->params[ 'orderBy' ] )
		) );
	}

	/**
	 * Return new SQL with result limit and optionally an offset
	 *
	 * @param int|null $count
	 * @param int|null $offset
	 * @return SQL
	 */
	function limit( $count = null, $offset = null ) {
		return $this->bind( array(
			'limit' => $this->context->limit( $count, $offset )
		) );
	}

	/**
	 * Return new SQL with paged limit.
	 * Pages start at 1
	 *
	 * @param int $pageSize
	 * @param int $page
	 * @return SQL
	 */
	function paged( $pageSize, $page ) {
		return $this->limit( $pageSize, ( $page - 1 ) * $pageSize );
	}

	/**
	 * Return new SQL filtered by references from $other.
	 * Eagerly loaded.
	 *
	 * @param Row|Result|SQL $other
	 * @return SQL
	 */
	function referencedBy( $other ) {
		$clone = clone $this;
		$clone->referencedBy = $other;
		$clone->referencing = null;
		$clone->via = null;
		return $clone;
	}

	/**
	 * Return new SQL filtered by references to $other.
	 * Eagerly loaded.
	 *
	 * @param Row|Result|SQL $other
	 * @return SQL
	 */
	function referencing( $other ) {
		$clone = clone $this;
		$clone->referencing = $other;
		$clone->referencedBy = null;
		$clone->via = null;
		return $clone;
	}

	/**
	 * Return new SQL using a different reference key
	 *
	 * @param string $key
	 * @return SQL
	 */
	function via( $key ) {
		$clone = clone $this;
		$clone->via = $key;
		return $clone;
	}

	/**
	 * @param array $data
	 * @return SQL
	 */
	function update( $data ) {
		return $this->exec()->update( $data );
	}

	/**
	 * @return SQL
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
		try {
			return $this->resolve()->table;
		} catch ( Exception $ex ) {
			// resolving might fail because of missing params
			// we can still return the table if it was set
			return $this->table;
		}
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
			return $ex->getMessage();
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
	 *
	 * @return int
	 */
	function count() {
		return $this->exec()->count();
	}

	/**
	 * JsonSerializable
	 *
	 * @return int
	 */
	function jsonSerialize() {
		return $this->exec()->jsonSerialize();
	}

	/**
	 * Internal
	 *
	 * @param string $key
	 * @return array
	 */
	function getKeys( $key ) {
		return $this->exec()->getKeys( $key );
	}

	/**
	 * Return PDO statement, if any. Internal
	 *
	 * @return \PDOStatement
	 */
	function getPdoStatement() {
		return $this->pdoStatement;
	}

	//

	/**
	 * Return SQL fragment with resolved params and tables rewritten
	 *
	 * @return SQL
	 */
	function resolve() {

		if ( $this->resolved ) return $this->resolved;

		$context = $this->context;
		$resolved = '';
		$params = array();
		$tokens = $this->getTokens();
		$count = count( $tokens );
		$q = 0;

		for ( $i = 0; $i < $count; ++$i ) {

			list( $type, $string ) = $tokens[ $i ];
			$r = $string;
			$key = substr( $string, 1 );

			switch ( $type ) {
			case self::TOKEN_QUESTION_MARK:
				if ( array_key_exists( $q, $this->params ) ) {
					$params[] = $this->params[ $q ];
				} else {
					$params[] = null;
				}
				++$q;
				break;

			case self::TOKEN_COLON_MARKER:
				if ( array_key_exists( $key, $this->params ) ) {
					$params[ $key ] = $this->params[ $key ];
				}
				break;

			case self::TOKEN_DOUBLE_QUESTION_MARK:
				if ( array_key_exists( $q, $this->params ) ) {
					$r = $context->quoteValue( $this->params[ $q ] );
				} else {
					throw new Exception( 'Unresolved parameter ' . $q );
				}
				++$q;
				break;

			case self::TOKEN_DOUBLE_COLON_MARKER:
				$key = substr( $key, 1 );
				if ( array_key_exists( $key, $this->params ) ) {
					$r = $context->quoteValue( $this->params[ $key ] );
				} else {
					throw new Exception( 'Unresolved parameter ' . $key );
				}
				break;

			case self::TOKEN_AMPERSAND_MARKER:
				if ( !$this->table ) $this->table = $key;
				$r = $context->quoteIdentifier( $context->getStructure()->rewrite( $key ) );
				break;
			}

			// handle fragment insertion
			if ( $r instanceof SQL ) {
				if ( !$this->table && $r->getTable() ) {
					$this->table = $r->getTable();
				}

				$r = $r->resolve();

				// merge fragment parameters
				// numbered params are appended
				// named params are merged only if the param does not exist yet
				foreach ( $r->getParams() as $key => $value ) {
					if ( is_int( $key ) ) {
						$params[] = $value;
					} else if ( !array_key_exists( $key, $this->params ) ) {
						$params[ $key ] = $value;
					}
				}
			}

			$resolved .= $r;

		}

		$this->resolved = $context( $resolved, $params );
		$this->resolved->table = $this->table;
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
		if ( $this->resolved !== $this ) {
			$this->resolved = null;
			$this->table = null;
		}
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

	/** @var Row|Result|SQL|null */
	protected $referencedBy;

	/** @var Row|Result|SQL|null */
	protected $referencing;

	/** @var string|null */
	protected $via;

	/** @var array */
	protected $tokens;

	/** @var SQL */
	protected $resolved;

	/** @var string */
	protected $table;

	/** @var \PDOStatement */
	protected $pdoStatement;

}
