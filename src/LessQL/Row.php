<?php

namespace LessQL;

/**
 * Represents a database row. May contain nested rows
 */
class Row implements \ArrayAccess, \IteratorAggregate, \JsonSerializable {

	/**
	 * Constructor. Internal, use $context->createRow() instead
	 *
	 * @param Context $context The database context
	 * @param string|null $table The source table of this row, if any
	 * @param array $properties Associative row data, may include referenced rows
	 */
	function __construct( $context, $table, $properties = array() ) {
		$this->_context = $context;
		$this->_table = $table;
		$this->setData( $properties );
	}

	/**
	 * Get a property
	 *
	 * @param string $column
	 * @return mixed
	 */
	function &__get( $column ) {

		if ( !isset( $this->_properties[ $column ] ) ) {
			$null = null;
			return $null;
		}

		return $this->_properties[ $column ];

	}

	/**
	 * Set a property
	 *
	 * @param string $column
	 * @param mixed $value
	 */
	function __set( $column, $value ) {

		if ( isset( $this->_properties[ $column ] ) && $this->_properties[ $column ] === $value ) {
			return;
		}

		// convert arrays to Rows or list of Rows

		if ( is_array( $value ) ) {

			$name = preg_replace( '/List$|_list$/', '', $column );
			$table = $this->getContext()->getStructure()->getAlias( $name );

			if ( $name === $column ) { // row

				$value = $this->getContext()->createRow( $table, $value );

			} else { // list

				foreach ( $value as $i => $v ) {
					$value[ $i ] = $this->getContext()->createRow( $table, $v );
				}

			}

		}

		$this->_properties[ $column ] = $value;
		$this->_modified[ $column ] = $value;

	}

	/**
	 * Check if property is not null
	 *
	 * @param string $column
	 * @return bool
	 */
	function __isset( $column ) {
		return isset( $this->_properties[ $column ] );
	}

	/**
	 * Remove a property from this row
	 * Property will be ignored when saved, different to setting to null
	 *
	 * @param string $column
	 * @return void
	 */
	function __unset( $column ) {
		unset( $this->_properties[ $column ] );
		unset( $this->_modified[ $column ] );
	}

	/**
	 * Get referenced row(s) by name. Suffix "List" gets many rows using
	 * a back reference.
	 *
	 * @param string $name
	 * @param array $args
	 * @return mixed
	 */
	function __call( $name, $args ) {
		array_unshift( $args, $name );
		return call_user_func_array( array( $this, 'query' ), $args );
	}

	/**
	 * Query referenced data.
	 * Suffix "List" gets many rows using a back reference.
	 *
	 * @param string $name
	 * @param string|array|null $where
	 * @param array $params
	 * @return Result
	 */
	function query( $name, $where = null, $params = array() ) {

		$schema = $this->getContext()->getStructure();
		$fullName = $name;
		$name = preg_replace( '/List$/', '', $fullName );
		$table = $schema->getAlias( $name );
		$single = $name === $fullName;
		$query = $this->getContext()->query( $table );

		if ( $single ) {
			$query = $query->referencedBy( $this )
				->via( $schema->getReference( $this->getTable(), $name ) );
		} else {
			$query = $query->referencing( $this )
				->via( $schema->getBackReference( $this->getTable(), $name ) );
		}

		if ( $where !== null ) return $query->where( $where, $params );

		return $query;

	}

	/**
	 * Get the row's id
	 *
	 * @return string|array
	 */
	function getId() {

		$primary = $this->getContext()->getStructure()->getPrimary( $this->getTable() );

		if ( is_array( $primary ) ) {

			$id = array();

			foreach ( $primary as $column ) {

				if ( !isset( $this[ $column ] ) ) return null;

				$id[ $column ] = $this[ $column ];

			}

			return $id;

		}

		return $this[ $primary ];

	}

	/**
	 * Get row data
	 *
	 * @return array
	 */
	function getData() {

		$data = array();

		foreach ( $this->_properties as $column => $value ) {
			if ( $value instanceof Row || is_array( $value ) ) continue;
			$data[ $column ] = $value;
		}

		return $data;

	}

	/**
	 * Set row data (extends the row)
	 *
	 * @param array $data
	 * @return $this
	 */
	function setData( $data ) {

		foreach ( $data as $column => $value ) {
			$this->__set( $column, $value );
		}

		return $this;

	}

	/**
	 * Get the original id
	 *
	 * @return string|array
	 */
	function getOriginalId() {
		return $this->_originalId;
	}

	/**
	 * Get modified data
	 *
	 * @return array
	 */
	function getModified() {

		$modified = array();

		foreach ( $this->_modified as $column => $value ) {

			if ( $value instanceof Row || is_array( $value ) ) {
				continue;
			}

			$modified[ $column ] = $value;

		}

		return $modified;

	}

	/**
	 * Save this row
	 * Also saves nested rows if $recursive is true (default)
	 *
	 * @param bool $recursive
	 * @return $this
	 * @throws Exception
	 */
	function save( $recursive = true ) {

		$context = $this->getContext();
		$schema = $context->getStructure();
		$table = $this->getTable();

		if ( !$recursive ) { // just save the row

			$this->updateReferences();

			if ( !$this->isClean() ) {

				$primary = $schema->getPrimary( $table );

				if ( $this->exists() ) {

					$idCondition = $this->getOriginalId();

					if ( !is_array( $idCondition ) ) {
						$idCondition = array( $primary => $idCondition );
					}

					$context->update( $table, $this->getModified(), $idCondition )->exec();
					$this->setClean();

				} else {

					$result = $context->insert( $table, $this->getData() )->exec();

					if ( !is_array( $primary ) && !isset( $this[ $primary ] ) ) {
						$id = $result->getInsertId();
						if ( isset( $id ) ) $this[ $primary ] = $id;
					}

					$this->setClean();

				}

			}

			return $this;

		}

		// make list of all rows in this tree

		$list = array();
		$this->listRows( $list );
		$count = count( $list );

		// keep iterating and saving until all references are known

		while ( true ) {

			$solvable = false;
			$clean = 0;

			foreach ( $list as $row ) {

				$row->updateReferences();

				$missing = $row->getMissing();

				if ( empty( $missing ) ) {

					$row->save( false );
					$row->updateBackReferences();
					$solvable = true;

				}

				if ( $row->isClean() ) ++$clean;

			}

			if ( !$solvable ) {

				throw new Exception(
					'Cannot recursively save structure (' . $table . ') - add required values or allow NULL'
				);

			}

			if ( $clean === $count ) break;

		}

		return $this;

	}

	/**
	 * @param array $list
	 */
	protected function listRows( &$list ) {

		$list[] = $this;

		foreach ( $this->_properties as $column => $value ) {

			if ( $value instanceof Row ) {

				$value->listRows( $list );

			} else if ( is_array( $value ) ) {

				foreach ( $value as $row ) {
					$row->listRows( $list );
				}

			}

		}

	}

	/**
	 * Check references and set respective keys
	 * Returns list of keys to unknown references
	 *
	 * @return array
	 */
	function updateReferences() {

		$unknown = array();
		$context = $this->getContext();

		foreach ( $this->_properties as $column => $value ) {

			if ( $value instanceof Row ) {

				$key = $context->getStructure()->getReference( $this->getTable(), $column );
				$this[ $key ] = $value->getId();

			}

		}

		return $unknown;

	}

	/**
	 * Check back references and set respective keys
	 *
	 * @return $this
	 */
	function updateBackReferences() {

		$id = $this->getId();

		if ( is_array( $id ) ) return $this;

		$context = $this->getContext();

		foreach ( $this->_properties as $column => $value ) {

			if ( is_array( $value ) ) {

				$key = $context->getStructure()->getBackReference( $this->getTable(), $column );

				foreach ( $value as $row ) {
					$row->{ $key } = $id;
				}

			}

		}

		return $this;

	}

	/**
	 * Get missing columns, i.e. any that is null but required by the schema.
	 * Internal
	 *
	 * @return array
	 */
	function getMissing() {

		$missing = array();
		$required = $this->getContext()->getStructure()->getRequired( $this->getTable() );

		foreach ( $required as $column => $true ) {

			if ( !isset( $this[ $column ] ) ) {
				$missing[] = $column;
			}

		}

		return $missing;

	}

	/**
	 * Update this row directly
	 *
	 * @param $data
	 * @param bool $recursive
	 * @return $this
	 */
	function update( $data, $recursive = true ) {
		return $this->setData( $data )->save( $recursive );
	}

	/**
	 * Delete this row
	 *
	 * @return $this
	 */
	function delete() {

		$context = $this->getContext();
		$table = $this->getTable();
		$idCondition = $this->getOriginalId();

		if ( $idCondition === null ) return $this;

		if ( !is_array( $idCondition ) ) {
			$primary = $context->getStructure()->getPrimary( $table );
			$idCondition = array( $primary => $idCondition );
		}

		$context->delete( $table, $idCondition )->exec();
		$this->_originalId = null;
		return $this->setDirty();

	}

	/**
	 * Does this row exist?
	 *
	 * @return bool
	 */
	function exists() {
		return $this->_originalId !== null;
	}

	/**
	 * Is this row clean, i.e. in sync with the database?
	 *
	 * @return bool
	 */
	function isClean() {
		return empty( $this->_modified );
	}

	/**
	 * Set this row to "clean" state, i.e. in sync with database
	 *
	 * @return $this
	 */
	function setClean() {

		$id = $this->getId();

		$this->_originalId = $id;
		$this->_modified = array();

		return $this;

	}

	/**
	 * Set this row to "dirty" state, i.e. out of sync with database
	 *
	 * @return $this
	 */
	function setDirty() {
		$this->_modified = $this->_properties; // copy...
		return $this;
	}

	function getKeys( $key ) {
		return array( $this[ $key ] );
	}

	/**
	 * Get the database context
	 *
	 * @return Context
	 */
	function getContext() {
		return $this->_context;
	}

	/**
	 * Get the table
	 *
	 * @return string
	 */
	function getTable() {
		return $this->_table;
	}

	/**
	 * Returns true if the given property exists, even if its value is null
	 *
	 * @param string $name Property name to check
	 * @return bool
	 */
	function hasProperty( $name ) {
		return array_key_exists( $name, $this->_properties );
	}

	// ArrayAccess

	/**
	 * @param string $offset
	 * @return bool
	 */
	function offsetExists( $offset ) {
		return $this->__isset( $offset );
	}

	/**
	 * @param string $offset
	 * @return mixed
	 */
	function &offsetGet( $offset ) {
		return $this->__get( $offset );
	}

	/**
	 * @param string $offset
	 * @param mixed $value
	 */
	function offsetSet( $offset, $value ) {

		$this->__set( $offset, $value );

	}

	/**
	 * @param string $offset
	 */
	function offsetUnset( $offset ) {
		$this->__unset( $offset );
	}

	// IteratorAggregate

	/**
	 * @return \ArrayIterator
	 */
	function getIterator() {
		return new \ArrayIterator( $this->_properties );
	}

	// JsonSerializable

	/**
	 * @return array
	 */
	function jsonSerialize() {

		$json = array();

		foreach ( $this->_properties as $key => $value ) {

			if ( $value instanceof \JsonSerializable ) {

				$json[ $key ] = $value->jsonSerialize();

			} else if ( $value instanceof \DateTime ) {

				$json[ $key ] = $value->format( 'Y-m-d H:i:s' );

			} else if ( is_array( $value ) ) { // list of Rows

				foreach ( $value as $i => $row ) {
					$value[ $i ] = $row->jsonSerialize();
				}

				$json[ $key ] = $value;

			} else {

				$json[ $key ] = $value;

			}

		}

		return $json;

	}

	//

	/** @var string|null */
	protected $_table;

	/** @var Context|null */
	protected $_context;

	/** @var array */
	protected $_properties = array();

	/** @var array */
	protected $_modified = array();

	/** @var null|string|array */
	protected $_originalId;

}
