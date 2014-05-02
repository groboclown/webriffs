SQL Formats
===========

The SQL files are stored in [Sql Migration](../sql-migration/README.md)
formatted YAML files, which allows for easy creation of raw schema,
upgradable schema, and custom files.

Actual SQL files should not be generated.


Conventions
===========

Each table is stored in its own file.

All table names are _singular_ and in _upper case_.

All column names are in mixed case with underlines separating the words.

All tables should include a unique `int` primary key in the form `Table_Name_Id`.

All tables include a `Created_On` timestamp field (not null), and a
`Last_Updated_On` timestamp field (null).  These should be updated as part of
normal sql operations.

Foreign keys should be marked as such.  The key should be the table name and
the foreign table's key name, joined by two underscores.
