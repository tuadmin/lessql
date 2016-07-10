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
	 * @param string $path Path to history file. Must be writable, otherwise throws
	 */
	function __construct( $context, $path ) {
		$this->context = $context;
		$this->path = $path;
		$this->history = is_file( $path ) ? require $this->path : array();
		// ensure history is writable
		$this->save();
	}

	/**
	 * Destructor. Always saves history.
	 */
	function __destruct() {
		$this->save();
	}

	/**
	 * Execute a statement if it has not been run by this migration before
	 *
	 * @param string $statement The statement to run
	 * @return $this
	 */
	function apply( $id, $action, $params = null ) {

		foreach ( $this->history as $item ) {
			if ( $id === @$item[ 'id' ] ) {
				return $this->log( 'skipped', $id );
			}
		}

		$self = $this;

		try {
			$this->context->runTransaction( function () use ( $self, $id, $action, $params ) {
				if ( is_string( $action ) ) {
					$this->context->createSQL( $action )->exec( $params );
				} else {
					$action( $self, $params );
				}
				$self->history( array( 'id' => $id ) )->log( 'applied', $id );
			} );
		} catch ( \Exception $ex ) {
			throw $ex;
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
	 * Get or add item to migration history
	 *
	 * @return array
	 */
	function history( $data = null ) {
		if ( $data === null ) return $this->history;

		$data[ 'time' ] = time();
		$this->history[] = $data;

		return $this->save();
	}

	/**
	 * Save migration history
	 *
	 * @return $this
	 */
	function save() {
		file_put_contents( $this->path, '<?php return ' . var_export( $this->history, true ) . ';' );
		return $this;
	}

	//

	/**
	 * Get JSON representation of migration
	 *
	 * @return array
	 */
	function jsonSerialize() {
		return array(
			'history' => $this->history,
			'log' => $this->log,
			'ok' => !$this->break
		);
	}

	/** @var Context */
	protected $context;

	/** @var string */
	protected $path;

	/** @var array */
	protected $history;

	/** @var array */
	protected $log = array();

	/** @var boolean */
	protected $break = false;

}
