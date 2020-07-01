<?php
namespace VinylFinder;

class Fatbeats extends \VinylFinder\Base {

    private $foundReleases = [];
    private $prettyLabel   = null;

    public  $releases      = [];
    public  $message       = null;

    public function getReleases() {

        foreach (json_decode(FATBEATS_LABELS) as $label) {

            // Reset values
            $this->prettyLabel   = ucwords(str_replace('-',' ', $label));
            $this->foundReleases = [];
            $this->releases      = [];
            $page                = 1;

            // Log and go
            parent::printLog(' - Extracting Label: ' . $this->prettyLabel);

            do {
                parent::printLog('  - Extracting page #' . $page);
                $currentReleases = count($this->foundReleases);
                $html = $this->runTheJewels('https://www.fatbeats.com/collections/' . $label . '/?sort_by=created-descending&page=' . $page);
                $this->findReleases($html);
                $totalReleases = count($this->foundReleases);

                // If current vs. total are same break the loop
                if ($totalReleases === $currentReleases) {

                    parent::printLog('   - No releases found on this page, exiting loop');

                    // If on page one, there may be an error
                    if ($page === 1) {
                        $this->message = 'No releases found, possible error.';
                        $this->sendEmail('Fatbeats Error');
                    }

                    break;
                }

                // else increase page, find the releases
                $page += 1;

            } while ($currentReleases !== $totalReleases);

            $this->cacheKey = 'Fatbeats-' . md5($label);
            $this->ttl      = 0;
            $this->checkForChanges();
            $this->setCache($this->foundReleases, true);
            $this->setEmail();

        }

        // Send email
        $this->sendEmail(FATBEATS_SUBJECT);
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
        <div class="Grid__Cell 1/2--phone 1/3--tablet-and-up 1/4--desk">
            <div class="ProductItem ">
                <div class="ProductItem__Wrapper">
                    <a href="/collections/mello-music-group/products/marlowe-lorange-solemn-brigham-marlowe-2-lp" class="ProductItem__ImageWrapper ProductItem__ImageWrapper--withAlternateImage">
                        <div class="AspectRatio AspectRatio--square" style="max-width: 1024px;  --aspect-ratio: 1.0">
                            <img class="ProductItem__Image ProductItem__Image--alternate Image--lazyLoad Image--fadeIn" data-src="//cdn.shopify.com/s/files/1/0068/0042/products/Marlowe2VinylMockup_1024x1024_a9561857-e80f-4568-92eb-9afae848b011_{width}x.jpg?v=1593100937" data-widths="[200,300,400,600,800,900,1000]" data-sizes="auto" alt="Marlowe (L&#39;Orange &amp; Solemn Brigham) - Marlowe 2 (LP)" data-image-id="15247695380528">
                            <img class="ProductItem__Image Image--lazyLoad Image--fadeIn" data-src="//cdn.shopify.com/s/files/1/0068/0042/products/Marlowe23600x3600LoRes_1024x1024_d2503906-a3c1-4e6a-a336-6838759d3c03_{width}x.jpg?v=1593100937" data-widths="[200,400,600,700,800,900,1000]" data-sizes="auto" alt="Marlowe (L&#39;Orange &amp; Solemn Brigham) - Marlowe 2 (LP)" data-image-id="15247697674288">
                            <span class="Image__Loader"></span>
                            <noscript>
                                <img class="ProductItem__Image ProductItem__Image--alternate" src="//cdn.shopify.com/s/files/1/0068/0042/products/Marlowe2VinylMockup_1024x1024_a9561857-e80f-4568-92eb-9afae848b011_600x.jpg?v=1593100937" alt="Marlowe (L&#39;Orange &amp; Solemn Brigham) - Marlowe 2 (LP)">
                                <img class="ProductItem__Image" src="//cdn.shopify.com/s/files/1/0068/0042/products/Marlowe23600x3600LoRes_1024x1024_d2503906-a3c1-4e6a-a336-6838759d3c03_600x.jpg?v=1593100937" alt="Marlowe (L&#39;Orange &amp; Solemn Brigham) - Marlowe 2 (LP)">
                            </noscript>
                        </div>
                    </a>
                    <div class="ProductItem__Info ProductItem__Info--center">
                        <h2 class="ProductItem__Title Heading">
                            <a href="/collections/mello-music-group/products/marlowe-lorange-solemn-brigham-marlowe-2-lp">Marlowe (L'Orange & Solemn Brigham) - Marlowe 2 (LP)</a>
                        </h2>
                        <div class="ProductItem__PriceList  Heading">
                            <span class="ProductItem__Price Price Text--subdued" data-money-convertible>
                                <span class='money'>$27.99</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        */

        $regex = '<div class="ProductItem__Wrapper">.*?';
        $regex .= '<img.*?class="ProductItem__Image" src="(.*?)".*?>.*?';
        $regex .= '<h2 class="ProductItem__Title Heading">.*?<a href="(.*?)">(.*?)<\/a>.*?<\/h2>.*?';
        $regex .= '<span class=\'money\'>(.*?)<\/span>.*?<\/div>';
        preg_match_all('/' . $regex . '/ism', $html, $m);

        // If there is a price, there are results
        // 0 = everything, 1 = image, 2 = url, 3 = title, 4 = price
        if (isset($m[4][0])) {
            foreach ($m[0] as $k => $val) {
                $soldOut = false;
                if (stristr($m[0][$k], 'sold out')) {
                    $soldOut = true;
                }

                $url     = 'https://www.fatbeats.com' . $m[2][$k];
                $image   = 'https:' . $m[1][$k];
                $title   = $m[3][$k];
                $price   = str_replace('$', '', $m[4][$k]);
                $hash    = md5($url);

                if (stristr($title, '(cd)')) {
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
    }

    private function setEmail() {

        parent::printLog(' - Formatting email');

        if (empty($this->releases)) {
            return;
        }

        $this->message .= '<h2>' . $this->prettyLabel . '</h2>';

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
