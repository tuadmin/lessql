<?php

namespace LessQL;

/**
 * Represents the result of a SQL statement.
 * May contain rows and the number of affected rows.
 *
 * Immutable
 */
class Result implements \IteratorAggregate, \Countable, \JsonSerializable {

	/**
	 * Constructor. Internal
	 */
	function __construct( $statement, $source ) {
		$this->statement = $statement;

		if ( is_array( $source ) ) {
			$this->rows = $source;
		} else {
			try {
				$this->rows = $source->fetchAll( \PDO::FETCH_ASSOC );
			} catch ( \Exception $ex ) {
				// ignore
			}

			try {
				$this->affected = $source->rowCount();
			} catch ( \Exception $ex ) {
				// ignore
			}
		}

		$self = $this;
		$this->rows = array_map( function ( $data ) use ( $statement ) {
			return $statement->getContext()
				->createRow( $statement->getTable(), $data )
				->setClean();
		}, $this->rows );
		$this->count = count( $this->rows );
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

	/**
	 * Return first row in result, if any
	 *
	 * @return Row
	 */
	function first() {
		return $this->count > 0 ? $this->rows[ 0 ] : null;
	}

	/**
	 * Return number of affected rows
	 *
	 * @return int
	 */
	function affected() {
		return $this->affected;
	}

	/**
	 * @return string
	 */
	function getTable() {
		return $this->statement->getTable();
	}

	/**
	 * @return Context
	 */
	function getContext() {
		return $this->statement->getContext();
	}

	/**
	 * Internal
	 *
	 * @param string $key
	 * @return array
	 */
	function getKeys( $key ) {

		$keys = array();

		foreach ( $this->rows as $row ) {
			if ( $row->__isset( $key ) ) {
				$keys[] = $row->__get( $key );
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * @param array $data
	 * @return SQL
	 */
	function update( $data ) {
		return $this->getContext()
			->update( $this->getTable(), $data, $this->wherePrimary() );
	}

	/**
	 * @return SQL
	 */
	function delete() {
		return $this->getContext()
			->delete( $this->getTable(), $this->wherePrimary() );
	}

	/**
	 * @return SQL
	 */
	protected function wherePrimary() {

		$context = $this->getContext();
		$table = $this->getTable();
		$primary = $context->getStructure()->getPrimary( $table );

		if ( is_array( $primary ) ) {
			$or = array();
			foreach ( $this->rows as $row ) {
				$and = array();
				foreach ( $primary as $column ) {
					$and[] = $context->is( $column, $row->__get( $column ) );
				}
				$or[] = "( " . implode( " AND ", $and ) . " )";
			}
			return $context( implode( " OR ", $or ) );
		}

		return $context->where( $primary, $this->getKeys( $primary ) );

	}

	//

	/**
	 * IteratorAggregate
	 *
	 * @return \ArrayIterator
	 */
	function getIterator() {
		return new \ArrayIterator( $this->rows );
	}

	/**
	 * Countable
	 *
	 * @return int
	 */
	function count() {
		return $this->count;
	}

	/**
	 * JsonSerializable
	 *
	 * @return array
	 */
	function jsonSerialize() {
		$rows = array();
		foreach ( $this->rows as $row ) {
			$rows[] = $row->jsonSerialize();
		}
		return $rows;
	}

	//

	/** @var SQL */
	protected $statement;

	/** @var array */
	protected $rows = array();

	/** @var int */
	protected $count = 0;

	/** @var int */
	protected $affected = 0;

}
