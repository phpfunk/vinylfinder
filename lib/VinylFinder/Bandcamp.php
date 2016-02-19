<?php
namespace VinylFinder;

class Bandcamp extends \VinylFinder\Base {

    private $foundReleases    = [];
    private $releases         = [];
    private $subdomain        = null;
    private $url              = null;

    public $message           = null;

    public function getReleases() {

        $baseUrl = 'https://{SUB}.bandcamp.com';

        foreach (json_decode(BANDCAMP_SUBDOMAINS) as $subdomain) {

            // Reset releases
            $this->foundReleases = [];
            $this->releases      = [];

            // Log and go
            parent::printLog(' - Extracting: ' . $subdomain);
            $this->url       = str_replace('{SUB}', $subdomain, $baseUrl);
            $this->subdomain = $subdomain;
            $html            = $this->runTheJewels($this->url . '/merch');

            // If not 200; continue
            if ($this->status != 200) {
                parent::printLog('  - Could not fetch ' . $subdomain . ' -- ' . $this->status);
                continue;
            }

            // Get releases and prepare email copy for this artist
            $this->findReleases($html);
            $this->cacheKey = 'Bandcamp-' . md5($subdomain);
            $this->ttl      = 0;
            $this->checkForChanges();
            $this->setCache($this->foundReleases, true);
            $this->setEmail();
        }

        // Send Email
        $this->sendEmail(BANDCAMP_SUBJECT);
    }

    private function checkForChanges() {

        parent::printLog('  - Checking releases against cache');

        // Get cached, if any exist
        $cachedReleases = $this->getCache();
        if ($cachedReleases !== false) {

            foreach ($this->foundReleases as $hash => $data) {
                $changes     = array();
                $priceChange = 0;

                // if not found, it could be a new release
                if (!isset($cachedReleases[$hash])) {
                    $this->releases[$hash]            = $data;
                    $this->releases[$hash]['changes'] = 'New Release';
                    continue;
                }

                // If sold out or no longer sold out, update
                if ($data['soldOut'] != $cachedReleases[$hash]['soldOut']) {
                    $changes[] = 'Inventory Changed';
                }

                // If price is less, alert us
                if ($data['price'] < $cachedReleases[$hash]['price']) {
                    $changes[] = 'Price Decreased';
                    $priceChange = '-' . number_format($cachedReleases[$hash]['price'] - $data['price'], 2, '.', '');
                }

                // If price is more, alert us
                if ($data['price'] > $cachedReleases[$hash]['price']) {
                    $changes[]   = 'Price Increased';
                    $priceChange = '+' . number_format($data['price'] - $cachedReleases[$hash]['price'], 2, '.', '');
                }

                // If any changes add to public release and let us know why
                if (!empty($changes)) {
                    $this->releases[$hash]                = $data;
                    $this->releases[$hash]['changes']     = implode(', ', $changes);
                    $this->releases[$hash]['oldPrice']    = $data['price'];
                    $this->releases[$hash]['priceChange'] = 'N/A';

                    // if price changed
                    if (!empty($priceChange)) {
                        $this->releases[$hash]['priceChange'] = $priceChange;
                        $this->releases[$hash]['oldPrice']    = $cachedReleases[$hash]['price'];
                    }
                }
            }

        // If no cache, just set found to public releases
        } else {
            $this->releases = $this->foundReleases;
        }
    }

    private function findReleases($html) {

        /*
        <li data-item-id="4107351121" data-band-id="15793245" class="merch-grid-item" data-bind="css: {'featured': featured()}">
            <a href="/album/backyard-breaks">
                <div class="art">
                    <img src="https://f1.bcbits.com/img/a2260866659_37.jpg" alt="" />
                </div>
                <p class="title">
                    Backyard Breaks &ndash; Drum Break Bonanza!
                </p>
            </a>

            <div class="merchtype secondaryText">Record/Vinyl</div>

            <p class="price sold-out">Sold out</p>

            -- OR --

            <p class="price ">
                <span class="price">$12</span>
                <span class="currency">USD</span>
            </p>
        </li>

        */

        $regex = '<li.*?merch-grid-item.*?>.*?';
        $regex .= '<a href="(.*?)".*?>.*?';
        $regex .= '<img.*?src="(.*?)"\s+(data-original="(.*?)")?.*?>.*?';
        $regex .= '<p class="title">(.*?)<\/p>.*?';
        $regex .= '<div.*?merchtype.*?>(.*?)<\/div>.*?';
        $regex .= '<p.*?class="price\s?(sold-out)?">.*?';
        $regex .= '(<span.*?price.*?>.*?\$(\d{1,}).*?<\/span>.*?)?';
        $regex .= '<\/li>';
        preg_match_all('/' . $regex . '/ism', $html, $m);

        foreach ($m[0] as $k => $val) {

            // If not a record, move on
            $type = $m[6][$k];
            if (stristr($type, 'record') === false && stristr($type, 'vinyl') === false) {
                continue;
            }

            // Define some vars
            $url     = $this->url . $m[1][$k];
            $image   = empty($m[4][$k]) ? $m[2][$k] : $m[4][$k];
            $title   = trim(html_entity_decode($m[5][$k], ENT_QUOTES));
            $title   = preg_replace('/[\r\n]/', '', $title);
            $title   = preg_replace('/\s+/', ' ', $title);
            $soldOut = !empty($m[7][$k]) ? true : false;
            $price   = $soldOut !== true ? number_format($m[9][$k], 2, '.', '') : 0.00;
            $hash    = md5($url);

            // If not a record, move on
            if (stristr($type, 'record') === false && stristr($type, 'vinyl') === false) {
                continue;
            }

            if (array_key_exists($hash, $this->foundReleases) === false) {
                $this->foundReleases[md5($url)] = array(
                    'title'       => $title,
                    'image'       => $image,
                    'price'       => $price,
                    'soldOut'     => $soldOut,
                    'url'         => $url,
                    'oldPrice'    => $price,
                    'priceChange' => '0.00',
                    'changes'     => 'N/A'
                );
            }
        }

    }

    private function setEmail() {

        parent::printLog('  - Formatting email for ' . $this->subdomain);

        if (empty($this->releases)) {
            return;
        }

        // Loop thru the listings and get the email ready
        foreach ($this->releases as $info) {

            $soldOut = $info['soldOut'] === true ? 'Yes' : 'No';

            $this->message .= '<h2>' . $this->subdomain . '</h2>';
            $this->message .= '<img src="' . $info['image'] . '" style="display:inline;width:150px;height:150px;float:left;margin-right: 5px;margin-bottom:10px;margin-top:50px;" />';
            $this->message .= '<h3 style="margin-top: 60px;"><a href="' . $info['url'] . '">' . $info['title'] . '</a></h3>';
            $this->message .= '<table style="width:100%;border: 1px solid black;border-collapse: collapse;"><tr>';
            $this->message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Price</th>';
            $this->message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Old Price</th>';
            $this->message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Price Difference</th>';
            $this->message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Sold Out</th>';
            $this->message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Changes</th></tr>';

            $this->message .= '<tr>';
            $this->message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">$' . $info['price'] . '</td>';
            $this->message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">$' . $info['oldPrice'] . '</td>';
            $this->message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">' . $info['priceChange'] . '</td>';
            $this->message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">' . $soldOut . '</td>';
            $this->message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">' . $info['changes'] . '</td>';
            $this->message .= '</tr>';
            $this->message .= '</table>';
        }

    }
}