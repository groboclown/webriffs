#/usr/bin/python3

import shutil, subprocess, distutils.dir_util

# -------------------------------------------------------------------------
# Build library
import sys, os

req_version = (3, 1)
cur_version = sys.version_info
assert cur_version >= req_version, "You must run this with Python 3"

TARGETS = {}
def depends(*dep_funcs):
    def target_decorator(f):
        TARGETS[f.__name__] = f
        def target_func(config):
            if f in config['!.ran']:
                return
            config['!.ran'].append(f)
            for d in dep_funcs:
                d(config)
            print("---------------------------------")
            print("Running " + str(f.__name__))
            f(config)
        return target_func
    return target_decorator
def build(default_target, function_namespace):
    filename = os.path.abspath(sys.argv[0])
    targets = sys.argv[1:]
    if len(targets) <= 0:
        targets = [ default_target ]
    config = { 'basedir': os.path.dirname(filename),
        'build.file': filename, '!.ran': [] }
    for t in targets:
        if not callable(t):
            t = function_namespace[t]
        t(config)
#--------------------------------------------------------------------------


#--------------------------------------------------------------------------
# Utilities

def todir(*path):
    p = os.path.join(*path)
    if not os.path.exists(p):
        os.makedirs(p)
    return p


def clean_dir(*path):
    p = os.path.join(*path)
    if os.path.exists(p):
        print("Deleting " + str(p))
        shutil.rmtree(p)
    os.makedirs(p)
    return p


def run_command(cmd, dir, env):
    print("Executing [" + "] [".join(cmd) + "]")
    ex = subprocess.Popen(cmd, cwd = dir, env = env, shell=True)
    ex.wait()
    return ex.returncode


def run_sqlmigrate(config, script, *args):
    c = [sys.executable, os.path.join(config['sql-migration.src.dir'], script)]
    c.extend(args)
    path = []
    env = os.environ
    env['PYTHONPATH'] = config['sql-migration.path']
    ret = run_command(c, config['basedir'], env)
    if ret != 0:
        raise Exception(" ".join(c) + " returned " + str(ret))
#--------------------------------------------------------------------------


@depends()
def init(config):
    config['work.dir'] = os.path.join(config['basedir'], 'work')
    config['exports.dir'] = os.path.join(config['basedir'], 'exports')
    config['root.dir'] = os.path.join(config['basedir'], '..')
    config['php.dir'] = os.path.join(config['root.dir'], 'php')
    config['client.dir'] = os.path.join(config['root.dir'], 'client')
    config['php.dir'] = os.path.join(config['root.dir'], 'php')
    config['sql.dir'] = os.path.join(config['root.dir'], 'sql')
    config['sql-categories.dirs'] = (
         os.path.join(config['sql.dir'], 'GroboAuth'),
         os.path.join(config['sql.dir'], 'GroboVersion'),
         os.path.join(config['sql.dir'], 'WebRiffs')
    )
    config['sql-migration.src.dir'] = os.path.join(config['root.dir'],
           'sql-migration', 'src')
    smp = [config['sql-migration.src.dir']]
    smp.extend(sys.path)
    config['sql-migration.path'] = os.pathsep.join(smp)


@depends(init)
def clean(config):
    if os.path.exists(config['work.dir']):
        print("Deleting " + config['work.dir'])
        shutil.rmtree(config['work.dir'])
    if os.path.exists(config['exports.dir']):
        print("Deleting " + config['exports.dir'])
        shutil.rmtree(config['exports.dir'])


@depends(init)
def setup(config):
    todir(os.path.exists(config['work.dir']))
    todir(os.path.exists(config['exports.dir']))


@depends(setup)
def generate_sql(config):
    i = 0
    for d in config['sql-categories.dirs']:
        print("Generating sql for " + str(d))
        outdir = clean_dir(config['exports.dir'], 'sql', "{0:02d}".format(i))
        run_sqlmigrate(config, 'genBaseSql.py', 'mysql', d, outdir)
        i += 1


@depends(setup)
def generate_dbo(config):
    for d in config['sql-categories.dirs']:
        dn = os.path.basename(d)
        outdir = clean_dir(config['exports.dir'], 'php', 'dbo', dn)
        run_sqlmigrate(config, 'genPhpDboLayer.py', 'Base\\DboParent',
            dn, d, outdir)


@depends(setup)
def lint_client(config):
    cmd_file = os.path.join(os.environ['DART_HOME'], 'dart-sdk', 'bin',
            'dartanalyzer')
    # Analyze each main file
    for f in [ 'web/main.dart' ]:
        cmd = [ cmd_file, f ]
        ret = run_command(cmd, config['client.dir'], os.environ)
        if ret != 0:
            raise Exception("Failed to run 'pub build' correctly")


@depends(setup)
def generate_client_js(config):
    cmd = [os.path.join(os.environ['DART_HOME'], 'dart-sdk', 'bin', 'pub'),
           'build']
    ret = run_command(cmd, config['client.dir'], os.environ)
    if ret != 0:
        raise Exception("Failed to run 'pub build' correctly")


@depends(setup)
def copy_client(config):
    distutils.dir_util.copy_tree(
         todir(config['client.dir'], 'build'),
         todir(config['exports.dir']),
         preserve_symlinks = False,
         update = True,
         verbose = True,
         dry_run = False)


@depends(setup)
def copy_php(config):
    for i in ['conf', 'lib', 'src', 'web']:
        distutils.dir_util.copy_tree(
             todir(config['php.dir'], i),
             todir(config['exports.dir'], i),
             preserve_symlinks = False,
             update = True,
             verbose = True,
             dry_run = False)


@depends(clean, generate_sql, generate_dbo,
         lint_client, generate_client_js, copy_client, copy_php)
def all(config):
    pass




#--------------------------------------------------------------------------
if __name__ == '__main__':
    run = []
    for n in sys.argv[1:]:
        if n in TARGETS:
            run.append(TARGETS[n])
        if n == '-h':
            print("Targets: " + str(TARGETS.keys()))
            sys.exit(0)
    if len(run) <= 0:
        print("Running default target 'all'")
        run.append(all)
    if len(run) > 1:
        print("For some reason, running 2 or more targets at once causes " +
            "issues. Try running them one at a time until this is fixed.")
        sys.exit(1)
    for t in run:
        build(t, globals())
