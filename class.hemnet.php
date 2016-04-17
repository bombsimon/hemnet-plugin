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

class Hemnet extends WP_Widget {
    function __construct() {
        parent::__construct(
            'Hemnet',
            __('Hemnet', 'hemnet'),
            array(
                'description' => __('Scrape real estates from Hemnet', 'hemnet')
            )
        );
    }

    public function widget( $args, $instance ) {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
        }

        $hemnet_result = $this->scrape_hemnet($instance);

        $empty_text = array(
            'for-sale'  => __('Det finns inga object till salu för tillfället.', 'hemnet'),
            'sold'      => __('Det finns inga sålda objekt för tillfället.', 'hemnet')
        );

        if (!count($hemnet_result)) {
            echo '<div class="estate">' . $empty_text[$instance['type']] . '</div>';
        }

        $i = 1;
        foreach ($hemnet_result as $estate) {
            echo '<div class="estates">';
            echo sprintf('<p class="estate address"><a href="%s" target="_blank">%s</a></p>', $estate['item-link-container'], $estate['address']);

            if ($instance['type'] == 'sold') {
                echo sprintf('<p class="estate sold-date">%s %s</p>', __('Såld', 'hemnet'), $estate['sold-date']);
            }

            echo sprintf('<p class="estate living-area">%s</p>', $estate['living-area']);
            echo sprintf('<p class="estate fee">%s</p>', $estate['fee']);

            if ($estate['price-per-m2']) {
                echo sprintf('<p class="estate price">%s (%s)</p>', $estate['price'], $estate['price-per-m2']);
            } else {
                // Might include "No price" information
                echo sprintf('<p class="estate price">%s</p>', $estate['price']);
            }

            if ($instance['type'] == 'sold') {
                echo sprintf('<p class="estate price-change">%s %s</pre>', __('Prisökning', 'hemnet'), $estate['price-change']);
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
        $title          = isset($instance['title'])         ? $instance['title']            : 'Hemnet';
        $type           = isset($instance['type'])          ? $instance['type']             : 'for-sale';
        $location_ids   = isset($instance['location_ids'])  ? $instance['location_ids']     : '882639';
        $exact_numbers  = isset($instance['exact_numbers']) ? $instance['exact_numbers']    : '';
        $max_results    = isset($instance['max_results'])   ? $instance['max_results']      : '';

?>

        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel:' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
        </p>

        <p>
            <label for="<?php echo $this->get_field_id( 'type' ); ?>"><?php _e( 'Typ:' ); ?></label>
            <select class="widefat" id="<?php echo $this->get_field_id( 'type' ); ?>" name="<?php echo $this->get_field_name( 'type' ); ?>">
                <option value="for-sale" <?php echo $type == 'for-sale' ? 'selected' : '' ?>><?php echo __('Till salu', 'hemnet') ?></option>
                <option value="sold" <?php echo $type == 'sold' ? 'selected' : '' ?>><?php echo __('Sålda', 'hemnet') ?></option>
            </select>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id( 'location_ids' ); ?>"><?php _e( 'Plats-IDn:' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'location_ids' ); ?>" name="<?php echo $this->get_field_name( 'location_ids' ); ?>" type="text" value="<?php echo esc_attr( $location_ids ); ?>">
            <small><?php echo __('Kommaseparerad lista av "location_ids". Sök efter önskad destination och kopiera sista numret i URLen från Hemnet', 'hemnet') ?></small>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id( 'exact_numbers' ); ?>"><?php _e( 'Exakta nummer:' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'exact_numbers' ); ?>" name="<?php echo $this->get_field_name( 'exact_numbers' ); ?>" type="text" value="<?php echo esc_attr( $exact_numbers ); ?>">
            <small><?php echo __('Kommaseparerad lista av specifika nummer för en adress. Använd endast tillsammans med ETT plats-id.', 'hemnet') ?></small>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id( 'max_results' ); ?>"><?php _e( 'Max resultat:' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'max_results' ); ?>" name="<?php echo $this->get_field_name( 'max_results' ); ?>" type="text" value="<?php echo esc_attr( $max_results ); ?>">
        </p>

<?php

    }

    public function update($new_instance, $old_instance) {
        $instance = array();

        $instance['title']          = (!empty($new_instance['title']))          ? strip_tags($new_instance['title'])            : '';
        $instance['type']           = (!empty($new_instance['type']))           ? strip_tags($new_instance['type'])             : '';
        $instance['location_ids']   = (!empty($new_instance['location_ids']))   ? strip_tags($new_instance['location_ids'])     : '';
        $instance['exact_numbers']  = (!empty($new_instance['exact_numbers']))  ? strip_tags($new_instance['exact_numbers'])    : '';
        $instance['max_results']    = (!empty($new_instance['max_results']))    ? strip_tags($new_instance['max_results'])      : '';

        return $instance;
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

        // Add extra path for sold items in the URL
        $address_extra = $args['type'] == 'sold' ? 'salda/' : '';

        // The Hemnet address
        $hemnet_address = sprintf('http://www.hemnet.se/%sbostader?%s', $address_extra, join('&', array_map(function($id) { return sprintf('location_ids[]=%d', $id); }, $location_ids)));

        // Get DOM from Hemnet - supress warnings because reasons...
        $dom = @file_get_html($hemnet_address);

        // Return empty object list if request fails
        if (!$dom)
            return $objects;

        // Fallback for errors
        if (!$attributes)
            return $objects;

        // Loop over each result
        foreach ($dom->find('.results') as $item) {
            // Loop over the attributes we're looking for over each object
            foreach ($attributes as $class) {
                $i = 0;

                // Every attribute will be found for every object, push them one by one
                foreach ($item->find('.' . $class) as $data) {
                    // Get plaintext except for URLs
                    // The reuslt list for sold items contain full link, the list for items for sale does not...
                    $attrib = $class == 'item-link-container' ? sprintf('%s%s', $args['type'] == 'for-sale' ? 'http://www.hemnet.se' : '', $data->href) : $data->plaintext;

                    // Some text cleanup...
                    $attrib = preg_replace('/&nbsp;/', '', $attrib);
                    $attrib = preg_replace('/^\s+|\s+$/', '', $attrib);
                    $attrib = preg_replace('/\s{2,}/', ' ', $attrib);

                    // And remove hard coded prefixes
                    $attrib = preg_replace('/Begärt pris: /', '', $attrib);
                    $attrib = preg_replace('/Såld /', '', $attrib);
                    $attrib = preg_replace('/Slutpris /', '', $attrib);

                    $objects[$i][$class] = $attrib;

                    $i++;
                }
            }
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
                'item-link-container'
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
