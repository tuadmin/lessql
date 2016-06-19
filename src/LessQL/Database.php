<?php

namespace LessQL;

/**
 * Database object wrapping a PDO instance
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

		if ( @$options[ 'identifierDelimiter' ] ) $this->identifierDelimiter = $options[ 'identifierDelimiter' ];
		$this->onQuery = @$options[ 'onQuery' ];

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

		array_unshift( $args, $name );
		return call_user_func_array( array( $this, 'table' ), $args );

	}

	/**
	 * Returns a result for table $name.
	 * If $id is given, return the row with that id.
	 *
	 * @param $name
	 * @param int|null $id
	 * @return Result|Row|null
	 */
	function table( $name, $id = null ) {

		// ignore List suffix
		$name = preg_replace( '/List$/', '', $name );

		if ( $id !== null ) {

			$result = $this->createResult( $this, $name );

			if ( !is_array( $id ) ) {

				$table = $this->getAlias( $name );
				$primary = $this->getPrimary( $table );
				$id = array( $primary => $id );

			}

			return $result->where( $id )->fetch();

		}

		return $this->result( $this, $name );

	}

	// Factories

	/**
	 * Create an SQL statement, optionally with bound params
	 * @param string $name
	 * @param array $properties
	 * @param Result|null $result
	 * @return Fragment
	 */
	function fragment( $sql, $params = array() ) {
		return new Fragment( $this, $sql, $params );
	}

	/**
	 * Create a row from given properties.
	 * Optionally bind it to the given result.
	 *
	 * @param string $name
	 * @param array $properties
	 * @param Result|null $result
	 * @return Row
	 */
	function row( $name, $properties = array(), $result = null ) {
		return new Row( $this, $name, $properties, $result );
	}

	/**
	 * Create a result bound to $parent using table or association $name.
	 * $parent may be the database, a result, or a row
	 *
	 * @param Database|Result|Row $parent
	 * @param string $name
	 * @return Result
	 */
	function result( $statement, $source ) {
		return new Result( $statement, $source );
	}

	/**
	 * Create a migration
	 */
	function migration( $path ) {
		return new Migration( $this, $path );
	}

	// PDO interface

	/**
	 * Prepare an SQL statement
	 *
	 * @param string $query
	 * @return Statement
	 */
	function prepare( $statement, $params = array() ) {
		return $this->statement( $statement, $params )->prepare();
	}

	/**
	 * Execute an SQL statement directly
	 *
	 * @param string $query
	 * @return Statement
	 */
	function exec( $statement, $params = array() ) {
		return $this->statement( $statement, $params )->exec();
	}

	/**
	 * Begin a transaction
	 *
	 * @return bool
	 */
	function begin() {
		return $this->pdo->beginTransaction();
	}

	/**
	 * Commit changes of transaction
	 *
	 * @return bool
	 */
	function commit() {
		return $this->pdo->commit();
	}

	/**
	 * Rollback any changes during transaction
	 *
	 * @return bool
	 */
	function rollback() {
		return $this->pdo->rollBack();
	}

	// Common statements

	/**
	 * Select rows from a table
	 *
	 * @param string $table
	 * @param mixed $exprs
	 * @param array $where
	 * @param array $orderBy
	 * @param int|null $limitCount
	 * @param int|null $limitOffset
	 * @param array $params
	 * @return Result
	 */
	function select( $table, $options = array() ) {

		$options = array_merge( array(
			'expr' => null,
			'where' => array(),
			'orderBy' => array(),
			'limitCount' => null,
			'limitOffset' => null,
			'params' => array()
		), $options );

		$query = "SELECT ";

		if ( empty( $options[ 'expr' ] ) ) {

			$query .= "*";

		} else if ( is_array( $options[ 'expr' ] ) ) {

			$query .= implode( ", ", $options[ 'expr' ] );

		} else {

			$query .= $options[ 'expr' ];

		}

		$table = $this->rewriteTable( $table );
		$query .= " FROM " . $this->quoteIdentifier( $table );

		$query .= $this->suffix( $options[ 'where' ], $options[ 'orderBy' ], $options[ 'limitCount' ], $options[ 'limitOffset' ] );

		$this->onQuery( $query, $options[ 'params' ] );

		$statement = $this->prepare( $query );
		$statement->setFetchMode( \PDO::FETCH_ASSOC );
		$statement->execute( $options[ 'params' ] );

		return $statement;

	}

	/**
	 * Insert one ore more rows into a table
	 *
	 * The $method parameter selects one of the following insert methods:
	 *
	 * "prepared": Prepare a query and execute it once per row using bound params
	 *             Does not support Fragments in row data (PDO limitation)
	 *
	 * "batch":    Create a single query mit multiple value lists
	 *             Supports Fragments, but not supported everywhere
	 *
	 * default:    Execute one INSERT per row
	 *             Supports Fragments, supported everywhere, slow for many rows
	 *
	 * @param string $table
	 * @param array $rows
	 * @param string|null $method
	 * @return Result|null
	 */
	function insert( $table, $rows, $method = null ) {

		if ( empty( $rows ) ) return;
		if ( !isset( $rows[ 0 ] ) ) $rows = array( $rows );

		if ( $method === 'prepared' ) {
			return $this->insertPrepared( $table, $rows );
		} else if ( $method === 'batch' ) {
			return $this->insertBatch( $table, $rows );
		}

		return $this->insertDefault( $table, $rows );

	}

	/**
	 * Insert rows using a prepared query
	 *
	 * @param string $table
	 * @param array $rows
	 * @return Result|null
	 */
	protected function insertPrepared( $table, $rows ) {

		$columns = $this->columns( $rows );
		if ( empty( $columns ) ) return;

		$prepared = $this->insertHead( $table, $columns )
			->bind( array( 'suffix' => $this->fragment( "( ?" . str_repeat( ", ?", count( $columns ) - 1 ) . " )" ) ) )
			->prepare();

		foreach ( $rows as $row ) {

			$values = array();

			foreach ( $columns as $column ) {
				$values[] = (string) $this->format( @$row[ $column ] );
			}

			$statement->exec( $values );

		}

		return $statement;

	}

	/**
	 * Insert rows using a single batch query
	 *
	 * @param string $table
	 * @param array $rows
	 * @return Result|null
	 */
	protected function insertBatch( $table, $rows ) {

		$columns = $this->columns( $rows );
		if ( empty( $columns ) ) return;

		$insert = $this->insertHead( $table, $columns );
		$lists = $this->valueLists( $rows, $columns );
		$insert->append( implode( ", ", $lists ) )->exec();

	}

	/**
	 * Insert rows using one query per row
	 *
	 * @param string $table
	 * @param array $rows
	 * @return Result|null
	 */
	protected function insertDefault( $table, $rows ) {

		$columns = $this->columns( $rows );
		if ( empty( $columns ) ) return;

		$insert = $this->insertHead( $table, $columns );
		$lists = $this->valueLists( $rows, $columns );

		foreach ( $lists as $list ) {
			$statement = $insert->append( $list )->exec();
		}

		return $statement; // last statement is returned

	}

	/**
	 * Build head of INSERT query (without values)
	 *
	 * @param string $table
	 * @param array $columns
	 * @return string
	 */
	protected function insertHead( $table, $columns ) {

		return $this->statement( "INSERT INTO &table ( &columns ) VALUES ", array(
			'table' => $this->rewriteTable( $table )
		) );

	}

	/**
	 * Get list of all columns used in the given rows
	 *
	 * @param array $rows
	 * @return array
	 */
	protected function columns( $rows ) {

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
	protected function valueLists( $rows, $columns ) {

		$lists = array();

		foreach ( $rows as $row ) {
			$values = array();
			foreach ( $columns as $column ) {
				$values[] = $this->quote( @$row[ $column ] );
			}
			$lists[] = "( " . implode( ", ", $values ) . " )";
		}

		return $lists;

	}

	/**
	 * Execute update query and return result
	 *
	 * UPDATE $table SET $data [WHERE $where]
	 *
	 * @param string $table
	 * @param array $data
	 * @param array $where
	 * @param array $params
	 * @return null|Result
	 */
	function update( $table, $data, $where = array(), $params = array() ) {

		if ( empty( $data ) ) return;

		$set = array();

		foreach ( $data as $column => $value ) {
			$set[] = $this->quoteIdentifier( $column ) . " = " . $this->quote( $value );
		}

		if ( !is_array( $where ) ) $where = array( $where );
		if ( !is_array( $params ) ) $params = array_slice( func_get_args(), 3 );

		$params[ 'table' ] = $this->rewriteTable( $table );

		return $this->statement( "UPDATE &table SET " . implode( ", ", $set ) . $this->suffix( $where ), $params )->exec();

	}

	/**
	 * Execute delete query and return result
	 *
	 * DELETE FROM $table [WHERE $where]
	 *
	 * @param string $table
	 * @param array $where
	 * @param array $params
	 * @return Result
	 */
	function delete( $table, $where = array(), $params = array() ) {

		if ( !is_array( $where ) ) $where = array( $where );
		if ( !is_array( $params ) ) $params = array_slice( func_get_args(), 2 );

		$params[ 'table' ] = $this->rewriteTable( $table );

		return $this->exec( "DELETE FROM &table" . $this->suffix( $where ), $params );

	}

	// SQL utility, mainly used internally

	/**
	 * Return WHERE/LIMIT/ORDER statement suffix
	 *
	 * @param array $where
	 * @param array $orderBy
	 * @param int|null $limitCount
	 * @param int|null $limitOffset
	 * @return string
	 */
	function suffix( $where, $orderBy = array(), $limitCount = null, $limitOffset = null ) {

		$suffix = "";

		if ( !empty( $where ) ) {
			$w = array();
			foreach ( $where as $key => $condition ) {
				if ( !is_numeric( $key ) ) {
					$condition = $this->is( $key, $condition );
				}
				$w[] = $condition;
			}
			$suffix .= " WHERE " . implode( " AND ", $w );
		}

		if ( !empty( $orderBy ) ) {
			$suffix .= " ORDER BY " . implode( ", ", $orderBy );
		}

		if ( isset( $limitCount ) ) {
			$suffix .= " LIMIT " . intval( $limitCount );
			if ( isset( $limitOffset ) ) {
				$suffix .= " OFFSET " . intval( $limitOffset );
			}
		}

		return $this->fragment( $suffix );

	}

	/**
	 * Build an SQL condition expressing that "$column is $value",
	 * or "$column is in $value" if $value is an array. Handles null
	 * and fragments like new Fragment( "NOW()" ) correctly.
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
				return $this->fragment( $column . " IS" . $not . " NULL" );
			} else {
				return $this->fragment( $column . " " . $bang . "= " . $this->quote( $value ) );
			}

		} else if ( count( $value ) > 1 ) {

			// if we have multiple values, use IN clause

			$values = array();
			$null = false;

			foreach ( $value as $v ) {

				if ( $v === null ) {
					$null = true;
				} else {
					$values[] = $this->quote( $v );
				}

			}

			$clauses = array();

			if ( !empty( $values ) ) {
				$clauses[] = $column . $not . " IN ( " . implode( ", ", $values ) . " )";
			}

			if ( $null ) {
				$clauses[] = $column . " IS" . $not . " NULL";
			}

			return $this->fragment( implode( $or, $clauses ) );

		}

		return $this->fragment( $novalue );

	}

	/**
	 * Build an SQL condition expressing that "$column is not $value"
	 * or "$column is not in $value" if $value is an array. Handles null
	 * and fragments like new Fragment( "NOW()" ) correctly.
	 *
	 * @param string $column
	 * @param string|array $value
	 * @return string
	 */
	function isNot( $column, $value ) {
		return $this->is( $column, $value, true );
	}

	/**
	 * Build a SET instruction, e.g. for UPDATE
	 */
	function set( $data ) {

		$set = array();

		foreach ( $data as $column => $value ) {
			$set[] = $this->quoteIdentifier( $column ) . " = " . $this->quote( $value );
		}

		return $this->fragment( implode( ", ", $set ) );

	}

	/**
	 * Quote a value for SQL
	 *
	 * @param mixed $value
	 * @return string
	 */
	function quote( $value ) {

		if ( is_array( $value ) ) {
			return implode( ", ", array_map( array( $this, 'quote' ), $value ) );
		}

		$value = $this->format( $value );

		if ( $value === null ) return "NULL";
		if ( $value === false ) return "'0'";
		if ( $value === true ) return "'1'";
		if ( $value instanceof Fragment ) return (string) $value;

		if ( is_int( $value ) ) {
			return "'" . ( (string) $value ) . "'";
		}

		if ( is_float( $value ) ) {
			return "'" . sprintf( "%F", $value ) . "'";
		}

		return $this->fragment( $this->pdo->quote( $value ) );

	}

	/**
	 * Format a value for SQL, e.g. DateTime objects
	 *
	 * @param mixed $value
	 * @return string
	 */
	function format( $value ) {

		if ( $value instanceof \DateTime ) {
			return $value->format( "Y-m-d H:i:s" );
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
			return implode( ", ", array_map( array( $this, 'quoteIdentifier' ), $identifier ) );
		}

		if ( $identifier instanceof Fragment ) return (string) $identifier;

		$delimiter = $this->identifierDelimiter;

		if ( empty( $delimiter ) ) return $identifier;

		$identifier = explode( ".", $identifier );

		$identifier = array_map(
			function( $part ) use ( $delimiter ) { return $delimiter . str_replace( $delimiter, $delimiter.$delimiter, $part ) . $delimiter; },
			$identifier
		);

		return $this->fragment( implode( ".", $identifier ) );

	}

	// SQL style

	/**
	 * Get identifier delimiter
	 *
	 * @return string
	 */
	function identifierDelimiter() {

		return $this->identifierDelimiter;

	}

	//

	/**
	 * Calls the query callback, if any
	 *
	 * @param string $query
	 * @param array $params
	 */
	function onQuery( $query, $params = array() ) {

		if ( $this->onQuery ) {
			call_user_func( $this->onQuery, $query, $params );
		}

	}

	//

	/** @var string */
	protected $identifierDelimiter = '`';

	/** @var null|callable */
	protected $onQuery;

}
