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
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, '@phpfunk VinylFinder');

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

    protected function sendEmail($subject) {

        // If there is a message to send, send it
        if (!empty($this->message)) {
            self::printLog(' - Sending email');

            // Send email
            $sendgrid = new \SendGrid(SENDGRID_KEY);
            $email    = new \SendGrid\Email();
            $email
                ->addTo(TO_ADDRESS)
                ->setFrom(FROM_ADDRESS)
                ->setSubject($subject)
                ->setHtml($this->message)
            ;

            try {
                $sendgrid->send($email);
                self::printLog('  - Email sent');
            } catch(\SendGrid\Exception $e) {
                self::printLog($e->getCode());
                foreach($e->getErrors() as $er) {
                    self::printLog($er);
                }
            }

        } else {
            self::printLog(' - No email to send');
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

}
