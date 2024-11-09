<?php

/**
 * Hemnet is a tool to fetch data from https://www.hemnet.se
 * php version 7
 *
 * @category Library
 * @package  Hemnet
 * @author   Simon Sawert <simon@sawert.se>
 * @license  https://opensource.org/license/mit/ MIT
 * @link     https://github.com/bombsimon/hemnet-plugin
 */

require_once "vendor/autoload.php";

use voku\helper\HtmlDomParser;

/**
 * Hemnet is a parser to parse Hemnet HTML DOM.
 */
class Hemnet
{
    /**
     * Hemnet is a plugin that fetches data from https://hemnet.se.
     */
    private int    $_cache_seconds;
    private int    $_last_fetched_sold;
    private int    $_last_fetched_for_sale;
    private string $_sold_dom;
    private string $_for_sale_dom;


    /**
     * Hemnet constructor.
     *
     * If you are using this in a static manner like showing listings for your
     * BRF with the Wordpress plugin you might want to cache the DOM. The
     * caching mechanism is very simple and doesn't not take exact numbers into
     * account. If you are calling listings for different locations you might
     * want to disable the cache (setting it to 0).
     *
     * @param int $cache_seconds How long to cache the HTML DOM for listings.
     */
    public function __construct(int $cache_seconds = 300)
    {
        $this->_cache_seconds = $cache_seconds;
        $this->_last_fetched_sold = 0;
        $this->_last_fetched_for_sale = 0;
    }

    /**
     * Get all listings for sale with the given location ids.
     *
     * This currently does not support pagination so if you expect more than the
     * single page result try to use fewer IDs.
     *
     * @param int[] $location_ids List of area IDs from the page.
     *
     * @return Listing[] List of objects from the scraped page
     */
    public function getListingsForSale(array $location_ids): array
    {
        $listings = [];

        $html = $this->_getHemnetSource($location_ids);
        $dom = HtmlDomParser::str_get_html($html);

        foreach ($dom->findMulti(".hcl-card") as $listing_card) {
            // Ads link to external pages and have a target set so we know we
            // can skip them.
            if ($listing_card->target) {
                continue;
            }

            $address = $listing_card->findOneOrFalse(".hcl-card__title");
            if (!$address) {
                continue;
            }

            $sale_data = $listing_card->findMultiOrFalse(
                ".hcl-grid--columns-4 > div"
            );
            if (!$sale_data) {
                continue;
            }

            $price       = $sale_data[0];
            $living_area = $sale_data[1];
            $rooms       = $sale_data[2];
            $floor       = count($sale_data) > 3 ? $sale_data[3] : null;
            $fee         = count($sale_data) > 4 ? $sale_data[4] : null;
            $price_psqm  = count($sale_data) > 5 ? $sale_data[5] : null;

            $listings[] = new Listing(
                sprintf("https://www.hemnet.se%s", $listing_card->href),
                $address->plaintext,
                $price ? $price->plaintext : null,
                $living_area ? $living_area->plaintext : null,
                $rooms ? $rooms->plaintext : null,
                $floor ? $floor->plaintext : null,
                $fee ? $fee->plaintext : null,
                $price_psqm ? $price_psqm->plaintext : null,
            );
        }

        return $listings;
    }

    /**
     * Get all listings sold with the given location ids.
     *
     * This currently does not support pagination so if you expect more than the
     * single page result try to use fewer IDs.
     *
     * @param int[] $location_ids List of area IDs from the page.
     *
     * @return Listing[] List of objects from the scraped page
     */
    public function getListingsSold(array $location_ids): array
    {
        $listings = [];

        $html = $this->_getHemnetSource($location_ids, "salda/");
        $dom = HtmlDomParser::str_get_html($html);

        foreach ($dom->findMulti(".hcl-card") as $listing_card) {
            // Ads link to external pages and have a target set so we know we
            // can skip them.
            if ($listing_card->target) {
                continue;
            }

            $address = $listing_card->findOneOrFalse(".hcl-card__title");
            if (!$address) {
                continue;
            }

            // There's no static naming of the contents holding the listing
            // information so it's a real mess to read this out from the DOM. We
            // simply have to just traverse the DOM from a starting point and
            // exit early.
            //
            // We're looking for two container elements. One holds size, rooms
            // and fee. The second one holds final price and price change.
            $container_data = $listing_card->findMultiOrFalse(
                ".hcl-flex--container"
            );

            // Ensure we got both of the expected elements.
            if (count($container_data) < 3) {
                continue;
            }

            // In the first node we expect the first child two be a div with
            // two p elements.
            $size_info = $container_data[1]->findMultiOrFalse("p");
            if (count($size_info) < 2) {
                continue;
            }

            // And the second element is simply a span with the fee info.
            $fee_info = $container_data[1]->findOneOrFalse("span");
            if (!$fee_info) {
                continue;
            }


            // The information about price and price change are in the first
            // node as two separate spans.
            $price_info = $container_data[3]->findMultiOrFalse("span");
            if (count($price_info) < 2) {
                continue;
            }

            // And the price per square meter is within a p node.
            $price_psqm_info = $container_data[3]->nextSibling();
            if (!$price_psqm_info) {
                continue;
            }

            $living_area  = $size_info[0];
            $rooms        = $size_info[1];
            $fee          = $fee_info;
            $price        = $price_info[0];
            $price_change = $price_info[1];
            $price_psqm   = $price_psqm_info;
            $sold_at      = $listing_card->findOneOrFalse(".hcl-label--sold-at");


            $listings[] = new Listing(
                sprintf("https://www.hemnet.se%s", $listing_card->href),
                $address->plaintext,
                $price ? $price->plaintext : null,
                $living_area ? $living_area->plaintext : null,
                $rooms ? $rooms->plaintext : null,
                null, // We don't know floor for sold items.
                $fee ? $fee->plaintext : null,
                $price_psqm ? $price_psqm->plaintext : null,
                $price_change ? $price_change->plaintext : null,
                $sold_at ? $sold_at->plaintext : null,
            );
        }

        return $listings;
    }

    /**
     * The actual scraping by traversing the DOM with CSS selectors.
     *
     * @param int[]  $location_ids List of area IDs from the page.
     * @param string $extra        Sold or For sale
     *
     * @return string DOM for the desired listing type.
     */
    private function _getHemnetSource(
        array $location_ids,
        string $extra = ""
    ): string {
        $last_fetched = $extra
            ? $this->_last_fetched_sold
            : $this->_last_fetched_for_sale;

        if (time() - $last_fetched < $this->_cache_seconds) {
            $cached_dom = $extra
                ? $this->_sold_dom
                : $this->_for_sale_dom;

            if (isset($cached_dom)) {
                return $cached_dom;
            }
        }

        $item_types = join(
            "&",
            array_map(
                function ($type) {
                    return sprintf("item_types[]=%s", $type);
                },
                ["villa", "radhus", "bostadsratt", "fritidshus"],
            ),
        );
        $location_id_string = join(
            "&",
            array_map(
                function ($id) {
                    return sprintf("location_ids[]=%d", $id);
                },
                $location_ids
            ),
        );
        $hemnet_address = sprintf(
            "https://www.hemnet.se/%sbostader?%s&%s",
            $extra,
            $item_types,
            $location_id_string,
        );

        if (getenv("HEMNET_DEBUG")) {
            echo "$hemnet_address\n";
        }

        $dom = $this->_getSource($hemnet_address);

        if ($extra) {
            $this->_last_fetched_sold = time();
            $this->_sold_dom = $dom;
        } else {
            $this->_last_fetched_for_sale = time();
            $this->_for_sale_dom = $dom;
        }

        return $dom;
    }

    /**
     * Get the source code from a URL.
     *
     * @param string $url The page to fetch source code for
     *
     * @return strin HTML DOM.
     */
    private function _getSource(string $url): string
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

        if (!$source_code) {
            $err = curl_error($curl);
            throw new Exception("Failed to get DOM: $err");
        }

        return $source_code;
    }
}

/**
 * Listing is a single listing item, for sale or sold, found at Hemnet.
 */
class Listing
{
    public string   $url;
    public string   $street;
    public int      $street_number;
    public string   $street_number_letter;
    public int      $price;
    public float    $living_area;
    public float    $living_bi_area;
    public float    $rooms;
    public float    $floor;
    public int      $fee;
    public int      $price_per_square_meter;
    public int      $price_change;
    public DateTime $sold_at;


    /**
     * Constructor of listing object.
     *
     * Most of the data passed is expected to not be sanitized and therefor the
     * constructor will do this. This includes things like splitting the address
     * to street, number and letter, parsing floor number and living area,
     * keeping only digits for numeric fields etc.
     *
     * @param string $url                    The URL to the listing
     * @param string $address                The address
     * @param string $price                  The price
     * @param string $living_area            The living area
     * @param string $rooms                  The number of rooms
     * @param string $floor                  The floor/level
     * @param string $fee                    The fee
     * @param string $price_per_square_meter The price per square meter
     * @param string $price_change           The change in price
     * @param string $sold_at                The date sold
     *
     * @return strin HTML DOM.
     */
    public function __construct(
        string $url,
        string $address,
        string $price,
        string $living_area,
        string $rooms,
        ?string $floor,
        ?string $fee,
        ?string $price_per_square_meter,
        ?string $price_change = null,
        ?string $sold_at = null,
    ) {
        [
            $_,
            $street,
            $street_number,
            $street_number_letter,
            $parsed_floor,
        ] = $this->_parseAddress($address);

        [
            $this->living_area,
            $this->living_bi_area,
        ] = $this->_parseLivingArea($living_area);

        if ($floor) {
            $this->floor = $this->_parseFloor($floor);
        } elseif ($parsed_floor) {
            $this->floor = floatval($parsed_floor);
        }

        if ($street_number) {
            $this->street = $street;
            $this->street_number = intval($street_number);
        } else {
            // Some addresses doesn"t have any number, it's just a street or
            // similar. If so just set the whole address as the street.
            $this->street = $address;
        }

        if ($street_number_letter) {
            $this->street_number_letter = $street_number_letter;
        }

        if ($price_change) {
            $this->price_change = _keepNumbers($price_change);
        }

        if ($sold_at) {
            $this->sold_at = $this->_parseSoldAt($sold_at);
        }

        $this->url                    = $url;
        $this->rooms                  = floatval($rooms);
        $this->price                  = _keepNumbers($price);
        $this->fee                    = _keepNumbers($fee);
        $this->price_per_square_meter = _keepNumbers($price_per_square_meter);
    }

    /**
     * Parse the address.
     *
     * @param string $address The original address
     *
     * @return mixed[] Array with [street, street_no, street_letter, floor]
     */
    private function _parseAddress(string $address): array
    {
        preg_match("/^(\D+) (\d+)?([A-Z])?(.*)$/", $address, $parsed);

        if (!count($parsed)) {
            return [null, $address, null, null, null];
        }

        if (!$parsed[4]) {
            return $parsed;
        }

        preg_match("/(?:Vån )?(\d+)(?:\s?tr)?/i", $parsed[4], $floor_matches);
        if (count($floor_matches)) {
            $parsed[4] = $floor_matches[1];
        }

        return $parsed;
    }

    /**
     * Parse the floor.
     *
     * @param string $floor The original floor string
     *
     * @return int The parsed floor
     *
     * This field tends to be very varying so we try to just keep the number
     * that actually represents the floor. f.ex this supports:
     *   - 8tr
     *   - 8/6
     *   - vån 8
     *   + Vån 8/10
     */
    private function _parseFloor(string $floor): int
    {
        preg_match("/^(?:vån )?-?(\d+)/i", $floor, $parsed_floor);
        if (!count($parsed_floor) > 1) {
            return null;
        }

        return floatval($parsed_floor[1]);
    }

    /**
     * Parse the living area.
     *
     * This field tends to be very varying so we try to just keep the number
     * that actually represents the floor. f.ex this supports:
     *   - 8tr
     *   - 8/6
     *   - vån 8
     *   + Vån 8/10
     *
     * @param string $living_area The original living area
     *
     * @return flaot[] with [living_area, bi_area]
     */
    private function _parseLivingArea(string $living_area): array
    {
        $living_area = preg_replace("/m²/", "", $living_area);
        $living_area = preg_replace("/,/", ".", $living_area);
        $living_area = preg_replace("/bv/i", "0", $living_area);

        $areas = explode("+", $living_area);
        if (count($areas) == 1) {
            return [floatval($areas[0]), 0.0];
        }

        return [floatval($areas[0]), floatval($areas[1])];
    }

    /**
     * Parse the sold date.
     *
     * Sold date is in an arbitrary Swedish format but we want to use a proper
     * ISO formatted date.
     *
     * @param string $sold_at The original sold at string
     *
     * @return DateTime Object with 00:00 timestamp and no timezone.
     */
    private function _parseSoldAt(string $sold_at): DateTime
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

        preg_match('/^Såld (\d+) (\w+). (\d+)$/', $sold_at, $sold_at_parts);
        $formatted_date = sprintf(
            '%d-%02d-%02d',
            $sold_at_parts[3],
            $m[$sold_at_parts[2]],
            $sold_at_parts[1],
        );

        return new DateTime($formatted_date);
    }

    /**
     * Get the address.
     *
     * This will use all available data from street, street number and street
     * number letter and return them in a consistent way.
     *
     * @return string Address with street, street number and street letter.
     */
    public function address(): string
    {
        $address = $this->street;

        if (isset($this->street_number)) {
            $address .= " " . $this->street_number;
        }

        if (isset($this->street_number_letter)) {
            $address .= $this->street_number_letter;
        }

        return $address;
    }

    /**
     * Formatted price with delimited number and suffix.
     *
     * @return string Formatted price.
     */
    public function formattedPrice(): string
    {
        return sprintf("%s kr", _formatNumber($this->price));
    }

    /**
     * Formatted price per square meter with delimited number and suffix.
     *
     * @return string Formatted price per square meter.
     */
    public function formattedPricePerSquareMeter(): string
    {
        return sprintf("%s kr/m²", _formatNumber($this->price_per_square_meter));
    }

    /**
     * Formatted fee with delimited number and suffix.
     *
     * @return string Formatted fee.
     */
    public function formattedFee(): string
    {
        return sprintf("%s kr", _formatNumber($this->fee));
    }

    /**
     * Formatted living area with suffix. Will include both living area and bi
     * area if any.
     *
     * @return string Formatted living area.
     */
    public function formattedLivingArea(): string
    {
        $bi_area = "";
        if (isset($this->living_bi_area) && $this->living_bi_area) {
            $bi_area = sprintf(" + %sm²", $this->living_bi_area);
        }

        return sprintf("%sm²%s ", $this->living_area, $bi_area);
    }

    /**
     * Formatted price change with suffix.
     *
     * @return string Formatted price change.
     */
    public function formattedPriceChange(): string
    {
        return sprintf("%d%%", _formatNumber($this->price_change));
    }
}

/**
 * Strip everything by numbers from a string.
 *
 * This is mostly here to make it clear in the code what we do even though it's
 * a single line of a `preg_replace`.
 *
 * @param $str The string containing some digits.
 *
 * @return int The integer value when only numbers are kept.
 */
function _keepNumbers($str): int
{
    return intval(preg_replace('/[^0-9-]+/', '', $str));
}

/**
 * Format number to a space delimited string and no decimals.
 *
 * @param int $number The number to format as a string
 *
 * @return string Pretty formatted string.
 */
function _formatNumber($number): string
{
    return number_format($number, 0, ',', ' ');
}

/**
 * Filter exact numbers from listing results. Will filter _in_ (keep) addresses with
 * the passed numbers.
 *
 * @param Listing[] $listings        The listings to filter
 * @param int[]     $include_numbers The numbers to include
 *
 * @return Listing[] Listings matching the exact numbers.
 */
function filterExactNumbers(array $listings, array $include_numbers): array
{
    return array_values(
        array_filter(
            $listings,
            function ($listing) use ($include_numbers) {
                if (!isset($listing->street_number)) {
                    return false;
                }

                return in_array($listing->street_number, $include_numbers);
            },
        )
    );
}
