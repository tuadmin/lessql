<?php

require_once 'vendor/autoload.php';
require_once 'BaseTest.php';

class MigrationTest extends BaseTest {

	function testHasProperty() {

		$migration = $this->db()->createMigration( 'tests/migration.php' );
		$migration->apply( "drop", "DROP TABLE lol" );
		$migration->apply( "create", "CREATE TABLE lol (id INT)" );

	}

}
