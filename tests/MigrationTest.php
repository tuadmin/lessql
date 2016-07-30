<?php

class MigrationTest extends BaseTest {

	function testBasic() {

		$db = $this->db();

		try {
			$this->exec( 'DROP TABLE migration' );
		} catch ( \Exception $ex ) {
			// ignore
		}

		try {
			$this->exec( 'DROP TABLE lol' );
		} catch ( \Exception $ex ) {
			// ignore
		}

		try {
			$this->exec( 'DROP TABLE foo' );
		} catch ( \Exception $ex ) {
			// ignore
		}


		$migration = $db->createMigration();
		$migration->apply( 'create', 'CREATE TABLE migration_test (id INT)' );
		$migration->apply( 'drop', function ( $context ) {
			$context( 'DROP TABLE migration_test' )->exec();
		} );

		$log = $migration->log();
		$this->assertEquals( 2, count( $log ) );
		$this->assertEquals( $log[ 0 ][ 'message' ], 'applied' );
		$this->assertEquals( $log[ 1 ][ 'message' ], 'applied' );

		$migration->apply( 'drop', 'whatever' );
		$migration->apply( 'x', 'CREATE TABLE foo (id INT)' );
		$migration->apply( 'create', 'never' );
		$migration->apply( 'y', 'SELECT * FROM foo' );
		$migration->apply( 'z', 'INSERT INTO migration_test (id) VALUES (1)' );
		$migration->apply( 'zz', 'SELECT * FROM foo' );

		$log = $migration->log();
		$this->assertEquals( 8, count( $log ) );
		$this->assertEquals( 'skipped', $log[ 2 ][ 'message' ] );
		$this->assertEquals( 'applied', $log[ 3 ][ 'message' ] );
		$this->assertEquals( 'skipped', $log[ 4 ][ 'message' ] );
		$this->assertEquals( 'applied', $log[ 5 ][ 'message' ] );
		$this->assertEquals( 'failed', $log[ 6 ][ 'message' ] );
		$this->assertEquals( 'skipped', $log[ 7 ][ 'message' ] );

		$migration->jsonSerialize();

	}

}
