<?php
/*
Plugin Name: Worker Time Tracker
Description: A plugin to track time, compensation transparency, and attendance, with admin dashboard and user interface.
Version: 3.7.4
Author: Cornbip-19
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Set timezone to Asia/Manila on plugin activation
function uttd_set_timezone() {
    update_option('timezone_string', 'Asia/Manila');
    error_log('UTTD: Set timezone to Asia/Manila');
}
register_activation_hook(__FILE__, 'uttd_set_timezone');

// Create database tables on plugin activation
function uttd_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_name = $wpdb->prefix . 'user_time_logs';
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        login_time datetime NOT NULL,
        logout_time datetime,
        amount decimal(10,2) DEFAULT 0.00,
        early_out_reason text,
        paid_status enum('unpaid','paid') DEFAULT 'unpaid',
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Add early_out_reason and paid_status columns if they don't exist
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'early_out_reason'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD early_out_reason TEXT");
        error_log('UTTD: Added early_out_reason column to user_time_logs table');
    }

    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'paid_status'");
    if (empty($columns)) {
        $wpdb->query("ALTER TABLE $table_name ADD paid_status ENUM('unpaid','paid') DEFAULT 'unpaid'");
        error_log('UTTD: Added paid_status column to user_time_logs table');
    }
}
register_activation_hook(__FILE__, 'uttd_create_tables');

// Create worker dashboard page on activation
function uttd_create_dashboard_page() {
    $page_title = 'Worker Dashboard';
    $page_content = '[uttd_dashboard]';
    $page_check = get_page_by_title($page_title);

    if (!$page_check) {
        $page = array(
            'post_title'   => $page_title,
            'post_content' => $page_content,
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_author'  => 1,
            'post_name'    => 'worker-dashboard'
        );
        $page_id = wp_insert_post($page);
        if ($page_id && !is_wp_error($page_id)) {
            update_post_meta($page_id, '_wp_page_template', 'worker-dashboard.php');
        } else {
            error_log('UTTD: Failed to create worker dashboard page');
        }
    }
}
register_activation_hook(__FILE__, 'uttd_create_dashboard_page');

// Add worker role on activation
function uttd_add_user_role() {
    add_role(
        'worker',
        'Worker',
        array(
            'read' => true,
        )
    );
}
register_activation_hook(__FILE__, 'uttd_add_user_role');

// Disable user registration for non-admins
function uttd_disable_user_registration() {
    update_option('users_can_register', false);
}
register_activation_hook(__FILE__, 'uttd_disable_user_registration');

// Register custom template
function uttd_register_template($templates) {
    $templates['worker-dashboard.php'] = 'Worker Dashboard Template';
    return $templates;
}
add_filter('theme_page_templates', 'uttd_register_template');

// Force custom template loading
function uttd_load_custom_template($template) {
    if (is_page('worker-dashboard')) {
        $custom_template = plugin_dir_path(__FILE__) . 'templates/worker-dashboard.php';
        if (file_exists($custom_template)) {
            error_log('UTTD: Loading custom template for worker-dashboard: ' . $custom_template);
            return $custom_template;
        } else {
            error_log('UTTD: Custom template not found: ' . $custom_template);
        }
    }
    return $template;
}
add_filter('template_include', 'uttd_load_custom_template', 99);

// Enqueue scripts and styles for front-end
function uttd_enqueue_scripts() {
    global $post;
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'uttd_dashboard') || is_page('worker-dashboard'))) {
        $style_url = plugin_dir_url(__FILE__) . 'style/style.css';
        $script_url = plugin_dir_url(__FILE__) . 'script/script.js';

        wp_enqueue_style('uttd-style', $style_url, array(), '3.7.4', 'all');
        wp_enqueue_script('uttd-script', $script_url, array('jquery'), '3.7.4', true);
        
        $user_id = get_current_user_id();
        $start_time = get_user_meta($user_id, 'uttd_start_time', true);
        $end_time = get_user_meta($user_id, 'uttd_end_time', true);

        wp_localize_script('uttd-script', 'uttd_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('uttd_nonce'),
            'start_time' => $start_time,
            'end_time' => $end_time
        ));

        error_log('UTTD: Enqueuing style: ' . $style_url);
        error_log('UTTD: Enqueuing script: ' . $script_url);
    }
}
add_action('wp_enqueue_scripts', 'uttd_enqueue_scripts', 999);

// Enqueue scripts and styles for admin
function uttd_enqueue_admin_scripts($hook) {
    if ($hook !== 'toplevel_page_uttd-admin') {
        return;
    }

    $style_url = plugin_dir_url(__FILE__) . 'style/style.css';
    $script_url = plugin_dir_url(__FILE__) . 'script/script.js';

    wp_enqueue_style('uttd-admin-style', $style_url, array(), '3.7.4', 'all');
    wp_enqueue_script('uttd-admin-script', $script_url, array('jquery'), '3.7.4', true);

    $ajax_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('uttd_admin_nonce')
    );
    wp_localize_script('uttd-admin-script', 'uttd_admin_ajax', $ajax_data);

    error_log('UTTD: Enqueuing admin style: ' . $style_url);
    error_log('UTTD: Enqueuing admin script: ' . $script_url);
}
add_action('admin_enqueue_scripts', 'uttd_enqueue_admin_scripts');

// Worker dashboard shortcode
function uttd_user_dashboard_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your dashboard.</p>';
    }

    $user = wp_get_current_user();
    if (!in_array('worker', (array) $user->roles)) {
        return '<p>This dashboard is only accessible to users with the Worker role.</p>';
    }

    ob_start();
    $user_id = get_current_user_id();
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_time_logs';
    
    $today = date('Y-m-d');
    $today_log = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND DATE(login_time) = %s AND logout_time IS NULL",
            $user_id,
            $today
        )
    );

    // Get daily rate, working hours, and position
    $daily_rate = floatval(get_user_meta($user_id, 'uttd_daily_rate', true)) ?: 0.00;
    $start_time = get_user_meta($user_id, 'uttd_start_time', true);
    $end_time = get_user_meta($user_id, 'uttd_end_time', true);
    $start_time_display = $start_time ? date('h:i A', strtotime($start_time)) : 'Not set';
    $end_time_display = $end_time ? date('h:i A', strtotime($end_time)) : 'Not set';
    $position = get_user_meta($user_id, 'uttd_position', true) ?: 'Not assigned';

    // Calculate early/late status if shift is active
    $arrival_message = '';
    if ($today_log && !$today_log->logout_time && $start_time) {
        $shift_start = new DateTime($today . ' ' . $start_time, new DateTimeZone('Asia/Manila'));
        $actual_start = new DateTime($today_log->login_time, new DateTimeZone('Asia/Manila'));

        if ($start_time > $end_time) {
            if ($actual_start->format('H:i') < $end_time) {
                $shift_start->modify('-1 day');
            }
        }

        $interval = $actual_start->diff($shift_start);
        $minutes = ($interval->h * 60) + $interval->i;

        if ($actual_start < $shift_start) {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            $time_str = $hours > 0 ? "$hours hr" . ($hours > 1 ? 's' : '') : '';
            $time_str .= $hours > 0 && $mins > 0 ? ' ' : '';
            $time_str .= $mins > 0 ? "$mins min" . ($mins > 1 ? 's' : '') : '';
            $arrival_message = "You are $time_str before your shift.";
        } elseif ($actual_start > $shift_start) {
            $hours = floor($minutes / 60);
            $mins = $minutes % 60;
            $time_str = $hours > 0 ? "$hours hr" . ($hours > 1 ? 's' : '') : '';
            $time_str .= $hours > 0 && $mins > 0 ? ' ' : '';
            $time_str .= $mins > 0 ? "$mins min" . ($mins > 1 ? 's' : '') : '';
            $arrival_message = "You are $time_str late to your shift.";
        } else {
            $arrival_message = "You started your shift on time.";
        }
    }

    ?>
    <div class="uttd-wrapper">
        <div class="uttd-dashboard">
            <div class="uttd-header">
                <h2>Welcome, <?php echo esc_html($user->display_name); ?></h2>
                <p><strong>Position:</strong> <?php echo esc_html($position); ?></p>
                <a href="<?php echo wp_logout_url(home_url()); ?>" class="uttd-logout-button">Logout</a>
            </div>
            
            <div class="uttd-analytics-grid">
                <div class="uttd-card uttd-date-card">
                    <h3>Today's Date</h3>
                    <p id="uttd-current-date"><?php echo date('F j, Y'); ?></p>
                </div>
                <div class="uttd-card uttd-time-card">
                    <h3>Current Time (PH)</h3>
                    <p id="uttd-current-time"></p>
                </div>
                <div class="uttd-card uttd-shift-card">
                    <h3>Shift Status</h3>
                    <div class="uttd-timer-section">
                        <?php if ($today_log && !$today_log->logout_time): ?>
                            <button id="uttd-end-timer" class="uttd-button uttd-button-end">End Shift</button>
                            <?php if ($arrival_message): ?>
                                <p class="uttd-arrival-message"><?php echo esc_html($arrival_message); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <button id="uttd-start-timer" class="uttd-button uttd-button-start">Start Shift</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="uttd-card uttd-rate-card">
                    <h3>Your Daily Rate</h3>
                    <p>₱<?php echo number_format($daily_rate, 2); ?></p>
                </div>
                <div class="uttd-card uttd-hours-card">
                    <h3>Working Hours</h3>
                    <p><?php echo esc_html($start_time_display . ' - ' . $end_time_display); ?></p>
                </div>
            </div>

            <div class="uttd-logs-section">
                <h3>Your Work Logs</h3>
                <div class="uttd-tabs">
                    <button class="uttd-tab-button active" data-tab="current">Current Logs</button>
                    <button class="uttd-tab-button" data-tab="paid">Paid Logs</button>
                </div>
                <div class="uttd-tab-content" id="current-logs">
                    <table class="uttd-history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Earnings</th>
                                <th>Reason for Early Out</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="5">No unpaid work logs available.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($log->login_time)); ?></td>
                                        <td><?php echo date('h:i A', strtotime($log->login_time)); ?></td>
                                        <td><?php echo $log->logout_time ? date('h:i A', strtotime($log->logout_time)) : '-'; ?></td>
                                        <td>₱<?php echo number_format($log->amount, 2); ?></td>
                                        <td><?php echo !empty($log->early_out_reason) ? esc_html($log->early_out_reason) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <?php if (!empty($logs)): ?>
                        <div class="uttd-totals">
                            <p><strong>Total Unpaid Earnings:</strong> ₱<?php echo number_format($total_amount, 2); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="uttd-tab-content" id="paid-logs" style="display: none;">
                    <table class="uttd-history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Earnings</th>
                                <th>Reason for Early Out</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $paid_logs = $wpdb->get_results(
                                $wpdb->prepare(
                                    "SELECT * FROM $table_name WHERE user_id = %d AND paid_status = 'paid' ORDER BY login_time DESC LIMIT 10",
                                    $user_id
                                )
                            );
                            
                            if (empty($paid_logs)) {
                                echo '<tr><td colspan="5">No paid work logs available.</td></tr>';
                            } else {
                                $total_paid_amount = 0;
                                foreach ($paid_logs as $log) {
                                    $amount = floatval($log->amount);
                                    $total_paid_amount += $amount;
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($log->login_time)); ?></td>
                                        <td><?php echo date('h:i A', strtotime($log->login_time)); ?></td>
                                        <td><?php echo $log->logout_time ? date('h:i A', strtotime($log->logout_time)) : '-'; ?></td>
                                        <td>₱<?php echo number_format($amount, 2); ?></td>
                                        <td><?php echo !empty($log->early_out_reason) ? esc_html($log->early_out_reason) : '-'; ?></td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                    <?php if (!empty($paid_logs)): ?>
                        <div class="uttd-totals">
                            <p><strong>Total Paid Earnings:</strong> ₱<?php echo number_format($total_paid_amount, 2); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    
    return ob_get_clean();
}
add_shortcode('uttd_dashboard', 'uttd_user_dashboard_shortcode');

// Admin dashboard page with user creation and management
function uttd_admin_menu() {
    add_menu_page(
        'User Time Tracking',
        'Time Tracking',
        'manage_options',
        'uttd-admin',
        'uttd_admin_page_content',
        'dashicons-clock'
    );
}
add_action('admin_menu', 'uttd_admin_menu');

function uttd_admin_page_content() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_time_logs';
    
    // Handle user creation
    if (isset($_POST['uttd_create_user']) && check_admin_referer('uttd_create_user_action')) {
        $username = sanitize_user($_POST['uttd_username']);
        $email = sanitize_email($_POST['uttd_email']);
        $password = $_POST['uttd_password'];
        $daily_rate = floatval($_POST['uttd_daily_rate']);
        $start_time = sanitize_text_field($_POST['uttd_start_time']);
        $end_time = sanitize_text_field($_POST['uttd_end_time']);
        $position = sanitize_text_field($_POST['uttd_position']);
        
        if (empty($password)) {
            echo '<div class="notice notice-error"><p>Please provide a password.</p></div>';
        } else {
            $user_id = wp_create_user($username, $password, $email);
            
            if (!is_wp_error($user_id)) {
                $user = new WP_User($user_id);
                $user->set_role('worker');
                
                update_user_meta($user_id, 'uttd_daily_rate', $daily_rate);
                update_user_meta($user_id, 'uttd_start_time', $start_time);
                update_user_meta($user_id, 'uttd_end_time', $end_time);
                update_user_meta($user_id, 'uttd_position', $position);
                
                $start_time_display = date('h:i A', strtotime($start_time));
                $end_time_display = date('h:i A', strtotime($end_time));
                
                wp_mail(
                    $email,
                    'Your New Account Credentials',
                    "Username: $username\nPassword: $password\nPosition: $position\nDaily Rate: ₱" . number_format($daily_rate, 2) . "\nWorking Hours: $start_time_display - $end_time_display\n\nLogin at: " . wp_login_url() . "\nDashboard: " . home_url('/worker-dashboard')
                );
                echo '<div class="notice notice-success"><p>User created successfully! Credentials sent to user email.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Error creating user: ' . $user_id->get_error_message() . '</p></div>';
            }
        }
    }
    
    // Handle rate update
    if (isset($_POST['uttd_update_rate']) && check_admin_referer('uttd_update_rate_action')) {
        $user_id = intval($_POST['uttd_user_id']);
        $new_rate = floatval($_POST['uttd_new_rate']);
        
        if ($new_rate < 0) {
            echo '<div class="notice notice-error"><p>Rate cannot be negative.</p></div>';
        } else {
            $updated = update_user_meta($user_id, 'uttd_daily_rate', $new_rate);
            if ($updated) {
                echo '<div class="notice notice-success"><p>Rate updated successfully for user ID ' . esc_html($user_id) . '!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to update rate for user ID ' . esc_html($user_id) . '.</p></div>';
            }
        }
    }
    
    // Handle working hours update
    if (isset($_POST['uttd_update_hours']) && check_admin_referer('uttd_update_hours_action')) {
        $user_id = intval($_POST['uttd_user_id']);
        $new_start_time = sanitize_text_field($_POST['uttd_new_start_time']);
        $new_end_time = sanitize_text_field($_POST['uttd_new_end_time']);
        
        if (empty($new_start_time) || empty($new_end_time)) {
            echo '<div class="notice notice-error"><p>Please provide both start and end times.</p></div>';
        } else {
            $start_updated = update_user_meta($user_id, 'uttd_start_time', $new_start_time);
            $end_updated = update_user_meta($user_id, 'uttd_end_time', $new_end_time);
            
            if ($start_updated && $end_updated) {
                echo '<div class="notice notice-success"><p>Working hours updated successfully for user ID ' . esc_html($user_id) . '!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to update working hours for user ID ' . esc_html($user_id) . '.</p></div>';
            }
        }
    }
    
    // Handle position update
    if (isset($_POST['uttd_update_position']) && check_admin_referer('uttd_update_position_action')) {
        $user_id = intval($_POST['uttd_user_id']);
        $new_position = sanitize_text_field($_POST['uttd_new_position']);
        
        if (empty($new_position)) {
            echo '<div class="notice notice-error"><p>Position cannot be empty.</p></div>';
        } else {
            $updated = update_user_meta($user_id, 'uttd_position', $new_position);
            if ($updated) {
                echo '<div class="notice notice-success"><p>Position updated successfully for user ID ' . esc_html($user_id) . '!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to update position for user ID ' . esc_html($user_id) . '.</p></div>';
            }
        }
    }
    
    // Handle mark as paid
    if (isset($_POST['uttd_mark_paid']) && check_admin_referer('uttd_mark_paid_action')) {
        $user_id = intval($_POST['uttd_user_id']);
        $pay_period = sanitize_text_field($_POST['uttd_pay_period']);
        
        $today = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $current_week_start = clone $today;
        $current_week_start->modify('monday this week');
        $current_week_start_str = $current_week_start->format('Y-m-d 00:00:00');
        
        $period_start = clone $today;
        if ($pay_period === 'weekly') {
            $period_start->modify('-1 week');
        } elseif ($pay_period === 'biweekly') {
            $period_start->modify('-2 weeks');
        } elseif ($pay_period === 'monthly') {
            $period_start->modify('-1 month');
        }
        $period_start_str = $period_start->format('Y-m-d 00:00:00');
        
        $result = $wpdb->update(
            $table_name,
            array('paid_status' => 'paid'),
            array(
                'user_id' => $user_id,
                'paid_status' => 'unpaid',
                'login_time' => $wpdb->prepare('BETWEEN %s AND %s', $period_start_str, $current_week_start_str)
            ),
            array('%s'),
            array('%d', '%s', '%s')
        );
        
        if ($result === false) {
            echo '<div class="notice notice-error"><p>Failed to mark logs as paid for user ID ' . esc_html($user_id) . '.</p></div>';
            error_log('UTTD: Failed to mark logs as paid for user ID ' . $user_id);
        } else {
            echo '<div class="notice notice-success"><p>Successfully marked ' . $result . ' logs as paid for user ID ' . esc_html($user_id) . '!</p></div>';
            error_log('UTTD: Marked ' . $result . ' logs as paid for user ID ' . $user_id);
        }
    }
    
    ?>
    <div class="wrap uttd-admin-wrapper">
        <h1>User Time Tracking</h1>
        
        <div class="uttd-admin-grid">
            <div class="uttd-admin-card uttd-add-worker-card">
                <h3>Add Worker</h3>
                <p>Create a new worker account</p>
            </div>
            
            <?php
            $users = get_users(array('role' => 'worker'));
            if (empty($users)) {
                echo '<p>No workers found.</p>';
            } else {
                foreach ($users as $user):
                    $daily_rate = floatval(get_user_meta($user->ID, 'uttd_daily_rate', true)) ?: 0.00;
                    $start_time = get_user_meta($user->ID, 'uttd_start_time', true);
                    $end_time = get_user_meta($user->ID, 'uttd_end_time', true);
                    $start_time_display = $start_time ? date('h:i A', strtotime($start_time)) : 'Not set';
                    $end_time_display = $end_time ? date('h:i A', strtotime($end_time)) : 'Not set';
                    $position = get_user_meta($user->ID, 'uttd_position', true) ?: 'Not assigned';
                ?>
                    <div class="uttd-admin-card" data-user-id="<?php echo esc_attr($user->ID); ?>">
                        <h3><?php echo esc_html($user->display_name); ?></h3>
                        <p><strong>ID:</strong> <?php echo esc_html($user->ID); ?></p>
                        <p><strong>Position:</strong> <?php echo esc_html($position); ?></p>
                        <p><strong>Daily Rate:</strong> ₱<?php echo number_format($daily_rate, 2); ?></p>
                        <p><strong>Working Hours:</strong> <?php echo esc_html($start_time_display . ' - ' . $end_time_display); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php } ?>
        </div>

        <div id="uttd-add-worker-popup" class="uttd-popup">
            <div class="uttd-popup-content">
                <span class="uttd-popup-close">&times;</span>
                <h2>Create New Worker</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('uttd_create_user_action'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="uttd_username">Username</label></th>
                            <td><input type="text" name="uttd_username" id="uttd_username" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="uttd_email">Email</label></th>
                            <td><input type="email" name="uttd_email" id="uttd_email" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="uttd_password">Password</label></th>
                            <td>
                                <input type="password" name="uttd_password" id="uttd_password" class="regular-text" required>
                                <p class="description">Enter a secure password for the worker.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="uttd_daily_rate">Daily Rate (₱)</label></th>
                            <td>
                                <input type="number" step="0.01" name="uttd_daily_rate" id="uttd_daily_rate" class="regular-text" value="0.00" required>
                                <p class="description">Set the daily rate for the worker in pesos.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="uttd_start_time">Start Time</label></th>
                            <td>
                                <input type="time" name="uttd_start_time" id="uttd_start_time" class="regular-text" required>
                                <p class="description">Set the shift start time (e.g., 21:00 for 9 PM).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="uttd_end_time">End Time</label></th>
                            <td>
                                <input type="time" name="uttd_end_time" id="uttd_end_time" class="regular-text" required>
                                <p class="description">Set the shift end time (e.g., 06:00 for 6 AM).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="uttd_position">Position</label></th>
                            <td>
                                <input type="text" name="uttd_position" id="uttd_position" class="regular-text" required>
                                <p class="description">Enter the worker's position (e.g., Manager, Technician).</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" name="uttd_create_user" class="button button-primary" value="Create User">
                    </p>
                </form>
            </div>
        </div>

        <div id="uttd-worker-details-popup" class="uttd-popup">
            <div class="uttd-popup-content">
                <span class="uttd-popup-close">&times;</span>
                <div id="uttd-worker-details-content"></div>
            </div>
        </div>
    </div>
    <?php
}

// AJAX handler to get worker details
function uttd_get_worker_details() {
    check_ajax_referer('uttd_admin_nonce', 'nonce');

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    error_log('UTTD: AJAX request received for user_id: ' . $user_id);

    if (!$user_id) {
        error_log('UTTD: Get worker details failed - invalid user ID');
        wp_send_json_error('Invalid user ID');
    }

    $user = get_userdata($user_id);
    if (!$user || !in_array('worker', (array) $user->roles)) {
        error_log('UTTD: Get worker details failed - user not found or not a worker: ' . $user_id);
        wp_send_json_error('User not found or not a worker');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'user_time_logs';

    $daily_rate = floatval(get_user_meta($user_id, 'uttd_daily_rate', true)) ?: 0.00;
    $start_time = get_user_meta($user_id, 'uttd_start_time', true);
    $end_time = get_user_meta($user_id, 'uttd_end_time', true);
    $start_time_display = $start_time ? date('h:i A', strtotime($start_time)) : 'Not set';
    $end_time_display = $end_time ? date('h:i A', strtotime($end_time)) : 'Not set';
    $position = get_user_meta($user_id, 'uttd_position', true) ?: 'Not assigned';

    ob_start();
    ?>
    <div class="uttd-worker-details">
        <div class="uttd-section uttd-worker-info">
            <h2 class="uttd-section-title"><?php echo esc_html($user->display_name); ?> (ID: <?php echo esc_html($user_id); ?>)</h2>
            <p><strong>Position:</strong> <?php echo esc_html($position); ?></p>
        </div>

        <div class="uttd-section uttd-rate-section">
            <h3 class="uttd-section-subtitle">Daily Rate</h3>
            <p class="uttd-current-value"><strong>Current Rate:</strong> ₱<?php echo number_format($daily_rate, 2); ?></p>
            <form method="post" action="" class="uttd-update-form">
                <?php wp_nonce_field('uttd_update_rate_action'); ?>
                <input type="hidden" name="uttd_user_id" value="<?php echo esc_attr($user_id); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="uttd_new_rate_<?php echo esc_attr($user_id); ?>">Update Daily Rate (₱)</label></th>
                        <td>
                            <input type="number" step="0.01" name="uttd_new_rate" id="uttd_new_rate_<?php echo esc_attr($user_id); ?>" class="regular-text" value="<?php echo esc_attr($daily_rate); ?>" required>
                            <p class="description">Enter the new daily rate for this worker.</p>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="uttd_update_rate" class="button button-secondary">Update Daily Rate</button>
            </form>
        </div>

        <div class="uttd-section uttd-hours-section">
            <h3 class="uttd-section-subtitle">Working Hours</h3>
            <p class="uttd-current-value"><strong>Current Hours:</strong> <?php echo esc_html($start_time_display . ' - ' . $end_time_display); ?></p>
            <form method="post" action="" class="uttd-update-form">
                <?php wp_nonce_field('uttd_update_hours_action'); ?>
                <input type="hidden" name="uttd_user_id" value="<?php echo esc_attr($user_id); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="uttd_new_start_time_<?php echo esc_attr($user_id); ?>">Update Start Time</label></th>
                        <td>
                            <input type="time" name="uttd_new_start_time" id="uttd_new_start_time_<?php echo esc_attr($user_id); ?>" class="regular-text" value="<?php echo esc_attr($start_time); ?>" required>
                            <p class="description">Set the new shift start time (e.g., 21:00 for 9 PM).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="uttd_new_end_time_<?php echo esc_attr($user_id); ?>">Update End Time</label></th>
                        <td>
                            <input type="time" name="uttd_new_end_time" id="uttd_new_end_time_<?php echo esc_attr($user_id); ?>" class="regular-text" value="<?php echo esc_attr($end_time); ?>" required>
                            <p class="description">Set the new shift end time (e.g., 06:00 for 6 AM).</p>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="uttd_update_hours" class="button button-secondary">Update Working Hours</button>
            </form>
        </div>

        <div class="uttd-section uttd-position-section">
            <h3 class="uttd-section-subtitle">Position</h3>
            <p class="uttd-current-value"><strong>Current Position:</strong> <?php echo esc_html($position); ?></p>
            <form method="post" action="" class="uttd-update-form">
                <?php wp_nonce_field('uttd_update_position_action'); ?>
                <input type="hidden" name="uttd_user_id" value="<?php echo esc_attr($user_id); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="uttd_new_position_<?php echo esc_attr($user_id); ?>">Update Position</label></th>
                        <td>
                            <input type="text" name="uttd_new_position" id="uttd_new_position_<?php echo esc_attr($user_id); ?>" class="regular-text" value="<?php echo esc_attr($position); ?>" required>
                            <p class="description">Enter the new position for this worker.</p>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="uttd_update_position" class="button button-secondary">Update Position</button>
            </form>
        </div>

        <div class="uttd-section uttd-payment-section">
            <h3 class="uttd-section-subtitle">Mark as Paid</h3>
            <form method="post" action="" class="uttd-update-form">
                <?php wp_nonce_field('uttd_mark_paid_action'); ?>
                <input type="hidden" name="uttd_user_id" value="<?php echo esc_attr($user_id); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="uttd_pay_period_<?php echo esc_attr($user_id); ?>">Pay Period</label></th>
                        <td>
                            <select name="uttd_pay_period" id="uttd_pay_period_<?php echo esc_attr($user_id); ?>" class="regular-text" required>
                                <option value="weekly">Weekly</option>
                                <option value="biweekly">Bi-weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                            <p class="description">Select the pay period to mark as paid. Current week's logs are excluded.</p>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="uttd_mark_paid" class="button button-secondary">Mark as Paid</button>
            </form>
        </div>

        <div class="uttd-section uttd-logs-section">
            <h3 class="uttd-section-subtitle">Work Logs</h3>
            <div class="uttd-tabs">
                <button class="uttd-tab-button active" data-tab="current">Current Logs</button>
                <button class="uttd-tab-button" data-tab="paid">Paid Logs</button>
            </div>
            <div class="uttd-tab-content" id="current-logs">
                <table class="uttd-history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Start Time (PH)</th>
                            <th>End Time (PH)</th>
                            <th>Earnings</th>
                            <th>Reason for Early Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $logs = $wpdb->get_results(
                            $wpdb->prepare(
                                "SELECT * FROM $table_name WHERE user_id = %d AND paid_status = 'unpaid' ORDER BY login_time DESC",
                                $user_id
                            )
                        );
                        
                        if (empty($logs)) {
                            echo '<tr><td colspan="5">No unpaid logs available for this worker.</td></tr>';
                        } else {
                            $total_amount = 0;
                            foreach ($logs as $log) {
                                $amount = floatval($log->amount);
                                $total_amount += $amount;
                                ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($log->login_time)); ?></td>
                                    <td><?php echo date('h:i A', strtotime($log->login_time)); ?></td>
                                    <td><?php echo $log->logout_time ? date('h:i A', strtotime($log->logout_time)) : '-'; ?></td>
                                    <td>₱<?php echo number_format($amount, 2); ?></td>
                                    <td><?php echo !empty($log->early_out_reason) ? esc_html($log->early_out_reason) : '-'; ?></td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
                <?php if (!empty($logs)): ?>
                    <div class="uttd-totals">
                        <p><strong>Total Unpaid Earnings:</strong> ₱<?php echo number_format($total_amount, 2); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="uttd-tab-content" id="paid-logs" style="display: none;"></div></div>
                <table class="uttd-history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Start Time (PH)</th>
                            <th>End Time (PH)</th>
                            <th>Earnings</th>
                            <th>Reason for Early Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $paid_logs = $wpdb->get_results(
                            $wpdb->prepare(
                                "SELECT * FROM $table_name WHERE user_id = %d AND paid_status = 'paid' ORDER BY login_time DESC LIMIT 10",
                                $user_id
                            )
                        );
                        
                        if (empty($paid_logs)) {
                            echo '<tr><td colspan="5">No paid logs available for this worker.</td></tr>';
                        } else {
                            $total_paid_amount = 0;
                            foreach ($paid_logs as $log) {
                                $amount = floatval($log->amount);
                                $total_paid_amount += $amount;
                                ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($log->login_time)); ?></td>
                                    <td><?php echo date('h:i A', strtotime($log->login_time)); ?></td>
                                    <td><?php echo $log->logout_time ? date('h:i A', strtotime($log->logout_time)) : '-'; ?></td>
                                    <td>₱<?php echo number_format($amount, 2); ?></td>
                                    <td><?php echo !empty($log->early_out_reason) ? esc_html($log->early_out_reason) : '-'; ?></td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
                <?php if (!empty($paid_logs)): ?>
                    <div class="uttd-totals"></div></div>
                        <p><strong>Total Paid Earnings:</strong> ₱<?php echo number_format($total_paid_amount, 2); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php

    $active_shifts = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE logout_time IS NULL ORDER BY login_time DESC"
    );

    if (empty($active_shifts)) {
        echo '<p>No active shifts at the moment.</p>';
    } else {
        echo '<table class="widefat fixed">';
        echo '<thead><tr><th>User</th><th>Start Time</th><th>Status</th></thead>';
        echo '<tbody>';
        foreach ($active_shifts as $shift) {
            $user = get_userdata($shift->user_id);
            echo '<tr>';
            echo '<td>' . esc_html($user->display_name) . '</td>';
            echo '<td>' . date('M d, Y h:i A', strtotime($shift->login_time)) . '</td>';
            echo '<td>Active</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
}

// AJAX handler to get worker details
function uttd_get_worker_details() {
    check_ajax_referer('uttd_admin_nonce', 'nonce');

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    error_log('UTTD: AJAX request received for user_id: ' . $user_id);

    if (!$user_id) {
        error_log('UTTD: Get worker details failed - invalid user ID');
        wp_send_json_error('Invalid user ID');
    }

    $user = get_userdata($user_id);
    if (!$user || !in_array('worker', (array) $user->roles)) {
        error_log('UTTD: Get worker details failed - user not found or not a worker: ' . $user_id);
        wp_send_json_error('User not found or not a worker');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'user_time_logs';

    $daily_rate = floatval(get_user_meta($user_id, 'uttd_daily_rate', true)) ?: 0.00;
    $start_time = get_user_meta($user_id, 'uttd_start_time', true);
    $end_time = get_user_meta($user_id, 'uttd_end_time', true);
    $start_time_display = $start_time ? date('h:i A', strtotime($start_time)) : 'Not set';
    $end_time_display = $end_time ? date('h:i A', strtotime($end_time)) : 'Not set';
    $position = get_user_meta($user_id, 'uttd_position', true) ?: 'Not assigned';

    ob_start();
    ?>
    <div class="uttd-worker-details">
        <div class="uttd-section uttd-worker-info">
            <h2 class="uttd-section-title"><?php echo esc_html($user->display_name); ?> (ID: <?php echo esc_html($user_id); ?>)</h2>
            <p><strong>Position:</strong> <?php echo esc_html($position); ?></p>
        </div>

        <div class="uttd-section uttd-rate-section">
            <h3 class="uttd-section-subtitle">Daily Rate</h3>
            <p class="uttd-current-value"><strong>Current Rate:</strong> ₱<?php echo number_format($daily_rate, 2); ?></p>
            <form method="post" action="" class="uttd-update-form">
                <?php wp_nonce_field('uttd_update_rate_action'); ?>
                <input type="hidden" name="uttd_user_id" value="<?php echo esc_attr($user_id); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="uttd_new_rate_<?php echo esc_attr($user_id); ?>">Update Daily Rate (₱)</label></th>
                        <td>
                            <input type="number" step="0.01" name="uttd_new_rate" id="uttd_new_rate_<?php echo esc_attr($user_id); ?>" class="regular-text" value="<?php echo esc_attr($daily_rate); ?>" required>
                            <p class="description">Enter the new daily rate for this worker.</p>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="uttd_update_rate" class="button button-secondary">Update Daily Rate</button>
            </form>
        </div>

        <div class="uttd-section uttd-hours-section">
            <h3 class="uttd-section-subtitle">Working Hours</h3>
            <p class="uttd-current-value"><strong>Current Hours:</strong> <?php echo esc_html($start_time_display . ' - ' . $end_time_display); ?></p>
            <form method="post" action="" class="uttd-update-form">
                <?php wp_nonce_field('uttd_update_hours_action'); ?>
                <input type="hidden" name="uttd_user_id" value="<?php echo esc_attr($user_id); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="uttd_new_start_time_<?php echo esc_attr($user_id); ?>">Update Start Time</label></th>
                        <td>
                            <input type="time" name="uttd_new_start_time" id="uttd_new_start_time_<?php echo esc_attr($user_id); ?>" class="regular-text" value="<?php echo esc_attr($start_time); ?>" required>
                            <p class="description">Set the new shift start time (e.g., 21:00 for 9 PM).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="uttd_new_end_time_<?php echo esc_attr($user_id); ?>">Update End Time</label></th>
                        <td>
                            <input type="time" name="uttd_new_end_time" id="uttd_new_end_time_<?php echo esc_attr($user_id); ?>" class="regular-text" value="<?php echo esc_attr($end_time); ?>" required>
                            <p class="description">Set the new shift end time (e.g., 06:00 for 6 AM).</p>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="uttd_update_hours" class="button button-secondary">Update Working Hours</button>
            </form>
        </div>

        <div class="uttd-section uttd-position-section">
            <h3 class="uttd-section-subtitle">Position</h3>
            <p class="uttd-current-value"><strong>Current Position:</strong> <?php echo esc_html($position); ?></p>
            <form method="post" action="" class="uttd-update-form">
                <?php wp_nonce_field('uttd_update_position_action'); ?>
                <input type="hidden" name="uttd_user_id" value="<?php echo esc_attr($user_id); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="uttd_new_position_<?php echo esc_attr($user_id); ?>">Update Position</label></th>
                        <td>
                            <input type="text" name="uttd_new_position" id="uttd_new_position_<?php echo esc_attr($user_id); ?>" class="regular-text" value="<?php echo esc_attr($position); ?>" required>
                            <p class="description">Enter the new position for this worker.</p>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="uttd_update_position" class="button button-secondary">Update Position</button>
            </form>
        </div>

        <div class="uttd-section uttd-payment-section">
            <h3 class="uttd-section-subtitle">Mark as Paid</h3>
            <form method="post" action="" class="uttd-update-form">
                <?php wp_nonce_field('uttd_mark_paid_action'); ?>
                <input type="hidden" name="uttd_user_id" value="<?php echo esc_attr($user_id); ?>">
                <table class="form-table">
                    <tr>
                        <th><label for="uttd_pay_period_<?php echo esc_attr($user_id); ?>">Pay Period</label></th>
                        <td>
                            <select name="uttd_pay_period" id="uttd_pay_period_<?php echo esc_attr($user_id); ?>" class="regular-text" required>
                                <option value="weekly">Weekly</option>
                                <option value="biweekly">Bi-weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                            <p class="description">Select the pay period to mark as paid. Current week's logs are excluded.</p>
                        </td>
                    </tr>
                </table>
                <button type="submit" name="uttd_mark_paid" class="button button-secondary">Mark as Paid</button>
            </form>
        </div>

        <div class="uttd-section uttd-logs-section">
            <h3 class="uttd-section-subtitle">Work Logs</h3>
            <div class="uttd-tabs">
                <button class="uttd-tab-button active" data-tab="current">Current Logs</button>
                <button class="uttd-tab-button" data-tab="paid">Paid Logs</button>
            </div>
            <div class="uttd-tab-content" id="current-logs">
                <table class="uttd-history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Start Time (PH)</th>
                            <th>End Time (PH)</th>
                            <th>Earnings</th>
                            <th>Reason for Early Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $logs = $wpdb->get_results(
                            $wpdb->prepare(
                                "SELECT * FROM $table_name WHERE user_id = %d AND paid_status = 'unpaid' ORDER BY login_time DESC",
                                $user_id
                            )
                        );
                        
                        if (empty($logs)) {
                            echo '<tr><td colspan="5">No unpaid logs available for this worker.</td></tr>';
                        } else {
                            $total_amount = 0;
                            foreach ($logs as $log) {
                                $amount = floatval($log->amount);
                                $total_amount += $amount;
                                ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($log->login_time)); ?></td>
                                    <td><?php echo date('h:i A', strtotime($log->login_time)); ?></td>
                                    <td><?php echo $log->logout_time ? date('h:i A', strtotime($log->logout_time)) : '-'; ?></td>
                                    <td>₱<?php echo number_format($amount, 2); ?></td>
                                    <td><?php echo !empty($log->early_out_reason) ? esc_html($log->early_out_reason) : '-'; ?></td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
                <?php if (!empty($logs)): ?>
                    <div class="uttd-totals">
                        <p><strong>Total Unpaid Earnings:</strong> ₱<?php echo number_format($total_amount, 2); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="uttd-tab-content" id="paid-logs" style="display: none;"></div></div>
                <table class="uttd-history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Start Time (PH)</th>
                            <th>End Time (PH)</th>
                            <th>Earnings</th>
                            <th>Reason for Early Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $paid_logs = $wpdb->get_results(
                            $wpdb->prepare(
                                "SELECT * FROM $table_name WHERE user_id = %d AND paid_status = 'paid' ORDER BY login_time DESC LIMIT 10",
                                $user_id
                            )
                        );
                        
                        if (empty($paid_logs)) {
                            echo '<tr><td colspan="5">No paid logs available for this worker.</td></tr>';
                        } else {
                            $total_paid_amount = 0;
                            foreach ($paid_logs as $log) {
                                $amount = floatval($log->amount);
                                $total_paid_amount += $amount;
                                ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($log->login_time)); ?></td>
                                    <td><?php echo date('h:i A', strtotime($log->login_time)); ?></td>
                                    <td><?php echo $log->logout_time ? date('h:i A', strtotime($log->logout_time)) : '-'; ?></td>
                                    <td>₱<?php echo number_format($amount, 2); ?></td>
                                    <td><?php echo !empty($log->early_out_reason) ? esc_html($log->early_out_reason) : '-'; ?></td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
                <?php if (!empty($paid_logs)): ?>
                    <div class="uttd-totals"></div></div>
                        <p><strong>Total Paid Earnings:</strong> ₱<?php echo number_format($total_paid_amount, 2); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php

    $content = ob_get_clean();
    error_log('UTTD: Successfully fetched worker details for user_id: ' . $user_id);
    wp_send_json_success($content);
}
add_action('wp_ajax_uttd_get_worker_details', 'uttd_get_worker_details');

// AJAX handlers for timer
function uttd_start_timer() {
    check_ajax_referer('uttd_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        error_log('UTTD: Start timer failed - user not logged in');
    }
    
    $user = wp_get_current_user();
    if (!in_array('worker', (array) $user->roles)) {
        wp_send_json_error('Unauthorized access');
        error_log('UTTD: Start timer failed - user not worker');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_time_logs';
    $user_id = get_current_user_id();
    
    $today = date('Y-m-d');
    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND DATE(login_time) = %s AND logout_time IS NULL",
            $user_id,
            $today
        )
    );
    
    if ($existing) {
        wp_send_json_error('Shift already active');
        error_log('UTTD: Start timer failed - shift already active for user ' . $user_id);
    }
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'login_time' => current_time('mysql'),
            'paid_status' => 'unpaid'
        ),
        array('%d', '%s', '%s')
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to start shift');
        error_log('UTTD: Start timer failed - database insert error for user ' . $user_id);
    }
    
    error_log('UTTD: Start timer succeeded for user ' . $user_id);
    wp_send_json_success();
}
add_action('wp_ajax_uttd_start_timer', 'uttd_start_timer');

function uttd_end_timer() {
    check_ajax_referer('uttd_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in');
        error_log('UTTD: End timer failed - user not logged in');
    }
    
    $user = wp_get_current_user();
    if (!in_array('worker', (array) $user->roles)) {
        wp_send_json_error('Unauthorized access');
        error_log('UTTD: End timer failed - user not worker');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_time_logs';
    $user_id = get_current_user_id();
    
    $today = date('Y-m-d');
    $session = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND DATE(login_time) = %s AND logout_time IS NULL",
            $user_id,
            $today
        )
    );
    
    if (!$session) {
        wp_send_json_error('No active shift');
        error_log('UTTD: End timer failed - no active shift for user ' . $user_id);
    }
    
    $early_out_reason = isset($_POST['early_out_reason']) ? sanitize_text_field($_POST['early_out_reason']) : '';
    
    $daily_rate = floatval(get_user_meta($user_id, 'uttd_daily_rate', true)) ?: 0.00;
    $start_time = get_user_meta($user_id, 'uttd_start_time', true);
    $end_time = get_user_meta($user_id, 'uttd_end_time', true);
    
    if (!$start_time || !$end_time) {
        wp_send_json_error('Working hours not set');
        error_log('UTTD: End timer failed - working hours not set for user ' . $user_id);
    }
    
    $login = new DateTime($session->login_time, new DateTimeZone('Asia/Manila'));
    $logout = new DateTime(current_time('mysql'), new DateTimeZone('Asia/Manila'));
    $shift_interval = $login->diff($logout);
    $shift_seconds = ($shift_interval->days * 86400) + ($shift_interval->h * 3600) + ($shift_interval->i * 60) + $shift_interval->s;
    
    $shift_start = new DateTime($today . ' ' . $start_time, new DateTimeZone('Asia/Manila'));
    $shift_end = new DateTime($today . ' ' . $end_time, new DateTimeZone('Asia/Manila'));
    
    if ($start_time > $end_time) {
        $shift_end->modify('+1 day');
    }
    
    $expected_interval = $shift_start->diff($shift_end);
    $expected_seconds = ($expected_interval->days * 86400) + ($expected_interval->h * 3600) + ($expected_interval->i * 60) + $expected_interval->s;
    
    $lateness_seconds = 0;
    if ($login > $shift_start) {
        $lateness_interval = $shift_start->diff($login);
        $lateness_seconds = ($lateness_interval->days * 86400) + ($lateness_interval->h * 3600) + ($lateness_interval->i * 60) + $lateness_interval->s;
        error_log('UTTD: Lateness detected for user ' . $user_id . ': ' . $lateness_seconds . ' seconds');
    }
    
    $rate_per_second = $daily_rate / $expected_seconds;
    $lateness_deduction = $lateness_seconds * $rate_per_second;
    $amount = $shift_seconds * $rate_per_second - $lateness_deduction;
    $amount = max(0, min($amount, $daily_rate));
    
    error_log('UTTD: Shift duration for user ' . $user_id . ': ' . $shift_seconds . ' seconds, Expected: ' . $expected_seconds . ' seconds, Rate per second: ₱' . number_format($rate_per_second, 6) . ', Lateness deduction: ₱' . number_format($lateness_deduction, 2) . ', Earnings: ₱' . number_format($amount, 2));
    
    $update_data = array(
        'logout_time' => current_time('mysql'),
        'amount' => $amount,
        'paid_status' => 'unpaid'
    );
    $update_formats = array('%s', '%f', '%s');
    
    if (!empty($early_out_reason)) {
        $update_data['early_out_reason'] = $early_out_reason;
        $update_formats[] = '%s';
    }
    
    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $session->id),
        $update_formats,
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to end shift');
        error_log('UTTD: End timer failed - database update error for user ' . $user_id);
    }
    
    error_log('UTTD: End timer succeeded for user ' . $user_id . ' with earnings: ₱' . number_format($amount, 2) . (empty($early_out_reason) ? '' : ' with early out reason: ' . $early_out_reason));
    wp_send_json_success(array('amount' => $amount));
}
add_action('wp_ajax_uttd_end_timer', 'uttd_end_timer');