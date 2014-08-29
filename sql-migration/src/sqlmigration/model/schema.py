"""
Describes the current schema for the database version.
"""

from .base import (BaseObject, TABLE_TYPE, COLUMN_TYPE, VIEW_TYPE,
                   CONSTRAINT_TYPE, SEQUENCE_TYPE, PROCEDURE_TYPE,
                   SqlSet, LanguageSet)


class SchemaObject(BaseObject):
    def __init__(self, name, order, comment, object_type, changes):
        BaseObject.__init__(self, order, comment, object_type)
        self.__object_type = object_type
        self.__changes = changes or []
        self.__name = name

        # One time setting of the parent
        for ch in self.__changes:
            ch.parent = self

    @property
    def name(self):
        return self.__name

    @property
    def changes(self):
        """
        The changes that need to be applied to this object to upgrade it from
        the previous version.  If there were no changes, or this is the first
        time this object exists, then there will be no changes.

        :return: tuple(Change)
        """
        return self.__changes

    @property
    def sub_schema(self):
        """
        Returns the sub-schema objects for the object this represents.  This
        allows for access into the sub-object changes.

        :return: tuple(SchemaObject)
        """
        return []

    @property
    def constraints(self):
        return []


class ValueTypeValue(object):
    """
    Describes a value.
    """
    def __init__(self, str_value, numeric_value, boolean_value, date_value,
                 computed_value):
        assert computed_value is None or isinstance(computed_value, SqlSet)
        object.__init__(self)
        self.__str_value = str_value
        self.__numeric_value = numeric_value
        self.__boolean_value = boolean_value
        self.__date_value = date_value
        self.__computed_value = computed_value

    @property
    def str_value(self):
        return self.__str_value

    @property
    def numeric_value(self):
        return self.__numeric_value

    @property
    def boolean_value(self):
        return self.__boolean_value

    @property
    def date_value(self):
        return self.__date_value

    @property
    def computed_value(self):
        """

        :return: None or SqlSet
        """
        return self.__computed_value

    @property
    def requires_argument(self):
        """
        Will return False unless this has a non-None computed_value, and that
        computed value requires the code to provide an argument.

        :return: bool True if this value requires an argument to be given by
            code, False if the value is self-contained.
        """
        return (self.__computed_value is not None and
            len(self.__computed_value.arguments) > 0)


class Constraint(SchemaObject):
    def __init__(self, order, comment, constraint_type, column_names, details,
                 changes):
        SchemaObject.__init__(self, constraint_type, order, comment,
                              CONSTRAINT_TYPE, changes)
        assert isinstance(constraint_type, str)
        self.__constraint_type = _strip_keys(constraint_type)
        details = details or {}
        assert isinstance(details, dict)
        self.__details = details
        self.__column_names = tuple(column_names or [])

    @property
    def constraint_type(self):
        return self.__constraint_type

    @property
    def details(self):
        """
        A bit bucket of additional information about the constraint.
        Eventually, this may be better defined.

        :return: dict
        """
        return self.__details

    @property
    def column_names(self):
        return self.__column_names

    def get_columns_by_names(self, parent_schema):
        """

        :return: list of __columns in the parent_schema that match the
            column_names.  The column_names order will be maintained.
        """
        assert (isinstance(parent_schema, Table) or
                isinstance(parent_schema, View))
        ret = []
        for cn in self.column_names:
            for col in parent_schema.__columns:
                if col.name == cn:
                    ret.append(col)
        return ret


class SqlConstraint(Constraint):
    def __init__(self, order, comment, constraint_type, column_names, details,
                 sql_set, changes):
        Constraint.__init__(self, order, comment, constraint_type, column_names,
                            details, changes)
        assert isinstance(sql_set, SqlSet)
        self.__sql_set = sql_set

    @property
    def sql(self):
        """
        :return SqlSet:
        """
        return self.__sql_set


class LanguageConstraint(Constraint):
    """
    A constraint that is defined as software, rather than direct SQL.
    """
    def __init__(self, order, comment, constraint_type, column_names, details,
                 code, changes):
        Constraint.__init__(self, order, comment, constraint_type, column_names,
                            details, changes)
        assert isinstance(code, LanguageSet)
        self.__code = code

    @property
    def code(self):
        """
        :return LanguageSet:
        """
        return self.__code


class NamedConstraint(Constraint):
    def __init__(self, order, comment, constraint_type, column_names, details,
                 name, changes):
        Constraint.__init__(self, order, comment, constraint_type, column_names,
                            details, changes)
        assert isinstance(name, str)
        self.__name = name

    @property
    def name(self):
        return self.__name


class Column(SchemaObject):
    def __init__(self, order, comment, name, value_type, value, default_value,
                 auto_increment, remarks, before_column, after_column, position,
                 constraints, changes):
        assert value_type is not None
        assert value is None or isinstance(value, ValueTypeValue)
        assert default_value is None or isinstance(default_value,
                                                   ValueTypeValue)

        SchemaObject.__init__(self, name, order, comment, COLUMN_TYPE, changes)
        self.__name = name
        self.__value_type = value_type
        self.__value = value
        self.__default_value = default_value
        self.__auto_increment = auto_increment
        self.__remarks = remarks
        self.__before_column = before_column
        self.__after_column = after_column
        self.__position = position
        self.__constraints = constraints

    @property
    def name(self):
        return self.__name

    @property
    def value_type(self):
        return self.__value_type

    @property
    def value(self):
        return self.__value

    @property
    def default_value(self):
        return self.__default_value

    @property
    def auto_increment(self):
        return self.__auto_increment

    @property
    def remarks(self):
        return self.__remarks

    @property
    def before_column(self):
        return self.__before_column

    @property
    def after_column(self):
        return self.__after_column

    @property
    def position(self):
        return self.__position

    @property
    def constraints(self):
        return self.__constraints

    @property
    def sub_schema(self):
        return self.__constraints


class WhereClause(object):
    """
    Extra where clauses that can be optionally added to the code.  These
    can be chained together with AND or OR statements.
    """
    def __init__(self, name, sqlset):
        """
        :param SqlSet sqlset:
        :param str name:
        """
        assert isinstance(name, str)
        assert isinstance(sqlset, SqlSet)
        self.__name = name
        self.__sqlset = sqlset

    @property
    def name(self):
        return self.__name

    @property
    def sql(self):
        return self.__sqlset

    @property
    def arguments(self):
        return self.sql.arguments

    def sql_args(self, platforms, arg_converter):
        """
        Return the sql for the given platforms, with the argument values
        replaced, using the function "arg_converter", which takes the argument
        name as input, and outputs the prepared statement replacement string.

        :param arg_converter:
        :return:
        """
        return self.sql.sql_args(platforms, arg_converter)


class ExtendedSql(object):
    """
    Defines extra Sql statements that should be added to the generated code.

    TODO these should add possible column definitions for QUERY types.
    """
    def __init__(self, name, sql_type, sqlset, post_sqlset):
        """
        :param str name:
        :param str sql_type: the type of sql being performed in the operation
                (query, insert, update, delete, other)
        :param SqlSet sqlset:
        """
        assert isinstance(name, str)
        assert isinstance(sql_type, str)
        assert isinstance(sqlset, SqlSet)

        # FIXME this is parsing that should be done elsewhere
        sql_type = sql_type.strip().lower()
        if sql_type == 'wrapper':
            assert isinstance(post_sqlset, SqlSet)
            self.__is_wrapper = True
        else:
            assert post_sqlset is None
            self.__is_wrapper = False

        self.__name = name
        self.__sql_type = sql_type
        self.__sqlset = sqlset
        self.__post_sqlset = post_sqlset

    @property
    def name(self):
        return self.__name

    @property
    def sql_type(self):
        return self.__sql_type

    @property
    def is_wrapper(self):
        return self.__is_wrapper

    @property
    def sql(self):
        """
        In the case of the "wrapper" sql_type, this is the pre-execution
        sql.  In all other cases, this is the actual sql to run.
        """
        return self.__sqlset

    @property
    def post_sql(self):
        """
        This is the sql to run in the post-execution for the "wrapper" sql_type,
        and None in all other cases.
        """
        return self.__post_sqlset

    @property
    def arguments(self):
        return self.__sqlset.arguments

    def sql_args(self, platforms, arg_converter):
        """
        Return the sql for the given platforms, with the argument values
        replaced, using the function "arg_converter", which takes the argument
        name as input, and outputs the prepared statement replacement string.

        :param arg_converter:
        :return:
        """
        return self.sql.sql_args(platforms, arg_converter)

    def post_sql_args(self, platforms, arg_converter):
        """

        """
        assert self.is_wrapper
        return self.post_sql.sql_args(platforms, arg_converter)



class ColumnarSchemaObject(SchemaObject):
    """
    A schema type that has __columns.  This includes tables, views, and stored
    procedures that return tables.
    """
    def __init__(self, order, comment, catalog_name, schema_name, name,
                 columns, top_constraints, object_type, changes,
                 where_clauses, extended_sql):
        SchemaObject.__init__(self, name, order, comment, object_type,
                              changes)
        self.__catalog_name = catalog_name
        self.__schema_name = schema_name
        self.__columns = columns
        self.__top_constraints = top_constraints
        self.__where_clauses = where_clauses or []
        self.__extended_sql = extended_sql or []

    @property
    def catalog_name(self):
        return self.__catalog_name

    @property
    def schema_name(self):
        return self.__schema_name

    @property
    def __columns(self):
        return self.__columns

    @property
    def constraints(self):
        return self.__top_constraints

    @property
    def where_clauses(self):
        """
        :return list(WhereClause):
        """
        return self.__where_clauses

    @property
    def extended_sql(self):
        """
        :return list(SqlSet):
        """
        return self.__extended_sql

    @property
    def sub_schema(self):
        ret = list(self.__columns)
        ret.extend(self.constraints)
        return ret


class Table(ColumnarSchemaObject):

    def __init__(self, order, comment, catalog_name, schema_name, table_name,
                 table_space, columns, table_constraints, changes,
                 where_clauses, extended_sql):
        ColumnarSchemaObject.__init__(self, order, comment, catalog_name,
                                      schema_name, table_name, columns,
                                      table_constraints, TABLE_TYPE, changes,
                                      where_clauses, extended_sql)

        self.__table_name = table_name
        self.__table_space = table_space

    @property
    def table_name(self):
        return self.__table_name

    @property
    def table_space(self):
        return self.__table_space


class View(ColumnarSchemaObject):
    def __init__(self, order, comment, catalog_name, replace_if_exists,
                 schema_name, view_name, select_query, columns,
                 table_constraints, changes, where_clauses, extended_sql):
        ColumnarSchemaObject.__init__(self, order, comment, catalog_name,
                                      schema_name, view_name, columns,
                                      table_constraints, VIEW_TYPE, changes,
                                      where_clauses,
                                      extended_sql)

        assert isinstance(select_query, SqlSet)
        self.__replace_if_exists = replace_if_exists
        self.__view_name = view_name
        self.__select_query = select_query

    @property
    def replace_if_exists(self):
        return self.__replace_if_exists

    @property
    def view_name(self):
        return self.__view_name

    @property
    def select_query(self):
        """

        :return: SqlSet
        """
        return self.__select_query


class Sequence(SchemaObject):
    # FIXME implement this class
    def __init__(self, order, comment, changes):
        SchemaObject.__init__(self, '', order, comment, SEQUENCE_TYPE, changes)
        raise Exception("not implemented")


class Procedure(SchemaObject):
    # FIXME implement this class
    def __init__(self, order, comment, changes):
        SchemaObject.__init__(self, '', order, comment, PROCEDURE_TYPE, changes)
        raise Exception("not implemented")


def _strip_keys(key):
    for c in ' \r\n\t_-':
        key = key.replace(c, '')
    return key.lower()
