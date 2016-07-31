<?php

namespace LessQL;

/**
 * Represents a database context,
 * capable of writing SQL statements and fragments.
 *
 * Essentially wraps a PDO connection with an improved API.
 * Also serves as a caching context.
 * Immutable, except for referenced Structure and caching.
 */
class Context extends EventEmitter {

	const VERSION = '1.0.0-beta1';

	/**
	 * Constructor. Sets PDO to exception mode.
	 *
	 * @param \PDO $pdo
	 * @param array $options
	 */
	function __construct( $pdo, array $options = array() ) {

		$this->connection = Connection::get( $pdo );
		$this->structure = isset( $options[ 'structure' ] ) ?
			$options[ 'structure' ] : new Structure();

	}

	/**
	 * Create an SQL fragment from a string and optional params
	 *
	 * Examples:
	 * $db( "SELECT * FROM &post WHERE id = ?", array( 1 ) )
	 *
	 * @param string|SQL $sql
	 * @param array $params
	 * @return SQL
	 */
	function __invoke( $sql = '', $params = array() ) {
		return $this->createSQL( $sql, $params );
	}

	/**
	 * Returns a basic SELECT query for table $name.
	 * If $id is given, return the row with that id.
	 *
	 * Examples:
	 * $db->user()->where( ... )
	 * $db->user( 1 )
	 *
	 * @param string $name
	 * @param array $args
	 * @return SQL|Row|null
	 */
	function __call( $name, $args ) {

		$structure = $this->getStructure();
		if ( !$structure->hasTable( $name ) ) {
			throw new Exception( 'Unknown table: ' . $name );
		}

		array_unshift( $args, $name );
		return call_user_func_array( array( $this, 'query' ), $args );
	}

	/**
	 * Returns a basic SELECT query for table $name.
	 * If $id is given, return the row with that id.
	 *
	 * @param $name
	 * @param int|null $id
	 * @return SQL|Row|null
	 */
	function query( $table, $id = null ) {

		$select = $this( 'SELECT ::select FROM ::table WHERE ::where ::orderBy ::limit', array(
			'select' => $this( '*' ),
			'table' => $this->table( $table ),
			'where' => $this->where(),
			'orderBy' => $this(),
			'limit' => $this()
		) );

		if ( $id !== null ) {

			if ( !is_array( $id ) ) {
				$table = $this->getStructure()->getAlias( $table );
				$primary = $this->getStructure()->getPrimary( $table );
				$id = array( $primary => $id );
			}

			return $select->where( $id )->first();

		}

		return $select;

	}

	/**
	 * Build an insert statement to insert a single row
	 *
	 * @param string $table
	 * @param array|\Traversable $row
	 * @return SQL
	 */
	function insert( $table, $row ) {
		return $this->insertBatch( $table, array( $row ) );
	}

	/**
	 * Build single batch statement to insert multiple rows
	 *
	 * Create a single statement with multiple value lists.
	 * Supports SQL fragment parameters, but not supported by all drivers.
	 *
	 * @param string $table
	 * @param array $rows
	 * @return SQL
	 */
	function insertBatch( $table, $rows ) {

		if ( count( $rows ) === 0 ) return $this( self::NOOP );

		$columns = $this->getColumns( $table, $rows );

		$lists = array();

		foreach ( $rows as $row ) {
			$values = array();
			foreach ( $columns as $column ) {
				if ( array_key_exists( $column, $row ) ) {
					$values[] = $this->quoteValue( $row[ $column ] );
				} else {
					$values[] = 'DEFAULT';
				}
			}
			$lists[] = $this( "( " . implode( ", ", $values ) . " )" );
		}

		return $this( 'INSERT INTO ::table ( ::columns ) VALUES ::values', array(
			'table' => $this->table( $table ),
			'columns' => $this->quoteIdentifier( $columns ),
			'values' => $lists
		) );

	}

	/**
	 * Insert multiple rows using a prepared statement (directly executed)
	 *
	 * Prepare a statement and execute it once per row using bound params.
	 * Does not support SQL fragments in row data.
	 *
	 * @param string $table
	 * @param array $rows
	 * @return Result The insert result for the last row
	 */
	function insertPrepared( $table, $rows ) {

		$result = $this( self::NOOP )->exec();

		if ( count( $rows ) === 0 ) return $result;

		$columns = $this->getColumns( $table, $rows );

		$prepared = $this( 'INSERT INTO ::table ( ::columns ) VALUES ::values', array(
			'table' => $this->table( $table ),
			'columns' => $this->quoteIdentifier( $columns ),
			'values' => $this( '( ?' . str_repeat( ', ?', count( $columns ) - 1 ) . ' )' )
		) )->prepare();

		foreach ( $rows as $row ) {
			$values = array();

			foreach ( $columns as $column ) {
				$values[] = (string) $this->formatValue( @$row[ $column ] );
			}

			$result = $prepared->exec( $values );
		}

		// return last result
		return $result;

	}

	/**
	 * Get list of all columns used in the given rows
	 *
	 * @param array $rows
	 * @return array
	 */
	protected function getColumns( $table, $rows ) {

		$columns = array();

		foreach ( $rows as $row ) {
			foreach ( $row as $column => $value ) {
				$columns[ $column ] = true;
			}
		}

		$columns = array_keys( $columns );

		if ( empty( $columns ) ) {
			$primary = $this->getStructure()->getPrimary( $table );
			$columns = is_array( $primary ) ? $primary : array( $primary );
		}

		return $columns;

	}

	/**
	 * Build an update statement
	 *
	 * UPDATE $table SET $data [WHERE $where]
	 *
	 * @param string $table
	 * @param array|\Traversable $data
	 * @param array|string $where
	 * @param array|mixed $params
	 * @return SQL
	 */
	function update( $table, $data, $where = array(), $params = array() ) {

		if ( empty( $data ) ) return $this( self::NOOP );

		return $this( 'UPDATE ::table SET ::set WHERE ::where ::limit', array(
			'table' => $this->table( $table ),
			'set' => $this->assign( $data ),
			'where' => $this->where( $where, $params ),
			'limit' => $this()
		) );

	}

	/**
	 * Build a delete statement
	 *
	 * DELETE FROM $table [WHERE $where]
	 *
	 * @param string $table
	 * @param array|string $where
	 * @param array|mixed $params
	 * @return SQL
	 */
	function delete( $table, $where = array(), $params = array() ) {

		return $this( 'DELETE FROM ::table WHERE ::where ::limit', array(
			'table' => $this->table( $table ),
			'where' => $this->where( $where, $params ),
			'limit' => $this()
		) );

	}

	/**
	 * Build a conditional expression fragment
	 *
	 * @param array|string $condition
	 * @param array|mixed $params
	 * @param SQL|null $before
	 * @return SQL
	 */
	function where( $condition = null, $params = array(), SQL $before = null ) {

		// empty condition evaluates to true
		if ( empty( $condition ) ) {
			return $before ? $before : $this( '1=1' );
		}

		// conditions in key-value array
		if ( is_array( $condition ) ) {
			$cond = $before;
			foreach ( $condition as $k => $v ) {
				$cond = $this->where( $k, $v, $cond );
			}
			return $cond;
		}

		// shortcut for basic "column is (in) value"
		if ( preg_match( '/^[a-z0-9_.`"]+$/i', $condition ) ) {
			$condition = $this->is( $condition, $params );
		} else {
			$condition = $this( $condition, $params );
		}

		if ( $before && (string) $before !== '1=1' ) {
			return $this( '(' . $before . ') AND ::__condition', $before->resolve()->getParams() )
				->bind( '__condition', $condition );
		}

		return $condition;

	}

	/**
	 * Build a negated conditional expression fragment
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param SQL|null $before
	 * @return SQL
	 */
	function whereNot( $key, $value = array(), $before = null ) {

		// key-value array
		if ( is_array( $key ) ) {
			$cond = $before;
			foreach ( $key as $k => $v ) {
				$cond = $this->whereNot( $k, $v, $cond );
			}
			return $cond;
		}

		// "column is not (in) value"
		$condition = $this->isNot( $key, $value );

		if ( $before && (string) $before !== '1=1' ) {
			return $this( '(' . $before . ') AND ::__condition', $before->resolve()->getParams() )
				->bind( '__condition', $condition );
		}

		return $condition;

	}

	/**
	 * Build an ORDER BY fragment
	 *
	 * @param string $column
	 * @param string $direction
	 * @param SQL|null $before
	 * @return SQL
	 */
	function orderBy( $column, $direction = 'ASC', $before = null ) {

		if ( !preg_match( '/^asc|desc$/i', $direction ) ) {
			throw new Exception( 'Invalid ORDER BY direction: ' . $direction );
		}

		return $this(
			( $before && (string) $before !== '' ? ( $before . ', ' ) : 'ORDER BY ' ) .
			$this->quoteIdentifier( $column ) . ' ' . $direction
		);

	}

	/**
	 * Build a LIMIT fragment
	 *
	 * @param int $count
	 * @param int $offset
	 * @return SQL
	 */
	function limit( $count = null, $offset = null ) {

		if ( $count !== null ) {

			$count = intval( $count );
			if ( $count < 1 ) throw new Exception( 'Invalid LIMIT count: ' + $count );

			if ( $offset !== null ) {
				$offset = intval( $offset );
				if ( $offset < 0 ) throw new Exception( 'Invalid LIMIT offset: ' + $offset );

				return $this( 'LIMIT ' . $count . ' OFFSET ' . $offset );
			}

			return $this( 'LIMIT ' . $count );
		}

		return $this();

	}

	/**
	 * Build an SQL condition expressing that "$column is $value",
	 * or "$column is in $value" if $value is an array. Handles null
	 * and fragments like new SQL( "NOW()" ) correctly.
	 *
	 * @param string $column
	 * @param mixed|array $value
	 * @param bool $not
	 * @return SQL
	 */
	function is( $column, $value, $not = false ) {

		$bang = $not ? '!' : '';
		$or = $not ? ' AND ' : ' OR ';
		$novalue = $not ? '1=1' : '0=1';
		$not = $not ? ' NOT' : '';

		// always treat value as array
		if ( !is_array( $value ) ) {
			$value = array( $value );
		}

		// always quote column identifier
		$column = $this->quoteIdentifier( $column );

		if ( count( $value ) === 1 ) {

			// use single column comparison if count is 1

			$value = $value[ 0 ];

			if ( $value === null ) {
				return $this( $column . ' IS' . $not . ' NULL' );
			} else {
				return $this( $column . ' ' . $bang . '= ' . $this->quoteValue( $value ) );
			}

		} else if ( count( $value ) > 1 ) {

			// if we have multiple values, use IN clause

			$values = array();
			$null = false;

			foreach ( $value as $v ) {

				if ( $v === null ) {
					$null = true;
				} else {
					$values[] = $this->quoteValue( $v );
				}

			}

			$clauses = array();

			if ( !empty( $values ) ) {
				$clauses[] = $column . $not . ' IN ( ' . implode( ', ', $values ) . ' )';
			}

			if ( $null ) {
				$clauses[] = $column . ' IS' . $not . ' NULL';
			}

			return $this( implode( $or, $clauses ) );

		}

		return $this( $novalue );

	}

	/**
	 * Build an SQL condition expressing that "$column is not $value"
	 * or "$column is not in $value" if $value is an array. Handles null
	 * and fragments like $db( "NOW()" ) correctly.
	 *
	 * @param string $column
	 * @param mixed|array $value
	 * @return SQL
	 */
	function isNot( $column, $value ) {
		return $this->is( $column, $value, true );
	}

	/**
	 * Build an assignment fragment, e.g. for UPDATE
	 *
	 * @param array|\Traversable $data
	 * @return SQL
	 */
	function assign( $data ) {

		$assign = array();

		foreach ( $data as $column => $value ) {
			$assign[] = $this->quoteIdentifier( $column ) . ' = ' . $this->quoteValue( $value );
		}

		return $this( implode( ', ', $assign ) );

	}

	/**
	 * Quote a value for SQL
	 *
	 * @param mixed $value
	 * @return SQL
	 */
	function quoteValue( $value ) {

		if ( is_array( $value ) ) {
			return $this( implode( ', ', array_map( array( $this, 'quoteValue' ), $value ) ) );
		}

		if ( $value instanceof SQL ) return $value;
		if ( $value === null ) return $this( 'NULL' );

		$value = $this->formatValue( $value );

		if ( is_int( $value ) ) $value = (string) $value;
		if ( is_float( $value ) ) $value = sprintf( '%F', $value );
		if ( $value === false ) $value = '0';
		if ( $value === true ) $value = '1';

		return $this( $this->getPdo()->quote( $value ) );

	}

	/**
	 * Format a value for SQL, e.g. DateTime objects
	 *
	 * @param mixed $value
	 * @return string
	 */
	function formatValue( $value ) {

		if ( $value instanceof \DateTime ) {
			$value = clone $value;
			$value->setTimeZone( new \DateTimeZone( 'UTC' ) );
			return $value->format( 'Y-m-d H:i:s' );
		}

		return $value;

	}

	/**
	 * Quote identifier(s)
	 *
	 * @param mixed $identifier
	 * @return SQL
	 */
	function quoteIdentifier( $identifier ) {

		if ( is_array( $identifier ) ) {
			return $this( implode( ', ', array_map( array( $this, 'quoteIdentifier' ), $identifier ) ) );
		}

		if ( $identifier instanceof SQL ) return $identifier;

		$delimiter = '"';
		$identifier = explode( '.', $identifier );

		$identifier = array_map(
			function( $part ) use ( $delimiter ) { return $delimiter . str_replace( $delimiter, $delimiter.$delimiter, $part ) . $delimiter; },
			$identifier
		);

		return $this( implode( '.', $identifier ) );

	}

	/**
	 * Validate and return a LessQL table reference
	 *
	 * @param string $name
	 * @return SQL A fragment with a & prefix
	 */
	function table( $name ) {
		if ( !preg_match( '(^[a-zA-Z_$][a-zA-Z0-9_$]+$)', $name ) ) {
			throw new Exception( 'Invalid table reference: ' . $name );
		}
		return $this( '&' . $name );
	}

	//

	/**
	 * Run a transaction
	 *
	 * Treats nested transactions as part of outer transaction
	 *
	 * @param callable $t The transaction body
	 * @return mixed The return value of $fn
	 */
	function runTransaction( $t ) {
		return $this->connection->runTransaction( $t, $this );
	}

	/**
	 * Query last insert id.
	 *
	 * For PostgreSQL, tries to infer the sequence name from the last statement
	 *
	 * @param string|null $sequence
	 * @return mixed|null
	 */
	function lastInsertId( $sequence = null ) {
		if ( !$sequence && $this->lastStatement ) {
			$sequence = $this->getStructure()
				->getSequence( $this->lastStatement->getTable() );
		}

		return $this->getPdo()->lastInsertId( $sequence );
	}

	// Factories

	/**
	 * Create an SQL statement, optionally with bound params
	 *
	 * @param string|SQL $name
	 * @param array $params
	 * @return SQL
	 */
	function createSQL( $sql = '', $params = array() ) {
		if ( $sql instanceof SQL ) return $sql->bind( $params );
		return new SQL( $this, $sql, $params );
	}

	/**
	 * Create a row from given properties
	 *
	 * @param string $table
	 * @param array $properties
	 * @return Row
	 */
	function createRow( $table, $properties = array() ) {
		return new Row( $this, $table, $properties );
	}

	/**
	 * Create a result from a statement and row data. Internal
	 *
	 * @param SQL $statement
	 * @param PDO|array $source
	 * @param string $insertId
	 * @return Result
	 */
	function createResult( $statement, $source ) {
		return new Result( $statement, $source );
	}

	/**
	 * Create a runner
	 *
	 * @param string $path
	 * @return Runner
	 */
	function createRunner( $table = null ) {
		return new Runner( $this, $table );
	}

	//

	/**
	 * Return wrapped PDO. Internal
	 *
	 * @return \PDO
	 */
	function getPdo() {
		return $this->connection->init();
	}

	/**
	 * Return structure manager
	 *
	 * @return Structure
	 */
	function getStructure() {
		return $this->structure;
	}

	//

	/**
	 * @param SQL|Result|Row $source
	 * @param string $name
	 * @param string|array $where
	 * @param array $params
	 * @return SQL
	 */
	function queryRef( $source, $name, $where = array(), $params = array() ) {

		$structure = $this->getStructure();
		$fullName = $name;
		$name = preg_replace( '/List$/', '', $fullName );
		$table = $structure->getAlias( $name );
		$single = $name === $fullName;
		$query = $this->query( $table );

		if ( $single ) {
			$query = $query->referencedBy( $source )
				->via( $structure->getReference( $source->getTable(), $name ) );
		} else {
			$query = $query->referencing( $source )
				->via( $structure->getBackReference( $source->getTable(), $name ) );
		}

		return $query->where( $where, $params );

	}

	/**
	 * Execute an SQL statement and return the result,
	 * or return result from cache. Internal
	 *
	 * @param SQL $sql
	 * @param array $params
	 * @return Result
	 */
	function exec( $statement, $params = array() ) {

		try {

			$statement = $this( $statement, $params );
			$resolved = $statement->resolve();

			if ( (string) $resolved === Context::NOOP ) {
				return $this->createResult( $statement, array() );
			}

			// cache key
			$key = json_encode( array(
				(string) $resolved,
				$resolved->getParams()
			) );

			// cache lookup
			if ( isset( $this->resultCache[ $key ] ) ) return $this->resultCache[ $key ];

			$this->emit( 'exec', $statement );
			$prepared = $resolved->prepare();
			$pdoStatement = $prepared->getPdoStatement();

			$pdoStatement->execute( $prepared->getParams() );

			// cache the result
			$result = $this->resultCache[ $key ] = $this->createResult( $statement, $pdoStatement );

			// track last executed statement for lastInsertId
			$this->lastStatement = $statement;

			return $result;

		} catch ( \Exception $ex ) {

			$this->emit( 'error', $statement );
			throw $ex;

		}

	}

	/**
	 * Get known keys from result cache. Internal
	 *
	 * @param string $table
	 * @param string $column
	 * @return array
	 */
	function getKnownKeys( $table, $column ) {

		$keys = array();

		foreach ( $this->resultCache as $result ) {
			if ( $result->getTable() === $table ) {
				foreach ( $result as $row ) {
					$value = $row[ $column ];
					if ( $value ) $keys[] = $value;
				}
			}
		}

		return array_values( array_unique( $keys ) );

	}

	/**
	 * Return new database context with empty cache
	 *
	 * @return Context
	 */
	function clear() {
		$clone = clone $this;
		$clone->resultCache = array();
		return $clone;
	}

	//

	/** @var Connection */
	protected $connection;

	/** @var Structure */
	protected $structure;

	/** @var array */
	protected $resultCache = array();

	/** @var SQL */
	protected $lastStatement;

	/** @var string */
	const NOOP = 'SELECT 1 WHERE 1=0';

}
