<?php

class MigrationTest extends BaseTest {

	function testBasic() {

		@unlink( 'tests/migration.php' );

		$migration = $this->db()->createMigration( 'tests/migration.php' );
		$migration->apply( "drop", "DROP TABLE IF EXISTS lol" );
		$migration->apply( "create", "CREATE TABLE lol (id INT)" );

		$this->assertEquals( count( $migration->log() ), 2 );
		$this->assertEquals( $migration->log()[ 0 ][ 'message' ], 'applied' );
		$this->assertEquals( $migration->log()[ 1 ][ 'message' ], 'applied' );

		$migration->apply( "drop", "whatever" );
		$migration->apply( "create", "never" );

		$this->assertEquals( count( $migration->log() ), 4 );
		$this->assertEquals( $migration->log()[ 2 ][ 'message' ], 'skipped' );
		$this->assertEquals( $migration->log()[ 3 ][ 'message' ], 'skipped' );

	}

}
