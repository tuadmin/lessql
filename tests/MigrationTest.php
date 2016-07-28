<?php

class MigrationTest extends BaseTest {

	function testBasic() {

		@unlink( 'tests/migration.php' );

		$migration = $this->db()->createMigration( 'tests/migration.php' );
		$migration->apply( "drop", "DROP TABLE IF EXISTS lol" );
		$migration->apply( "create", "CREATE TABLE lol (id INT)" );

		$log = $migration->log();
		$this->assertEquals( count( $log ), 2 );
		$this->assertEquals( $log[ 0 ][ 'message' ], 'applied' );
		$this->assertEquals( $log[ 1 ][ 'message' ], 'applied' );

		$migration->apply( "drop", "whatever" );
		$migration->apply( "create", "never" );

		$log = $migration->log();
		$this->assertEquals( count( $log ), 4 );
		$this->assertEquals( $log[ 2 ][ 'message' ], 'skipped' );
		$this->assertEquals( $log[ 3 ][ 'message' ], 'skipped' );

	}

}
