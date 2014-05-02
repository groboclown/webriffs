"""
Manages the different versions of the schema.
"""


class SchemaVersion(object):
    """
    Represents a single version of the schema, along with the changes to
    get here from the previous version.

    The "version" must be an integer.
    """
    def __init__(self, version, top_changes, schema):
        object.__init__(self)
        assert type(version) == type(int)
        self.__version = version
        self.__top_changes = top_changes
        self.__schema = schema

    @property
    def version(self):
        return self.__version

    @property
    def top_changes(self):
        return self.__top_changes

    @property
    def schema(self):
        return self.__schema

    def __lt__(self, version):
        assert isinstance(version, SchemaVersion)
        return (self.version - version.version) < 0

    def __le__(self, version):
        assert isinstance(version, SchemaVersion)
        return (self.version - version.version) <= 0

    def __gt__(self, version):
        assert isinstance(version, SchemaVersion)
        return (self.version - version.version) > 0

    def __ge__(self, version):
        assert isinstance(version, SchemaVersion)
        return (self.version - version.version) >= 0