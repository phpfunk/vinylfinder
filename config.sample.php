<?php
// Ebay & Discogs
define('EBAY_APPID', '');
define('DISCOGS_PERSONAL_TOKEN', '');
define('DISCOGS_USERNAME', '');
define('EMAIL_SUBJECT', 'Discogs Wantlist on Ebay');

//Location Based
define('COUNTRY_CODE', 'US');

// Sendgrid
define('SENDGRID_KEY', '');

// To / From Email
define('TO_ADDRESS', '');
define('FROM_ADDRESS', '');

// MelloMusicGroup
define('MMG_SUBJECT', 'MelloMusicGroup Vinyl Updates');

// Bandcamp
// Subdomains is a list of all the subs you want to parse
define('BANDCAMP_SUBJECT', 'Bandcamp Updates');
define('BANDCAMP_SUBDOMAINS', json_encode(array(
    'j-zone'
)));

// Fatbeats
define('FATBEATS_SUBJECT', 'Fatbeats Updates');
define('FATBEATS_LABELS', json_encode(array(
    'big-crown-records'
)));

// Debug
define('DEBUG', true);
