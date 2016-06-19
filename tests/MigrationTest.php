<?php

require_once 'vendor/autoload.php';

class MigrationTest extends PHPUnit_Framework_TestCase {

	function testHasProperty() {

		$db = new \LessQL\Database( new \PDO( 'sqlite:tests/shop.sqlite3' ) );

		$migration = $db->migration( 'tests/migration.php' );
		$migration->apply( "DROP TABLE lol" );
		$migration->apply( "CREATE TABLE lol (id INT)" );

		var_dump( json_encode( $migration ) );

	}

}
