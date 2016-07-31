<?php

namespace LessQL;

/**
 * A database transaction runner, useful for migrations
 */
class Runner implements \JsonSerializable {

	/**
	 * Constructor
	 *
	 * @param Context $context
	 * @param string $table Table to store runner history in
	 */
	function __construct( $context, $table = null ) {
		$this->context = $context;
		$this->table = $table ?: 'history';

		try {
			$context( 'CREATE TABLE ?? ( id TEXT, executed TEXT )' )
				->exec( array( $context->table( $this->table ) ) );
		} catch ( \Exception $ex ) {
			$context->emit( 'debug', $ex );
		}
	}

	/**
	 * Execute a transaction once
	 *
	 * @param string $id Unique ID of transaction
	 * @param string|callable $action The transaction or statement to run
	 * @param array $params
	 * @return $this
	 */
	function once( $id, $action, $params = null ) {
		return $this->run( $id, $action, $params, true );
	}


	/**
	 * Execute a transaction
	 *
	 * @param string $id Unique ID of transaction
	 * @param string|callable $action The transaction or statement to run
	 * @param array $params
	 * @return $this
	 */
	function run( $id, $action, $params = null, $once = false ) {

		if ( !$this->ok ) return $this->log( 'skipped', $id );

		$self = $this;
		$table = $this->table;

		try {

			$this->context->runTransaction( function ( $context )
					use ( $self, $table, $id, $action, $params, $once ) {

				if ( $once && $context->clear()->query( $table, $id ) ) {
					return $self->log( 'skipped', $id );
				}

				if ( is_string( $action ) ) {
					$context( $action )->exec( $params );
				} else {
					call_user_func( $action, $context, $params );
				}

				if ( $once ) {
					$context->clear()
						->insert( $table, array( 'id' => $id, 'executed' => microtime() ) )
						->exec();
				}

				return $self->log( 'applied', $id );

			} );

		} catch ( \Exception $ex ) {

			$this->ok = false;
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
		return $this->context->clear()->query( $this->table )
			->orderBy( 'executed' )->exec();
	}

	/**
	 *
	 */
	function ok() {
		return $this->ok;
	}

	/**
	 *
	 */
	function version() {
		return Context::VERSION;
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
			'ok' => $this->ok(),
			'version' => $this->version()
		);
	}

	/**
	 *
	 */
	function report( $output = null ) {

		date_default_timezone_set( @date_default_timezone_get() );

		if ( $output === null ) {
			$output = php_sapi_name() === 'cli' ? 'cli' : 'html';
		}

		$report = $this->jsonSerialize();
		$views = dirname( dirname( dirname ( __FILE__ ) ) ) . '/views';

		switch ( $output ) {
		case 'cli':
			require $views . '/cliReport.php';
			exit( $report[ 'ok' ] ? 0 : 1 );
			break;
		case 'html':
			header( 'HTTP/1.1 ' . ( $report[ 'ok' ] ? '200 OK' : '500 Internal Server Error' ) );
			require $views . '/htmlReport.php';
			break;
		default:
			throw new Exception( 'Unknown report output ' . $output );
		}

	}

	/** @var Context */
	protected $context;

	/** @var string */
	protected $table;

	/** @var array */
	protected $log = array();

	/** @var boolean */
	protected $ok = true;

}
