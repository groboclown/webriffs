"""
Describes the current schema for the database version.
"""

from .base import (BaseObject, TABLE_TYPE, COLUMN_TYPE, VIEW_TYPE,
                   CONSTRAINT_TYPE)


class SchemaObject(BaseObject):
    def __init__(self, order, comment, object_type, changes):
        BaseObject.__init__(self, order, comment, object_type)
        self.__object_type = object_type
        self.__changes = changes or []

        # One time setting of the parent
        for ch in self.__changes:
            ch.parent = self

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


class ValueTypeValue(object):
    """
    Describes a value.
    """
    def __init__(self, str_value, numeric_value, boolean_value, date_value,
                 computed_value):
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
        return self.__computed_value


class Constraint(SchemaObject):
    def __init__(self, order, comment, constraint_type, changes):
        SchemaObject.__init__(self, order, comment, CONSTRAINT_TYPE, changes)
        self.__constraint_type = constraint_type

    @property
    def constraint_type(self):
        return self.__constraint_type


class Column(SchemaObject):
    def __init__(self, order, comment, name, value_type, value, default_value,
                 auto_increment, remarks, before_column, after_column, position,
                 constraints, changes):
        assert value_type is not None
        assert value is None or isinstance(value, ValueTypeValue)
        assert default_value is None or isinstance(default_value,
                                                   ValueTypeValue)

        SchemaObject.__init__(self, order, comment, COLUMN_TYPE, changes)
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


class Table(SchemaObject):
    def __init__(self, order, comment, catalog_name, schema_name, table_name,
                 table_space, columns, changes):
        SchemaObject.__init__(self, order, comment, TABLE_TYPE, changes)
        self.__catalog_name = catalog_name
        self.__schema_name = schema_name
        self.__table_name = table_name
        self.__table_space = table_space
        self.__columns = columns

    @property
    def catalog_name(self):
        return self.__catalog_name

    @property
    def schema_name(self):
        return self.__schema_name

    @property
    def table_name(self):
        return self.__table_name

    @property
    def table_space(self):
        return self.__table_space

    @property
    def columns(self):
        return self.__columns

    @property
    def sub_schema(self):
        return self.columns


class View(SchemaObject):
    def __init__(self, order, comment, catalog_name, replace_if_exists,
                 schema_name, view_name, select_query, columns, changes):
        SchemaObject.__init__(self, order, comment, VIEW_TYPE, changes)
        self.__catalog_name = catalog_name
        self.__replace_if_exists = replace_if_exists
        self.__schema_name = schema_name
        self.__view_name = view_name
        self.__select_query = select_query
        self.__columns = columns

    @property
    def catalog_name(self):
        return self.__catalog_name

    @property
    def replace_if_exists(self):
        return self.__replace_if_exists

    @property
    def schema_name(self):
        return self.__schema_name

    @property
    def view_name(self):
        return self.__view_name

    @property
    def select_query(self):
        return self.__select_query

    @property
    def columns(self):
        return self.__columns

    @property
    def sub_schema(self):
        return self.columns





# TODO
# class Sequence
# class Procedure