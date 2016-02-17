<?php
namespace VinylFinder;

class MelloMusicGroup extends \VinylFinder\Base {

    private $foundReleases = [];
    public  $releases      = [];
    public  $message       = null;

    public function getReleases() {
        $page = 1;
        do {
            parent::printLog('  - Extracting page #' . $page);
            $html = $this->runTheJewels('http://www.mellomusicgroup.com/collections/vinyl?page=' . $page);

            // If no more releases, break out
            if (stristr($html, 'no products in this collection') !== false) {
                parent::printLog('   - No releases found on this page, exiting loop');
                break;
            }

            // else increase page, find the releases
            $page += 1;
            $this->findReleases($html);
        } while (true === true);

        $this->cacheKey = 'MelloMusicGroupVinyl';
        $this->ttl      = 0;
        $this->checkForChanges();
        $this->setCache($this->foundReleases, true);
        $this->setEmail();
        $this->sendEmail(MMG_SUBJECT);
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
                    $priceChange = '-' . number_format($cachedReleases[$hash]['price'] - $data['price'], 2, '.', ',');
                }

                // If price is more, alert us
                if ($data['price'] > $cachedReleases[$hash]['price']) {
                    $changes[]   = 'Price Increased';
                    $priceChange = '+' . number_format($data['price'] - $cachedReleases[$hash]['price'], 2, '.', ',');
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
        <div class="grid__item large--one-quarter medium-down--one-half">
            <a href="/collections/vinyl/products/mr-lif-dont-look-down-lp" class="grid-link">
                <span class="grid-link__image grid-link__image--product">
                    <span class="grid-link__image-centered">
                        <img src="//cdn.shopify.com/s/files/1/0154/0333/products/MrLif_DontLookDown_Cover_Art_hi-res_e4ceae90-17e2-4d79-a97f-b43555a69712_large.jpg?v=1455203289" alt="Mr. Lif - Don&#39;t Look Down (LP)">
                    </span>
                </span>
                <p class="grid-link__title">Mr. Lif - Don't Look Down (LP)</p>
                <p class="grid-link__meta">
                    <strong>$18.99</strong>
                </p>
            </a>
        </div>
        */

        $regex = '<div class="grid__item.*?(sold-out)?">.*?';
        $regex .= '<a href="(\/collections\/vinyl\/products\/.*?)".*?>.*?';
        $regex .= '<img src="(.*?)".*?>.*?';
        $regex .= '<p class="grid-link__title">(.*?)<\/p>.*?';
        $regex .= '<p class="grid-link__meta">.*?\$(\d{1,}\.\d{2}).*?<\/p>.*?';
        $regex .= '<\/a>.*?<\/div>';
        preg_match_all('/' . $regex . '/ism', $html, $m);

        foreach ($m[0] as $k => $val) {
            $url     = 'http://www.mellomusicgroup.com' . $m[2][$k];
            $image   = 'http:' . $m[3][$k];
            $title   = $m[4][$k];
            $price   = $m[5][$k];
            $soldOut = !empty($m[1][$k]) ? true : false;
            $hash    = md5($url);

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

        parent::printLog(' - Formatting email');

        if (empty($this->releases)) {
            return;
        }

        // Loop thru the listings and get the email ready
        foreach ($this->releases as $info) {

            $soldOut = $info['soldOut'] === true ? 'Yes' : 'No';

            $this->message .= '<img src="' . $info['image'] . '" style="display:inline;width:150px;height:150px;float:left;margin-right: 5px;margin-bottom:10px;margin-top:50px;" />';
            $this->message .= '<h2 style="margin-top: 60px;"><a href="' . $info['url'] . '">' . $info['title'] . '</a></h2>';
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