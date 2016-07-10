# Changelog

## v1.0.0-beta1

- Major refactoring with breaking API changes
- The new core API features arbitrary, composable `SQL` statements and fragments
- `Database` was renamed to `Context`
- Schema information is now stored in a `Structure` object
- Select queries are represented by `SQL` objects
- `Result` now represents the result of an arbitrary SQL statement
- `Result` is iterable and has a `first` method, but `fetch` and `fetchAll` were removed
- `insert`, `update` and `delete` methods now generate statements which have to be executed with `->exec()`
- `$db->table( $table )` is now `$context->query( $table )`
- `$something->referenced( $table )` is now `$something->query( $table )`
- `$statement->where( $condition, $param1, $param2, ... )` is not possible anymore; use an array
- Aggregation functions where removed, use custom SQL for that
- Transactions are run with `$context->runTransaction( $callable )` and can be nested
- LessQL now throws `LessQL\Exception` where appropriate
- Added `Exception`, `Structure`, `Prepared`, and `Migration` classes

## v0.x.x

- See `v0` branch
