<?php

$root = dirname( dirname( dirname( __FILE__ ) ) );
require $root . '/vendor/autoload.php';

$pdo = new \PDO( 'sqlite:' . $root . '/examples/data/blog.sqlite' );
$context = new \LessQL\Context( $pdo );

$context->createRunner()
	->once( 'createPost', 'CREATE TABLE post (id INT, title VARCHAR)' )
	->once( 'addPostBody', 'ALTER TABLE post ADD COLUMN body TEXT' )
	->once( 'addPostTimes', function ( $context ) {
		$context( 'ALTER TABLE post ADD COLUMN created DATETIME' )->exec();
		$context( 'ALTER TABLE post ADD COLUMN modified DATETIME' )->exec();
	} )
	->report();
