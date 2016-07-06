<?php

require_once 'vendor/autoload.php';
require_once 'BaseTest.php';

class SchemaTest extends BaseTest {

	function testHints() {

		$db = $this->db();
		$schema = $db->schema();

		$schema->setAlias( 'alias', 'foo' );
		$schema->setPrimary( 'foo', 'fid' );
		$schema->setPrimary( 'bar', array( 'x', 'y' ) );
		$schema->setReference( 'bar', 'foo', 'fid' );
		$schema->setBackReference( 'foo', 'bar', 'fid' );
		$schema->setRequired( 'foo', 9 );
		$schema->setRequired( 'foo', 10 );
		$schema->setSequence( 'foo', 'fooseq' );

		$a = array(
			$schema->getAlias( 'alias' ),
			$schema->getPrimary( 'foo' ),
			$schema->getPrimary( 'bar' ),
			$schema->getReference( 'bar', 'foo' ),
			$schema->getBackReference( 'foo', 'bar' ),
			$schema->isRequired( 'foo', 9 ),
			$schema->isRequired( 'foo', 10 ),
			$schema->getRequired( 'foo' ),
			$schema->getSequence( 'foo' ),
			$schema->getSequence( 'baz' )
		);

		$ex = array(
			'foo',
			'fid',
			array( 'x', 'y' ),
			'fid',
			'fid',
			true,
			true,
			array( 9 => true, 10 => true ),
			'fooseq',
			'baz_id_seq'
		);

		$this->assertEquals( $ex, $a );

	}

	function testRewrite() {

		$db = $this->db();
		$schema = $db->schema();

		$schema->setRewrite( function( $table ) {
			return 'dummy';
		} );

		$db->runTransaction( function ( $db ) {
			$db->post()->exec();
			$db->insert( 'user', array( 'test' => 42 ) )->exec();
			$db->update( 'category', array( 'test' => 42 ) );
			$db->delete( 'post' );
		} );

		$this->assertEquals( array(
			"SELECT * FROM `dummy`",
			"INSERT INTO `dummy` ( `test` ) VALUES ( '42' )",
			"UPDATE `dummy` SET `test` = '42'",
			"DELETE FROM `dummy`",
		), $this->statements );

	}

}
