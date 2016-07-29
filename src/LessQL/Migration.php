<?php

namespace LessQL;

/**
 * Represents a database migration
 */
class Migration implements \JsonSerializable {

	/**
	 * Constructor
	 *
	 * @param Context $context
	 * @param string $table Migration table
	 */
	function __construct( $context, $table = 'migration' ) {
		$this->context = $context;
		$this->table = $table;

		try {
			$context( 'CREATE TABLE ?? ( id TEXT, applied_at TEXT )' )
				->exec( array( $context->table( $table ) ) );
		} catch ( \Exception $ex ) {
			// ignore
		}
	}

	/**
	 * Execute an action if it has not been run by this migration before
	 *
	 * @param string|callable $action The action or statement to run
	 * @param array $params
	 * @return $this
	 */
	function apply( $id, $action, $params = null ) {

		if ( $this->break ) return $this->log( 'skipped', $id );

		$self = $this;
		$table = $this->table;

		try {

			$this->context->runTransaction( function ( $context )
					use ( $self, $table, $id, $action, $params ) {

				if ( $context->clear()->query( $table, $id ) ) {
					return $self->log( 'skipped', $id );
				}

				if ( is_string( $action ) ) {
					$context( $action )->exec( $params );
				} else {
					call_user_func( $action, $context, $params );
				}

				$context->clear()
					->insert( $table, array( 'id' => $id, 'applied_at' => microtime() ) )
					->exec();
				return $self->log( 'applied', $id );

			} );

		} catch ( \Exception $ex ) {

			$this->break = true;
			$this->log( 'failed', $id, $ex );

		}

		return $this;

	}

	/**
	 * Get or add item to migration log
	 *
	 * @param string $message
	 * @param string $statement
	 * @param \Exception $ex
	 * @return array
	 */
	function log( $message = null, $id = null, $ex = null ) {
		if ( $message === null ) return $this->log;

		$this->log[] = array(
			'message' => $message,
			'id' => $id,
			'ex' => $ex
		);

		return $this;
	}

	/**
	 *
	 */
	function history() {
		return $this->context->query( $this->table )
			->orderBy( 'applied_at' )->exec();
	}

	/**
	 *
	 */
	function ok() {
		return !$this->break;
	}

	//

	/**
	 * Get JSON representation of migration
	 *
	 * @return array
	 */
	function jsonSerialize() {
		return array(
			'history' => $this->history()->jsonSerialize(),
			'log' => array_map( function ( $item ) {
				if ( @$item[ 'ex' ] ) $item[ 'ex' ] = (string) $item[ 'ex' ];
				return $item;
			}, $this->log() ),
			'ok' => $this->ok()
		);
	}

	/** @var Context */
	protected $context;

	/** @var string */
	protected $table;

	/** @var array */
	protected $log = array();

	/** @var boolean */
	protected $break = false;

}
