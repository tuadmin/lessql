# LessQL

[![Build Status](https://travis-ci.org/morris/lessql.svg?branch=master)](https://travis-ci.org/morris/lessql)
[![Test Coverage](https://codeclimate.com/github/morris/lessql/badges/coverage.svg)](https://codeclimate.com/github/morris/lessql/coverage)
[![Join the chat at https://gitter.im/morris/lessql](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/morris/lessql?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

A lightweight and performant alternative to Object-Relational Mapping for PHP.
Features a novel, intuitive approach to SQL writing.
Read more at __[LessQL.net](http://lessql.net)__


## Usage

```php
// SCHEMA
// user: id, name
// post: id, title, body, date_published, is_published, user_id
// categorization: category_id, post_id
// category: id, title

// Connection
$pdo = new PDO( 'sqlite:blog.sqlite3' );
$db = new LessQL\Context( $pdo );

// Find posts, their authors and categories efficiently:
// Eager loading of references happens automatically.
// This example only needs FOUR queries, one for each table.
$posts = $db->post()
	->where( 'is_published', 1 )
	->orderBy( 'date_published', 'DESC' );

foreach ( $posts as $post ) {
	$author = $post->user()->first();

	foreach ( $post->categorizationList()->category() as $category ) {
		// ...
	}
}

// Saving complex structures is easy
$row = $db->createRow( 'post', array(
	'title' => 'News',
	'body' => 'Yay!',
	'categorizationList' => array(
		array(
			'category' => array( 'title' => 'New Category' )
		),
		array( 'category' => $existingCategoryRow )
	)
);

// Creates a post, a new category, two new categorizations
// and connects them all correctly.
$row->save();
```


## Features

- Efficient deep finding through intelligent eager loading
- Constant number of queries, no N+1 problems
- Truly blend with raw [SQL](docs/sql.md) at any time
- Save complex, nested structures with one method call
- [Convention over configuration](docs/conventions.md)
- Concise and intuitive [API](docs/api.md)
- Work closely to your database: LessQL is not an ORM
- Fully tested with SQLite3, MySQL and PostgreSQL

Inspired mainly by NotORM, it was written from scratch to provide a clean API and simplified concepts. [About LessQL](docs/about.md)


## Installation

Install LessQL via composer: `composer require morris/lessql`.
LessQL requires PHP >= 5.3.4 and PDO.


## Documentation

- [Guide](docs/guide.md)
- [Conventions](docs/conventions.md)
- [SQL](docs/sql.md)
- [Events](docs/events.md)
- [Runners](docs/runners.md)
- [API](docs/api.md)
- [About](docs/about.md)
