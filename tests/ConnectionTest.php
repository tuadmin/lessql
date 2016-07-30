<?php

class ConnectionTest extends BaseTest {

	function testConnection() {
		$connection = \LessQL\Connection::get( $this->pdo );
		$other = \LessQL\Connection::get( $this->pdo );
		$this->assertEquals( $connection, $other );
	}

	function testDriver() {
		$connection = \LessQL\Connection::get( $this->pdo );
		$this->assertEquals( $this->getDriver(), $connection->getDriver() );
	}

	function testTransaction() {
		$connection = \LessQL\Connection::get( $this->pdo );
		$connection->runTransaction( function () {

		} );
		$connection->runTransaction( function () {

		} );
	}

	/**
     * @expectedException \LessQL\Exception
	 * @expectedExceptionMessage Transaction must be callable
     */
	function testTransactionCallable() {
		$connection = \LessQL\Connection::get( $this->pdo );
		$connection->runTransaction( 'notcallable' );
	}

	/**
     * @expectedException \Exception
	 * @expectedExceptionMessage test
     */
	function testTransactionException() {
		$connection = \LessQL\Connection::get( $this->pdo );
		$connection->runTransaction( function () {
			throw new \Exception( 'test' );
		} );
	}

	/**
     * @expectedException \Exception
	 * @expectedExceptionMessage test
     */
	function testTransactionNestedException() {
		$connection = \LessQL\Connection::get( $this->pdo );
		$connection->runTransaction( function () use ( $connection ) {
			$connection->runTransaction( function () {
				throw new \Exception( 'test' );
			} );
		} );
	}

	/**
     * @expectedException \LessQL\Exception
	 * @expectedExceptionMessage Must roll back, nested transaction failed: test
     */
	function testTransactionNestedCaughtException() {
		$connection = \LessQL\Connection::get( $this->pdo );
		$connection->runTransaction( function () use ( $connection ) {
			try {
				$connection->runTransaction( function () {
					throw new \Exception( 'test' );
				} );
			} catch ( \Exception $ex ) {
				// ignore
			}
		} );
	}

}
