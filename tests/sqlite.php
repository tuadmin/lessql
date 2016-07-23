<?php

$GLOBALS[ 'PDO' ] = new \PDO( 'sqlite:tests/shop.sqlite' );

require 'BaseTest.php';
require 'vendor/autoload.php';
