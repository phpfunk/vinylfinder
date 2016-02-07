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
            if (stristr($html, 'No products found') !== false) {
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
        <div class="masonry-item product span3">
            <div class="image">
                <a href="/collections/vinyl/products/copy-of-apollo-brown-grandeur-2xlp">
                    <img src="//cdn.shopify.com/s/files/1/0154/0333/products/ApolloBrown_Grandeur_RGBCover_02_1affefb9-0097-4b8d-b16e-9edd2bc20419_large.jpg?v=1437497550" alt="Apollo Brown - Grandeur (2xLP)" />
                </a>
            </div> <!-- /.image -->
            <div class="details">
                <a href="/collections/vinyl/products/copy-of-apollo-brown-grandeur-2xlp" class="clearfix">

                    <h4 class="title">Apollo Brown - Grandeur (2xLP)</h4>



                        <span class="price">$22.49</span>




                        <span class="banner sold-out">Sold Out</span>

                </a>
            </div> <!-- /.details -->
        </div> <!-- /.product -->
        */

        $regex = '<a href="(\/collections\/vinyl\/products\/.*?)".*?>.*?';
        $regex .= '<img src="(.*?)".*?>.*?';
        $regex .= '<h4 class="title">(.*?)<\/h4>.*?';
        $regex .= '<span class="price">\$(.*?)<\/span>.*?';
        $regex .= '(<span class=".*?sold-out">.*?<\/span>.*?)?';
        $regex .= '<\/a>';
        preg_match_all('/' . $regex . '/ism', $html, $m);

        foreach ($m[0] as $k => $val) {
            $url     = 'http://www.mellomusicgroup.com' . $m[1][$k];
            $image   = 'http:' . $m[2][$k];
            $title   = $m[3][$k];
            $price   = str_replace('$', '', $m[4][$k]);
            $soldOut = !empty($m[5][$k]) ? true : false;
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