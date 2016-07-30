<?php

class FindTest extends BaseTest {

	function testPrimary() {

		$db = $this->db();

		$a = $db->person( 2 );
		$b = $db->person( 3 );
		$c = $db->person( 42 );

		$this->assertNotNull( $a );
		$this->assertNotNull( $b );
		$this->assertNull( $c );
		$this->assertTrue( $a->exists() );
		$this->assertTrue( $b->exists() );
		$this->assertEquals( 'Editor', $a->name );
		$this->assertEquals( 'Chief Editor', $b[ 'name' ] );

	}

	function testVia() {

		$db = $this->db();

		$post = $db->post( 12 );

		$this->assertNotNull( $post );

		$author = $post->person()->via( 'author_id' )->first();
		$editor = $post->person()->via( 'editor_id' )->first();

		$this->assertEquals( 1, $author->id );
		$this->assertEquals( 2, $editor->id );

		$posts = $author->postList()->via( 'author_id' );
		$this->assertEquals( array( '11', '12' ), $posts->exec()->getKeys( 'id' ) );

		$this->assertEquals( array(
			"SELECT * FROM `post` WHERE `id` = '12'",
			"SELECT * FROM `person` WHERE `id` = '1'",
			"SELECT * FROM `person` WHERE `id` = '2'",
			"SELECT * FROM `post` WHERE `author_id` IN ( '1', '2' )"
		), $this->statements );

	}

	function testWhere() {

		$db = $this->db();

		$db->dummy()->where( 'test', null )->first();
		$db->dummy()->where( 'test', 31 )->first();
		$db->dummy()->whereNot( 'test', null )->first();
		$db->dummy()->whereNot( 'test', 31 )->first();
		$db->dummy()->where( 'test', array( 1, 2, 3 ) )->first();
		$db->dummy()->where( 'test = 31' )->first();
		$db->dummy()->where( 'test = ?', array( 31 ) )->first();
		$db->dummy()->where( 'test = ?', array( 32 ) )->first();
		$db->dummy()->where( 'test = :param', array( 'param' => 31 ) )->first();
		$db->dummy()
			->where( 'test < :a', array( 'a' => 31 ) )
			->where( 'test > :b', array( 'b' => 0 ) )
			->first();

		$this->assertEquals( array(
			"SELECT * FROM `dummy` WHERE `test` IS NULL",
			"SELECT * FROM `dummy` WHERE `test` = '31'",
			"SELECT * FROM `dummy` WHERE `test` IS NOT NULL",
			"SELECT * FROM `dummy` WHERE `test` != '31'",
			"SELECT * FROM `dummy` WHERE `test` IN ( '1', '2', '3' )",
			"SELECT * FROM `dummy` WHERE test = 31",
			"SELECT * FROM `dummy` WHERE test = ?",
			"SELECT * FROM `dummy` WHERE test = ?",
			"SELECT * FROM `dummy` WHERE test = :param",
			"SELECT * FROM `dummy` WHERE (test < :a) AND test > :b"
		), $this->statements );

		$this->assertEquals( array(
			array(),
			array(),
			array(),
			array(),
			array(),
			array(),
			array( 31 ),
			array( 32 ),
			array( 'param' => 31 ),
			array( 'a' => 31, 'b' => 0 )
		), $this->params );

	}

	function testOrderBy() {

		$db = $this->db();

		$db->dummy()->orderBy( 'id', 'DESC' )->orderBy( 'test' )->first();

		$this->assertEquals( array(
			"SELECT * FROM `dummy` WHERE 1=1 ORDER BY `id` DESC, `test` ASC",
		), $this->statements );

	}

	function testLimit() {

		$db = $this->db();

		$db->dummy()->limit( 3 )->first();
		$db->dummy()->limit( 3, 10 )->first();

		$this->assertEquals( array(
			"SELECT * FROM `dummy` WHERE 1=1  LIMIT 3",
			"SELECT * FROM `dummy` WHERE 1=1  LIMIT 3 OFFSET 10",
		), $this->statements );

	}

	function testPaged() {

		$db = $this->db();

		$db->dummy()->paged( 10, 1 )->first();
		$db->dummy()->paged( 10, 3 )->first();

		$this->assertEquals( array(
			"SELECT * FROM `dummy` WHERE 1=1  LIMIT 10 OFFSET 0",
			"SELECT * FROM `dummy` WHERE 1=1  LIMIT 10 OFFSET 20",
		), $this->statements );

	}

	function testSelect() {

		$db = $this->db();

		$db->dummy()->select( 'test' )->first();
		$db->dummy()->select( 'test', 'id' )->first();
		$db->clear()->dummy()->select( 'test' )->select( 'id' )->first();

		$this->assertEquals( array(
			"SELECT `test` FROM `dummy` WHERE 1=1",
			"SELECT `test`, `id` FROM `dummy` WHERE 1=1",
			"SELECT `test`, `id` FROM `dummy` WHERE 1=1"
		), $this->statements );

	}

	function testTraversal() {

		$db = $this->db();

		$posts = array();

		foreach ( $db->post()->orderBy( 'date_published', 'DESC' ) as $post ) {

			$author = $post->author()->first();
			$editor = $post->editor()->first();
			$editor2 = $post->editor( 'id > ?', array( 0 ) )->first();

			if ( $author ) $this->assertTrue( $author->exists() );
			if ( $editor ) $this->assertTrue( $editor->exists() );

			$t = array();

			$t[ 'title' ] = $post->title;
			$t[ 'author' ] = $author->name;
			$t[ 'editor' ] = $editor ? $editor->name : null;
			$t[ 'categories' ] = array();

			foreach ( $post->categorizationList()->category() as $category ) {
				$t[ 'categories' ][] = $category->title;
			}

			$post->categorizationList()->category( 'id > ?', array( 0 ) )->exec();

			$posts[] = $t;

		}

		$this->assertEquals( array(
			"SELECT * FROM `post` WHERE 1=1 ORDER BY `date_published` DESC",
			"SELECT * FROM `person` WHERE `id` IN ( '2', '1' )",
			"SELECT * FROM `person` WHERE `id` IN ( '3', '2' )",
			"SELECT * FROM `person` WHERE (id > ?) AND `id` IN ( '3', '2' )",
			"SELECT * FROM `categorization` WHERE `post_id` IN ( '13', '11', '12' )",
			"SELECT * FROM `category` WHERE `id` IN ( '22', '23', '21' )",
			"SELECT * FROM `category` WHERE (id > ?) AND `id` IN ( '22', '23', '21' )"
		), $this->statements );

		$this->assertEquals( array(
			array(
				'title' => 'Bar released',
				'categories' => array( 'Tech' ),
				'author' => 'Editor',
				'editor' => 'Chief Editor'
			),
			array(
				'title' => 'Championship won',
				'categories' => array( 'Sports', 'Basketball' ),
				'author' => 'Writer',
				'editor' => null
			),
			array(
				'title' => 'Foo released',
				'categories' => array( 'Tech' ),
				'author' => 'Writer',
				'editor' => 'Editor'
			)
		), $posts );

	}

	function testBackReference() {

		$db = $this->db();

		foreach ( $db->person() as $person ) {
			$posts_as_editor = $person->edit_postList()->exec();
		}

		$this->assertEquals( array(
			"SELECT * FROM `person` WHERE 1=1",
			"SELECT * FROM `post` WHERE `editor_id` IN ( '1', '2', '3' )"
		), $this->statements );

	}

	function testJsonSerialize() {

		// only supported for PHP >= 5.4.0
		if ( version_compare( phpversion(), '5.4.0', '<' ) ) return;

		$db = $this->db();

		$ids = $db->person()->select( 'id' );
		foreach ( $ids as $row ) {
			$row[ 'id' ] = intval( $row[ 'id' ] );
		}
		$json = json_encode( $ids );
		$expected = '[{"id":1},{"id":2},{"id":3}]';
		$this->assertEquals( $expected, $json );

	}

	function testBadReference() {
		$db = $this->db();
		$db->person()->post()->exec();
		$this->assertEquals( array(
			"SELECT * FROM `person` WHERE 1=1",
			"SELECT * FROM `post` WHERE 0=1"
		), $this->statements );
	}

}
