<?php

require_once 'vendor/autoload.php';

class MigrationTest extends PHPUnit_Framework_TestCase {

	function testHasProperty() {

		$migration = $this->db()->migration( 'tests/migration.php' );
		$migration->apply( "DROP TABLE lol" );
		$migration->apply( "CREATE TABLE lol (id INT)" );

		var_dump( json_encode( $migration ) );

	}

}
