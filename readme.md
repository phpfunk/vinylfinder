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
* `MMG_SUBJECT`: The subject for the email if checking MelloMusicGroup inventory
* `SENDGRID_KEY`: Your sendgrid API Key
* `DEBUG`: If set to `true` the script will only run for 10% of your wantlist

### MelloMusicGroup
As of `2015-02-07`, I added support to keep track of Vinyl inventory on MelloMusicGroup. On first run you will get an email of all vinyl release, their price and if they are in stock or not.

After that it will keep track of the prices and inventory, then each time it is run it will see if any prices changes or inventory changed. If so it will only email the release that have changed in some way.

If a new release is posted it will also email you that. If you want to run this script just set up a new cron to run the script once a day. Follow the `How to Run` section below.


### How to Run
* Update your `config.php` file
* Run `composer install`
* $ `php index.php`
* $ `php mellomusicgroup.php` -- If you want to keep track of MMG inventory

### My Setup
I have this running for myself on Google's Computer Engine (Micro Instance). It is set up as a cron to run twice a day. I also have a cron set up that once every 10 days it removes all the `Ebay-*.cache` files and starts fresh.

If will work locally as well. I decided for Compute Engine to make sure the cron fires since sometimes my computer is off.

### PHP Version
I have tested on `5.5.27` and `7.0.2`.