<?php

require_once 'vendor/autoload.php';

class FragmentTest extends PHPUnit_Framework_TestCase {

	function testEquals() {

		$db = new \LessQL\Database( new \PDO( 'sqlite:tests/shop.sqlite3' ) );

		$a = $db->fragment( 'CREATE TABLE test (id INT, name VARCHAR)' );
		$b = $db->fragment( ' create /* evil */  table TEST ( Id iNT ,  namE   VARCHAR )   -- bad' );

		$this->assertTrue( $a->equals( $a ) );
		$this->assertTrue( $b->equals( $b ) );
		$this->assertTrue( $a->equals( $b ) );

	}

	function testResolve() {

		$db = new \LessQL\Database( new \PDO( 'sqlite:tests/shop.sqlite3' ) );

		$f = $db->fragment( 'SELECT * FROM &table,&tables WHERE &conds OR foo=:bar OR x in (:lol)', array(
			'table' => 'post',
			'tables' => array( 'a', 'b' ),
			'conds' => $db->suffix( array( 'foo' => 'bar', 'x is null' ) ),
			'lol' => array( 1, 2, 3 )
		) );

		$this->assertEquals(
			"SELECT * FROM `post`,`a`, `b` WHERE  WHERE `foo` = 'bar' AND x is null OR foo=:bar OR x in ('1', '2', '3')",
			(string) $f->resolve()
		);

	}

}
