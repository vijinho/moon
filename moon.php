<?php

/**
 * moon.php - CLI/WWW script for getting lunar phase
 * relies on command-line tools, tested on MacOS.
 *
 * @author Vijay Mahrra <vijay@yoyo.org>
 * @copyright (c) Copyright 2018 Vijay Mahrra
 * @license GPLv3 (http://www.gnu.org/licenses/gpl-3.0.html)
 * @url https://github.com/vijinho/moon
 * @see https://github.com/solarissmoke/php-moon-phase
 */
date_default_timezone_set('UTC');
ini_set('default_charset', 'utf-8');
ini_set('mbstring.encoding_translation', 'On');
ini_set('mbstring.func_overload', 6);
ini_set('auto_detect_line_endings', TRUE);

//-----------------------------------------------------------------------------
// get moon class

require_once dirname(__FILE__) . '/vendor/autoload.php';


class MyMoonPhase extends Solaris\MoonPhase
{

    private $ts;

    /**
     * Set timestamp - temporary fix for 'protected' parent property of timestamp
     *
     * @param int $timestamp
     */
    public function setTimeStamp($timestamp)
    {
        $this->ts = (int) $timestamp;
    }


    /**
     * return the values as a array
     * method allows outputting values as an array
     *
     * @param string date_format format to date()
     * @param boolean $round round the result?
     * @return array moon phase information
     * @link http://php.net/manual/en/language.oop5.magic.php#object.tostring
     */
    public function toArray($round = false, $date_format = 'U'): array
    {
        return [
            'timestamp'          => $this->ts,
            'datestamp'          => date('r', $this->ts),
            'phase'              => $round ? round($this->getPhase(), 3) : $this->getPhase(),
            'illumination'       => $round ? round($this->getIllumination(), 3) : $this->getIllumination(),
            'age_days'           => $round ? round($this->getAge(), 3) : $this->getAge(),
            'distance_km'        => $round ? ceil($this->getDistance()) : $this->getDistance(),
            'diameter'           => $round ? round($this->getDiameter(), 3) : $this->getDiameter(),
            'sun_distance_km'    => $round ? ceil($this->getSunDiameter()) : $this->getSunDiameter(),
            'sun_diameter'       => $round ? round($this->getSunDistance(), 3) : $this->getSunDistance(),
            'new_moon_last'      => date($date_format, $round ? round($this->getPhaseNewMoon()) : $this->getPhaseNewMoon()),
            'new_moon_next'      => date($date_format, $round ? round($this->getPhaseNextNewMoon()) : $this->getPhaseNextNewMoon()),
            'full_moon'          => date($date_format, $round ? round($this->getPhaseFullMoon()) : $this->getPhaseFullMoon()),
            'full_moon_next'     => date($date_format, $round ? round($this->getPhaseNextFullMoon()) : $this->getPhaseNextFullMoon()),
            'first_quarter'      => date($date_format, $round ? round($this->getPhaseFirstQuarter()) : $this->getPhaseFirstQuarter()),
            'first_quarter_next' => date($date_format, $round ? round($this->getPhaseNextFirstQuarter()) : $this->getPhaseNextFirstQuarter()),
            'last_quarter'       => date($date_format, $round ? round($this->getPhaseLastQuarter()) : $this->getPhaseLastQuarter()),
            'last_quarter_next'  => date($date_format, $round ? round($this->getPhaseNextLastQuarter()) : $this->getPhaseNextLastQuarter()),
            'phase_name'         => $round ? round($this->getPhaseName()) : $this->getPhaseName(),
            'stage'              => $this->getPhase() < 0.5 ? 'waxing' : 'waning'
        ];
    }


    /**
     * returned values when called with var_dump()
     *
     * @return array|null debug info
     * @link http://php.net/manual/en/language.oop5.magic.php#object.debuginfo
     */
    public function __debugInfo(): array
    {
        return $this->toArray(false);
    }


    /**
     * return the values as a string
     * method allows outputting values as a string
     *
     * @param string date_format format to date()
     * @param boolean $round round the result?
     * @return string json_encode()
     * @link http://php.net/manual/en/language.oop5.magic.php#object.tostring
     */
    public function toJSON($round = false, $date_format = 'U'): string
    {
        return json_encode(to_charset($this->toArray($round, $date_format)), JSON_PRETTY_PRINT);
    }


    /**
     * return the values as a string
     * method allows outputting values as a string
     *
     * @return string json_encode()
     * @link http://php.net/manual/en/language.oop5.magic.php#object.tostring
     */
    public function __toString(): string
    {
        return $this->toJSON();
    }


}

//-----------------------------------------------------------------------------
// detect if run in web mode or cli

switch (php_sapi_name()) {
    case 'cli':
        break;
    default:
    case 'cli-server': // run as web-service
        define('DEBUG', 0);
        $save_data = 0;
        $params    = [
            'date', 'refresh', 'round', 'date-format'
        ];

        // filter input variables
        $_REQUEST = array_change_key_case($_REQUEST);
        $keys     = array_intersect($params, array_keys($_REQUEST));
        $params   = [];
        foreach ($_REQUEST as $k => $v) {
            if (!in_array($k, $keys)) {
                unset($_REQUEST[$k]);
                continue;
            }
            $v = trim(strip_tags(filter_var(urldecode($v),
                        FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW)));
            if (!empty($v)) {
                $_REQUEST[$k]      = $v;
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
// define command-line options
// see https://secure.php.net/manual/en/function.getopt.php
// : - required, :: - optional

$options = getopt("hvdrt:", ['help', 'verbose', 'debug', 'date:', 'date-format:', 'round']);

$do = [];
foreach ([
'verbose' => ['v', 'verbose'],
 'debug'   => ['d', 'debug'],
 'round'   => [null, 'round'],
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
debug('OPTIONS:', $do);

//-----------------------------------------------------------------------------
// help
if (array_key_exists('h', $options) || array_key_exists('help', $options)) {
    options:

    $readme_file = dirname(__FILE__) . '/README.md';
    if (file_exists($readme_file)) {
        $readme = file_get_contents('README.md');
        if (!empty($readme)) {
            output($readme . "\n");
        }
    }

    print join("\n",
            [
        "Usage: php moon.php",
        "Get the moon phase data using class https://github.com/solarissmoke/php-moon-phase",
        "(Specifying any other unknown argument options will be ignored.)\n",
        "\t-h,  --help                   Display this help and exit",
        "\t-v,  --verbose                Run in verbose mode",
        "\t-d,  --debug                  Run in debug mode (implies also -v, --verbose)",
        "\t-r,  --round                  (Optional) Round returned esults",
        "\t-t   --date={now}             (Optional) Date/time default 'now' see: https://secure.php.net/manual/en/function.strtotime.php",
        "\t     --date-format={U}        (Optional) Format to output, using date(), default unixtime, see: https://secure.php.net/manual/en/function.date.php",
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
//-----------------------------------------------------------------------------
// get date from/to from command-line

$date = 0;
if (!empty($options['date'])) {
    $date = $options['date'];
}
if (!empty($date)) {
    $date = strtotime($date);
    if (false === $date) {
        $errors[] = sprintf("Unable to parse --date: %s", $options['date']);
        goto errors;
    }

    verbose(sprintf("Fetching results FROM date/time '%s': %s",
            $options['date'], gmdate('r', $date)));
}
if (empty($date)) {
    $date = time();
}

//-----------------------------------------------------------------------------
// date format
$date_format = 'U';
if (!empty($options['date-format'])) {
    $date_format = $options['date-format'];
    if (false === date($date_format)) {
        $errors[] = "Invalid date format: $date_format";
        goto errors;
    }
}

//-----------------------------------------------------------------------------
// round result?

$round = array_key_exists('round', $options) | array_key_exists('r', $options);

//-----------------------------------------------------------------------------
// MAIN
// set up request params for sg_point_request($request_params)
$moon = new MyMoonPhase( new DateTime(date("Y-m-d H:i:s", $date)) );
$moon->setTimeStamp($date);

//-----------------------------------------------------------------------------
// final output of data

echo $moon->toJSON($round, $date_format);

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
        $float = (string) (float) $data;
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
