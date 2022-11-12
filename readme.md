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
* `COUNTRY_CODE`: Set the two character country code if you want Ebay results to be targeted to a specific country
* `TO_ADDRESS`: the email in which to send the results
* `FROM_ADDRESS`: I am sure you know what this is for
* `EMAIL_SUBJECT`: The subject of the email that is sent
* `MMG_SUBJECT`: The subject for the email if checking MelloMusicGroup inventory
* `BANDCAMP_SUBJECT`: The subject for the email if checking Bandcamp inventory
* `BANDCAMP_SUBDOMAINS`: Array of subdomains to check on bandcamp
* `FATBEATS_SUBJECT`: The subject for the email if checking Fatbeats inventory
* `FATBEATS_LABELS`: Array of labels to check inventory for
* `SENDGRID_KEY`: Your sendgrid API Key
* `DEBUG`: If set to `true` the script will only run for 10% of your wantlist

### FatBeats
As of `2020-07-01`, I added support to keep track of Vinyl inventory on FatBeats for labels. It will only work with label collections. On first run you will get an email of all vinyl release, their price and if they are in stock or not.

Follow the `How to Run` section below.

### MelloMusicGroup
As of `2015-02-07`, I added support to keep track of Vinyl inventory on MelloMusicGroup. On first run you will get an email of all vinyl release, their price and if they are in stock or not.

After that it will keep track of the prices and inventory, then each time it is run it will see if any prices changes or inventory changed. If so it will only email the release that have changed in some way.

If a new release is posted it will also email you that. If you want to run this script just set up a new cron to run the script once a day. Follow the `How to Run` section below.

### Bandcamp
As of `2015-02-19`, I added support to keep track of Vinyl inventory on Bandcamp per artist. On first run you will get an email of all vinyl release, their price and if they are in stock or not.

The logic is the same as for MelloMusicGroup above. To configure bandcamp, just place the subdomain for the artist (IE: `j-zone`) in the array provided in the config sample. You can then just run `php bandcamp.php` and the script will do the rest.


### How to Run
* Update your `config.php` file
* Run `composer install`
* $ `php index.php`
* $ `php mellomusicgroup.php` -- If you want to keep track of MMG inventory
* $ `php bandcamp.php` -- If you want to keep track of Bandcamp artist inventory
* $ `php fatbeats.php` -- If you want to keep track of Fatbeats Label inventory
* $ `php get-random-release.php` -- If you want a random Discogs wantlist item returned to so you can purchase it

### My Setup
I have this running for myself on Google's Computer Engine (Micro Instance). It is set up as a cron to run twice a day. I also have a cron set up that once every 10 days it removes all the `Ebay-*.cache` files and starts fresh.

If will work locally as well. I decided for Compute Engine to make sure the cron fires since sometimes my computer is off.

### PHP Version
I have tested on `5.5.*` and `7.0.*`.
