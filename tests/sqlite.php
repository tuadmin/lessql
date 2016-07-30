<?php

require 'BaseTest.php';
require 'vendor/autoload.php';

BaseTest::$PDO = new \PDO( 'sqlite:tests/shop.sqlite' );
