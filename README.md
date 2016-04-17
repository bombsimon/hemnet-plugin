# hemnet-plugin
Hemnet plugin for WordPress

## About
This is a test to fetch information from Hemnet (http://www.hemnet.se) and put in a widget on your WordPress blog. Since Hemnet does not have an open API this code will fetch the source code for a search result and make som DOM walking.

## Setup
Just put the folder hemnet in your plugins folder, `your/wordpress/installation/wp-content/plugins/`.

## Usage
* Make a search for an address or an area on http://www.hemnet.se.
* When at at the search result page, check the last digits in the URL (should end with something like `location_ids%5B%5D=123456` or `location_ids[]=123456`).
* Copy those digits into the field for location IDs in the plugin settings.
* If you want to display results from several searches, repeat the two steps above and enter them with a comma between each number.
* Select if you want to display sold items or items for sale in the plugin settings.
* If you want to display results for specific numbers (i.e. you've search for "Kungsgatan" but only want to show Kungsgatan 88 and 90), fill those numbers separated with a comma (88,90).
* Select if you want to limit the search to a maximum number of results
* Click save

## Disclaimer
Hemnet and http://www.hemnet.se has nothing to do with this plugin. I have not had any contact with them and made this plugin as a test to see if there was an easy way to display information about apartments for sale in my area.
