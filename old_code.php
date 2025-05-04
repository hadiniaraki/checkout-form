/**
 * Add ACF 'user_type' radio field to WooCommerce checkout billing form
 * above first name, set default, custom label, and save it.
 */

// اطمینان از فعال بودن ACF و WooCommerce
if ( ! function_exists( 'acf_render_field' ) || ! class_exists( 'WooCommerce' ) ) {
    return;
}

// 1. اضافه کردن فیلد 'user_type' (Radio) به آرایه فیلدهای صورتحساب (Billing) و تعیین موقعیت آن
add_filter( 'woocommerce_checkout_fields', 'add_user_type_field_to_billing_fields' );

function add_user_type_field_to_billing_fields( $fields ) {
    // تعریف فیلد سفارشی 'user_type' به عنوان Radio Box
    $user_type_field = array(
        'type'     => 'radio',        // نوع فیلد: رادیو باکس
        'label'    => 'کاربر:',       // لیبل مورد نظر شما (ووکامرس * اجباری را اضافه می‌کند)
        'required' => true,           // فیلد اجباری باشد
        'class'    => array( 'form-row-wide', 'user-type-radio-container' ), // کلاس استاندارد + یک کلاس سفارشی برای استایل‌دهی
        'options'  => array(          // گزینه‌های رادیو باکس (با لیبل‌های فارسی کنار دکمه‌ها)
            'individual' => 'حقیقی',
            'corporate'  => 'حقوقی',
        ),
        'default'  => 'individual',   // تنظیم مقدار پیش‌فرض به 'individual'
        // 'priority' => 5, // می‌توانید با priority هم موقعیت را تا حدودی کنترل کنید (اولویت پایین‌تر = بالاتر)
                          // اما درج در آرایه دقیق‌تر و مطمئن‌تر است برای قرارگیری در ابتدا.
    );

    // نام فیلد در آرایه fields['billing'] را برابر با نام فیلد ACF ('user_type') قرار می‌دهیم.
    // قرار دادن فیلد در ابتدای آرایه $fields['billing'] برای نمایش بالای نام و نام خانوادگی
    // از عملگر + برای ادغام آرایه‌ها استفاده می‌کنیم که اگر کلیدی در آرایه سمت راست نبود اضافه می‌شود
    // و ترتیب آرایه سمت چپ (که فیلد جدید اول آن است) حفظ می‌شود.
    $fields['billing'] = array( 'user_type' => $user_type_field ) + $fields['billing'];

    // نیازی به مرتب‌سازی پیچیده با array_search و slice/merge نیست،
    // عملگر + به تنهایی فیلد جدید را در ابتدای آرایه اضافه می‌کند.

    return $fields;
}

// 2. اعتبارسنجی فیلد 'user_type' هنگام ارسال فرم Checkout
// این فیلد اجباری است و پیش‌فرض دارد، اما این اعتبارسنجی به عنوان یک لایه اطمینان باقی می‌ماند.
add_action('woocommerce_checkout_process', 'validate_user_type_field_on_checkout');

function validate_user_type_field_on_checkout() {
    // بررسی می‌کنیم که مقدار فیلد 'user_type' در داده‌های ارسالی وجود داشته باشد و خالی نباشد
    // با توجه به اینکه پیش‌فرض دارد و اجباری است، این شرط معمولاً فقط در حالت‌های خاص اتفاق می‌افتد.
    if ( empty( $_POST['user_type'] ) ) {
        // اگر خالی بود، یک پیغام خطا به WooCommerce اضافه می‌کنیم
        wc_add_notice( __( 'لطفاً نوع کاربری را مشخص کنید.', 'your-text-domain' ), 'error' );
    }
}


// 3. ذخیره مقدار فیلد 'user_type' در متادیتای سفارش (Order Meta)
// این کار باعث می‌شود مقدار انتخابی در جزئیات سفارش هم نمایش داده شود.
add_action('woocommerce_checkout_update_order_meta', 'save_user_type_field_to_order_meta');

function save_user_type_field_to_order_meta( $order_id ) {
    if ( isset( $_POST['user_type'] ) && ! empty( $_POST['user_type'] ) ) {
        // ذخیره مقدار فیلد در متادیتای سفارش با کلید _user_type
        update_post_meta( $order_id, '_user_type', sanitize_text_field( $_POST['user_type'] ) );
    }
}

// 4. ذخیره مقدار فیلد 'user_type' در متادیتای کاربر (User Meta) با استفاده از ACF
// این کار باعث می‌شود مقدار انتخابی در پروفایل کاربری (اگر لاگین باشد یا ثبت نام کند) ذخیره شود.
add_action('woocommerce_checkout_order_processed', 'save_user_type_field_to_user_meta', 10, 3);

function save_user_type_field_to_user_meta( $order_id, $posted_data, $order ) {
    // شناسه کاربری مرتبط با این سفارش را می‌گیریم (اگر لاگین باشد یا ثبت نام کرده باشد)
    $user_id = $order->get_customer_id(); // برای مهمان‌ها 0 برمی‌گرداند

    // اگر کاربر لاگین بود یا در همین فرآیند ثبت نام کرد و فیلد ارسال شده بود
    if ( $user_id > 0 && isset( $_POST['user_type'] ) && ! empty( $_POST['user_type'] ) ) {
         // بررسی وجود تابع update_field از ACF
         if ( function_exists( 'update_field' ) ) {
            // مقدار فیلد را با استفاده از تابع update_field ACF برای کاربر ذخیره می‌کنیم.
            // 'user_' . $user_id فرمت صحیح برای ذخیره فیلد برای یک کاربر خاص در ACF است.
            update_field( 'user_type', sanitize_text_field( $_POST['user_type'] ), 'user_' . $user_id );
         }
    }
    // توجه: اگر کاربر به صورت مهمان خرید کند و لاگین نباشد یا ثبت نام نکند، این مقدار در متادیتای کاربر ذخیره نخواهد شد، فقط در متادیتای سفارش.
}

// 5. نمایش مقدار ذخیره شده فیلد 'user_type' در صفحه ویرایش سفارش مدیریت وردپرس
// این بخش نیازی به تغییر ندارد و با مقادیر ذخیره شده کار می‌کند
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_user_type_on_admin_order_page', 10, 1 );

/**
 * Display the 'user_type' custom field on the admin order page after billing address.
 *
 * @param WC_Order $order The order object.
 */
function display_user_type_on_admin_order_page( $order ) {
    // مقدار ذخیره شده فیلد 'user_type' را از متادیتای سفارش دریافت می‌کنیم
    // توجه کنید که با کلید _user_type ذخیره شده است (با یک آندرلاین در ابتدا)
    $user_type_saved_value = $order->get_meta( '_user_type' );

    // اگر مقداری ذخیره شده بود، آن را نمایش می‌دهیم
    if ( ! empty( $user_type_saved_value ) ) {
        // ترجمه مقدار ذخیره شده برای نمایش کاربرپسند
        $display_text = '';
        switch ( $user_type_saved_value ) {
            case 'individual':
                $display_text = 'حقیقی';
                break;
            case 'corporate':
                $display_text = 'حقوقی';
                break;
            default:
                // اگر مقدار ناشناخته‌ای ذخیره شده بود، همان مقدار ذخیره شده را نشان می‌دهیم
                $display_text = $user_type_saved_value;
                break;
        }

        // نمایش مقدار در یک پاراگراف با استایل‌دهی مدیریت ووکامرس
        echo '<p class="form-field form-field-wide wc-customer-user">'; // استفاده از کلاس‌های استاندارد ووکامرس برای همخوانی ظاهری
        echo '<strong>' . __( 'نوع کاربری', 'your-text-domain' ) . ':</strong> ' . esc_html( $display_text );
        echo '</p>';
    }
}