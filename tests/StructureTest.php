<?php

class StructureTest extends BaseTest {

	function testHints() {

		$db = $this->db();
		$structure = $db->getStructure();

		$structure->setAlias( 'alias', 'foo' );
		$structure->setPrimary( 'foo', 'fid' );
		$structure->setPrimary( 'bar', array( 'x', 'y' ) );
		$structure->setReference( 'bar', 'foo', 'fid' );
		$structure->setBackReference( 'foo', 'bar', 'fid' );
		$structure->setRequired( 'foo', 9 );
		$structure->setRequired( 'foo', 10 );
		$structure->setSequence( 'foo', 'fooseq' );

		$a = array(
			$structure->getAlias( 'alias' ),
			$structure->getPrimary( 'foo' ),
			$structure->getPrimary( 'bar' ),
			$structure->getReference( 'bar', 'foo' ),
			$structure->getBackReference( 'foo', 'bar' ),
			$structure->isRequired( 'foo', 9 ),
			$structure->isRequired( 'foo', 10 ),
			$structure->getRequired( 'foo' ),
			$structure->getSequence( 'foo' ),
			$structure->getSequence( 'baz' )
		);

		$ex = array(
			'foo',
			'fid',
			array( 'x', 'y' ),
			'fid',
			'fid',
			true,
			true,
			array( 9, 10 ),
			'fooseq',
			'baz_id_seq'
		);

		$this->assertEquals( $ex, $a );

	}

	function testRewrite() {

		$db = $this->db();
		$structure = $db->getStructure();

		$structure->setRewrite( function( $table ) {
			return 'dummy';
		} );

		$db->runTransaction( function ( $db ) {
			$db->post()->exec();
			$db->insert( 'person', array( 'test' => 42 ) )->exec();
			$db->update( 'category', array( 'test' => 42 ) )->exec();
			$db->delete( 'post' )->exec();
		} );

		$this->assertEquals( array(
			"SELECT * FROM `dummy` WHERE 1=1",
			"INSERT INTO `dummy` ( `test` ) VALUES ( '42' )",
			"UPDATE `dummy` SET `test` = '42' WHERE 1=1",
			"DELETE FROM `dummy` WHERE 1=1",
		), $this->statements );

	}

}
