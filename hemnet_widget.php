<?php
/**
 * Widget around Hemnet class to fetch listings for sale or sold.
 * php version 7
 *
 * @category Wordpres_Plugin
 * @package  Hemnet
 * @author   Simon Sawert <simon@sawert.se>
 * @license  https://opensource.org/license/mit/ MIT
 * @link     https://github.com/bombsimon/hemnet-plugin
 */


/*
Plugin Name:    Hemnet
Plugin URI:     https://github.com/bombsimon/hemnet-plugin
Description:    Scrape information from Hemnet
Author:         Simon Sawert
Version:        0.4.0
Author URI:     http://sawert.se
License:        MIT OR GPL3
License URI     https://www.gnu.org/licenses/gpl-3.0.html
License URI:    https://opensource.org/licenses/MIT
Domain Path:    /languages
Text Domain:    hemnet
 */

require_once "hemnet.php";

if (!defined("ABSPATH")) {
    die(-1);
}

add_action(
    "widgets_init",
    function () {
        wp_enqueue_style('hemnet', plugins_url("static/hemnet.css", __FILE__));
        register_widget('HemnetWidget');
    }
);

load_plugin_textdomain(
    'hemnet',
    false,
    dirname(plugin_basename(__FILE__)) . "/languages/"
);

/**
 * Available listing types.
 */
enum ListingType: string
{
    case ForSale = "for-sale";
    case Sold    = "sold";
}

// phpcs:ignore
/**
 * HemnetWidget extends the WP_Wdiget to serve the Hemnet widget.
 */
class HemnetWidget extends WP_Widget
{
    private Hemnet $_hemnet;

    /**
     * Construct the plugin by setting a name and description.
     */
    public function __construct()
    {
        $this->_hemnet = new Hemnet();

        parent::__construct(
            "Hemnet",
            __("Hemnet", "hemnet"),
            array(
                "description" => __("Fetch listings from Hemnet", "hemnet")
            )
        );
    }


    /**
     * Implement the widget method from WP_Widget by rendering HTML.
     *
     * @param mixed[] $args     Arguments to the widget
     * @param mixed[] $instance Settings for the widget instance
     *
     * @return void
     */
    public function widget($args, $instance)
    {
        $location_ids = explode(",", $instance["location_ids"]);
        $exact_numbers = $instance["exact_numbers"]
            ? explode(",", $instance["exact_numbers"])
            : [];

        $hemnet_result = $instance["type"] == "sold"
            ? $this->_hemnet->getListingsSold($location_ids)
            : $this->_hemnet->getListingsForSale($location_ids);

        if (count($exact_numbers)) {
            $hemnet_result = filterExactNumbers($hemnet_result, $exact_numbers);
        }

        $type = $instance["type"] == "for-sale"
            ? ListingType::ForSale
            : ListingType::Sold;

        $empty_text = [
            "for-sale" => __(
                "There are no objects for sale at the moment.",
                "hemnet",
            ),
            "sold" => __(
                "There are no sold objects at the moment.",
                "hemnet",
            )
        ];

        echo $args["before_widget"];

        if (!empty($instance["title"])) {
            echo $args["before_title"];
            echo apply_filters("widget_title", $instance["title"]);
            echo $args["after_title"];
        }


        if (!count($hemnet_result)) {
            echo '<div class="estate">' . $empty_text[$type->value] . '</div>';
        }

        if ($instance["max_results"]) {
            $hemnet_result = array_slice(
                $hemnet_result,
                0,
                $instance["max_results"],
            );
        }

        foreach ($hemnet_result as $estate) {
            echo '<div class="estates">';

            $show_date_after = '';
            if ($instance["date_after_address"] && $type == ListingType::Sold) {
                $show_date_after = sprintf(
                    ' <small>(%s)</small>',
                    $estate->sold_at->format("Y-m-d"),
                );
            }

            printf(
                '<p class="estate address"><a href="%s" target="_blank">%s</a>%s</p>', // phpcs:ignore
                $estate->url,
                $estate->address(),
                $show_date_after,
            );

            if ($type == ListingType::Sold && !$instance["date_after_address"]) {
                printf(
                    '<p class="estate sold-date">%s %s</p>',
                    _x("Sold", "Displayed before date", "hemnet"),
                    $estate->sold_at->format("Y-m-d"),
                );
            }

            printf(
                '<p class="estate living-area">%s - %.0f %s</p>',
                $estate->formattedLivingArea(),
                $estate->rooms,
                _n("room", "rooms", $estate->rooms, "hemnet"),
            );
            printf(
                '<p class="estate fee">%s/%s</p>',
                $estate->formattedFee(),
                __("month", "hemnet"),
            );

            $price_per_square_meter = "";
            if ($instance["show_ppm2"]) {
                $price_per_square_meter = sprintf(
                    " (%s)",
                    $estate->formattedPricePerSquareMeter(),
                );
            }

            if ($estate->price) {
                printf(
                    '<p class="estate price">%s%s</p>',
                    $estate->formattedPrice(),
                    $price_per_square_meter,
                );
            } else {
                // TODO: No price information.
                echo __("No price information.", "hemnet");
            }

            if ($type == ListingType::Sold && $instnace["show_increase"]) {
                printf(
                    '<p class="estate price-change">%s %s</pre>',
                    __("Price change", "hemnet"),
                    $estate->formattedPriceChange(),
                );
            }

            echo '</div>';
        }

        echo $args["after_widget"];
    }


    /**
     * Implement the form method for WP_Widget which will allow custom
     * configuration.
     *
     * @param mixed[] $instance Current settings for the widget instance.
     *
     * @return void
     */
    public function form($instance)
    {
        foreach ($this->_settings() as $setting => $data) {
            $setting_value = isset($instance[$setting])
                ? $instance[$setting]
                : $data["default-value"];

            if ($setting_value) {
                printf('<p><strong>%s</strong></p>', $data["header"]);
            }

            echo '<p>';
            if ($data["type"] == "text") {
                printf(
                    '<label for="%s">%s</label>',
                    $this->get_field_id($setting),
                    $data["title"],
                );
                printf(
                    '<input class="widefat" id="%s" name="%s" type="text" value="%s">', // phpcs:ignore
                    $this->get_field_id($setting),
                    $this->get_field_name($setting),
                    esc_attr($setting_value),
                );
            } elseif ($data["type"] == "select") {
                printf(
                    '<label for="%s">%s</label>',
                    $this->get_field_id($setting),
                    $data["title"],
                );
                printf(
                    '<select class="widefat" id="%s" name="%s">',
                    $this->get_field_id($setting),
                    $this->get_field_name($setting),
                );

                foreach ($data["options"] as $option => $option_value) {
                    $selected = $setting_value == $option ? "selected" : "";
                    printf(
                        '<option value="%s" %s>%s</option>',
                        $option,
                        $selected,
                        $option_value,
                    );
                }

                printf('</select>');
            } elseif ($data["type"] == "checkbox") {
                $is_checked = $setting_value ? "checked" : "";
                printf(
                    '<input id="%s" name="%s" type="checkbox" value="1" %s>',
                    $this->get_field_id($setting),
                    $this->get_field_name($setting),
                    $is_checked,
                );
                printf(
                    '<label for="%s">%s</label>',
                    $this->get_field_id($setting),
                    $data['title'],
                );
            }

            if ($data["description"]) {
                printf('<small>%s</small>', $data['description']);
            }

            echo '</p>';
        }
    }


    /**
     * Implement the update method for WP_Widget which will update the custom
     * configuration.
     *
     * @param mixed[] $new_instance The new configuration
     * @param mixed[] $old_instance The old configuration
     *
     * @return string[] The new configuration
     */
    public function update($new_instance, $old_instance)
    {
        $instance = [];

        foreach ($this->_settings() as $setting => $data) {
            $instance[$setting] = (!empty($new_instance[$setting]))
                ? strip_tags($new_instance[$setting])
                : '';
        }

        return $instance;
    }


    /**
     * Key-value definition of available settings to automatically render the
     * settings panel for the widget.
     *
     * @return mixed[]
     */
    private function _settings()
    {
        // phpcs:disable
        $settings = [
            "title"              => [
                "title"         => __("Title:", "hemnet"),
                "default-value" => "Hemnet",
                "type"          => "text",
            ],
            "type"               => [
                "title"         => __("Type:", "hemnet"),
                "default-value" => "for-sale",
                "type"          => "select",
                "options"       => [
                    "for-sale" => __("For sale", "hemnet"),
                    "sold"     => _x("Sold", "In settings dropdown", "hemnet"),
                ]
            ],
            "location_ids"       => [
                "title"         => __("Location ID's:", "hemnet"),
                "default-value" => "882639",
                "type"          => "text",
                "description"   => __('Comma separated list of "location_ids". Search your desired location and copy the last number from the URL from Hemnet.', "hemnet"),
            ],
            "exact_numbers"      => [
                "title"       => __("Exact numbers:", "hemnet"),
                "type"        => "text",
                "description" => __("Comma separated list of specific numbers for a given address. Only use this with ONE location ID.", "hemnet"),
            ],
            "max_results"        => [
                "title"         => __("Max results:", "hemnet"),
                "default-value" => "10",
                "type"          => "text",
            ],
            "show_increase"      => [
                "title"         => __("Display price change (only for sold)", "hemnet"),
                "default-value" => "1",
                "type"          => "checkbox",
                "header"        => __("Formatting", "hemnet"),
            ],
            "date_after_address" => [
                "title" => __("Display date after address (only for sold)", "hemnet"),
                "type"  => "checkbox",
            ],
            "show_ppm2"          => [
                "title"         => __("Display price per m2", "hemnet"),
                "default-value" => "1",
                "type"          => "checkbox",
            ],
        ];
        // phpcs:enable

        return $settings;
    }
}
