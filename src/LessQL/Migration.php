<?php

namespace LessQL;

/**
 * Represents a database migration
 */
class Migration implements \JsonSerializable {

	/**
	 * Constructor
	 *
	 * @param Database $db
	 * @param string $path Path to history file. Must be writable, otherwise throws
	 */
	function __construct( $db, $path ) {
		$this->db = $db;
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
	function apply( $action ) {

		if ( is_callable( $action ) ) {
			$this->db->begin();
			try {
				$action( $this );
				$this->db->commit();
			} catch ( \Exception $ex ) {
				$this->break = true;
				$this->log( 'failed', $action, $ex );
				try {
					$this->db->rollback();
				} catch ( \Exception $ex2 ) {
					$this->log( 'rollbackFailed', null, $ex2 );
				}
			}

			return $this;
		}

		if ( $this->break ) {
			return $this->log( 'skipped', $statement );
		}

		$s = $this->db->createFragment( $statement );

		foreach ( $this->history as $item ) {
			if ( $s->equals( $item[ 'statement' ] ) ) {
				return $this->log( 'skipped', $statement );
			}
		}

		try {

			$this->db->exec( $statement );

			$this->history[] = array(
				'statement' => $statement,
				'time' => time()
			);

			$this->save();

			return $this->log( 'applied', $statement );

		} catch ( \Exception $ex ) {
			$this->break = true;
			return $this->log( 'failed', $statement, $ex );
		}

	}

	/**
	 * Get or add item to migration log
	 *
	 * @param string $status
	 * @param string $statement
	 * @param \Exception $ex
	 * @return array
	 */
	function log( $status = null, $statement = null, $ex = null ) {
		if ( $status === null ) return $this->log;

		$this->log[] = array(
			'status' => $status,
			'statement' => $statement,
			'ex' => $ex
		);

		return $this;
	}

	/**
	 * Get migration history
	 *
	 * @return array
	 */
	function history() {
		return $this->history;
	}

	/**
	 * Save migration history
	 *
	 * @return int Bytes written
	 */
	function save() {
		return file_put_contents( $this->path, '<?php return ' . var_export( $this->history, true ) . ';' );
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

	/** @var Database */
	protected $db;

	/** @var string */
	protected $path;

	/** @var array */
	protected $history;

	/** @var array */
	protected $log = array();

	/** @var boolean */
	protected $break = false;

}
