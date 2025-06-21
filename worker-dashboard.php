<?php
/*
Template Name: Worker Dashboard Template
*/

// Prevent direct access
if (!defined("ABSPATH")) {
    exit;
}

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php
        error_log("UTTD: Rendering worker dashboard template");
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if (in_array("worker", (array) $user->roles)) {
                echo do_shortcode("[uttd_dashboard]");
            } else {
                echo "<p>This dashboard is only accessible to users with the Worker role.</p>";
            }
        } else {
            echo "<p>Please <a href=\"" . wp_login_url(get_permalink()) . "\">log in</a> to view your dashboard.</p>";
        }
        ?>
    </main>
</div>

<?php
get_footer();