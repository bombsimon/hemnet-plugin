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
    public function __construct($strict = false)
    {
        $this->strict = $strict;
    }

    /**
     * The actual scraping by traversing the DOM with CSS selectors.
     *
     * @param array $args Mixed settings for what and how to scrape.
     * @return array List of objects from the scraped page
     */
    public function scrape_hemnet($location_ids, $type, $exact_numbers)
    {
        $objects = [];
        $attributes = $this->get_attributes();

        if (!$attributes) {
            return $objects;
        }

        $location_id_string = join('&', array_map(function ($id) {
            return sprintf('location_ids[]=%d', $id);
        }, $location_ids));
        $hemnet_address = sprintf('http://www.hemnet.se/%sbostader?%s', $attributes['address-extra'][$type], $location_id_string);
        $hemnet_source = $this->get_html_source($hemnet_address);

        $dom = new simple_html_dom();
        $dom->load($hemnet_source);

        if (!$dom) {
            return $objects;
        }

        $i = 0;
        foreach ($dom->find($attributes['dom-classes'][$type]) as $item) {
            // Ads link to external pages so skip those.
            if ($item->target) {
                continue;
            }

            // Carousel at the top doesn't have an address so skip those.
            if (!$item->find($attributes['data-classes']['address'][$type])) {
                continue;
            }

            // Skip highlights.
            if ($item->class && str_contains($item->class, "hcl-card--highlighted")) {
                continue;
            }

            foreach ($attributes['data-classes'] as $key => $element) {
                if (!isset($element[$type])) {
                    continue;
                }

                if (!array_key_exists($i, $objects)) {
                    $objects[$i] = [];
                }

                // PHP Simple HTML DOM Parser does not support nth-child CSS
                // selectors so we must check if we given a specific index.
                $ci = array_key_exists(sprintf('%s-i', $type), $element) ? $element[sprintf('%s-i', $type)] : 0;
                $data = $item->find($element[$type], $ci);

                if (!$data) {
                    if ($key == 'sold-before-preview') {
                        $objects[$i][$key] = false;
                        continue;
                    } elseif ($key == 'price-change') {
                        $objects[$i][$key] = '+/- 0%';
                        continue;
                    } elseif ($this->strict) {
                        die("DATA FOR '$key' MISSING: Could not find '$element[$type]'\n");
                    } else {
                        continue;
                    }
                }


                // Remove inner elements if data element contains children
                if (array_key_exists('remove', $element) && count($element['remove']) > 0) {
                    foreach ($element['remove'] as $remove_child) {
                        if ($data->find($remove_child, 0)) {
                            $data->find($remove_child, 0)->innertext = '';
                        }
                    }
                }

                $value = $data->plaintext;

                if ($key == 'url') {
                    $value = $data->href;
                }

                if ($key == 'image') {
                    $value = $data->{'data-src'};
                }


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

                if ($key == 'sold-date') {
                    $value = $this->format_date($value);
                }

                if ($key == 'sold-before-preview') {
                    $value = true;
                }

                // Sold properties stores living area and rooms in the same
                // element so we extract them
                if ($key == 'size') {
                    preg_match('/^([\d,]+) ([\d,]+)/', $value, $size_info);

                    if (count($size_info) >= 3) {
                        $objects[$i]['living-area'] = $size_info[1];
                        $objects[$i]['rooms'] = $size_info[2];
                    }

                    continue;
                }

                $objects[$i][$key] = $value;
            }

            $objects[$i]['url'] = sprintf("https://www.hemnet.se%s", $item->href);
            $i++;
        }

        if (!count($exact_numbers)) {
            return $objects;
        }

        $exact_matches = [];
        foreach ($objects as $obj) {
            foreach ($exact_numbers as $exact) {
                preg_match('/^(\D+) (\d+)\w?([, ]|$)/', $obj['address'], $address);

                if (count($address) >= 3 && $address[2] == $exact) {
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
            'jan' => 1,
            'feb' => 2,
            'mar' => 3,
            'apr' => 4,
            'maj' => 5,
            'jun' => 6,
            'jul' => 7,
            'aug' => 8,
            'sep' => 9,
            'okt' => 10,
            'nov' => 11,
            'dec' => 12,
        ];

        preg_match('/^(\d+) (\w+). (\d+)$/', $date, $dp);
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
                'sold'     => '.hcl-card',
                'for-sale' => '.hcl-card',
            ],
            'address-extra' => [
                'sold'     => 'salda/',
                'for-sale' => null,
            ],
            'data-classes'  => [
                'address'             => [
                    'sold'     => '.hcl-card__title',
                    'for-sale' => '.hcl-card__title',
                ],
                'price'               => [
                    'sold'       => '.hcl-text',
                    'for-sale'   => '.hcl-grid--columns-4 > div',
                    'sold-i'     => 3,
                    'for-sale-i' => 0,
                ],
                'living-area'         => [
                    'sold'       => '.hcl-text',
                    'for-sale'   => '.hcl-grid--columns-4 > div',
                    'sold-i'     => 0,
                    'for-sale-i' => 1,
                ],
                'rooms'               => [
                    'sold'       => '.hcl-text',
                    'for-sale'   => '.hcl-grid--columns-4 > div',
                    'sold-i'     => 1,
                    'for-sale-i' => 2,
                ],
                'floor'               => [
                    'sold'       => null,
                    'for-sale'   => '.hcl-grid--columns-4 > div',
                    'for-sale-i' => 3,
                ],
                'fee'                 => [
                    'sold'       => '.hcl-text',
                    'for-sale'   => '.hcl-grid--columns-4 > div',
                    'sold-i'     => 2,
                    'for-sale-i' => 4,
                ],
                'price-per-m2'        => [
                    'sold'       => '.hcl-text',
                    'for-sale'   => '.hcl-grid--columns-4 > div',
                    'sold-i'     => 5,
                    'for-sale-i' => 5,
                ],
                'price-change'        => [
                    'sold'     => '.hcl-text',
                    'for-sale' => null,
                    'sold-i'   => 4,
                ],
                'sold-date'           => [
                    'sold'     => '.hcl-label--sold-at',
                    'for-sale' => null,
                ],
            ]
        ];

        return $class_map;
    }
}
