# SQL Writing

At its core, LessQL is an enhanced SQL writing engine.
Invoking the context on SQL text and parameters returns objects
representing SQL statements and fragments:

```php
$q = new LessQL\Context( $pdo );

$posts = $q( 'SELECT * FROM &post WHERE id = ?', array( 4 ) );
$orderByTitle = $q( 'ORDER BY title' );
```

## Tables

*Table references* prefixed with an ampersand are automatically quoted and
rewritten using the rewrite function from the database structure
(see [Structure](structure.md)).

Note that LessQL infers the primary table of any query
from the first `&table` occurrence and cannot identify regular table names.

## Parameters

In addition to regular parameters like `:name` and `?`,
LessQL introduces *immediate parameters* which are resolved
before preparing the statement. They can hold arbitrary values like arrays, `NULL`, and other SQL fragments
and are written as `::name` or `??`.

Immediate parameters enable powerful composition:

```php
$ids = array( 1, 2, 3 );
$posts = $q( 'SELECT * FROM &post WHERE id IN (??) ?? LIMIT ??',
	array( $ids, $orderByTitle, count( $ids ) ) );

// use $posts as sub query
$tags = $q( 'SELECT * FROM &tag WHERE post_id in (::posts) ORDER BY name ::dir',
	array(
		'posts' => $posts
		'dir' => $q( 'ASC' )
	) );
```

### Reserved Parameters

Internally, LessQL uses immediate parameters to implement common use-cases:

```php
$posts = $q->post();
// => SELECT ::select FROM &post WHERE ::where ::orderBy ::limit

// this...
$posts = $posts->where( 'id', 4 )
	->orderBy( 'created', 'DESC' )
	->limit( 10 );

// ... could also be written like this:
$posts = $posts->bind( array(
	'where' => $q( 'id = 4' ),
	'orderBy' => $q( 'ORDER BY created DESC' ),
	'limit' => $q( 'LIMIT 10' )
) );
```
