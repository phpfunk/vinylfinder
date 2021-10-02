<?php
namespace VinylFinder;

class MelloMusicGroup extends \VinylFinder\Base {

    private $foundReleases = [];

    public  $releases      = [];
    public  $message       = null;

    public function getReleases() {
        $page = 1;
        do {
            /**
             * Get total releases now
             * Print a log, extract HTML
             * Find releases in the HTML
             * Find new number of releases
             **/
            $currentReleases = count($this->foundReleases);
            parent::printLog('  - Extracting page #' . $page);
            $html = $this->runTheJewels('https://www.mellomusicgroup.com/collections/vinyl?page=' . $page);
            $this->findReleases($html);
            $totalReleases = count($this->foundReleases);

            // If current vs. total are same break the loop
            if ($totalReleases === $currentReleases) {

                parent::printLog('   - No releases found on this page, exiting loop');

                // If on page one, there may be an error
                if ($page === 1) {
                    $this->message = 'No releases found, possible error.';
                    $this->sendEmail('Mello Music Group Error');
                }

                break;
            }

            // else increase page, find the releases
            $page += 1;

        } while ($currentReleases !== $totalReleases);

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
        <figure class="product-grid-item--center">
    <a id="ProductGridImageWrapper-collection-template-7321125-4784135045209" href="/collections/vinyl/products/open-mike-eagle-brick-body-kids-still-daydream-b-lp" class="product_card"><div class="product_card__image-wrapper" data-bgset="//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_180x.jpg?v=1612203919 180w 180h,//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_295x.jpg?v=1612203919 295w 295h,//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_394x.jpg?v=1612203919 394w 394h,//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_590x.jpg?v=1612203919 590w 590h,//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_788x.jpg?v=1612203919 788w 788h,//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_900x.jpg?v=1612203919 900w 900h,//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_1180x.jpg?v=1612203919 1180w 1180h,//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_1200x.jpg?v=1612203919 1200w 1200h,//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_1500x.jpg?v=1612203919 1500w 1500h,//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_1800x.jpg?v=1612203919 1800w 1800h,//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_2000x.jpg?v=1612203919 2000w 2000h,//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a.jpg?v=1612203919 3600w 3600h">


        <img id="ProductGridImage-collection-template-7321125-4784135045209" class="product_card__image lazyload "
          src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=="
          data-src="//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_{width}x.jpg?v=1612203919"
          data-widths="[295,394,590,700,800,1000,1200,1500,1800,2000,2400,3600]"
          data-aspectratio="1.0"
          data-sizes="auto"
          data-expand="600"
          data-fallback="//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_295x.jpg?v=1612203919"
          alt="Open Mike Eagle - Brick Body Kids Still Daydream (LP)">
        <noscript>
          <img class="product_card__image" src="//cdn.shopify.com/s/files/1/0154/0333/products/Pirates_Press_Open_Mike_Eagle_Math_was_Invented_in_the_Projects_3600x3600_RGB_2043558d-8903-4ac6-87e5-66994570091a_394x.jpg?v=1612203919" alt="Open Mike Eagle - Brick Body Kids Still Daydream (LP)">
        </noscript><span class="label sold-out label--bottom-right ">Sold Out
</span></div>
    </a>
    <figcaption>
      <div class="product-title">
        <a href="/collections/vinyl/products/open-mike-eagle-brick-body-kids-still-daydream-b-lp" class="title">Open Mike Eagle - Brick Body Kids Still Daydream (LP)</a>

      </div>

      <span class="price

         price--sold-out
        ">


          <span class="money">$21.99</span>


        <div class="price__unit">
          <dt>
            <span class="visually-hidden">Unit price</span>
          </dt>
          <dd class="price-unit-price"><span data-unit-price></span><span aria-hidden="true">/</span><span class="visually-hidden">per&nbsp;</span><span data-unit-price-base-unit></span></dd>
        </div>
      </span>

    </figcaption></figure>
        */

        $regex = '<div class="box product">.*?<figure.*?>.*?';
        $regex .= '<img class="product_card__image" src="(.*?)".*?>.*?';
        $regex .= '<div class="product-title">.*?<a href="(\/collections\/vinyl\/products\/.*?)" class="title">(.*?)<\/a>.*?<\/div>.*?';
        $regex .= '<span class="(price.*?)">.*?';
        $regex .= '<span class="money">\$(\d{1,}\.\d{2}).*?<\/span>.*?';
        $regex .= '<\/figure>.*?<\/div>';

        preg_match_all('/' . $regex . '/ism', $html, $m);

        // If no title, not releases
        if (!isset($m[3][0])) {

        }

        foreach ($m[0] as $k => $val) {
            $url     = 'https://www.mellomusicgroup.com' . $m[2][$k];
            $hash    = md5($url);
            $image   = 'https:' . $m[1][$k];
            $title   = $m[3][$k];
            $price   = $m[5][$k];
            $soldOut = true;

            // if string is not found, it's NOT sold out
            if (stristr($m[4][$k], 'sold-out') === false) {
              $soldOut = false;
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
