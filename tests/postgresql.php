<?php

require 'BaseTest.php';
require 'vendor/autoload.php';

BaseTest::$PDO = new \PDO( 'pgsql:host=127.0.0.1;port=5432;dbname=lessql;user=postgres' );
