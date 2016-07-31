<?php

class RunnerTest extends BaseTest {

	function testBasic() {

		$db = $this->db();

		try {
			$this->exec( 'DROP TABLE history' );
		} catch ( \Exception $ex ) {
			// ignore
		}

		try {
			$this->exec( 'DROP TABLE runner_test' );
		} catch ( \Exception $ex ) {
			// ignore
		}

		try {
			$this->exec( 'DROP TABLE foo' );
		} catch ( \Exception $ex ) {
			// ignore
		}


		$runner = $db->createRunner();
		$runner->once( 'create', 'CREATE TABLE runner_test (id INT)' );
		$runner->once( 'drop', function ( $context ) {
			$context( 'DROP TABLE runner_test' )->exec();
		} );

		$log = $runner->log();
		$this->assertEquals( 2, count( $log ) );
		$this->assertEquals( 'applied', $log[ 0 ][ 'message' ] );
		$this->assertEquals( 'applied', $log[ 1 ][ 'message' ] );

		$runner->once( 'drop', 'whatever' );
		$runner->once( 'x', 'CREATE TABLE foo (id INT)' );
		$runner->once( 'create', 'never' );
		$runner->once( 'y', 'SELECT * FROM foo' );
		$runner->once( 'z', 'INSERT INTO runner_test (id) VALUES (1)' );
		$runner->once( 'zz', 'SELECT * FROM foo' );

		$log = $runner->log();
		$this->assertEquals( 8, count( $log ) );
		$this->assertEquals( 'skipped', $log[ 2 ][ 'message' ] );
		$this->assertEquals( 'applied', $log[ 3 ][ 'message' ] );
		$this->assertEquals( 'skipped', $log[ 4 ][ 'message' ] );
		$this->assertEquals( 'applied', $log[ 5 ][ 'message' ] );
		$this->assertEquals( 'failed', $log[ 6 ][ 'message' ] );
		$this->assertEquals( 'skipped', $log[ 7 ][ 'message' ] );

		$runner->jsonSerialize();
		
		$runner = $db->createRunner( 'foo' );

	}

}
