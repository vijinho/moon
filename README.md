# moon - A command-line php for the Moon Phase

CLI script `moon.php` instantiates class [solarissmoke/php-moon-phase](https://github.com/solarissmoke/php-moon-phase) and echoes JSON if successful

The script can also be run as a web-service with PHP's in-built webserver for testing the JSON request/responses.

*NOTE:* Originally this script called the burning soul REST API to get the data, that
previous code is on this branch: https://github.com/vijinho/moon/tree/burningsoul

## Features

- Runs on the command-line
- Can be called as a stand-alone webservice using the php command line built-in server
- All messages when running with `--debug` or `--verbose` are to *stderr* to avoid interference with *stdout*
- Can output the result if successful to *stdout*
- Errors are output in JSON as 'errors' with just a bunch of strings

```
{
    "errors": [
        "Unable to parse --date: next sunsaday"
    ]
}
```

## Returned results fields/columns/keys

 - `phase`: the terminator phase angle as a fraction of a full circle (i.e., 0 to 1). Both 0 and 1 correspond to a New Moon, and 0.5 corresponds to a Full Moon.
 - `illumination`: the illuminated fraction of the Moon (0 = New, 1 = Full).
 - `age_days`: the age of the Moon, in days.
 - `distance_km`: the distance of the Moon from the centre of the Earth (kilometres).
 - `diameter`: the angular diameter subtended by the Moon as seen by an observer at the centre of the Earth (degrees).
 - `sun_distance_km`: the distance to the Sun (kilometres).
 - `sun_diameter`: the angular diameter subtended by the Sun as seen by an observer at the centre of the Earth (degrees).
 - `new_moon`: the time of the last New Moon (UNIX timestamp).
 - `next_new_moon`: the time of the next New Moon (UNIX timestamp).
 - `full_moon`: the time of the Full Moon in the current lunar cycle (UNIX timestamp).
 - `next_full_moon`: the time of the next Full Moon in the current lunar cycle (UNIX timestamp).
 - `first_quarter`: the time of the first quarter in the current lunar cycle (UNIX timestamp).
 - `next_first_quarter`: the time of the next first quarter in the current lunar cycle (UNIX timestamp).
 - `last_quarter`: the time of the last quarter in the current lunar cycle (UNIX timestamp).
 - `next_last_quarter`: the time of the next last quarter in the current lunar cycle (UNIX timestamp).
 - `phase_name`: the [phase name](http://aa.usno.navy.mil/faq/docs/moon_phases.php).
 - `stage`: the phase waxing/waning

## Instructions

### Command-line options

```
Usage: php moon.php
Get the moon phase data using class https://github.com/solarissmoke/php-moon-phase
(Specifying any other unknown argument options will be ignored.)

        -h,  --help                   Display this help and exit
        -v,  --verbose                Run in verbose mode
        -d,  --debug                  Run in debug mode (implies also -v, --verbose)
        -r,  --round                  (Optional) Round returned esults
        -t   --date={now}             (Optional) Date/time default 'now' see: https://secure.php.net/manual/en/function.strtotime.php
```

### Requirements/Installation

- PHP7
- composer: run `composer install` to install dependency [solarissmoke/php-moon-phase](https://github.com/solarissmoke/php-moon-phase)

## Testing Example

Run the following to run the test and view in 'less' text viewer:

`php moon.php --debug 2>&1 | less`

```
[D 1/1] OPTIONS:
Array
(
    [debug] => 1
    [round] => 0
    [verbose] => 1
)
{
    "timestamp": 0,
    "datestamp": "Thu, 01 Jan 1970 00:00:00 +0000",
    "phase": 0.752075462874,
    "illumination": 0.493479925882,
    "age_days": 22.2092311504,
    "distance_km": 391227.193148,
    "diameter": 0.509060110309,
    "sundistance_km": 147099708.642,
    "sundiameter": 0.542184276533,
    "new_moon_last": -1952337.60241,
    "new_moon_next": 592540.734328,
    "full_moon": -714314.659417,
    "full_moon_next": 1860925.62916,
    "first_quarter": -1378241.1266,
    "first_quarter_next": 1171091.16973,
    "last_quarter": -4036.1356616,
    "last_quarter_next": 2558339.3858,
    "phase_name": "Third Quarter",
    "stage": "waning"
}[D 1/1] Memory used (1/1) MB (current/peak).
```

with rounding '-r'

```
{
    "timestamp": 0,
    "datestamp": "Thu, 01 Jan 1970 00:00:00 +0000",
    "phase": 0.752,
    "illumination": 0.493,
    "age_days": 22.209,
    "distance_km": 391228,
    "diameter": 0.509,
    "sundistance_km": 147099709,
    "sundiameter": 0.542,
    "new_moon_last": -1952338,
    "new_moon_next": 592541,
    "full_moon": -714315,
    "full_moon_next": 1860926,
    "first_quarter": -1378241,
    "first_quarter_next": 1171091,
    "last_quarter": -4036,
    "last_quarter_next": 2558339,
    "phase_name": 0,
    "stage": "waning"
}
```

### Save to file example

`php moon.php --date='next year' > test.json`

## Running as a webservice

### Starting the service

1. Start the PHP webserver with `php -S 127.0.0.1:12312`
2. Browse the URL: http://127.0.0.1:12312/moon.php with GET/POST parameters as 'date=<UNIX TIMESTAMP>'  and 'round' for rounding or no param for NOW.

### Webservice Example

Search for moon phase 'next sunday' with rounding:

http://127.0.0.1:12312/moon.php?date=next%20sunday&round

Result:

```
{
    "timestamp": 1540080000,
    "datestamp": "Sun, 21 Oct 2018 00:00:00 +0000",
    "phase": 0.379,
    "illumination": 0.862,
    "age_days": 11.191,
    "distance_km": 400971,
    "diameter": 0.497,
    "sundistance_km": 148914125,
    "sundiameter": 0.536,
    "new_moon_last": 1539056870,
    "new_moon_next": 1541606572,
    "full_moon": 1540399654,
    "full_moon_next": 1542951687,
    "first_quarter": 1539712949,
    "first_quarter_next": 1542293669,
    "last_quarter": 1541004147,
    "last_quarter_next": 1543537278,
    "phase_name": 0,
    "stage": "waxing"
}
```

----
vijay@yoyo.org
