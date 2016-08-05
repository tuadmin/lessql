# Guide


## Installation

LessQL requires PHP >= 5.3.4 and PDO. Installation via [Composer](http://packagist.org):

```
composer require morris/lessql
```


## Context

LessQL works on existing MySQL, PostgreSQL or SQLite3 databases.

The following tables are used throughout this guide:

```
user:           id, name
post:           id, title, body, date_published, is_published, user_id
categorization: category_id, post_id
category:       id, title
```

You can use [LessQL Runners](runners.md) to generate your database.


## Setup

Create a `PDO` instance and a `LessQL\Context` using it.
We will also need a few hints about our database so we define them at setup.

```php
$pdo = new \PDO( 'sqlite:blog.sqlite3' );
$db = new \LessQL\Context( $pdo );

$db->setAlias( 'author', 'user' );
$db->setPrimary( 'categorization', array( 'category_id', 'post_id' ) );
```

We define `author` to be a table alias for `user`
and a compound primary key for the `categorization` table.
See the [Structure](structure.md) section
for more information about schema hints.


## Finding <small>and Traversal</small>

The most interesting feature of LessQL is easy and performant traversal of associated tables.
Here, we're iterating over four tables in an intuitive way,
and the data is retrieved efficiently under the hood.

```php
foreach ( $db->post()
	->orderBy( 'date_published', 'DESC' )
	->where( 'is_published', 1 )
	->paged( 10, 1 ) as $post ) {

	// Get author of post
	// Uses the pre-defined alias, gets from user where id is post.author_id
	$author = $post->author()->first();

	// Get category titles of post
	$categories = array();

	foreach ( $post->categorizationList()->category() as $category ) {

		$categories[] = $category[ 'title' ];

	}

	// render post
	$app->renderPost( $post, $author, $categories );

}
```

LessQL creates only four queries to execute this example:

```sql
SELECT * FROM `post` WHERE `is_published` = 1 ORDER BY `published` DESC LIMIT 10 OFFSET 0
SELECT * FROM `user` WHERE `id` IN ( ... )
SELECT * FROM `categorization` WHERE `post_id` IN ( ... )
SELECT * FROM `category` WHERE `id` IN ( ... )
```

When traversing associations, LessQL always eagerly loads all references in one query.
This way, the number of queries is always constant,
no matter how "deep" you are traversing your database.

Let's step through the example in some detail.
The first part iterates over a subset of posts:

```php
foreach ( $db->post()
	->orderBy( 'date_published', 'DESC' )
	->where( 'is_published', 1 )
	->paged( 10, 1 ) as $post ) {
```

The `orderBy` and `where` calls are basic SQL,
`paged( 10, 1 )` limits to page 1 where pages have a size of 10 posts.

Note that `Result` objects are __immutable__.
All filter methods like `where` or `orderBy`
return a new `Result` instance with the new `SELECT` information.

Inside the loop, we have access to `$post`.
It is a `Row` instance which can be worked with like an associative array or object.
It can be modified, saved, deleted, and you can retrieve associated rows.


## Many-To-One

```php
// Get author of post
$author = $post->author()->first();
```

A post has one author, a *Many-To-One-Association*.
LessQL will look for `author_id` in the post table and find the corresponding author, if any.

Note the explicit `first()` to get the row.
This is required because you might want to get the author using other methods (`via`).


## One-To-Many <small>and Many-To-Many, too</small>

```php
// Get category titles of post
$categories = array();

foreach ( $post->categorizationList()->category() as $category ) {

	$categories[] = $category[ 'title' ];

}
```

A post has many categorizations, a *One-To-Many-Association*.
The `List` suffix in `$post->categorizationList()`
tells LessQL to look for `post_id` in the categorization table
and find all rows that point to our post.

In turn, the categorization table points to the category table.
This way we model a *Many-To-Many-Association* between posts and categories.

Note how we directly call `->category()` without intermediate rows.


## Saving

LessQL is capable of saving deeply nested structures with a single
method call.

```php
$row = $db->createRow( 'post', array(
	'title' => 'Fantasy Movie Review',
	'author' => array(
		'name' => 'Fantasy Guy'
	),
	'categorizationList' => array(

		array(
			'category' => array( 'title' => 'Movies' )
		),
		array(
			'category' => $existingFantasyCategory
		)

	)
) );

// wrapping this in a transaction is a good practice and more performant
$db->runTransaction( array( $row, 'save' ) );
```

LessQL generates all queries needed to save the structure, including
references:

```sql
INSERT INTO `post` ( `title`, `author_id` ) VALUES ( 'Fantasy Movie Review', NULL )
INSERT INTO `user` ( `name` ) VALUES ( 'Fantasy Guy' )
UPDATE `post` SET `user_id` = ... WHERE `id` = ...
INSERT INTO `category` ( `title` ) VALUES ( 'Movies' )
INSERT INTO `categorization` ( `post_id`, `category_id` ) VALUES ( ... )
INSERT INTO `categorization` ( `post_id`, `category_id` ) VALUES ( ... )
```

For this operation to work, two things are crucial:
First, `author_id` must be nullable.
Second, the database must know about the compound primary key of the `categorization` table.

Always define required columns and compound primary keys at setup.
See [Structure](structure.md) for more details.
