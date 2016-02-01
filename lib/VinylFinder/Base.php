<?php
namespace VinylFinder;

class Base {

    public $info;
    public $error;
    public $status;

    public $cacheKey = null;
    public $ttl      = 86400;

    private $cacheDir = 'cache/';
    private $cacheExt = '.cache';

    public function __construct() {
        $this->cacheDir = realpath(__DIR__ . '/../../') . '/' . $this->cacheDir;
    }

    public function runTheJewels($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $res          = curl_exec($ch);
        $this->info   = curl_getinfo($ch);
        $this->status = $this->info['http_code'];
        $this->error  = curl_error($ch);
        curl_close($ch);

        return $res;
    }

    public function getCache() {
        $cacheFile = $this->getCacheFileName();

        if ($this->isCacheExpired() === true) {
            return false;
        }

        // If serialized, unserialize it
        $value        = file_get_contents($cacheFile);
        $unserialized = unserialize($value);
        return $unserialized === false ? $value : $unserialized;
    }

    public function removeCache() {
        $cacheFile = $this->getCacheFileName();

        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    public function setCache($value, $override = false) {

        if ($this->isCacheExpired() === false && $override === false) {
            return;
        }

        // If array, serialize it
        $value = is_array($value) ? serialize($value) : $value;

        // write to cache
        file_put_contents($this->getCacheFileName(), $value);
    }

    public function isCacheExpired() {
        $cacheFile = $this->getCacheFileName();

        if (file_exists($cacheFile) === false) {
            return true;
        }

        // So you can use the cache methods just to save files
        if ($this->ttl <= 0) {
            return false;
        }

        $lastModified = filemtime($cacheFile);
        return $lastModified === false || (time() - $this->ttl) > $lastModified ? true : false;
    }

    private function getCacheFileName() {
        return $this->cacheDir . $this->cacheKey . $this->cacheExt;
    }

    public static function printLog($message) {
        print $message . PHP_EOL;
    }

    public static function getEmailMessage($wantList) {
        // Set the base email style, stupid table shit
        $message = null;

        // Loop thru the listings and get the email ready
        foreach ($wantList as $info) {
            $listings = isset($info['listings']) ? $info['listings'] : array();
            $total    = count($listings);

            if ($total > 0) {
                $message .= '<img src="' . $info['thumb'] . '" style="display:inline;float:left;margin-right: 5px;margin-bottom:10px;margin-top:50px;" />';
                $message .= '<h2 style="margin-top: 60px;"><a href="' . $info['discogs_url'] . '">' . $info['emailTitle'] . '</a> (' . $total . ')</h2>';
                $message .= '<table style="width:100%;border: 1px solid black;border-collapse: collapse;"><tr>';
                $message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Title</th>';
                $message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Location</th>';
                $message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Condition</th>';
                $message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Price</th>';
                $message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Shipping</th>';
                $message .= '<th style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">Total</th></tr>';
                foreach ($listings as $listing) {
                    $message .= '<tr>';
                    $message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;"><a href="' . $listing['url'] . '">';
                    $message .= $listing['title'] . '</a></td>';
                    $message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">' . $listing['location'] . '</td>';
                    $message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">' . $listing['condition'] . '</td>';
                    $message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">$' . $listing['price'] . '</td>';
                    $message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">$' . $listing['shipping'] . '</td>';
                    $message .= '<td style="border: 1px solid black;border-collapse: collapse;padding: 5px;text-align: left;">$' . $listing['totalPrice'] . '</td>';
                    $message .= '</tr>';
                }
                $message .= '</table>';
            }
        }

        return $message;
    }
}