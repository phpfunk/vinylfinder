<?php
// Ebay & Discogs
define('EBAY_APPID', '', true);
define('DISCOGS_PERSONAL_TOKEN', '', true);
define('DISCOGS_USERNAME', '', true);
define('EMAIL_SUBJECT', 'Discogs Wantlist on Ebay', true);

// Sendgrid
define('SENDGRID_KEY', '', true);

// To / From Email
define('TO_ADDRESS', '', true);
define('FROM_ADDRESS', '', true);

// MelloMusicGroup
define('MMG_SUBJECT', 'MelloMusicGroup Vinyl Updates', true);

// Bandcamp
// Subdomains is a list of all the subs you want to parse
define('BANDCAMP_SUBJECT', 'Bandcamp Updates', true);
define('BANDCAMP_SUBDOMAINS', json_encode(array(
    'j-zone'
)), true);

// Fatbeats
define('FATBEATS_SUBJECT', 'Fatbeats Updates', true);
define('FATBEATS_LABELS', json_encode(array(
    'big-crown-records'
)), true);

// Debug
define('DEBUG', true, true);
