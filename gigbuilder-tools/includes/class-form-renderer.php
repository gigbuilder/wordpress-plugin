<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gigbuilder_Form_Renderer {

    /**
     * Render a complete form from a CRM form definition.
     *
     * @param array  $form_data The form object from the API (title, fields).
     * @param string $date      The selected date to include as hidden field.
     * @return string HTML form markup.
     */
    public static function render( $form_data, $date ) {
        $html = '<form class="gigbuilder-form" data-date="' . esc_attr( $date ) . '">';

        if ( ! empty( $form_data['title'] ) ) {
            $html .= '<h3 class="gigbuilder-form-title">' . esc_html( $form_data['title'] ) . '</h3>';
        }

        if ( ! empty( $form_data['fields'] ) ) {
            // Split fields into groups: each group has an optional section label + fields
            $groups = array();
            $current_group = array( 'label' => '', 'fields' => array() );

            foreach ( $form_data['fields'] as $field ) {
                if ( ( $field['type'] ?? '' ) === 'section' ) {
                    // Save current group if it has fields
                    if ( ! empty( $current_group['fields'] ) ) {
                        $groups[] = $current_group;
                    }
                    // Start new group with this section label
                    $current_group = array(
                        'label'       => $field['label'] ?? '',
                        'description' => $field['description'] ?? '',
                        'fields'      => array(),
                    );
                } else {
                    $current_group['fields'][] = $field;
                }
            }
            // Save last group
            if ( ! empty( $current_group['fields'] ) ) {
                $groups[] = $current_group;
            }

            // Render each group
            foreach ( $groups as $group ) {
                if ( $group['label'] !== '' ) {
                    $html .= '<div class="gigbuilder-section">';
                    $html .= '<div class="gigbuilder-section-title">' . esc_html( $group['label'] ) . '</div>';
                    if ( ! empty( $group['description'] ) ) {
                        $html .= '<p class="gigbuilder-section-desc">' . wp_kses_post( $group['description'] ) . '</p>';
                    }
                }

                $html .= '<div class="gigbuilder-form-fields">';
                foreach ( $group['fields'] as $field ) {
                    $html .= self::render_field( $field );
                }
                $html .= '</div>';

                if ( $group['label'] !== '' ) {
                    $html .= '</div>';
                }
            }
        }

        $html .= '<div class="gigbuilder-field gigbuilder-field--submit">';
        $html .= '<button type="submit" class="gigbuilder-submit">' . esc_html__( 'Submit', 'gigbuilder-tools' ) . '</button>';
        $html .= '</div>';
        $html .= '</form>';

        return $html;
    }

    /**
     * Render a single form field from its JSON definition.
     *
     * @param array $field Field definition with type, subType, name, label, etc.
     * @return string HTML markup for the field.
     */
    public static function render_field( $field ) {
        $type        = $field['type'] ?? 'input';
        $sub_type    = $field['subType'] ?? 'text';
        $name        = $field['name'] ?? '';
        $label       = $field['label'] ?? '';
        $placeholder = $field['placeholder'] ?? '';
        $required    = ! empty( $field['required'] );
        $values      = $field['values'] ?? array();
        $columns     = intval( $field['columns'] ?? 1 );
        $default     = $field['value'] ?? '';
        $field_id    = 'gb-' . sanitize_html_class( $name );

        $span_class = 'gigbuilder-field--span-' . ( $columns === 2 ? '2' : '1' );
        $css_class = 'gigbuilder-field gigbuilder-field--' . sanitize_html_class( $type ) . ' ' . $span_class;
        if ( $type === 'input' ) {
            $css_class .= ' gigbuilder-field--' . sanitize_html_class( $sub_type );
        }

        $html = '<div class="' . $css_class . '">';

        // Label
        if ( $label ) {
            $html .= '<label for="' . esc_attr( $field_id ) . '">';
            $html .= esc_html( $label );
            if ( $required ) {
                $html .= ' <span class="gigbuilder-required">*</span>';
            }
            $html .= '</label>';
        }

        // Field markup
        switch ( $type ) {
            case 'input':
                $input_type = 'text';
                if ( $sub_type === 'email' ) $input_type = 'email';
                if ( $sub_type === 'phone' ) $input_type = 'tel';
                if ( $sub_type === 'number' ) $input_type = 'number';

                $html .= '<input type="' . esc_attr( $input_type ) . '"';
                $html .= ' id="' . esc_attr( $field_id ) . '"';
                $html .= ' name="' . esc_attr( $name ) . '"';
                if ( $default !== '' ) {
                    $html .= ' value="' . esc_attr( $default ) . '"';
                }
                if ( $placeholder ) {
                    $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
                }
                if ( $required ) {
                    $html .= ' required';
                }
                $html .= ' />';
                break;

            case 'textarea':
                $html .= '<textarea';
                $html .= ' id="' . esc_attr( $field_id ) . '"';
                $html .= ' name="' . esc_attr( $name ) . '"';
                if ( $placeholder ) {
                    $html .= ' placeholder="' . esc_attr( $placeholder ) . '"';
                }
                if ( $required ) {
                    $html .= ' required';
                }
                $html .= '>' . esc_html( $default ) . '</textarea>';
                break;

            case 'select':
                $html .= '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '"';
                if ( $required ) {
                    $html .= ' required';
                }
                $html .= '>';
                $html .= '<option value="">' . esc_html__( '— Select —', 'gigbuilder-tools' ) . '</option>';
                foreach ( $values as $val ) {
                    $parts = self::parse_value( $val );
                    $selected = ( $default !== '' && $parts['value'] === $default ) ? ' selected' : '';
                    $html .= '<option value="' . esc_attr( $parts['value'] ) . '"' . $selected . '>' . esc_html( $parts['label'] ) . '</option>';
                }
                $html .= '</select>';
                break;

            case 'radio':
                foreach ( $values as $i => $val ) {
                    $parts = self::parse_value( $val );
                    $radio_id = $field_id . '-' . $i;
                    $html .= '<div class="gigbuilder-radio-option">';
                    $html .= '<input type="radio" id="' . esc_attr( $radio_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $parts['value'] ) . '"';
                    if ( $default !== '' && $parts['value'] === $default ) {
                        $html .= ' checked';
                    }
                    if ( $required && $i === 0 ) {
                        $html .= ' required';
                    }
                    $html .= ' />';
                    $html .= '<label for="' . esc_attr( $radio_id ) . '">' . esc_html( $parts['label'] ) . '</label>';
                    $html .= '</div>';
                }
                break;

            case 'time':
                // Parse default value like "6:30 PM"
                $def_hour = '';
                $def_min  = '';
                $def_ampm = '';
                if ( $default !== '' && preg_match( '/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', $default, $m ) ) {
                    $def_hour = $m[1];
                    $def_min  = $m[2];
                    $def_ampm = strtoupper( $m[3] );
                }

                $html .= '<input type="hidden" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $default ) . '"';
                if ( $required ) {
                    $html .= ' required';
                }
                $html .= ' />';
                $html .= '<div class="gigbuilder-time-selects">';

                // Hour
                $html .= '<select id="' . esc_attr( $field_id ) . '-hour" onchange="GigbuilderTools.updateTimeField(\'' . esc_attr( $field_id ) . '\')">';
                $html .= '<option value="">' . esc_html__( 'Hr', 'gigbuilder-tools' ) . '</option>';
                for ( $h = 1; $h <= 12; $h++ ) {
                    $hval = (string) $h;
                    $sel = ( $def_hour !== '' && intval( $def_hour ) === $h ) ? ' selected' : '';
                    $html .= '<option value="' . $hval . '"' . $sel . '>' . $hval . '</option>';
                }
                $html .= '</select>';

                // Minute
                $html .= '<select id="' . esc_attr( $field_id ) . '-min" onchange="GigbuilderTools.updateTimeField(\'' . esc_attr( $field_id ) . '\')">';
                $html .= '<option value="">' . esc_html__( 'Min', 'gigbuilder-tools' ) . '</option>';
                for ( $m = 0; $m < 60; $m += 5 ) {
                    $mval = str_pad( $m, 2, '0', STR_PAD_LEFT );
                    $sel = ( $def_min !== '' && $mval === $def_min ) ? ' selected' : '';
                    $html .= '<option value="' . $mval . '"' . $sel . '>' . $mval . '</option>';
                }
                $html .= '</select>';

                // AM/PM
                $html .= '<select id="' . esc_attr( $field_id ) . '-ampm" onchange="GigbuilderTools.updateTimeField(\'' . esc_attr( $field_id ) . '\')">';
                $html .= '<option value="AM"' . ( $def_ampm === 'AM' ? ' selected' : '' ) . '>AM</option>';
                $html .= '<option value="PM"' . ( $def_ampm === 'PM' ? ' selected' : '' ) . '>PM</option>';
                $html .= '</select>';

                $html .= '</div>';
                break;

            case 'location':
                $html .= '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '"';
                $html .= ' onchange="GigbuilderTools.handleLocationChange(\'' . esc_attr( $field_id ) . '\')"';
                if ( $required ) {
                    $html .= ' required';
                }
                $html .= '>';
                $html .= '<option value="">' . esc_html__( '— Select —', 'gigbuilder-tools' ) . '</option>';
                foreach ( $values as $val ) {
                    $parts = self::parse_value( $val );
                    $selected = ( $default !== '' && $parts['value'] === $default ) ? ' selected' : '';
                    $html .= '<option value="' . esc_attr( $parts['value'] ) . '"' . $selected . '>' . esc_html( $parts['label'] ) . '</option>';
                }
                $html .= '</select>';
                $html .= '<div id="' . esc_attr( $field_id ) . '-name-wrap" class="gigbuilder-location-name" style="display:none;">';
                $html .= '<input type="text" id="' . esc_attr( $field_id ) . '-name" name="locationName" placeholder="Enter location name or address" />';
                $html .= '</div>';
                break;

            case 'duration':
                // Parse default value like "4:30" (hours:minutes)
                $def_dhour = '';
                $def_dmin  = '';
                if ( $default !== '' && preg_match( '/^(\d{1,2}):(\d{2})$/', $default, $m ) ) {
                    $def_dhour = $m[1];
                    $def_dmin  = $m[2];
                }

                $html .= '<input type="hidden" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $default ) . '"';
                if ( $required ) {
                    $html .= ' required';
                }
                $html .= ' />';
                $html .= '<div class="gigbuilder-time-selects">';

                // Hours
                $html .= '<select id="' . esc_attr( $field_id ) . '-hour" onchange="GigbuilderTools.updateDurationField(\'' . esc_attr( $field_id ) . '\')">';
                $html .= '<option value="">' . esc_html__( 'Hrs', 'gigbuilder-tools' ) . '</option>';
                for ( $h = 1; $h <= 24; $h++ ) {
                    $hval = (string) $h;
                    $sel = ( $def_dhour !== '' && intval( $def_dhour ) === $h ) ? ' selected' : '';
                    $html .= '<option value="' . $hval . '"' . $sel . '>' . $hval . '</option>';
                }
                $html .= '</select>';
                $html .= '<span>' . esc_html__( 'hrs', 'gigbuilder-tools' ) . '</span>';

                // Minutes
                $html .= '<select id="' . esc_attr( $field_id ) . '-min" onchange="GigbuilderTools.updateDurationField(\'' . esc_attr( $field_id ) . '\')">';
                $html .= '<option value="">' . esc_html__( 'Min', 'gigbuilder-tools' ) . '</option>';
                for ( $m = 0; $m < 60; $m += 5 ) {
                    $mval = str_pad( $m, 2, '0', STR_PAD_LEFT );
                    $sel = ( $def_dmin !== '' && $mval === $def_dmin ) ? ' selected' : '';
                    $html .= '<option value="' . $mval . '"' . $sel . '>' . $mval . '</option>';
                }
                $html .= '</select>';
                $html .= '<span>' . esc_html__( 'min', 'gigbuilder-tools' ) . '</span>';

                $html .= '</div>';
                break;

            case 'checkbox':
                foreach ( $values as $i => $val ) {
                    $parts = self::parse_value( $val );
                    $cb_id = $field_id . '-' . $i;
                    $html .= '<div class="gigbuilder-checkbox-option">';
                    $checked = ( $default !== '' && strpos( $default, $parts['value'] ) !== false ) ? ' checked' : '';
                    $html .= '<input type="checkbox" id="' . esc_attr( $cb_id ) . '" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $parts['value'] ) . '"' . $checked . ' />';
                    $html .= '<label for="' . esc_attr( $cb_id ) . '">' . esc_html( $parts['label'] ) . '</label>';
                    $html .= '</div>';
                }
                break;
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Parse a "Label|value" string. If no pipe, both label and value are the same.
     *
     * @param string $val Raw value string from CRM (e.g., "Arizona|AZ" or "Wedding").
     * @return array With 'label' and 'value' keys.
     */
    private static function parse_value( $val ) {
        if ( strpos( $val, '|' ) !== false ) {
            $parts = explode( '|', $val, 2 );
            return array( 'label' => $parts[0], 'value' => $parts[1] );
        }
        return array( 'label' => $val, 'value' => $val );
    }
}
