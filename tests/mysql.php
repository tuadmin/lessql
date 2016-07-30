<?php

require 'BaseTest.php';
require 'vendor/autoload.php';

BaseTest::$PDO = new \PDO( 'mysql:host=127.0.0.1;dbname=lessql', 'root' );
