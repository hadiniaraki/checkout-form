<?php
/**
 * Customize WooCommerce Checkout Fields based on User Type (Individual/Corporate).
 * Adds user_type radio field, hides/shows/validates other fields dynamically.
 * Saves custom ACF fields for Corporate users to Order and User Meta.
 * Displays custom fields on Admin Order Edit page.
 */

// Ensure ACF and WooCommerce are active
if ( ! function_exists( 'acf_render_field' ) || ! class_exists( 'WooCommerce' ) ) {
    error_log('ACF or WooCommerce is not active. Custom checkout fields functionality will not be available.');
    return;
}

// --- 1. Define and Modify Checkout Fields ---
add_filter( 'woocommerce_checkout_fields', 'customize_checkout_fields_by_user_type' );

function customize_checkout_fields_by_user_type( $fields ) {
    // --- Add User Type Radio Field ---
    $user_type_field = array(
        'type'     => 'radio',
        'label'    => 'کاربر:',
        'required' => true,
        'class'    => array( 'form-row-wide', 'user-type-radio-container' ),
        'options'  => array(
            'individual' => 'حقیقی',
            'corporate'  => 'حقوقی',
        ),
        'default'  => 'individual',
        'priority' => 5, // Place at the very top
    );
    // Add user_type field at the beginning of billing fields
    $fields['billing'] = array( 'user_type' => $user_type_field ) + $fields['billing'];

    // --- Modify Standard Billing Fields ---

    // Modify Company Name (billing_company) - Hidden for Individual, Shown/Required for Corporate
    if ( isset( $fields['billing']['billing_company'] ) ) {
        // Set initial state: hidden and not required (default is individual)
        $fields['billing']['billing_company']['required'] = false;
        $fields['billing']['billing_company']['class'][] = 'hidden-field';
        $fields['billing']['billing_company']['class'][] = 'conditional-field'; // Add a class for JS targeting
        $fields['billing']['billing_company']['class'][] = 'corporate-field'; // Indicate it's a corporate-specific field
         $fields['billing']['billing_company']['priority'] = 20; // Adjust priority
    } else {
         // If somehow billing_company doesn't exist, define it to control its visibility
         $fields['billing']['billing_company'] = array(
             'type'      => 'text',
             'label'     => 'نام شرکت', // This label will be set by JS for corporate
             'required'  => false,
             'class'     => array('form-row-wide', 'hidden-field', 'conditional-field', 'corporate-field'),
             'priority'  => 20,
         );
    }


    // Change Label of Phone (billing_phone) to "تلفن" and make required for both
    // Note: HTML ID is 'tel', Name is 'mobile/email' due to Digits plugin based on user info.
    // We modify the field array entry with the standard key 'billing_phone'.
    if ( isset( $fields['billing']['billing_phone'] ) ) {
        $fields['billing']['billing_phone']['label'] = 'تلفن';
        $fields['billing']['billing_phone']['required'] = true; // Make required for both types
         // Remove 'optional' span if theme adds it based on original required=false
         $fields['billing']['billing_phone']['label'] = str_replace(' <span class="optional">(اختیاری)</span>', '', $fields['billing']['billing_phone']['label']);
         // No need for conditional visibility class here, it's always shown
    }


    // Email (billing_email) should be optional for both
    if ( isset( $fields['billing']['billing_email'] ) ) {
        $fields['billing']['billing_email']['required'] = false;
         // Ensure 'optional' span IS added or label reflects optionality
         // Standard WC handles this if required=false, but double-check theme
         $fields['billing']['billing_email']['label'] = str_replace(' <span class="required">*</span>', '', $fields['billing']['billing_email']['label']);
         if ( strpos($fields['billing']['billing_email']['label'], '(اختیاری)') === false ) {
             $fields['billing']['billing_email']['label'] .= ' <span class="optional">(اختیاری)</span>';
         }
         // No need for conditional visibility class here, it's always shown
    }


    // Modify 'National ID' field (billing_national_id) - Label "کدملی", Required for Individual, Hidden for Corporate
    $national_id_key = 'billing_national_id'; // Key confirmed by user

    if ( isset( $fields['billing'][ $national_id_key ] ) ) {
        $fields['billing'][ $national_id_key ]['label'] = 'کدملی';
        $fields['billing'][ $national_id_key ]['required'] = true; // Required for Individual (default)
        // No need to add 'hidden-field' class here, JS handles initial state
        $fields['billing'][ $national_id_key ]['class'][] = 'conditional-field'; // Add a class for JS targeting
        $fields['billing'][ $national_id_key ]['class'][] = 'individual-field'; // Indicate it's an individual-specific field
         $fields['billing'][ $national_id_key ]['priority'] = 90; // Adjust priority
    }
     // If 'billing_national_id' doesn't exist and you need to add it:
     // else {
     //    $fields['billing'][ $national_id_key ] = array(
     //        'type'     => 'text', // Or number
     //        'label'    => 'کدملی',
     //        'required' => true, // Required for Individual
     //        'class'    => array('form-row-wide', 'conditional-field', 'individual-field'),
     //        'priority' => 90,
     //    );
     // }


    // --- Add New ACF Fields for Corporate Users (Hidden for Individual) ---
    // These fields are initially hidden and made required by JS for 'corporate'
    // Use ACF field names as keys for easier saving/validation via $_POST

    $fields['billing']['co_national_id'] = array( // Key matches ACF field name for شناسه ملی
        'type'     => 'text', // Assuming text input based on typical IDs
        'label'    => 'شناسه ملی',
        'required' => false, // Conditionally required (handled by JS/validation)
        'class'    => array('form-row-wide', 'hidden-field', 'conditional-field', 'corporate-field'), // Hidden by default, specific classes for JS
        'priority' => 22, // Adjust priority
    );

     $fields['billing']['register_id'] = array( // Key matches ACF field name for شناسه ثبت
        'type'     => 'text', // Assuming text input
        'label'    => 'شناسه ثبت',
        'required' => false, // Conditionally required
        'class'    => array('form-row-wide', 'hidden-field', 'conditional-field', 'corporate-field'), // Hidden by default, specific classes for JS
        'priority' => 24, // Adjust priority
    );

    $fields['billing']['tel_com'] = array( // Key matches ACF field name for شماره تلفن ثابت
        'type'     => 'text', // Assuming text input
        'label'    => 'شماره تلفن ثابت',
        'required' => false, // Conditionally required
        'class'    => array('form-row-wide', 'hidden-field', 'conditional-field', 'corporate-field'), // Hidden by default, specific classes for JS
        'priority' => 50, // Adjust priority (maybe near phone)
    );

    // --- Note on Country Field ('billing_country') ---
    // To limit Country to only 'Iran', you would use the 'woocommerce_countries' filter separately.
    // This is outside the scope of the user_type toggle code.

    return $fields;
}


// --- 2. Add JavaScript for Dynamic Toggle ---
add_action( 'wp_footer', 'checkout_user_type_conditional_fields_script' ); // Or woocommerce_after_checkout_form

function checkout_user_type_conditional_fields_script() {
    if ( ! is_checkout() || is_wc_endpoint_url() ) {
        return;
    }
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Find the radio buttons for user type
            var userTypeRadios = $('input[name="user_type"]');

            // Cache field containers and input/select elements for performance
            var $billingCompanyField = $('#billing_company_field'); // Standard WC field container
            var $billingCompanyInput = $('#billing_company');       // Standard WC input element

            var $billingNationalIdField = $('#billing_national_id_field'); // 'کدملی' field container
            var $billingNationalIdInput = $('#billing_national_id');       // 'کدملی' input element

            // ACF Corporate Field Containers (WooCommerce uses field name + _field for container ID)
            var $coNationalIdField = $('#co_national_id_field');
            var $registerIdField = $('#register_id_field');
            var $telComField = $('#tel_com_field');

            // ACF Corporate Input Elements (WooCommerce uses field name for input ID)
            var $coNationalIdInput = $('#co_national_id');
            var $registerIdInput = $('#register_id');
            var $telComInput = $('#tel_com');


            // Function to toggle field visibility and required status
            function toggleUserTypeFields(selectedUserType) {
                // Hide all conditional fields initially
                $('.conditional-field').addClass('hidden-field');

                // Remove required status from all conditionally required fields
                 $('.conditional-field input, .conditional-field select, .conditional-field textarea')
                    .prop('required', false)
                    .closest('.form-row') // Get the parent container
                    .removeClass('validate-required'); // Remove WC validation class

                // Clear values of hidden fields to prevent validation issues
                 $('.conditional-field.hidden-field input, .conditional-field.hidden-field select, .conditional-field.hidden-field textarea')
                    .val('');


                if (selectedUserType === 'individual') {
                    // --- Show/Require fields for Individual ---
                    $billingNationalIdField.removeClass('hidden-field'); // Show 'کدملی'
                    $billingNationalIdInput.prop('required', true).closest('.form-row').addClass('validate-required'); // Make 'کدملی' required

                } else if (selectedUserType === 'corporate') {
                    // --- Show/Require fields for Corporate ---
                    $billingCompanyField.removeClass('hidden-field'); // Show Standard 'Company Name'
                     // Update label text for standard Company field (remove optional)
                    $billingCompanyField.find('label').text('نام شرکت');
                    $billingCompanyInput.prop('required', true).closest('.form-row').addClass('validate-required'); // Make 'Company Name' required

                    $coNationalIdField.removeClass('hidden-field'); // Show 'شناسه ملی' (ACF)
                    $coNationalIdInput.prop('required', true).closest('.form-row').addClass('validate-required'); // Make 'شناسه ملی' required

                    $registerIdField.removeClass('hidden-field'); // Show 'شناسه ثبت' (ACF)
                    $registerIdInput.prop('required', true).closest('.form-row').addClass('validate-required'); // Make 'شناسه ثبت' required

                    $telComField.removeClass('hidden-field'); // Show 'شماره تلفن ثابت' (ACF)
                    $telComInput.prop('required', true).closest('.form-row').addClass('validate-required'); // Make 'شماره تلفن ثابت' required

                }

                // Trigger WooCommerce's updated_checkout event to refresh fragments and validation
                $(document.body).trigger('updated_checkout');
            }


            // Trigger toggle on radio change
            userTypeRadios.on('change', function() {
                toggleUserTypeFields($(this).val());
            });

            // Trigger toggle on page load based on initial selection
            // Ensure initial state is correct
            var initialUserType = $('input[name="user_type"]:checked').val();
            if (initialUserType) {
                 toggleUserTypeFields(initialUserType);
            } else {
                // Fallback if no default is checked (shouldn't happen with default set)
                 toggleUserTypeFields('individual'); // Assume individual if no selection
            }

             // --- Special handling for standard Company field label on page load ---
             // If corporate is the initial selection, the label needs to be updated immediately.
             // The toggleUserTypeFields call above handles this, but this is belt-and-suspenders.
             // If initialUserType === 'corporate' && $billingCompanyField.length > 0) {
             //     $billingCompanyField.find('label').text('نام شرکت');
             // }


        });
    </script>
    <?php
}


// --- 3. Server-Side Validation ---
add_action('woocommerce_checkout_process', 'validate_conditional_checkout_fields');

function validate_conditional_checkout_fields() {
    // Ensure user_type is selected (redundant with required=true but good practice)
    if ( empty( $_POST['user_type'] ) ) {
         wc_add_notice( __( 'لطفاً نوع کاربری را مشخص کنید.', 'your-text-domain' ), 'error' );
         return; // Stop validation if user_type is missing
    }

    $selected_user_type = sanitize_text_field( $_POST['user_type'] );
    $is_corporate = ( $selected_user_type === 'corporate' );
    $is_individual = ( $selected_user_type === 'individual' );

    // Validate conditionally required fields
    if ( $is_individual ) {
        // Check required fields for Individual
        // 'کدملی' field (billing_national_id)
        if ( empty( $_POST['billing_national_id'] ) ) { // Use the correct POST key
            wc_add_notice( __( 'وارد کردن کدملی الزامی است.', 'your-text-domain' ), 'error' );
        }

    } elseif ( $is_corporate ) {
        // Check required fields for Corporate
        if ( empty( $_POST['billing_company'] ) ) { // Standard Company field
            wc_add_notice( __( 'وارد کردن نام شرکت الزامی است.', 'your-text-domain' ), 'error' );
        }
        if ( empty( $_POST['co_national_id'] ) ) { // ACF field: شناسه ملی
            wc_add_notice( __( 'وارد کردن شناسه ملی الزامی است.', 'your-text-domain' ), 'error' );
        }
         if ( empty( $_POST['register_id'] ) ) { // ACF field: شناسه ثبت
            wc_add_notice( __( 'وارد کردن شناسه ثبت الزامی است.', 'your-text-domain' ), 'error' );
        }
         if ( empty( $_POST['tel_com'] ) ) { // ACF field: شماره تلفن ثابت
            wc_add_notice( __( 'وارد کردن شماره تلفن ثابت الزامی است.', 'your-text-domain' ), 'error' );
        }
    }

    // Note: Fields required for *both* types (like name, last name, phone, address)
    // are typically handled by WooCommerce's default validation if marked required in the filter.
    // This function focuses on the *conditionally* required fields based on user type.
}


// --- 4. Save Custom Fields to Order Meta ---
add_action('woocommerce_checkout_update_order_meta', 'save_custom_checkout_fields_to_order_meta');

function save_custom_checkout_fields_to_order_meta( $order_id ) {
    $selected_user_type = isset($_POST['user_type']) ? sanitize_text_field($_POST['user_type']) : '';

    // Save user_type
    if ( ! empty( $selected_user_type ) ) {
        update_post_meta( $order_id, '_user_type', $selected_user_type );
    }

    // Save 'کدملی' field (billing_national_id) if submitted (for individual)
    $national_id_key = 'billing_national_id'; // Use the correct POST key
     if ( $selected_user_type === 'individual' && isset( $_POST[ $national_id_key ] ) && ! empty( $_POST[ $national_id_key ] ) ) {
         update_post_meta( $order_id, '_' . $national_id_key, sanitize_text_field( $_POST[ $national_id_key ] ) );
     }
     // Optional: Clear the meta if user was corporate and this meta existed from a previous order
     // elseif ( $selected_user_type === 'corporate' ) {
     //     delete_post_meta( $order_id, '_' . $national_id_key );
     // }


    // Save Corporate ACF fields if submitted (for corporate)
    $corporate_acf_fields = array( 'co_national_id', 'register_id', 'tel_com' );
    if ( $selected_user_type === 'corporate' ) {
        foreach ( $corporate_acf_fields as $field_name ) {
            if ( isset( $_POST[ $field_name ] ) && ! empty( $_POST[ $field_name ] ) ) {
                // Save to order meta using the ACF field name as the meta key (prefixed with _)
                update_post_meta( $order_id, '_' . $field_name, sanitize_text_field( $_POST[ $field_name ] ) );
            }
            // Optional: Clear the meta if the field was empty but existed previously
             // elseif ( metadata_exists('post', $order_id, '_' . $field_name) ) {
            //      delete_post_meta( $order_id, '_' . $field_name );
            //  }
        }
    }
     // Optional: Clear corporate ACF meta if user was individual
     // elseif ( $selected_user_type === 'individual' ) {
     //     foreach ( $corporate_acf_fields as $field_name ) {
     //          delete_post_meta( $order_id, '_' . $field_name );
     //     }
     // }


    // Note: Standard billing fields like billing_company, billing_first_name, etc.
    // are saved to order meta automatically by WooCommerce if they are present in $_POST.
}


// --- 5. Save Custom Fields to User Meta (using ACF update_field) ---
// This saves user_type and the ACF Corporate fields to the user's profile
add_action('woocommerce_checkout_order_processed', 'save_custom_checkout_fields_to_user_meta', 10, 3);

function save_custom_checkout_fields_to_user_meta( $order_id, $posted_data, $order ) {
    // Get user ID (0 for guests)
    $user_id = $order->get_customer_id();

    // Only save to user meta if a user is logged in or registered during checkout
    // And only if ACF's update_field function exists
    if ( $user_id > 0 && function_exists( 'update_field' ) ) {

        $selected_user_type = isset($_POST['user_type']) ? sanitize_text_field($_POST['user_type']) : '';

        // Always save user_type to the user's profile
        if ( ! empty( $selected_user_type ) ) {
             update_field( 'user_type', $selected_user_type, 'user_' . $user_id );
        }

        // Save Corporate ACF fields if user is Corporate type
        $corporate_acf_fields = array( 'co_national_id', 'register_id', 'tel_com' ); // Use ACF field names
        $all_handled_acf_fields = array_merge($corporate_acf_fields, array('user_type'));

        if ( $selected_user_type === 'corporate' ) {
             foreach ( $corporate_acf_fields as $field_name ) {
                if ( isset( $_POST[ $field_name ] ) && ! empty( $_POST[ $field_name ] ) ) {
                    // Save using ACF's update_field to the user profile
                    update_field( $field_name, sanitize_text_field( $_POST[ $field_name ] ), 'user_' . $user_id );
                } else {
                    // If the field is empty but user type is corporate, ensure it's cleared in user meta
                    update_field( $field_name, '', 'user_' . $user_id );
                }
             }
            // Optional: Clear individual-specific ACF fields if switching type
            // Example: if you had an ACF field 'national_id_individual'
            // update_field( 'national_id_individual', '', 'user_' . $user_id );

        } elseif ( $selected_user_type === 'individual' ) {
             // Save 'کدملی' IF it's an ACF field (which it isn't based on billing_national_id key)
             // If billing_national_id should save to user meta (NOT via ACF), use update_user_meta instead.
             // $national_id_key = 'billing_national_id';
             // if ( isset( $_POST[ $national_id_key ] ) && ! empty( $_POST[ $national_id_key ] ) ) {
             //      update_user_meta( $user_id, $national_id_key, sanitize_text_field( $_POST[ $national_id_key ] ) );
             // } else {
             //      delete_user_meta( $user_id, $national_id_key );
             // }

            // Clear corporate-specific ACF fields if switching from corporate
            foreach ( $corporate_acf_fields as $field_name ) {
                update_field( $field_name, '', 'user_' . $user_id );
            }

        }
         // Note: Standard billing/shipping fields (billing_company, billing_phone, billing_email etc.)
         // are usually saved to user meta by WooCommerce itself.
    }
}


// --- 6. Display Custom Fields on Admin Order Edit Page ---
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_custom_fields_on_admin_order_page', 10, 1 );

function display_custom_fields_on_admin_order_page( $order ) {
    // Display User Type
    $user_type_saved_value = $order->get_meta( '_user_type' );
    $user_type_display = '';
     if ( 'individual' === $user_type_saved_value ) { $user_type_display = 'حقیقی'; }
     elseif ( 'corporate' === $user_type_saved_value ) { $user_type_display = 'حقوقی'; }

    if ( ! empty( $user_type_display ) ) {
        echo '<p class="form-field form-field-wide wc-customer-user">';
        echo '<strong>' . __( 'نوع کاربری', 'your-text-domain' ) . ':</strong> ' . esc_html( $user_type_display );
        echo '</p>';
    }

    // Display Conditionally saved fields (Company, کدملی, Corporate ACF fields)
    // Standard billing_company is saved automatically by WC to _billing_company
    $company_name_value = $order->get_meta( '_billing_company' );
     if ( ! empty( $company_name_value ) ) {
        echo '<p class="form-field form-field-wide">';
        echo '<strong>' . __( 'نام شرکت', 'your-text-domain' ) . ':</strong> ' . esc_html( $company_name_value );
        echo '</p>';
     }


    // Display 'کدملی' field (billing_national_id) if saved in order meta (for individual)
    $national_id_order_meta_key = '_billing_national_id'; // Use the meta key used in save_custom_checkout_fields_to_order_meta
     $national_id_value = $order->get_meta( $national_id_order_meta_key );
     if ( ! empty( $national_id_value ) ) {
        echo '<p class="form-field form-field-wide">';
        echo '<strong>' . __( 'کدملی', 'your-text-domain' ) . ':</strong> ' . esc_html( $national_id_value );
        echo '</p>';
     }

    // Display Corporate ACF Fields if saved in order meta
    $corporate_fields_to_display = array(
        '_co_national_id' => 'شناسه ملی', // Meta keys used in save_custom_checkout_fields_to_order_meta
        '_register_id'  => 'شناسه ثبت',
        '_tel_com'      => 'شماره تلفن ثابت',
    );

    foreach ( $corporate_fields_to_display as $meta_key => $label ) {
        $value = $order->get_meta( $meta_key );
        if ( ! empty( $value ) ) {
            echo '<p class="form-field form-field-wide">';
            echo '<strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value );
            echo '</p>';
        }
    }

    // Note: Standard billing fields like phone, email, address are displayed by WC by default in the billing address block.
}