# About

LessQL is heavily inspired by [NotORM](http://notorm.com)</a>
which presents a novel, intuitive API to SQL databases.
Combined with an efficient implementation,
its concepts are very unique to all database layers out there,
whether they are ORMs, DBALs or something else.

In contrast to ORM, you work directly with tables and rows.
This has several advantages:

- __Write less:__
	No glue code/objects required. Just work with your database directly
	and truly blend with raw SQL at any time.
- __Transparency:__
	It is always obvious that your application code works with a 1:1 representation of your database.
- __Relational power:__
	Leverage the relational features of SQL databases,
	don't hide them using an inadequate&nbsp;abstraction.

For more in-depth opinion why ORM is not always desirable, see:

- <a href="http://www.yegor256.com/2014/12/01/orm-offensive-anti-pattern.html" title="ORM Is an Offensive Anti-Pattern">http://www.yegor256.com/2014/12/01/orm-offensive-anti-pattern.html</a>
- <a href="http://seldo.com/weblog/2011/08/11/orm_is_an_antipattern" title="ORM is an anti-pattern">http://seldo.com/weblog/2011/08/11/orm_is_an_antipattern</a>
- <a href="http://en.wikipedia.org/wiki/Object-relational_impedance_mismatch" title="Object-relational impedance mismatch">http://en.wikipedia.org/wiki/Object-relational_impedance_mismatch</a>

----

NotORM introduced a unique and efficient solution to database abstraction.
However, it does have a few weaknesses:

- The API is not always intuitive: `$result->name` is different from `$result->name()` and more.
- There is no difference between One-To-Many and Many-To-One associations in the API (LessQL uses the `List` suffix for that).
- There is no advanced save operation for nested structures.
- Defining your database structure is hard (involves sub-classing).
- The source code is very hard to read and understand.
- You cannot really blend with raw SQL and keep using the eager loading features.

LessQL addresses all of these issues, is fully tested, and actively maintained.

<a href="https://github.com/morris/lessql" title="Fork LessQL on GitHub">Contributions and Feedback are always welcome.</a>
