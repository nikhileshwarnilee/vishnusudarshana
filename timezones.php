<?php
// This file contains a static array of all IANA timezones as of PHP 8.2, with abbreviation and numeric offset
$timezones = [];
$tzIdentifiers = [

    // UTC −12
    "Etc/GMT+12",

    // UTC −11
    "Pacific/Pago_Pago",

    // UTC −10
    "Pacific/Honolulu",

    // UTC −9:30
    "Pacific/Marquesas",

    // UTC −9
    "America/Anchorage",

    // UTC −8
    "America/Los_Angeles",

    // UTC −7
    "America/Denver",

    // UTC −6
    "America/Chicago",

    // UTC −5
    "America/New_York",

    // UTC −4
    "America/Halifax",

    // UTC −3:30
    "America/St_Johns",

    // UTC −3
    "America/Sao_Paulo",

    // UTC −2
    "Atlantic/South_Georgia",

    // UTC −1
    "Atlantic/Azores",

    // UTC ±0
    "UTC",

    // UTC +1
    "Europe/Paris",

    // UTC +2
    "Europe/Athens",

    // UTC +3
    "Europe/Moscow",

    // UTC +3:30
    "Asia/Tehran",

    // UTC +4
    "Asia/Dubai",

    // UTC +4:30
    "Asia/Kabul",

    // UTC +5
    "Asia/Karachi",

    // UTC +5:30  (India, Sri Lanka)
    "Asia/Kolkata",

    // UTC +5:45
    "Asia/Kathmandu",

    // UTC +6
    "Asia/Dhaka",

    // UTC +6:30
    "Asia/Yangon",

    // UTC +7
    "Asia/Bangkok",

    // UTC +8
    "Asia/Shanghai",

    // UTC +8:45
    "Australia/Eucla",

    // UTC +9
    "Asia/Tokyo",

    // UTC +9:30
    "Australia/Adelaide",

    // UTC +10
    "Australia/Sydney",

    // UTC +10:30
    "Australia/Lord_Howe",

    // UTC +11
    "Pacific/Guadalcanal",

    // UTC +12
    "Pacific/Auckland",

    // UTC +12:45
    "Pacific/Chatham",

    // UTC +13
    "Pacific/Tongatapu",

    // UTC +14
    "Pacific/Kiritimati"
];

foreach ($tzIdentifiers as $tz) {
	$dtz = new DateTimeZone($tz);
	$now = new DateTime('now', $dtz);
	$offset = $dtz->getOffset($now);
	$sign = ($offset < 0) ? '-' : '+';
	$absOffset = abs($offset);
	$hours = floor($absOffset / 3600);
	$minutes = floor(($absOffset % 3600) / 60);
	$offsetStr = sprintf('%s%02d:%02d', $sign, $hours, $minutes);
	$offsetNum = $offset / 3600;
	$offsetNum = round($offsetNum * 2) / 2; // round to nearest 0.5
	$abbr = $now->format('T');
	$timezones[] = [
		'name' => $tz,
		'abbreviation' => $abbr,
		'offset' => $offsetNum,
		'offset_str' => $offsetStr
	];
}
