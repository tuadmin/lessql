<?php

class EventEmitterTest extends BaseTest {

	function testEvents() {

		$emitter = new \LessQL\EventEmitter();

		$this->i = 0;

		$emitter
			->on( 'add', array( $this, 'add' ) )
			->on( 'sub', array( $this, 'sub' ) )
			->on( 'sub', array( $this, 'sub' ) );

		$emitter->emit( 'add', 1 );
		$this->assertEquals( $this->i, 1 );

		$emitter->emit( 'add', 2 );
		$this->assertEquals( $this->i, 3 );

		$emitter->emit( 'sub', 1 );
		$this->assertEquals( $this->i, 1 );

	}

	/**
     * @expectedException \LessQL\Exception
	 * @expectedExceptionMessage Listener must be callable
     */
	function testException() {

		$emitter = new \LessQL\EventEmitter();
		$emitter->on( 'bad', 'notcallable' );

		$emitter->emit( 'bad' );

	}

	//

	function add( $j ) {
		$this->i += $j;
	}

	function sub( $j ) {
		$this->i -= $j;
	}

	protected $i;

}
