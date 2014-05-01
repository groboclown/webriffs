SQL Formats
===========

The SQL files are stored in [Liquibase](http://www.liquibase.org/) formatted
YAML files, which allows for easy creation of upgradable database files.

Each table is stored in its own file.

Actual SQL files should be generated from these yaml files, with the head
revision in "install", and upgrades from old versions in the form "v1-2".


Conventions
===========

All table names are _singular_ and in _upper case_.

All column names are in mixed case with underlines separating the words.

All tables should include a unique `int` primary key in the form `Table_Name_Id`.
