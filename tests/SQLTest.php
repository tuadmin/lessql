<?php

require_once 'vendor/autoload.php';
require_once 'BaseTest.php';

class SQLTest extends BaseTest {

	function testEquals() {

		$db = $this->db();

		$a = $db( 'CREATE TABLE test (id INT, name VARCHAR)' );
		$b = $db( ' create /* evil */  table TEST ( Id iNT ,  namE   VARCHAR )   -- bad' );

		$this->assertTrue( $a->equals( $a ) );
		$this->assertTrue( $b->equals( $b ) );
		$this->assertTrue( $a->equals( $b ) );

	}

	function testResolve() {

		$db = $this->db();

		$f = $db( 'SELECT * FROM &table,&tables WHERE &conds OR foo=:bar OR x in (:lol)', array(
			'table' => 'post',
			'tables' => array( 'a', 'b' ),
			'conds' => $db->getSuffix( array( 'foo' => 'bar', 'x is null' ) ),
			'lol' => array( 1, 2, 3 )
		) );

		$this->assertEquals(
			"SELECT * FROM `post`,`a`, `b` WHERE  WHERE `foo` = 'bar' AND x is null OR foo=:bar OR x in ('1', '2', '3')",
			(string) $f->resolve()
		);

	}

	function testPrimaryTable() {

		$db = $this->db();

		$f = $db( 'SELECT * FROM post WHERE foo=bar' );
		$this->assertEquals( $f->getPrimaryTable(), 'post' );

		$f = $db( 'SELECT post.title FROM blog. post WHERE foo=bar' );
		$this->assertEquals( $f->getPrimaryTable(), 'blog.post' );

		$f = $db( 'SELECT * FROM "blog". post WHERE foo=bar' );
		$this->assertEquals( $f->getPrimaryTable(), '"blog".post' );

		$f = $db( 'SELECT * FROM "blog". `post` WHERE foo=bar' );
		$this->assertEquals( $f->getPrimaryTable(), '"blog".`post`' );

		$f = $db( 'SELECT * FROM &table WHERE foo=bar', array(
			'table' => 'post',
		) );
		$this->assertEquals( $f->getPrimaryTable(), '`post`' );

		$f = $db( 'INSERT INTO "blog". `post` (title) VALUES (:evil)' );
		$this->assertEquals( $f->getPrimaryTable(), null );

	}

	function testTokens() {

	}

	function testPrepare() {

	}

	function testExec() {

		$db = $this->db();

	}

}
