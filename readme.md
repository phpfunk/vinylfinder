## VinylFinder
Generic name, naming things is hard. Anyway, this small package will get your Discogs wantlist and search Ebay for those titles. It's not perfect but does a pretty good job. It is set up to search the `Records` category on Ebay. You can customize this if you wish.

### How it works

* Extracts your wantlist from Discogs (caches for 24 hours)
* Figures out what to query on Ebay (if it's a 7" it will append ` 45` to a search), it also splits titles with ` / ` in them to look for A and B sides, again not perfect.
* It caches each ebay search to remember the last price per url of an item, if you have already seen that price, it won't send that item listing again
* If the price changes it will send it to you, I found this makes it more managable for large wantlists
* It emails you this list (using sendgrid, free 25K emails per month)

### Config File
* Copy `config.sample.php` to `config.php` and fill in the values
* `EBAY_APPID`: Your Ebay API Key
* `DISCOGS_PERSONAL_TOKEN`: Your personal discogs token
* `DISCOGS_USERNAME`: Yep, you guessed it, your discogs username
* `TO_ADDRESS`: the email in which to send the results
* `FROM_ADDRESS`: I am sure you know what this is for
* `EMAIL_SUBJECT`: The subject of the email that is sent
* `SENDGRID_KEY`: Your sendgrid API Key
* `DEBUG`: If set to `true` the script will only run for 10% of your wantlist

### How to Run
* Update your `config.php` file
* Run `composer install`
* $ `php index.php`

### My Setup
I have this running for myself on Google's Computer Engine (Micro Instance). It is set up as a cron to run twice a day. I also have a cron set up that once every 10 days it removes all the `Ebay-*.cache` files and starts fresh.

If will work locally as well. I decided for Compute Engine to make sure the cron fires since sometimes my computer is off.

### PHP Version
I have tested on `5.5.27` and `7.0.2`.