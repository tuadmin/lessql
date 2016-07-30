<?php

class BaseTest extends PHPUnit_Framework_TestCase {

	function setUp() {

		$this->connect();
		$this->resetSchema();
		$this->resetData();

		$this->statements = array();
		$this->params = array();

	}

	function testDummy() {

	}

	function db( $options = array() ) {

		$db = new \LessQL\Context( $this->pdo, $options );
		$db->on( 'exec', array( $this, 'onExec' ) );
		$db->on( 'error', array( $this, 'onError' ) );

		$structure = $db->getStructure();
		$structure->addTables( array(
			'post',
			'person',
			'category',
			'categorization',
			'dummy'
		) );
		$structure->setAlias( 'author', 'person' );
		$structure->setAlias( 'editor', 'person' );
		$structure->setPrimary( 'categorization', array( 'category_id', 'post_id' ) );

		$structure->setAlias( 'edit_post', 'post' );
		$structure->setBackReference( 'person', 'edit_post', 'editor_id' );

		return $db;

	}

	function onExec( $sql ) {

		$statement = str_replace( '"', '`', trim( (string) $sql ) );
		$params = $sql->resolve()->getParams();

		if ( strtoupper( substr( $statement, 0, 6 ) ) !== 'SELECT' ) $this->dirty = true;

		$this->statements[] = $statement;
		$this->params[] = $params;

	}

	function onError( $sql ) {
		var_dump( (string) $sql );
	}

	function str( $mixed ) {
		return (string) $mixed;
	}

	//

	function connect() {
		if ( $this->pdo ) return;
		$this->pdo = self::$PDO;
		$this->pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
	}

	function resetSchema() {

		if ( !$this->dirtySchema ) return;

		$this->pdo->beginTransaction();

		if ( $this->getDriver() === 'sqlite' ) {
			$p = "INTEGER PRIMARY KEY AUTOINCREMENT";
		}

		if ( $this->getDriver() === 'mysql' ) {
			$p = "INTEGER PRIMARY KEY AUTO_INCREMENT";
		}

		if ( $this->getDriver() === 'pgsql' ) {
			$p = "SERIAL PRIMARY KEY";
		}

		$this->exec( "DROP TABLE IF EXISTS person" );

		$this->exec( "CREATE TABLE person (
			id $p,
			name varchar(30) NOT NULL
		)" );

		$this->exec( "DROP TABLE IF EXISTS post" );

		$this->exec( "CREATE TABLE post (
			id $p,
			author_id INTEGER DEFAULT NULL,
			editor_id INTEGER DEFAULT NULL,
			is_published INTEGER DEFAULT 0,
			date_published VARCHAR(30) DEFAULT NULL,
			title VARCHAR(30) NOT NULL
		)" );

		$this->exec( "DROP TABLE IF EXISTS category" );

		$this->exec( "CREATE TABLE category (
			id $p,
			title varchar(30) NOT NULL
		)" );

		$this->exec( "DROP TABLE IF EXISTS categorization" );

		$this->exec( "CREATE TABLE categorization (
			category_id INTEGER NOT NULL,
			post_id INTEGER NOT NULL
		)" );

		$this->exec( "DROP TABLE IF EXISTS dummy" );

		$this->exec( "CREATE TABLE dummy (
			id $p,
			test INTEGER
		)" );

		$this->pdo->commit();
		$this->dirtySchema = false;
		$this->dirtyData = true;

	}

	function resetData() {

		if ( !$this->dirtyData ) return;

		$this->pdo->beginTransaction();

		// sequences

		if ( $this->getDriver() === 'sqlite' ) {

			$this->exec( "DELETE FROM sqlite_sequence WHERE name='person'" );
			$this->exec( "DELETE FROM sqlite_sequence WHERE name='post'" );
			$this->exec( "DELETE FROM sqlite_sequence WHERE name='category'" );
			$this->exec( "DELETE FROM sqlite_sequence WHERE name='dummy'" );

		}

		if ( $this->getDriver() === 'mysql' ) {

			$this->exec( "ALTER TABLE person AUTO_INCREMENT = 1" );
			$this->exec( "ALTER TABLE post AUTO_INCREMENT = 1" );
			$this->exec( "ALTER TABLE category AUTO_INCREMENT = 1" );
			$this->exec( "ALTER TABLE dummy AUTO_INCREMENT = 1" );

		}

		if ( $this->getDriver() === 'pgsql' ) {

			$this->exec( "SELECT setval('person_id_seq', 3)" );
			$this->exec( "SELECT setval('post_id_seq', 13)" );
			$this->exec( "SELECT setval('category_id_seq', 23)" );
			$this->exec( "SELECT setval('dummy_id_seq', 1, false)" );

		}

		// data

		// persons

		$this->exec( "DELETE FROM person" );

		$this->exec( "INSERT INTO person (id, name) VALUES (1, 'Writer')" );
		$this->exec( "INSERT INTO person (id, name) VALUES (2, 'Editor')" );
		$this->exec( "INSERT INTO person (id, name) VALUES (3, 'Chief Editor')" );

		// posts

		$this->exec( "DELETE FROM post" );

		$this->exec( "INSERT INTO post (id, title, date_published, author_id, editor_id) VALUES (11, 'Championship won', '2014-09-18', 1, NULL)" );
		$this->exec( "INSERT INTO post (id, title, date_published, author_id, editor_id) VALUES (12, 'Foo released', '2014-09-15', 1, 2)" );
		$this->exec( "INSERT INTO post (id, title, date_published, author_id, editor_id) VALUES (13, 'Bar released', '2014-09-21', 2, 3)" );

		// categories

		$this->exec( "DELETE FROM category" );

		$this->exec( "INSERT INTO category (id, title) VALUES (21, 'Tech')" );
		$this->exec( "INSERT INTO category (id, title) VALUES (22, 'Sports')" );
		$this->exec( "INSERT INTO category (id, title) VALUES (23, 'Basketball')" );

		// categorization

		$this->exec( "DELETE FROM categorization" );

		$this->exec( "INSERT INTO categorization (category_id, post_id) VALUES (22, 11)" );
		$this->exec( "INSERT INTO categorization (category_id, post_id) VALUES (23, 11)" );
		$this->exec( "INSERT INTO categorization (category_id, post_id) VALUES (21, 12)" );
		$this->exec( "INSERT INTO categorization (category_id, post_id) VALUES (21, 13)" );

		// dummy

		$this->exec( "DELETE FROM dummy" );

		$this->pdo->commit();
		$this->dirtyData = false;

	}

	function exec( $q ) {
		return $this->pdo->exec( $q );
	}

	function getDriver() {
		return $this->pdo->getAttribute( \PDO::ATTR_DRIVER_NAME );
	}

	public $pdo;
	public $statements = array();
	public $params = array();

	protected $dirtySchema = true;
	protected $dirtyData = true;

	public static $PDO;

}
