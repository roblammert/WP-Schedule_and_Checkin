<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once SC_PLUGIN_DIR . 'includes/class-sc-admin.php';
require_once SC_PLUGIN_DIR . 'includes/class-sc-shortcodes.php';

class SC_Plugin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        SC_Install::maybe_upgrade();

        add_action('init', [$this, 'register_assets']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'public_assets']);
        add_action('template_redirect', [$this, 'render_standalone_kiosk']);
        add_action('sc_process_reminders', ['SC_Admin', 'process_scheduled_reminders']);

        if (!wp_next_scheduled('sc_process_reminders')) {
            wp_schedule_event(time() + 300, 'hourly', 'sc_process_reminders');
        }

        SC_Admin::init();
        SC_Shortcodes::init();
    }

    public function register_assets() {
        wp_register_style('sc-public', SC_PLUGIN_URL . 'assets/public.css', [], SC_PLUGIN_VERSION);
        wp_register_script('sc-public', SC_PLUGIN_URL . 'assets/public.js', ['jquery'], SC_PLUGIN_VERSION, true);

        wp_register_style('sc-admin', SC_PLUGIN_URL . 'assets/admin.css', [], SC_PLUGIN_VERSION);
        wp_register_script('sc-admin', SC_PLUGIN_URL . 'assets/admin.js', ['jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'], SC_PLUGIN_VERSION, true);
    }

    public function register_admin_menu() {
        add_menu_page(
            __('Schedule & Check-In', 'schedule-checkin'),
            __('Schedule & Check-In', 'schedule-checkin'),
            'manage_options',
            'sc-events',
            ['SC_Admin', 'render_events_page'],
            'dashicons-calendar-alt',
            26
        );

        add_submenu_page('sc-events', __('Events', 'schedule-checkin'), __('Events', 'schedule-checkin'), 'manage_options', 'sc-events', ['SC_Admin', 'render_events_page']);
        add_submenu_page('sc-events', __('Volunteers', 'schedule-checkin'), __('Volunteers', 'schedule-checkin'), 'manage_options', 'sc-volunteers', ['SC_Admin', 'render_volunteers_page']);
        add_submenu_page('sc-events', __('Assignments', 'schedule-checkin'), __('Assignments', 'schedule-checkin'), 'manage_options', 'sc-assignments', ['SC_Admin', 'render_assignments_page']);
        add_submenu_page('sc-events', __('Reports', 'schedule-checkin'), __('Reports', 'schedule-checkin'), 'manage_options', 'sc-reports', ['SC_Admin', 'render_reports_page']);
        add_submenu_page('sc-events', __('Check-In Logs', 'schedule-checkin'), __('Check-In Logs', 'schedule-checkin'), 'manage_options', 'sc-logs', ['SC_Admin', 'render_logs_page']);
        add_submenu_page('sc-events', __('Communications', 'schedule-checkin'), __('Communications', 'schedule-checkin'), 'manage_options', 'sc-communications', ['SC_Admin', 'render_communications_page']);
        add_submenu_page('sc-events', __('FAQ', 'schedule-checkin'), __('FAQ', 'schedule-checkin'), 'manage_options', 'sc-faq', ['SC_Admin', 'render_faq_page']);
        add_submenu_page('sc-events', __('Settings', 'schedule-checkin'), __('Settings', 'schedule-checkin'), 'manage_options', 'sc-settings', ['SC_Admin', 'render_settings_page']);
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'sc-') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('sc-admin');
        wp_enqueue_script('sc-admin');

        wp_localize_script('sc-admin', 'scAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sc_admin_nonce'),
            'mediaTitle' => __('Select Event Image', 'schedule-checkin'),
            'mediaButton' => __('Use Image', 'schedule-checkin'),
        ]);
    }

    public function public_assets() {
        wp_enqueue_style('sc-public');
        wp_enqueue_script('sc-public');

        wp_localize_script('sc-public', 'scPublic', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sc_public_nonce'),
        ]);
    }

    public function render_standalone_kiosk() {
        $event_id = 0;
        $should_render_kiosk = false;

        if (isset($_GET['sc_kiosk']) && isset($_GET['event_id'])) {
            $should_render_kiosk = true;
            $event_id = absint($_GET['event_id']);
        } elseif (is_singular()) {
            $post_id = (int) get_queried_object_id();
            $post = $post_id ? get_post($post_id) : null;

            if ($post && !empty($post->post_content) && has_shortcode($post->post_content, 'sc_kiosk')) {
                $should_render_kiosk = true;
                $pattern = get_shortcode_regex(['sc_kiosk']);
                if (preg_match('/' . $pattern . '/', $post->post_content, $matches) && isset($matches[3])) {
                    $atts = shortcode_parse_atts($matches[3]);
                    if (is_array($atts) && isset($atts['event_id'])) {
                        $event_id = absint($atts['event_id']);
                    }
                }
            }
        }

        if (!$should_render_kiosk) {
            return;
        }

        if (!$event_id) {
            wp_die(esc_html__('Invalid event.', 'schedule-checkin'));
        }

        nocache_headers();
        echo SC_Shortcodes::render_kiosk($event_id, true);
        exit;
    }
}
