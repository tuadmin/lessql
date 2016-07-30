<?php

namespace LessQL;

/**
 * Represents an event emitter
 */
class EventEmitter {

	/**
	 * Emit an event
	 *
	 * @param string $event
	 * @param mixed $data
	 */
	function emit( $event, $data = null ) {
		if ( isset( $this->listeners[ $event ] ) ) {
			foreach ( $this->listeners[ $event ] as $listener ) {
				call_user_func( $listener, $data );
			}
		}
		return $this;
	}

	/**
	 * Listen to an event
	 *
	 * @param string $event
	 * @param callable $listener
	 */
	function on( $event, $listener ) {
		if ( !is_callable( $listener ) ) {
			throw new Exception( 'Listener must be callable' );
		}

		if ( isset( $this->listeners[ $event ] ) ) {
			$this->listeners[ $event ][] = $listener;
			return $this;
		}
		$this->listeners[ $event ] = array( $listener );
		return $this;
	}

	/** @var array */
	protected $listeners = array();

}
