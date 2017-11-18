<?php
/*
Plugin Name:    Hemnet
Plugin URI:     https://github.com/bombsimon/hemnet-plugin
Description:    Scrape information from Hemnet
Author:         Simon Sawert
Version:        0.2.0
Author URI:     http://sawert.se
License:        GPL3
License URI:    https://www.gnu.org/licenses/gpl-3.0.html
Domain Path:    /languages
Text Domain:    hemnet
 */

include_once '_inc/simple_html_dom.php';

if ( ! defined( 'ABSPATH' ) )
    die( '-1' );


add_action( 'widgets_init', function() {
    wp_enqueue_style( 'hemnet', plugins_url( '_inc/style.css', __FILE__ ) );
    register_widget( 'Hemnet' );
});

load_plugin_textdomain( 'hemnet', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

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

    public function widget( $args, $instance ) {
        echo $args['before_widget'];

        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
        }

        $hemnet_result = $this->scrape_hemnet( $instance );

        $empty_text = [
            'for-sale'  => __( 'There are no objects for sale at the moment.', 'hemnet' ),
            'sold'      => __( 'There are no sold objects at the moment.', 'hemnet' )
        ];

        if ( ! count( $hemnet_result ) ) {
            echo '<div class="estate">' . $empty_text[$instance['type']] . '</div>';
        }

        $i = 1;
        foreach ( $hemnet_result as $estate ) {
            echo '<div class="estates">';

            $show_date_after = '';
            if ( $instance['date_after_address'] && $instance['type'] == 'sold' ) {
                $show_date_after = sprintf(' <small>(%s)</small>', $estate['sold-date'] );
            }

            if ( $estate['sold-before-preview'] ) {
                printf( '<p class="estate address"><strong>%s%s</strong></p>', $estate['address'], $show_date_after );
                printf( '<p class="estate sold"><small>%s</small></p>', __( 'Sold before preview', 'hemnet' ) );
            } else {
                printf( '<p class="estate address"><a href="%s" target="_blank">%s</a>%s</p>', $estate['url'], $estate['address'], $show_date_after );
            }

            if ( $instance['type'] == 'sold' && ! $instance['date_after_address'] ) {
                printf( '<p class="estate sold-date">%s %s</p>', _x( 'Sold', 'Displayed before date', 'hemnet' ), $estate['sold-date'] );
            }

            printf( '<p class="estate living-area">%s %s - %s %s</p>', $estate['living-area'], __( 'm²', 'hemnet' ), $estate['rooms'], _n( 'room', 'rooms', $estate['rooms'], 'hemnet' ) );
            printf( '<p class="estate fee">%s %s</p>', $estate['fee'], __( 'kr/month', 'hemnet' ) );

            if ( $estate['price-per-m2'] && $instance['show_ppm2'] ) {
                printf( '<p class="estate price">%s (%s %s)</p>', $estate['price'], $estate['price-per-m2'], __( 'kr/m²', 'hemnet' ) );
            } else {
                // Might include "No price" information
                printf( '<p class="estate price">%s %s</p>', $estate['price'], __( 'kr', 'hemnet' ) );
            }

            if ( $instance['type'] == 'sold' ) {
                if ( $instance['show_increase'] ) {
                    printf( '<p class="estate price-change">%s %s</pre>', __( 'Price change', 'hemnet' ), $estate['price-change'] );
                }
            }

            echo '</div>';

            if ( $instance['max_results'] ) {
                if ( $i == $instance['max_results'] )
                    break;
            }

            $i++;
        }

        echo $args['after_widget'];
    }

    public function form ( $instance ) {
        foreach ( $this->settings() as $setting => $data ) {
            $setting_value = $this->defined_or_fallback( $instance[$setting], $data['default-value'] );

            if ( $data['header'] ) {
                printf( '<p><strong>%s</strong></p>', $data['header'] );
            }

            echo '<p>';
            if ( $data['type'] == 'text' ) {
                printf( '<label for="%s">%s</label>', $this->get_field_id( $setting ), $data['title'] );
                printf( '<input class="widefat" id="%s" name="%s" type="text" value="%s">', $this->get_field_id( $setting ), $this->get_field_name( $setting ), esc_attr( $setting_value ) );
            } else if ( $data['type'] == 'select' ) {
                printf( '<label for="%s">%s</label>', $this->get_field_id( $setting ), $data['title'] );
                printf( '<select class="widefat" id="%s" name="%s">', $this->get_field_id( $setting ), $this->get_field_name( $setting ) );

                foreach ( $data['options'] as $option => $option_value ) {
                    $selected = $setting_value == $option ? 'selected' : '';
                    printf( '<option value="%s" %s>%s</option>', $option, $selected, $option_value );
                }
                printf( '</select>' );

            } else if ( $data['type'] == 'checkbox' ) {
                $is_checked = $setting_value ? 'checked' : '';
                printf( '<input id="%s" name="%s" type="checkbox" value="1" %s>', $this->get_field_id( $setting ), $this->get_field_name( $setting ), $is_checked );
                printf( '<label for="%s">%s</label>', $this->get_field_id( $setting ), $data['title'] );
            }

            if ( $data['description'] ) {
                printf( '<small>%s</small>', $data['description'] );
            }
            echo '</p>';
        }
    }

    public function update( $new_instance, $old_instance ) {
        $instance = [];

        foreach ( $this->settings() as $setting => $data ) {
            $instance[$setting] = ( ! empty( $new_instance[$setting] ) ) ? strip_tags( $new_instance[$setting] ) : '';
        }

        return $instance;
    }

    private function defined_or_fallback ( $defined, $fallback = '') {
        return isset( $defined ) ? $defined : $fallback;
    }

    private function settings () {
        $settings = [
            'title' => [
                'title'         => __( 'Title:', 'hemnet' ),
                'default-value' => 'Hemnet',
                'type'          => 'text',
            ],
            'type' => [
                'title'         => __( 'Type:', 'hemnet' ),
                'default-value' => 'for-sale',
                'type'          => 'select',
                'options' => [
                    'for-sale'  => __( 'For sale', 'hemnet' ),
                    'sold'      => _x( 'Sold', 'In settings dropdown', 'hemnet' ),
                ]
            ],
            'location_ids' => [
                'title'         => __( 'Location ID\'s:', 'hemnet' ),
                'default-value' => '882639',
                'type'          => 'text',
                'description'   => __( 'Comma separated list of "location_ids". Search your desired location and copy the last number from the URL from Hemnet.', 'hemnet' ),
            ],
            'exact_numbers' => [
                'title'         => __( 'Exact numbers:', 'hemnet' ),
                'type'          => 'text',
                'description'   => __( 'Comma separated list of specific numbers for a given address. Only use this with ONE location ID.', 'hemnet' ),
            ],
            'max_results' => [
                'title'         => __( 'Max results:', 'hemnet' ),
                'default-value' => '10',
                'type'          => 'text',
            ],
            'show_increase' => [
                'title'         => __( 'Display price change (only for sold)', 'hemnet' ),
                'default-value' => '1',
                'type'          => 'checkbox',
                'header'        => __( 'Formatting', 'hemnet' ),
            ],
            'date_after_address' => [
                'title'         => __( 'Display date after address (only for sold)', 'hemnet' ),
                'type'          => 'checkbox',
            ],
            'show_ppm2' => [
                'title'         => __( 'Display price per m2', 'hemnet' ),
                'default-value' => '1',
                'type'          => 'checkbox',
            ],
        ];

        return $settings;
    }

    private function scrape_hemnet ( $args ) {
        $location_ids  = explode( ',', $args['location_ids'] );
        $exact_numbers = $args['exact_numbers'] ? explode( ',', $args['exact_numbers'] ) : [];

        $objects    = [];
        $attributes = $this->get_attributes();

        if ( ! $attributes )
            return $objects;

        $type = $args['type'];

        $hemnet_address = sprintf( 'http://www.hemnet.se/%sbostader?%s', $attributes['address-extra'][$type], join( '&', array_map( function( $id ) { return sprintf( 'location_ids[]=%d', $id ); }, $location_ids ) ) );
        $dom            = @file_get_html( $hemnet_address );

        if ( ! $dom )
            return $objects;

        foreach ( $dom->find( $attributes['dom-classes'][$type] ) as $item ) {
            foreach ( $attributes['data-classes'] as $key => $element ) {
                if ( ! isset( $element[$type] ) )
                    continue;

                $data = $item->find( $element[$type], 0 );

                // Remove inner elements if data element contains children
                if ( $element['remove'] && $data->find( $element['remove'], 0 ) )
                    $data->find( $element['remove'], 0 )->innertext = '';

                $value = $data->plaintext;

                if ( $key == 'url' )
                    $value = sprintf( '%s%s', $args['type'] == 'for-sale' ? 'http://www.hemnet.se' : '', $data->href );

                if ( $key == 'image' )
                    $value = $data->{'data-src'};

                $value = preg_replace( '/&nbsp;/', ' ', $value );
                $value = preg_replace( '/^\s+|\s+$/', '', $value );
                $value = preg_replace( '/\s{2,}/', ' ', $value );
                $value = preg_replace( '/Begärt pris: /', '', $value );
                $value = preg_replace( '/Såld /', '', $value );
                $value = preg_replace( '/Slutpris /', '', $value );
                $value = preg_replace( '/ kr(\/m(²|ån))?/', '', $value );
                $value = preg_replace( '/ rum/', '', $value );
                $value = preg_replace( '/ m²/', '', $value );

                if ( $key == 'sold-date' ) {
                    $value = $this->format_date( $value );
                }

                // Sold properties stores living area and rooms in the same element so we extract them
                if ( $key == 'size' ) {
                    preg_match( '/^([\d,]+) ([\d,]+)/', $value, $size_info );

                    $objects[$i]['living-area'] = $size_info[1];
                    $objects[$i]['rooms']       = $size_info[2];

                    continue;
                }

                $objects[$i][$key] = $value;
            }

            $i++;
        }

        if ( ! count( $exact_numbers ) )
            return $objects;

        $exact_matches = [];
        foreach ( $objects as $obj ) {
            foreach ( $exact_numbers as $exact ) {
                preg_match( '/^(\D+) (\d+)\w?(,|$)/', $obj['address'], $address );

                if ( $address[2] == $exact ) {
                    $exact_matches[] = $obj;
                }
            }
        }

        return $exact_matches;
    }

    private function format_date( $date = '1 januari 1990' ) {
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

        preg_match( '/^(\d+) (\w+) (\d+)$/', $date, $dp );
        $formatted_date = sprintf( '%d-%02d-%02d', $dp[3], $m[$dp[2]], $dp[1] );

        return $formatted_date;
    }

    private function get_attributes() {
        $class_map = [
            'dom-classes' => [
                'sold'     => '.sold-property-listing',
                'for-sale' => '.results  > .result',
            ],
            'address-extra' => [
                'sold'     => 'salda/',
                'for-sale' => null,
            ],
            'data-classes' => [
                'address' => [
                    'sold'     => '.item-result-meta-attribute-is-bold',
                    'for-sale' => '.property-address--desktop-only',
                ],
                'age' => [
                    'sold'     => null,
                    'for-sale' => '.age',
                ],
                'price' => [
                    'sold'     => '.sold-property-listing__price > .sold-property-listing__subheading',
                    'for-sale' => '.price',
                ],
                'price-change' => [
                    'sold'     => '.sold-property-listing__price-change',
                    'for-sale' => null,
                ],
                'fee' => [
                    'sold'     => '.sold-property-listing__fee',
                    'for-sale' => '.fee',
                ],
                'size' => [
                    'sold'     => '.sold-property-listing__size > .sold-property-listing__subheading',
                    'for-sale' => null,
                ],
                'living-area' => [
                    'sold'     => null,
                    'for-sale' => '.living-area',
                ],
                'rooms' => [
                    'sold'     => null,
                    'for-sale' => '.rooms',
                ],
                'price-per-m2' => [
                    'sold'     => '.sold-property-listing__price-per-m2',
                    'for-sale' => '.price-per-m2',
                ],
                'url' => [
                    'sold'     => '.item-link-container',
                    'for-sale' => '.item-link-container',
                ],
                'image' => [
                    'sold'     => null,
                    'for-sale' => '.property-image',
                ],
                'sold-date' => [
                    'sold'     => '.sold-property-listing__sold-date',
                    'for-sale' => null,
                ],
                'sold-before-preview' => [
                    'sold'     => null,
                    'for-sale' => '.ribbon--deactivated-before-open-house-day',
                ]
            ]
        ];

        return $class_map;
    }
}
