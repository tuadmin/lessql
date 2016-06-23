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

		$this->schema = new Schema( $this );

	}

	/**
	 * Returns an SQL fragment
	 *
	 * Examples:
	 * $db( "SELECT * FROM post" )
	 *
	 * @param string|Fragment $sql
	 * @param array $params
	 */
	function __invoke( $sql, $params = array() ) {
		return $this->createFragment( $sql, $params );
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

		var_dump( $name );

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

		$select = $this( "SELECT &select FROM &table WHERE &where &orderBy &limit", array(
			'select' => new Select( $this->db ),
			'table' => $name,
			'where' => new Conditional( $this->db ),
			'orderBy' => new OrderBy( $this->db ),
			'limit' =>  new Limit( $this->db )
		) );

		if ( $id !== null ) {

			if ( !is_array( $id ) ) {
				$table = $this->getSchema()->getAlias( $name );
				$primary = $this->getSchema()->getPrimary( $table );
				$id = array( $primary => $id );
			}

			return $select->where( $id )->first();

		}

		return $select;

	}

	// Factories

	/**
	 * Create an SQL statement, optionally with bound params
	 *
	 * @param string $name
	 * @param array $properties
	 * @param Result|null $result
	 * @return Fragment
	 */
	function createFragment( $sql, $params = array() ) {
		if ( $sql instanceof Fragment ) return $sql->bind( $params );
		return new Fragment( $this, $sql, $params );
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
	 * Optionally bind it to the given result.
	 *
	 * @param string $name
	 * @param array $properties
	 * @param Result|null $result
	 * @return Row
	 */
	function createRow( $properties = array(), $options = array() ) {
		return new Row( $properties, $options );
	}

	/**
	 * Create a result bound to $parent using table or association $name.
	 * $parent may be the database, a result, or a row
	 *
	 * @param Database|Result|Row $parent
	 * @param string $name
	 * @return Result
	 */
	function createResult( $statement, $source ) {
		return new Result( $statement, $source );
	}

	/**
	 * Create a migration
	 */
	function createMigration( $path ) {
		return new Migration( $this, $path );
	}

	/**
	 * Run a transaction
	 */
	function runTransaction( $fn ) {

		if ( !is_callable( $fn ) ) {
			throw new \LogicException( 'Transaction is not callable' );
		}

		$this->pdo->beginTransaction();

		try {
			$return = $fn();
			$this->pdo->commit();
			return $return;
		} catch ( \Exception $ex ) {
			$this->pdo->rollBack();
			throw $ex;
		}

	}

	// Common statements

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

		$columns = $this->getColumns( $rows );
		if ( empty( $columns ) ) return;

		$prepared = $this->insertHead( $table, $columns )
			->bind( array( 'suffix' => $this( "( ?" . str_repeat( ", ?", count( $columns ) - 1 ) . " )" ) ) )
			->prepare();

		foreach ( $rows as $row ) {

			$values = array();

			foreach ( $columns as $column ) {
				$values[] = (string) $this->formatValue( @$row[ $column ] );
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

		$columns = $this->getColumns( $rows );
		if ( empty( $columns ) ) return;

		$insert = $this->insertHead( $table, $columns );
		$lists = $this->getValueLists( $rows, $columns );
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

		$columns = $this->getColumns( $rows );
		if ( empty( $columns ) ) return;

		$insert = $this->insertHead( $table, $columns );
		$lists = $this->getValueLists( $rows, $columns );

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
			$set[] = $this->quoteIdentifier( $column ) . " = " . $this->quoteValue( $value );
		}

		if ( !is_array( $where ) ) $where = array( $where );
		if ( !is_array( $params ) ) $params = array_slice( func_get_args(), 3 );

		$params[ 'table' ] = $this->rewriteTable( $table );

		return $this->statement( "UPDATE &table SET " . implode( ", ", $set ) . $this->getSuffix( $where ), $params )->exec();

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

		return $this->exec( "DELETE FROM &table" . $this->getSuffix( $where ), $params );

	}

	// SQL utility, mainly used internally

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
	function getSet( $data ) {

		$set = array();

		foreach ( $data as $column => $value ) {
			$set[] = $this->quoteIdentifier( $column ) . " = " . $this->quoteValue( $value );
		}

		return $this( implode( ", ", $set ) );

	}

	/**
	 * Quote a value for SQL
	 *
	 * @param mixed $value
	 * @return string
	 */
	function quoteValue( $value ) {

		if ( is_array( $value ) ) {
			return implode( ", ", array_map( array( $this, 'quoteValue' ), $value ) );
		}

		$value = $this->formatValue( $value );

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

		return $this( implode( ".", $identifier ) );

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

	//

	/** @var \PDO */
	protected $pdo;

	/** @var string */
	protected $identifierDelimiter = '`';

	/** @var null|callable */
	protected $onQuery;

	/** @var Schema */
	protected $schema;

}
