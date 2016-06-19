<?php

namespace LessQL;

class Schema {

	function __construct( $db ) {
		$this->db = $db;
	}

	/**
	 * Get primary key of a table, may be array for compound keys
	 *
	 * Convention is "id"
	 *
	 * @param string $table
	 * @return string|array
	 */
	function getPrimary( $table ) {
		if ( isset( $this->primary[ $table ] ) ) {
			return $this->primary[ $table ];
		}

		return 'id';
	}

	/**
	 * Set primary key of a table.
	 * Compound keys may be passed as an array.
	 * Always set compound primary keys explicitly with this method.
	 *
	 * @param string $table
	 * @param string|array $key
	 * @return $this
	 */
	function setPrimary( $table, $key ) {
		$this->primary[ $table ] = $key;

		// compound keys are never auto-generated,
		// so we can assume they are required
		if ( is_array( $key ) ) {

			foreach ( $key as $k ) {
				$this->setRequired( $table, $k );
			}

		}

		return $this;
	}

	/**
	 * Get a reference key for an association on a table
	 *
	 * "How would $table reference another table under $name?"
	 *
	 * Convention is "$name_id"
	 *
	 * @param string $table
	 * @param string $name
	 * @return string
	 */
	function getReference( $table, $name ) {
		if ( isset( $this->references[ $table ][ $name ] ) ) {
			return $this->references[ $table ][ $name ];
		}

		return $name . '_id';
	}

	/**
	 * Set a reference key for an association on a table
	 *
	 * @param string $table
	 * @param string $name
	 * @param string $key
	 * @return $this
	 */
	function setReference( $table, $name, $key ) {
		$this->references[ $table ][ $name ] = $key;
		return $this;
	}

	/**
	 * Get a back reference key for an association on a table
	 *
	 * "How would $table be referenced by another table under $name?"
	 *
	 * Convention is "$table_id"
	 *
	 * @param string $table
	 * @param string $name
	 * @return string
	 */
	function getBackReference( $table, $name ) {
		if ( isset( $this->backReferences[ $table ][ $name ] ) ) {
			return $this->backReferences[ $table ][ $name ];
		}

		return $table . '_id';
	}

	/**
	 * Set a back reference key for an association on a table
	 *
	 * @param string $table
	 * @param string $name
	 * @param string $key
	 * @return $this
	 */
	function setBackReference( $table, $name, $key ) {
		$this->backReferences[ $table ][ $name ] = $key;
		return $this;
	}

	/**
	 * Get alias of a table
	 *
	 * @param string $alias
	 * @return string
	 */
	function getAlias( $alias ) {
		return isset( $this->aliases[ $alias ] ) ? $this->aliases[ $alias ] : $alias;
	}

	/**
	 * Set alias of a table
	 *
	 * @param string $alias
	 * @param string $table
	 * @return $this
	 */
	function setAlias( $alias, $table ) {
		$this->aliases[ $alias ] = $table;
		return $this;
	}

	/**
	 * Is a column of a table required for saving? Default is no
	 *
	 * @param string $table
	 * @param string $column
	 * @return bool
	 */
	function isRequired( $table, $column ) {
		return isset( $this->required[ $table ][ $column ] );
	}

	/**
	 * Get a map of required columns of a table
	 *
	 * @param string $table
	 * @return array
	 */
	function getRequired( $table ) {
		return isset( $this->required[ $table ] ) ? $this->required[ $table ] : array();
	}

	/**
	 * Set a column to be required for saving
	 * Any primary key that is not auto-generated should be required
	 * Compound primary keys are required by default
	 *
	 * @param string $table
	 * @param string $column
	 * @return $this
	 */
	function setRequired( $table, $column ) {
		$this->required[ $table ][ $column ] = true;
		return $this;
	}

	/**
	 * Get primary sequence name of table (used in INSERT by Postgres)
	 *
	 * Conventions is "$tableRewritten_$primary_seq"
	 *
	 * @param string $table
	 * @return null|string
	 */
	function getSequence( $table ) {

		if ( isset( $this->sequences[ $table ] ) ) {
			return $this->sequences[ $table ];
		}

		$primary = $this->getPrimary( $table );

		if ( is_array( $primary ) ) return null;

		$table = $this->rewriteTable( $table );

		return $table . '_' . $primary . '_seq';

	}

	/**
	 * Set primary sequence name of table
	 *
	 * @param string $table
	 * @param string $sequence
	 * @return $this
	 */
	function setSequence( $table, $sequence ) {

		$this->sequences[ $table ] = $sequence;
		return $this;

	}

	/**
	 * Get rewritten table name
	 *
	 * @param string $table
	 * @return string
	 */
	function rewriteTable( $table ) {
		if ( is_callable( $this->rewrite ) ) {
			return call_user_func( $this->rewrite, $table );
		}

		return $table;
	}

	/**
	 * Set table rewrite function
	 * For example, it could add a prefix
	 *
	 * @param callable $rewrite
	 * @return $this
	 */
	function setRewrite( $rewrite ) {
		$this->rewrite = $rewrite;
		return $this;
	}

	//

	/** @var Database */
	protected $db;

	/** @var array */
	protected $primary = array();

	/** @var array */
	protected $references = array();

	/** @var array */
	protected $backReferences = array();

	/** @var array */
	protected $aliases = array();

	/** @var array */
	protected $required = array();

	/** @var array */
	protected $sequences = array();

	/** @var null|callable */
	protected $rewrite;

}
