# moon - a command-line php script/webservice proxy for burning soul moon API at https://burningsoul.in/apis/moon

CLI script `moon.php` calls the burning soul moon API](https://burningsoul.in/apis/moon) and writes the JSON to a file if successful. Optionally will output the result to *stdout*.

- [burningsoul/API-CLIENT](https://github.com/burningsoul/API-CLIENT/wiki/MOON)

The script can also be run as a web-service with PHP's in-built webserver for testing the JSON request/responses.

## Features

- Runs on the command-line
- Uses a simple [moon.ini](moon.ini.dist) configuration file for options
- Uses command-line [curl](https://curl.haxx.se)
- Can be called as a stand-alone webservice using the php command line built-in server
- Validates parameters before sending
- Caches the result (json-encoded) to avoiding sending repeat-requests with configurable cache age time (in seconds)
- Cache filename is human-readable - format is: moon-unixtime.json ie. *cache/moon-1540080000.json*
- All messages when running with `--debug` or `--verbose` are to *stderr* to avoid interference with *stdout*
- Can output the result if successful to *stdout*
- Errors are output in JSON as 'errors' with just a bunch of strings

```
{
    "errors": [
        "Unable to parse --date: &#39;next sundsay&#39;"
    ]
}
```

## Returned results fields/columns/keys

- age : current age of moon in days
- illumination : illumination (in %)
- stage : current moon phase stage (waning/waxing)
- DFCOE : distance from core of earth
- DFS : distance from sun
- FM->UT: full moon unix time stamp
- FM->DT: full moon date and time
- NNM->UT: new moon unix time stamp
- NNM->DT: new moon date and time

## Instructions

### Command-line options

```
Usage: php moon.php
Call to the burning soul moon API - https://burningsoul.in/apis/moon
(Specifying any other unknown argument options will be ignored.)

        -h,  --help                   Display this help and exit
        -v,  --verbose                Run in verbose mode
        -d,  --debug                  Run in debug mode (implies also -v, --verbose)
        -t,  --test                   Run in test mode.
        -o,  --offline                Do not go-online when performing tasks (only use local files for url resolution for example)
        -e,  --echo                   (Optional) Echo/output the result to stdout if successful
        -r,  --refresh                (Optional) Force cache-refresh
             --date={now}             (Optional) Date/time default 'now' see: https://secure.php.net/manual/en/function.strtotime.php
             --dir={.}                (Optional) Directory for storing files (current dir if not specified)
        -f,  --filename={output.}     (Optional) Filename for output data from operation, default is 'output.{--format}'
             --format={json}          (Optional) Output format for script data: json (default)
```

### Requirements/Installation

- PHP7
- curl (command-line)
- Copy the `moon.ini.dist` to `moon.ini`

## Testing Example

Run the following to run the test and view in 'less' text viewer:

`php moon.php --echo`

```
{
    "DFCOE": 401387.33,
    "DFS": 149164258.67,
    "FM": {
        "UT": 1540399654.02,
        "DT": "16:47:34-24 Oct 2018"
    },
    "NNM": {
        "UT": 1541606572.44,
        "DT": "16:02:52-7 Nov 2018"
    },
    "age": 5.8547815926,
    "illumination": 34.0306758453,
    "stage": "waxing"
}
```

Same wiith full verbosity and debugging:

`php moon.php --debug --echo 2>&1 | less`

```
[D 1/1] CONFIG
Array
(
    [settings] => Array
        (
            [timezone] => UTC
            [cache] => Array
                (
                    [seconds] => 3600
                )

        )

    [api] => Array
        (
            [url] => http://api.burningsoul.in/moon/
        )

)
[D 1/1] COMMANDS:
Array
(
    [curl] => /usr/local/bin/curl
)
[D 1/1] OPTIONS:
Array
(
    [debug] => 1
    [echo] => 1
    [offline] => 0
    [refresh] => 0
    [test] => 0
    [verbose] => 1
)
[V 1/1] OUTPUT_FORMAT: json
[D 1/1] Cached data loaded from: /Users/vijay/src/moon/cache/moon-0.json
[V 1/1] JSON written to output file:
        /Users/vijay/src/moon/output.json (304 bytes)
{
    "DFCOE": 401387.33,
    "DFS": 149164258.67,
    "FM": {
        "UT": 1540399654.02,
        "DT": "16:47:34-24 Oct 2018"
    },
    "NNM": {
        "UT": 1541606572.44,
        "DT": "16:02:52-7 Nov 2018"
    },
    "age": 5.8547815926,
    "illumination": 34.0306758453,
    "stage": "waxing"
}[D 1/1] Memory used (1/1) MB (current/peak).
```

## Running as a webservice

### Starting the service

1. Start the PHP webserver with `php -S 127.0.0.1:12312`
2. Browse the URL: http://127.0.0.1:12312/moon.php with GET/POST parameters as 'date=<UNIX TIMESTAMP>' or no param for NOW.

### Webservice Example 1

e.g. http://127.0.0.1:12312/moon.php

Use with '?refresh' to refresh

Returns:

```
{
    "DFCOE": 401387.33,
    "DFS": 149164258.67,
    "FM": {
        "UT": 1540399654.02,
        "DT": "16:47:34-24 Oct 2018"
    },
    "NNM": {
        "UT": 1541606572.44,
        "DT": "16:02:52-7 Nov 2018"
    },
    "age": 5.8547815926,
    "illumination": 34.0306758453,
    "stage": "waxing"
}
```

### Webservice Example 2

Search for moon phase 'next sunday'

http://127.0.0.1:12312/moon.php?date=next%20sunday

Result:

```
{
    "DFCOE": 400970.22,
    "DFS": 148914124.33,
    "FM": {
        "UT": 1540399654.02,
        "DT": "16:47:34-24 Oct 2018"
    },
    "NNM": {
        "UT": 1541606572.44,
        "DT": "16:02:52-7 Nov 2018"
    },
    "age": 11.1912482402,
    "illumination": 86.226462107,
    "stage": "waxing"
}
```

----
vijay@yoyo.org
