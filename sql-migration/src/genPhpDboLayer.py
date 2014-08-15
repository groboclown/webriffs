#!/usr/bin/python3

import os
import sys
import sqlmigration
import time

FAIL_IF_EXISTS = True

parent_class = None
namespace = None
output_dir = None
schema_by_name = {}

PLATFORMS = ['mysql']

# FIXME need better implementation when a collection comes in
def PREPSQL_CONVERTER(a):
    return ':' + a.name

# TODO create the where clause objects within the file.
# TODO add extended sql functions.
# TODO make the return value be more robust - make it an "object" where it
#    has whether it was an error or not, and row count (if applicable),
#    generated IDs, and so on.


def generate_file(analysis_obj):
    assert isinstance(analysis_obj, sqlmigration.codegen.ColumnSetAnalysis)

    sql_name = analysis_obj.sql_name
    class_name = generate_php_name(sql_name)
    file_name = os.path.join(output_dir, class_name + '.php')
    if os.path.exists(file_name) and FAIL_IF_EXISTS:
        raise Exception("Will not overwrite " + file_name)

    uses = ''
    if parent_class.count('\\') > 0:
        uses = 'use ' + parent_class[0: parent_class.find('\\')] + ';'

    with open(file_name, 'w') as f:
        f.writelines('\n'.join([
            '<?php',
            '',
            'namespace ' + namespace + ';',
            '',
            'use PDO;',
            uses,
            '',
        ]))
        
        if len(analysis_obj.schema.where_clauses) > 0:
            f.writelines('\n'.join([
                '', '',
                'class ' + class_name + '__WhereParent {',
                # No need to pass by reference
                '    public function bindVariables($data) {}',
                '}',
                '', ''
            ]))
            
        
        for w in analysis_obj.schema.where_clauses:
            assert isinstance(w, sqlmigration.model.WhereClause)
            f.writelines('\n'.join([
                '', '',
                '/**',
                ' * Where clause for ' + sql_name,
                ' */',
                'class ' + class_name + '_' + generate_php_name(w.name) +
                ' extends ' + class_name + '__WhereParent {',
                ''
            ]))
            
            if len(w.arguments) > 0:
                for a in w.arguments:
                    f.write('    public $' + a.name + ';\n')
                f.write('    function __construct(' +
                    (', '.join(('$' + a.name) for a in w.arguments)) + ') {\n')
                for a in w.arguments:
                    f.write('        $this->' + a.name + ' = $' + a.name + ';\n')
                f.write('    }\n')
            f.writelines('\n'.join([
                '', '',
                '    public function bindVariables(&$data) {',
                '',
            ]))
            for a in w.arguments:
                f.write('        $data["' + a.name + '"] = $this->' + a.name + ';\n')
            f.writelines('\n'.join([
                '    }', '', ''
                '    public function __toString() {',
                '        return \'' +
                sqlmigration.codegen.php.escape_php_string(
                       w.sql_args(PLATFORMS, PREPSQL_CONVERTER)) + '\';',
                '    }', '',
                '}', ''
            ]))
        
        f.writelines('\n'.join([
            '', '',
            '/**',
            ' * DBO object for ' + sql_name,
            ' *',
            ' * Generated on ' + time.asctime(time.gmtime(time.time())),
            ' */',
            'class ' + class_name + ' extends ' + parent_class + ' {',
            '    public static $INSTANCE;',
            '    public static $TABLENAME = "' + sql_name + '";',
            '',
            '    public function writeLockTable($db, $invoker) {',
            '        return $this->lockTable($db, $invoker, "' + sql_name +
                    ' WRITE");',
            '    }',
            '',
            '    public function readLockTable($db, $invoker) {',
            '        return $this->lockTable($db, $invoker, "' + sql_name +
                    ' READ");',
            '    }',
            '',
            '    public function writeLockTables($db, $tables, $invoker) {',
            '        assert(sizeof($tables) > 0);',
            '        return $this->lockTable($db, $invoker, implode(" WRITE, ", $tables)." WRITE");',
            '    }',
            '',
            '    public function readLockTables($db, $tables, $invoker) {',
            '        assert(sizeof($tables) > 0);',
            '        return $this->lockTable($db, $invoker, implode(" READ, ", $tables)." READ");',
            '    }',
            '',
        ]))
        
        
        f.write('\n'.join(generate_read(analysis_obj)))

        if not analysis_obj.is_read_only:
            f.writelines('\n'.join(generate_create(analysis_obj)))
            f.writelines((line + '\n') for line in generate_update(
                analysis_obj))
            f.writelines(line + '\n' for line in generate_delete(
                analysis_obj))
        
        f.writelines('\n'.join(generate_extended_sql(analysis_obj)))

        f.writelines('\n'.join(generate_validations(
            analysis_obj)))

        f.write('\n'.join([
            '',
            '    private function createReturn($stmt, $extractor) {',
            '        $errs = $stmt->errorInfo();',
            '        $ret = array("haserror" => false, "rowcount" => 0, ' +
            '"success" => true, "result" => null);',
            '        if ($errs[1] !== null) {',
            '            $ret["haserror"] = true;',
            '            $ret["success"] = false;',
            '            $ret["error"] = $errs[2];',
            '            $ret["errorcode"] = $errs[1];',
            '        } else {',
            '            $ret["rowcount"] = $stmt->rowCount();',
            '            $ret["result"] = $extractor($stmt);',
            '        }',
            # FIXME is there a close statement to run?
            '        //$stmt->close();',
            '        return $ret;',
            '    }', '',
            '',
            '    private function lockTable($db, $invoker, $locks) {',
            '        $stmt = $db->prepare("LOCK TABLES ".$locks);',
            '        $stmt->execute($data);',
            '        $errs = $stmt->errorInfo();',
            '        if ($errs[1] !== null) {',
            '            return $this->createReturn($stmt, null);',
            '        }',
            '        $except = null;',
            '        $ret = null;',
            '        try {',
            '            $ret = $invoker();',
            '        } catch (Exception $e) {',
            '            $except = $e;',
            '        }',
            '        $stmt = $db->prepare("UNLOCK TABLES");',
            '        $stmt->execute($data);',
            '        $errs = $stmt->errorInfo();',
            # Regardless of whether the unlock caused an error or not, use
            # the top-level invoker error as the return, if it had an error.
            '        if ($except !== null) {',
            '            throw $except;',
            '        }',
            '        if ($ret !== null && $ret["haserror"]) {',
            '            return $ret;',
            '        }',
            # IF there was an error only in the unocking, then return that as
            # the error.
            '        if ($errs[1] !== null) {',
            '            return $this->createReturn($stmt, null);',
            '        }',
            '        return $ret;',
            '    }',
            '',
            '}',
            class_name + '::$INSTANCE = new ' + class_name + ';',
            ''
        ]))


def generate_read(analysis_obj):
    assert isinstance(analysis_obj, sqlmigration.codegen.ColumnSetAnalysis)

    read_data = sqlmigration.codegen.ReadQueryData(analysis_obj, PLATFORMS,
                                                   'php')
    default_order_by = None
    always_order_by_clause = ' '
    if len(analysis_obj.primary_key_columns) == 1:
        default_order_by = analysis_obj.primary_key_columns[0]
        assert isinstance(default_order_by, sqlmigration.codegen.ColumnAnalysis)
        always_order_by_clause = ' ORDER BY '
    escaped_sql = read_data.sql.replace("'", "\\'")
    arg_names = []
    arg_arg = ''
    if len(read_data.arguments) > 0:
        arg_names = [('$' + a) for a in read_data.arguments]
        arg_arg = ', ' + ', '.join(arg_names)
    
    where_arg = ''
    if len(analysis_obj.schema.where_clauses) > 0:
        where_arg = ', $whereClauses = null'

    # FIXME add where clause support
    ret = [
        '',
        '    /**',
        '     * Returns the number of rows in the table.',
        '     */',
        '    public function countAll($db' + where_arg + ') {',
        '        $sql = \'SELECT COUNT(*) FROM ' + analysis_obj.sql_name +
        '\';',
        '        $data = array();',
    ]
    ret.extend(generate_where_clause(analysis_obj, False))
    ret.extend([
        '        $stmt = $db->prepare($sql);',
        '        $stmt->execute($data);',
        '        return $this->createReturn($stmt, function ($s) {',
        '            return intval($s->fetchColumn());',
        '        });',
        '    }', ''
    ])

    ret.extend([
        '',
        '    /**',
        '     * Reads the row data without filters.',
        '     */',
        '    public function readAll($db' + arg_arg + where_arg +
        ', $order = false, $start = -1, $end = -1) {',
        '        $sql = \'' + escaped_sql + '\';',
    ])
    if len(arg_names) > 0:
        ret.append('        $data = array(')
        # Notice how this skips the use of arg_names, and recreates those values
        for a in read_data.arguments:
            ret.append('            \'' + a + '\' => $' + a + ',')
        ret.append('        );')
    else:
        ret.append('        $data = array();')
    if len(analysis_obj.schema.where_clauses) > 0:
        ret.extend(generate_where_clause(analysis_obj, False))
    ret.append('        $sql .= \'' + always_order_by_clause + '\';')
    order_code = []
    if default_order_by is not None:
        order_code.extend([
            '        if (! $order) {',
            '            $sql .= \'' + default_order_by.sql_name + '\';',
            '        } else {',
            '            $sql .= $order;',
            '        }',
        ])
    else:
        order_code.extend([
            '        if (!! $order) {',
            '            $sql .= " ORDER BY " . $order;',
            '        }'
        ])
    ret.extend(order_code)
    ret.extend([
        '        if ($start >= 0 && $end > 0) {',
        '            $sql .= \' LIMIT \'.$start.\',\'.$end;',
        '        }',
        '        $stmt = $db->prepare($sql);',
        '        $stmt->setFetchMode(PDO::FETCH_ASSOC);',
        '        $stmt->execute($data);',
        '        return $this->createReturn($stmt, function ($s) {',
    ])
    if len(analysis_obj.get_read_validations()) > 0:
        # TODO add the invalid rows to a "invalidrows" array in the return
        # structure.
        ret.extend([
            '            $rows = array();',
            '            foreach ($s->fetchAll() as $row) {',
            '                if (!$this->validateRead($row)) {',
            '                    return null;',
            '                }',
            '                $rows[] = $row;',
            '            }',
            ''
        ])
    else:
        ret.extend([
            '            return $s->fetchAll();',
        ])
    ret.extend([
        '        });',
        '    }', '',
    ])


    # NOTE: no where clause object support for the "any" 
    ret.extend([
        '',
        '    public function countAny($db, $whereClause = false, '
        '$data = false) {',
        '        $whereClause = $whereClause || "";',
        '        $data = $data || array();',
        '        $stmt = $db->prepare(\'SELECT COUNT(*) FROM ' +
        analysis_obj.sql_name + ' \'.$whereClause);',
        '        $stmt->execute($data);',
        '        return $this->createReturn($stmt, function ($s) {',
        '            return intval($s->fetchColumn());',
        '        });',
        '    }', '', '',
        '    public function readAny($db, $query, $data, $start = -1, '
        '$end = -1) {',
        '        if ($start >= 0 && $end > 0) {',
        '            $query .= \' LIMIT \'.$start.\',\'.$end;',
        '        }',
        '        $stmt = $db->prepare($query);',
        '        $stmt->setFetchMode(PDO::FETCH_ASSOC);',
        '        $stmt->execute($data);',
        # No validation can be performed with the results, because we don't know
        # what's in the results.
        '        return $this->createReturn($stmt, function ($s) {',
        '            return $s->fetchAll();',
        '        });',
        '    }',
        '',
    ])

    for read_by_columns in analysis_obj.get_selectable_column_lists():
        assert len(read_by_columns) > 0

        # TODO if the read-by is a primary key or unique key, then the
        # order MUST NOT be part of the query, and even the start/end should
        # be removed.

        # each column in the column lists comes from the current table, and
        # never from a joined-to table.  So, we don't need to worry about
        # the table name as part of the column name.
        
        read_by_analysis = [analysis_obj.get_column_analysis(c)
                            for c in read_by_columns]
        read_by_names = [c.sql_name for c in read_by_analysis]

        title = '_x_'.join(read_by_names)
        arg_list = list(read_by_names)
        arg_list.extend(read_data.arguments)
        args = '$' + (', $'.join(arg_list))
        where_clause = read_data.where_clause
        if read_data.has_where_clauses:
            where_clause += ' AND '
        else:
            where_clause += ' WHERE '
        where_clause += (' AND '.join(((col + ' = :' + col)
                                      for col in read_by_names)))

        setup_code = ['        $data = array(']
        # Notice how this skips the use of arg_names, and recreates those values
        for a in arg_list:
            setup_code.append('            \'' + a + '\' => $' + a + ',')
        setup_code.append('        );')

        clause_sql = (read_data.from_clause + where_clause).\
            replace("'", "\\'")
        select = ('SELECT ' + read_data.select_columns_clause +
                  read_data.from_clause + where_clause).replace("'", "\\'")

        ret.extend([
            '',
            '    public function countBy_' + title + '($db, ' + args +
            where_arg + ') {',
            '        $sql = \'SELECT COUNT(*) ' + clause_sql + '\';',
        ])
        ret.extend(setup_code)
        ret.extend(generate_where_clause(analysis_obj, True))
        ret.extend([
            '        $stmt = $db->prepare($sql);',
            '        $stmt->execute($data);',
            '        return $this->createReturn($stmt, function ($s) {',
            '            return intval($s->fetchColumn());',
            '        });',
            '    }', '' '',
            '    public function readBy_' + title + '($db, ' + args +
            where_arg + ', $order = false, $start = -1, $end = -1) {',
            '        $sql = \'' + select + '\';',
        ])
        ret.extend(setup_code)
        ret.extend(generate_where_clause(analysis_obj, True))
        ret.append('        $sql .= \'' + always_order_by_clause + '\';')
        ret.extend(order_code)
        ret.extend([
            '        if ($start >= 0 && $end > 0) {',
            '            $sql .= \' LIMIT \'.$start.\',\'.$end;',
            '        }',
            '        $stmt = $db->prepare($sql);',
            '        $stmt->setFetchMode(PDO::FETCH_ASSOC);',
            '        $stmt->execute($data);',
            # FIXME correct the return/error check to use createReturn 
            '        return $this->createReturn($stmt, function ($s) {',
        ])

        if len(analysis_obj.get_read_validations()) > 0:
            ret.extend([
                '            $rows = array();',
                '            foreach ($s->fetchAll() as $row) {',
                '                if (!$this->validateRead($row)) {',
                '                    return null;',
                '                }',
                '                $rows[] = $row;',
                '            }',
                '            return $rows',
            ])
        else:
            ret.extend([
                '            return $s->fetchAll();',
            ])

        ret.extend([
            '        });',
            '    }', '',
        ])

    return ret


def generate_create(analysis_obj):
    assert isinstance(analysis_obj, sqlmigration.codegen.ColumnSetAnalysis)
    assert not analysis_obj.is_read_only

    create_data = sqlmigration.codegen.CreateQuery(analysis_obj, PLATFORMS,
                                                   'php', PREPSQL_CONVERTER)

    php_argument_list = [('$' + a.name)
                         for a in create_data.required_input_arguments]
    php_argument_list.extend([('$' + a.name + ' = false')
                              for a in create_data.optional_input_arguments])

    # we can have no arguments if the table is essentially just an ID.
    php_argument_str = ''
    if len(php_argument_list) > 0:
        php_argument_str = ', ' + (', '.join(php_argument_list))

    ret = [
        '',
        '    public function create($db' + php_argument_str + ') {',
        '        $sql = \'INSERT INTO ' + analysis_obj.sql_name + ' (' +
        # FIXME escape the php string
        ', '.join(c.column_name for c in create_data.required_input_values) +
        '\';',
        '        $values = \'' + (', '.join(
            # FIXME escape the php string
            c.get_sql_value(True) for c in create_data.required_input_values)) +
        '\';',
    ]
    if len(create_data.required_input_values) <= 0:
        ret.append('        $has_columns = false;')

    # FIXME This "where" doesn't really work right, and it doesn't look like it
    # will ever really work right.
    has_where = False
    for r in create_data.where_values.values():
        if len(r) > 0:
            has_where = True
            break

    if has_where:
        ret.append('        $where = " FROM ' + analysis_obj.sql_name +
                   ' WHERE 1 = 1";')

    data_values = [
        '        $data = array(',
    ]
    for r in create_data.required_input_values:
        assert isinstance(r, sqlmigration.codegen.InputValue)
        code = r.get_code_value(True)
        if code is not None:
            ret.append(code)
        for arg in r.get_sql_arguments(True):
            assert isinstance(arg, sqlmigration.model.SqlArgument)
            # FIXME use the type and is_collection to generate this
            data_values.append('            \'' + arg.name + '\' => $' + arg.name + ',')

        for wr in create_data.where_values[r]:
            assert isinstance(wr, sqlmigration.codegen.InputValue)
            code = wr.get_code_value(True)
            if code is not None:
                ret.append(code)
            for arg in wr.get_sql_arguments(True):
                assert isinstance(arg, sqlmigration.model.SqlArgument)
                data_values.append('            \'' + arg.name + '\' => $' + arg.name +
                                   ',')
            ret.append('        $where .= \' AND ' + wr.get_sql_value(True) +
                       '\';')

    ret.extend(data_values)
    ret.append('        );')

    for r in create_data.optional_input_values:
        assert isinstance(r, sqlmigration.codegen.InputValue)
        arguments = create_data.value_arguments[r]
        assert arguments is not None and len(arguments) > 0
        s1 = ['$' + a.name + ' !== false' for a in arguments]
        s2 = []
        s3 = []
        code = r.get_code_value(True)
        if code is not None:
            s2.append(code)
        code = r.get_code_value(False)
        if code is not None:
            s3.append(code)
        for a in r.get_sql_arguments(True):
            assert isinstance(a, sqlmigration.model.SqlArgument)
            s2.append('            $data[\'' + a.name + '\'] = $' + a.name + ';')
            if len(create_data.required_input_values) <= 0:
                s2.extend([
                    '                if (! $has_columns) {',
                    '                    $has_columns = true;',
                    '                } else {',
                    '                    $sql .= \', \';',
                    '                    $values .= \', \';',
                    '                }',
                    '                $sql .= \'' + r.column_name + '\';',
                    '                $values .= \'' +
                    r.get_sql_value(True) + '\';',
                ])
            else:
                s2.extend([
                    '                $sql .= \', ' + r.column_name + '\';',
                    '                $values .= \', ' +
                    r.get_sql_value(True) + '\';',
                ])
        for a in r.get_sql_arguments(False):
            assert isinstance(arg, sqlmigration.model.SqlArgument)
            s3.append('            $data[\'' + a.name + '\'] = $' + a.name + ';')
            if len(create_data.required_input_values) <= 0:
                s3.extend([
                    '                if (! $has_columns) {',
                    '                    $has_columns = true;',
                    '                } else {',
                    '                    $sql .= \', \';',
                    '                    $values .= \', \';',
                    '                }',
                    '                $sql .= \'' + r.column_name + '\';',
                    '                $values .= \'' +
                    r.get_sql_value(False) + '\';',
                ])
            else:
                s3.extend([
                    '                $sql .= \', ' + r.column_name + '\';',
                    '                $values .= \', ' +
                    r.get_sql_value(False) + '\';',
                ])

        for wr in create_data.where_values[r]:
            assert isinstance(wr, sqlmigration.codegen.InputValue)
            code = wr.get_code_value(True)
            if code is not None:
                s2.append(code)
            for arg in wr.get_sql_arguments(True):
                assert isinstance(arg, sqlmigration.model.SqlArgument)
                s2.append('            $data[\'' + arg.name + '\'] = $' + arg.name + ';')
            sv = wr.get_sql_value(True)
            if sv is not None:
                s2.append('        $where .= \' AND ' + sv + '\';')

            code = wr.get_code_value(False)
            if code is not None:
                s3.append(code)
            for arg in wr.get_sql_arguments(False):
                assert isinstance(arg, sqlmigration.model.SqlArgument)
                s3.append('            $data[\'' + arg.name + '\'] = $' + arg.name + ';')
            sv = wr.get_sql_value(False)
            if sv is not None:
                s3.append('        $where .= \' AND ' + sv + '\';')

        ret.append('        if (' + (' && '.join(s1)) + ') {')
        ret.extend(s2)
        if len(s3) > 0:
            ret.append('        } else { ')
            ret.extend(s3)
        ret.append('        }')

    ret.extend([
        '        if (! $this->validateWrite($data)) {',
        '            return false;',
        '        }',
    ])

    if has_where:
        ret.append('        $sql .= \') SELECT \'.$values.$where;')
    else:
        ret.append('        $sql .= \') VALUES (\'.$values.\')\';')

    ret.extend([
        '        $stmt = $db->prepare($sql);',
        '        $stmt->execute($data);',
        '        $ret = $this->createReturn($stmt, function ($s) {',
        '            return true;',
        '        });',
    ])

    if create_data.generated_column_name is not None:
        ret.append('        $ret["result"] = $db->lastInsertId();')

    ret.extend([
        '        return $ret;',
        '    }', ''
    ])
    
    
    # -------------------------------------------------------------------
    # Create the upsert command (insert or update if exists)
    # We should only generate this if:
    #   - there is no autoincrement column
    #   - there is a unique index or primary key
    # We should also generate this for each primary key / unique index.
    
    
    primary_keys = analysis_obj.primary_key_columns
    required_input_columns = list(create_data.required_input_values)
    non_index_required_columns = list(create_data.required_input_values)
    allow_upsert = len(primary_keys) > 0
    for pk in primary_keys:
        assert isinstance(pk, sqlmigration.codegen.ColumnAnalysis)
        if pk.auto_gen:
            allow_upsert = False
        else:
            if php_argument_list.count('$' + pk.sql_name) > 0:
                php_argument_list.remove('$' + pk.sql_name)
            if pk not in required_input_columns:
                required_input_columns.append(pk)
            if pk in non_index_required_columns:
                non_index_required_columns.remove(pk)
    allow_upsert = allow_upsert and len(php_argument_list) > 0
    
    if allow_upsert:
        #print("non index required columns: "+repr(non_index_required_columns))
        update_values = {}
        for cu in analysis_obj.columns_for_update:
            assert isinstance(cu, sqlmigration.codegen.ColumnAnalysis)
            cuv = cu.update_value
            if cuv is not None:
                cuvc = cuv.constraint
                # TODO in the future, this might be code
                assert isinstance(cuvc, sqlmigration.model.SqlConstraint)
                update_values[cu.sql_name] = cuvc.sql_args(
                    PLATFORMS, PREPSQL_CONVERTER)
            else:
                update_values[cu.sql_name] = PREPSQL_CONVERTER(cu.name_as_sql_argument)
        ret.extend([
            '',
            '    public function upsert($db, $' +
            (', $'.join(c.sql_name for c in primary_keys)) + ', ' +
            (', '.join(php_argument_list)) +
            ') {',
            '        $sql = \'INSERT INTO ' + analysis_obj.sql_name + ' (' +
            # FIXME escape the php string
            ', '.join(c.column_name for c in create_data.required_input_values) +
            '\';',
            '        $values = \'' + (', '.join(
                # FIXME escape the php string
                c.get_sql_value(True) for c in create_data.required_input_values)) +
            '\';',
        ])
        dup_start = []
        for c in non_index_required_columns:
            if c.column_name in update_values:
                dup_start.append(c.column_name + ' = ' +
                                 update_values[c.column_name])
        ret.append('        $dup = \' ON DUPLICATE KEY UPDATE ' + (', '.join(
                dup_start)) + '\';')
        data_values = [ '        $data = array(' ]
        for r in create_data.required_input_values:
            assert isinstance(r, sqlmigration.codegen.InputValue)
            code = r.get_code_value(True)
            if code is not None:
                ret.append(code)
            for arg in r.get_sql_arguments(True):
                assert isinstance(arg, sqlmigration.model.SqlArgument)
                data_values.append('            \'' + arg.name + '\' => $' + arg.name + ',')
        data_values.append('        );')
        ret.extend(data_values)
        
        # Note that this is somewhat of a cut-n-paste from above, but not quite
        if len(non_index_required_columns) <= 0:
            ret.append('        $needsDupComma = false;')
        for r in create_data.optional_input_values:
            assert isinstance(r, sqlmigration.codegen.InputValue)
            arguments = create_data.value_arguments[r]
            assert arguments is not None and len(arguments) > 0
            s1 = ['$' + a.name + ' !== false' for a in arguments]
            s2 = []
            s3 = []
            code = r.get_code_value(True)
            if code is not None:
                s2.append(code)
            code = r.get_code_value(False)
            if code is not None:
                s3.append(code)
            for a in r.get_sql_arguments(True):
                assert isinstance(a, sqlmigration.model.SqlArgument)
                if r.column_name in update_values:
                    if len(non_index_required_columns) <= 0:
                        s2.extend([
                             '        if ($needsDupComma) {',
                             '            $dup .= \', \';',
                             '        } else {',
                             '            $needsDupComma = true;',
                             '        }',
                        ])
                    s2.append('            $dup .= \'' +
                              (len(non_index_required_columns) <= 0 and '' or ', ') +
                              r.column_name + ' = ' + update_values[r.column_name] + '\';')
                s2.extend([
                    '            $data[\'' + a.name + '\'] = $' + a.name + ';'
                    '            $sql .= \', ' + r.column_name + '\';',
                    '            $values .= \', ' +
                    r.get_sql_value(True) + '\';',
                ])
            for a in r.get_sql_arguments(False):
                assert isinstance(a, sqlmigration.model.SqlArgument)
                s3.append('            $data[\'' + a.name + '\'] = $' + a.name + ';')
                s3.extend([
                    '                $sql .= \', ' + r.column_name + '\';',
                    '                $values .= \', ' +
                    r.get_sql_value(False) + '\';',
                ])
    
            ret.append('        if (' + (' && '.join(s1)) + ') {')
            ret.extend(s2)
            if len(s3) > 0:
                ret.append('        } else { ')
                ret.extend(s3)
            ret.append('        }')
    
        ret.extend([
            '        if (! $this->validateWrite($data)) {',
            '            return false;',
            '        }',
        ])
    
        ret.append('        $sql .= \') VALUES (\'.$values.\')\' . $dup;')
    
        ret.extend([
            '        $stmt = $db->prepare($sql);',
            '        $stmt->execute($data);',
            '        $ret = $this->createReturn($stmt, function ($s) {',
            '            return true;',
            '        });',
        ])
        
        ret.extend([
            '    }', ''
        ])
    

    return ret


def generate_update(analysis_obj):
    assert isinstance(analysis_obj, sqlmigration.codegen.ColumnSetAnalysis)

    # TODO replace with UpdateQuery

    column_name_values = {}
    required_argument_names = []
    optional_argument_names = []
    optional_col_args = []
    where_ands = []
    always_column_names = []

    # TODO if there are any "always_column_names", then the "$set_count" logic
    # should be skipped.

    # TODO if there is just one optional_argument_names, then it should be
    # required.

    for column in analysis_obj.primary_key_columns:
        assert isinstance(column, sqlmigration.codegen.ColumnAnalysis)
        assert column.update_value is None
        required_argument_names.append(column.sql_name)
        # FIXME use the converter
        where_ands.append(column.sql_name + ' = :' + column.sql_name)

    for column in analysis_obj.columns_for_update:
        assert isinstance(column, sqlmigration.codegen.ColumnAnalysis)
        if not column in analysis_obj.primary_key_columns:
            uv = column.update_value
            if uv is not None:
                uvc = uv.constraint
                # TODO in the future, this might be code
                assert isinstance(uvc, sqlmigration.model.SqlConstraint)
                column_name_values[column.sql_name] = uvc.sql_args(
                    PLATFORMS, PREPSQL_CONVERTER)
                assert column_name_values[column.sql_name] is not None
                if len(column.update_arguments) > 0:
                    optional_col_args.append(
                        [column.sql_name, [a.name for a in column.update_arguments]])
                    optional_argument_names.extend(a.name for a in column.update_arguments)
                else:
                    always_column_names.append(column.sql_name)

            else:
                column_name_values[column.sql_name] = PREPSQL_CONVERTER(column.name_as_sql_argument)
                optional_col_args.append([column.sql_name, [column.sql_name]])
                optional_argument_names.append(column.sql_name)

    if len(required_argument_names) <= 0:
        raise Exception("cannot update table because there is no primary key")

    if len(optional_argument_names) <= 0:
        # Nothing to do
        return []

    initial_update = 'UPDATE ' + analysis_obj.sql_name + ' SET '
    first = True
    for n in always_column_names:
        if first:
            first = False
        else:
            initial_update += ', '
        initial_update += n + ' = ' + column_name_values[n]

    ret = [
        '',
        '    public function update($db, ' +
        (', '.join(('$' + n) for n in required_argument_names)) + ', ' +
        (', '.join(('$' + n + ' = false') for n in optional_argument_names)) +
        ') {',
        '        $sql = "' + initial_update + '";',
        '        $set_count = ' + str(len(always_column_names)) + ';',
        '        $data = array(',
    ]
    for n in required_argument_names:
        ret.extend([
            '            "' + n + '" => $' + n + ',',
        ])
    ret.extend([
        '        );',
    ])
    for n in optional_col_args:
        ret.extend(['        if (' + (
            ' && '.join(('$' + c + ' !== false')
                        for c in n[1])) + ') {',
            '            if ($set_count > 0) {',
            '                $sql .= ", ";',
            '            }',
            '            $set_count++;',
            '            $sql .= "' + n[0] + ' = ' +
            column_name_values[n[0]] + '";',
        ])
        for anx in n[1]:
            ret.append('            $data["' + anx + '"] = $' + anx + ';')

        ret.append('        }')

    if len(where_ands) > 0:
        ret.append('        $sql .= " WHERE ' + (' AND '.join(where_ands)) +
                   '";')

    # FIXME check if updated row count was 0, and if so report an error.
    ret.extend([
        '        $stmt = $db->prepare($sql);',
        '        $stmt->execute($data);',
        '        return $this->createReturn($stmt, function ($s) {',
        '            return true;',
        '        });',
        '    }', ''
    ])

    return ret


def generate_delete(analysis_obj):
    assert isinstance(analysis_obj, sqlmigration.codegen.ColumnSetAnalysis)

    args = [('$' + c.sql_name) for c in analysis_obj.primary_key_columns]
    if len(args) <= 0:
        print("** WARNING: no way to delete " + analysis_obj.sql_name)
        return []

    sql = ('DELETE FROM ' + analysis_obj.sql_name + ' WHERE ' +
           ' AND '.join([(c.sql_name + ' = :' + c.sql_name)
           for c in analysis_obj.primary_key_columns]))

    ret = [
        '',
        '    public function remove($db, ' + ', '.join(args) + ') {',
        '        $stmt = $db->prepare(\'' + sql.replace("'", "\\'") + '\');',
        '        $data = array(',
    ]

    for c in analysis_obj.primary_key_columns:
        ret.append('            "' + c.sql_name + '" => $' + c.sql_name + ',')

    ret.extend([
        '        );',
        '        $stmt->execute($data);',
        '        return $this->createReturn($stmt, function ($s) {',
        '            return true;',
        '        });',
        '    }'
        '',
    ])

    return ret


def generate_extended_sql(analysis_obj):
    assert isinstance(analysis_obj, sqlmigration.codegen.ColumnSetAnalysis)
    
    extended_sql_set = analysis_obj.schema.extended_sql
    
    ret = []
    
    for extended_sql in extended_sql_set:
        assert isinstance(extended_sql, sqlmigration.model.ExtendedSql)
        php_name = generate_php_name(extended_sql.name)
        arg_prefix = ''
        if len(extended_sql.arguments) > 0:
            arg_prefix = ', '
        ret.extend([
            '',
            '    public function run' + php_name + '($db' + arg_prefix +
            (', '.join(('$' + a.name) for a in extended_sql.arguments)) +
            ', $start = -1, $end = -1) {',
            '        $sql = \'' + sqlmigration.codegen.php.escape_php_string(
            extended_sql.sql_args(PLATFORMS, PREPSQL_CONVERTER)) + '\';',
            '        if ($start >= 0 && $end > 0) {',
            '            $sql .= \' LIMIT \'.$start.\',\'.$end;',
            '        }',
            '        $data = array(',
        ])
        
        for a in extended_sql.arguments:
            assert isinstance(a, sqlmigration.model.SqlArgument)
            ret.append('            "' + a.name + '" => $' + a.name + ',')
        
        ret.extend([
            '        );',
            '        $stmt = $db->prepare($sql);',
            '        $stmt->setFetchMode(PDO::FETCH_ASSOC);',
            '        $stmt->execute($data);',
            # No validation can be performed with the results, because we don't know
            # what's in the results.
            '        return $this->createReturn($stmt, function ($s) {',
        ])
        if extended_sql.sql_type == 'query':
            ret.append('            return $s->fetchAll();')
        elif extended_sql.sql_type in ['id', 'count']:
            ret.append('            return intval($s->fetchColumn);')
        else:
            ret.append('            return true;')
        ret.extend([
            '        });',
            '    }', '', '',
        ])
    
    return ret


def generate_validations(analysis_obj):
    assert isinstance(analysis_obj, sqlmigration.codegen.ColumnSetAnalysis)

    table_validates = [
        '',
        '    private function validateTable(&$row) {',
        '        $ret = True;',
    ]
    read_validates = [
        '',
        '    private function validateRead(&$row) {',
        '        $ret = True;',
    ]
    write_validates = [
        '',
        '    private function validateWrite(&$row) {',
        '        $ret = $this->validateTable($row);',
    ]

    ret = []

    # Always run the validation, by having it appear before the '&& $ret',
    # so that the parent class can collect all of the validation errors.



    # FIXME --------------------------------------------------------
    # FIXME --------------------------------------------------------
    # FIXME --------------------------------------------------------
    # FIXME --------------------------------------------------------
    # FIXME --------------------------------------------------------
    # FIXME these comments and what they call need to be redone

    #for v in processed_columns.php_read:
    #    assert isinstance(v, ProcessedPhpValidationConstraint)
    #    read_validates.append('        $ret = $this->validate' + v.order_name +
    #                          '($row) && $ret;')
    #    ret.extend(generate_validation(v))
    #for v in processed_columns.php_write:
    #    assert isinstance(v, ProcessedPhpValidationConstraint)
    #    write_validates.append('        $ret = $this->validate' + v.order_name +
    #                           '($row) && $ret;')
    #    ret.extend(generate_validation(v))
    #for v in processed_columns.php_validation:
    #    assert isinstance(v, ProcessedPhpValidationConstraint)
    #    write_validates.append('        $ret = $this->validate' + v.order_name +
    #                           '($row) && $ret;')
    #    ret.extend(generate_validation(v))
    #
    #for validation in table_validations:
    #    assert isinstance(validation, ProcessedPhpValidationConstraint)
    #    table_validates.append('        $ret = $this->validate' +
    #                           validation.order_name + '($row) && $ret;')
    #    ret.extend(generate_validation(validation))

    table_validates.extend(['        return $this->finalCheck($ret);', '    }',
                            ''])
    read_validates.extend(['        return $this->finalCheck($ret);', '    }',
                           ''])
    write_validates.extend(['        return $this->finalCheck($ret);', '    }',
                            ''])
    ret.extend(read_validates)
    ret.extend(write_validates)
    ret.extend(table_validates)
    
    ret.extend(['    private function finalCheck($ret) {',
                # FIXME
                '        return $ret;',
                '    }',
                ''])
    
    return ret


def generate_validation(validation):
    assert isinstance(validation, sqlmigration.codegen.LanguageConstraint)
    assert validation.language == 'php'

    ret = [
        '',
        '    private function validate' + validation.order_name + '(&$row) {',
    ]

    if validation.php_name is not None:
        ret.append('        $' + validation.php_name + ' = $row["' +
                   validation.php_name + '"];')
    ret.extend([
        '        return $this->ensure(' + ('            '.join(
        line for line in validation.script.splitlines())) + ', "' +
        validation.column_name + '");', '    }', ''
    ])
    return ret


def generate_php_name(schema_name):
    first = True
    ret = ''
    for c in schema_name:
        if c == '_':
            first = True
        else:
            if first:
                c = c.upper()
                first = False
            else:
                c = c.lower()
            ret += c
    assert len(ret) > 0
    return ret


def generate_where_clause(analysis_obj, already_added_where):
    ret = []
    if len(analysis_obj.schema.where_clauses) > 0:
        ret.append(
            '        if ($whereClauses !== null && sizeof($whereClauses) > 0) {')
        if already_added_where:
            ret.extend([
            '            foreach ($whereClauses as $w) {',
            '                $w->bindVariables($data);',
            '                $sql .= " AND " . $w;',
            '            }',
            ])
        else:
            ret.extend([
                '            $hasWhere = false;',
                '            foreach ($whereClauses as $w) {',
                '                if ($hasWhere) {',
                '                    $sql .= " AND ";',
                '                } else {',
                '                    $sql .= " WHERE ";',
                '                    $hasWhere = true;',
                '                }',
                '                $w->bindVariables($data);',
                '                $sql .= $w;',
                '            }',
            ])
        ret.append('        }')
    return ret


if __name__ == '__main__':
    (parent_class, namespace, in_dir, output_dir) = sys.argv[1:]
    versions = sqlmigration.parse_versions(in_dir)
    if len(versions) <= 0:
        raise Exception("no versions found")
    head = versions[0]
    analysis_model = sqlmigration.codegen.AnalysisModel()
    analysis_model.add_version(in_dir, head)

    for schema in head.schema:
        generate_file(analysis_model.get_analysis_for(schema))
