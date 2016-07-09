<?php

namespace LessQL;

/**
 * Represents a database context,
 * capable of writing SQL statements and fragments.
 *
 * Essentially wraps a PDO object with an improved API.
 * Also serves as a caching context.
 */
class Database {

	/**
	 * Constructor. Sets PDO to exception mode.
	 *
	 * @param \PDO $pdo
	 * @param array $options
	 */
	function __construct( $pdo, $options = array() ) {

		$pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

		$this->pdo = $pdo;
		$this->transactions = new Transactions( $pdo );
		$this->schema = new Schema( $this );
		$this->beforeExec = @$options[ 'beforeExec' ];

		if ( @$options[ 'identifierDelimiter' ] ) {
			$this->identifierDelimiter = $options[ 'identifierDelimiter' ];
		}

		try {
			$pdo->exec( "SET sql_mode='NO_BACKSLASH_ESCAPES'" );
		} catch ( \PDOException $ex ) {
			//var_dump( (string) $ex );
		}

		try {
			$pdo->exec( "SET standard_conforming_strings=on" );
		} catch ( \PDOException $ex ) {
			//var_dump( (string) $ex );
		}

	}

	/**
	 * Create an SQL fragment from a string
	 *
	 * Examples:
	 * $db( "SELECT * FROM post" )
	 *
	 * @param string|SQL $sql
	 * @param array $params
	 */
	function __invoke( $sql = '', $params = array() ) {
		return $this->createSQL( $sql, $params );
	}

	/**
	 * Returns a result for table $name.
	 * If $id is given, return the row with that id.
	 *
	 * Examples:
	 * $db->user()->where( ... )
	 * $db->user( 1 )
	 *
	 * @param string $name
	 * @param array $args
	 * @return Result|Row|null
	 */
	function __call( $name, $args ) {

		$schema = $this->getSchema();
		if ( !$schema->hasTable( $name ) ) {
			throw new Exception( 'Unknown table: ' . $name );
		}

		array_unshift( $args, $name );
		return call_user_func_array( array( $this, 'query' ), $args );
	}

	/**
	 * Returns basic SELECT query for table $name.
	 * If $id is given, return the row with that id.
	 *
	 * @param $name
	 * @param int|null $id
	 * @return Result|Row|null
	 */
	function query( $table, $id = null ) {

		$select = $this( "SELECT ::select FROM ::table WHERE ::where ::orderBy ::limit", array(
			'select' => $this( '*' ),
			'table' => $this->table( $table ),
			'where' => $this->where(),
			'orderBy' => $this(),
			'limit' => $this()
		) );

		if ( $id !== null ) {

			if ( !is_array( $id ) ) {
				$table = $this->getSchema()->getAlias( $table );
				$primary = $this->getSchema()->getPrimary( $table );
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
	 * @param array $row
	 * @return SQL
	 */
	function insert( $table, $row ) {
		return $this->insertBatch( $table, array( $row ) );
	}

	/**
	 * Build single batch statement to insert multiple rows
	 *
	 * Create a single statement with multiple value lists
	 * Supports SQL fragment parameters, but not supported by all drivers
	 *
	 * @param string $table
	 * @param array $rows
	 * @return SQL
	 */
	function insertBatch( $table, $rows ) {

		$columns = $this->getColumns( $rows );
		if ( empty( $columns ) ) return $this( self::NOOP );

		return $this( "INSERT INTO ::table ( ::columns ) VALUES ::values", array(
			'table' => $this->table( $table ),
			'columns' => $this->quoteIdentifier( $columns ),
			'values' => $this->getValueLists( $rows, $columns )
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
	 * @return Result
	 */
	function insertPrepared( $table, $rows ) {

		$result = $this( self::NOOP )->exec();

		$columns = $this->getColumns( $rows );
		if ( empty( $columns ) ) return $result;

		$prepared = $this( "INSERT INTO ::table ( ::columns ) VALUES ::values", array(
			'table' => $this->table( $table ),
			'columns' => $this->quoteIdentifier( $columns ),
			'values' => $this( "( ?" . str_repeat( ", ?", count( $columns ) - 1 ) . " )" )
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
	protected function getColumns( $rows ) {

		$columns = array();

		foreach ( $rows as $row ) {
			foreach ( $row as $column => $value ) {
				$columns[ $column ] = true;
			}
		}

		return array_keys( $columns );

	}

	/**
	 * Build lists of quoted values for INSERT
	 *
	 * @param array $rows
	 * @param array $columns
	 * @return array
	 */
	protected function getValueLists( $rows, $columns ) {

		$lists = array();

		foreach ( $rows as $row ) {
			$values = array();
			foreach ( $columns as $column ) {
				$values[] = $this->quoteValue( @$row[ $column ] );
			}
			$lists[] = $this( "( " . implode( ", ", $values ) . " )" );
		}

		return $lists;

	}

	/**
	 * Build an update statement
	 *
	 * UPDATE $table SET $data [WHERE $where]
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $where
	 * @param array $params
	 * @return SQL
	 */
	function update( $table, $data, $where = array(), $params = array() ) {

		if ( empty( $data ) ) return $this( self::NOOP );

		return $this( "UPDATE ::table SET ::set WHERE ::where ::limit", array(
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
	 * @param array $where
	 * @param array $params
	 * @return SQL
	 */
	function delete( $table, $where = array(), $params = array() ) {

		return $this( "DELETE FROM ::table WHERE ::where ::limit", array(
			'table' => $this->table( $table ),
			'where' => $this->where( $where, $params ),
			'limit' => $this()
		) );

	}

	/**
	 * Build a conditional expression fragment
	 */
	function where( $condition = null, $params = array(), $before = null ) {

		// empty condition evaluates to true
		if ( empty( $condition ) ) {
			return $this( $before ? $before : '1=1' );
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
			return $this( '(' . $before . ') AND ' . $condition );
		}

		return $condition;

	}

	/**
	 * Build a negated conditional expression fragment
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
			return $this( '(' . $before . ') AND ' . $condition );
		}

		return $condition;

	}

	/**
	 * Build an ORDER BY fragment
	 */
	function orderBy( $column, $direction = 'ASC', $before = null ) {

		if ( !preg_match( '/^asc|desc$/i', $direction ) ) {
			throw new Exception( 'Invalid ORDER BY direction: ' + $direction );
		}

		return $this(
			( $before && (string) $before !== '' ? ( $before . ', ' ) : 'ORDER BY ' ) .
			$this->quoteIdentifier( $column ) . ' ' . $direction
		);

	}

	/**
	 * Build a LIMIT fragment
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
	 * @param string|array $value
	 * @param bool $not
	 * @return string
	 */
	function is( $column, $value, $not = false ) {

		$bang = $not ? "!" : "";
		$or = $not ? " AND " : " OR ";
		$novalue = $not ? "1=1" : "0=1";
		$not = $not ? " NOT" : "";

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
				return $this( $column . " IS" . $not . " NULL" );
			} else {
				return $this( $column . " " . $bang . "= " . $this->quoteValue( $value ) );
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
				$clauses[] = $column . $not . " IN ( " . implode( ", ", $values ) . " )";
			}

			if ( $null ) {
				$clauses[] = $column . " IS" . $not . " NULL";
			}

			return $this( implode( $or, $clauses ) );

		}

		return $this( $novalue );

	}

	/**
	 * Build an SQL condition expressing that "$column is not $value"
	 * or "$column is not in $value" if $value is an array. Handles null
	 * and fragments like new SQL( "NOW()" ) correctly.
	 *
	 * @param string $column
	 * @param string|array $value
	 * @return string
	 */
	function isNot( $column, $value ) {
		return $this->is( $column, $value, true );
	}

	/**
	 * Build an assignment fragment, e.g. for UPDATE
	 */
	function assign( $data ) {

		$assign = array();

		foreach ( $data as $column => $value ) {
			$assign[] = $this->quoteIdentifier( $column ) . " = " . $this->quoteValue( $value );
		}

		return $this( implode( ", ", $assign ) );

	}

	/**
	 * Quote a value for SQL
	 *
	 * @param mixed $value
	 * @return string
	 */
	function quoteValue( $value ) {

		if ( is_array( $value ) ) {
			return $this( implode( ", ", array_map( array( $this, 'quoteValue' ), $value ) ) );
		}

		if ( $value instanceof SQL ) return $value;
		if ( $value === null ) return $this( 'NULL' );

		$value = $this->formatValue( $value );

		if ( is_int( $value ) ) $value = (string) $value;
		if ( is_float( $value ) ) $value = sprintf( '%F', $value );
		if ( $value === false ) $value = '0';
		if ( $value === true ) $value = '1';

		return $this( $this->pdo->quote( $value ) );

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
	 * Quote identifier
	 *
	 * @param string $identifier
	 * @return string
	 */
	function quoteIdentifier( $identifier ) {

		if ( is_array( $identifier ) ) {
			return $this( implode( ", ", array_map( array( $this, 'quoteIdentifier' ), $identifier ) ) );
		}

		if ( $identifier instanceof SQL ) return $identifier;

		$delimiter = $this->identifierDelimiter;

		if ( empty( $delimiter ) ) return $identifier;

		$identifier = explode( ".", $identifier );

		$identifier = array_map(
			function( $part ) use ( $delimiter ) { return $delimiter . str_replace( $delimiter, $delimiter.$delimiter, $part ) . $delimiter; },
			$identifier
		);

		return $this( implode( ".", $identifier ) );

	}

	/**
	 *
	 */
	function table( $name ) {
		if ( !preg_match( '([a-zA-Z_$][a-zA-Z0-9_$]+)', $name ) ) {
			throw new Exception( 'Invalid table reference: ' . $name );
		}
		return $this( '&' . $name );
	}

	//

	/**
	 * Run a transaction
	 */
	function runTransaction( $fn ) {
		return $this->transactions->run( $fn, $this );
	}

	// Factories

	/**
	 * Create an SQL statement, optionally with bound params
	 *
	 * @param string $name
	 * @param array $properties
	 * @param Result|null $result
	 * @return SQL
	 */
	function createSQL( $sql, $params = array() ) {
		if ( $sql instanceof SQL ) return $sql->bind( $params );
		return new SQL( $this, $sql, $params );
	}

	/**
	 * Create a prepared statement from a statement
	 *
	 * @param Statement $statement
	 * @return Prepared
	 */
	function createPrepared( $statement ) {
		return new Prepared( $statement );
	}

	/**
	 * Create a row from given properties.
	 *
	 * @param string $table
	 * @param array $properties
	 * @param Result|null $result
	 * @return Row
	 */
	function createRow( $table, $properties = array() ) {
		return new Row( $this, $table, $properties );
	}

	/**
	 * Create a result from a statement and row data
	 *
	 * @param SQL $statement
	 * @param PDO|array $source
	 * @param string $insertId
	 * @return Result
	 */
	function createResult( $statement, $source, $insertId = null ) {
		return new Result( $statement, $source, $insertId );
	}

	/**
	 * Create a migration
	 */
	function createMigration( $path ) {
		return new Migration( $this, $path );
	}

	/**
	 * Create an eager loading policy
	 */
	function createEager( $statement, $other, $back = false ) {
		return new Eager( $statement, $other, $back );
	}

	//

	/**
	 * Return wrapped PDO
	 * @return \PDO
	 */
	function getPdo() {
		return $this->pdo;
	}

	/**
	 * Return schema manager
	 * @return \PDO
	 */
	function getSchema() {
		return $this->schema;
	}

	/**
	 * Get identifier delimiter
	 *
	 * @return string
	 */
	function getIdentifierDelimiter() {
		return $this->identifierDelimiter;
	}

	//

	/**
	 * Internal. Execute an SQL statement and return the result,
	 * or return result from cache.
	 *
	 * @param SQL $sql
	 */
	function exec( $sql, $params = array() ) {

		$sql = $this( $sql, $params );
		$resolved = $sql->resolve();
		$string = (string) $resolved;

		// skip NOOP
		if ( $string === self::NOOP ) {
			return $this->createResult( $this(), array() );
		}

		// try cache
		$key = json_encode( array( $string, $resolved->getParams() ) );
		$result = @$this->resultCache[ $key ];
		if ( $result ) return $result;

		if ( $this->beforeExec ) {
			call_user_func( $this->beforeExec, $sql );
		}

		// execute statement via PDO
		$pdoStatement = $this->pdo->prepare( $string );
		$pdoStatement->execute( $resolved->getParams() );
		$sequence = $this->getSchema()->getSequence( $sql->getTable() );
		$insertId = $this->pdo->lastInsertId( $sequence );

		// write to cache
		$result = $this->resultCache[ $key ] = $this->createResult( $sql, $pdoStatement, $insertId );

		return $result;

	}

	/**
	 * Internal. Get known keys from result cache
	 */
	function getKnownKeys( $table, $column ) {

		$keys = array();

		foreach ( $this->resultCache as $result ) {
			if ( $result->getTable() === $table ) {
				foreach ( $result as $row ) {
					$keys[] = $row[ $column ];
				}
			}
		}

		return array_values( array_unique( $keys ) );

	}

	/**
	 * Return new database context with empty cache
	 */
	function clear() {
		$clone = clone $this;
		$clone->resultCache = array();
		return $clone;
	}

	//

	/** @var \PDO */
	protected $pdo;

	/** @var Schema */
	protected $schema;

	/** @var string */
	protected $identifierDelimiter = '`';

	/** @var Transactions */
	protected $transactions;

	/** @var null|callable */
	protected $beforeExec;

	/** @var array */
	protected $resultCache = array();

	/** @var string */
	const NOOP = 'SELECT 1 WHERE 1=0';

}
