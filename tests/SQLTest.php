<?php

class SQLTest extends BaseTest {

	function testTokens() {

		$db = $this->db();

		$s = $db( 'SELECT * FROM &post' );

	}

	function testResolve() {

		$db = $this->db();

		$s = $db( 'SELECT * FROM &post,::tables WHERE ::conds OR foo=:bar OR x in (::lol) :lol', array(
			'tables' => $db->quoteIdentifier( array( 'a', 'b' ) ),
			'conds' => $db->where( array( 'foo' => 'bar', 'x' => null ) ),
			'lol' => array( 1, 2, 3 )
		) );

		$this->assertEquals(
			"SELECT * FROM `post`,`a`, `b` WHERE (`foo` = 'bar') AND `x` IS NULL OR foo=:bar OR x in ('1', '2', '3') :lol",
			str_replace( '"', '`', (string) $s )
		);

		$this->assertEquals(
			array( array( 1, 2, 3 ) ),
			array_values( $s->resolve()->getParams() )
		);

	}

	function testGetTable() {

		$db = $this->db();

		$this->assertEquals(
			'post',
			$db( 'SELECT * FROM &post, &person' )->getTable()
		);

		$this->assertEquals(
			'post',
			$db( 'SELECT * FROM ::table, &person', array(
				'table' => $db->table( 'post' )
			) )->getTable()
		);

	}

	function testReferencedBy() {

		$db = $this->db();

		$categorizations = $db( 'SELECT * FROM &categorization' );
		$post = $db( 'SELECT * FROM &post WHERE ::where' )
			->referencedBy( $categorizations->first() )
			->first();

		$this->assertEquals( 'Championship won', $post[ 'title' ] );

		$posts = $db( 'SELECT * FROM &post WHERE ::where' )
			->referencedBy( $categorizations );

		$this->assertEquals( 3, $posts->count() );

	}

	function testReferencedByVia() {

		$db = $this->db();

		$posts = $db( 'SELECT * FROM &post' );
		$author = $db( 'SELECT * FROM &person WHERE ::where' )
			->referencedBy( $posts->first() )
			->via( 'author_id' )
			->first();

		$this->assertEquals( 'Writer', $author[ 'name' ] );

		$authors = $db( 'SELECT * FROM &person WHERE ::where' )
			->referencedBy( $posts )
			->via( 'author_id' );

		$this->assertEquals( 2, $authors->count() );

	}

	function testReferencing() {

		$db = $this->db();

		$posts = $db( 'SELECT * FROM &post' );
		$categorizations = $db( 'SELECT * FROM &categorization WHERE ::where' )
			->referencing( $posts->first() );

		$this->assertEquals( 3, $posts->count() );

	}

	function testReferencingVia() {

		$db = $this->db();

		$authors = $db( 'SELECT * FROM &person' );
		$posts = $db( 'SELECT * FROM &post WHERE ::where' )
			->referencing( $authors->first() )
			->via( 'author_id' );

		$this->assertEquals( 2, $posts->count() );

	}

}
