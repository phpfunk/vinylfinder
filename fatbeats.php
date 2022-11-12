<?php
// Require some files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/vendor/autoload.php';

// Start the log
\VinylFinder\Base::printLog(date('Y-m-d H:i:s A'));
\VinylFinder\Base::printLog(' - Extracting pages from Fat Beats');

$fb = new \VinylFinder\Fatbeats;
$fb->getReleases();
