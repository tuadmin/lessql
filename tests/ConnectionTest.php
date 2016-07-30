<?php

class ConnectionTest extends BaseTest {

	function testConnection() {

		$connection = \LessQL\Connection::get( $this->pdo );
		$other = \LessQL\Connection::get( $this->pdo );

		$this->assertEquals( $connection, $other );
		$this->assertEquals( $this->getDriver(), $connection->getDriver() );

	}

}
