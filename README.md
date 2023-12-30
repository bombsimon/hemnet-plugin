# hemnet-plugin

Hemnet plugin for WordPress

## About

This is a test to fetch information from [Hemnet](https://www.hemnet.se) and put
in a widget on your WordPress blog. Since Hemnet does not have an open API this
code will fetch the source code for a search result and make som DOM walking.

> [!NOTE]
> This is the old kind of plugin that was used before the Gutenberg blocks. This
> means it will not work with new themes that doesn't support this. Since
> Gutenberg blocks are [written in
> JavaScript](https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/writing-your-first-block-type/)
> I do not plan to add support for this.

## Setup

Just put the folder hemnet in your plugins folder,
`your/wordpress/installation/wp-content/plugins/`.

## Usage

- Make a search for an address or an area on [hemnet.se](https://www.hemnet.se).
- When at at the search result page, check the last digits in the URL (should
  end with something like `location_ids%5B%5D=123456` or
  `location_ids[]=123456`).
- Copy those digits into the field for location IDs in the plugin settings.
- If you want to display results from several searches, repeat the two steps
  above and enter them with a comma between each number.
- Select if you want to display sold items or items for sale in the plugin
  settings.
- If you want to display results for specific numbers (i.e. you've search for
  "Kungsgatan" but only want to show Kungsgatan 88 and 90), fill those numbers
  separated with a comma (88,90).
- Select if you want to limit the search to a maximum number of results
- Click save

### Dependencies

This code _does not_ bundle the dependencies like some Wordpress plugins might
do. To ensure this will run in your Wordpress instance, ensure you have
[composer](https://getcomposer.org/) installed and run

```sh
composer install
```

## Disclaimer

Hemnet and [https://www.hemnet.se](hemnet.se) has nothing to do with this
plugin. I have not had any contact with them and made this plugin as a test to
see if there was an easy way to display information about apartments for sale
in my area.

## Development

- Setup [composer](https://getcomposer.org/) by running
  [`setup_composer.sh`](./seupt_composer.sh).
- Install dependencies with `php composer.phar install`
- Use the [`example.php`](./example.php) to test the Hemnet scraping
- Use [`docker-compose`](./docker-compose.yaml) to run Wordpress locally
- Check your code with `./vendor/bin/phpcs *.php -w`

The style guide is a combination of multiple ones found at
[this](https://stackoverflow.com/questions/45254784/when-should-i-use-camelcase-camel-case-or-underscores-in-php-naming)
StackOverflow post.

There's currently no bundle way to re-generate the `hemnet.pot` (and thus it is
very old). Although for manual changes the `po` files can be converted to `mo`
files with `msgfmt <languages/hemnet-sv_SE.po -o languages/hemnet-sv_SE.mo`.
