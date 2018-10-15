<?php
/**
 * moon.php - CLI script for interacting with burningsoul moon api
 * relies on command-line tools, tested on MacOS.
 *
 * @author Vijay Mahrra <vijay@yoyo.org>
 * @copyright (c) Copyright 2018 Vijay Mahrra
 * @license GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 * @see https://burningsoul.in/apis/moon
 */
ini_set('default_charset', 'utf-8');
ini_set('mbstring.encoding_translation', 'On');
ini_set('mbstring.func_overload', 6);
ini_set('auto_detect_line_endings',TRUE);

// detect if run in web mode or cli
switch (php_sapi_name()) {
    case 'cli':
        break;
    default:
    case 'cli-server': // run as web-service
        define('DEBUG', 0);
        $save_data = 0;
        $params = [
            'date', 'refresh'
        ];

        // filter input variables
        $_REQUEST = array_change_key_case($_REQUEST);
        $keys = array_intersect($params, array_keys($_REQUEST));
        $params = [];
        foreach ($_REQUEST as $k => $v) {
            if (!in_array($k, $keys)) {
                unset($_REQUEST[$k]);
                continue;
            }
            $v = trim(strip_tags(filter_var(urldecode($v), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW)));
            if (!empty($v)) {
                $_REQUEST[$k] = $v;
                // params to command line
                $params['--' . $k] = escapeshellarg($v);
            } else {
                $params['--' . $k] = '';
            }
        }

        // build command line
        $php = cmd_execute('which php');
        $cmd = $php[0] . ' ' . $_SERVER['SCRIPT_FILENAME'] . ' --echo ';
        foreach ($params as $k => $v) {
            $cmd .= (empty($v)) ? " $k" : " $k=$v";
        }

        // exexute command line and quit
        $data = shell_execute($cmd);
        header('Content-Type: application/json');
        echo $data['stdout'];
        exit;
}

//-----------------------------------------------------------------------------
// required commands check
$requirements = [
    'curl' => 'tool: curl - https://curl.haxx.se',
];

$commands = get_commands($requirements);

if (empty($commands)) {
    verbose("Error: Missing commands.", $commands);
    exit;
}

// load moon.ini file
$config = parse_ini_file(dirname(__FILE__) . '/moon.ini', true);

date_default_timezone_set($config['settings']['timezone']);

//-----------------------------------------------------------------------------
// define command-line options
// see https://secure.php.net/manual/en/function.getopt.php
// : - required, :: - optional

$options = getopt("hvdtk:f:oer",
    [
    'help', 'verbose', 'debug', 'test', 'offline', 'echo',
    'dir:', 'filename:',
    'date:',
    'refresh',
    ]);

$do = [];
foreach ([
'verbose' => ['v', 'verbose'],
 'test'    => ['t', 'test'],
 'debug'   => ['d', 'debug'],
 'test'    => ['t', 'test'],
 'offline' => ['o', 'offline'],
 'echo'    => ['e', 'echo'],
 'refresh' => ['r', 'refresh'],
] as $i => $opts) {
    $do[$i] = (int) (array_key_exists($opts[0], $options) || array_key_exists($opts[1],
            $options));
}

if (array_key_exists('debug', $do) && !empty($do['debug'])) {
    $do['verbose']      = $options['verbose'] = 1;
}

ksort($do);

//-----------------------------------------------------------------------------
// defines (int) - forces 0 or 1 value

define('DEBUG', (int) $do['debug']);
define('VERBOSE', (int) $do['verbose']);
define('TEST', (int) $do['test']);
define('OFFLINE', (int) $do['offline']);
define('BS_API_URL_POINT', $config['api']['url']);

debug('CONFIG', $config);
debug("COMMANDS:", $commands);
debug('OPTIONS:', $do);

//-----------------------------------------------------------------------------
// help
if (empty($options) || array_key_exists('h', $options) || array_key_exists('help',
        $options)) {
    options:

    $readme_file = dirname(__FILE__) . '/README.md';
    if (file_exists($readme_file)) {
        $readme = file_get_contents('README.md');
        if (!empty($readme)) {
            output($readme . "\n");
        }
    }

    print "Requirements:\n";
    foreach ($requirements as $cmd => $desc) {
        printf("%s:\n\t%s\n", $cmd, $desc);
    }

    print join("\n",
            [
        "Usage: php moon.php",
        "Call to the burning soul moon API - https://burningsoul.in/apis/moon",
        "(Specifying any other unknown argument options will be ignored.)\n",
        "\t-h,  --help                   Display this help and exit",
        "\t-v,  --verbose                Run in verbose mode",
        "\t-d,  --debug                  Run in debug mode (implies also -v, --verbose)",
        "\t-t,  --test                   Run in test mode.",
        "\t-o,  --offline                Do not go-online when performing tasks (only use local files for url resolution for example)",
        "\t-e,  --echo                   (Optional) Echo/output the result to stdout if successful",
        "\t-r,  --refresh                (Optional) Force cache-refresh",
        "\t     --date={now}             (Optional) Date/time default 'now' see: https://secure.php.net/manual/en/function.strtotime.php",
        "\t     --dir=                   (Optional) Directory for storing files (sys_get_temp_dir() if not specified)",
        "\t-f,  --filename={output.}     (Optional) Filename for output data from operation",
        "\t     --format={json}          (Optional) Output format for output filename (reserved for future): json (default)",
    ]);

    // goto jump here if there's a problem
    errors:
    if (!empty($errors)) {
        if (is_array($errors)) {
             echo json_encode(['errors' => $errors], JSON_PRETTY_PRINT);
        }
    } else {
        output("\nNo errors occurred.\n");
    }

    goto end;
    exit;
}

//-----------------------------------------------------------------------------
// initialise variables

$errors = []; // errors to be output if a problem occurred
$output = []; // data to be output at the end
$save_data = true; // save the array $data in 'output:' section at the end
$format = '';
if (!empty($options['format'])) {
    $format = $options['format'];
}
switch ($format) {
    default:
    case 'json':
        $format = 'json';
}
define('OUTPUT_FORMAT', $format);
verbose("OUTPUT_FORMAT: $format");

//-----------------------------------------------------------------------------
// get dir and file for output

$dir = sys_get_temp_dir();
if (!empty($options['dir'])) {
    $dir = $options['dir'];
}
$dircheck = realpath($dir);
if (empty($dircheck) || !is_dir($dircheck)) {
    $errors[] = "You must specify a valid directory!";
    goto errors;
}

$output_filename = !empty($options['filename']) ? $options['filename'] : '';

//-----------------------------------------------------------------------------
// get date from/to from command-line

$date = 0;
if (!empty($options['date'])) {
    $date = $options['date'];
}
if (!empty($date)) {
    $date = strtotime($date);
    if (false === $date) {
        $errors[] = sprintf("Unable to parse --date: %s",
            $options['date']);
    }
    verbose(sprintf("Fetching results FROM date/time '%s': %s",
            $options['date'], gmdate('r', $date)));
}

//-----------------------------------------------------------------------------
// MAIN
// set up request params for sg_point_request($request_params)

// load from cache
$cache_key  = 'moon-' . $date;
$cache_dir  = $dir;
$cache_file = $cache_dir . '/' . $cache_key . '.json';

// load from cache, expire if out-of-date in order to refresh after
if (!$do['refresh'] && file_exists($cache_file)) {
    $expired = time() > ($config['settings']['cache']['seconds'] + filemtime($cache_file));
    if ($expired) {
        unlink($cache_file);
    } else {
        $data = json_load($cache_file);
    }
}

// not in cache!
if (!empty($data)) {
    debug("Cached data loaded from: $cache_file");
} else {
    debug("Cached file data not found for: $cache_file");
    $data = bs_moon_request($date);
    if (empty($data) || !is_array($data)) {
        $errors[] = $data;
        goto errors;
    }

    // cache the result
    $save = json_save($cache_file, $data);
    if (true !== $save) {
        $errors[] = "\nFailed encoding JSON cached results output file:\n\t$cache_file\n";
        $errors[] = "\nJSON Error: $save\n";
        goto errors;
    } else {
        verbose(sprintf("JSON written to cached results output file:\n\t%s (%d bytes)\n",
                $cache_file, filesize($cache_file)));
    }
}

//-----------------------------------------------------------------------------
// final output of data

output:

// display any errors
if (!empty($errors)) {
    goto errors;
}

// set data to write to file
if (is_array($data) && !empty($data)) {
    $output = $data;
}

// only write/display output if we have some!
if (!empty($output)) {

    if ($save_data && !empty($output_filename)) {
        $file = $output_filename;
        switch (OUTPUT_FORMAT) {
            default:
            case 'json':
                $save = json_save($file, $output);
                if (true !== $save) {
                    $errors[] = "\nFailed encoding JSON output file:\n\t$file\n";
                    $errors[] = "\nJSON Error: $save\n";
                    goto errors;
                } else {
                    verbose(sprintf("JSON written to output file:\n\t%s (%d bytes)\n",
                            $file, filesize($file)));
                }
                break;
        }

    }

    // output data if --echo
    if ($do['echo']) {
        echo json_encode($output, JSON_PRETTY_PRINT);
    }
}

end:

debug(sprintf("Memory used (%s) MB (current/peak).", get_memory_used()));
output("\n");
exit;

//-----------------------------------------------------------------------------
// functions used above

/**
 * Output string, to STDERR if available
 *
 * @param  string { string to output
 * @param  boolean $STDERR write to stderr if it is available
 */
function output($text, $STDERR = true)
{
    if (!empty($STDERR) && defined('STDERR')) {
        fwrite(STDERR, $text);
    } else {
        echo $text;
    }
}


/**
 * Dump debug data if DEBUG constant is set
 *
 * @param  optional string $string string to output
 * @param  optional mixed $data to dump
 * @return boolean true if string output, false if not
 */
function debug($string = '', $data = [])
{
    if (DEBUG) {
        output(trim('[D ' . get_memory_used() . '] ' . $string) . "\n");
        if (!empty($data)) {
            output(print_r($data, 1));
        }
        return true;
    }
    return false;
}


/**
 * Output string if VERBOSE constant is set
 *
 * @param  string $string string to output
 * @param  optional mixed $data to dump
 * @return boolean true if string output, false if not
 */
function verbose($string, $data = [])
{
    if (VERBOSE && !empty($string)) {
        output(trim('[V' . ((DEBUG) ? ' ' . get_memory_used() : '') . '] ' . $string) . "\n");
        if (!empty($data)) {
            output(print_r($data, 1));
        }
        return true;
    }
    return false;
}


/**
 * Return the memory used by the script, (current/peak)
 *
 * @return string memory used
 */
function get_memory_used()
{
    return(
        ceil(memory_get_usage() / 1024 / 1024) . '/' .
        ceil(memory_get_peak_usage() / 1024 / 1024));
}


/**
 * check required commands installed and get path
 *
 * @param  array $requirements [][command -> description]
 * @return mixed array [command -> path] or string errors
 */
function get_commands($requirements = [])
{
    static $commands = []; // cli command paths

    $found = true;
    foreach ($requirements as $tool => $description) {
        if (!array_key_exists($tool, $commands)) {
            $found = false;
            break;
        }
    }
    if ($found) {
        return $commands;
    }

    $errors = [];
    foreach ($requirements as $tool => $description) {
        $cmd = cmd_execute("which $tool");
        if (empty($cmd)) {
            $errors[] = "Error: Missing requirement: $tool - " . $description;
        } else {
            $commands[$tool] = $cmd[0];
        }
    }

    if (!empty($errors)) {
        output(join("\n", $errors) . "\n");
    }

    return $commands;
}


/**
 * Execute a command and return streams as an array of
 * stdin, stdout, stderr
 *
 * @param  string $cmd command to execute
 * @return array|false array $streams | boolean false if failure
 * @see    https://secure.php.net/manual/en/function.proc-open.php
 */
function shell_execute($cmd)
{
    $process = proc_open(
        $cmd,
        [
        ['pipe', 'r'],
        ['pipe', 'w'],
        ['pipe', 'w']
        ], $pipes
    );
    if (is_resource($process)) {
        $streams = [];
        foreach ($pipes as $p => $v) {
            $streams[] = stream_get_contents($pipes[$p]);
        }
        proc_close($process);
        return [
            'stdin'  => $streams[0],
            'stdout' => $streams[1],
            'stderr' => $streams[2]
        ];
    }
    return false;
}


/**
 * Execute a command and return output of stdout or throw exception of stderr
 *
 * @param  string $cmd command to execute
 * @param  boolean $split split returned results? default on newline
 * @param  string $exp regular expression to preg_split to split on
 * @return mixed string $stdout | Exception if failure
 * @see    shell_execute($cmd)
 */
function cmd_execute($cmd, $split = true, $exp = "/\n/")
{
    $result = shell_execute($cmd);
    if (!empty($result['stderr'])) {
        throw new Exception($result['stderr']);
    }
    $data = $result['stdout'];
    if (empty($split) || empty($exp) || empty($data)) {
        return $data;
    }
    return preg_split($exp, $data);
}


/**
 * Encode array character encoding recursively
 *
 * @param mixed $data
 * @param string $to_charset convert to encoding
 * @param string $from_charset convert from encoding
 * @return mixed
 */
function to_charset($data, $to_charset = 'UTF-8', $from_charset = 'auto')
{
    if (is_numeric($data)) {
        $float = (string)(float) $data;
        if (is_int($data)) {
            return (int) $data;
        } else if (is_float($data) || $data === $float) {
            return (float) $data;
        } else {
            return (int) $data;
        }
    } else if (is_string($data)) {
        return mb_convert_encoding($data, $to_charset, $from_charset);
    } else if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = to_charset($value, $to_charset, $from_charset);
        }
    } else if (is_object($data)) {
        foreach ($data as $key => $value) {
            $data->$key = to_charset($value, $to_charset, $from_charset);
        }
    }
    return $data;
}


/**
 * Load a json file and return a php array of the content
 *
 * @param  string $file the json filename
 * @return string|array error string or data array
 */
function json_load($file)
{
    $data = [];
    if (file_exists($file)) {
        $data = to_charset(file_get_contents($file));
        $data = json_decode(
            mb_convert_encoding($data, 'UTF-8', "auto"), true, 512,
            JSON_OBJECT_AS_ARRAY || JSON_BIGINT_AS_STRING
        );
    }
    if (null === $data) {
        return json_last_error_msg();
    }
    if (is_array($data)) {
        $data = to_charset($data);
    }
    return $data;
}


/**
 * Save data array to a json
 *
 * @param  string $file the json filename
 * @param  array $data data to save
 * @param  string optional $prepend string to prepend in the file
 * @param  string optional $append string to append to the file
 * @return boolean true|string TRUE if success or string error message
 */
function json_save($file, $data, $prepend = '', $append = '')
{
    if (empty($data)) {
        return 'No data to write to file.';
    }
    if (is_array($data)) {
        $data = to_charset($data);
    }
    if (!file_put_contents($file,
            $prepend . json_encode($data, JSON_PRETTY_PRINT) . $append)) {
        $error = json_last_error_msg();
        if (empty($error)) {
            $error = sprintf("Unknown Error writing file: '%s' (Prepend: '%s', Append: '%s')",
                $file, $prepend, $append);
        }
        return $error;
    }
    return true;
}


/**
 * Send a request to the burning soul moon API and return the result as a PHP array
 *
 * @param optional int $timestamp default null
 * @param array $options to merge in for curl (timeout (int), max_time (int), user_agent (string))
 * @return boolean|string|array of results. false or string if error
 * @see https://burningsoul.in/apis/moon
 */
function bs_moon_request($timestamp = null, $options = [])
{
    $timestamp = (int) $timestamp;
    if ($timestamp > 0) {
        $url = BS_API_URL_POINT . $timestamp;
    } else {
        $url = BS_API_URL_POINT;
    }

    $commands = get_commands();
    $curl     = $commands['curl'];

    $timeout    = !empty($options['timeout']) ? (int) $options['timeout'] : 3;
    $max_time   = !empty($options['max_time']) ? (int) $options['max_time'] : $timeout
        * 10;
    $user_agent = !empty($options['user_agent']) ? $options['user_agent'] : '';

    $curl_options      = "--connect-timeout $timeout --max-time $max_time --ciphers ALL -k";
    $curl_url_resolve  = "curl $curl_options -L -s " . escapeshellarg($url);

    if (OFFLINE) {
        debug(sprintf("OFFLINE MODE! Can't request:\n\t%s\n\t", $curl_url_resolve));
        return false;
    }

    // execute request
    $data = cmd_execute($curl_url_resolve, false);

    // decode json data to php
    if (!empty($data)) {
        $return = to_charset($data);
        $return = json_decode(
            mb_convert_encoding($return, 'UTF-8', "auto"), true, 512,
            JSON_OBJECT_AS_ARRAY || JSON_BIGINT_AS_STRING
        );
    }

    if (empty($data) || empty($return)) {
        $return = sprintf("JSON decode failed: %s\nData:\n\t",
                json_last_error_msg()) . print_r($data, 1);
    }

    ksort($return);
    return $return;
}

