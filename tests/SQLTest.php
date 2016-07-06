<?php

require_once 'vendor/autoload.php';
require_once 'BaseTest.php';

class SQLTest extends BaseTest {

	function testResolve() {

		$db = $this->db();

		$f = $db( 'SELECT * FROM &table,&tables WHERE &conds OR foo=:bar OR x in (:lol)', array(
			'table' => 'post',
			'tables' => array( 'a', 'b' ),
			'conds' => $db->where( array( 'foo' => 'bar', 'x' => null ) ),
			'lol' => array( 1, 2, 3 )
		) );

		$this->assertEquals(
			"SELECT * FROM `post`,`a`, `b` WHERE (`foo` = 'bar') AND `x` IS NULL OR foo=:bar OR x in ('1', '2', '3')",
			(string) $f->resolve()
		);

	}

}
