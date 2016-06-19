<?php

namespace LessQL;

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

	function __destruct() {
		$this->save();
	}

	/**
	 * Execute a statement if it has not been run by this migration before
	 *
	 * @param string $statement The statement to run
	 */
	function apply( $statement ) {
		if ( $this->break ) {
			return $this->log( 'skipped', $statement );
		}

		$s = $this->db->fragment( $statement );

		foreach ( $this->history as $item ) {
			if ( $s->equals( $this->db->fragment( $item[ 'statement' ] ) ) ) {
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

	function log( $status = null, $statement = null, $ex = null ) {
		if ( $status === null ) return $this->log;

		$this->log[] = array(
			'status' => $status,
			'statement' => $statement,
			'ex' => $ex
		);

		return $this;
	}

	function history() {
		return $this->history;
	}

	function save() {
		return file_put_contents( $this->path, '<?php return ' . var_export( $this->history, true ) . ';' );
	}

	//

	function jsonSerialize() {
		return array(
			'history' => $this->history,
			'log' => $this->log,
			'ok' => !$this->break
		);
	}

	protected $db;
	protected $path;
	protected $history;
	protected $log = array();
	protected $break = false;

}
