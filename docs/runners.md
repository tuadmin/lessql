# Runners

Runners are containers for reproducible one-time transactions on your database,
such as schema changes and migrations.
Transactions are sequentially defined and are only run if all previous actions
were successful.

```php
// schema/post.php
$context->createRunner()
	->once( 'createPost', 'CREATE TABLE post (id INT, title VARCHAR)' )
	->once( 'addPostBody', 'ALTER TABLE post ADD COLUMN body TEXT' )
	->once( 'addPostTimes', function ( $context ) {
		$context( 'ALTER TABLE post ADD COLUMN created DATETIME' )->exec();
		$context( 'ALTER TABLE post ADD COLUMN modified DATETIME' )->exec();
	} )
	->report();
```

Run `php schema/post.php` or visit the script via browser to apply the transactions.
The runner history is stored in the `history` table.
Use `$context->createRunner( 'my_history' )` to configure a different table.

To ensure reproducibility, runners deployed to production-like environments should be treated as append-only.
