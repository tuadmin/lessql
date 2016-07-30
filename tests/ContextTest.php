<?php

class ContextTest extends BaseTest {

	function testQuoteValue() {

		$db = $this->db();

		$a = array_map( array( $this, 'str' ), array(
			$db->quoteValue( null ),
			$db->quoteValue( $db( 'NULL' ) ),
			$db->quoteValue( false ),
			$db->quoteValue( true ),
			$db->quoteValue( 0 ),
			$db->quoteValue( 1 ),
			$db->quoteValue( 0.0 ),
			$db->quoteValue( 3.1 ),
			$db->quoteValue( '1' ),
			$db->quoteValue( 'foo' ),
			$db->quoteValue( '' ),
			$db->quoteValue( $db() ),
			$db->quoteValue( $db( 'BAR' ) ),
		) );

		$ex = array(
			"NULL",
			"NULL",
			"'0'",
			"'1'",
			"'0'",
			"'1'",
			"'0.000000'",
			"'3.100000'",
			"'1'",
			"'foo'",
			"''",
			"",
			"BAR",
		);

		$this->assertEquals( $ex, $a );

	}

	function testQuoteIdentifier() {

		$db = $this->db();

		$a = array_map( array( $this, 'str' ), array(
			$db->quoteIdentifier( 'foo' ),
			$db->quoteIdentifier( 'foo.bar' ),
			$db->quoteIdentifier( 'foo".bar' ),
		) );

		$ex = array(
			'"foo"',
			'"foo"."bar"',
			'"foo"""."bar"'
		);

		$this->assertEquals( $ex, $a );

	}

	function testTransactions() {

		$db = $this->db();

		$db->runTransaction( function ( $db ) {
			$db->runTransaction( function ( $db ) {

			} );
		} );

	}

	function testIs() {

		$db = $this->db();

		$a = array_map( array( $this, 'str' ), array(
			$db->is( 'foo', null ),
			$db->is( 'foo', 0 ),
			$db->is( 'foo', 'bar' ),
			$db->is( 'foo', new \DateTime( '2015-01-01 01:00:00' ) ),
			$db->is( 'foo', $db( "BAR" ) ),
			$db->is( 'foo', array( 'x', 'y' ) ),
			$db->is( 'foo', array( 'x', null ) ),
			$db->is( 'foo', array( 'x' ) ),
			$db->is( 'foo', array() ),
			$db->is( 'foo', array( null ) ),
		) );

		$ex = array(
			'"foo" IS NULL',
			'"foo" = \'0\'',
			'"foo" = \'bar\'',
			'"foo" = \'2015-01-01 01:00:00\'',
			'"foo" = BAR',
			'"foo" IN ( \'x\', \'y\' )',
			'"foo" IN ( \'x\' ) OR "foo" IS NULL',
			'"foo" = \'x\'',
			'0=1',
			'"foo" IS NULL',
		);

		$this->assertEquals( $ex, $a );

	}

	function testIsNot() {

		$db = $this->db();

		$a = array(
			$db->isNot( 'foo', null ),
			$db->isNot( 'foo', 0 ),
			$db->isNot( 'foo', 'bar' ),
			$db->isNot( 'foo', new \DateTime( '2015-01-01 01:00:00' ) ),
			$db->isNot( 'foo', $db( "BAR" ) ),
			$db->isNot( 'foo', array( 'x', 'y' ) ),
			$db->isNot( 'foo', array( 'x', null ) ),
			$db->isNot( 'foo', array( 'x' ) ),
			$db->isNot( 'foo', array() ),
			$db->isNot( 'foo', array( null ) ),
		);

		$ex = array(
			'"foo" IS NOT NULL',
			'"foo" != \'0\'',
			'"foo" != \'bar\'',
			'"foo" != \'2015-01-01 01:00:00\'',
			'"foo" != BAR',
			'"foo" NOT IN ( \'x\', \'y\' )',
			'"foo" NOT IN ( \'x\' ) AND "foo" IS NOT NULL',
			'"foo" != \'x\'',
			'1=1',
			'"foo" IS NOT NULL',
		);

		$this->assertEquals( $ex, $a );

	}

	function testQuery() {

		$db = $this->db();

		$result1 = $db->person();
		$result2 = $db->query( 'person' );

		$row1 = $db->person( 1 );
		$row2 = $db->query( 'person', 2 );

		$ex = array( 'person', 'person', 'person', 'person', 1, 2 );
		$a = array(
			(string) $result1->getTable(),
			(string) $result2->getTable(),
			(string) $row1->getTable(),
			(string) $row2->getTable(),
			$row1[ 'id' ],
			$row2[ 'id' ]
		);

		$this->assertEquals( $ex, $a );

	}

	function testCreateRow() {

		$db = $this->db();

		$row = $db->createRow( 'person', array( 'name' => 'foo' ) );

		$this->assertTrue( $row instanceof \LessQL\Row );
		$this->assertSame( 'person', $row->getTable() );

		$row->save();

		$row = $db->person( $row[ 'id' ] );

		$this->assertSame( 'foo', $row[ 'name' ] );

	}

	function testInsert() {

		$db = $this->db();

		$db->runTransaction( function ( $db ) {
			$db->insert( 'dummy', array() )->exec(); // does nothing
			$db->insert( 'dummy', array( 'id' => 1, 'test' => 42 ) )->exec();
			foreach ( array(
				array( 'id' => 2,  'test' => 1 ),
				array( 'id' => 3,  'test' => 2 ),
				array( 'id' => 4,  'test' => 3 )
			) as $row ) $db->insert( 'dummy', $row )->exec();
		} );

		$this->assertEquals( array(
			"INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '1', '42' )",
			"INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '2', '1' )",
			"INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '3', '2' )",
			"INSERT INTO `dummy` ( `id`, `test` ) VALUES ( '4', '3' )"
		), $this->statements );

	}

	function testInsertPrepared() {

		$db = $this->db();

		$db->runTransaction( function ( $db ) {
			$db->insertPrepared( 'dummy', array(
				array( 'test' => 1 ),
				array( 'test' => 2 ),
				array( 'test' => 3 )
			) );
		} );

		$this->assertEquals( array(
			"INSERT INTO `dummy` ( `test` ) VALUES ( ? )",
			"INSERT INTO `dummy` ( `test` ) VALUES ( ? )",
			"INSERT INTO `dummy` ( `test` ) VALUES ( ? )"
		), $this->statements );

		$this->assertEquals( array(
			array( 'test' => 1 ),
			array( 'test' => 2 ),
			array( 'test' => 3 )
		), $db->dummy()->select( 'test' )->jsonSerialize() );

	}

	function testInsertBatch() {

		$db = $this->db();

		// not supported by sqlite < 3.7, skip

		try {

			$db->runTransaction( function ( $db ) {
				$db->insertBatch( 'dummy', array(
					array( 'test' => 1 ),
					array( 'test' => 2 ),
					array( 'test' => 3 )
				) )->exec();
			} );

		} catch ( \Exception $ex ) {
			// ignore
		}

		$this->assertEquals( array(
			"INSERT INTO `dummy` ( `test` ) VALUES ( '1' ), ( '2' ), ( '3' )",
		), $this->statements );

	}

	function testUpdate() {

		$db = $this->db();
		$self = $this;

		$db->runTransaction( function ( $db ) use ( $self ) {
			$db->update( 'dummy', array() )->exec();
			$db->update( 'dummy', array( 'test' => 42 ) )->exec();
			$db->update( 'dummy', array( 'test' => 42 ) )->where( 'test', 1 )->exec();

			$statements = $self->statements;
			$db->insert( 'dummy', array( 'id' => 1, 'test' => 44 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 2, 'test' => 42 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 3, 'test' => 45 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 4, 'test' => 47 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 5, 'test' => 48 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 6, 'test' => 43 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 7, 'test' => 41 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 8, 'test' => 46 ) )->exec();
			$self->statements = $statements;
		} );

		$db->runTransaction( function ( $db ) {
			$db->dummy()->where( 'test > 42' )->limit( 2, 2 )
				->update( array( 'test' => 42 ) )->exec();
			$db->dummy()->where( 'test > 42' )->orderBy( 'test' )->limit( 2 )
				->update( array( 'test' => 42 ) )->exec();
			$db->dummy()->where( 'test > 42' )
				->update( array( 'test' => 42 ) )->exec();
		} );

		$this->assertEquals( array(
			"UPDATE `dummy` SET `test` = '42' WHERE 1=1",
			"UPDATE `dummy` SET `test` = '42' WHERE `test` = '1'",
			"SELECT * FROM `dummy` WHERE test > 42  LIMIT 2 OFFSET 2",
			"UPDATE `dummy` SET `test` = '42' WHERE `id` IN ( '4', '5' )",
			"SELECT * FROM `dummy` WHERE test > 42 ORDER BY `test` ASC LIMIT 2",
			"UPDATE `dummy` SET `test` = '42' WHERE `id` IN ( '6', '1' )",
			"SELECT * FROM `dummy` WHERE test > 42",
			"UPDATE `dummy` SET `test` = '42' WHERE `id` IN ( '3', '8' )"
		), $this->statements );

	}

	function testDelete() {

		$db = $this->db();
		$self = $this;

		$db->runTransaction( function ( $db ) use ( $self ) {
			$db->delete( 'dummy' )->exec();
			$db->delete( 'dummy', array( 'test' => 1 ) )->exec();

			$statements = $self->statements;
			$db->insert( 'dummy', array( 'id' => 1, 'test' => 44 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 2, 'test' => 42 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 3, 'test' => 45 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 4, 'test' => 47 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 5, 'test' => 48 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 6, 'test' => 43 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 7, 'test' => 41 ) )->exec();
			$db->insert( 'dummy', array( 'id' => 8, 'test' => 46 ) )->exec();
			$self->statements = $statements;
		} );

		$db->runTransaction( function ( $db ) {
			$db->dummy()->where( 'test > 42' )->limit( 2, 2 )->delete()->exec();
			$db->dummy()->where( 'test > 42' )->orderBy( 'test' )->limit( 2 )->delete()->exec();
			$db->dummy()->where( 'test > 42' )->delete()->exec();
		} );

		$this->assertEquals( array(
			"DELETE FROM `dummy` WHERE 1=1",
			"DELETE FROM `dummy` WHERE `test` = '1'",
			"SELECT * FROM `dummy` WHERE test > 42  LIMIT 2 OFFSET 2",
			"DELETE FROM `dummy` WHERE `id` IN ( '4', '5' )",
			"SELECT * FROM `dummy` WHERE test > 42 ORDER BY `test` ASC LIMIT 2",
			"DELETE FROM `dummy` WHERE `id` IN ( '6', '1' )",
			"SELECT * FROM `dummy` WHERE test > 42",
			"DELETE FROM `dummy` WHERE `id` IN ( '3', '8' )",
		), $this->statements );

	}

	function testDeleteComposite() {

		$db = $this->db();
		$self = $this;

		$db->runTransaction( function ( $db ) use ( $self ) {
			$db->categorization()->where( 'category_id > 21' )->limit( 2 )
				->delete()->exec();
		} );

		$this->assertEquals( array(
			"SELECT * FROM `categorization` WHERE category_id > 21  LIMIT 2",
			"DELETE FROM `categorization` WHERE ( `category_id` = '22' AND `post_id` = '11' ) OR ( `category_id` = '23' AND `post_id` = '11' )",
		), $this->statements );

	}

}
