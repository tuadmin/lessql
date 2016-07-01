<?php

require_once 'vendor/autoload.php';
require_once 'BaseTest.php';

class DatabaseTest extends BaseTest {

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

		$db = $this->db( '`' );

		$a = array(
			$db->quoteIdentifier( 'foo' ),
			$db->quoteIdentifier( 'foo.bar' ),
			$db->quoteIdentifier( 'foo`.bar' ),
		);

		$db = $this->db( '"' );

		$a[] = $db->quoteIdentifier( 'foo.bar' );

		$ex = array(
			"`foo`",
			"`foo`.`bar`",
			"`foo```.`bar`",
			'"foo"."bar"',
		);

		$this->assertEquals( $ex, $a );

	}

	function testTransactions() {

		$db = $this->db();

		$db->runTransaction( function ( $db ) {

		} );

	}

	function testIs() {

		$db = $this->db( '`' );

		$a = array(
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
		);

		$ex = array(
			"`foo` IS NULL",
			"`foo` = '0'",
			"`foo` = 'bar'",
			"`foo` = '2015-01-01 01:00:00'",
			"`foo` = BAR",
			"`foo` IN ( 'x', 'y' )",
			"`foo` IN ( 'x' ) OR `foo` IS NULL",
			"`foo` = 'x'",
			"0=1",
			"`foo` IS NULL",
		);

		$this->assertEquals( $ex, $a );

	}

	function testIsNot() {

		$db = $this->db( '`' );

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
			"`foo` IS NOT NULL",
			"`foo` != '0'",
			"`foo` != 'bar'",
			"`foo` != '2015-01-01 01:00:00'",
			"`foo` != BAR",
			"`foo` NOT IN ( 'x', 'y' )",
			"`foo` NOT IN ( 'x' ) AND `foo` IS NOT NULL",
			"`foo` != 'x'",
			"1=1",
			"`foo` IS NOT NULL",
		);

		$this->assertEquals( $ex, $a );

	}

	function testFind() {

		$db = $this->db();

		$result1 = $db->user();
		$result2 = $db->find( 'user' );

		$row1 = $db->user( 1 );
		$row2 = $db->find( 'user', 2 );

		$ex = array( 'user', 'user', 'user', 'user', 1, 2 );
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

		$row = $db->createRow( 'dummy', array( 'foo' => 'bar' ) );

		var_dump( $row->getTable() );
		$this->assertEquals( $row->getTable(), 'dummy' );
		$this->assertEquals( $row->foo, 'bar' );

	}

}
