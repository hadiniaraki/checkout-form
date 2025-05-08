<?php
/**
 * Customize WooCommerce Checkout and Account Edit Fields based on User Type (Individual/Corporate).
 * Adds user_type radio field, hides/shows/validates other fields dynamically.
 * Saves custom ACF fields for Corporate users to Order and User Meta.
 * Displays custom fields on Admin Order Edit page and My Account Edit page.
 * Includes validation for Company National ID (co_national_id) using the provided algorithm.
 * Updated to:
 * - Validate co_national_id for corporate users in Checkout and Edit Account.
 * - Add client-side validation for co_national_id with JavaScript.
 * - Fix dynamic field display logic in edit account page.
 * - Ensure correct fields show/hide based on user_type.
 * - Add debug logs and console logs for JavaScript.
 * - Increase hook priority to avoid plugin conflicts.
 * - Set default values in checkout from user meta.
 * - Ensure fields are saved in wp_usermeta.
 */

// Ensure ACF and WooCommerce are active
if (!function_exists('acf_render_field') || !class_exists('WooCommerce')) {
    error_log('ACF or WooCommerce is not active. Custom checkout fields functionality will not be available.');
    return;
}

// --- 1. Validate Company National ID ---
/**
 * Validate Company National ID based on the provided algorithm.
 * @param string $co_national_id The 11-digit company national ID.
 * @return bool True if valid, false otherwise.
 */
function validate_company_national_id($co_national_id) {
    // بررسی طول و نوع ورودی (باید 11 رقم باشد)
    if (!ctype_digit($co_national_id) || strlen($co_national_id) !== 11) {
        error_log('Invalid co_national_id format: ' . $co_national_id);
        return false;
    }

    // تبدیل به آرایه اعداد
    $digits = array_map('intval', str_split($co_national_id));

    // ضرایب برای 10 رقم اول
    $weights = [29, 27, 23, 19, 17, 29, 27, 23, 19, 247];

    // محاسبه مجموع با ضرایب
    $total = 0;
    for ($i = 0; $i < 10; $i++) {
        $total += $digits[$i] * $weights[$i];
    }

    // اضافه کردن 460
    $total += 460;

    // محاسبه باقیمانده
    $remainder = $total % 11;
    if ($remainder == 10) {
        $remainder = 0;
    }

    // بررسی رقم کنترلی (رقم یازدهم)
    $is_valid = $remainder === $digits[10];
    error_log('co_national_id: ' . $co_national_id . ', remainder: ' . $remainder . ', control_digit: ' . $digits[10] . ', valid: ' . ($is_valid ? 'true' : 'false'));

    return $is_valid;
}

// --- 2. Define and Modify Checkout Fields ---
add_filter('woocommerce_checkout_fields', 'customize_checkout_fields_by_user_type', 1000);

function customize_checkout_fields_by_user_type($fields) {
    $user_id = get_current_user_id();
    $user_type = get_user_meta($user_id, 'user_type', true) ?: 'individual';

    // --- Add User Type Radio Field (at the top) ---
    $fields['billing']['user_type'] = array(
        'type'     => 'radio',
        'label'    => 'کاربر:',
        'required' => true,
        'class'    => array('form-row-wide', 'user-type-radio-container'),
        'options'  => array(
            'individual' => 'حقیقی',
            'corporate'  => 'حقوقی',
        ),
        'default'  => $user_type,
        'priority' => 5,
    );

    // --- Hide Country Field and Set Default to Iran ---
    if (isset($fields['billing']['billing_country'])) {
        $fields['billing']['billing_country']['required'] = false;
        $fields['billing']['billing_country']['class'][] = 'hidden-field';
        $fields['billing']['billing_country']['default'] = 'IR';
        $fields['billing']['billing_country']['priority'] = 200;
    }

    // --- Manage State Field (billing_state) ---
    if (isset($fields['billing']['billing_state'])) {
        $fields['billing']['billing_state']['priority'] = 50;
    }

    // --- Manage City Field (billing_city) - After State ---
    if (isset($fields['billing']['billing_city'])) {
        $fields['billing']['billing_city']['priority'] = 55;
    }

    // --- Modify Standard Billing Fields ---

    // First Name (billing_first_name)
    if (isset($fields['billing']['billing_first_name'])) {
        $fields['billing']['billing_first_name']['priority'] = 10;
    }

    // Last Name (billing_last_name)
    if (isset($fields['billing']['billing_last_name'])) {
        $fields['billing']['billing_last_name']['priority'] = 15;
    }

    // National ID (billing_national_id) - After Last Name for Individual
    $national_id_key = 'billing_national_id';
    if (isset($fields['billing'][$national_id_key])) {
        $fields['billing'][$national_id_key]['label'] = 'کدملی <span class="required">*</span>';
        $fields['billing'][$national_id_key]['required'] = false; // Managed by JS
        $fields['billing'][$national_id_key]['class'] = array('form-row-wide', 'hidden-field', 'conditional-field', 'individual-field');
        $fields['billing'][$national_id_key]['priority'] = 20;
        $fields['billing'][$national_id_key]['default'] = get_user_meta($user_id, $national_id_key, true);
    }

    // Company Name (billing_company) - After Last Name for Corporate
    if (isset($fields['billing']['billing_company'])) {
        $fields['billing']['billing_company']['required'] = false;
        $fields['billing']['billing_company']['class'] = array('form-row-wide', 'hidden-field', 'conditional-field', 'corporate-field');
        $fields['billing']['billing_company']['priority'] = 25;
        $fields['billing']['billing_company']['default'] = get_user_meta($user_id, 'billing_company', true);
    } else {
        $fields['billing']['billing_company'] = array(
            'type'     => 'text',
            'label'    => 'نام شرکت',
            'required' => false,
            'class'    => array('form-row-wide', 'hidden-field', 'conditional-field', 'corporate-field'),
            'priority' => 25,
            'default'  => get_user_meta($user_id, 'billing_company', true),
        );
    }

    // Phone (billing_phone) - After National ID (Individual) or Register ID (Corporate)
    if (isset($fields['billing']['billing_phone'])) {
        $fields['billing']['billing_phone']['label'] = 'تلفن';
        $fields['billing']['billing_phone']['required'] = true;
        $fields['billing']['billing_phone']['label'] = str_replace(' <span class="optional">(اختیاری)</span>', '', $fields['billing']['billing_phone']['label']);
        $fields['billing']['billing_phone']['priority'] = 40;
    }

    // Address 1 (billing_address_1)
    if (isset($fields['billing']['billing_address_1'])) {
        $fields['billing']['billing_address_1']['priority'] = 60;
    }

    // Address 2 (billing_address_2)
    if (isset($fields['billing']['billing_address_2'])) {
        $fields['billing']['billing_address_2']['priority'] = 70;
    }

    // Postcode (billing_postcode)
    if (isset($fields['billing']['billing_postcode'])) {
        $fields['billing']['billing_postcode']['priority'] = 80;
    }

    // Email (billing_email) - After Postcode
    if (isset($fields['billing']['billing_email'])) {
        $fields['billing']['billing_email']['required'] = false;
        $fields['billing']['billing_email']['label'] = str_replace(' <span class="required">*</span>', '', $fields['billing']['billing_email']['label']);
        if (strpos($fields['billing']['billing_email']['label'], '(اختیاری)') === false) {
            $fields['billing']['billing_email']['label'] .= ' <span class="optional">(اختیاری)</span>';
        }
        $fields['billing']['billing_email']['priority'] = 90;
    }

    // --- Add ACF Fields for Corporate Users ---
    $fields['billing']['co_national_id'] = array(
        'type'     => 'text',
        'label'    => 'شناسه ملی',
        'required' => false,
        'class'    => array('form-row-wide', 'hidden-field', 'conditional-field', 'corporate-field'),
        'priority' => 30,
        'default'  => $user_id ? get_field('co_national_id', 'user_' . $user_id) : '',
    );

    $fields['billing']['register_id'] = array(
        'type'     => 'text',
        'label'    => 'شناسه ثبت',
        'required' => false,
        'class'    => array('form-row-wide', 'hidden-field', 'conditional-field', 'corporate-field'),
        'priority' => 35,
        'default'  => $user_id ? get_field('register_id', 'user_' . $user_id) : '',
    );

    $fields['billing']['tel_com'] = array(
        'type'     => 'text',
        'label'    => 'شماره تلفن ثابت <span class="optional">(اختیاری)</span>',
        'required' => false,
        'class'    => array('form-row-wide', 'hidden-field', 'conditional-field', 'corporate-field'),
        'priority' => 120,
        'default'  => $user_id ? get_field('tel_com', 'user_' . $user_id) : '',
    );

    return $fields;
}

// --- 3. Add Custom Fields to My Account Edit Page ---
add_action('woocommerce_edit_account_form', 'add_custom_fields_to_edit_account_form', 1000);

function add_custom_fields_to_edit_account_form() {
    $user_id = get_current_user_id();
    $user_type = get_user_meta($user_id, 'user_type', true) ?: 'individual';
    error_log('Adding custom fields to edit account form for user ID: ' . $user_id . ', user_type: ' . $user_type);
    ?>
    <fieldset class="custom-user-fields">
        <legend><?php _e('اطلاعات کاربری', 'your-text-domain'); ?></legend>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide user-type-radio-container">
            <label><?php _e('نوع کاربری:', 'your-text-domain'); ?> <span class="required">*</span></label>
            <label><input type="radio" name="user_type" value="individual" <?php checked($user_type, 'individual'); ?>> <?php _e('حقیقی', 'your-text-domain'); ?></label>
            <label><input type="radio" name="user_type" value="corporate" <?php checked($user_type, 'corporate'); ?>> <?php _e('حقوقی', 'your-text-domain'); ?></label>
        </p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide individual-field conditional-field <?php echo $user_type !== 'individual' ? 'hidden-field' : ''; ?>" id="billing_national_id_field">
            <label for="billing_national_id"><?php _e('کدملی', 'your-text-domain'); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="billing_national_id" id="billing_national_id" value="<?php echo esc_attr(get_user_meta($user_id, 'billing_national_id', true)); ?>">
        </p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide corporate-field conditional-field <?php echo $user_type !== 'corporate' ? 'hidden-field' : ''; ?>" id="billing_company_field">
            <label for="billing_company"><?php _e('نام شرکت', 'your-text-domain'); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="billing_company" id="billing_company" value="<?php echo esc_attr(get_user_meta($user_id, 'billing_company', true)); ?>">
        </p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide corporate-field conditional-field <?php echo $user_type !== 'corporate' ? 'hidden-field' : ''; ?>" id="co_national_id_field">
            <label for="co_national_id"><?php _e('شناسه ملی', 'your-text-domain'); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="co_national_id" id="co_national_id" value="<?php echo esc_attr(get_field('co_national_id', 'user_' . $user_id)); ?>">
        </p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide corporate-field conditional-field <?php echo $user_type !== 'corporate' ? 'hidden-field' : ''; ?>" id="register_id_field">
            <label for="register_id"><?php _e('شناسه ثبت', 'your-text-domain'); ?> <span class="required">*</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="register_id" id="register_id" value="<?php echo esc_attr(get_field('register_id', 'user_' . $user_id)); ?>">
        </p>

        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide corporate-field conditional-field <?php echo $user_type !== 'corporate' ? 'hidden-field' : ''; ?>" id="tel_com_field">
            <label for="tel_com"><?php _e('شماره تلفن ثابت', 'your-text-domain'); ?> <span class="optional">(اختیاری)</span></label>
            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="tel_com" id="tel_com" value="<?php echo esc_attr(get_field('tel_com', 'user_' . $user_id)); ?>">
        </p>
    </fieldset>
    <?php
}

// --- 4. Add JavaScript and CSS for Dynamic Toggle on Edit Account Page ---
add_action('wp_footer', 'edit_account_user_type_conditional_fields_script', 100);

function edit_account_user_type_conditional_fields_script() {
    if (!is_account_page() || !is_wc_endpoint_url('edit-account')) {
        return;
    }
    ?>
    <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                console.log('Edit account JavaScript loaded');
                var userTypeRadios = $('input[name="user_type"]');
                var $billingCompanyField = $('#billing_company_field');
                var $billingCompanyInput = $('#billing_company');
                var $billingNationalIdField = $('#billing_national_id_field');
                var $billingNationalIdInput = $('#billing_national_id');
                var $coNationalIdField = $('#co_national_id_field');
                var $registerIdField = $('#register_id_field');
                var $telComField = $('#tel_com_field');
                var $coNationalIdInput = $('#co_national_id');
                var $registerIdInput = $('#register_id');
                var $telComInput = $('#tel_com');

                console.log('Found user type radios: ', userTypeRadios.length);
                if (userTypeRadios.length === 0) {
                    console.error('User type radio buttons not found!');
                    return;
                }

                function validateCompanyNationalId(co_national_id) {
                    console.log('Validating co_national_id: ' + co_national_id);
                    if (!/^\d{11}$/.test(co_national_id)) {
                        return false;
                    }
                    var digits = co_national_id.split('').map(Number);
                    var weights = [29, 27, 23, 19, 17, 29, 27, 23, 19, 247];
                    var total = 0;
                    for (var i = 0; i < 10; i++) {
                        total += digits[i] * weights[i];
                    }
                    total += 460;
                    var remainder = total % 11;
                    if (remainder === 10) {
                        remainder = 0;
                    }
                    var isValid = remainder === digits[10];
                    console.log('co_national_id validation: remainder=' + remainder + ', control_digit=' + digits[10] + ', valid=' + isValid);
                    return isValid;
                }

                function toggleUserTypeFields(selectedUserType) {
                    console.log('Toggling fields for user type: ' + selectedUserType);
                    $('.conditional-field').addClass('hidden-field');
                    $('.conditional-field input').prop('required', false).val('')
                        .closest('.form-row').removeClass('validate-required');

                    if (selectedUserType === 'individual') {
                        console.log('Showing individual fields');
                        $billingNationalIdField.removeClass('hidden-field');
                        $billingNationalIdField.find('label').html('کدملی <span class="required">*</span>');
                        $billingNationalIdInput.prop('required', true).closest('.form-row').addClass('validate-required');
                    } else if (selectedUserType === 'corporate') {
                        console.log('Showing corporate fields');
                        $billingCompanyField.removeClass('hidden-field');
                        $billingCompanyField.find('label').html('نام شرکت <span class="required">*</span>');
                        $billingCompanyInput.prop('required', true).closest('.form-row').addClass('validate-required');

                        $coNationalIdField.removeClass('hidden-field');
                        $coNationalIdField.find('label').html('شناسه ملی <span class="required">*</span>');
                        $coNationalIdInput.prop('required', true).closest('.form-row').addClass('validate-required');

                        $registerIdField.removeClass('hidden-field');
                        $registerIdField.find('label').html('شناسه ثبت <span class="required">*</span>');
                        $registerIdInput.prop('required', true).closest('.form-row').addClass('validate-required');

                        $telComField.removeClass('hidden-field');
                        $telComField.find('label').html('شماره تلفن ثابت <span class="optional">(اختیاری)</span>');
                        $telComInput.prop('required', false);
                    }
                }

                userTypeRadios.on('change', function() {
                    console.log('User type changed to: ' + $(this).val());
                    toggleUserTypeFields($(this).val());
                    $coNationalIdField.find('.national-id-error').remove();
                    $coNationalIdInput.closest('.form-row').removeClass('woocommerce-invalid');
                });

                $coNationalIdInput.on('blur', function() {
                    var value = $(this).val();
                    if (value && !validateCompanyNationalId(value)) {
                        $(this).closest('.form-row').addClass('woocommerce-invalid');
                        if (!$coNationalIdField.find('.national-id-error').length) {
                            $coNationalIdField.append('<span class="national-id-error" style="color: red;">شناسه ملی معتبر نیست</span>');
                        }
                    } else {
                        $(this).closest('.form-row').removeClass('woocommerce-invalid');
                        $coNationalIdField.find('.national-id-error').remove();
                    }
                });

                var initialUserType = $('input[name="user_type"]:checked').val() || 'individual';
                console.log('Initial user type: ' + initialUserType);
                toggleUserTypeFields(initialUserType);
            });
        })(jQuery);
    </script>
    <style type="text/css">
        .custom-user-fields .hidden-field {
            display: none !important;
        }
        .custom-user-fields .user-type-radio-container label {
            display: inline-block;
            margin-right: 20px;
        }
        .custom-user-fields .woocommerce-form-row.conditional-field {
            margin-bottom: 20px;
            display: block !important;
        }
        .custom-user-fields fieldset {
            border: 1px solid #ccc;
            padding: 20px;
            margin-bottom: 20px;
        }
        .custom-user-fields legend {
            font-weight: bold;
        }
        .woocommerce-invalid .national-id-error {
            display: block;
            margin-top: 5px;
        }
    </style>
    <?php
}

// --- 5. Validate Custom Fields on Edit Account Page ---
add_action('woocommerce_save_account_details_errors', 'validate_custom_fields_on_edit_account', 10, 2);

function validate_custom_fields_on_edit_account($errors, $user) {
    if (empty($_POST['user_type'])) {
        $errors->add('user_type_error', __('لطفاً نوع کاربری را مشخص کنید.', 'your-text-domain'));
    }

    $selected_user_type = isset($_POST['user_type']) ? sanitize_text_field($_POST['user_type']) : '';
    if ($selected_user_type === 'individual') {
        if (empty($_POST['billing_national_id'])) {
            $errors->add('billing_national_id_error', __('وارد کردن کدملی الزامی است.', 'your-text-domain'));
        }
    } elseif ($selected_user_type === 'corporate') {
        if (empty($_POST['billing_company'])) {
            $errors->add('billing_company_error', __('وارد کردن نام شرکت الزامی است.', 'your-text-domain'));
        }
        if (empty($_POST['co_national_id'])) {
            $errors->add('co_national_id_error', __('وارد کردن شناسه ملی الزامی است.', 'your-text-domain'));
        } elseif (!validate_company_national_id($_POST['co_national_id'])) {
            $errors->add('co_national_id_invalid_error', __('شناسه ملی وارد شده معتبر نیست.', 'your-text-domain'));
        }
        if (empty($_POST['register_id'])) {
            $errors->add('register_id_error', __('وارد کردن شناسه ثبت الزامی است.', 'your-text-domain'));
        }
    }
    error_log('Validation errors: ' . print_r($errors->get_error_messages(), true));
}

// --- 6. Save Custom Fields on Edit Account Page ---
add_action('woocommerce_save_account_details', 'save_custom_fields_on_edit_account', 10, 1);

function save_custom_fields_on_edit_account($user_id) {
    $user_type = isset($_POST['user_type']) ? sanitize_text_field($_POST['user_type']) : 'individual';
    error_log('Saving user_type for user ID ' . $user_id . ': ' . $user_type);
    update_user_meta($user_id, 'user_type', $user_type);

    // Save or clear billing_national_id
    if ($user_type === 'individual' && isset($_POST['billing_national_id']) && !empty($_POST['billing_national_id'])) {
        $national_id = sanitize_text_field($_POST['billing_national_id']);
        update_user_meta($user_id, 'billing_national_id', $national_id);
        error_log('Saved billing_national_id for user ID ' . $user_id . ': ' . $national_id);
    } else {
        delete_user_meta($user_id, 'billing_national_id');
        error_log('Cleared billing_national_id for user ID ' . $user_id);
    }

    // Save or clear corporate fields
    $corporate_fields = array('billing_company', 'co_national_id', 'register_id', 'tel_com');
    foreach ($corporate_fields as $field_name) {
        if ($user_type === 'corporate' && isset($_POST[$field_name]) && !empty($_POST[$field_name])) {
            $value = sanitize_text_field($_POST[$field_name]);
            if (in_array($field_name, array('co_national_id', 'register_id', 'tel_com'))) {
                update_field($field_name, $value, 'user_' . $user_id);
                error_log('Saved ACF field ' . $field_name . ' for user ID ' . $user_id . ': ' . $value);
            } else {
                update_user_meta($user_id, 'field_name', $value);
                error_log('Saved meta field ' . $field_name . ' for user ID ' . $user_id . ': ' . $value);
            }
        } else {
            if (in_array($field_name, array('co_national_id', 'register_id', 'tel_com'))) {
                update_field($field_name, '', 'user_' . $user_id);
                error_log('Cleared ACF field ' . $field_name . ' for user ID ' . $user_id);
            } else {
                delete_user_meta($user_id, $field_name);
                error_log('Cleared meta field ' . $field_name . ' for user ID ' . $user_id);
            }
        }
    }
}

// --- 7. Checkout Fields JavaScript ---
add_action('wp_footer', 'checkout_user_type_conditional_fields_script');

function checkout_user_type_conditional_fields_script() {
    if (!is_checkout() || is_wc_endpoint_url()) {
        return;
    }
    ?>
    <script type="text/javascript">
        (function($) {
            $(document).ready(function() {
                console.log('Checkout JavaScript loaded');
                var userTypeRadios = $('input[name="user_type"]');
                var $billingCompanyField = $('#billing_company_field');
                var $billingCompanyInput = $('#billing_company');
                var $billingNationalIdField = $('#billing_national_id_field');
                var $billingNationalIdInput = $('#billing_national_id');
                var $coNationalIdField = $('#co_national_id_field');
                var $registerIdField = $('#register_id_field');
                var $telComField = $('#tel_com_field');
                var $coNationalIdInput = $('#co_national_id');
                var $registerIdInput = $('#register_id');
                var $telComInput = $('#tel_com');

                function validateCompanyNationalId(co_national_id) {
                    console.log('Validating co_national_id: ' + co_national_id);
                    if (!/^\d{11}$/.test(co_national_id)) {
                        return false;
                    }
                    var digits = co_national_id.split('').map(Number);
                    var weights = [29, 27, 23, 19, 17, 29, 27, 23, 19, 247];
                    var total = 0;
                    for (var i = 0; i < 10; i++) {
                        total += digits[i] * weights[i];
                    }
                    total += 460;
                    var remainder = total % 11;
                    if (remainder === 10) {
                        remainder = 0;
                    }
                    var isValid = remainder === digits[10];
                    console.log('co_national_id validation: remainder=' + remainder + ', control_digit=' + digits[10] + ', valid=' + isValid);
                    return isValid;
                }

                function toggleUserTypeFields(selectedUserType) {
                    console.log('Toggling checkout fields for user type: ' + selectedUserType);
                    $('.conditional-field').addClass('hidden-field');
                    $('.conditional-field input').prop('required', false).val('')
                        .closest('.form-row').removeClass('validate-required');

                    if (selectedUserType === 'individual') {
                        $billingNationalIdField.removeClass('hidden-field');
                        $billingNationalIdField.find('label').html('کدملی <span class="required">*</span>');
                        $billingNationalIdInput.prop('required', true).closest('.form-row').addClass('validate-required');
                    } else if (selectedUserType === 'corporate') {
                        $billingCompanyField.removeClass('hidden-field');
                        $billingCompanyField.find('label').html('نام شرکت <span class="required">*</span>');
                        $billingCompanyInput.prop('required', true).closest('.form-row').addClass('validate-required');

                        $coNationalIdField.removeClass('hidden-field');
                        $coNationalIdField.find('label').html('شناسه ملی <span class="required">*</span>');
                        $coNationalIdInput.prop('required', true).closest('.form-row').addClass('validate-required');

                        $registerIdField.removeClass('hidden-field');
                        $registerIdField.find('label').html('شناسه ثبت <span class="required">*</span>');
                        $registerIdInput.prop('required', true).closest('.form-row').addClass('validate-required');

                        $telComField.removeClass('hidden-field');
                        $telComField.find('label').html('شماره تلفن ثابت <span class="optional">(اختیاری)</span>');
                        $telComInput.prop('required', false);
                    }

                    $(document.body).trigger('updated_checkout');
                }

                userTypeRadios.on('change', function() {
                    console.log('Checkout user type changed to: ' + $(this).val());
                    toggleUserTypeFields($(this).val());
                    $coNationalIdField.find('.national-id-error').remove();
                    $coNationalIdInput.closest('.form-row').removeClass('woocommerce-invalid');
                });

                $coNationalIdInput.on('blur', function() {
                    var value = $(this).val();
                    if (value && !validateCompanyNationalId(value)) {
                        $(this).closest('.form-row').addClass('woocommerce-invalid');
                        if (!$coNationalIdField.find('.national-id-error').length) {
                            $coNationalIdField.append('<span class="national-id-error" style="color: red;">شناسه ملی معتبر نیست</span>');
                        }
                    } else {
                        $(this).closest('.form-row').removeClass('woocommerce-invalid');
                        $coNationalIdField.find('.national-id-error').remove();
                    }
                });

                var initialUserType = $('input[name="user_type"]:checked').val() || 'individual';
                console.log('Initial checkout user type: ' + initialUserType);
                toggleUserTypeFields(initialUserType);
            });
        })(jQuery);
    </script>
    <?php
}

// --- 8. Server-Side Validation for Checkout ---
add_action('woocommerce_checkout_process', 'validate_conditional_checkout_fields');

function validate_conditional_checkout_fields() {
    if (empty($_POST['user_type'])) {
        wc_add_notice(__('لطفاً نوع کاربری را مشخص کنید.', 'your-text-domain'), 'error');
        return;
    }

    $selected_user_type = sanitize_text_field($_POST['user_type']);
    if ($selected_user_type === 'individual') {
        if (empty($_POST['billing_national_id'])) {
            wc_add_notice(__('وارد کردن کدملی الزامی است.', 'your-text-domain'), 'error');
        }
    } elseif ($selected_user_type === 'corporate') {
        if (empty($_POST['billing_company'])) {
            wc_add_notice(__('وارد کردن نام شرکت الزامی است.', 'your-text-domain'), 'error');
        }
        if (empty($_POST['co_national_id'])) {
            wc_add_notice(__('وارد کردن شناسه ملی الزامی است.', 'your-text-domain'), 'error');
        } elseif (!validate_company_national_id($_POST['co_national_id'])) {
            wc_add_notice(__('شناسه ملی وارد شده معتبر نیست.', 'your-text-domain'), 'error');
        }
        if (empty($_POST['register_id'])) {
            wc_add_notice(__('وارد کردن شناسه ثبت الزامی است.', 'your-text-domain'), 'error');
        }
    }
    error_log('Checkout validation for user type: ' . $selected_user_type);
}

// --- 9. Save Custom Fields to Order Meta ---
add_action('woocommerce_checkout_update_order_meta', 'save_custom_checkout_fields_to_order_meta');

function save_custom_checkout_fields_to_order_meta($order_id) {
    $selected_user_type = isset($_POST['user_type']) ? sanitize_text_field($_POST['user_type']) : '';
    if (!empty($selected_user_type)) {
        update_post_meta($order_id, '_user_type', $selected_user_type);
        error_log('Saved _user_type for order ID ' . $order_id . ': ' . $selected_user_type);
    }

    $national_id_key = 'billing_national_id';
    if ($selected_user_type === 'individual' && isset($_POST[$national_id_key]) && !empty($_POST[$national_id_key])) {
        $national_id = sanitize_text_field($_POST[$national_id_key]);
        update_post_meta($order_id, '_' . $national_id_key, $national_id);
        error_log('Saved _' . $national_id_key . ' for order ID ' . $order_id . ': ' . $national_id);
    }

    $corporate_acf_fields = array('co_national_id', 'register_id', 'tel_com');
    if ($selected_user_type === 'corporate') {
        foreach ($corporate_acf_fields as $field_name) {
            if (isset($_POST[$field_name]) && !empty($_POST[$field_name])) {
                $value = sanitize_text_field($_POST[$field_name]);
                update_post_meta($order_id, '_' . $field_name, $value);
                error_log('Saved _' . $field_name . ' for order ID ' . $order_id . ': ' . $value);
            }
        }
    }
}

// --- 10. Save Custom Fields to User Meta ---
add_action('woocommerce_checkout_order_processed', 'save_custom_checkout_fields_to_user_meta', 10, 3);

function save_custom_checkout_fields_to_user_meta($order_id, $posted_data, $order) {
    $user_id = $order->get_customer_id();
    if ($user_id > 0 && function_exists('update_field')) {
        $selected_user_type = isset($_POST['user_type']) ? sanitize_text_field($_POST['user_type']) : '';
        if (!empty($selected_user_type)) {
            update_user_meta($user_id, 'user_type', $selected_user_type);
            error_log('Saved user_type for user ID ' . $user_id . ': ' . $selected_user_type);
        }

        if ($selected_user_type === 'individual' && isset($_POST['billing_national_id']) && !empty($_POST['billing_national_id'])) {
            $national_id = sanitize_text_field($_POST['billing_national_id']);
            update_user_meta($user_id, 'billing_national_id', $national_id);
            error_log('Saved billing_national_id for user ID ' . $user_id . ': ' . $national_id);
        } else {
            delete_user_meta($user_id, 'billing_national_id');
            error_log('Cleared billing_national_id for user ID ' . $user_id);
        }

        $corporate_acf_fields = array('co_national_id', 'register_id', 'tel_com');
        if ($selected_user_type === 'corporate') {
            foreach ($corporate_acf_fields as $field_name) {
                if (isset($_POST[$field_name]) && !empty($_POST[$field_name])) {
                    $value = sanitize_text_field($_POST[$field_name]);
                    update_field($field_name, $value, 'user_' . $user_id);
                    error_log('Saved ACF field ' . $field_name . ' for user ID ' . $user_id . ': ' . $value);
                } else {
                    update_field($field_name, '', 'user_' . $user_id);
                    error_log('Cleared ACF field ' . $field_name . ' for user ID ' . $user_id);
                }
            }
        } else {
            foreach ($corporate_acf_fields as $field_name) {
                update_field($field_name, '', 'user_' . $user_id);
                error_log('Cleared ACF field ' . $field_name . ' for user ID ' . $user_id);
            }
        }
    }
}

// --- 11. Display Custom Fields on Admin Order Edit Page ---
add_action('woocommerce_admin_order_data_after_billing_address', 'display_custom_fields_on_admin_order_page', 10, 1);

function display_custom_fields_on_admin_order_page($order) {
    $user_type_saved_value = $order->get_meta('_user_type');
    $user_type_display = '';
    if ('individual' === $user_type_saved_value) {
        $user_type_display = 'حقیقی';
    } elseif ('corporate' === $user_type_saved_value) {
        $user_type_display = 'حقوقی';
    }

    if (!empty($user_type_display)) {
        echo '<p class="form-field form-field-wide wc-customer-user">';
        echo '<strong>' . __('نوع کاربری', 'your-text-domain') . ':</strong> ' . esc_html($user_type_display);
        echo '</p>';
    }

    $company_name_value = $order->get_meta('_billing_company');
    if (!empty($company_name_value)) {
        echo '<p class="form-field form-field-wide">';
        echo '<strong>' . __('نام شرکت', 'your-text-domain') . ':</strong> ' . esc_html($company_name_value);
        echo '</p>';
    }

    $national_id_order_meta_key = '_billing_national_id';
    $national_id_value = $order->get_meta($national_id_order_meta_key);
    if (!empty($national_id_value)) {
        echo '<p class="form-field form-field-wide">';
        echo '<strong>' . __('کدملی', 'your-text-domain') . ':</strong> ' . esc_html($national_id_value);
        echo '</p>';
    }

    $corporate_fields_to_display = array(
        '_co_national_id' => 'شناسه ملی',
        '_register_id'    => 'شناسه ثبت',
        '_tel_com'        => 'شماره تلفن ثابت',
    );

    foreach ($corporate_fields_to_display as $meta_key => $label) {
        $value = $order->get_meta($meta_key);
        if (!empty($value)) {
            echo '<p class="form-field form-field-wide">';
            echo '<strong>' . esc_html($label) . ':</strong> ' . esc_html($value);
            echo '</p>';
        }
    }
}