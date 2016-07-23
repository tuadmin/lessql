<?php

class MigrationTest extends BaseTest {

	function testBasic() {

		$migration = $this->db()->createMigration( 'tests/migration.php' );
		$migration->apply( "drop", "DROP TABLE lol" );
		$migration->apply( "create", "CREATE TABLE lol (id INT)" );

	}

}
