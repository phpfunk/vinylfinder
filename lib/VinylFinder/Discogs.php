<?php
namespace VinylFinder;

class Discogs extends \VinylFinder\Base {

    public $personalToken;
    private $wants = array();

    public function __construct($personalToken) {
        parent::__construct();
        $this->personalToken = $personalToken;
    }

    private function search($path, $params = array()) {
        $url = 'https://api.discogs.com/' . $path;
        $url .= '?token=' . $this->personalToken;
        foreach ($params as $param => $val) {
            $url .= '&' . $param . '=' . urlencode($val);
        }

        return json_decode($this->runTheJewels($url));

    }

    public function searchByTitleAndArtist($title, $artist) {
        return $this->search('database/search', array(
            'release_title' => $title,
            'artist'        => $artist
        ));

    }

    public function searchMarketplace($wantList = array()) {

        foreach ($wantList as $key => $listing) {

            // Check if the listings array is set yet, if not, set it
            if (!isset($wantList[$key]['listings'])) {
                $wantList[$key]['listings'] = array();
            }

            $this->cacheKey = 'Discogs_Marketplace_' . $listing['releaseId'];
            $this->ttl = 28800; // 8 hours

            parent::printLog('  - fetching ' . $listing['releaseId'] . ' from discogs marketplace');

            $result = $this->getCache();
            if ($this->isCacheExpired() === true || $result === false) {
                $result = $this->getMarketplaceListingsByReleaseId($listing['releaseId']);

                // If too many requests, take a break
                if ($this->status == 429) {
                    parent::printLog("   - too many requests to the discogs marketplace, take a break");
                    break;
                }

                // Only save cache if there is a result
                if (!empty($result)) {
                    $this->setCache($result);
                }

                // Take a break so you don't get rate limited
                sleep(1);
            }

            // How many is found
            parent::printLog('   - (' . count($result) . ') listings found');

            // set the listings to the wantlist item
            $wantList[$key]['listings'] = $result;

        }

        // Send email
    }

    public function getMarketplaceListingsByReleaseId($releaseId) {
        $result = $this->runTheJewels('https://www.discogs.com/sell/mplistrss?release_id=' . $releaseId);
        $result = json_decode(json_encode(simplexml_load_string($result)));

        if (!isset($result->entry) || empty($result->entry)) {
            return [];
        }

        $listings = [];
        $x = 0;
        foreach ($result->entry as $listing) {
            $listings[$x] = [];
            $listings[$x]['url'] = $listing->id;
            $listings[$x]['last_updated'] = $listing->updated;
            $summary = explode(' - ', $listing->summary);
            $price   = explode(' ', $summary[0]);
            $listings[$x]['currency'] = $price[0];
            $listings[$x]['price'] = $price[1];
            $listings[$x]['seller'] = $summary[1];
            $listings[$x]['seller_rating'] = 'https://www.discogs.com/sell/seller_feedback/' . $summary[1];
            $listings[$x]['notes'] = $summary[2];
            $x++;
        }

        return $listings;
    }

    public function getByReleaseId($releaseId) {
        $res = $this->search('releases/' . $releaseId);
        print_r($res);
    }

    public function getRandomRelease($username) {
      $wantlist = $this->getWantlist($username);
      $itemKey  = array_rand($wantlist);
      return $wantlist[$itemKey];
    }

    public function getWantlist($username) {
        $this->cacheKey = 'Discogs_WantList_' . md5($username);

        $cachedResult = $this->getCache();
        if ($this->isCacheExpired() === false && $cachedResult !== false) {
            return $this->prepareQueries($cachedResult);
        }

        $this->wants = array();
        $wants       = $this->search('users/' . $username . '/wants');

        // If no wants, message and leave
        if (!isset($wants->wants)) {
            parent::printLog("  - Your wantlist wasn't found");
            parent::printLog('  - Status Code: ' . $this->status);
            parent::printLog('  - Error: ' . $this->error);
            exit;
        }

        $this->theFormat($wants);

        // get total pages
        $pages = 1;
        if (isset($wants->pagination->pages)) {
            $pages = $wants->pagination->pages;
        }

        // If more than one page, get the rest
        if ($pages > 1) {
            for ($page = 2; $page <= $pages; $page++) {

                $this->theFormat(
                    $this->search(
                      'users/' . $username . '/wants',
                      array('page' => $page)
                    )
                );
            }
        }

        $this->setCache($this->wants);
        return $this->prepareQueries();
    }

    private function prepareQueries($wantList = array()) {

        $this->wants = !empty($wantList) ? $wantList : $this->wants;
        $wants       = array();

        foreach ($this->wants as $record) {

            // Set some shit
            $artist = trim($record['artist']);
            $title  = trim($record['title']);
            $is45   = $record['is45'] === true ? ' 45' : '';
            $key    = md5(trim($artist . $title));

            // If the query already exists, skip it
            if (array_key_exists($key, $wants) === true) {
                continue;
            }

            // Save the base information from your wantlist
            $wants[$key]                = array();
            $wants[$key]['queries']     = array();
            $wants[$key]['discogs_url'] = $record['discogs_url'];
            $wants[$key]['artist']      = $artist;
            $wants[$key]['title']       = $title;
            $wants[$key]['is45']        = $record['is45'];
            $wants[$key]['thumb']       = $record['thumb'];
            $wants[$key]['releaseId']   = $record['release_id'];
            $wants[$key]['emailTitle']  = $artist . ' - ' . $title . $is45;

            // If there is a \s/\s, search both titles
            if (stristr($record['title'], ' / ')) {
                $tmp = explode(' / ', $title);
                foreach ($tmp as $text) {
                    $wants[$key]['queries'][] = $artist . ' ' . $text . $is45;
                }
            }
            // Else just search the one
            else {
                $wants[$key]['queries'][] = $artist . ' ' . $title . $is45;
            }

        }

        return $wants;
    }

    private function theFormat($wants) {
        foreach ($wants->wants as $want) {

            $info   = $want->basic_information;
            $id     = $info->id;

            // Find the artist
            $artist = null;
            if (isset($info->artists[0]->name)) {
                $artist = $info->artists[0]->name;
            }

            // Title
            $title = $info->title;
            $titleKey = md5($title);

            if (array_key_exists($titleKey, $this->wants)) {
                continue;
            }

            // Discogs URL
            $url = 'http://www.discogs.com/sell/release/' . $id;

            // Check if this is a 45
            $is45 = false;
            if (isset($info->formats[0]->descriptions)) {
                foreach ($info->formats[0]->descriptions as $format) {
                    if ($format == '7"' || $format == '45 RPM') {
                        $is45 = true;
                        break;
                    }
                }
            }

            $this->wants[$titleKey] = array(
                'release_id'  => $id,
                'title'       => $title,
                'discogs_url' => $url,
                'is45'        => $is45,
                'artist'      => $artist,
                'thumb'       => $info->thumb
            );

        }
    }

}
