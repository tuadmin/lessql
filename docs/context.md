# Context <small>and Caching</small>

By design, statements in LessQL are always bound to a context.
Each statement will be executed at most once in its context,
with the result being cached inside the context.
The result cache is then used in eager loading.

There are cases where you need to execute a statement multiple times
or you want to fetch objects in isolation,
e.g. when too many objects are eagerly loaded.
Use `$context->clear()` to clone the context with an empty cache:

```php
$context = new LessQL/Context( $pdo );

$context( 'SELECT * FROM &post WHERE id = ?', array( 4 ) )->first();
// Executed => title: Original

$context( 'UPDATE &post SET title = ?', array( 'Test' ) )->exec();
// Executed

$context( 'UPDATE &post SET title = ?', array( 'Test' ) )->exec();
// Not executed

$context( 'SELECT * FROM &post WHERE id = ?', array( 4 ) )->first();
// Not executed => title: Original

$cleared = $context->clear();
$cleared( 'SELECT * FROM &post WHERE id = ?', array( 4 ) )->first();
// Executed => title: Test
```
