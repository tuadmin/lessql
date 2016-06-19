<?php

require_once 'vendor/autoload.php';
require_once 'BaseTest.php';

class FragmentTest extends BaseTest {

	function testEquals() {

		$db = $this->db();

		$a = $db->fragment( 'CREATE TABLE test (id INT, name VARCHAR)' );
		$b = $db->fragment( ' create /* evil */  table TEST ( Id iNT ,  namE   VARCHAR )   -- bad' );

		$this->assertTrue( $a->equals( $a ) );
		$this->assertTrue( $b->equals( $b ) );
		$this->assertTrue( $a->equals( $b ) );

	}

	function testResolve() {

		$db = $this->db();

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

	function testTokens() {

	}

	function testPrepare() {

	}

	function testExec() {

		$db = $this->db();

	}

}
