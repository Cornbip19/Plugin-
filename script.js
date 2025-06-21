<?phpy(document).ready(function($) {
/*  // Update current time every second
Plugin Name: Worker Time Tracker
Description: A plugin to track time, compensation transparency, and attendance, with admin dashboard and user interface.
Version: 3.7.4options = { timeZone: 'Asia/Manila', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
Author: Cornbip-19String = now.toLocaleTimeString('en-US', options);
*/      $('#uttd-current-time').text(timeString);
    }
// Prevent direct access
if (!defined('ABSPATH')) {, 1000);
    exit;
}   // Check if current time is during working hours
    function isDuringWorkingHours() {
// Set timezone to Asia/Manila on plugin activation_time) {
function uttd_set_timezone() {
    update_option('timezone_string', 'Asia/Manila');
    error_log('UTTD: Set timezone to Asia/Manila');
}       const now = new Date();
register_activation_hook(__FILE__, 'uttd_set_timezone'); + now.getMinutes();

// Create database tables on plugin activationd_ajax.start_time.split(":").map(Number);
function uttd_create_tables() {utes] = uttd_ajax.end_time.split(":").map(Number);
    global $wpdb;rtTimeInMinutes = startHours * 60 + startMinutes;
    $charset_collate = $wpdb->get_charset_collate();Minutes;

    $table_name = $wpdb->prefix . 'user_time_logs'; endTimeInMinutes;
    $sql = "CREATE TABLE $table_name (se;
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        login_time datetime NOT NULL,>= startTimeInMinutes || currentTimeInMinutes < endTimeInMinutes) {
        logout_time datetime,ngHours = true;
        amount decimal(10,2) DEFAULT 0.00,
        early_out_reason text,
        paid_status enum('unpaid','paid') DEFAULT 'unpaid',&& currentTimeInMinutes < endTimeInMinutes) {
        PRIMARY KEY (id)WorkingHours = true;
    ) $charset_collate;";
        }
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Add early_out_reason and paid_status columns if they don't exist
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'early_out_reason'");
    if (empty($columns)) {;
        $wpdb->query("ALTER TABLE $table_name ADD early_out_reason TEXT");
        error_log('UTTD: Added early_out_reason column to user_time_logs table');
    }   console.log('Start Shift button clicked'); // Debugging log

    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'paid_status'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD paid_status ENUM('unpaid','paid') DEFAULT 'unpaid'");
        error_log('UTTD: Added paid_status column to user_time_logs table');
    }       alert('An error occurred. Please contact the administrator.');
}           $button.prop('disabled', false).text('Start Shift');
register_activation_hook(__FILE__, 'uttd_create_tables');
        }
// Create worker dashboard page on activation
function uttd_create_dashboard_page() {
    $page_title = 'Worker Dashboard';
    $page_content = '[uttd_dashboard]';
    $page_check = get_page_by_title($page_title);
                action: 'uttd_start_timer',
    if (!$page_check) {uttd_ajax.nonce,
        $page = array(
            'post_title'   => $page_title,
            'post_content' => $page_content, response);
            'post_status'  => 'publish',
            'post_type'    => 'page', {
            'post_author'  => 1, started successfully!');
            'post_name'    => 'worker-dashboard' to reflect changes
        );      } else {
        $page_id = wp_insert_post($page);response:', response.data);
        if ($page_id && !is_wp_error($page_id)) {ta);
            update_post_meta($page_id, '_wp_page_template', 'worker-dashboard.php');
        } else {}
            error_log('UTTD: Failed to create worker dashboard page');
        }   error: function (xhr, status, error) {
    }           console.error('AJAX error:', status, error);
}               alert('An error occurred. Please try again.');
register_activation_hook(__FILE__, 'uttd_create_dashboard_page');');
            },
// Add worker role on activation
function uttd_add_user_role() {
    add_role(
        'worker',
        'Worker',n('click', '#uttd-end-timer', function(e) {
        array(entDefault();
            'read' => true,his);
        )button.prop('disabled', true).text('Ending Shift...');
    );
}       let earlyOutReason = '';
register_activation_hook(__FILE__, 'uttd_add_user_role');
            earlyOutReason = prompt('You are ending your shift early. Please provide a reason for early departure:');
// Disable user registration for non-admins
function uttd_disable_user_registration() {lse).text('End Shift');
    update_option('users_can_register', false);
}           }
register_activation_hook(__FILE__, 'uttd_disable_user_registration');
                alert('Please provide a valid reason for early departure.');
// Register custom templatep('disabled', false).text('End Shift');
function uttd_register_template($templates) {
    $templates['worker-dashboard.php'] = 'Worker Dashboard Template';
    return $templates;
}
add_filter('theme_page_templates', 'uttd_register_template');
            url: uttd_ajax.ajax_url,
// Force custom template loading
function uttd_load_custom_template($template) {
    if (is_page('worker-dashboard')) {r',
        $custom_template = plugin_dir_path(__FILE__) . 'templates/worker-dashboard.php';
        if (file_exists($custom_template)) {ason
            error_log('UTTD: Loading custom template for worker-dashboard: ' . $custom_template);
            return $custom_template;se) {
        } else {if (response.success) {
            error_log('UTTD: Custom template not found: ' . $custom_template);ata.amount.toFixed(2));
        }           location.reload();
    }           } else {
    return $template;lert('Error: ' + response.data);
}                   $button.prop('disabled', false).text('End Shift');
add_filter('template_include', 'uttd_load_custom_template', 99);
            },
// Enqueue scripts and styles for front-endror) {
function uttd_enqueue_scripts() {AX error:', status, error);
    global $post;lert('An error occurred. Please try again.');
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'uttd_dashboard') || is_page('worker-dashboard'))) {
        $style_url = plugin_dir_url(__FILE__) . 'style/style.css';
        $script_url = plugin_dir_url(__FILE__) . 'script/script.js';
    });
        wp_enqueue_style('uttd-style', $style_url, array(), '3.7.4', 'all');
        wp_enqueue_script('uttd-script', $script_url, array('jquery'), '3.7.4', true);
        uttd-add-worker-card').on('click', function() {
        $user_id = get_current_user_id();n();
        $start_time = get_user_meta($user_id, 'uttd_start_time', true);
        $end_time = get_user_meta($user_id, 'uttd_end_time', true);
    // Admin: Open worker details popup
        wp_localize_script('uttd-script', 'uttd_ajax', array() {
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('uttd_nonce'),
            'start_time' => $start_time,l,
            'end_time' => $end_time
        )); data: {
                action: 'uttd_get_worker_details',
        error_log('UTTD: Enqueuing style: ' . $style_url);
        error_log('UTTD: Enqueuing script: ' . $script_url);
    }       },
}           success: function(response) {
add_action('wp_enqueue_scripts', 'uttd_enqueue_scripts', 999);
                    $('#uttd-worker-details-content').html(response.data);
// Enqueue scripts and styles for adminails-popup').fadeIn();
function uttd_enqueue_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_uttd-admin') {
        return;     alert('Error: ' + response.data);
    }           }
            },
    $style_url = plugin_dir_url(__FILE__) . 'style/style.css';
    $script_url = plugin_dir_url(__FILE__) . 'script/script.js';
                alert('An error occurred while fetching worker details.');
    wp_enqueue_style('uttd-admin-style', $style_url, array(), '3.7.4', 'all');
    wp_enqueue_script('uttd-admin-script', $script_url, array('jquery'), '3.7.4', true);
    });
    $ajax_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('uttd_admin_nonce')
    );  $(this).closest('.uttd-popup').fadeOut();
    wp_localize_script('uttd-admin-script', 'uttd_admin_ajax', $ajax_data);

    error_log('UTTD: Enqueuing admin style: ' . $style_url);
    error_log('UTTD: Enqueuing admin script: ' . $script_url);
}       if ($(e.target).hasClass('uttd-popup')) {
add_action('admin_enqueue_scripts', 'uttd_enqueue_admin_scripts');
        }
// Worker dashboard shortcode
function uttd_user_dashboard_shortcode() {
    if (!is_user_logged_in()) {ity
        return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your dashboard.</p>';
    }   $('.uttd-tab-button').off('click').on('click', function() {
            const tab = $(this).data('tab');
    $user = wp_get_current_user();d-tab-button').removeClass('active');
    if (!in_array('worker', (array) $user->roles)) {
        return '<p>This dashboard is only accessible to users with the Worker role.</p>';
    }       $(this).closest('.uttd-logs-section').find('.uttd-tab-content').hide();
            $(this).closest('.uttd-logs-section').find('#' + tab + '-logs').show();
    ob_start();
    $user_id = get_current_user_id();
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_time_logs';
    bindTabEvents();
    $today = date('Y-m-d');    $today_log = $wpdb->get_row(        $wpdb->prepare(            "SELECT * FROM $table_name WHERE user_id = %d AND DATE(login_time) = %s AND logout_time IS NULL",            $user_id,            $today        )    );    // Get daily rate, working hours, and position    $daily_rate = floatval(get_user_meta($user_id, 'uttd_daily_rate', true)) ?: 0.00;    $start_time = get_user_meta($user_id, 'uttd_start_time', true);    $end_time = get_user_meta($user_id, 'uttd_end_time', true);    $start_time_display = $start_time ? date('h:i A', strtotime($start_time)) : 'Not set';    $end_time_display = $end_time ? date('h:i A', strtotime($end_time)) : 'Not set';    $position = get_user_meta($user_id, 'uttd_position', true) ?: 'Not assigned';    // Calculate early/late status if shift is active    $arrival_message = '';    if ($today_log && !$today_log->logout_time && $start_time) {        $shift_start = new DateTime($today . ' ' . $start_time, new DateTimeZone('Asia/Manila'));        $actual_start = new DateTime($today_log->login_time, new DateTimeZone('Asia/Manila'));        if ($start_time > $end_time) {            if ($actual_start->format('H:i') < $end_time) {                $shift_start->modify('-1 day');            }        }        $interval = $actual_start->diff($shift_start);        $minutes = ($interval->h * 60) + $interval->i;        if ($actual_start < $shift_start) {            $hours = floor($minutes / 60);            $mins = $minutes % 60;            $time_str = $hours > 0 ? "$hours hr" . ($hours > 1 ? 's' : '') : '';            $time_str .= $hours > 0 && $mins > 0 ? ' ' : '';            $time_str .= $mins > 0 ? "$mins min" . ($mins > 1 ? 's' : '') : '';            $arrival_message = "You are $time_str before your shift.";        } elseif ($actual_start > $shift_start) {            $hours = floor($minutes / 60);            $mins = $minutes % 60;            $time_str = $hours > 0 ? "$hours hr" . ($hours > 1 ? 's' : '') : '';            $time_str .= $hours > 0 && $mins > 0 ? ' ' : '';            $time_str .= $mins > 0 ? "$mins min" . ($mins > 1 ? 's' : '') : '';            $arrival_message = "You are $time_str late to your shift.";        } else {            $arrival_message = "You started your shift on time.";        }    }    ?>    <div class="uttd-wrapper">        <div class="uttd-dashboard">            <div class="uttd-header">                <h2>Welcome, <?php echo esc_html($user->display_name); ?></h2>                <p><strong>Position:</strong> <?php echo esc_html($position); ?></p>                <a href="<?php echo wp_logout_url(home_url()); ?>" class="uttd-logout-button">Logout</a>            </div>                        <div class="uttd-analytics-grid">                <div class="uttd-card uttd-date-card">                    <h3>Today's Date</h3>                    <p id="uttd-current-date"><?php echo date('F j, Y'); ?></p>                </div>                <div class="uttd-card uttd-time-card">                    <h3>Current Time (PH)</h3>                    <p id="uttd-current-time"></p>                </div>                <div class="uttd-card uttd-shift-card">                    <h3>Shift Status</h3>                    <div class="uttd-timer-section">                        <?php if ($today_log && !$today_log->logout_time): ?>                            <button id="uttd-end-timer" class="uttd-button uttd-button-end">End Shift</button>                            <?php if ($arrival_message): ?>                                <p class="uttd-arrival-message"><?php echo esc_html($arrival_message); ?></p>                            <?php endif; ?>                        <?php else: ?>                            <button id="uttd-start-timer" class="uttd-button uttd-button-start">Start Shift</button>                        <?php endif; ?>                    </div>                </div>                <div class="uttd-card uttd-rate-card">                    <h3>Your Daily Rate</h3>                    <p>₱<?php echo number_format($daily_rate, 2); ?></p>                </div>                <div class="uttd-card uttd-hours-card">                    <h3>Working Hours</h3>                    <p><?php echo esc_html($start_time_display . ' - ' . $end_time_display); ?></p>                </div>            </div>            <div class="uttd-logs-section">                <h3>Your Work Logs</h3>                <div class="uttd-tabs">                    <button class="uttd-tab-button active" data-tab="current">Current Logs</button>                    <button class="uttd-tab-button" data-tab="paid">Paid Logs</button>                </div>                <div class="uttd-tab-content" id="current-logs">                    <table class="uttd-history-table">                        <thead>                            <tr>                                <th>Date</th>                                <th>Start Time</th>                                <th>End Time</th>                                <th>Earnings</th>                                <th>Reason for Early Out</th>                            </tr>                        </thead>                        <tbody>                            <?php                            $logs = $wpdb->get_results(                                $wpdb->prepare(                                    "SELECT * FROM $table_name WHERE user_id = %d AND paid_status = 'unpaid' ORDER BY login_time DESC LIMIT 10",                                    $user_id                                )                            );                            if (empty($logs)): ?>                                <tr><td colspan="5">No unpaid work logs available.</td></tr>                            <?php else: ?>                                <?php foreach ($logs as $log): ?>                                    <tr>                                        <td><?php echo date('M d, Y', strtotime($log->login_time)); ?></td>                                        <td><?php echo date('h:i A', strtotime($log->login_time)); ?></td>                                        <td><?php echo $log->logout_time ? date('h:i A', strtotime($log->logout_time)) : '-'; ?></td>                                        <td>₱<?php echo number_format($log->amount, 2); ?></td>                                        <td><?php echo !empty($log->early_out_reason) ? esc_html($log->early_out_reason) : '-'; ?></td>                                    </tr>                                <?php endforeach; ?>                            <?php endif; ?>                        </tbody>                    </table>                    <?php if (!empty($logs)): ?>                        <div class="uttd-totals">                            <p><strong>Total Unpaid Earnings:</strong> ₱<?php echo number_format($total_amount, 2); ?></p>                        </div>                    <?php endif; ?>                </div>                <div class="uttd-tab-content" id="paid-logs" style="display: none;">                    <table class="uttd-history-table">                        <thead>                            <tr>                                <th>Date</th>                                <th>Start Time</th>                                <th>End Time</th>                                <th>Earnings</th>                                <th>Reason for Early Out</th>                            </tr>                        </thead>                        <tbody>                            <?php                            $paid_logs = $wpdb->get_results(                                $wpdb->prepare(                                    "SELECT * FROM $table_name WHERE user_id = %d AND paid_status = 'paid' ORDER BY login_time DESC LIMIT 10",                                    $user_id                                )                            );                                                        if (empty($paid_logs)) {                                echo '<tr><td colspan="5">No paid work logs available.</td></tr>';                            } else {                                $total_paid_amount = 0;                                foreach ($paid_logs as $log) {                                    $amount = floatval($log->amount);                                    $total_paid_amount += $amount;                                    ?>                                    <tr>                                        <td><?php echo date('M d, Y', strtotime($log->login_time)); ?></td>                                        <td><?php echo date('h:i A', strtotime($log->login_time)); ?></td>                                        <td><?php echo $log->logout_time ? date('h:i A', strtotime($log->logout_time)) : '-'; ?></td>                                        <td>₱<?php echo number_format($amount, 2); ?></td>                                        <td><?php echo !empty($log->early_out_reason) ? esc_html($log->early_out_reason) : '-'; ?></td>                                    </tr>                                    <?php                                }                            }                            ?>                        </tbody>                    </table>                    <?php if (!empty($paid_logs)): ?>                        <div class="uttd-totals">                            <p><strong>Total Paid Earnings:</strong> ₱<?php echo number_format($total_paid_amount, 2); ?></p>                        </div>                    <?php endif; ?>                </div>            </div>        </div>    </div>    <?php        return ob_get_clean();}        'manage_options',        'uttd-admin',        'uttd_admin_page_content',        'dashicons-clock'    );}add_action('admin_menu', 'uttd_admin_menu');function uttd_admin_page_content() {    global $wpdb;    $table_name = $wpdb->prefix . 'user_time_logs';        // Handle user creation        $email = sanitize_email($_POST['uttd_email']);        $password = $_POST['uttd_password'];        $daily_rate = floatval($_POST['uttd_daily_rate']);        $start_time = sanitize_text_field($_POST['uttd_start_time']);        $end_time = sanitize_text_field($_POST['uttd_end_time']);        $position = sanitize_text_field($_POST['uttd_position']);                if (empty($password)) {            echo '<div class="notice notice-error"><p>Please provide a password.</p></div>';        } else {            $user_id = wp_create_user($username, $password, $email);                        if (!is_wp_error($user_id)) {                $user = new WP_User($user_id);                $user->set_role('worker');                                update_user_meta($user_id, 'uttd_daily_rate', $daily_rate);                update_user_meta($user_id, 'uttd_start_time', $start_time);                update_user_meta($user_id, 'uttd_end_time', $end_time);                update_user_meta($user_id, 'uttd_position', $position);                                $start_time_display = date('h:i A', strtotime($start_time));                $end_time_display = date('h:i A', strtotime($end_time));                                wp_mail(                    $email,                    'Your New Account Credentials',                    "Username: $username\nPassword: $password\nPosition: $position\nDaily Rate: ₱" . number_format($daily_rate, 2) . "\nWorking Hours: $start_time_display - $end_time_display\n\nLogin at: " . wp_login_url() . "\nDashboard: " . home_url('/worker-dashboard')                );                echo '<div class="notice notice-success"><p>User created successfully! Credentials sent to user email.</p></div>';            } else {                echo '<div class="notice notice-error"><p>Error creating user: ' . $user_id->get_error_message() . '</p></div>';            }        }    }        // Handle rate update    if (isset($_POST['uttd_update_rate']) && check_admin_referer('uttd_update_rate_action')) {        $user_id = intval($_POST['uttd_user_id']);        $new_rate = floatval($_POST['uttd_new_rate']);                if ($new_rate < 0) {            echo '<div class="notice notice-error"><p>Rate cannot be negative.</p></div>';        } else {            $updated = update_user_meta($user_id, 'uttd_daily_rate', $new_rate);            if ($updated) {                echo '<div class="notice notice-success"><p>Rate updated successfully for user ID ' . esc_html($user_id) . '!</p></div>';            } else {                echo '<div class="notice notice-error"><p>Failed to update rate for user ID ' . esc_html($user_id) . '.</p></div>';            }        }    }        // Handle working hours update    if (isset($_POST['uttd_update_hours']) && check_admin_referer('uttd_update_hours_action')) {        $user_id = intval($_POST['uttd_user_id']);        $new_start_time = sanitize_text_field($_POST['uttd_new_start_time']);        $new_end_time = sanitize_text_field($_POST['uttd_new_end_time']);                if (empty($new_start_time) || empty($new_end_time)) {            echo '<div class="notice notice-error"><p>Please provide both start and end times.</p></div>';        } else {            $start_updated = update_user_meta($user_id, 'uttd_start_time', $new_start_time);            $end_updated = update_user_meta($user_id, 'uttd_end_time', $new_end_time);                        if ($start_updated && $end_updated) {                echo '<div class="notice notice-success"><p>Working hours updated successfully for user ID ' . esc_html($user_id) . '!</p></div>';            } else {                echo '<div class="notice notice-error"><p>Failed to update working hours for user ID ' . esc_html($user_id) . '.</p></div>';            }        }    }        // Handle position update    if (isset($_POST['uttd_update_position']) && check_admin_referer('uttd_update_position_action')) {        $user_id = intval($_POST['uttd_user_id']);        $new_position = sanitize_text_field($_POST['uttd_new_position']);                if (empty($new_position)) {            echo '<div class="notice notice-error"><p>Position cannot be empty.</p></div>';        } else {            $updated = update_user_meta($user_id, 'uttd_position', $new_position);            if ($updated) {                echo '<div class="notice notice-success"><p>Position updated successfully for user ID ' . esc_html($user_id) . '!</p></div>';            } else {                echo '<div class="notice notice-error"><p>Failed to update position for user ID ' . esc_html($user_id) . '.</p></div>';            }        }    }        // Handle mark as paid    if (isset($_POST['uttd_mark_paid']) && check_admin_referer('uttd_mark_paid_action')) {        $user_id = intval($_POST['uttd_user_id']);        $pay_period = sanitize_text_field($_POST['uttd_pay_period']);                $today = new DateTime('now', new DateTimeZone('Asia/Manila'));        $current_week_start = clone $today;        $current_week_start->modify('monday this week');        $current_week_start_str = $current_week_start->format('Y-m-d 00:00:00');                $period_start = clone $today;        if ($pay_period === 'weekly') {            $period_start->modify('-1 week');        } elseif ($pay_period === 'biweekly') {            $period_start->modify('-2 weeks');        } elseif ($pay_period === 'monthly') {            $period_start->modify('-1 month');        }        $period_start_str = $period_start->format('Y-m-d 00:00:00');                $result = $wpdb->update(            $table_name,            array('paid_status' => 'paid'),            array(                'user_id' => $user_id,                'paid_status' => 'unpaid',