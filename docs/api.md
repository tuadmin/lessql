# API

This section covers the public, user-relevant API of LessQL.


## Setup

Creating a database context:

```php
$db = new \LessQL\Context( $pdo );
```


## Structure

Defining structural information (see [Conventions](conventions.md) for usage):

```php
$structure = $db->getStructure();
$structure->setAlias( $alias, $table );
$structure->setPrimary( $table, $column );
$structure->setReference( $table, $name, $column );
$structure->setBackReference( $table, $name, $column );
$structure->setRequired( $table, $column );
$structure->setRewrite( $rewriteFunc );
$structure->setIdentifierDelimiter( $delimiter ); // default is ` (backtick)
```


## Basic Finding

```php
// query 'table_name'
$sql = $db->table_name();
$sql = $db->query( 'table_name' );

// get first row
$row = $sql->first();

// iterate over resulting rows of a query
foreach ( $sql as $row ) {
    // ...
}

// finds and encodes all rows (requires PHP >= 5.4.0)
json_encode( $sql );

// get all rows as an array
$rows = iterator_to_array( $sql );

// get a row directly by primary key
$row = $db->table_name( $id );
$row = $db->query( 'table_name', $id );
```


## Deep Finding <small>and Traversal</small>

```php
$other = $sql->table_name();       // get one row, reference
$other = $sql->table_nameList();   // get many rows, back reference
$other = $sql->query( 'table_name' );
$other = $sql->query( 'table_nameList' );

$other = $row->table_name();       // get one row, reference
$other = $row->table_nameList();   // get many rows, back reference
$other = $row->query( 'table_name' );
$other = $row->query( 'table_nameList' );

$other = $row->table_name()->via( $key ); // use alternate foreign key
```

## Where

```php
// WHERE $column IS NULL
$sql2 = $sql->where( $column, null );

// WHERE $column = $value (escaped)
$sql2 = $sql->where( $column, $value );

// WHERE $column IN $array (escaped)
// $array containing null is respected with OR $column IS NULL
$sql2 = $sql->where( $column, $array );

// WHERE $column IS NOT NULL
$sql2 = $sql->whereNot( $column, null );

// WHERE $column != $value (escaped)
$sql2 = $sql->whereNot( $column, $value );

// WHERE $column NOT IN $array (escaped)
// $array containing null is respected with AND $column IS NOT NULL
$sql2 = $sql->whereNot( $column, $array );

// named and/or numeric params for PDO
$sql2 = $sql->where( $whereString, $paramArray );

// for each key-value pair, call $sql->where( $key, $value )
$sql2 = $sql->where( $array );

// for each key-value pair, call $sql->whereNot( $key, $value )  
$sql2 = $sql->whereNot( $array );
```


## Selected Columns, Order and Limit

```php
// identfiers NOT escaped, so expressions are possible
// multiple calls are joined with a comma
// if never called a default of '*' is used
$sql2 = $sql->select( $expr );

// $column will be escaped
// stacks
$sql2 = $sql->orderBy( $column );
$sql2 = $sql->orderBy( $column, 'ASC' );
$sql2 = $sql->orderBy( $column, 'DESC' );

$sql2 = $sql->limit( $count );
$sql2 = $sql->limit( $count, $offset );
$sql2 = $sql->paged( $pageSize, $page ); // pages start at 1
```

Note that `Result` objects are __immutable__.
All filter methods like `where` or `orderBy`
return a new `Result` instance with the new `SELECT` information.


## Manipulation

 Except for `insertPrepared`, the manpulation API generates statements that have to be executed manually using `$statement->exec()`.

```php
// generate an INSERT statement for a single row
// supports SQL fragments
// slow for many rows
$statement = $db->insert( $table, $row );

// generate an INSERT statement with multiple value lists
// supports fragments, but not supported in all PDO drivers (SQLite fails)
$statement = $db->insertBatch( $table, $rows );

// insert multiple rows using a prepared PDO statement directly
// does not support fragments (PDO limitation)
$lastResult = $db->insertPrepared( $table, $rows );

// generate UPDATE statement
// UPDATE ... SET ... [WHERE ...]
$statement = $db->update( $table, $set, $whereOptional, $paramsOptional );

// generate DELETE statement
// DELETE FROM ... [WHERE ...]
$statement = $db->delete( $table, $whereOptional, $paramsOptional );         
```


## Transactions

```php
$db->runTransaction( function ( $db ) {
    // transaction body
    // commit on success
    // rollback on exception
} );
```


## Rows

```php
// create row from scratch
$row = $db->createRow( $table, $properties = array() );

// get or set properties
$row->property;
$row->property = $value;
isset( $row->property );
unset( $row->property );

// array access is equivalent to property access
$row[ 'property' ];
$row[ 'property' ] = $value;
isset( $row[ 'property' ] );
unset( $row[ 'property' ] );

$row->setData( $array ); // sets data on row, extending it

// manipulation
$row->isClean();       // returns true if in sync with database
$row->exists();        // returns true if the row exists in the database
$row->save();          // inserts if not in database, updates changes (only) otherwise
$row->update( $data ); // set data and save
$row->delete();

// references
$other = $row->table_name();         // get one row, reference
$other = $row->table_nameList();     // get many rows, back reference
$other = $row->query( 'table_name' );
$other = $row->query( 'table_nameList' );

json_encode( $row );

// iterate over properties
foreach ( $row as $name => $value ) {
    // ...
}
```

Rows are mutable. Think of them as a representation of a live database row
which may be in sync with the database or not.


## SQL Statements and Fragments

TODO

## Results

TODO
