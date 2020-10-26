<?php
/**
 * hemnet_widget.php
 *
 * @package default
 */


/*
Plugin Name:    Hemnet
Plugin URI:     https://github.com/bombsimon/hemnet-plugin
Description:    Scrape information from Hemnet
Author:         Simon Sawert
Version:        0.3.0
Author URI:     http://sawert.se
License:        MIT OR GPL3
License URI:    https://opensource.org/licenses/MIT OR https://www.gnu.org/licenses/gpl-3.0.html
Domain Path:    /languages
Text Domain:    hemnet
 */

include_once 'hemnet.php';

if (!defined('ABSPATH'))
    die('-1');


add_action('widgets_init', function () {
    wp_enqueue_style('hemnet', plugins_url('_inc/style.css', __FILE__));
    register_widget('HemnetWidget');
});

load_plugin_textdomain('hemnet', FALSE, dirname(plugin_basename(__FILE__)) . '/languages/');

class HemnetWidget extends WP_Widget
{
    /**
     * Construct the plugin by setting a name and description.
     */
    function __construct()
    {
        parent::__construct(
            'Hemnet',
            __('Hemnet', 'hemnet'),
            array(
                'description' => __('Scrape real estates from Hemnet', 'hemnet')
            )
        );
    }


    /**
     * Implement the widget method from WP_Widget by rendering HTML.
     *
     * @param mixed[] $args
     * @param mixed[] $instance settings for the widget instance
     */
    public function widget($args, $instance)
    {
        echo $args['before_widget'];

        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        $location_ids = explode(',', $instance['location_ids']);
        $exact_numbers = $instance['exact_numbers'] ? explode(',', $instance['exact_numbers']) : [];
        $hemnet = new Hemnet();
        $hemnet_result = $hemnet->scrape_hemnet($location_ids, $instance['type'], $exact_numbers);

        $empty_text = [
            'for-sale' => __('There are no objects for sale at the moment.', 'hemnet'),
            'sold'     => __('There are no sold objects at the moment.', 'hemnet')
        ];

        if (!count($hemnet_result)) {
            echo '<div class="estate">' . $empty_text[$instance['type']] . '</div>';
        }

        $i = 1;
        foreach ($hemnet_result as $estate) {
            echo '<div class="estates">';

            $show_date_after = '';
            if ($instance['date_after_address'] && $instance['type'] == 'sold') {
                $show_date_after = sprintf(' <small>(%s)</small>', $estate['sold-date']);
            }

            if ($estate['sold-before-preview']) {
                printf('<p class="estate address"><strong>%s%s</strong></p>', $estate['address'], $show_date_after);
                printf('<p class="estate sold"><small>%s</small></p>', __('Sold before preview', 'hemnet'));
            } else {
                printf('<p class="estate address"><a href="%s" target="_blank">%s</a>%s</p>', $estate['url'], $estate['address'], $show_date_after);
            }

            if ($instance['type'] == 'sold' && !$instance['date_after_address']) {
                printf('<p class="estate sold-date">%s %s</p>', _x('Sold', 'Displayed before date', 'hemnet'), $estate['sold-date']);
            }

            printf('<p class="estate living-area">%s %s - %s %s</p>', $estate['living-area'], __('m²', 'hemnet'), $estate['rooms'], _n('room', 'rooms', $estate['rooms'], 'hemnet'));
            printf('<p class="estate fee">%s %s</p>', $estate['fee'], __('kr/month', 'hemnet'));

            if ($estate['price-per-m2'] && $instance['show_ppm2']) {
                printf('<p class="estate price">%s (%s %s)</p>', $estate['price'], $estate['price-per-m2'], __('kr/m²', 'hemnet'));
            } else {
                // Might include "No price" information
                printf('<p class="estate price">%s %s</p>', $estate['price'], __('kr', 'hemnet'));
            }

            if ($instance['type'] == 'sold') {
                if ($instance['show_increase']) {
                    printf('<p class="estate price-change">%s %s</pre>', __('Price change', 'hemnet'), $estate['price-change']);
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


    /**
     * Implement the form method for WP_Widget which will allow custom configuration.
     *
     * @param mixed[] $instance Current settings for the widget instance.
     */
    public function form($instance)
    {
        foreach ($this->settings() as $setting => $data) {
            $setting_value = $this->defined_or_fallback($instance[$setting], $data['default-value']);

            if ($data['header']) {
                printf('<p><strong>%s</strong></p>', $data['header']);
            }

            echo '<p>';
            if ($data['type'] == 'text') {
                printf('<label for="%s">%s</label>', $this->get_field_id($setting), $data['title']);
                printf('<input class="widefat" id="%s" name="%s" type="text" value="%s">', $this->get_field_id($setting), $this->get_field_name($setting), esc_attr($setting_value));
            } else if ($data['type'] == 'select') {
                printf('<label for="%s">%s</label>', $this->get_field_id($setting), $data['title']);
                printf('<select class="widefat" id="%s" name="%s">', $this->get_field_id($setting), $this->get_field_name($setting));

                foreach ($data['options'] as $option => $option_value) {
                    $selected = $setting_value == $option ? 'selected' : '';
                    printf('<option value="%s" %s>%s</option>', $option, $selected, $option_value);
                }
                printf('</select>');

            } else if ($data['type'] == 'checkbox') {
                $is_checked = $setting_value ? 'checked' : '';
                printf('<input id="%s" name="%s" type="checkbox" value="1" %s>', $this->get_field_id($setting), $this->get_field_name($setting), $is_checked);
                printf('<label for="%s">%s</label>', $this->get_field_id($setting), $data['title']);
            }

            if ($data['description']) {
                printf('<small>%s</small>', $data['description']);
            }
            echo '</p>';
        }
    }


    /**
     * Implement the update method for WP_Widget which will update the custom conifugration.
     *
     * @param mixed[] $new_instance The new configuration
     * @param mixed[] $old_instance The old configuration
     * @return string[] The new configuration
     */
    public function update($new_instance, $old_instance)
    {
        $instance = [];

        foreach ($this->settings() as $setting => $data) {
            $instance[$setting] = (!empty($new_instance[$setting])) ? strip_tags($new_instance[$setting]) : '';
        }

        return $instance;
    }


    /**
     * Return the value of $defined or if it's not set (null), return the fallback value.
     * The fallback value will default to an empty string.
     *
     * @param string|null $defined
     * @param string $fallback (optional)
     * @return string
     */
    private function defined_or_fallback($defined, $fallback = '')
    {
        return isset($defined) ? $defined : $fallback;
    }


    /**
     * Key-value definition of available settings to automatically render the
     * settings panel for the widget.
     *
     * @return array
     */
    private function settings()
    {
        $settings = [
            'title'              => [
                'title'         => __('Title:', 'hemnet'),
                'default-value' => 'Hemnet',
                'type'          => 'text',
            ],
            'type'               => [
                'title'         => __('Type:', 'hemnet'),
                'default-value' => 'for-sale',
                'type'          => 'select',
                'options'       => [
                    'for-sale' => __('For sale', 'hemnet'),
                    'sold'     => _x('Sold', 'In settings dropdown', 'hemnet'),
                ]
            ],
            'location_ids'       => [
                'title'         => __('Location ID\'s:', 'hemnet'),
                'default-value' => '882639',
                'type'          => 'text',
                'description'   => __('Comma separated list of "location_ids". Search your desired location and copy the last number from the URL from Hemnet.', 'hemnet'),
            ],
            'exact_numbers'      => [
                'title'       => __('Exact numbers:', 'hemnet'),
                'type'        => 'text',
                'description' => __('Comma separated list of specific numbers for a given address. Only use this with ONE location ID.', 'hemnet'),
            ],
            'max_results'        => [
                'title'         => __('Max results:', 'hemnet'),
                'default-value' => '10',
                'type'          => 'text',
            ],
            'show_increase'      => [
                'title'         => __('Display price change (only for sold)', 'hemnet'),
                'default-value' => '1',
                'type'          => 'checkbox',
                'header'        => __('Formatting', 'hemnet'),
            ],
            'date_after_address' => [
                'title' => __('Display date after address (only for sold)', 'hemnet'),
                'type'  => 'checkbox',
            ],
            'show_ppm2'          => [
                'title'         => __('Display price per m2', 'hemnet'),
                'default-value' => '1',
                'type'          => 'checkbox',
            ],
        ];

        return $settings;
    }
}
