<?php
namespace VinylFinder;

class Ebay extends \VinylFinder\Base {

    private $appId;
    private $countryCode;
    private $wantList = array();

    public  $categoryId = '176985'; // Records
    public  $message    = null;

    public function __construct($appId, $countryCode = null) {
      parent::__construct();
      $this->appId  = $appId;
      $this->countryCode = $countryCode;
    }

    public function getListings($wantList = array()) {

      foreach ($wantList as $key => $listing) {

          // cycle thru the search queries and execute
          foreach ($listing['queries'] as $query) {
              parent::printLog(' - Searching for: ' . $query);
              $results = $this->search($query, 50);

              // If there are results, move ahead
              if ($results !== false) {

                  // Check if the listings array is set yet, if not, set it
                  if (!isset($wantList[$key]['listings'])) {
                      $wantList[$key]['listings'] = array();
                  }

                  // loop thru ebay search results and save them
                  foreach ($results as $result) {

                      // Set a unique key by the url
                      // If it already exists (searching same title split up), skip it
                      $listingKey = md5(trim($result['url']));
                      if (array_key_exists($listingKey, $wantList[$key]['listings'])) {
                          continue;
                      }

                      // Set the ebay supplied information to the listing array
                      $wantList[$key]['listings'][$listingKey] = array(
                          'totalPrice' => number_format($result['price'] + $result['shipping'], 2, '.', ','),
                          'price'      => number_format($result['price'], 2, '.', ','),
                          'shipping'   => number_format($result['shipping'], 2, '.', ','),
                          'location'   => $result['location'],
                          'url'        => $result['url'],
                          'title'      => $result['title'],
                          'condition'  => $result['condition'],
                      );

                  }

                  parent::printLog('  - ' . count($wantList[$key]['listings']) . ' item(s) found');
              } else {
                  parent::printLog('  - No items returned');
              }
          }
      }

      $this->wantList = $wantList;
      $this->setEmail();
      $this->sendEmail(EMAIL_SUBJECT);
    }

    private function search($query, $returnTotal = 50) {

      $this->cacheKey = 'Ebay-' . md5($query);
      $this->ttl      = 0;

      $url = 'http://svcs.ebay.com/services/search/FindingService/v1?OPERATION-NAME=findItemsAdvanced&SERVICE-VERSION=1.0.0&';
      $url .= 'SECURITY-APPNAME=' . $this->appId . '&GLOBAL-ID=EBAY-US';
      $url .= '&keywords=' . urlencode($query) . '&paginationInput.entriesPerPage=100';
      $url .= '&categoryId=' . $this->categoryId . '&sortOrder=StartTimeNewest';

      // Call API
      $res = $this->runTheJewels($url);

      // Normalize Results
      if ($this->status == '200') {
          $xml   = simplexml_load_string($res);
          $json  = json_decode(json_encode($xml), true);

          if ($json['ack'] == 'Failure' || !isset($json['paginationOutput']['totalEntries'])) {
              return false;
          }

          $total = $json['paginationOutput']['totalEntries'];

          if ($total < 1) {
              return false;
          }

          // Set items
          $items = $json['searchResult']['item'];

          //Get all the cached prices for this query
          $cachedPrices = $this->getCache();

          $results    = array();
          $priceCache = array();
          foreach ($items as $item) {

              if (count($results) >= $returnTotal) {
                  break;
              }

              // Set item country
              $country = isset($item['country']) ? $item['country'] : null;

              // if country specific, make sure the listing matches the country
              if (!is_null($this->countryCode) && strtoupper($country) != strtoupper($this->countryCode)) {
                continue;
              }

              $shipping = 0;
              if (isset($item['shippingInfo']['shippingServiceCost'])) {
                  $shipping = $item['shippingInfo']['shippingServiceCost'];
              }

              $condition = null;
              if (isset($item['condition']['conditionDisplayName'])) {
                  $condition = $item['condition']['conditionDisplayName'];
              }

              $category   = null;
              $categoryId = null;
              if (isset($item['primaryCategory']['categoryName'])) {
                  $category   = $item['primaryCategory']['categoryName'];
                  $categoryId = $item['primaryCategory']['categoryId'];
              }

              if ($categoryId == $this->categoryId) {
                  $priceKey              = md5($item['viewItemURL']);
                  $priceCache[$priceKey] = $item['sellingStatus']['currentPrice'];

                  // If you have already seen this price, don't send it again
                  if (!isset($cachedPrices[$priceKey])
                      || $cachedPrices[$priceKey] != $priceCache[$priceKey]) {

                      $results[] = array(
                          'title'     => $item['title'],
                          'url'       => $item['viewItemURL'],
                          'location'  => $item['location'],
                          'shipping'  => $shipping,
                          'price'     => $item['sellingStatus']['currentPrice'],
                          'condition' => $condition,
                          'category'  => $category,
                          'cat_id'    => $categoryId
                      );
                  }
              }
          }

          // Set each result set to cache for later use
          $this->setCache($priceCache, true);

          // Return results
          return count($results) < 1 ? false : $results;
      } else {
          return false;
      }
    }

    private function setEmail() {

      parent::printLog(' - Formatting email');

      if (empty($this->wantList)) {
          return;
      }

      // Loop thru the listings and get the email ready
      foreach ($this->wantList as $info) {
          $listings = isset($info['listings']) ? $info['listings'] : array();
          $total    = count($listings);

          if ($total > 0) {
              $this->message .= '<img src="' . $info['thumb'] . '" style="display:inline;float:left;margin-right: 5px;margin-bottom:10px;margin-top:50px;" />';
              $this->message .= '<h2 style="margin-top: 60px;"><a href="' . $info['discogs_url'] . '">' . $info['emailTitle'] . '</a> (' . $total . ')</h2>';
              $this->message .= '<table style="width:100%;border: 1px solid black;border-collapse: collapse;"><tr>';
              $this->message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Title</th>';
              $this->message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Location</th>';
              $this->message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Condition</th>';
              $this->message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Price</th>';
              $this->message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Shipping</th>';
              $this->message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Total</th></tr>';
              foreach ($listings as $listing) {
                  $this->message .= '<tr>';
                  $this->message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;"><a href="' . $listing['url'] . '">';
                  $this->message .= $listing['title'] . '</a></td>';
                  $this->message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">' . $listing['location'] . '</td>';
                  $this->message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">' . $listing['condition'] . '</td>';
                  $this->message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">$' . $listing['price'] . '</td>';
                  $this->message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">$' . $listing['shipping'] . '</td>';
                  $this->message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">$' . $listing['totalPrice'] . '</td>';
                  $this->message .= '</tr>';
              }
              $this->message .= '</table>';
          }
      }

    }

}
