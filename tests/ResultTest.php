<?php

class ResultTest extends BaseTest {

	function testQuery() {
		$db = $this->db();
		$db->post()->exec()->author()->exec();
	}

	/**
     * @expectedException \LessQL\Exception
	 * @expectedExceptionMessage Unknown table/alias: tag
     */
	function testQueryUnknown() {
		$db = $this->db();
		$db->post()->exec()->tag()->exec();
	}

}
