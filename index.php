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

// Get your discogs wantlist
$discogs       = new \VinylFinder\Discogs(DISCOGS_PERSONAL_TOKEN);
$wantList      = $discogs->getWantList(DISCOGS_USERNAME);
$wantListTotal = count($wantList);

\VinylFinder\Base::printLog(' - ' . $wantListTotal . ' wantlist item(s) found');

// get 10% of the wantlist if debug is on
if (DEBUG === true) {
    $x          = 0;
    $tenPercent = floor($wantListTotal * .1);

    $new = array();
    foreach ($wantList as $key => $array) {
        if ($x >= $tenPercent) {
            break;
        }

        $new[$key] = $wantList[$key];
        $x += 1;
    }
    unset($wantList);
    $wantList = $new;
    unset($new);
}

//\VinylFinder\Base::printLog(' - Starting to search the discogs marketplace');
//$discogs->searchMarketplace($wantList);

\VinylFinder\Base::printLog(' - Starting to search ebay for your wantlist');

// Ebay it
$ebay = new \VinylFinder\Ebay(EBAY_APPID);
$ebay->getListings($wantList);
