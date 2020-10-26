<?php

include_once '_inc/simple_html_dom.php';

/**
 * Class Hemnet is a Hemnet scraper. Given location ID(s) (found by searching on
 * the Hemnet webpage) and a type (for sale or sold items) this class will
 * scrape the result. All items will be stored, if exact nubmers are desired,
 * pass them as a third argument to `scrape_hemnet`.
 */
class Hemnet
{
    /**
     * @var bool Strict mode for the class.
     */
    private $strict;


    /**
     * Hemnet constructor.
     * @param bool $strict Strict mode will die on errors.
     */
    function __construct($strict = FALSE)
    {
        $this->strict = $strict;
    }

    /**
     * The actual scraping by traversing the DOM with CSS selectors.
     *
     * @param array $args Mixed settings for what and how to scrape.
     * @return array List of objects from the scraped page
     */
    function scrape_hemnet($location_ids, $type, $exact_numbers)
    {
        $objects = [];
        $attributes = $this->get_attributes();

        if (!$attributes)
            return $objects;

        $location_id_string = join('&', array_map(function ($id) {
            return sprintf('location_ids[]=%d', $id);
        }, $location_ids));
        $hemnet_address = sprintf('http://www.hemnet.se/%sbostader?%s', $attributes['address-extra'][$type], $location_id_string);
        $hemnet_source = $this->get_html_source($hemnet_address);

        $dom = new simple_html_dom();
        $dom->load($hemnet_source);

        if (!$dom)
            return $objects;

        $i = 0;
        foreach ($dom->find($attributes['dom-classes'][$type]) as $item) {
            foreach ($attributes['data-classes'] as $key => $element) {
                if (!isset($element[$type]))
                    continue;

                // PHP Simple HTML DOM Parser does not support nth-child CSS
                // selectors so we must check if we given a specific index.
                $ci = array_key_exists(sprintf('%s-i', $type), $element) ? $element[sprintf('%s-i', $type)] : 0;
                $data = $item->find($element[$type], $ci);

                if (!$data) {
                    if ($key == 'sold-before-preview') {
                        $objects[$i][$key] = FALSE;
                        continue;
                    } elseif ($key == 'price-change') {
                        $objects[$i][$key] = '+/- 0%';
                        continue;
                    } elseif ($this->strict) {
                        die("DATA FOR '$key' MISSING: Could not find '$element[$type]'\n");
                    }
                }

                // Remove inner elements if data element contains children
                if (array_key_exists('remove', $element) && count($element['remove']) > 0) {
                    foreach ($element['remove'] as $remove_child) {
                        if ($data->find($remove_child, 0))
                            $data->find($remove_child, 0)->innertext = '';
                    }
                }

                $value = $data->plaintext;

                if ($key == 'url')
                    $value = $data->href;

                if ($key == 'image')
                    $value = $data->{'data-src'};


                // Non breaking space, isn't caught by \s or trim()...
                $breaking_space = urldecode("%C2%A0");
                $value = preg_replace("/$breaking_space/", ' ', $value);

                $value = preg_replace('/&nbsp;/', ' ', $value);
                $value = preg_replace('/\s{2,}/', ' ', $value);
                $value = preg_replace('/Begärt pris: /', '', $value);
                $value = preg_replace('/Såld /', '', $value);
                $value = preg_replace('/Slutpris /', '', $value);
                $value = preg_replace('/ rum/', '', $value);
                $value = preg_replace('/kr(\/m(²|ån))?/', '', $value);
                $value = preg_replace('/ m²/', '', $value);
                $value = preg_replace('/^\s+|\s+$/', '', $value);

                if ($key == 'sold-date')
                    $value = $this->format_date($value);

                if ($key == 'sold-before-preview')
                    $value = TRUE;

                // Sold properties stores living area and rooms in the same element so we extract them
                if ($key == 'size') {
                    preg_match('/^([\d,]+) ([\d,]+)/', $value, $size_info);

                    $objects[$i]['living-area'] = $size_info[1];
                    $objects[$i]['rooms'] = $size_info[2];

                    continue;
                }

                $objects[$i][$key] = $value;
            }

            $i++;
        }

        if (!count($exact_numbers))
            return $objects;

        $exact_matches = [];
        foreach ($objects as $obj) {
            foreach ($exact_numbers as $exact) {
                preg_match('/^(\D+) (\d+)\w?(,|$)/', $obj['address'], $address);

                if ($address[2] == $exact) {
                    $exact_matches[] = $obj;
                }
            }
        }

        return $exact_matches;
    }

    /**
     * Get the source code from a given URL by fetching it with curl.
     *
     * @param string $url The URL to fetch
     * @return string The source code
     */
    private function get_html_source($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_REFERER, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $source_code = curl_exec($curl);
        curl_close($curl);

        return $source_code;
    }

    /**
     * Format dates in ISO 8601 (ish) format from a string.
     *
     * @param string $date (optional) The date in format <day> <name-of-month> <year>
     * @return string ISO 8601 formatted date without timezone
     */
    private function format_date($date = '1 januari 1990')
    {
        $m = [
            'januari'   => 1,
            'februari'  => 2,
            'mars'      => 3,
            'april'     => 4,
            'maj'       => 5,
            'juni'      => 6,
            'juli'      => 7,
            'augusti'   => 8,
            'september' => 9,
            'oktober'   => 10,
            'november'  => 11,
            'december'  => 12,
        ];

        preg_match('/^(\d+) (\w+) (\d+)$/', $date, $dp);
        $formatted_date = sprintf('%d-%02d-%02d', $dp[3], $m[$dp[2]], $dp[1]);

        return $formatted_date;
    }


    /**
     * Get a map of how to fetch different attributes for different object types.
     *
     * @return array
     */
    private function get_attributes()
    {
        $class_map = [
            'dom-classes'   => [
                'sold'     => '.sold-results__normal-hit',
                'for-sale' => '.normal-results__hit',
            ],
            'address-extra' => [
                'sold'     => 'salda/',
                'for-sale' => null,
            ],
            'data-classes'  => [
                'address'             => [
                    'sold'     => '.item-result-meta-attribute-is-bold',
                    'for-sale' => '.listing-card__street-address',
                    'remove'   => [
                        'title', 'span',
                    ],
                ],
                'age'                 => [
                    'sold'     => null,
                    'for-sale' => null // TODO: This is not a part of the object but an item for each value.
                ],
                'price'               => [
                    'sold'       => '.sold-property-listing__price .sold-property-listing__subheading',
                    'for-sale'   => '.listing-card__attributes-row > .listing-card__attribute--primary',
                    'for-sale-i' => 0,
                ],
                'price-change'        => [
                    'sold'     => '.sold-property-listing__price-change',
                    'for-sale' => null,
                ],
                'fee'                 => [
                    'sold'     => '.sold-property-listing__fee',
                    'for-sale' => '.listing-card__attribute--fee',
                ],
                'size'                => [
                    'sold'     => '.sold-property-listing__size > .clear-children > .sold-property-listing__subheading',
                    'for-sale' => null,
                ],
                'living-area'         => [
                    'sold'       => null,
                    'for-sale'   => '.listing-card__attributes-row > .listing-card__attribute--primary',
                    'for-sale-i' => 1,
                ],
                'rooms'               => [
                    'sold'       => null,
                    'for-sale'   => '.listing-card__attributes-row > .listing-card__attribute--primary',
                    'for-sale-i' => 2,
                ],
                'price-per-m2'        => [
                    'sold'     => '.sold-property-listing__price-per-m2',
                    'for-sale' => '.listing-card__attribute--square-meter-price',
                ],
                'url'                 => [
                    'sold'     => '.item-link-container',
                    'for-sale' => '.js-listing-card-link',
                ],
                'image'               => [
                    'sold'     => null,
                    'for-sale' => null,
                ],
                'sold-date'           => [
                    'sold'     => '.sold-property-listing__sold-date',
                    'for-sale' => null,
                ],
                'sold-before-preview' => [
                    'sold'     => null,
                    'for-sale' => '.state-label-icon--removed_before_showing',
                ]
            ]
        ];

        return $class_map;
    }
}
