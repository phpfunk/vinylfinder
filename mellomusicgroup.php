<?php

// Require some files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/vendor/autoload.php';

// Start the log
\VinylFinder\Base::printLog(date('Y-m-d H:i:s A'));
\VinylFinder\Base::printLog(' - Extracting pages from Mello Music');

$mmg = new \VinylFinder\MelloMusicGroup;
$mmg->getReleases();

// If there is a message to send, send it
if (!empty($mmg->message)) {
    \VinylFinder\Base::printLog(' - Sending email');

    // Send email
    $sendgrid = new SendGrid(SENDGRID_KEY);
    $email    = new SendGrid\Email();
    $email
        ->addTo(TO_ADDRESS)
        ->setFrom(FROM_ADDRESS)
        ->setSubject(MMG_SUBJECT)
        ->setHtml($mmg->message)
    ;

    try {
        $sendgrid->send($email);
        \VinylFinder\Base::printLog('  - Email sent');
    } catch(\SendGrid\Exception $e) {
        \VinylFinder\Base::printLog($e->getCode());
        echo $e->getCode();
        foreach($e->getErrors() as $er) {
            \VinylFinder\Base::printLog($er);
        }
    }

} else {
    \VinylFinder\Base::printLog(' - No email to send');
}