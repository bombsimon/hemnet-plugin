<?php
/*
Plugin Name:    Hemnet
Plugin URI:     https://github.com/bombsimon/hemnet-plugin
Description:    Scrape information from Hemnet
Author:         Simon Sawert
Version:        0.1.0
Author URI:     http://sawert.se
License:        GPL3
License URI:    https://www.gnu.org/licenses/gpl-3.0.html
Domain Path:    /languages
Text Domain:    hemnet
 */

include_once '_inc/simple_html_dom.php';

// Block direct requests
if (!defined('ABSPATH'))
    die('-1');


add_action('widgets_init', function() {
    wp_enqueue_style('hemnet', plugins_url('_inc/style.css', __FILE__));
    register_widget('Hemnet');
});

load_plugin_textdomain('hemnet', FALSE, dirname(plugin_basename(__FILE__)) . '/languages/');

class Hemnet extends WP_Widget {
    function __construct() {
        parent::__construct(
            'Hemnet',
            __( 'Hemnet', 'hemnet' ),
            array(
                'description' => __( 'Scrape real estates from Hemnet', 'hemnet' )
            )
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']). $args['after_title'];
        }

        $hemnet_result = $this->scrape_hemnet($instance);

        $empty_text = array(
            'for-sale'  => __( 'There are no objects for sale at the moment.', 'hemnet' ),
            'sold'      => __( 'There are no sold objects at the moment.', 'hemnet' )
        );

        if (!count($hemnet_result)) {
            echo '<div class="estate">' . $empty_text[$instance['type']] . '</div>';
        }

        $i = 1;
        foreach ($hemnet_result as $estate) {
            echo '<div class="estates">';

            $show_date_after = '';
            if ( $instance['date_after_address'] && $instance['type'] == 'sold' ) {
                $show_date_after = sprintf(' <small>(%s)</small>', $estate['sold-date']);
            }

            if ($estate['deactivated-before-open-house-day']) {
                printf('<p class="estate address"><strong>%s%s</strong></p>', $estate['address'], $show_date_after);
                printf('<p class="estate sold"><small>%s</small></p>', __( 'Sold before preview', 'hemnet' ));
            } else {
                printf('<p class="estate address"><a href="%s" target="_blank">%s</a>%s</p>', $estate['item-link-container'], $estate['address'], $show_date_after);
            }

            if ($instance['type'] == 'sold' && ! $instance['date_after_address']) {
                printf( '<p class="estate sold-date">%s %s</p>', __( 'Sold', 'hemnet' ), $estate['sold-date'] );
            }

            printf('<p class="estate living-area">%s</p>', $estate['living-area']);
            printf('<p class="estate fee">%s</p>', $estate['fee']);

            if ($estate['price-per-m2'] && $instance['show_ppm2'] ) {
                printf('<p class="estate price">%s (%s %s)</p>', $estate['price'], $estate['price-per-m2'], __( 'kr/m²', 'hemnet' ));
            } else {
                // Might include "No price" information
                printf('<p class="estate price">%s</p>', $estate['price']);
            }

            if ($instance['type'] == 'sold') {
                if ( $instance['show_increase'] ) {
                    printf('<p class="estate price-change">%s %s</pre>', __( 'Price increase', 'hemnet' ), $estate['price-change']);
                }
            }

            echo '</div>';

            if ($instance['max_results']) {
                if ($i == $instance['max_results'])
                    break;
            }

            $i++;
        }

        echo $args['after_widget'];
    }

    public function form ($instance) {
        $current_values = array();

        foreach ($this->available_settings() as $setting => $default_value) {
            $current_values[$setting] = $this->defined_or_fallback( $instance[$setting], $default_value );
        }
?>

        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'hemnet' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $current_values['title'] ); ?>">
        </p>

        <p>
            <label for="<?php echo $this->get_field_id( 'type' ); ?>"><?php _e( 'Type:', 'hemnet' ); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id( 'type' ); ?>" name="<?php echo $this->get_field_name( 'type' ); ?>">
                <option value="for-sale" <?php echo $current_values['type'] == 'for-sale' ? 'selected' : '' ?>><?php echo __( 'For sale', 'hemnet' ) ?></option>
                <option value="sold" <?php echo $current_values['type'] == 'sold' ? 'selected' : '' ?>><?php echo __( 'Sold', 'hemnet' ) ?></option>
            </select>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id( 'location_ids' ); ?>"><?php _e( 'Location ID\'s:', 'hemnet' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'location_ids' ); ?>" name="<?php echo $this->get_field_name( 'location_ids' ); ?>" type="text" value="<?php echo esc_attr( $current_values['location_ids'] ); ?>">
            <small><?php echo __( 'Comma separated list of "location_ids". Search your desired location and copy the last number from the URL from Hemnet.', 'hemnet' ) ?></small>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id( 'exact_numbers' ); ?>"><?php _e( 'Exact numbers:', 'hemnet' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'exact_numbers' ); ?>" name="<?php echo $this->get_field_name( 'exact_numbers' ); ?>" type="text" value="<?php echo esc_attr( $current_values['exact_numbers'] ); ?>">
            <small><?php echo __( 'Comma separated list of specific numbers for a given address. Only use this with ONE location ID.', 'hemnet' ) ?></small>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id( 'max_results' ); ?>"><?php _e( 'Max results:', 'hemnet' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'max_results' ); ?>" name="<?php echo $this->get_field_name( 'max_results' ); ?>" type="text" value="<?php echo esc_attr( $current_values['max_results'] ); ?>">
        </p>

        <p>
            <strong><?php _e( 'Formatting', 'hemnet' ) ?></strong>
        </p>

        <p>
            <?php $increase_checked = $current_values['show_increase'] ? 'checked' : ''; ?>
            <input id="<?php echo $this->get_field_id( 'show_increase'); ?>" name="<?php echo $this->get_field_name( 'show_increase' ); ?>" type="checkbox" value="1" <?php echo $increase_checked ?>>
            <label for="<?php echo $this->get_field_id( 'show_increase' ); ?>"><?php _e( 'Display price increase (only for sold)', 'hemnet' ) ?></label>
        </p>

        <p>
            <?php $date_after_checked = $current_values['date_after_address'] ? 'checked' : ''; ?>
            <input id="<?php echo $this->get_field_id( 'date_after_address'); ?>" name="<?php echo $this->get_field_name( 'date_after_address' ); ?>" type="checkbox" value="1" <?php echo $date_after_checked ?>>
            <label for="<?php echo $this->get_field_id( 'date_after_address' ); ?>"><?php _e( 'Display date after address (only for sold)', 'hemnet' ) ?></label>
        </p>

        <p>
            <?php $ppm2_checked = $current_values['show_ppm2'] ? 'checked' : ''; ?>
            <input id="<?php echo $this->get_field_id( 'show_ppm2'); ?>" name="<?php echo $this->get_field_name( 'show_ppm2' ); ?>" type="checkbox" value="1" <?php echo $ppm2_checked ?>>
            <label for="<?php echo $this->get_field_id( 'show_ppm2' ); ?>"><?php _e( 'Display price per m2', 'hemnet' ) ?></label>
        </p>

<?php

    }

    public function update($new_instance, $old_instance) {
        $instance = array();

        foreach ($this->available_settings() as $setting => $default_value) {
            $instance[$setting] = (!empty($new_instance[$setting])) ? strip_tags($new_instance[$setting]) : '';
        }

        return $instance;
    }

    private function defined_or_fallback ( $defined, $fallback = '') {
        return isset($defined) ? $defined : $fallback;
    }

    private function available_settings () {
        $settings = [
            'title'              => 'Hemnet',
            'type'               => 'for-sale',
            'location_ids'       => '882639',
            'exact_numbers'      => '',
            'max_results'        => '10',
            'show_increase'      => '1',
            'date_after_address' => '',
            'show_ppm2'          => '1',
        ];

        return $settings;
    }

    private function scrape_hemnet ($args) {
        // Location IDs @ hemnet.se - Search for your location and copy the last number/ID in the URL
        // Rembemer to be as specific as possible, this will only show the first 50 results (first result page on hemnet.se)
        $location_ids = split(',', $args['location_ids']);

        // Exact match, only match these numbers from the results (hemnet.se does not support specific numbers so we just filter everything else out)
        // This should not be used when using multiple location IDs since we don't know which of the addresses the number should be fixed for
        $exact_numbers = $args['exact_numbers'] ? split(',', $args['exact_numbers']) : [];

        // Object result placeholder
        $objects = [];

        // Attributes to fetch from each result object based on sold or for sale search
        $attributes = $this->get_attributes_by_type($args['type']);

        // Fallback for errors
        if (!$attributes)
            return $objects;

        // Add extra path for sold items in the URL
        $address_extra = $args['type'] == 'sold' ? 'salda/' : '';

        // The Hemnet address
        $hemnet_address = sprintf('http://www.hemnet.se/%sbostader?%s', $address_extra, join('&', array_map(function($id) { return sprintf('location_ids[]=%d', $id); }, $location_ids)));

        // Get DOM from Hemnet - supress warnings because reasons...
        $dom = @file_get_html($hemnet_address);

        // Return empty object list if request fails
        if (!$dom)
            return $objects;

        foreach ($dom->find('.result .normal') as $item) {
            foreach ($attributes as $class) {
                $data = $item->find('.' . $class, 0);

                // Get plaintext except for URLs
                $value = $data->plaintext;

                // Get href if we're looking for URL
                // The reuslt list for sold items contain full link, the list for items for sale does not...
                if ($class == 'item-link-container')
                    $value = sprintf('%s%s', $args['type'] == 'for-sale' ? 'http://www.hemnet.se' : '', $data->href);

                // Get data-src if we're looking for image
                if ($class == 'property-image')
                    $value = $data->{'data-src'};

                // Some text cleanup...
                $value = preg_replace('/&nbsp;/', '', $value);
                $value = preg_replace('/^\s+|\s+$/', '', $value);
                $value = preg_replace('/\s{2,}/', ' ', $value);

                // And remove hard coded pre- and postfixes
                $value = preg_replace('/Begärt pris: /', '', $value);
                $value = preg_replace('/Såld /', '', $value);
                $value = preg_replace('/Slutpris /', '', $value);
                $value = preg_replace('/ kr\/m²/', '', $value);

                // Cleanup class names
                $class = preg_replace('/ribbon--/', '', $class);

                $objects[$i][$class] = $value;
            }

            $i++;
        }

        // Return all (max 50) matches if no number filter is set
        if (!count($exact_numbers))
            return $objects;

        // Fiter exact matches for specific numbers, still from the max 50 results
        $final = [];
        foreach ($objects as $obj) {
            foreach ($exact_numbers as $exact) {
                // Sadly there are no standard for addresses so this should match what's the street number in the string
                preg_match('/^(\D+) (\d+)\w?(,|$)/', $obj['address'], $address);

                if ($address[2] == $exact) {
                    $final[] = $obj;
                }
            }
        }

        return $final;
    }

    private function get_attributes_by_type ($type = 'for-sale') {
        $type_map = array(
            'for-sale' => [
                'age', 'price', 'fee',
                'area', 'city', 'address',
                'living-area', 'price-per-m2',
                'item-link-container', 'property-image',
                'ribbon--deactivated-before-open-house-day'
            ],
            'sold' => [
                'sold-date', 'price', 'price-per-m2',
                'fee', 'asked-price', 'price-change',
                'address', 'area', 'living-area',
                'item-link-container'
            ]
        );

        return $type_map[$type];
    }
}
