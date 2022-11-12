<?php
if (file_exists(__DIR__ . '/config.php') === false) {
    print 'You must copy config.sample.php to config.php';
    exit;
}

// Require some files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/vendor/autoload.php';

// Start the log
\VinylFinder\Base::printLog(date('Y-m-d H:i:s A'));
\VinylFinder\Base::printLog(' - Getting wantlist from discogs');

// Get a random release
$discogs = new \VinylFinder\Discogs(DISCOGS_PERSONAL_TOKEN);
$release = $discogs->getRandomRelease(DISCOGS_USERNAME);

print '-------------------------------------------' . PHP_EOL;
print $release['emailTitle'] . PHP_EOL;
print $release['discogs_url'] . '?ships_from=United+States' . PHP_EOL;
