<?php

if (!defined('ABSPATH')) {
    exit;
}

class SC_Admin {
    private static $plugin_email_from = '';
    private static $plugin_email_from_name = '';

    public static function init() {
        add_action('admin_post_sc_save_event', [__CLASS__, 'handle_save_event']);
        add_action('admin_post_sc_delete_event', [__CLASS__, 'handle_delete_event']);
        add_action('admin_post_sc_save_task', [__CLASS__, 'handle_save_task']);
        add_action('admin_post_sc_generate_tasks', [__CLASS__, 'handle_generate_tasks']);
        add_action('admin_post_sc_delete_task', [__CLASS__, 'handle_delete_task']);
        add_action('admin_post_sc_delete_all_tasks', [__CLASS__, 'handle_delete_all_tasks']);
        add_action('admin_post_sc_update_assignment', [__CLASS__, 'handle_update_assignment']);
        add_action('admin_post_sc_assign_substitute', [__CLASS__, 'handle_assign_substitute']);
        add_action('admin_post_sc_remove_assignment', [__CLASS__, 'handle_remove_assignment']);
        add_action('admin_post_sc_checkout_all', [__CLASS__, 'handle_checkout_all']);
        add_action('admin_post_sc_update_volunteer', [__CLASS__, 'handle_update_volunteer']);
        add_action('admin_post_sc_merge_volunteers', [__CLASS__, 'handle_merge_volunteers']);
        add_action('admin_post_sc_set_volunteer_active', [__CLASS__, 'handle_set_volunteer_active']);
        add_action('admin_post_sc_update_log', [__CLASS__, 'handle_update_log']);
        add_action('admin_post_sc_delete_log', [__CLASS__, 'handle_delete_log']);
        add_action('admin_post_sc_export_event_hours_csv', [__CLASS__, 'handle_export_event_hours_csv']);
        add_action('admin_post_sc_export_volunteer_hours_csv', [__CLASS__, 'handle_export_volunteer_hours_csv']);
        add_action('admin_post_sc_export_logs_csv', [__CLASS__, 'handle_export_logs_csv']);
        add_action('admin_post_sc_export_event_hours_excel', [__CLASS__, 'handle_export_event_hours_excel']);
        add_action('admin_post_sc_export_volunteer_hours_excel', [__CLASS__, 'handle_export_volunteer_hours_excel']);
        add_action('admin_post_sc_export_logs_excel', [__CLASS__, 'handle_export_logs_excel']);
        add_action('admin_post_sc_export_event_hours_pdf', [__CLASS__, 'handle_export_event_hours_pdf']);
        add_action('admin_post_sc_export_volunteer_hours_pdf', [__CLASS__, 'handle_export_volunteer_hours_pdf']);
        add_action('admin_post_sc_export_logs_pdf', [__CLASS__, 'handle_export_logs_pdf']);
        add_action('admin_post_sc_export_assignment_report_csv', [__CLASS__, 'handle_export_assignment_report_csv']);
        add_action('admin_post_sc_export_assignment_report_excel', [__CLASS__, 'handle_export_assignment_report_excel']);
        add_action('admin_post_sc_export_assignment_report_pdf', [__CLASS__, 'handle_export_assignment_report_pdf']);
        add_action('admin_post_sc_export_advanced_report_csv', [__CLASS__, 'handle_export_advanced_report_csv']);
        add_action('admin_post_sc_export_advanced_report_excel', [__CLASS__, 'handle_export_advanced_report_excel']);
        add_action('admin_post_sc_export_advanced_report_pdf', [__CLASS__, 'handle_export_advanced_report_pdf']);
        add_action('admin_post_sc_export_comm_logs_csv', [__CLASS__, 'handle_export_comm_logs_csv']);
        add_action('admin_post_sc_export_comm_logs_excel', [__CLASS__, 'handle_export_comm_logs_excel']);
        add_action('admin_post_sc_export_comm_logs_pdf', [__CLASS__, 'handle_export_comm_logs_pdf']);
        add_action('admin_post_sc_reset_database', [__CLASS__, 'handle_reset_database']);
        add_action('admin_post_sc_save_comm_settings', [__CLASS__, 'handle_save_comm_settings']);
        add_action('admin_post_sc_send_test_sms', [__CLASS__, 'handle_send_test_sms']);
        add_action('admin_post_sc_send_test_email', [__CLASS__, 'handle_send_test_email']);
        add_action('admin_post_sc_save_comm_template', [__CLASS__, 'handle_save_comm_template']);
        add_action('admin_post_sc_delete_comm_template', [__CLASS__, 'handle_delete_comm_template']);
        add_action('admin_post_sc_send_event_scheduled_message', [__CLASS__, 'handle_send_event_scheduled_message']);
        add_action('admin_post_sc_send_substitute_pool_message', [__CLASS__, 'handle_send_substitute_pool_message']);
        add_action('admin_post_sc_send_mass_message', [__CLASS__, 'handle_send_mass_message']);
        add_action('admin_post_sc_send_assignment_report_now', [__CLASS__, 'handle_send_assignment_report_now']);
        add_action('wp_ajax_sc_move_assignment', [__CLASS__, 'handle_move_assignment_ajax']);
    }

    private static function tables() {
        global $wpdb;
        return [
            'events' => $wpdb->prefix . 'sc_events',
            'tasks' => $wpdb->prefix . 'sc_tasks',
            'volunteers' => $wpdb->prefix . 'sc_volunteers',
            'assignments' => $wpdb->prefix . 'sc_assignments',
            'checkins' => $wpdb->prefix . 'sc_checkins',
            'comm_templates' => $wpdb->prefix . 'sc_comm_templates',
            'comm_logs' => $wpdb->prefix . 'sc_comm_logs',
            'settings' => $wpdb->prefix . 'sc_settings',
        ];
    }

    private static function render_page_intro($title, $description = '', $show_dependency_status = false) {
        echo '<h1>' . esc_html($title) . '</h1>';
        if ($description !== '') {
            echo '<p class="description">' . esc_html($description) . '</p>';
        }
        if ($show_dependency_status) {
            self::render_dependency_status();
        }
        self::render_admin_notices();
    }

    public static function render_events_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }

        global $wpdb;
        $t = self::tables();

        $events = $wpdb->get_results(
            "SELECT e.*,
                COUNT(t.id) AS task_count,
                COALESCE(SUM(t.slots), 0) AS slot_count
             FROM {$t['events']} e
             LEFT JOIN {$t['tasks']} t ON t.event_id = e.id
             GROUP BY e.id
             ORDER BY e.start_datetime DESC"
        );
        $selected_event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $selected_task_id = isset($_GET['task_id']) ? absint($_GET['task_id']) : 0;
        $task_sort = isset($_GET['task_sort']) ? sanitize_key($_GET['task_sort']) : 'time';
        $task_dir = isset($_GET['task_dir']) ? strtolower(sanitize_text_field($_GET['task_dir'])) : 'asc';
        $task_dir = in_array($task_dir, ['asc', 'desc'], true) ? $task_dir : 'asc';
        $selected_event = null;
        $selected_task = null;
        $tasks = [];
        $email_templates = $wpdb->get_results("SELECT id, name FROM {$t['comm_templates']} WHERE channel = 'email' ORDER BY name ASC");
        $sms_templates = $wpdb->get_results("SELECT id, name FROM {$t['comm_templates']} WHERE channel = 'sms' ORDER BY name ASC");
        $event_categories = self::get_event_categories();

        if ($selected_event_id) {
            $selected_event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['events']} WHERE id = %d", $selected_event_id));
            $order_by = $task_sort === 'title' ? 'title' : 'start_datetime';
            $tasks = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['tasks']} WHERE event_id = %d ORDER BY {$order_by} {$task_dir}, id ASC", $selected_event_id));
            if ($selected_task_id) {
                $selected_task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['tasks']} WHERE id = %d AND event_id = %d", $selected_task_id, $selected_event_id));
            }
        }
        $selected_event_category = self::normalize_event_category((string) ($selected_event->event_category ?? ''), $event_categories);
        $selected_assignment_schedule = self::sanitize_assignment_auto_schedule((string) ($selected_event->assignment_auto_schedule ?? 'weekly_monday'));
        $selected_allow_substitutes = isset($selected_event->allow_substitutes) ? (int) $selected_event->allow_substitutes : 1;

        $total_events = count($events);
        $total_tasks = 0;
        $total_slots = 0;
        foreach ($events as $event_row) {
            $total_tasks += (int) $event_row->task_count;
            $total_slots += (int) $event_row->slot_count;
        }

        $fields = ['name', 'email', 'phone'];
        if ($selected_event && !empty($selected_event->visible_fields)) {
            $decoded = json_decode($selected_event->visible_fields, true);
            if (is_array($decoded) && !empty($decoded)) {
                $fields = $decoded;
            }
        }

        $image_url = '';
        if ($selected_event && !empty($selected_event->image_id)) {
            $image_url = wp_get_attachment_image_url((int) $selected_event->image_id, [300, 200]);
        }

        ?>
        <div class="wrap sc-admin-wrap">
            <?php self::render_page_intro(__('Schedule & Check-In', 'schedule-checkin'), __('Create events, manage tasks, and configure reminder behavior from one place.', 'schedule-checkin')); ?>

            <div class="sc-stats-grid">
                <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Total Events', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) $total_events; ?></div></div>
                <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Total Tasks', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) $total_tasks; ?></div></div>
                <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Total Slots', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) $total_slots; ?></div></div>
            </div>

            <div class="sc-card">
                <h2><?php echo $selected_event ? esc_html__('Edit Event', 'schedule-checkin') : esc_html__('Create Event', 'schedule-checkin'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_save_event'); ?>
                    <input type="hidden" name="action" value="sc_save_event" />
                    <input type="hidden" name="event_id" value="<?php echo esc_attr($selected_event_id); ?>" />

                    <div class="sc-grid-2">
                        <label>
                            <span><?php esc_html_e('Event Title', 'schedule-checkin'); ?></span>
                            <input required type="text" name="title" value="<?php echo esc_attr($selected_event->title ?? ''); ?>" />
                        </label>
                        <div>
                            <span><?php esc_html_e('Event Image', 'schedule-checkin'); ?></span>
                            <div class="sc-media-picker">
                                <input type="hidden" id="sc_image_id" name="image_id" value="<?php echo esc_attr($selected_event->image_id ?? ''); ?>" />
                                <button type="button" class="button" id="sc_pick_image"><?php esc_html_e('Choose Image', 'schedule-checkin'); ?></button>
                                <button type="button" class="button" id="sc_clear_image"><?php esc_html_e('Clear', 'schedule-checkin'); ?></button>
                            </div>
                        </div>
                        <label>
                            <span><?php esc_html_e('Start Date/Time (CST)', 'schedule-checkin'); ?></span>
                            <input required type="datetime-local" name="start_datetime" value="<?php echo esc_attr(self::for_input_datetime_cst($selected_event->start_datetime ?? '')); ?>" />
                        </label>
                        <label>
                            <span><?php esc_html_e('End Date/Time (CST)', 'schedule-checkin'); ?></span>
                            <input required type="datetime-local" name="end_datetime" value="<?php echo esc_attr(self::for_input_datetime_cst($selected_event->end_datetime ?? '')); ?>" />
                        </label>
                        <label>
                            <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                            <select name="event_category">
                                <option value=""><?php esc_html_e('None', 'schedule-checkin'); ?></option>
                                <?php foreach ($event_categories as $event_category_option) : ?>
                                    <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($selected_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Event Owner Name', 'schedule-checkin'); ?></span>
                            <input type="text" name="owner_name" value="<?php echo esc_attr($selected_event->owner_name ?? ''); ?>" />
                        </label>
                        <label>
                            <span><?php esc_html_e('Event Owner Email', 'schedule-checkin'); ?></span>
                            <input type="email" name="owner_email" value="<?php echo esc_attr($selected_event->owner_email ?? ''); ?>" />
                        </label>
                    </div>

                    <label>
                        <span><?php esc_html_e('Description', 'schedule-checkin'); ?></span>
                        <textarea name="description" rows="4"><?php echo esc_textarea($selected_event->description ?? ''); ?></textarea>
                    </label>

                    <div class="sc-grid-3">
                        <label>
                            <span><?php esc_html_e('Volunteer fields shown on signup', 'schedule-checkin'); ?></span>
                            <select multiple name="visible_fields[]">
                                <?php foreach (['name', 'email', 'phone'] as $field) : ?>
                                    <option value="<?php echo esc_attr($field); ?>" <?php selected(in_array($field, $fields, true)); ?>><?php echo esc_html(ucfirst($field)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Require Check-Out', 'schedule-checkin'); ?></span>
                            <select name="require_checkout">
                                <option value="1" <?php selected((int) ($selected_event->require_checkout ?? 1), 1); ?>><?php esc_html_e('Yes', 'schedule-checkin'); ?></option>
                                <option value="0" <?php selected((int) ($selected_event->require_checkout ?? 1), 0); ?>><?php esc_html_e('No', 'schedule-checkin'); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Allow Substitutes', 'schedule-checkin'); ?></span>
                            <select name="allow_substitutes">
                                <option value="1" <?php selected($selected_allow_substitutes, 1); ?>><?php esc_html_e('Yes', 'schedule-checkin'); ?></option>
                                <option value="0" <?php selected($selected_allow_substitutes, 0); ?>><?php esc_html_e('No', 'schedule-checkin'); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Universal Admin PIN', 'schedule-checkin'); ?></span>
                            <input required type="text" maxlength="10" name="admin_pin" value="<?php echo esc_attr($selected_event->admin_pin ?? ''); ?>" />
                        </label>
                    </div>

                    <h3><?php esc_html_e('Scheduled Reminder Settings', 'schedule-checkin'); ?></h3>
                    <p class="description"><?php esc_html_e('Configure up to two reminders sent before the event through each volunteer\'s preferred communication method.', 'schedule-checkin'); ?></p>
                    <div class="sc-grid-2">
                        <div>
                            <label class="sc-checkbox-label">
                                <input type="checkbox" name="reminder_1_enabled" value="1" <?php checked((int) ($selected_event->reminder_1_enabled ?? 0), 1); ?> />
                                <span><?php esc_html_e('Enable Reminder 1', 'schedule-checkin'); ?></span>
                            </label>
                            <label><span><?php esc_html_e('Days Before', 'schedule-checkin'); ?></span><input type="number" min="0" name="reminder_1_days_before" value="<?php echo esc_attr((int) ($selected_event->reminder_1_days_before ?? 2)); ?>" /></label>
                            <label>
                                <span><?php esc_html_e('Email Template', 'schedule-checkin'); ?></span>
                                <select name="reminder_1_email_template_id">
                                    <option value="0"><?php esc_html_e('None', 'schedule-checkin'); ?></option>
                                    <?php foreach ($email_templates as $template) : ?>
                                        <option value="<?php echo (int) $template->id; ?>" <?php selected((int) ($selected_event->reminder_1_email_template_id ?? 0), (int) $template->id); ?>><?php echo esc_html($template->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span><?php esc_html_e('SMS Template', 'schedule-checkin'); ?></span>
                                <select name="reminder_1_sms_template_id">
                                    <option value="0"><?php esc_html_e('None', 'schedule-checkin'); ?></option>
                                    <?php foreach ($sms_templates as $template) : ?>
                                        <option value="<?php echo (int) $template->id; ?>" <?php selected((int) ($selected_event->reminder_1_sms_template_id ?? 0), (int) $template->id); ?>><?php echo esc_html($template->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div>
                            <label class="sc-checkbox-label">
                                <input type="checkbox" name="reminder_2_enabled" value="1" <?php checked((int) ($selected_event->reminder_2_enabled ?? 0), 1); ?> />
                                <span><?php esc_html_e('Enable Reminder 2', 'schedule-checkin'); ?></span>
                            </label>
                            <label><span><?php esc_html_e('Days Before', 'schedule-checkin'); ?></span><input type="number" min="0" name="reminder_2_days_before" value="<?php echo esc_attr((int) ($selected_event->reminder_2_days_before ?? 1)); ?>" /></label>
                            <label>
                                <span><?php esc_html_e('Email Template', 'schedule-checkin'); ?></span>
                                <select name="reminder_2_email_template_id">
                                    <option value="0"><?php esc_html_e('None', 'schedule-checkin'); ?></option>
                                    <?php foreach ($email_templates as $template) : ?>
                                        <option value="<?php echo (int) $template->id; ?>" <?php selected((int) ($selected_event->reminder_2_email_template_id ?? 0), (int) $template->id); ?>><?php echo esc_html($template->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span><?php esc_html_e('SMS Template', 'schedule-checkin'); ?></span>
                                <select name="reminder_2_sms_template_id">
                                    <option value="0"><?php esc_html_e('None', 'schedule-checkin'); ?></option>
                                    <?php foreach ($sms_templates as $template) : ?>
                                        <option value="<?php echo (int) $template->id; ?>" <?php selected((int) ($selected_event->reminder_2_sms_template_id ?? 0), (int) $template->id); ?>><?php echo esc_html($template->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                    </div>

                    <h3><?php esc_html_e('Automatic Assignment Reports', 'schedule-checkin'); ?></h3>
                    <p class="description"><?php esc_html_e('When enabled, assignment reports are emailed to the event owner based on the selected schedule, plus a final report at 6:00 AM CST on event day.', 'schedule-checkin'); ?></p>
                    <div class="sc-grid-2">
                        <label>
                            <span><?php esc_html_e('Send Assignment Reports Automatically', 'schedule-checkin'); ?></span>
                            <select name="assignment_auto_enabled">
                                <option value="0" <?php selected((int) ($selected_event->assignment_auto_enabled ?? 0), 0); ?>><?php esc_html_e('No', 'schedule-checkin'); ?></option>
                                <option value="1" <?php selected((int) ($selected_event->assignment_auto_enabled ?? 0), 1); ?>><?php esc_html_e('Yes', 'schedule-checkin'); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Assignment Report Schedule', 'schedule-checkin'); ?></span>
                            <select name="assignment_auto_schedule">
                                <option value="weekly_monday" <?php selected($selected_assignment_schedule, 'weekly_monday'); ?>><?php esc_html_e('Weekly (Mondays)', 'schedule-checkin'); ?></option>
                                <option value="biweekly_monday" <?php selected($selected_assignment_schedule, 'biweekly_monday'); ?>><?php esc_html_e('Bi-Weekly (Mondays)', 'schedule-checkin'); ?></option>
                                <option value="biweekly_monday_thursday" <?php selected($selected_assignment_schedule, 'biweekly_monday_thursday'); ?>><?php esc_html_e('Bi-Weekly (Mondays + Thursdays)', 'schedule-checkin'); ?></option>
                            </select>
                        </label>
                    </div>

                    <div class="sc-image-preview" id="sc_image_preview" <?php echo $image_url ? '' : 'style="display:none;"'; ?>>
                        <img src="<?php echo esc_url($image_url); ?>" alt="" width="300" height="200" />
                    </div>

                    <p>
                        <button class="button button-primary" type="submit"><?php esc_html_e('Save Event', 'schedule-checkin'); ?></button>
                    </p>
                </form>
                <?php if ($selected_event_id) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                        <?php wp_nonce_field('sc_send_assignment_report_now'); ?>
                        <input type="hidden" name="action" value="sc_send_assignment_report_now" />
                        <input type="hidden" name="event_id" value="<?php echo (int) $selected_event_id; ?>" />
                        <button class="button" type="submit"><?php esc_html_e('Send Assignment Report Now', 'schedule-checkin'); ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Events', 'schedule-checkin'); ?></h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Title', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Date', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Category', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Task Count', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Slot Count', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Shortcodes', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Actions', 'schedule-checkin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$events) : ?>
                            <tr><td colspan="7"><?php esc_html_e('No events yet.', 'schedule-checkin'); ?></td></tr>
                        <?php else :
                            foreach ($events as $event) :
                                ?>
                                <tr>
                                    <td><?php echo esc_html($event->title); ?></td>
                                    <td><?php echo esc_html(self::format_range_cst($event->start_datetime, $event->end_datetime)); ?></td>
                                    <td><?php echo self::render_event_category_badge((string) ($event->event_category ?? '')); ?></td>
                                    <td><?php echo (int) $event->task_count; ?></td>
                                    <td><?php echo (int) $event->slot_count; ?></td>
                                    <td>
                                        <strong><?php esc_html_e('Signup:', 'schedule-checkin'); ?></strong>
                                        <code>[sc_signup event_id="<?php echo (int) $event->id; ?>"]</code><br />
                                        <strong><?php esc_html_e('Kiosk Check-In:', 'schedule-checkin'); ?></strong>
                                        <code>[sc_kiosk event_id="<?php echo (int) $event->id; ?>"]</code>
                                    </td>
                                    <td>
                                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-events&event_id=' . (int) $event->id)); ?>"><?php esc_html_e('Edit', 'schedule-checkin'); ?></a>
                                        <form class="sc-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <?php wp_nonce_field('sc_delete_event'); ?>
                                            <input type="hidden" name="action" value="sc_delete_event" />
                                            <input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>" />
                                            <button type="submit" class="button button-link-delete"><?php esc_html_e('Delete', 'schedule-checkin'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>

            <?php if ($selected_event) : ?>
                <div class="sc-card">
                    <h2><?php esc_html_e('Tasks', 'schedule-checkin'); ?></h2>

                    <details class="sc-card sc-task-wizard" open>
                        <summary><strong><?php esc_html_e('Task Wizard (Generate Across Event Timeframe)', 'schedule-checkin'); ?></strong></summary>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('sc_generate_tasks'); ?>
                            <input type="hidden" name="action" value="sc_generate_tasks" />
                            <input type="hidden" name="event_id" value="<?php echo (int) $selected_event->id; ?>" />

                            <div class="sc-grid-3">
                                <label>
                                    <span><?php esc_html_e('Task Title Schema', 'schedule-checkin'); ?></span>
                                    <input required type="text" name="title_schema" value="Adorer_{start_cst} - {end_cst}" />
                                </label>
                                <label>
                                    <span><?php esc_html_e('Interval Minutes', 'schedule-checkin'); ?></span>
                                    <select name="interval_minutes">
                                        <?php foreach ([15, 30, 60, 120] as $interval) : ?>
                                            <option value="<?php echo (int) $interval; ?>" <?php selected($interval, 30); ?>><?php echo (int) $interval; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span><?php esc_html_e('Slots Per Task', 'schedule-checkin'); ?></span>
                                    <input required type="number" min="1" name="slots" value="1" />
                                </label>
                            </div>

                            <label class="sc-checkbox-label">
                                <input type="checkbox" name="replace_existing" value="1" id="sc_replace_existing_toggle" />
                                <span><?php esc_html_e('Replace existing tasks for this event before generating', 'schedule-checkin'); ?></span>
                            </label>

                            <label id="sc_replace_confirm_row" class="sc-confirm-row is-hidden" aria-hidden="true">
                                <span><?php esc_html_e('Type REPLACE to confirm destructive regeneration', 'schedule-checkin'); ?></span>
                                <input type="text" name="replace_confirm" value="" placeholder="REPLACE" id="sc_replace_confirm_input" disabled />
                            </label>

                            <label>
                                <span><?php esc_html_e('Description for Generated Tasks (optional)', 'schedule-checkin'); ?></span>
                                <textarea name="description" rows="2"></textarea>
                            </label>
                            <p class="description"><?php esc_html_e('Schema tokens: {start_cst}, {end_cst}, {start_date_cst}, {end_date_cst}, {start_time_cst}, {end_time_cst}', 'schedule-checkin'); ?></p>
                            <p class="description"><?php esc_html_e('Tokens are optional. Example fixed title: Adorer', 'schedule-checkin'); ?></p>
                            <p class="description"><?php esc_html_e('Event end date/time must be later than event start date/time to generate tasks.', 'schedule-checkin'); ?></p>
                            <p><button class="button button-primary" type="submit"><?php esc_html_e('Generate Tasks', 'schedule-checkin'); ?></button></p>
                        </form>
                    </details>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('sc_save_task'); ?>
                        <input type="hidden" name="action" value="sc_save_task" />
                        <input type="hidden" name="event_id" value="<?php echo (int) $selected_event->id; ?>" />
                        <input type="hidden" name="task_id" value="<?php echo (int) ($selected_task->id ?? 0); ?>" />

                        <div class="sc-grid-2">
                            <label>
                                <span><?php esc_html_e('Task Title', 'schedule-checkin'); ?></span>
                                <input required type="text" name="title" value="<?php echo esc_attr($selected_task->title ?? ''); ?>" />
                            </label>
                            <label>
                                <span><?php esc_html_e('Slots', 'schedule-checkin'); ?></span>
                                <input required type="number" min="1" name="slots" value="<?php echo esc_attr((int) ($selected_task->slots ?? 1)); ?>" />
                            </label>
                            <label>
                                <span><?php esc_html_e('Task Start (CST)', 'schedule-checkin'); ?></span>
                                <input required type="datetime-local" name="start_datetime" value="<?php echo esc_attr(self::for_input_datetime_cst($selected_task->start_datetime ?? '')); ?>" />
                            </label>
                            <label>
                                <span><?php esc_html_e('Task End (CST)', 'schedule-checkin'); ?></span>
                                <input required type="datetime-local" name="end_datetime" value="<?php echo esc_attr(self::for_input_datetime_cst($selected_task->end_datetime ?? '')); ?>" />
                            </label>
                        </div>
                        <label>
                            <span><?php esc_html_e('Task Description (optional)', 'schedule-checkin'); ?></span>
                            <textarea name="description" rows="3"><?php echo esc_textarea($selected_task->description ?? ''); ?></textarea>
                        </label>
                        <p>
                            <button class="button button-primary" type="submit"><?php echo $selected_task ? esc_html__('Update Task', 'schedule-checkin') : esc_html__('Add Task', 'schedule-checkin'); ?></button>
                            <?php if ($selected_task) : ?>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-events&event_id=' . (int) $selected_event->id)); ?>"><?php esc_html_e('Cancel Edit', 'schedule-checkin'); ?></a>
                            <?php endif; ?>
                        </p>
                    </form>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>
                                    <?php
                                    $title_next_dir = ($task_sort === 'title' && $task_dir === 'asc') ? 'desc' : 'asc';
                                    $title_sort_url = admin_url('admin.php?page=sc-events&event_id=' . (int) $selected_event->id . '&task_sort=title&task_dir=' . $title_next_dir);
                                    ?>
                                    <a href="<?php echo esc_url($title_sort_url); ?>"><?php esc_html_e('Task Title', 'schedule-checkin'); ?></a>
                                </th>
                                <th>
                                    <?php
                                    $time_next_dir = ($task_sort === 'time' && $task_dir === 'asc') ? 'desc' : 'asc';
                                    $time_sort_url = admin_url('admin.php?page=sc-events&event_id=' . (int) $selected_event->id . '&task_sort=time&task_dir=' . $time_next_dir);
                                    ?>
                                    <a href="<?php echo esc_url($time_sort_url); ?>"><?php esc_html_e('Time', 'schedule-checkin'); ?></a>
                                </th>
                                <th><?php esc_html_e('Slots', 'schedule-checkin'); ?></th>
                                <th><?php esc_html_e('Actions', 'schedule-checkin'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$tasks) : ?>
                                <tr><td colspan="4"><?php esc_html_e('No tasks yet.', 'schedule-checkin'); ?></td></tr>
                            <?php else : foreach ($tasks as $task) : ?>
                                <tr>
                                    <td><?php echo esc_html($task->title); ?><br/><small><?php echo esc_html($task->description); ?></small></td>
                                    <td><?php echo esc_html(self::format_range_cst($task->start_datetime, $task->end_datetime)); ?></td>
                                    <td><?php echo (int) $task->slots; ?></td>
                                    <td>
                                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-events&event_id=' . (int) $selected_event->id . '&task_id=' . (int) $task->id)); ?>"><?php esc_html_e('Edit', 'schedule-checkin'); ?></a>
                                        <form class="sc-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                            <?php wp_nonce_field('sc_delete_task'); ?>
                                            <input type="hidden" name="action" value="sc_delete_task" />
                                            <input type="hidden" name="event_id" value="<?php echo (int) $selected_event->id; ?>" />
                                            <input type="hidden" name="task_id" value="<?php echo (int) $task->id; ?>" />
                                            <button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Delete this task and its assignments?', 'schedule-checkin')); ?>');"><?php esc_html_e('Delete', 'schedule-checkin'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('sc_delete_all_tasks'); ?>
                        <input type="hidden" name="action" value="sc_delete_all_tasks" />
                        <input type="hidden" name="event_id" value="<?php echo (int) $selected_event->id; ?>" />
                        <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Delete ALL tasks and related assignments/check-ins for this event?', 'schedule-checkin')); ?>');"><?php esc_html_e('Delete All Tasks for Event', 'schedule-checkin'); ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function render_assignments_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }

        global $wpdb;
        $t = self::tables();
        $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $events = $wpdb->get_results("SELECT id, title FROM {$t['events']} ORDER BY start_datetime DESC");
        $selected_event_allows_substitutes = true;

        if (!$event_id && !empty($events)) {
            $event_id = (int) $events[0]->id;
        }
        if ($event_id) {
            $selected_event_allows_substitutes = self::event_allows_substitutes($event_id);
        }

        $tasks = [];
        $assignments_by_task = [];
        $substitute_pool = [];
        if ($event_id) {
            $tasks = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['tasks']} WHERE event_id = %d ORDER BY start_datetime ASC", $event_id));
            $assignments = $wpdb->get_results($wpdb->prepare(
                "SELECT a.*, v.name, v.email, v.phone FROM {$t['assignments']} a
                 INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
                 WHERE a.event_id = %d ORDER BY a.task_id ASC, a.slot_number ASC",
                $event_id
            ));

            foreach ($assignments as $assignment) {
                if ((string) $assignment->assignment_type === 'substitute' && empty($assignment->task_id)) {
                    if ($selected_event_allows_substitutes) {
                        $substitute_pool[] = $assignment;
                    }
                    continue;
                }
                if (empty($assignment->task_id)) {
                    continue;
                }
                if (!isset($assignments_by_task[$assignment->task_id])) {
                    $assignments_by_task[$assignment->task_id] = [];
                }
                $assignments_by_task[$assignment->task_id][] = $assignment;
            }
        }

        ?>
        <div class="wrap sc-admin-wrap">
            <?php self::render_page_intro(__('Assignments (Drag & Drop)', 'schedule-checkin'), __('Manage slot assignments, substitute placement, and final checkout actions.', 'schedule-checkin')); ?>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="sc-assignments" />
                <label>
                    <span><?php esc_html_e('Event', 'schedule-checkin'); ?></span>
                    <select name="event_id">
                        <?php foreach ($events as $event) : ?>
                            <option value="<?php echo (int) $event->id; ?>" <?php selected($event_id, $event->id); ?>><?php echo esc_html($event->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button button-primary" type="submit"><?php esc_html_e('Load', 'schedule-checkin'); ?></button>
            </form>

            <?php if ($selected_event_allows_substitutes && !empty($substitute_pool) && !empty($tasks)) : ?>
                <div class="sc-card">
                    <h2><?php esc_html_e('Assign Substitute to Open Slot', 'schedule-checkin'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('sc_assign_substitute'); ?>
                        <input type="hidden" name="action" value="sc_assign_substitute" />
                        <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                        <div class="sc-grid-3">
                            <label>
                                <span><?php esc_html_e('Substitute', 'schedule-checkin'); ?></span>
                                <select required name="assignment_id">
                                    <option value=""><?php esc_html_e('Select substitute', 'schedule-checkin'); ?></option>
                                    <?php foreach ($substitute_pool as $substitute) : ?>
                                        <option value="<?php echo (int) $substitute->id; ?>"><?php echo esc_html($substitute->name . ' | ' . ($substitute->email ?: __('No email', 'schedule-checkin'))); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span><?php esc_html_e('Task', 'schedule-checkin'); ?></span>
                                <select required name="task_id">
                                    <option value=""><?php esc_html_e('Select task', 'schedule-checkin'); ?></option>
                                    <?php foreach ($tasks as $task) : ?>
                                        <option value="<?php echo (int) $task->id; ?>"><?php echo esc_html($task->title . ' | ' . self::format_range_cst($task->start_datetime, $task->end_datetime)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span><?php esc_html_e('Slot', 'schedule-checkin'); ?></span>
                                <input required type="number" min="1" name="slot_number" />
                            </label>
                        </div>
                        <button class="button button-primary" type="submit"><?php esc_html_e('Assign Substitute', 'schedule-checkin'); ?></button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="sc-task-columns">
                <?php foreach ($tasks as $task) :
                    $task_assignments = $assignments_by_task[$task->id] ?? [];
                    ?>
                    <div class="sc-task-column" data-task-id="<?php echo (int) $task->id; ?>" data-event-id="<?php echo (int) $event_id; ?>">
                        <h3><?php echo esc_html($task->title); ?> <small>(<?php echo (int) $task->slots; ?>)</small></h3>
                        <div class="sc-task-time-meta">
                            <small><strong><?php esc_html_e('Start:', 'schedule-checkin'); ?></strong> <?php echo esc_html(self::format_datetime_cst_12h($task->start_datetime)); ?></small><br />
                            <small><strong><?php esc_html_e('End:', 'schedule-checkin'); ?></strong> <?php echo esc_html(self::format_datetime_cst_12h($task->end_datetime)); ?></small>
                        </div>
                        <ul class="sc-sortable">
                            <?php for ($slot = 1; $slot <= (int) $task->slots; $slot++) :
                                $found = null;
                                foreach ($task_assignments as $a) {
                                    if ((int) $a->slot_number === $slot) {
                                        $found = $a;
                                        break;
                                    }
                                }
                                ?>
                                <li class="sc-slot<?php echo $found ? ' sc-slot-assigned' : ' sc-slot-open'; ?>" data-slot-number="<?php echo (int) $slot; ?>" data-assignment-id="<?php echo (int) ($found->id ?? 0); ?>">
                                    <strong><?php echo sprintf(esc_html__('Slot %d', 'schedule-checkin'), $slot); ?></strong>
                                    <?php if ($found) : ?>
                                        <div class="sc-volunteer-display">
                                            <h4 class="sc-volunteer-name"><?php echo esc_html($found->name); ?> <small>(<?php echo esc_html(ucfirst(($selected_event_allows_substitutes ? (string) ($found->assignment_type ?: 'scheduled') : 'scheduled'))); ?>)</small></h4>
                                            <div class="sc-volunteer-row"><?php echo esc_html($found->email ?: __('No email', 'schedule-checkin')); ?></div>
                                            <div class="sc-volunteer-row"><?php echo esc_html(self::format_phone_for_display($found->phone)); ?></div>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-assignment-remove-form">
                                                <?php wp_nonce_field('sc_remove_assignment'); ?>
                                                <input type="hidden" name="action" value="sc_remove_assignment" />
                                                <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                                                <input type="hidden" name="assignment_id" value="<?php echo (int) $found->id; ?>" />
                                                <?php if ($selected_event_allows_substitutes) : ?>
                                                    <label class="sc-checkbox-label">
                                                        <input type="checkbox" name="move_to_substitute" value="1" />
                                                        <span><?php esc_html_e('Move person to substitute pool for this event', 'schedule-checkin'); ?></span>
                                                    </label>
                                                <?php endif; ?>
                                                <button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Remove this assignment from the slot? If checked, they will be moved to substitute pool; otherwise the assignment is deleted for this event.', 'schedule-checkin')); ?>');"><?php esc_html_e('Remove Assignment', 'schedule-checkin'); ?></button>
                                            </form>
                                        </div>
                                    <?php else : ?>
                                        <em><?php esc_html_e('Open', 'schedule-checkin'); ?></em>
                                    <?php endif; ?>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('sc_checkout_all'); ?>
                <input type="hidden" name="action" value="sc_checkout_all" />
                <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                <button class="button button-primary" type="submit"><?php esc_html_e('Check Out All Remaining', 'schedule-checkin'); ?></button>
            </form>
        </div>
        <?php
    }

    public static function render_reports_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }

        global $wpdb;
        $t = self::tables();
        $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $assignment_event_id = isset($_GET['assignment_event_id']) ? absint($_GET['assignment_event_id']) : 0;
        $coverage_event_id = isset($_GET['coverage_event_id']) ? absint($_GET['coverage_event_id']) : 0;
        $substitute_event_id = isset($_GET['substitute_event_id']) ? absint($_GET['substitute_event_id']) : 0;
        $substitute_history_event_id = isset($_GET['substitute_history_event_id']) ? absint($_GET['substitute_history_event_id']) : 0;
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'month';
        $volunteer_id = isset($_GET['volunteer_id']) ? absint($_GET['volunteer_id']) : 0;
        $allowed_tabs = ['event-hours', 'assignment-report', 'volunteer-period', 'volunteers', 'lifetime-hours', 'attendance', 'late-early', 'task-coverage', 'substitute-pool', 'substitute-history', 'checkin-audit', 'sms-analytics', 'retention'];
        $report_tab = isset($_GET['report_tab']) ? sanitize_key($_GET['report_tab']) : 'event-hours';
        $sms_period = isset($_GET['sms_period']) ? sanitize_key($_GET['sms_period']) : 'month';
        $sms_start_date = sanitize_text_field($_GET['sms_start_date'] ?? '');
        $sms_end_date = sanitize_text_field($_GET['sms_end_date'] ?? '');
        $event_categories = self::get_event_categories();
        $report_event_category = self::normalize_event_category(sanitize_text_field($_GET['event_category'] ?? ''), $event_categories);
        $volunteer_report_event_ids = self::sanitize_id_list($_GET['volunteers_event_ids'] ?? []);
        $volunteer_report_event_categories = self::sanitize_event_category_list($_GET['volunteers_event_categories'] ?? [], $event_categories);
        $volunteer_report_volunteer_id = absint($_GET['volunteers_volunteer_id'] ?? 0);
        $volunteer_report_is_active = self::sanitize_volunteer_active_filter($_GET['volunteers_is_active'] ?? 'all');
        $volunteer_report_period = self::sanitize_volunteers_period($_GET['volunteers_period'] ?? 'all-time');
        $volunteer_report_start_date = self::sanitize_date_yyyy_mm_dd($_GET['volunteers_start_date'] ?? '');
        $volunteer_report_end_date = self::sanitize_date_yyyy_mm_dd($_GET['volunteers_end_date'] ?? '');
        if (!isset($_GET['report_tab']) && $coverage_event_id) {
            $report_tab = 'task-coverage';
        }
        if (!isset($_GET['report_tab']) && $substitute_event_id) {
            $report_tab = 'substitute-pool';
        }
        if (!isset($_GET['report_tab']) && $substitute_history_event_id) {
            $report_tab = 'substitute-history';
        }
        if (!in_array($report_tab, $allowed_tabs, true)) {
            $report_tab = 'event-hours';
        }

        if ($report_event_category !== '') {
            $events = $wpdb->get_results($wpdb->prepare("SELECT id, title FROM {$t['events']} WHERE event_category = %s ORDER BY start_datetime DESC", $report_event_category));
        } else {
            $events = $wpdb->get_results("SELECT id, title FROM {$t['events']} ORDER BY start_datetime DESC");
        }
        $all_events = $wpdb->get_results("SELECT id, title FROM {$t['events']} ORDER BY start_datetime DESC");
        $volunteers = $wpdb->get_results("SELECT id, name FROM {$t['volunteers']} ORDER BY name ASC");

        $hours_rows = $event_id ? self::query_event_hours($event_id, $report_event_category) : [];
        $assignment_rows = $assignment_event_id ? self::query_assignment_report_rows($assignment_event_id) : [];
        $assignment_selected_event = $assignment_event_id
            ? $wpdb->get_row($wpdb->prepare("SELECT id, title, owner_name, owner_email FROM {$t['events']} WHERE id = %d", $assignment_event_id))
            : null;

        $volunteer_hours = $volunteer_id ? self::query_volunteer_hours($volunteer_id, $period, $report_event_category) : [];
        $event_total_hours = 0.0;
        foreach ($hours_rows as $hours_row) {
            $event_total_hours += (float) ($hours_row['hours_worked'] ?? 0);
        }
        $event_role_breakdown = $event_id ? self::query_event_role_breakdown($event_id) : [];

        $lifetime_hours_rows = self::query_lifetime_hours($report_event_category);
        $attendance_reliability_rows = self::query_attendance_reliability($report_event_category);
        $late_early_rows = self::query_late_early_analysis($report_event_category);
        $task_coverage_rows = self::query_task_coverage_health($coverage_event_id, $report_event_category);
        $substitute_pool_rows = self::query_substitute_pool($substitute_event_id, $report_event_category);
        $substitute_history_rows = self::query_substitute_history($substitute_history_event_id, $report_event_category);
        $checkin_audit_rows = self::query_checkin_method_audit($report_event_category);
        $sms_analytics = self::query_sms_analytics($sms_period, $sms_start_date, $sms_end_date, $report_event_category);
        $sms_rows = $sms_analytics['rows'];
        $sms_summary = $sms_analytics['summary'];
        $retention_rows = self::query_retention_activity_monthly($report_event_category);
        $volunteers_report_data = self::query_volunteers_report([
            'event_ids' => $volunteer_report_event_ids,
            'event_categories' => $volunteer_report_event_categories,
            'volunteer_id' => $volunteer_report_volunteer_id,
            'is_active' => $volunteer_report_is_active,
            'period' => $volunteer_report_period,
            'start_date' => $volunteer_report_start_date,
            'end_date' => $volunteer_report_end_date,
        ]);
        $volunteers_report_rows = $volunteers_report_data['rows'];
        $volunteers_report_category_columns = $volunteers_report_data['category_columns'];
        $volunteers_report_usage_totals = $volunteers_report_data['usage_totals'];
        $volunteers_report_category_totals = $volunteers_report_data['category_totals'];
        $volunteers_report_grand_total_hours = $volunteers_report_data['grand_total_hours'];

        $substitute_opt_in_count = 0;
        $substitute_available_count = 0;
        $substitute_assigned_count = 0;
        foreach ($substitute_pool_rows as $substitute_row) {
            if ((int) ($substitute_row->future_substitute_opt_in ?? 0) === 1) {
                $substitute_opt_in_count++;
            }
            if ((int) ($substitute_row->available_pool_count ?? 0) > 0) {
                $substitute_available_count++;
            }
            if ((int) ($substitute_row->assigned_slot_count ?? 0) > 0) {
                $substitute_assigned_count++;
            }
        }

        $editing_volunteer = isset($_GET['edit_volunteer']) ? absint($_GET['edit_volunteer']) : 0;
        $edit_volunteer = $editing_volunteer ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['volunteers']} WHERE id = %d", $editing_volunteer)) : null;

        ?>
        <div class="wrap sc-admin-wrap">
            <?php self::render_page_intro(__('Reports', 'schedule-checkin'), __('Review volunteer performance, attendance trends, and export-ready analytics.', 'schedule-checkin')); ?>

            <div class="sc-stats-grid">
                <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Total Events', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) count($events); ?></div></div>
                <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Event Hours Total', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo esc_html(number_format($event_total_hours, 2)); ?></div></div>
                <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Event Volunteers', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) count($hours_rows); ?></div></div>
                <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Volunteer Records', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) count($volunteer_hours); ?></div></div>
            </div>

            <div class="sc-reports-tabs" data-default-tab="<?php echo esc_attr($report_tab); ?>">
                <button type="button" class="button" data-tab="event-hours"><?php esc_html_e('Event Hours', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="assignment-report"><?php esc_html_e('Assignment Report', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="volunteer-period"><?php esc_html_e('Volunteer Period', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="volunteers"><?php esc_html_e('Volunteers', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="lifetime-hours"><?php esc_html_e('Lifetime Hours', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="attendance"><?php esc_html_e('Attendance', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="late-early"><?php esc_html_e('Late / Early', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="task-coverage"><?php esc_html_e('Task Coverage', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="substitute-pool"><?php esc_html_e('Substitute Pool', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="substitute-history"><?php esc_html_e('Substitute History', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="checkin-audit"><?php esc_html_e('Method Audit', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="sms-analytics"><?php esc_html_e('SMS Analytics', 'schedule-checkin'); ?></button>
                <button type="button" class="button" data-tab="retention"><?php esc_html_e('Retention', 'schedule-checkin'); ?></button>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="event-hours">
                <h2><?php esc_html_e('Event Hours Report', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('All times shown in CST.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="event-hours" />
                    <label>
                        <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                        <select name="event_category">
                            <option value=""><?php esc_html_e('All Categories', 'schedule-checkin'); ?></option>
                            <?php foreach ($event_categories as $event_category_option) : ?>
                                <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($report_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Event', 'schedule-checkin'); ?></span>
                        <select name="event_id">
                            <option value="0"><?php esc_html_e('Select Event', 'schedule-checkin'); ?></option>
                            <?php foreach ($events as $event) : ?>
                                <option value="<?php echo (int) $event->id; ?>" <?php selected($event_id, $event->id); ?>><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>
                <?php if ($event_id) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                        <?php wp_nonce_field('sc_export_event_hours_csv'); ?>
                        <input type="hidden" name="action" value="sc_export_event_hours_csv" />
                        <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                        <button class="button" type="submit"><?php esc_html_e('Export Event Hours CSV', 'schedule-checkin'); ?></button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                        <?php wp_nonce_field('sc_export_event_hours_excel'); ?>
                        <input type="hidden" name="action" value="sc_export_event_hours_excel" />
                        <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                        <button class="button" type="submit"><?php esc_html_e('Export Event Hours Excel', 'schedule-checkin'); ?></button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                        <?php wp_nonce_field('sc_export_event_hours_pdf'); ?>
                        <input type="hidden" name="action" value="sc_export_event_hours_pdf" />
                        <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                        <button class="button" type="submit"><?php esc_html_e('Export Event Hours PDF', 'schedule-checkin'); ?></button>
                    </form>
                <?php endif; ?>

                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Volunteer', 'schedule-checkin'); ?></th><th><?php esc_html_e('Email', 'schedule-checkin'); ?></th><th><?php esc_html_e('Event Category', 'schedule-checkin'); ?></th><th><?php esc_html_e('Hours Worked', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (!$hours_rows) : ?>
                            <tr><td colspan="4"><?php esc_html_e('No data available.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($hours_rows as $row) : ?>
                            <tr><td><?php echo esc_html($row['name'] ?? ''); ?></td><td><?php echo esc_html($row['email'] ?? ''); ?></td><td><?php echo self::render_event_category_badge((string) ($row['event_category'] ?? '')); ?></td><td><?php echo esc_html(number_format((float) ($row['hours_worked'] ?? 0), 2)); ?></td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <?php if ($event_id) : ?>
                    <h3><?php esc_html_e('Attendance by Type', 'schedule-checkin'); ?></h3>
                    <table class="widefat striped">
                        <thead><tr><th><?php esc_html_e('Type', 'schedule-checkin'); ?></th><th><?php esc_html_e('Total', 'schedule-checkin'); ?></th><th><?php esc_html_e('Checked In', 'schedule-checkin'); ?></th><th><?php esc_html_e('Completed', 'schedule-checkin'); ?></th></tr></thead>
                        <tbody>
                            <?php if (empty($event_role_breakdown)) : ?>
                                <tr><td colspan="4"><?php esc_html_e('No data available.', 'schedule-checkin'); ?></td></tr>
                            <?php else : foreach ($event_role_breakdown as $type_row) : ?>
                                <tr>
                                    <td><?php echo esc_html(ucfirst((string) ($type_row->assignment_type ?: 'scheduled'))); ?></td>
                                    <td><?php echo (int) $type_row->total_count; ?></td>
                                    <td><?php echo (int) $type_row->checked_in_count; ?></td>
                                    <td><?php echo (int) $type_row->completed_count; ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($event_id) : ?>
                    <p><a class="button" target="_blank" href="<?php echo esc_url(admin_url('admin.php?page=sc-logs&event_id=' . (int) $event_id . '&print_sheet=1')); ?>"><?php esc_html_e('Print Check-In Sheet (PDF)', 'schedule-checkin'); ?></a></p>
                <?php endif; ?>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="assignment-report">
                <h2><?php esc_html_e('Assignment Report', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Shows scheduled volunteers and open slots for a single event, sorted by task start time.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="assignment-report" />
                    <label>
                        <span><?php esc_html_e('Event', 'schedule-checkin'); ?></span>
                        <select name="assignment_event_id">
                            <option value="0"><?php esc_html_e('Select Event', 'schedule-checkin'); ?></option>
                            <?php foreach ($events as $event) : ?>
                                <option value="<?php echo (int) $event->id; ?>" <?php selected($assignment_event_id, $event->id); ?>><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>
                <?php if ($assignment_event_id && $assignment_selected_event) : ?>
                    <p class="description">
                        <?php
                        $owner_name = trim((string) ($assignment_selected_event->owner_name ?? ''));
                        $owner_email = trim((string) ($assignment_selected_event->owner_email ?? ''));
                        if ($owner_email !== '') {
                            if ($owner_name !== '') {
                                echo esc_html(sprintf(__('Manual send will email %1$s (%2$s).', 'schedule-checkin'), $owner_name, $owner_email));
                            } else {
                                echo esc_html(sprintf(__('Manual send will email %s.', 'schedule-checkin'), $owner_email));
                            }
                        } else {
                            echo esc_html__('Manual send destination is not configured. Set Event Owner Email on the event.', 'schedule-checkin');
                        }
                        ?>
                    </p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_send_assignment_report_now'); ?>
                    <input type="hidden" name="action" value="sc_send_assignment_report_now" />
                    <input type="hidden" name="event_id" value="<?php echo (int) $assignment_event_id; ?>" />
                    <button class="button" type="submit" <?php disabled($assignment_event_id <= 0); ?>><?php esc_html_e('Send Assignment Report Now', 'schedule-checkin'); ?></button>
                </form>
                <?php if ($assignment_event_id) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                        <?php wp_nonce_field('sc_export_assignment_report_csv'); ?>
                        <input type="hidden" name="action" value="sc_export_assignment_report_csv" />
                        <input type="hidden" name="assignment_event_id" value="<?php echo (int) $assignment_event_id; ?>" />
                        <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                        <?php wp_nonce_field('sc_export_assignment_report_excel'); ?>
                        <input type="hidden" name="action" value="sc_export_assignment_report_excel" />
                        <input type="hidden" name="assignment_event_id" value="<?php echo (int) $assignment_event_id; ?>" />
                        <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                        <?php wp_nonce_field('sc_export_assignment_report_pdf'); ?>
                        <input type="hidden" name="action" value="sc_export_assignment_report_pdf" />
                        <input type="hidden" name="assignment_event_id" value="<?php echo (int) $assignment_event_id; ?>" />
                        <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                    </form>
                <?php endif; ?>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Task', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Start', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('End', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Slot', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Status', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Volunteer', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Email', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Phone', 'schedule-checkin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$assignment_event_id) : ?>
                            <tr><td colspan="8"><?php esc_html_e('Select an event to run this report.', 'schedule-checkin'); ?></td></tr>
                        <?php elseif (!$assignment_rows) : ?>
                            <tr><td colspan="8"><?php esc_html_e('No task slots found for this event.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($assignment_rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row['task_title'] ?? ''); ?></td>
                                <td><?php echo esc_html(self::format_datetime_cst_12h($row['task_start_datetime'] ?? '')); ?></td>
                                <td><?php echo esc_html(self::format_datetime_cst_12h($row['task_end_datetime'] ?? '')); ?></td>
                                <td><?php echo (int) ($row['slot_number'] ?? 0); ?></td>
                                <td><?php echo esc_html($row['status'] ?? ''); ?></td>
                                <td><?php echo esc_html($row['volunteer_name'] ?? ''); ?></td>
                                <td><?php echo esc_html($row['volunteer_email'] ?? ''); ?></td>
                                <td><?php echo esc_html(self::format_phone_for_display($row['volunteer_phone'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="volunteer-period">
                <h2><?php esc_html_e('Volunteer Time Period Report', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('All times shown in CST.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="volunteer-period" />
                    <label>
                        <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                        <select name="event_category">
                            <option value=""><?php esc_html_e('All Categories', 'schedule-checkin'); ?></option>
                            <?php foreach ($event_categories as $event_category_option) : ?>
                                <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($report_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Volunteer', 'schedule-checkin'); ?></span>
                        <select name="volunteer_id">
                            <option value="0"><?php esc_html_e('Select Volunteer', 'schedule-checkin'); ?></option>
                            <?php foreach ($volunteers as $volunteer) : ?>
                                <option value="<?php echo (int) $volunteer->id; ?>" <?php selected($volunteer_id, $volunteer->id); ?>><?php echo esc_html($volunteer->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Period', 'schedule-checkin'); ?></span>
                        <select name="period">
                            <?php foreach (['day', 'week', 'month', 'year', 'all'] as $p) : ?>
                                <option value="<?php echo esc_attr($p); ?>" <?php selected($period, $p); ?>><?php echo esc_html(ucfirst($p)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>
                <?php if ($volunteer_id) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                        <?php wp_nonce_field('sc_export_volunteer_hours_csv'); ?>
                        <input type="hidden" name="action" value="sc_export_volunteer_hours_csv" />
                        <input type="hidden" name="volunteer_id" value="<?php echo (int) $volunteer_id; ?>" />
                        <input type="hidden" name="period" value="<?php echo esc_attr($period); ?>" />
                        <button class="button" type="submit"><?php esc_html_e('Export Volunteer CSV', 'schedule-checkin'); ?></button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                        <?php wp_nonce_field('sc_export_volunteer_hours_excel'); ?>
                        <input type="hidden" name="action" value="sc_export_volunteer_hours_excel" />
                        <input type="hidden" name="volunteer_id" value="<?php echo (int) $volunteer_id; ?>" />
                        <input type="hidden" name="period" value="<?php echo esc_attr($period); ?>" />
                        <button class="button" type="submit"><?php esc_html_e('Export Volunteer Excel', 'schedule-checkin'); ?></button>
                    </form>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                        <?php wp_nonce_field('sc_export_volunteer_hours_pdf'); ?>
                        <input type="hidden" name="action" value="sc_export_volunteer_hours_pdf" />
                        <input type="hidden" name="volunteer_id" value="<?php echo (int) $volunteer_id; ?>" />
                        <input type="hidden" name="period" value="<?php echo esc_attr($period); ?>" />
                        <button class="button" type="submit"><?php esc_html_e('Export Volunteer PDF', 'schedule-checkin'); ?></button>
                    </form>
                <?php endif; ?>

                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Event', 'schedule-checkin'); ?></th><th><?php esc_html_e('Event Category', 'schedule-checkin'); ?></th><th><?php esc_html_e('Check In', 'schedule-checkin'); ?></th><th><?php esc_html_e('Check Out', 'schedule-checkin'); ?></th><th><?php esc_html_e('Hours', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (!$volunteer_hours) : ?>
                            <tr><td colspan="5"><?php esc_html_e('No records for this selection.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($volunteer_hours as $row) : ?>
                            <tr><td><?php echo esc_html($row['title'] ?? ''); ?></td><td><?php echo self::render_event_category_badge((string) ($row['event_category'] ?? '')); ?></td><td><?php echo esc_html(self::format_datetime_cst_12h($row['checked_in_at'] ?? '')); ?></td><td><?php echo esc_html(self::format_datetime_cst_12h($row['checked_out_at'] ?? '')); ?></td><td><?php echo esc_html(number_format((float) ($row['hours_worked'] ?? 0), 2)); ?></td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="volunteers">
                <h2><?php esc_html_e('Volunteers Report', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Shows volunteer profile fields, usage data, and volunteered hours by event category with grand totals.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="volunteers" />
                    <div class="sc-grid-3">
                        <label>
                            <span><?php esc_html_e('Event (multi-select)', 'schedule-checkin'); ?></span>
                            <select name="volunteers_event_ids[]" multiple size="6">
                                <?php foreach ($all_events as $event) : ?>
                                    <option value="<?php echo (int) $event->id; ?>" <?php selected(in_array((int) $event->id, $volunteer_report_event_ids, true)); ?>><?php echo esc_html($event->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small><?php esc_html_e('Leave empty for All Events.', 'schedule-checkin'); ?></small>
                        </label>
                        <label>
                            <span><?php esc_html_e('Event Category (multi-select)', 'schedule-checkin'); ?></span>
                            <select name="volunteers_event_categories[]" multiple size="6">
                                <?php foreach ($event_categories as $event_category_option) : ?>
                                    <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected(in_array($event_category_option, $volunteer_report_event_categories, true)); ?>><?php echo esc_html($event_category_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small><?php esc_html_e('Leave empty for All Categories.', 'schedule-checkin'); ?></small>
                        </label>
                        <label>
                            <span><?php esc_html_e('Volunteer Name', 'schedule-checkin'); ?></span>
                            <select name="volunteers_volunteer_id">
                                <option value="0"><?php esc_html_e('All Volunteers', 'schedule-checkin'); ?></option>
                                <?php foreach ($volunteers as $volunteer_option) : ?>
                                    <option value="<?php echo (int) $volunteer_option->id; ?>" <?php selected($volunteer_report_volunteer_id, $volunteer_option->id); ?>><?php echo esc_html($volunteer_option->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Volunteer Is Active', 'schedule-checkin'); ?></span>
                            <select name="volunteers_is_active">
                                <option value="all" <?php selected($volunteer_report_is_active, 'all'); ?>><?php esc_html_e('All', 'schedule-checkin'); ?></option>
                                <option value="yes" <?php selected($volunteer_report_is_active, 'yes'); ?>><?php esc_html_e('Yes', 'schedule-checkin'); ?></option>
                                <option value="no" <?php selected($volunteer_report_is_active, 'no'); ?>><?php esc_html_e('No', 'schedule-checkin'); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Period', 'schedule-checkin'); ?></span>
                            <select name="volunteers_period">
                                <option value="week" <?php selected($volunteer_report_period, 'week'); ?>><?php esc_html_e('This Week', 'schedule-checkin'); ?></option>
                                <option value="month" <?php selected($volunteer_report_period, 'month'); ?>><?php esc_html_e('This Month', 'schedule-checkin'); ?></option>
                                <option value="year" <?php selected($volunteer_report_period, 'year'); ?>><?php esc_html_e('This Year', 'schedule-checkin'); ?></option>
                                <option value="date-range" <?php selected($volunteer_report_period, 'date-range'); ?>><?php esc_html_e('Date Range', 'schedule-checkin'); ?></option>
                                <option value="all-time" <?php selected($volunteer_report_period, 'all-time'); ?>><?php esc_html_e('All Time', 'schedule-checkin'); ?></option>
                            </select>
                        </label>
                        <label><span><?php esc_html_e('Date From', 'schedule-checkin'); ?></span><input type="date" name="volunteers_start_date" value="<?php echo esc_attr($volunteer_report_start_date); ?>" /></label>
                        <label><span><?php esc_html_e('Date To', 'schedule-checkin'); ?></span><input type="date" name="volunteers_end_date" value="<?php echo esc_attr($volunteer_report_end_date); ?>" /></label>
                    </div>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_csv'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_csv" />
                    <input type="hidden" name="report_key" value="volunteers" />
                    <?php foreach ($volunteer_report_event_ids as $selected_event_id) : ?>
                        <input type="hidden" name="volunteers_event_ids[]" value="<?php echo (int) $selected_event_id; ?>" />
                    <?php endforeach; ?>
                    <?php foreach ($volunteer_report_event_categories as $selected_category) : ?>
                        <input type="hidden" name="volunteers_event_categories[]" value="<?php echo esc_attr($selected_category); ?>" />
                    <?php endforeach; ?>
                    <input type="hidden" name="volunteers_volunteer_id" value="<?php echo (int) $volunteer_report_volunteer_id; ?>" />
                    <input type="hidden" name="volunteers_is_active" value="<?php echo esc_attr($volunteer_report_is_active); ?>" />
                    <input type="hidden" name="volunteers_period" value="<?php echo esc_attr($volunteer_report_period); ?>" />
                    <input type="hidden" name="volunteers_start_date" value="<?php echo esc_attr($volunteer_report_start_date); ?>" />
                    <input type="hidden" name="volunteers_end_date" value="<?php echo esc_attr($volunteer_report_end_date); ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_excel'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_excel" />
                    <input type="hidden" name="report_key" value="volunteers" />
                    <?php foreach ($volunteer_report_event_ids as $selected_event_id) : ?>
                        <input type="hidden" name="volunteers_event_ids[]" value="<?php echo (int) $selected_event_id; ?>" />
                    <?php endforeach; ?>
                    <?php foreach ($volunteer_report_event_categories as $selected_category) : ?>
                        <input type="hidden" name="volunteers_event_categories[]" value="<?php echo esc_attr($selected_category); ?>" />
                    <?php endforeach; ?>
                    <input type="hidden" name="volunteers_volunteer_id" value="<?php echo (int) $volunteer_report_volunteer_id; ?>" />
                    <input type="hidden" name="volunteers_is_active" value="<?php echo esc_attr($volunteer_report_is_active); ?>" />
                    <input type="hidden" name="volunteers_period" value="<?php echo esc_attr($volunteer_report_period); ?>" />
                    <input type="hidden" name="volunteers_start_date" value="<?php echo esc_attr($volunteer_report_start_date); ?>" />
                    <input type="hidden" name="volunteers_end_date" value="<?php echo esc_attr($volunteer_report_end_date); ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                    <?php wp_nonce_field('sc_export_advanced_report_pdf'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_pdf" />
                    <input type="hidden" name="report_key" value="volunteers" />
                    <?php foreach ($volunteer_report_event_ids as $selected_event_id) : ?>
                        <input type="hidden" name="volunteers_event_ids[]" value="<?php echo (int) $selected_event_id; ?>" />
                    <?php endforeach; ?>
                    <?php foreach ($volunteer_report_event_categories as $selected_category) : ?>
                        <input type="hidden" name="volunteers_event_categories[]" value="<?php echo esc_attr($selected_category); ?>" />
                    <?php endforeach; ?>
                    <input type="hidden" name="volunteers_volunteer_id" value="<?php echo (int) $volunteer_report_volunteer_id; ?>" />
                    <input type="hidden" name="volunteers_is_active" value="<?php echo esc_attr($volunteer_report_is_active); ?>" />
                    <input type="hidden" name="volunteers_period" value="<?php echo esc_attr($volunteer_report_period); ?>" />
                    <input type="hidden" name="volunteers_start_date" value="<?php echo esc_attr($volunteer_report_start_date); ?>" />
                    <input type="hidden" name="volunteers_end_date" value="<?php echo esc_attr($volunteer_report_end_date); ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                </form>

                <div class="sc-stats-grid">
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Total Assignments', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) ($volunteers_report_usage_totals['assignments'] ?? 0); ?></div></div>
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Total Check-Ins', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) ($volunteers_report_usage_totals['checkins'] ?? 0); ?></div></div>
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Total Messages', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) ($volunteers_report_usage_totals['messages'] ?? 0); ?></div></div>
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Grand Total Hours', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo esc_html(number_format((float) $volunteers_report_grand_total_hours, 2)); ?></div></div>
                </div>

                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th colspan="5"><?php esc_html_e('Volunteer Profile', 'schedule-checkin'); ?></th>
                            <th colspan="3"><?php esc_html_e('Usage Data', 'schedule-checkin'); ?></th>
                            <th colspan="<?php echo (int) (count($volunteers_report_category_columns) + 1); ?>"><?php esc_html_e('Volunteered Hours', 'schedule-checkin'); ?></th>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Name', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Email', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Phone', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Preferred', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Status', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Assignments', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Check-Ins', 'schedule-checkin'); ?></th>
                            <th><?php esc_html_e('Messages', 'schedule-checkin'); ?></th>
                            <?php foreach ($volunteers_report_category_columns as $hours_category_label) : ?>
                                <th><?php echo esc_html($hours_category_label); ?></th>
                            <?php endforeach; ?>
                            <th><?php esc_html_e('Total Hours', 'schedule-checkin'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($volunteers_report_rows)) : ?>
                            <tr><td colspan="<?php echo (int) (9 + count($volunteers_report_category_columns)); ?>"><?php esc_html_e('No volunteer records found for this selection.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($volunteers_report_rows as $volunteer_row) : ?>
                            <tr>
                                <td><?php echo esc_html((string) ($volunteer_row['name'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($volunteer_row['email'] ?? '')); ?></td>
                                <td><?php echo esc_html(self::format_phone_for_display((string) ($volunteer_row['phone'] ?? ''))); ?></td>
                                <td><?php echo esc_html(strtoupper((string) ($volunteer_row['preferred_contact_method'] ?? 'email'))); ?></td>
                                <td><?php echo esc_html((string) ($volunteer_row['status_label'] ?? '')); ?></td>
                                <td><?php echo (int) ($volunteer_row['assignment_count'] ?? 0); ?></td>
                                <td><?php echo (int) ($volunteer_row['checkin_count'] ?? 0); ?></td>
                                <td><?php echo (int) ($volunteer_row['comm_count'] ?? 0); ?></td>
                                <?php foreach ($volunteers_report_category_columns as $hours_category_label) : ?>
                                    <td><?php echo esc_html(number_format((float) (($volunteer_row['hours_by_category'][$hours_category_label] ?? 0)), 2)); ?></td>
                                <?php endforeach; ?>
                                <td><?php echo esc_html(number_format((float) ($volunteer_row['total_hours'] ?? 0), 2)); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <h3><?php esc_html_e('Grand Totals', 'schedule-checkin'); ?></h3>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Metric', 'schedule-checkin'); ?></th><th><?php esc_html_e('Total', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <tr><td><?php esc_html_e('Assignments', 'schedule-checkin'); ?></td><td><?php echo (int) ($volunteers_report_usage_totals['assignments'] ?? 0); ?></td></tr>
                        <tr><td><?php esc_html_e('Check-Ins', 'schedule-checkin'); ?></td><td><?php echo (int) ($volunteers_report_usage_totals['checkins'] ?? 0); ?></td></tr>
                        <tr><td><?php esc_html_e('Messages', 'schedule-checkin'); ?></td><td><?php echo (int) ($volunteers_report_usage_totals['messages'] ?? 0); ?></td></tr>
                        <?php foreach ($volunteers_report_category_columns as $hours_category_label) : ?>
                            <tr><td><?php echo esc_html(sprintf(__('Hours - %s', 'schedule-checkin'), $hours_category_label)); ?></td><td><?php echo esc_html(number_format((float) ($volunteers_report_category_totals[$hours_category_label] ?? 0), 2)); ?></td></tr>
                        <?php endforeach; ?>
                        <tr><td><strong><?php esc_html_e('Grand Total Volunteered Hours', 'schedule-checkin'); ?></strong></td><td><strong><?php echo esc_html(number_format((float) $volunteers_report_grand_total_hours, 2)); ?></strong></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="lifetime-hours">
                <h2><?php esc_html_e('Volunteer Lifetime Hours', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('All times shown in CST.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="lifetime-hours" />
                    <label>
                        <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                        <select name="event_category">
                            <option value=""><?php esc_html_e('All Categories', 'schedule-checkin'); ?></option>
                            <?php foreach ($event_categories as $event_category_option) : ?>
                                <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($report_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_csv'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_csv" />
                    <input type="hidden" name="report_key" value="lifetime_hours" />
                    <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_excel'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_excel" />
                    <input type="hidden" name="report_key" value="lifetime_hours" />
                    <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                    <?php wp_nonce_field('sc_export_advanced_report_pdf'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_pdf" />
                    <input type="hidden" name="report_key" value="lifetime_hours" />
                    <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                </form>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Volunteer', 'schedule-checkin'); ?></th><th><?php esc_html_e('Email', 'schedule-checkin'); ?></th><th><?php esc_html_e('Lifetime Hours', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (!$lifetime_hours_rows) : ?>
                            <tr><td colspan="3"><?php esc_html_e('No data available.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($lifetime_hours_rows as $row) : ?>
                            <tr><td><?php echo esc_html($row->name); ?></td><td><?php echo esc_html($row->email); ?></td><td><?php echo esc_html(number_format((float) $row->hours_worked, 2)); ?></td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="attendance">
                <h2><?php esc_html_e('Attendance Reliability', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('All times shown in CST.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="attendance" />
                    <label>
                        <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                        <select name="event_category">
                            <option value=""><?php esc_html_e('All Categories', 'schedule-checkin'); ?></option>
                            <?php foreach ($event_categories as $event_category_option) : ?>
                                <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($report_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_csv'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_csv" />
                    <input type="hidden" name="report_key" value="attendance_reliability" />
                    <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_excel'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_excel" />
                    <input type="hidden" name="report_key" value="attendance_reliability" />
                    <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                    <?php wp_nonce_field('sc_export_advanced_report_pdf'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_pdf" />
                    <input type="hidden" name="report_key" value="attendance_reliability" />
                    <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                </form>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Volunteer', 'schedule-checkin'); ?></th><th><?php esc_html_e('Scheduled', 'schedule-checkin'); ?></th><th><?php esc_html_e('Checked In', 'schedule-checkin'); ?></th><th><?php esc_html_e('No Show', 'schedule-checkin'); ?></th><th><?php esc_html_e('Attendance %', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (!$attendance_reliability_rows) : ?>
                            <tr><td colspan="5"><?php esc_html_e('No data available.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($attendance_reliability_rows as $row) :
                            $scheduled = max(1, (int) $row->scheduled_count);
                            $pct = ((int) $row->checked_in_count / $scheduled) * 100;
                            ?>
                            <tr><td><?php echo esc_html($row->name); ?></td><td><?php echo (int) $row->scheduled_count; ?></td><td><?php echo (int) $row->checked_in_count; ?></td><td><?php echo (int) $row->no_show_count; ?></td><td><?php echo esc_html(number_format($pct, 1)); ?>%</td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="late-early">
                <h2><?php esc_html_e('Late / Early Analysis', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('All times shown in CST.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="late-early" />
                    <label>
                        <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                        <select name="event_category">
                            <option value=""><?php esc_html_e('All Categories', 'schedule-checkin'); ?></option>
                            <?php foreach ($event_categories as $event_category_option) : ?>
                                <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($report_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_csv'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_csv" />
                    <input type="hidden" name="report_key" value="late_early_analysis" />
                    <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_excel'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_excel" />
                    <input type="hidden" name="report_key" value="late_early_analysis" />
                    <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                    <?php wp_nonce_field('sc_export_advanced_report_pdf'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_pdf" />
                    <input type="hidden" name="report_key" value="late_early_analysis" />
                    <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                </form>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Volunteer', 'schedule-checkin'); ?></th><th><?php esc_html_e('Avg Late (min)', 'schedule-checkin'); ?></th><th><?php esc_html_e('Avg Early Leave (min)', 'schedule-checkin'); ?></th><th><?php esc_html_e('Late Arrivals', 'schedule-checkin'); ?></th><th><?php esc_html_e('Early Leaves', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (!$late_early_rows) : ?>
                            <tr><td colspan="5"><?php esc_html_e('No data available.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($late_early_rows as $row) : ?>
                            <tr><td><?php echo esc_html($row->name); ?></td><td><?php echo esc_html(number_format((float) $row->avg_late_min, 1)); ?></td><td><?php echo esc_html(number_format((float) $row->avg_early_leave_min, 1)); ?></td><td><?php echo (int) $row->late_arrivals; ?></td><td><?php echo (int) $row->early_leaves; ?></td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="task-coverage">
                <h2><?php esc_html_e('Task Coverage Health', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('All times shown in CST.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="task-coverage" />
                    <label>
                        <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                        <select name="event_category">
                            <option value=""><?php esc_html_e('All Categories', 'schedule-checkin'); ?></option>
                            <?php foreach ($event_categories as $event_category_option) : ?>
                                <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($report_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Event', 'schedule-checkin'); ?></span>
                        <select name="coverage_event_id">
                            <option value="0"><?php esc_html_e('All Events', 'schedule-checkin'); ?></option>
                            <?php foreach ($events as $event) : ?>
                                <option value="<?php echo (int) $event->id; ?>" <?php selected($coverage_event_id, $event->id); ?>><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_csv'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_csv" />
                    <input type="hidden" name="report_key" value="task_coverage_health" />
                    <input type="hidden" name="coverage_event_id" value="<?php echo (int) $coverage_event_id; ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_excel'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_excel" />
                    <input type="hidden" name="report_key" value="task_coverage_health" />
                    <input type="hidden" name="coverage_event_id" value="<?php echo (int) $coverage_event_id; ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                    <?php wp_nonce_field('sc_export_advanced_report_pdf'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_pdf" />
                    <input type="hidden" name="report_key" value="task_coverage_health" />
                    <input type="hidden" name="coverage_event_id" value="<?php echo (int) $coverage_event_id; ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                </form>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Event', 'schedule-checkin'); ?></th><th><?php esc_html_e('Event Category', 'schedule-checkin'); ?></th><th><?php esc_html_e('Task', 'schedule-checkin'); ?></th><th><?php esc_html_e('Start', 'schedule-checkin'); ?></th><th><?php esc_html_e('Filled / Slots', 'schedule-checkin'); ?></th><th><?php esc_html_e('Fill %', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (!$task_coverage_rows) : ?>
                            <tr><td colspan="6"><?php esc_html_e('No data available.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($task_coverage_rows as $row) : ?>
                            <tr><td><?php echo esc_html($row->event_title); ?></td><td><?php echo self::render_event_category_badge((string) ($row->event_category ?? '')); ?></td><td><?php echo esc_html($row->task_title); ?></td><td><?php echo esc_html(self::format_datetime_cst_12h($row->start_datetime)); ?></td><td><?php echo (int) $row->filled_slots; ?> / <?php echo (int) $row->slots; ?></td><td><?php echo esc_html(number_format((float) $row->fill_rate, 1)); ?>%</td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="substitute-pool">
                <h2><?php esc_html_e('Substitute Pool', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('All times shown in CST.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="substitute-pool" />
                    <label>
                        <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                        <select name="event_category">
                            <option value=""><?php esc_html_e('All Categories', 'schedule-checkin'); ?></option>
                            <?php foreach ($event_categories as $event_category_option) : ?>
                                <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($report_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Event', 'schedule-checkin'); ?></span>
                        <select name="substitute_event_id">
                            <option value="0"><?php esc_html_e('All Events', 'schedule-checkin'); ?></option>
                            <?php foreach ($events as $event) : ?>
                                <option value="<?php echo (int) $event->id; ?>" <?php selected($substitute_event_id, $event->id); ?>><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>

                <p>
                    <?php if ($substitute_event_id) : ?>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-assignments&event_id=' . (int) $substitute_event_id)); ?>"><?php esc_html_e('Open Assignments for Selected Event', 'schedule-checkin'); ?></a>
                    <?php else : ?>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-assignments')); ?>"><?php esc_html_e('Open Assignments', 'schedule-checkin'); ?></a>
                    <?php endif; ?>
                </p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_csv'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_csv" />
                    <input type="hidden" name="report_key" value="substitute_pool" />
                    <input type="hidden" name="substitute_event_id" value="<?php echo (int) $substitute_event_id; ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_excel'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_excel" />
                    <input type="hidden" name="report_key" value="substitute_pool" />
                    <input type="hidden" name="substitute_event_id" value="<?php echo (int) $substitute_event_id; ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                    <?php wp_nonce_field('sc_export_advanced_report_pdf'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_pdf" />
                    <input type="hidden" name="report_key" value="substitute_pool" />
                    <input type="hidden" name="substitute_event_id" value="<?php echo (int) $substitute_event_id; ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                </form>

                <div class="sc-stats-grid">
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Opted In For Future', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) $substitute_opt_in_count; ?></div></div>
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Currently Available', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) $substitute_available_count; ?></div></div>
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Assigned To Slots', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) $substitute_assigned_count; ?></div></div>
                </div>

                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Name', 'schedule-checkin'); ?></th><th><?php esc_html_e('Email', 'schedule-checkin'); ?></th><th><?php esc_html_e('Phone', 'schedule-checkin'); ?></th><th><?php esc_html_e('Future Opt-In', 'schedule-checkin'); ?></th><th><?php esc_html_e('Substitute Signups', 'schedule-checkin'); ?></th><th><?php esc_html_e('Available', 'schedule-checkin'); ?></th><th><?php esc_html_e('Assigned', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (!$substitute_pool_rows) : ?>
                            <tr><td colspan="7"><?php esc_html_e('No substitute pool data available.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($substitute_pool_rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->name); ?></td>
                                <td><?php echo esc_html($row->email ?: __('No email', 'schedule-checkin')); ?></td>
                                <td><?php echo esc_html(self::format_phone_for_display($row->phone)); ?></td>
                                <td><?php echo (int) ($row->future_substitute_opt_in ?? 0) === 1 ? esc_html__('Yes', 'schedule-checkin') : esc_html__('No', 'schedule-checkin'); ?></td>
                                <td><?php echo (int) ($row->substitute_signup_count ?? 0); ?></td>
                                <td><?php echo (int) ($row->available_pool_count ?? 0); ?></td>
                                <td><?php echo (int) ($row->assigned_slot_count ?? 0); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="substitute-history">
                <h2><?php esc_html_e('Substitute History', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Shows who signed up as a substitute for each event, whether they opted in for future substitute requests, and whether they have ever been assigned to a substitute slot.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="substitute-history" />
                    <label>
                        <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                        <select name="event_category">
                            <option value=""><?php esc_html_e('All Categories', 'schedule-checkin'); ?></option>
                            <?php foreach ($event_categories as $event_category_option) : ?>
                                <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($report_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Event', 'schedule-checkin'); ?></span>
                        <select name="substitute_history_event_id">
                            <option value="0"><?php esc_html_e('All Events', 'schedule-checkin'); ?></option>
                            <?php foreach ($events as $event) : ?>
                                <option value="<?php echo (int) $event->id; ?>" <?php selected($substitute_history_event_id, $event->id); ?>><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_csv'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_csv" />
                    <input type="hidden" name="report_key" value="substitute_history" />
                    <input type="hidden" name="substitute_history_event_id" value="<?php echo (int) $substitute_history_event_id; ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_excel'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_excel" />
                    <input type="hidden" name="report_key" value="substitute_history" />
                    <input type="hidden" name="substitute_history_event_id" value="<?php echo (int) $substitute_history_event_id; ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                    <?php wp_nonce_field('sc_export_advanced_report_pdf'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_pdf" />
                    <input type="hidden" name="report_key" value="substitute_history" />
                    <input type="hidden" name="substitute_history_event_id" value="<?php echo (int) $substitute_history_event_id; ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                </form>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Volunteer', 'schedule-checkin'); ?></th><th><?php esc_html_e('Email', 'schedule-checkin'); ?></th><th><?php esc_html_e('Event', 'schedule-checkin'); ?></th><th><?php esc_html_e('Event Category', 'schedule-checkin'); ?></th><th><?php esc_html_e('Future Opt-In', 'schedule-checkin'); ?></th><th><?php esc_html_e('Ever Assigned', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (!$substitute_history_rows) : ?>
                            <tr><td colspan="6"><?php esc_html_e('No substitute history data available.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($substitute_history_rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row->name); ?></td>
                                <td><?php echo esc_html($row->email ?: __('No email', 'schedule-checkin')); ?></td>
                                <td><?php echo esc_html($row->event_title); ?></td>
                                <td><?php echo self::render_event_category_badge((string) ($row->event_category ?? '')); ?></td>
                                <td><?php echo (int) ($row->future_substitute_opt_in ?? 0) === 1 ? esc_html__('Yes', 'schedule-checkin') : esc_html__('No', 'schedule-checkin'); ?></td>
                                <td><?php echo (int) ($row->ever_assigned_any_event ?? 0) === 1 ? esc_html__('Yes', 'schedule-checkin') : esc_html__('No', 'schedule-checkin'); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="checkin-audit">
                <h2><?php esc_html_e('Check-In Method Audit', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('All times shown in CST.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="checkin-audit" />
                    <label>
                        <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                        <select name="event_category">
                            <option value=""><?php esc_html_e('All Categories', 'schedule-checkin'); ?></option>
                            <?php foreach ($event_categories as $event_category_option) : ?>
                                <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($report_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_csv'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_csv" />
                    <input type="hidden" name="report_key" value="checkin_method_audit" />
                    <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_excel'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_excel" />
                    <input type="hidden" name="report_key" value="checkin_method_audit" />
                    <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                    <?php wp_nonce_field('sc_export_advanced_report_pdf'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_pdf" />
                    <input type="hidden" name="report_key" value="checkin_method_audit" />
                    <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                </form>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Source', 'schedule-checkin'); ?></th><th><?php esc_html_e('Check In', 'schedule-checkin'); ?></th><th><?php esc_html_e('Check Out', 'schedule-checkin'); ?></th><th><?php esc_html_e('Total Actions', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (!$checkin_audit_rows) : ?>
                            <tr><td colspan="4"><?php esc_html_e('No data available.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($checkin_audit_rows as $row) : ?>
                            <tr><td><?php echo esc_html($row->source); ?></td><td><?php echo (int) $row->checkin_count; ?></td><td><?php echo (int) $row->checkout_count; ?></td><td><?php echo (int) $row->total_actions; ?></td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="sms-analytics">
                <h2><?php esc_html_e('SMS Analytics', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Sent SMS by time period with character counts, segment totals, average length, and estimated cost.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="sms-analytics" />
                    <div class="sc-grid-3">
                        <label>
                            <span><?php esc_html_e('Time Period', 'schedule-checkin'); ?></span>
                            <select name="sms_period">
                                <?php foreach (['month' => __('Month', 'schedule-checkin'), 'year' => __('Year', 'schedule-checkin'), 'date-range' => __('Date Range', 'schedule-checkin'), 'all' => __('All Time', 'schedule-checkin')] as $sms_p => $sms_p_label) : ?>
                                    <option value="<?php echo esc_attr($sms_p); ?>" <?php selected($sms_period, $sms_p); ?>><?php echo esc_html($sms_p_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><span><?php esc_html_e('Date From', 'schedule-checkin'); ?></span><input type="date" name="sms_start_date" value="<?php echo esc_attr($sms_start_date); ?>" /></label>
                        <label><span><?php esc_html_e('Date To', 'schedule-checkin'); ?></span><input type="date" name="sms_end_date" value="<?php echo esc_attr($sms_end_date); ?>" /></label>
                        <label>
                            <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                            <select name="event_category">
                                <option value=""><?php esc_html_e('All Categories', 'schedule-checkin'); ?></option>
                                <?php foreach ($event_categories as $event_category_option) : ?>
                                    <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($report_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_csv'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_csv" />
                    <input type="hidden" name="report_key" value="sms_analytics" />
                    <input type="hidden" name="sms_period" value="<?php echo esc_attr($sms_period); ?>" />
                    <input type="hidden" name="sms_start_date" value="<?php echo esc_attr($sms_start_date); ?>" />
                    <input type="hidden" name="sms_end_date" value="<?php echo esc_attr($sms_end_date); ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_excel'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_excel" />
                    <input type="hidden" name="report_key" value="sms_analytics" />
                    <input type="hidden" name="sms_period" value="<?php echo esc_attr($sms_period); ?>" />
                    <input type="hidden" name="sms_start_date" value="<?php echo esc_attr($sms_start_date); ?>" />
                    <input type="hidden" name="sms_end_date" value="<?php echo esc_attr($sms_end_date); ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                    <?php wp_nonce_field('sc_export_advanced_report_pdf'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_pdf" />
                    <input type="hidden" name="report_key" value="sms_analytics" />
                    <input type="hidden" name="sms_period" value="<?php echo esc_attr($sms_period); ?>" />
                    <input type="hidden" name="sms_start_date" value="<?php echo esc_attr($sms_start_date); ?>" />
                    <input type="hidden" name="sms_end_date" value="<?php echo esc_attr($sms_end_date); ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                </form>

                <div class="sc-stats-grid">
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Total SMS Sent', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) ($sms_summary['total_sms'] ?? 0); ?></div></div>
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Total Characters', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) ($sms_summary['total_chars'] ?? 0); ?></div></div>
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Avg Characters / SMS', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo esc_html(number_format((float) ($sms_summary['avg_chars'] ?? 0), 1)); ?></div></div>
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Total Segments', 'schedule-checkin'); ?></div><div class="sc-stat-value"><?php echo (int) ($sms_summary['total_segments'] ?? 0); ?></div></div>
                    <div class="sc-stat-card"><div class="sc-stat-label"><?php esc_html_e('Estimated Cost', 'schedule-checkin'); ?></div><div class="sc-stat-value">$<?php echo esc_html(number_format((float) ($sms_summary['total_cost'] ?? 0), 4)); ?></div></div>
                </div>

                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('When', 'schedule-checkin'); ?></th><th><?php esc_html_e('Event', 'schedule-checkin'); ?></th><th><?php esc_html_e('Event Category', 'schedule-checkin'); ?></th><th><?php esc_html_e('Recipient', 'schedule-checkin'); ?></th><th><?php esc_html_e('Purpose', 'schedule-checkin'); ?></th><th><?php esc_html_e('Chars', 'schedule-checkin'); ?></th><th><?php esc_html_e('Encoding', 'schedule-checkin'); ?></th><th><?php esc_html_e('Segments', 'schedule-checkin'); ?></th><th><?php esc_html_e('Estimated Cost', 'schedule-checkin'); ?></th><th><?php esc_html_e('Message', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (!$sms_rows) : ?>
                            <tr><td colspan="10"><?php esc_html_e('No SMS records for this period.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($sms_rows as $sms_row) : ?>
                            <tr>
                                <td><?php echo esc_html(self::format_datetime_cst_12h($sms_row['created_at'] ?? '')); ?></td>
                                <td><?php echo esc_html($sms_row['event_title'] ?? ''); ?></td>
                                <td><?php echo self::render_event_category_badge((string) ($sms_row['event_category'] ?? '')); ?></td>
                                <td><?php echo esc_html($sms_row['recipient'] ?? ''); ?></td>
                                <td><?php echo esc_html($sms_row['purpose'] ?? ''); ?></td>
                                <td><?php echo (int) ($sms_row['char_count'] ?? 0); ?></td>
                                <td><?php echo esc_html($sms_row['encoding'] ?? ''); ?></td>
                                <td><?php echo (int) ($sms_row['segments'] ?? 0); ?></td>
                                <td>$<?php echo esc_html(number_format((float) ($sms_row['estimated_cost'] ?? 0), 4)); ?></td>
                                <td><?php echo esc_html(wp_trim_words((string) ($sms_row['message'] ?? ''), 20, '...')); ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card sc-report-card" data-report-tab="retention">
                <h2><?php esc_html_e('Retention & Activity (Monthly)', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('All times shown in CST.', 'schedule-checkin'); ?></p>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                    <input type="hidden" name="page" value="sc-reports" />
                    <input type="hidden" name="report_tab" value="retention" />
                    <label>
                        <span><?php esc_html_e('Event Category', 'schedule-checkin'); ?></span>
                        <select name="event_category">
                            <option value=""><?php esc_html_e('All Categories', 'schedule-checkin'); ?></option>
                            <?php foreach ($event_categories as $event_category_option) : ?>
                                <option value="<?php echo esc_attr($event_category_option); ?>" <?php selected($report_event_category, $event_category_option); ?>><?php echo esc_html($event_category_option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Run', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_csv'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_csv" />
                    <input type="hidden" name="report_key" value="retention_activity_monthly" />
                    <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_advanced_report_excel'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_excel" />
                    <input type="hidden" name="report_key" value="retention_activity_monthly" />
                    <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                    <?php wp_nonce_field('sc_export_advanced_report_pdf'); ?>
                    <input type="hidden" name="action" value="sc_export_advanced_report_pdf" />
                    <input type="hidden" name="report_key" value="retention_activity_monthly" />
                    <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                </form>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Month', 'schedule-checkin'); ?></th><th><?php esc_html_e('New Volunteers', 'schedule-checkin'); ?></th><th><?php esc_html_e('Returning Volunteers', 'schedule-checkin'); ?></th><th><?php esc_html_e('Active Volunteers', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (!$retention_rows) : ?>
                            <tr><td colspan="4"><?php esc_html_e('No data available.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($retention_rows as $row) : ?>
                            <tr><td><?php echo esc_html($row->month_key); ?></td><td><?php echo (int) $row->new_count; ?></td><td><?php echo (int) $row->returning_count; ?></td><td><?php echo (int) $row->active_volunteers; ?></td></tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($edit_volunteer) : ?>
                <div class="sc-card sc-report-card" data-report-tab="volunteer-period">
                    <h2><?php esc_html_e('Edit Volunteer', 'schedule-checkin'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('sc_update_volunteer'); ?>
                        <input type="hidden" name="action" value="sc_update_volunteer" />
                        <input type="hidden" name="volunteer_id" value="<?php echo (int) $edit_volunteer->id; ?>" />

                        <div class="sc-grid-3">
                            <label><span><?php esc_html_e('Name *', 'schedule-checkin'); ?></span><input type="text" name="name" value="<?php echo esc_attr($edit_volunteer->name); ?>" required /></label>
                            <label><span><?php esc_html_e('Email Address *', 'schedule-checkin'); ?></span><input type="email" name="email" value="<?php echo esc_attr($edit_volunteer->email); ?>" required /></label>
                            <label><span><?php esc_html_e('Phone Number *', 'schedule-checkin'); ?></span><input type="text" name="phone" value="<?php echo esc_attr(self::format_phone_for_input((string) $edit_volunteer->phone)); ?>" placeholder="123-456-7890" pattern="\d{3}-\d{3}-\d{4}" title="Phone number must be in the format 123-456-7890" required /></label>
                            <label><span><?php esc_html_e('Preferred Communication', 'schedule-checkin'); ?></span><select name="preferred_contact_method"><option value="email" <?php selected((string) ($edit_volunteer->preferred_contact_method ?? 'email'), 'email'); ?>><?php esc_html_e('Email', 'schedule-checkin'); ?></option><option value="sms" <?php selected((string) ($edit_volunteer->preferred_contact_method ?? 'email'), 'sms'); ?>><?php esc_html_e('SMS', 'schedule-checkin'); ?></option></select></label>
                        </div>
                        <p><button class="button button-primary" type="submit"><?php esc_html_e('Save Volunteer', 'schedule-checkin'); ?></button></p>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <script>
        (function () {
            var selectedCategory = <?php echo wp_json_encode($report_event_category); ?>;
            if (!selectedCategory) {
                return;
            }

            var forms = document.querySelectorAll('.sc-report-card form');
            for (var i = 0; i < forms.length; i++) {
                var form = forms[i];
                if (form.querySelector('[name="event_category"]')) {
                    continue;
                }

                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'event_category';
                hidden.value = selectedCategory;
                form.appendChild(hidden);
            }
        })();
        </script>
        <?php
    }

    public static function render_logs_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }

        global $wpdb;
        $t = self::tables();
        $event_id = isset($_GET['event_id']) ? absint($_GET['event_id']) : 0;
        $edit_log_id = isset($_GET['edit_log']) ? absint($_GET['edit_log']) : 0;

        if (isset($_GET['print_sheet']) && $event_id) {
            self::render_print_sheet($event_id);
            return;
        }

        $page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        if ($event_id) {
            $where = $wpdb->prepare('WHERE c.event_id = %d', $event_id);
        } else {
            $where = 'WHERE 1=1';
        }

        $logs = $wpdb->get_results(
            "SELECT c.*, v.name, a.assignment_type, t.title AS task_title FROM {$t['checkins']} c
             INNER JOIN {$t['volunteers']} v ON v.id = c.volunteer_id
             LEFT JOIN {$t['assignments']} a ON a.id = c.assignment_id
             LEFT JOIN {$t['tasks']} t ON t.id = c.task_id
             {$where}
             ORDER BY c.created_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t['checkins']} c {$where}");
        $total_pages = max(1, (int) ceil($total / $limit));
        $edit_log = null;
        if ($edit_log_id) {
            $edit_log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['checkins']} WHERE id = %d", $edit_log_id));
        }

        ?>
        <div class="wrap sc-admin-wrap">
            <?php self::render_page_intro(__('Check-In Logs', 'schedule-checkin'), __('Review, correct, and export event check-in history. All times shown in CST.', 'schedule-checkin')); ?>

            <?php if ($edit_log) : ?>
                <div class="sc-card">
                    <h2><?php esc_html_e('Edit Log Entry', 'schedule-checkin'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('sc_update_log'); ?>
                        <input type="hidden" name="action" value="sc_update_log" />
                        <input type="hidden" name="log_id" value="<?php echo (int) $edit_log->id; ?>" />
                        <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                        <div class="sc-grid-3">
                            <label><span><?php esc_html_e('Date/Time (CST)', 'schedule-checkin'); ?></span><input type="datetime-local" name="created_at" value="<?php echo esc_attr(self::for_input_datetime_cst($edit_log->created_at)); ?>" required /></label>
                            <label><span><?php esc_html_e('Action', 'schedule-checkin'); ?></span><select name="log_action"><option value="checkin" <?php selected($edit_log->action, 'checkin'); ?>><?php esc_html_e('Check In', 'schedule-checkin'); ?></option><option value="checkout" <?php selected($edit_log->action, 'checkout'); ?>><?php esc_html_e('Check Out', 'schedule-checkin'); ?></option></select></label>
                            <label><span><?php esc_html_e('Source', 'schedule-checkin'); ?></span><input type="text" name="source" value="<?php echo esc_attr($edit_log->source); ?>" /></label>
                        </div>
                        <label><span><?php esc_html_e('Notes', 'schedule-checkin'); ?></span><textarea name="notes" rows="2"><?php echo esc_textarea($edit_log->notes); ?></textarea></label>
                        <p><button class="button button-primary" type="submit"><?php esc_html_e('Save Log', 'schedule-checkin'); ?></button> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-logs' . ($event_id ? '&event_id=' . $event_id : ''))); ?>"><?php esc_html_e('Cancel', 'schedule-checkin'); ?></a></p>
                    </form>
                </div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                <?php wp_nonce_field('sc_export_logs_csv'); ?>
                <input type="hidden" name="action" value="sc_export_logs_csv" />
                <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                <button class="button" type="submit"><?php esc_html_e('Export Logs CSV', 'schedule-checkin'); ?></button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                <?php wp_nonce_field('sc_export_logs_excel'); ?>
                <input type="hidden" name="action" value="sc_export_logs_excel" />
                <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                <button class="button" type="submit"><?php esc_html_e('Export Logs Excel', 'schedule-checkin'); ?></button>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                <?php wp_nonce_field('sc_export_logs_pdf'); ?>
                <input type="hidden" name="action" value="sc_export_logs_pdf" />
                <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                <button class="button" type="submit"><?php esc_html_e('Export Logs PDF', 'schedule-checkin'); ?></button>
            </form>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date/Time', 'schedule-checkin'); ?></th>
                        <th><?php esc_html_e('Volunteer', 'schedule-checkin'); ?></th>
                        <th><?php esc_html_e('Type', 'schedule-checkin'); ?></th>
                        <th><?php esc_html_e('Task', 'schedule-checkin'); ?></th>
                        <th><?php esc_html_e('Action', 'schedule-checkin'); ?></th>
                        <th><?php esc_html_e('Source', 'schedule-checkin'); ?></th>
                        <th><?php esc_html_e('Notes', 'schedule-checkin'); ?></th>
                        <th><?php esc_html_e('Actions', 'schedule-checkin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$logs) : ?>
                        <tr><td colspan="8"><?php esc_html_e('No logs found.', 'schedule-checkin'); ?></td></tr>
                    <?php else : foreach ($logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html(self::format_datetime_cst_12h($log->created_at)); ?></td>
                            <td><?php echo esc_html($log->name); ?></td>
                            <td><?php echo esc_html(ucfirst((string) ($log->assignment_type ?: 'scheduled'))); ?></td>
                            <td><?php echo esc_html($log->task_title ?: __('No slot assignment', 'schedule-checkin')); ?></td>
                            <td><?php echo esc_html($log->action); ?></td>
                            <td><?php echo esc_html($log->source); ?></td>
                            <td><?php echo esc_html($log->notes); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-logs' . ($event_id ? '&event_id=' . $event_id : '') . '&edit_log=' . (int) $log->id)); ?>"><?php esc_html_e('Edit', 'schedule-checkin'); ?></a>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                                    <?php wp_nonce_field('sc_delete_log'); ?>
                                    <input type="hidden" name="action" value="sc_delete_log" />
                                    <input type="hidden" name="log_id" value="<?php echo (int) $log->id; ?>" />
                                    <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                                    <button type="submit" class="button button-link-delete" onclick="return confirm('<?php echo esc_js(__('Delete this log entry?', 'schedule-checkin')); ?>');"><?php esc_html_e('Delete', 'schedule-checkin'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>

            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $base = admin_url('admin.php?page=sc-logs' . ($event_id ? '&event_id=' . $event_id : ''));
                    for ($i = 1; $i <= $total_pages; $i++) {
                        $class = $i === $page ? 'button button-primary' : 'button';
                        echo '<a class="' . esc_attr($class) . '" href="' . esc_url($base . '&paged=' . $i) . '">' . (int) $i . '</a> ';
                    }
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }

        $twilio_account_sid = (string) self::get_plugin_setting('sc_twilio_account_sid', '', 'sc_twilio_sid');
        $twilio_api_key_sid = (string) self::get_plugin_setting('sc_twilio_api_key_sid', '');
        $twilio_api_key_secret = (string) self::get_plugin_setting('sc_twilio_api_key_secret', '');
        $twilio_auth_token = (string) self::get_plugin_setting('sc_twilio_auth_token', '', 'sc_twilio_token');
        $twilio_from = (string) self::get_plugin_setting('sc_twilio_from', '');
        $twilio_messaging_service_sid = (string) self::get_plugin_setting('sc_twilio_messaging_service_sid', '');
        $twilio_sms_cost_gsm_segment = (float) self::get_plugin_setting('sc_twilio_sms_cost_gsm_segment', '0');
        $twilio_sms_cost_unicode_segment = (float) self::get_plugin_setting('sc_twilio_sms_cost_unicode_segment', '0');
        $email_from_address = (string) self::get_plugin_setting('sc_email_from_address', '');
        $event_categories_raw = (string) self::get_plugin_setting('sc_event_categories', 'General');

        ?>
        <div class="wrap sc-admin-wrap">
            <?php self::render_page_intro(__('Settings', 'schedule-checkin'), __('Configure Twilio delivery and manage system-level plugin controls.', 'schedule-checkin'), true); ?>

            <div class="sc-card">
                <h2><?php esc_html_e('Twilio Settings', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Provide Twilio credentials for SMS delivery. Preferred auth is API Key SID + API Key Secret with Account SID.', 'schedule-checkin'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_save_comm_settings'); ?>
                    <input type="hidden" name="action" value="sc_save_comm_settings" />
                    <input type="hidden" name="redirect_page" value="sc-settings" />
                    <input type="hidden" name="settings_section" value="twilio" />

                    <div class="sc-grid-3">
                        <label><span><?php esc_html_e('Twilio Account SID', 'schedule-checkin'); ?></span><input type="text" name="twilio_account_sid" value="<?php echo esc_attr($twilio_account_sid); ?>" placeholder="AC..." /></label>
                        <label><span><?php esc_html_e('Twilio API Key SID', 'schedule-checkin'); ?></span><input type="text" name="twilio_api_key_sid" value="<?php echo esc_attr($twilio_api_key_sid); ?>" placeholder="SK..." /></label>
                        <label><span><?php esc_html_e('Twilio API Key Secret', 'schedule-checkin'); ?></span><input type="password" name="twilio_api_key_secret" value="<?php echo esc_attr($twilio_api_key_secret); ?>" /></label>
                        <label><span><?php esc_html_e('Twilio Auth Token (fallback)', 'schedule-checkin'); ?></span><input type="password" name="twilio_auth_token" value="<?php echo esc_attr($twilio_auth_token); ?>" /></label>
                        <label><span><?php esc_html_e('Twilio From Number', 'schedule-checkin'); ?></span><input type="text" name="twilio_from" value="<?php echo esc_attr($twilio_from); ?>" placeholder="+15551234567" /></label>
                        <label><span><?php esc_html_e('Twilio Messaging Service SID', 'schedule-checkin'); ?></span><input type="text" name="twilio_messaging_service_sid" value="<?php echo esc_attr($twilio_messaging_service_sid); ?>" placeholder="MG..." /></label>
                        <label><span><?php esc_html_e('SMS Cost / Segment (GSM-7)', 'schedule-checkin'); ?></span><input type="number" min="0" step="0.000001" name="twilio_sms_cost_gsm_segment" value="<?php echo esc_attr(number_format($twilio_sms_cost_gsm_segment, 6, '.', '')); ?>" /></label>
                        <label><span><?php esc_html_e('SMS Cost / Segment (Unicode)', 'schedule-checkin'); ?></span><input type="number" min="0" step="0.000001" name="twilio_sms_cost_unicode_segment" value="<?php echo esc_attr(number_format($twilio_sms_cost_unicode_segment, 6, '.', '')); ?>" /></label>
                        <label><span><?php esc_html_e('Plugin From Email', 'schedule-checkin'); ?></span><input type="email" name="email_from_address" value="<?php echo esc_attr($email_from_address); ?>" placeholder="noreply@example.com" /></label>
                    </div>
                    <p class="description"><?php esc_html_e('Use either Messaging Service SID or From Number. Messaging Service SID is recommended.', 'schedule-checkin'); ?></p>
                    <p class="description"><?php esc_html_e('Costs use Twilio segment rules: GSM-7 (160/153) and Unicode/UCS-2 (70/67). Enter your current Twilio per-segment rates for accurate estimates.', 'schedule-checkin'); ?></p>
                    <p class="description"><?php esc_html_e('Plugin From Email is used for all email sent by this plugin. Leave blank to use WordPress defaults.', 'schedule-checkin'); ?></p>
                    <div class="sc-card" style="margin:12px 0 0;padding:12px;">
                        <h3 style="margin-top:0;"><?php esc_html_e('Twilio Resources', 'schedule-checkin'); ?></h3>
                        <ul style="margin:0 0 0 18px;">
                            <li>
                                <a href="https://www.twilio.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Twilio Website', 'schedule-checkin'); ?></a>
                                — <?php esc_html_e('Official Twilio platform and account dashboard.', 'schedule-checkin'); ?>
                            </li>
                            <li>
                                <a href="https://www.twilio.com/en-us/sms/pricing/us" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Twilio Pricing', 'schedule-checkin'); ?></a>
                                — <?php esc_html_e('Current US SMS rates for pricing reference.', 'schedule-checkin'); ?>
                            </li>
                            <li>
                                <a href="https://www.twilio.com/docs/glossary/what-sms-character-limit?_ga=2.120963272.1302977634.1677506553-732055303.1670338818" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Twilio Messaging Guidelines', 'schedule-checkin'); ?></a>
                                — <?php esc_html_e('Character limits, encoding, and segment behavior details.', 'schedule-checkin'); ?>
                            </li>
                        </ul>
                    </div>
                    <p><button class="button button-primary" type="submit"><?php esc_html_e('Save Twilio Settings', 'schedule-checkin'); ?></button></p>
                </form>

                <hr />

                <h3><?php esc_html_e('Send Test SMS', 'schedule-checkin'); ?></h3>
                <p class="description"><?php esc_html_e('Send a one-time test message using your current Twilio configuration.', 'schedule-checkin'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_send_test_sms'); ?>
                    <input type="hidden" name="action" value="sc_send_test_sms" />

                    <div class="sc-grid-3">
                        <label><span><?php esc_html_e('Test Phone Number', 'schedule-checkin'); ?></span><input type="text" name="test_sms_to" value="" placeholder="+15551234567" required /></label>
                        <label style="grid-column: span 2;"><span><?php esc_html_e('Message (optional)', 'schedule-checkin'); ?></span><textarea name="test_sms_message" rows="3" placeholder="<?php echo esc_attr__('Twilio test message from Schedule and Check-In.', 'schedule-checkin'); ?>"></textarea></label>
                    </div>
                    <p><button class="button button-primary" type="submit"><?php esc_html_e('Send Test SMS', 'schedule-checkin'); ?></button></p>
                </form>

                <h3><?php esc_html_e('Send Test Email', 'schedule-checkin'); ?></h3>
                <p class="description"><?php esc_html_e('Send a one-time test email using your current site email setup.', 'schedule-checkin'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_send_test_email'); ?>
                    <input type="hidden" name="action" value="sc_send_test_email" />

                    <div class="sc-grid-3">
                        <label><span><?php esc_html_e('Test Email Address', 'schedule-checkin'); ?></span><input type="email" name="test_email_to" value="" placeholder="volunteer@example.com" required /></label>
                        <label style="grid-column: span 2;"><span><?php esc_html_e('Subject (optional)', 'schedule-checkin'); ?></span><input type="text" name="test_email_subject" value="" /></label>
                        <label style="grid-column: span 3;"><span><?php esc_html_e('Message (optional)', 'schedule-checkin'); ?></span><textarea name="test_email_message" rows="4" placeholder="<?php echo esc_attr__('This is a test email from Schedule and Check-In.', 'schedule-checkin'); ?>"></textarea></label>
                    </div>
                    <p><button class="button button-primary" type="submit"><?php esc_html_e('Send Test Email', 'schedule-checkin'); ?></button></p>
                </form>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Event Categories', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Manage report and event categories independently from Twilio settings.', 'schedule-checkin'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_save_comm_settings'); ?>
                    <input type="hidden" name="action" value="sc_save_comm_settings" />
                    <input type="hidden" name="redirect_page" value="sc-settings" />
                    <input type="hidden" name="settings_section" value="event_categories" />

                    <label>
                        <span><?php esc_html_e('Event Categories (one per line)', 'schedule-checkin'); ?></span>
                        <textarea name="event_categories" rows="8" placeholder="General&#10;Youth&#10;Outreach"><?php echo esc_textarea($event_categories_raw); ?></textarea>
                    </label>
                    <p class="description"><?php esc_html_e('These categories appear on event creation and can be used to filter reports.', 'schedule-checkin'); ?></p>
                    <p><button class="button button-primary" type="submit"><?php esc_html_e('Save Event Categories', 'schedule-checkin'); ?></button></p>
                </form>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Danger Zone', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('This action deletes all plugin event, task, volunteer, assignment, and check-in data, then recreates the database schema at the current version.', 'schedule-checkin'); ?></p>
                <p class="description"><strong><?php esc_html_e('This cannot be undone.', 'schedule-checkin'); ?></strong></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_reset_database'); ?>
                    <input type="hidden" name="action" value="sc_reset_database" />

                    <label>
                        <span><?php esc_html_e('Reset Scope', 'schedule-checkin'); ?></span>
                        <select name="reset_settings_scope" id="sc_reset_settings_scope">
                            <option value="keep_settings"><?php esc_html_e('Recreate data tables only (keep Settings page options)', 'schedule-checkin'); ?></option>
                            <option value="delete_settings"><?php esc_html_e('Recreate data tables and delete Settings page options', 'schedule-checkin'); ?></option>
                        </select>
                    </label>

                    <p class="description" id="sc_reset_settings_warning" style="display:none;"><strong><?php esc_html_e('Warning: You selected deleting Settings page options. This includes Twilio credentials/cost settings and event categories.', 'schedule-checkin'); ?></strong></p>

                    <label id="sc_reset_settings_confirm_row" style="display:none;">
                        <span><?php esc_html_e('Type this exactly to delete Settings page options:', 'schedule-checkin'); ?></span>
                        <code>DELETE SETTINGS</code>
                        <input type="text" id="sc_reset_settings_confirm" name="reset_settings_confirm" value="" placeholder="DELETE SETTINGS" />
                    </label>

                    <label>
                        <span><?php esc_html_e('Type this exactly to confirm:', 'schedule-checkin'); ?></span>
                        <code>I KNOW WHAT I AM DOING</code>
                        <input type="text" name="reset_confirm" value="" placeholder="I KNOW WHAT I AM DOING" required />
                    </label>

                    <label class="sc-checkbox-label">
                        <input type="checkbox" name="reset_acknowledge" value="1" id="sc_reset_acknowledge" />
                        <span><?php esc_html_e('I understand this cannot be undone.', 'schedule-checkin'); ?></span>
                    </label>

                    <p>
                        <button class="button button-primary" id="sc_reset_database_submit" type="submit" disabled onclick="return confirm('<?php echo esc_js(__('Delete all plugin data and recreate schema now?', 'schedule-checkin')); ?>');"><?php esc_html_e('Delete & Recreate Database', 'schedule-checkin'); ?></button>
                    </p>
                </form>
                <script>
                (function () {
                    var acknowledge = document.getElementById('sc_reset_acknowledge');
                    var scope = document.getElementById('sc_reset_settings_scope');
                    var settingsWarning = document.getElementById('sc_reset_settings_warning');
                    var settingsConfirmRow = document.getElementById('sc_reset_settings_confirm_row');
                    var settingsConfirmInput = document.getElementById('sc_reset_settings_confirm');
                    var submit = document.getElementById('sc_reset_database_submit');
                    if (!acknowledge || !submit || !scope || !settingsWarning || !settingsConfirmRow || !settingsConfirmInput) {
                        return;
                    }
                    var sync = function () {
                        var deletingSettings = scope.value === 'delete_settings';
                        settingsWarning.style.display = deletingSettings ? '' : 'none';
                        settingsConfirmRow.style.display = deletingSettings ? '' : 'none';
                        if (!deletingSettings) {
                            settingsConfirmInput.value = '';
                        }

                        var settingsPhraseOk = !deletingSettings || settingsConfirmInput.value.trim() === 'DELETE SETTINGS';
                        submit.disabled = !acknowledge.checked || !settingsPhraseOk;
                    };
                    acknowledge.addEventListener('change', sync);
                    scope.addEventListener('change', sync);
                    settingsConfirmInput.addEventListener('input', sync);
                    sync();
                })();
                </script>
            </div>
            <script>
            (function () {
                var cards = document.querySelectorAll('.sc-admin-wrap .sc-card > h2');
                if (!cards.length) {
                    return;
                }

                for (var i = 0; i < cards.length; i++) {
                    (function (heading) {
                        var contentNodes = [];
                        var node = heading.nextElementSibling;
                        while (node) {
                            contentNodes.push(node);
                            node.style.display = 'none';
                            node = node.nextElementSibling;
                        }

                        heading.style.cursor = 'pointer';
                        heading.setAttribute('role', 'button');
                        heading.setAttribute('tabindex', '0');
                        heading.setAttribute('aria-expanded', 'false');

                        var sync = function () {
                            var expanded = heading.getAttribute('aria-expanded') === 'true';
                            for (var c = 0; c < contentNodes.length; c++) {
                                contentNodes[c].style.display = expanded ? '' : 'none';
                            }
                        };

                        var toggle = function () {
                            var expanded = heading.getAttribute('aria-expanded') === 'true';
                            heading.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                            sync();
                        };

                        heading.addEventListener('click', toggle);
                        heading.addEventListener('keydown', function (event) {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                toggle();
                            }
                        });
                    })(cards[i]);
                }
            })();
            </script>
        </div>
        <?php
    }

    public static function render_communications_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }

        global $wpdb;
        $t = self::tables();

        $events = $wpdb->get_results("SELECT id, title, start_datetime, end_datetime, allow_substitutes FROM {$t['events']} ORDER BY start_datetime DESC");
        $substitute_enabled_events = array_values(array_filter((array) $events, static function ($event) {
            return !isset($event->allow_substitutes) || (int) $event->allow_substitutes === 1;
        }));
        $email_templates = $wpdb->get_results("SELECT * FROM {$t['comm_templates']} WHERE channel = 'email' ORDER BY name ASC");
        $sms_templates = $wpdb->get_results("SELECT * FROM {$t['comm_templates']} WHERE channel = 'sms' ORDER BY name ASC");
        $all_templates = $wpdb->get_results("SELECT * FROM {$t['comm_templates']} ORDER BY channel ASC, name ASC");
        $sms_gsm_rate = (float) self::get_plugin_setting('sc_twilio_sms_cost_gsm_segment', '0');
        $sms_unicode_rate = (float) self::get_plugin_setting('sc_twilio_sms_cost_unicode_segment', '0');
        $event_sms_estimates = self::build_event_sms_estimate_context($events);
        $comm_log_event_id = absint($_GET['comm_log_event_id'] ?? 0);
        $comm_log_channel = sanitize_key((string) ($_GET['comm_log_channel'] ?? ''));
        if (!in_array($comm_log_channel, ['', 'email', 'sms'], true)) {
            $comm_log_channel = '';
        }
        $comm_log_status = sanitize_key((string) ($_GET['comm_log_status'] ?? ''));
        $comm_log_status_options = $wpdb->get_col("SELECT DISTINCT status FROM {$t['comm_logs']} ORDER BY status ASC");
        $comm_log_status_options = array_values(array_filter(array_map('sanitize_key', (array) $comm_log_status_options)));
        if ($comm_log_status !== '' && !in_array($comm_log_status, $comm_log_status_options, true)) {
            $comm_log_status = '';
        }
        $comm_log_page = max(1, absint($_GET['comm_log_paged'] ?? 1));
        $comm_log_limit = 50;
        $comm_log_offset = ($comm_log_page - 1) * $comm_log_limit;

        $comm_log_filters = [
            'event_id' => $comm_log_event_id,
            'channel' => $comm_log_channel,
            'status' => $comm_log_status,
        ];
        $comm_logs = self::query_communication_logs($comm_log_filters, $comm_log_limit, $comm_log_offset, false);
        $comm_log_total = (int) self::query_communication_logs($comm_log_filters, 0, 0, true);
        $comm_log_total_pages = max(1, (int) ceil($comm_log_total / $comm_log_limit));

        $editing_template_id = isset($_GET['edit_template']) ? absint($_GET['edit_template']) : 0;
        $editing_template = null;
        if ($editing_template_id) {
            $editing_template = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['comm_templates']} WHERE id = %d", $editing_template_id));
        }

        $token_help = [
            'volunteer_name' => 'Alex Morgan',
            'volunteer_first_name' => 'Alex',
            'event_title' => 'Community Outreach Event',
            'task_title' => 'Greeting Team',
            'event_start' => '03/20/2026 08:00 AM CST',
            'event_end' => '03/20/2026 12:00 PM CST',
            'volunteer_start_datetime_cst' => '03/20/2026 08:00 AM CST',
            'volunteer_end_datetime_cst' => '03/20/2026 09:00 AM CST',
            'days_before' => '2',
            'site_name' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
        ];

        $email_template_preview_map = [];
        foreach ((array) $email_templates as $template_row) {
            $email_template_preview_map[(string) (int) ($template_row->id ?? 0)] = [
                'subject' => (string) ($template_row->subject ?? ''),
                'body' => (string) ($template_row->body ?? ''),
            ];
        }

        $sms_template_preview_map = [];
        foreach ((array) $sms_templates as $template_row) {
            $sms_template_preview_map[(string) (int) ($template_row->id ?? 0)] = [
                'subject' => (string) ($template_row->subject ?? ''),
                'body' => (string) ($template_row->body ?? ''),
            ];
        }

        $event_title_map = [];
        foreach ((array) $events as $event_row) {
            $event_title_map[(string) (int) ($event_row->id ?? 0)] = (string) ($event_row->title ?? '');
        }

        ?>
        <div class="wrap sc-admin-wrap">
            <?php self::render_page_intro(__('Communications', 'schedule-checkin'), __('Manage templates, send outreach campaigns, and monitor delivery logs.', 'schedule-checkin')); ?>

            <div class="sc-card">
                <h2><?php esc_html_e('Templates', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Create, edit, and delete templates. Use variables to personalize each message.', 'schedule-checkin'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_save_comm_template'); ?>
                    <input type="hidden" name="action" value="sc_save_comm_template" />
                    <input type="hidden" name="template_id" value="<?php echo esc_attr((int) ($editing_template->id ?? 0)); ?>" />
                    <div class="sc-grid-3">
                        <label><span><?php esc_html_e('Channel', 'schedule-checkin'); ?></span><select id="sc_template_channel" name="channel"><option value="email" <?php selected((string) ($editing_template->channel ?? 'email'), 'email'); ?>><?php esc_html_e('Email', 'schedule-checkin'); ?></option><option value="sms" <?php selected((string) ($editing_template->channel ?? 'email'), 'sms'); ?>><?php esc_html_e('SMS', 'schedule-checkin'); ?></option></select></label>
                        <label><span><?php esc_html_e('Template Name', 'schedule-checkin'); ?></span><input type="text" name="name" value="<?php echo esc_attr($editing_template->name ?? ''); ?>" required /></label>
                        <label><span><?php esc_html_e('Email Subject (optional for SMS)', 'schedule-checkin'); ?></span><input type="text" name="subject" value="<?php echo esc_attr($editing_template->subject ?? ''); ?>" /></label>
                    </div>
                    <label>
                        <span><?php esc_html_e('Event Context (optional)', 'schedule-checkin'); ?></span>
                        <select id="sc_template_event_context">
                            <option value=""><?php esc_html_e('No event context', 'schedule-checkin'); ?></option>
                            <?php foreach ($events as $event) : ?>
                                <option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span><?php esc_html_e('Message Body', 'schedule-checkin'); ?></span><textarea id="sc_template_body" name="body" rows="5" required><?php echo esc_textarea($editing_template->body ?? ''); ?></textarea></label>
                    <div class="sc-grid-3">
                        <div><strong><?php esc_html_e('Character Count', 'schedule-checkin'); ?>:</strong> <span id="sc_template_char_count">0</span></div>
                        <div id="sc_template_sms_encoding_block"><strong><?php esc_html_e('Estimated Encoding', 'schedule-checkin'); ?>:</strong> <span id="sc_template_encoding">GSM-7</span></div>
                        <div id="sc_template_sms_segments_block"><strong><?php esc_html_e('Estimated Segments', 'schedule-checkin'); ?>:</strong> <span id="sc_template_segments">0</span></div>
                    </div>
                    <div id="sc_template_sms_cost_estimates">
                        <div class="sc-grid-3">
                            <div><strong><?php esc_html_e('Estimated SMS Cost / Message', 'schedule-checkin'); ?>:</strong> $<span id="sc_template_cost_per_message">0.0000</span></div>
                            <div><strong><?php esc_html_e('Estimated Recipients (Event)', 'schedule-checkin'); ?>:</strong> <span id="sc_template_event_recipients">0</span></div>
                            <div><strong><?php esc_html_e('Estimated Event SMS Cost', 'schedule-checkin'); ?>:</strong> $<span id="sc_template_cost_total">0.0000</span></div>
                        </div>
                        <p class="description"><?php esc_html_e('Estimated Recipients (Event) counts scheduled volunteers for the selected event who prefer SMS.', 'schedule-checkin'); ?></p>
                    </div>
                    <label><span><?php esc_html_e('Insert Variable', 'schedule-checkin'); ?></span>
                        <select id="sc_template_insert_token">
                            <option value=""><?php esc_html_e('Choose a variable', 'schedule-checkin'); ?></option>
                            <?php foreach ($token_help as $token_name => $sample_value) : ?>
                                <option value="{<?php echo esc_attr($token_name); ?>}"><?php echo esc_html('{' . $token_name . '}'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p class="description"><?php esc_html_e('Variables:', 'schedule-checkin'); ?> <?php echo esc_html(implode(', ', array_map(static function ($k) { return '{' . $k . '}'; }, array_keys($token_help)))); ?></p>
                    <label><span><?php esc_html_e('Live Preview (sample replacements)', 'schedule-checkin'); ?></span><textarea id="sc_template_preview" rows="4" readonly data-token-preview="<?php echo esc_attr(wp_json_encode($token_help)); ?>"></textarea></label>
                    <div id="sc_template_sms_context" data-event-estimates="<?php echo esc_attr(wp_json_encode($event_sms_estimates)); ?>" data-gsm-rate="<?php echo esc_attr(number_format($sms_gsm_rate, 6, '.', '')); ?>" data-unicode-rate="<?php echo esc_attr(number_format($sms_unicode_rate, 6, '.', '')); ?>"></div>
                    <p>
                        <button class="button button-primary" type="submit"><?php echo $editing_template ? esc_html__('Update Template', 'schedule-checkin') : esc_html__('Create Template', 'schedule-checkin'); ?></button>
                        <?php if ($editing_template) : ?>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-communications')); ?>"><?php esc_html_e('Cancel Edit', 'schedule-checkin'); ?></a>
                            <?php if ((int) ($editing_template->is_system ?? 0) !== 1) : ?>
                                <button class="button button-link-delete" type="submit" form="sc_delete_template_editor_form" onclick="return confirm('<?php echo esc_js(__('Delete this template?', 'schedule-checkin')); ?>');"><?php esc_html_e('Delete Template', 'schedule-checkin'); ?></button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                </form>
                <?php if ($editing_template && (int) ($editing_template->is_system ?? 0) !== 1) : ?>
                    <form id="sc_delete_template_editor_form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                        <?php wp_nonce_field('sc_delete_comm_template'); ?>
                        <input type="hidden" name="action" value="sc_delete_comm_template" />
                        <input type="hidden" name="template_id" value="<?php echo (int) $editing_template->id; ?>" />
                    </form>
                <?php endif; ?>

                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Channel', 'schedule-checkin'); ?></th><th><?php esc_html_e('Name', 'schedule-checkin'); ?></th><th><?php esc_html_e('Subject', 'schedule-checkin'); ?></th><th><?php esc_html_e('Actions', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                    <?php if (empty($all_templates)) : ?>
                        <tr><td colspan="4"><?php esc_html_e('No templates yet.', 'schedule-checkin'); ?></td></tr>
                    <?php else : foreach ($all_templates as $template) : ?>
                        <tr>
                            <td><?php echo esc_html(strtoupper((string) $template->channel)); ?></td>
                            <td><?php echo esc_html($template->name); ?></td>
                            <td><?php echo esc_html($template->subject ?: ''); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-communications&edit_template=' . (int) $template->id)); ?>"><?php esc_html_e('Edit', 'schedule-checkin'); ?></a>
                                <?php if ((int) ($template->is_system ?? 0) !== 1) : ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                                        <?php wp_nonce_field('sc_delete_comm_template'); ?>
                                        <input type="hidden" name="action" value="sc_delete_comm_template" />
                                        <input type="hidden" name="template_id" value="<?php echo (int) $template->id; ?>" />
                                        <button class="button button-link-delete" type="submit" onclick="return confirm('<?php echo esc_js(__('Delete this template?', 'schedule-checkin')); ?>');"><?php esc_html_e('Delete', 'schedule-checkin'); ?></button>
                                    </form>
                                <?php else : ?>
                                    <em><?php esc_html_e('System', 'schedule-checkin'); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Message Scheduled Event Members', 'schedule-checkin'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_send_event_scheduled_message'); ?>
                    <input type="hidden" name="action" value="sc_send_event_scheduled_message" />
                    <input type="hidden" name="sc_preview_scope" value="scheduled-members" />
                    <div class="sc-grid-3">
                        <label><span><?php esc_html_e('Event', 'schedule-checkin'); ?></span><select name="event_id" required><option value=""><?php esc_html_e('Select Event', 'schedule-checkin'); ?></option><?php foreach ($events as $event) : ?><option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->title); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e('Email Template', 'schedule-checkin'); ?></span><select name="email_template_id"><option value="0"><?php esc_html_e('None', 'schedule-checkin'); ?></option><?php foreach ($email_templates as $template) : ?><option value="<?php echo (int) $template->id; ?>"><?php echo esc_html($template->name); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e('SMS Template', 'schedule-checkin'); ?></span><select name="sms_template_id"><option value="0"><?php esc_html_e('None', 'schedule-checkin'); ?></option><?php foreach ($sms_templates as $template) : ?><option value="<?php echo (int) $template->id; ?>"><?php echo esc_html($template->name); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="sc-grid-2 sc-live-comm-preview">
                        <label><span><?php esc_html_e('Live Email Preview', 'schedule-checkin'); ?></span><textarea rows="5" class="sc-live-email-preview" readonly><?php esc_html_e('Select an email template to preview.', 'schedule-checkin'); ?></textarea></label>
                        <label><span><?php esc_html_e('Live SMS Preview', 'schedule-checkin'); ?></span><textarea rows="5" class="sc-live-sms-preview" readonly><?php esc_html_e('Select an SMS template to preview.', 'schedule-checkin'); ?></textarea></label>
                    </div>
                    <p><button class="button button-primary" type="submit"><?php esc_html_e('Send To Scheduled Members', 'schedule-checkin'); ?></button></p>
                </form>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Request Substitutions', 'schedule-checkin'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_send_substitute_pool_message'); ?>
                    <input type="hidden" name="action" value="sc_send_substitute_pool_message" />
                    <input type="hidden" name="sc_preview_scope" value="request-substitutions" />
                    <div class="sc-grid-3">
                        <label><span><?php esc_html_e('Event', 'schedule-checkin'); ?></span><select name="event_id" required><option value=""><?php esc_html_e('Select Event', 'schedule-checkin'); ?></option><?php foreach ($substitute_enabled_events as $event) : ?><option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->title); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e('Email Template', 'schedule-checkin'); ?></span><select name="email_template_id"><option value="0"><?php esc_html_e('None', 'schedule-checkin'); ?></option><?php foreach ($email_templates as $template) : ?><option value="<?php echo (int) $template->id; ?>"><?php echo esc_html($template->name); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e('SMS Template', 'schedule-checkin'); ?></span><select name="sms_template_id"><option value="0"><?php esc_html_e('None', 'schedule-checkin'); ?></option><?php foreach ($sms_templates as $template) : ?><option value="<?php echo (int) $template->id; ?>"><?php echo esc_html($template->name); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="sc-grid-2 sc-live-comm-preview">
                        <label><span><?php esc_html_e('Live Email Preview', 'schedule-checkin'); ?></span><textarea rows="5" class="sc-live-email-preview" readonly><?php esc_html_e('Select an email template to preview.', 'schedule-checkin'); ?></textarea></label>
                        <label><span><?php esc_html_e('Live SMS Preview', 'schedule-checkin'); ?></span><textarea rows="5" class="sc-live-sms-preview" readonly><?php esc_html_e('Select an SMS template to preview.', 'schedule-checkin'); ?></textarea></label>
                    </div>
                    <p><button class="button button-primary" type="submit"><?php esc_html_e('Send Substitution Request', 'schedule-checkin'); ?></button></p>
                </form>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Mass Message Volunteers', 'schedule-checkin'); ?></h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_send_mass_message'); ?>
                    <input type="hidden" name="action" value="sc_send_mass_message" />
                    <input type="hidden" name="sc_preview_scope" value="mass-message" />
                    <div class="sc-grid-3">
                        <label><span><?php esc_html_e('Email Template', 'schedule-checkin'); ?></span><select name="email_template_id"><option value="0"><?php esc_html_e('None', 'schedule-checkin'); ?></option><?php foreach ($email_templates as $template) : ?><option value="<?php echo (int) $template->id; ?>"><?php echo esc_html($template->name); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e('SMS Template', 'schedule-checkin'); ?></span><select name="sms_template_id"><option value="0"><?php esc_html_e('None', 'schedule-checkin'); ?></option><?php foreach ($sms_templates as $template) : ?><option value="<?php echo (int) $template->id; ?>"><?php echo esc_html($template->name); ?></option><?php endforeach; ?></select></label>
                        <label><span><?php esc_html_e('Event for Exclusion Filter', 'schedule-checkin'); ?></span><select name="exclude_event_id"><option value="0"><?php esc_html_e('None', 'schedule-checkin'); ?></option><?php foreach ($events as $event) : ?><option value="<?php echo (int) $event->id; ?>"><?php echo esc_html($event->title); ?></option><?php endforeach; ?></select></label>
                    </div>
                    <div class="sc-grid-2 sc-live-comm-preview">
                        <label><span><?php esc_html_e('Live Email Preview', 'schedule-checkin'); ?></span><textarea rows="5" class="sc-live-email-preview" readonly><?php esc_html_e('Select an email template to preview.', 'schedule-checkin'); ?></textarea></label>
                        <label><span><?php esc_html_e('Live SMS Preview', 'schedule-checkin'); ?></span><textarea rows="5" class="sc-live-sms-preview" readonly><?php esc_html_e('Select an SMS template to preview.', 'schedule-checkin'); ?></textarea></label>
                    </div>
                    <label class="sc-checkbox-label"><input type="checkbox" name="exclude_scheduled_for_event" value="1" /><span><?php esc_html_e('Exclude volunteers already scheduled for the selected event', 'schedule-checkin'); ?></span></label>
                    <p><button class="button button-primary" type="submit"><?php esc_html_e('Send Mass Message', 'schedule-checkin'); ?></button></p>
                </form>
            </div>

            <div id="sc_campaign_preview_data"
                 data-email-templates="<?php echo esc_attr(wp_json_encode($email_template_preview_map)); ?>"
                 data-sms-templates="<?php echo esc_attr(wp_json_encode($sms_template_preview_map)); ?>"
                 data-event-titles="<?php echo esc_attr(wp_json_encode($event_title_map)); ?>"
                 data-token-preview="<?php echo esc_attr(wp_json_encode($token_help)); ?>"></div>

            <div class="sc-card">
                <h2><?php esc_html_e('Communication Log', 'schedule-checkin'); ?></h2>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-bottom:10px;">
                    <input type="hidden" name="page" value="sc-communications" />
                    <div class="sc-grid-3">
                        <label>
                            <span><?php esc_html_e('Event', 'schedule-checkin'); ?></span>
                            <select name="comm_log_event_id">
                                <option value="0"><?php esc_html_e('All Events', 'schedule-checkin'); ?></option>
                                <?php foreach ($events as $event) : ?>
                                    <option value="<?php echo (int) $event->id; ?>" <?php selected($comm_log_event_id, (int) $event->id); ?>><?php echo esc_html($event->title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Channel', 'schedule-checkin'); ?></span>
                            <select name="comm_log_channel">
                                <option value=""><?php esc_html_e('All Channels', 'schedule-checkin'); ?></option>
                                <option value="email" <?php selected($comm_log_channel, 'email'); ?>><?php esc_html_e('Email', 'schedule-checkin'); ?></option>
                                <option value="sms" <?php selected($comm_log_channel, 'sms'); ?>><?php esc_html_e('SMS', 'schedule-checkin'); ?></option>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Status', 'schedule-checkin'); ?></span>
                            <select name="comm_log_status">
                                <option value=""><?php esc_html_e('All Statuses', 'schedule-checkin'); ?></option>
                                <?php foreach ($comm_log_status_options as $status_option) : ?>
                                    <option value="<?php echo esc_attr($status_option); ?>" <?php selected($comm_log_status, $status_option); ?>><?php echo esc_html($status_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <button class="button button-primary" type="submit"><?php esc_html_e('Apply Filters', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_comm_logs_csv'); ?>
                    <input type="hidden" name="action" value="sc_export_comm_logs_csv" />
                    <input type="hidden" name="comm_log_event_id" value="<?php echo (int) $comm_log_event_id; ?>" />
                    <input type="hidden" name="comm_log_channel" value="<?php echo esc_attr($comm_log_channel); ?>" />
                    <input type="hidden" name="comm_log_status" value="<?php echo esc_attr($comm_log_status); ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export CSV', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                    <?php wp_nonce_field('sc_export_comm_logs_excel'); ?>
                    <input type="hidden" name="action" value="sc_export_comm_logs_excel" />
                    <input type="hidden" name="comm_log_event_id" value="<?php echo (int) $comm_log_event_id; ?>" />
                    <input type="hidden" name="comm_log_channel" value="<?php echo esc_attr($comm_log_channel); ?>" />
                    <input type="hidden" name="comm_log_status" value="<?php echo esc_attr($comm_log_status); ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export Excel', 'schedule-checkin'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form" target="_blank">
                    <?php wp_nonce_field('sc_export_comm_logs_pdf'); ?>
                    <input type="hidden" name="action" value="sc_export_comm_logs_pdf" />
                    <input type="hidden" name="comm_log_event_id" value="<?php echo (int) $comm_log_event_id; ?>" />
                    <input type="hidden" name="comm_log_channel" value="<?php echo esc_attr($comm_log_channel); ?>" />
                    <input type="hidden" name="comm_log_status" value="<?php echo esc_attr($comm_log_status); ?>" />
                    <button class="button" type="submit"><?php esc_html_e('Export PDF', 'schedule-checkin'); ?></button>
                </form>
                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('When', 'schedule-checkin'); ?></th><th><?php esc_html_e('Volunteer', 'schedule-checkin'); ?></th><th><?php esc_html_e('Event', 'schedule-checkin'); ?></th><th><?php esc_html_e('Channel', 'schedule-checkin'); ?></th><th><?php esc_html_e('Purpose', 'schedule-checkin'); ?></th><th><?php esc_html_e('Recipient', 'schedule-checkin'); ?></th><th><?php esc_html_e('Status', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                    <?php if (!$comm_logs) : ?>
                        <tr><td colspan="7"><?php esc_html_e('No communication logs found.', 'schedule-checkin'); ?></td></tr>
                    <?php else : foreach ($comm_logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html(self::format_datetime_cst_12h($log['created_at'] ?? '')); ?></td>
                            <td><?php echo esc_html(($log['volunteer_name'] ?? '') !== '' ? $log['volunteer_name'] : __('Unknown', 'schedule-checkin')); ?></td>
                            <td><?php echo esc_html(($log['event_title'] ?? '') !== '' ? $log['event_title'] : __('N/A', 'schedule-checkin')); ?></td>
                            <td><?php echo esc_html(strtoupper((string) ($log['channel'] ?? ''))); ?></td>
                            <td><?php echo esc_html($log['purpose'] ?? ''); ?></td>
                            <td><?php echo esc_html($log['recipient'] ?? ''); ?></td>
                            <td><?php echo esc_html($log['status'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $comm_base_args = [
                            'page' => 'sc-communications',
                            'comm_log_event_id' => $comm_log_event_id,
                            'comm_log_channel' => $comm_log_channel,
                            'comm_log_status' => $comm_log_status,
                        ];
                        for ($i = 1; $i <= $comm_log_total_pages; $i++) {
                            $class = $i === $comm_log_page ? 'button button-primary' : 'button';
                            $url = add_query_arg(array_merge($comm_base_args, ['comm_log_paged' => $i]), admin_url('admin.php'));
                            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . (int) $i . '</a> ';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_faq_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }

        ?>
        <div class="wrap sc-admin-wrap">
            <?php self::render_page_intro(__('FAQ', 'schedule-checkin'), __('Reference help screens for setup, operations, reminders, communications, and reporting.', 'schedule-checkin'), false); ?>

            <div class="sc-card">
                <h2><?php esc_html_e('Twilio Integration', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Use this checklist to configure, test, and troubleshoot Twilio + email delivery.', 'schedule-checkin'); ?></p>

                <h3><?php esc_html_e('Setup', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Go to Settings and enter Twilio Account SID, API Key SID, and API Key Secret.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Set either Twilio Messaging Service SID or Twilio From Number (Messaging Service SID is recommended).', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Save Twilio Settings and confirm the success notice.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Test Delivery', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('In Settings, use Send Test SMS with an E.164 phone number such as +15551234567.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('In Settings, use Send Test Email with a valid recipient address.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('A success or failure notice appears after each test attempt.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Verify Logs', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Open Communications and review Communication Log entries.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Look for purpose = manual_test with channel, recipient, status, and timestamp.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Troubleshooting', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('If SMS fails, confirm Twilio credentials and sender configuration, then retry.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('If email fails, verify WordPress mail/SMTP configuration, then retry.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Use the status and error message in Communication Log to isolate the issue quickly.', 'schedule-checkin'); ?></li>
                </ul>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Reminder Scheduling', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Configure and verify automated reminders sent to scheduled volunteers before each event.', 'schedule-checkin'); ?></p>

                <h3><?php esc_html_e('Setup', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Open Events, select an event, and use Scheduled Reminder Settings.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Enable Reminder 1 and/or Reminder 2, set Days Before, and choose email and SMS templates.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Save the event after updating reminder settings.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Delivery Behavior', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Reminder processing runs on an hourly cron schedule.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Recipients are contacted by their preferred method (email or SMS), with fallback to the other channel if needed.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('The system deduplicates reminder campaigns per volunteer and event to avoid duplicate sends.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Verify Logs', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Open Communications and review the Communication Log after reminder windows are reached.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Check channel, purpose, recipient, status, and error message fields for each reminder attempt.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Troubleshooting', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('If no reminders send, confirm the event reminder checkboxes, days-before values, and template selections are saved.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('If SMS reminders fail, verify Twilio settings in Settings.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('If email reminders fail, verify WordPress mail/SMTP configuration.', 'schedule-checkin'); ?></li>
                </ul>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Mass Messaging Filters', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Use Communications to contact volunteers in bulk while excluding people already scheduled for a selected event.', 'schedule-checkin'); ?></p>

                <h3><?php esc_html_e('Setup', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Open Communications and locate the Mass Message panel.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Select the target template(s) and optional event filter for exclusion.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Submit the mass send action and confirm the completion notice.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Filter Behavior', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('When an event is selected, volunteers already scheduled for that event are excluded from recipients.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Each volunteer is sent using preferred contact method with fallback to the alternate channel when needed.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Communication records are written for each delivery attempt.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Verify Logs', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Open Communication Log and confirm rows for the campaign show expected channel and status.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Review recipient and error message fields to validate filter results and failures.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Troubleshooting', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('If too many recipients are included, verify the selected event filter before sending.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('If SMS or email sends fail, validate channel configuration in Settings and retry.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Use Communication Log status and error columns to isolate per-recipient issues.', 'schedule-checkin'); ?></li>
                </ul>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Event Setup and Task Generation', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Create events and build task schedules quickly while keeping slots and timing valid.', 'schedule-checkin'); ?></p>

                <h3><?php esc_html_e('Setup', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Create or select an event, then confirm title, start/end time, and admin PIN are valid.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Use Generate Tasks from timeframe for fast setup, or add tasks manually for custom coverage.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Confirm each task has correct time range and slot count before assigning volunteers.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Common Mistakes', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Task times must be inside event time range.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Task end time must be after task start time.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Replacing generated tasks removes existing tasks for that event.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Validation', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Review task list and slot totals on Events before publishing signup links.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Use Assignments view to confirm slot structure matches staffing expectations.', 'schedule-checkin'); ?></li>
                </ul>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Day-of Check-In Operations', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Run kiosk check-in smoothly, handle exceptions, and keep logs accurate during the event.', 'schedule-checkin'); ?></p>

                <h3><?php esc_html_e('Before Doors Open', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Open Assignments and verify scheduled volunteers, substitutes, and empty slots.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Open kiosk mode for the correct event and verify the admin PIN unlocks controls.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Optionally print check-in sheets for offline backup.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('During Event', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Use kiosk for arrivals/departures; updates are written as check-in log entries.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('If staffing changes, use Assignments actions to move, remove, or assign substitutes by slot.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Use bulk checkout at end of event only when needed.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('After Event', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Review Check-In Logs for missing check-outs or incorrect timestamps.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Edit individual log rows to correct issues before exporting reports.', 'schedule-checkin'); ?></li>
                </ul>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Reporting and Exports', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Use reports to audit attendance, volunteer hours, substitute usage, and communication outcomes.', 'schedule-checkin'); ?></p>

                <h3><?php esc_html_e('Report Workflow', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Open Reports and select the report type and filters (event or volunteer) as needed.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Use on-screen results to verify values before exporting.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Export CSV, Excel, or PDF based on your downstream process.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Best Practices', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Finalize log corrections before generating final hours exports.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Use event-specific filters when auditing substitute history or campaign outcomes.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('Keep exported files with timestamped names for audit traceability.', 'schedule-checkin'); ?></li>
                </ul>

                <h3><?php esc_html_e('Export Troubleshooting', 'schedule-checkin'); ?></h3>
                <ul>
                    <li><?php esc_html_e('If Excel or PDF export fails, run composer install and confirm required libraries are available.', 'schedule-checkin'); ?></li>
                    <li><?php esc_html_e('If report output looks wrong, re-check selected filters and source log data.', 'schedule-checkin'); ?></li>
                </ul>
            </div>
            <script>
            (function () {
                var cards = document.querySelectorAll('.sc-admin-wrap .sc-card > h2');
                if (!cards.length) {
                    return;
                }

                for (var i = 0; i < cards.length; i++) {
                    (function (heading) {
                        var contentNodes = [];
                        var node = heading.nextElementSibling;
                        while (node) {
                            contentNodes.push(node);
                            node.style.display = 'none';
                            node = node.nextElementSibling;
                        }

                        heading.style.cursor = 'pointer';
                        heading.setAttribute('role', 'button');
                        heading.setAttribute('tabindex', '0');
                        heading.setAttribute('aria-expanded', 'false');

                        var sync = function () {
                            var expanded = heading.getAttribute('aria-expanded') === 'true';
                            for (var c = 0; c < contentNodes.length; c++) {
                                contentNodes[c].style.display = expanded ? '' : 'none';
                            }
                        };

                        var toggle = function () {
                            var expanded = heading.getAttribute('aria-expanded') === 'true';
                            heading.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                            sync();
                        };

                        heading.addEventListener('click', toggle);
                        heading.addEventListener('keydown', function (event) {
                            if (event.key === 'Enter' || event.key === ' ') {
                                event.preventDefault();
                                toggle();
                            }
                        });
                    })(cards[i]);
                }
            })();
            </script>
        </div>
        <?php
    }

    private static function render_print_sheet($event_id) {
        if (!class_exists('Dompdf\Dompdf')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-logs&event_id=' . (int) $event_id . '&sc_error=pdf_library_missing'));
            exit;
        }

        global $wpdb;
        $t = self::tables();
        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['events']} WHERE id = %d", $event_id));
        if (!$event) {
            wp_safe_redirect(admin_url('admin.php?page=sc-logs&sc_error=missing_event'));
            exit;
        }

        $tasks = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['tasks']} WHERE event_id = %d ORDER BY start_datetime ASC", $event_id));
        $assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, v.name, v.phone FROM {$t['assignments']} a
             INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
             WHERE a.event_id = %d AND a.task_id IS NOT NULL ORDER BY a.task_id ASC, a.slot_number ASC",
            $event_id
        ));

        $by_task = [];
        foreach ($assignments as $a) {
            $by_task[$a->task_id][] = $a;
        }

        $html = '<html><head><meta charset="utf-8"><style>';
        $html .= '@page { margin: 22px 20px; }';
        $html .= 'body{font-family:DejaVu Sans, Arial, sans-serif;color:#111827;font-size:11px;}';
        $html .= 'h1{margin:0 0 6px;font-size:18px;}';
        $html .= '.meta{margin:0 0 14px;color:#4b5563;}';
        $html .= '.task-block{margin:0 0 12px 0; page-break-inside: avoid; break-inside: avoid-page;}';
        $html .= '.task-title{font-size:13px;font-weight:700;margin:0 0 6px 0;}';
        $html .= 'table{width:100%;border-collapse:collapse;table-layout:fixed;}';
        $html .= 'th,td{border:1px solid #d1d5db;padding:6px;vertical-align:top;text-align:left;}';
        $html .= 'thead{display:table-header-group;}';
        $html .= 'tr{page-break-inside:avoid;break-inside:avoid-page;}';
        $html .= '.col-slot{width:9%;} .col-volunteer{width:34%;} .col-phone{width:22%;} .col-in{width:17%;} .col-out{width:18%;}';
        $html .= '</style></head><body>';
        $html .= '<h1>' . esc_html($event->title) . '</h1>';
        $html .= '<p class="meta">' . esc_html(self::format_range_cst($event->start_datetime, $event->end_datetime)) . '</p>';

        if (empty($tasks)) {
            $html .= '<p>' . esc_html__('No tasks found for this event.', 'schedule-checkin') . '</p>';
        }

        foreach ($tasks as $task) {
            $html .= '<div class="task-block">';
            $html .= '<div class="task-title">' . esc_html((string) $task->title) . ' | ' . esc_html(self::format_range_cst((string) $task->start_datetime, (string) $task->end_datetime)) . '</div>';
            $html .= '<table><thead><tr>';
            $html .= '<th class="col-slot">' . esc_html__('Slot', 'schedule-checkin') . '</th>';
            $html .= '<th class="col-volunteer">' . esc_html__('Volunteer', 'schedule-checkin') . '</th>';
            $html .= '<th class="col-phone">' . esc_html__('Phone', 'schedule-checkin') . '</th>';
            $html .= '<th class="col-in">' . esc_html__('Check In', 'schedule-checkin') . '</th>';
            $html .= '<th class="col-out">' . esc_html__('Check Out', 'schedule-checkin') . '</th>';
            $html .= '</tr></thead><tbody>';

            for ($slot = 1; $slot <= (int) $task->slots; $slot++) {
                $match = null;
                foreach ($by_task[$task->id] ?? [] as $a) {
                    if ((int) $a->slot_number === $slot) {
                        $match = $a;
                        break;
                    }
                }

                $html .= '<tr>';
                $html .= '<td>' . (int) $slot . '</td>';
                $html .= '<td>' . esc_html((string) ($match->name ?? '')) . '</td>';
                $html .= '<td>' . esc_html(self::format_phone_for_display((string) ($match->phone ?? ''))) . '</td>';
                $html .= '<td></td>';
                $html .= '<td></td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        $html .= '</body></html>';

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename=' . sanitize_file_name(self::build_export_filename('checkin-sheet-' . $event_id, 'pdf')));
        echo $dompdf->output();
        exit;
    }

    public static function render_volunteers_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }

        global $wpdb;
        $t = self::tables();

        $status_filter = sanitize_key($_GET['status'] ?? 'active');
        if (!in_array($status_filter, ['all', 'active', 'inactive'], true)) {
            $status_filter = 'active';
        }

        $where = '';
        if ($status_filter === 'active') {
            $where = 'WHERE v.is_active = 1';
        } elseif ($status_filter === 'inactive') {
            $where = 'WHERE v.is_active = 0';
        }

        $volunteers = $wpdb->get_results(
            "SELECT v.*, 
                merged_target.name AS merged_into_name,
                    (SELECT COUNT(*) FROM {$t['assignments']} a WHERE a.volunteer_id = v.id) AS assignment_count,
                    (SELECT COUNT(*) FROM {$t['checkins']} c WHERE c.volunteer_id = v.id) AS checkin_count,
                    (SELECT COUNT(*) FROM {$t['comm_logs']} l WHERE l.volunteer_id = v.id) AS comm_count
             FROM {$t['volunteers']} v
             LEFT JOIN {$t['volunteers']} merged_target ON merged_target.id = v.merged_into_volunteer_id
             {$where}
             ORDER BY v.is_active DESC, v.name ASC"
        );

        $all_volunteers = $wpdb->get_results("SELECT id, name, email, is_active FROM {$t['volunteers']} ORDER BY is_active DESC, name ASC");
        $editing_volunteer_id = absint($_GET['edit_volunteer'] ?? 0);
        $editing_volunteer = $editing_volunteer_id
            ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['volunteers']} WHERE id = %d", $editing_volunteer_id))
            : null;

        ?>
        <div class="wrap sc-admin-wrap">
            <?php self::render_page_intro(__('Volunteers', 'schedule-checkin'), __('Manage volunteer profiles, merge duplicate records, and mark members as inactive.', 'schedule-checkin')); ?>

            <div class="sc-card">
                <h2><?php esc_html_e('Volunteer List', 'schedule-checkin'); ?></h2>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="sc-inline-form">
                    <input type="hidden" name="page" value="sc-volunteers" />
                    <label>
                        <span><?php esc_html_e('Status', 'schedule-checkin'); ?></span>
                        <select name="status">
                            <option value="active" <?php selected($status_filter, 'active'); ?>><?php esc_html_e('Active', 'schedule-checkin'); ?></option>
                            <option value="inactive" <?php selected($status_filter, 'inactive'); ?>><?php esc_html_e('Inactive', 'schedule-checkin'); ?></option>
                            <option value="all" <?php selected($status_filter, 'all'); ?>><?php esc_html_e('All', 'schedule-checkin'); ?></option>
                        </select>
                    </label>
                    <button class="button" type="submit"><?php esc_html_e('Apply', 'schedule-checkin'); ?></button>
                </form>

                <table class="widefat striped">
                    <thead><tr><th><?php esc_html_e('Name', 'schedule-checkin'); ?></th><th><?php esc_html_e('Email', 'schedule-checkin'); ?></th><th><?php esc_html_e('Phone', 'schedule-checkin'); ?></th><th><?php esc_html_e('Preferred', 'schedule-checkin'); ?></th><th><?php esc_html_e('Status', 'schedule-checkin'); ?></th><th><?php esc_html_e('Usage', 'schedule-checkin'); ?></th><th><?php esc_html_e('Actions', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php if (empty($volunteers)) : ?>
                            <tr><td colspan="7"><?php esc_html_e('No volunteers found.', 'schedule-checkin'); ?></td></tr>
                        <?php else : foreach ($volunteers as $volunteer) : ?>
                            <tr>
                                <td><?php echo esc_html((string) $volunteer->name); ?></td>
                                <td><?php echo esc_html((string) $volunteer->email); ?></td>
                                <td><?php echo esc_html(self::format_phone_for_display((string) $volunteer->phone)); ?></td>
                                <td><?php echo esc_html(strtoupper((string) ($volunteer->preferred_contact_method ?: 'email'))); ?></td>
                                <td>
                                    <?php
                                    if ((int) ($volunteer->is_active ?? 1) === 1) {
                                        echo esc_html__('Active', 'schedule-checkin');
                                    } else {
                                        $merged_into_name = trim((string) ($volunteer->merged_into_name ?? ''));
                                        if ($merged_into_name !== '') {
                                            echo esc_html(sprintf(__('Inactive (Merged into %s)', 'schedule-checkin'), $merged_into_name));
                                        } else {
                                            echo esc_html__('Inactive', 'schedule-checkin');
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html(sprintf(__('Assignments: %1$d, Check-Ins: %2$d, Messages: %3$d', 'schedule-checkin'), (int) ($volunteer->assignment_count ?? 0), (int) ($volunteer->checkin_count ?? 0), (int) ($volunteer->comm_count ?? 0))); ?></td>
                                <td>
                                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-volunteers&status=' . urlencode($status_filter) . '&edit_volunteer=' . (int) $volunteer->id)); ?>"><?php esc_html_e('Edit', 'schedule-checkin'); ?></a>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-inline-form">
                                        <?php wp_nonce_field('sc_set_volunteer_active'); ?>
                                        <input type="hidden" name="action" value="sc_set_volunteer_active" />
                                        <input type="hidden" name="volunteer_id" value="<?php echo (int) $volunteer->id; ?>" />
                                        <input type="hidden" name="is_active" value="<?php echo (int) ($volunteer->is_active ?? 1) === 1 ? '0' : '1'; ?>" />
                                        <input type="hidden" name="return_page" value="sc-volunteers" />
                                        <button class="button" type="submit"><?php echo (int) ($volunteer->is_active ?? 1) === 1 ? esc_html__('Mark Inactive', 'schedule-checkin') : esc_html__('Mark Active', 'schedule-checkin'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="sc-card">
                <h2><?php esc_html_e('Merge Volunteers', 'schedule-checkin'); ?></h2>
                <p class="description"><?php esc_html_e('Merge updates assignments, check-ins, and communication logs to the target volunteer, then marks the source volunteer inactive.', 'schedule-checkin'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('sc_merge_volunteers'); ?>
                    <input type="hidden" name="action" value="sc_merge_volunteers" />
                    <input type="hidden" name="return_page" value="sc-volunteers" />
                    <div class="sc-grid-2">
                        <label>
                            <span><?php esc_html_e('Source Volunteer (to merge from)', 'schedule-checkin'); ?></span>
                            <select name="source_volunteer_id" required>
                                <option value=""><?php esc_html_e('Select source volunteer', 'schedule-checkin'); ?></option>
                                <?php foreach ($all_volunteers as $volunteer) : ?>
                                    <option value="<?php echo (int) $volunteer->id; ?>"><?php echo esc_html($volunteer->name . ' | ' . ($volunteer->email ?: __('No email', 'schedule-checkin')) . ((int) ($volunteer->is_active ?? 1) === 1 ? '' : ' | ' . __('Inactive', 'schedule-checkin'))); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span><?php esc_html_e('Target Volunteer (to keep)', 'schedule-checkin'); ?></span>
                            <select name="target_volunteer_id" required>
                                <option value=""><?php esc_html_e('Select target volunteer', 'schedule-checkin'); ?></option>
                                <?php foreach ($all_volunteers as $volunteer) : ?>
                                    <option value="<?php echo (int) $volunteer->id; ?>"><?php echo esc_html($volunteer->name . ' | ' . ($volunteer->email ?: __('No email', 'schedule-checkin'))); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <p><button class="button button-primary" type="submit" onclick="return confirm('<?php echo esc_js(__('Merge source into target? This cannot be undone automatically.', 'schedule-checkin')); ?>');"><?php esc_html_e('Merge Volunteers', 'schedule-checkin'); ?></button></p>
                </form>
            </div>

            <?php if ($editing_volunteer) : ?>
                <div class="sc-card">
                    <h2><?php esc_html_e('Edit Volunteer', 'schedule-checkin'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('sc_update_volunteer'); ?>
                        <input type="hidden" name="action" value="sc_update_volunteer" />
                        <input type="hidden" name="volunteer_id" value="<?php echo (int) $editing_volunteer->id; ?>" />
                        <input type="hidden" name="return_page" value="sc-volunteers" />
                        <div class="sc-grid-2">
                            <label><span><?php esc_html_e('Name *', 'schedule-checkin'); ?></span><input required type="text" name="name" value="<?php echo esc_attr((string) $editing_volunteer->name); ?>" /></label>
                            <label><span><?php esc_html_e('Email Address *', 'schedule-checkin'); ?></span><input required type="email" name="email" value="<?php echo esc_attr((string) $editing_volunteer->email); ?>" /></label>
                            <label><span><?php esc_html_e('Phone Number *', 'schedule-checkin'); ?></span><input required type="text" name="phone" value="<?php echo esc_attr(self::format_phone_for_input((string) $editing_volunteer->phone)); ?>" pattern="\d{3}-\d{3}-\d{4}" /></label>
                            <label><span><?php esc_html_e('Preferred Communication', 'schedule-checkin'); ?></span><select name="preferred_contact_method"><option value="email" <?php selected((string) ($editing_volunteer->preferred_contact_method ?? 'email'), 'email'); ?>><?php esc_html_e('Email', 'schedule-checkin'); ?></option><option value="sms" <?php selected((string) ($editing_volunteer->preferred_contact_method ?? 'email'), 'sms'); ?>><?php esc_html_e('SMS', 'schedule-checkin'); ?></option></select></label>
                            <label><span><?php esc_html_e('Status', 'schedule-checkin'); ?></span><select name="is_active"><option value="1" <?php selected((int) ($editing_volunteer->is_active ?? 1), 1); ?>><?php esc_html_e('Active', 'schedule-checkin'); ?></option><option value="0" <?php selected((int) ($editing_volunteer->is_active ?? 1), 0); ?>><?php esc_html_e('Inactive', 'schedule-checkin'); ?></option></select></label>
                        </div>
                        <p><button class="button button-primary" type="submit"><?php esc_html_e('Save Volunteer', 'schedule-checkin'); ?></button> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=sc-volunteers&status=' . urlencode($status_filter))); ?>"><?php esc_html_e('Cancel', 'schedule-checkin'); ?></a></p>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_save_event() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_save_event');

        global $wpdb;
        $t = self::tables();

        $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
        $now = current_time('mysql');

        $title = sanitize_text_field($_POST['title'] ?? '');
        $start_datetime = self::normalize_datetime_cst($_POST['start_datetime'] ?? '');
        $end_datetime = self::normalize_datetime_cst($_POST['end_datetime'] ?? '');
        $event_categories = self::get_event_categories();
        $event_category = self::normalize_event_category(sanitize_text_field($_POST['event_category'] ?? ''), $event_categories);
        $owner_name = sanitize_text_field($_POST['owner_name'] ?? '');
        $owner_email = sanitize_email($_POST['owner_email'] ?? '');
        $allow_substitutes = isset($_POST['allow_substitutes']) ? (absint($_POST['allow_substitutes']) ? 1 : 0) : 1;
        $assignment_auto_enabled = !empty($_POST['assignment_auto_enabled']) ? 1 : 0;
        $assignment_auto_schedule = self::sanitize_assignment_auto_schedule($_POST['assignment_auto_schedule'] ?? 'weekly_monday');
        $admin_pin = preg_replace('/\D+/', '', (string) ($_POST['admin_pin'] ?? ''));
        if ($title === '' || $start_datetime === '' || $end_datetime === '') {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=invalid_event_data'));
            exit;
        }
        if (strtotime($end_datetime) < strtotime($start_datetime)) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=event_time_order'));
            exit;
        }
        if (strlen($admin_pin) < 4 || strlen($admin_pin) > 10) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=invalid_pin'));
            exit;
        }
        if ($owner_email !== '' && !is_email($owner_email)) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=invalid_event_owner_email'));
            exit;
        }

        $image_id = absint($_POST['image_id'] ?? 0);
        if ($image_id && !wp_attachment_is_image($image_id)) {
            $image_id = 0;
        }

        $data = [
            'title' => $title,
            'event_category' => $event_category !== '' ? $event_category : null,
            'description' => wp_kses_post($_POST['description'] ?? ''),
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
            'image_id' => $image_id,
            'visible_fields' => wp_json_encode(self::sanitize_visible_fields((array) ($_POST['visible_fields'] ?? []))),
            'require_checkout' => isset($_POST['require_checkout']) ? (absint($_POST['require_checkout']) ? 1 : 0) : 1,
            'allow_substitutes' => $allow_substitutes,
            'admin_pin' => $admin_pin,
            'reminder_1_enabled' => !empty($_POST['reminder_1_enabled']) ? 1 : 0,
            'reminder_1_days_before' => max(0, absint($_POST['reminder_1_days_before'] ?? 2)),
            'reminder_1_email_template_id' => ($tmp = absint($_POST['reminder_1_email_template_id'] ?? 0)) ? $tmp : null,
            'reminder_1_sms_template_id' => ($tmp = absint($_POST['reminder_1_sms_template_id'] ?? 0)) ? $tmp : null,
            'reminder_2_enabled' => !empty($_POST['reminder_2_enabled']) ? 1 : 0,
            'reminder_2_days_before' => max(0, absint($_POST['reminder_2_days_before'] ?? 1)),
            'reminder_2_email_template_id' => ($tmp = absint($_POST['reminder_2_email_template_id'] ?? 0)) ? $tmp : null,
            'reminder_2_sms_template_id' => ($tmp = absint($_POST['reminder_2_sms_template_id'] ?? 0)) ? $tmp : null,
            'owner_name' => $owner_name !== '' ? $owner_name : null,
            'owner_email' => $owner_email !== '' ? $owner_email : null,
            'assignment_auto_enabled' => $assignment_auto_enabled,
            'assignment_auto_schedule' => $assignment_auto_schedule,
            'updated_at' => $now,
        ];

        if ($event_id) {
            $wpdb->update($t['events'], $data, ['id' => $event_id]);
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($t['events'], $data);
            $event_id = (int) $wpdb->insert_id;
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_msg=event_saved'));
        exit;
    }

    public static function handle_delete_event() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_delete_event');

        global $wpdb;
        $t = self::tables();
        $event_id = absint($_POST['event_id'] ?? 0);
        if ($event_id) {
            $wpdb->delete($t['events'], ['id' => $event_id]);
            $wpdb->delete($t['tasks'], ['event_id' => $event_id]);
            $wpdb->delete($t['assignments'], ['event_id' => $event_id]);
            $wpdb->delete($t['checkins'], ['event_id' => $event_id]);
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-events&sc_msg=event_deleted'));
        exit;
    }

    public static function handle_save_task() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_save_task');

        global $wpdb;
        $t = self::tables();
        $event_id = absint($_POST['event_id'] ?? 0);
        $task_id = absint($_POST['task_id'] ?? 0);
        if (!$event_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events'));
            exit;
        }

        $event = $wpdb->get_row($wpdb->prepare("SELECT id, start_datetime, end_datetime FROM {$t['events']} WHERE id = %d", $event_id));
        if (!$event) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&sc_error=missing_event'));
            exit;
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $start_datetime = self::normalize_datetime_cst($_POST['start_datetime'] ?? '');
        $end_datetime = self::normalize_datetime_cst($_POST['end_datetime'] ?? '');
        if ($title === '' || $start_datetime === '' || $end_datetime === '') {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=invalid_task_data'));
            exit;
        }
        if (strtotime($end_datetime) < strtotime($start_datetime)) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=task_time_order'));
            exit;
        }
        if (strtotime($start_datetime) < strtotime($event->start_datetime) || strtotime($end_datetime) > strtotime($event->end_datetime)) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=task_outside_event'));
            exit;
        }

        $now = current_time('mysql');
        $data = [
            'event_id' => $event_id,
            'title' => $title,
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
            'slots' => max(1, absint($_POST['slots'] ?? 1)),
            'updated_at' => $now,
        ];

        if ($task_id) {
            $wpdb->update($t['tasks'], $data, ['id' => $task_id, 'event_id' => $event_id]);
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_msg=task_updated'));
            exit;
        }

        $sort_order = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM {$t['tasks']} WHERE event_id = %d", $event_id));
        $data['sort_order'] = $sort_order;
        $data['created_at'] = $now;
        $wpdb->insert($t['tasks'], $data);

        wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_msg=task_added'));
        exit;
    }

    public static function handle_generate_tasks() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_generate_tasks');

        global $wpdb;
        $t = self::tables();
        $event_id = absint($_POST['event_id'] ?? 0);
        $interval_minutes = max(1, absint($_POST['interval_minutes'] ?? 30));
        $slots = max(1, absint($_POST['slots'] ?? 1));
        $title_schema = sanitize_text_field($_POST['title_schema'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $replace_existing = !empty($_POST['replace_existing']);
        $replace_confirm = strtoupper(trim(sanitize_text_field($_POST['replace_confirm'] ?? '')));

        if (!$event_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&sc_error=missing_event'));
            exit;
        }
        if ($title_schema === '') {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=invalid_task_schema'));
            exit;
        }
        if ($replace_existing && $replace_confirm !== 'REPLACE') {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=replace_confirm_required'));
            exit;
        }

        $event = $wpdb->get_row($wpdb->prepare("SELECT id, start_datetime, end_datetime FROM {$t['events']} WHERE id = %d", $event_id));
        if (!$event) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&sc_error=missing_event'));
            exit;
        }

        $event_start_cst = self::to_cst_datetime((string) ($event->start_datetime ?? ''));
        $event_end_cst = self::to_cst_datetime((string) ($event->end_datetime ?? ''));
        if (!$event_start_cst || !$event_end_cst) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=task_wizard_event_datetime_invalid'));
            exit;
        }
        if ($event_end_cst <= $event_start_cst) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=task_wizard_event_time_order'));
            exit;
        }

        $interval_seconds = $interval_minutes * MINUTE_IN_SECONDS;
        $cursor = clone $event_start_cst;
        $sort_order = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(sort_order),0) FROM {$t['tasks']} WHERE event_id = %d", $event_id));
        $now = current_time('mysql');
        $created = 0;

        if ($replace_existing) {
            $task_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$t['tasks']} WHERE event_id = %d", $event_id));
            if (!empty($task_ids)) {
                $task_ids = array_map('absint', $task_ids);
                $in_clause = implode(',', $task_ids);
                $wpdb->query("DELETE FROM {$t['checkins']} WHERE event_id = " . (int) $event_id . " OR task_id IN ({$in_clause})");
                $wpdb->query("DELETE FROM {$t['assignments']} WHERE event_id = " . (int) $event_id . " OR task_id IN ({$in_clause})");
            }
            $wpdb->delete($t['tasks'], ['event_id' => $event_id]);
            $sort_order = 0;
        }

        while ($cursor < $event_end_cst) {
            $block_start = clone $cursor;
            $block_end = clone $cursor;
            $block_end->modify('+' . $interval_seconds . ' seconds');
            if ($block_end > $event_end_cst) {
                $block_end = clone $event_end_cst;
            }
            if ($block_end <= $block_start) {
                break;
            }

            $sort_order++;
            $wpdb->insert($t['tasks'], [
                'event_id' => $event_id,
                'title' => self::apply_task_title_schema($title_schema, $block_start, $block_end),
                'description' => $description,
                'start_datetime' => self::from_cst_datetime_to_storage($block_start),
                'end_datetime' => self::from_cst_datetime_to_storage($block_end),
                'slots' => $slots,
                'sort_order' => $sort_order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $created++;

            $cursor = $block_end;
        }

        if (!$created) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=task_generation_failed'));
            exit;
        }

        $message = $replace_existing ? 'tasks_replaced_generated' : 'tasks_generated';
        wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_msg=' . $message));
        exit;
    }

    public static function handle_delete_task() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_delete_task');

        global $wpdb;
        $t = self::tables();
        $event_id = absint($_POST['event_id'] ?? 0);
        $task_id = absint($_POST['task_id'] ?? 0);

        if ($event_id && $task_id) {
            $wpdb->delete($t['checkins'], ['task_id' => $task_id]);
            $wpdb->delete($t['assignments'], ['task_id' => $task_id]);
            $wpdb->delete($t['tasks'], ['id' => $task_id, 'event_id' => $event_id]);
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_msg=task_deleted'));
        exit;
    }

    public static function handle_delete_all_tasks() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_delete_all_tasks');

        global $wpdb;
        $t = self::tables();
        $event_id = absint($_POST['event_id'] ?? 0);

        if ($event_id) {
            $task_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$t['tasks']} WHERE event_id = %d", $event_id));
            if (!empty($task_ids)) {
                $task_ids = array_map('absint', $task_ids);
                $in_clause = implode(',', $task_ids);
                $wpdb->query("DELETE FROM {$t['checkins']} WHERE event_id = " . (int) $event_id . " OR task_id IN ({$in_clause})");
                $wpdb->query("DELETE FROM {$t['assignments']} WHERE event_id = " . (int) $event_id . " OR task_id IN ({$in_clause})");
            }
            $wpdb->delete($t['tasks'], ['event_id' => $event_id]);
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_msg=tasks_deleted_all'));
        exit;
    }

    public static function handle_update_assignment() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_update_assignment');

        global $wpdb;
        $t = self::tables();
        $assignment_id = absint($_POST['assignment_id'] ?? 0);

        if ($assignment_id) {
            $assignment = $wpdb->get_row($wpdb->prepare("SELECT id, event_id, task_id, volunteer_id FROM {$t['assignments']} WHERE id = %d", $assignment_id));
            if (!$assignment) {
                wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=sc-assignments&sc_error=assignment_missing'));
                exit;
            }

            $checked_in_at = self::normalize_datetime($_POST['checked_in_at'] ?? '');
            $checked_out_at = self::normalize_datetime($_POST['checked_out_at'] ?? '');
            if ($checked_in_at && $checked_out_at && strtotime($checked_out_at) < strtotime($checked_in_at)) {
                wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=sc-assignments&sc_error=time_order'));
                exit;
            }

            $wpdb->delete($t['checkins'], ['assignment_id' => $assignment_id]);

            if ($checked_in_at) {
                $wpdb->insert($t['checkins'], [
                    'event_id' => (int) $assignment->event_id,
                    'task_id' => !empty($assignment->task_id) ? (int) $assignment->task_id : null,
                    'assignment_id' => (int) $assignment_id,
                    'volunteer_id' => (int) $assignment->volunteer_id,
                    'action' => 'checkin',
                    'source' => 'admin',
                    'notes' => 'Edited from assignment time entry.',
                    'created_at' => $checked_in_at,
                ]);
            }

            if ($checked_out_at) {
                $wpdb->insert($t['checkins'], [
                    'event_id' => (int) $assignment->event_id,
                    'task_id' => !empty($assignment->task_id) ? (int) $assignment->task_id : null,
                    'assignment_id' => (int) $assignment_id,
                    'volunteer_id' => (int) $assignment->volunteer_id,
                    'action' => 'checkout',
                    'source' => 'admin',
                    'notes' => 'Edited from assignment time entry.',
                    'created_at' => $checked_out_at,
                ]);
            }

            $wpdb->update($t['assignments'], [
                'updated_at' => current_time('mysql'),
            ], ['id' => $assignment_id]);
        }

        $redirect = wp_get_referer() ?: admin_url('admin.php?page=sc-assignments');
        wp_safe_redirect(add_query_arg('sc_msg', 'assignment_saved', $redirect));
        exit;
    }

    public static function handle_assign_substitute() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_assign_substitute');

        global $wpdb;
        $t = self::tables();
        $event_id = absint($_POST['event_id'] ?? 0);
        $assignment_id = absint($_POST['assignment_id'] ?? 0);
        $task_id = absint($_POST['task_id'] ?? 0);
        $slot_number = absint($_POST['slot_number'] ?? 0);

        if (!$event_id || !$assignment_id || !$task_id || !$slot_number) {
            wp_safe_redirect(admin_url('admin.php?page=sc-assignments&event_id=' . $event_id . '&sc_error=invalid_task_data'));
            exit;
        }

        if (!self::event_allows_substitutes($event_id)) {
            wp_safe_redirect(admin_url('admin.php?page=sc-assignments&event_id=' . $event_id . '&sc_error=substitutes_not_allowed'));
            exit;
        }

        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT id, event_id, assignment_type, task_id FROM {$t['assignments']} WHERE id = %d",
            $assignment_id
        ));
        $task = $wpdb->get_row($wpdb->prepare(
            "SELECT id, event_id, slots FROM {$t['tasks']} WHERE id = %d",
            $task_id
        ));

        if (!$assignment || !$task || (int) $assignment->event_id !== $event_id || (int) $task->event_id !== $event_id || (string) $assignment->assignment_type !== 'substitute' || !empty($assignment->task_id)) {
            wp_safe_redirect(admin_url('admin.php?page=sc-assignments&event_id=' . $event_id . '&sc_error=assignment_missing'));
            exit;
        }

        if ($slot_number > (int) $task->slots) {
            wp_safe_redirect(admin_url('admin.php?page=sc-assignments&event_id=' . $event_id . '&sc_error=invalid_slot'));
            exit;
        }

        $occupied = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['assignments']} WHERE task_id = %d AND slot_number = %d",
            $task_id,
            $slot_number
        ));
        if ($occupied) {
            wp_safe_redirect(admin_url('admin.php?page=sc-assignments&event_id=' . $event_id . '&sc_error=slot_filled'));
            exit;
        }

        $wpdb->update($t['assignments'], [
            'task_id' => $task_id,
            'slot_number' => $slot_number,
            'status' => 'assigned',
            'updated_at' => current_time('mysql'),
        ], ['id' => $assignment_id]);

        wp_safe_redirect(admin_url('admin.php?page=sc-assignments&event_id=' . $event_id . '&sc_msg=substitute_assigned'));
        exit;
    }

    public static function handle_remove_assignment() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_remove_assignment');

        global $wpdb;
        $t = self::tables();
        $assignment_id = absint($_POST['assignment_id'] ?? 0);
        $event_id = absint($_POST['event_id'] ?? 0);
        $move_to_substitute = !empty($_POST['move_to_substitute']);

        if (!$assignment_id || !$event_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-assignments&event_id=' . $event_id . '&sc_error=assignment_missing'));
            exit;
        }

        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t['assignments']} WHERE id = %d AND event_id = %d",
            $assignment_id,
            $event_id
        ));
        if (!$assignment) {
            wp_safe_redirect(admin_url('admin.php?page=sc-assignments&event_id=' . $event_id . '&sc_error=assignment_missing'));
            exit;
        }

        if ($move_to_substitute) {
            if (!self::event_allows_substitutes($event_id)) {
                wp_safe_redirect(admin_url('admin.php?page=sc-assignments&event_id=' . $event_id . '&sc_error=substitutes_not_allowed'));
                exit;
            }

            $wpdb->delete($t['checkins'], ['assignment_id' => $assignment_id]);
            $wpdb->update($t['assignments'], [
                'task_id' => null,
                'slot_number' => null,
                'assignment_type' => 'substitute',
                'status' => 'substitute_pool',
                'updated_at' => current_time('mysql'),
            ], ['id' => $assignment_id]);

            wp_safe_redirect(admin_url('admin.php?page=sc-assignments&event_id=' . $event_id . '&sc_msg=assignment_moved_to_substitute'));
            exit;
        }

        $wpdb->delete($t['checkins'], ['assignment_id' => $assignment_id]);
        $wpdb->delete($t['assignments'], ['id' => $assignment_id]);
        wp_safe_redirect(admin_url('admin.php?page=sc-assignments&event_id=' . $event_id . '&sc_msg=assignment_deleted'));
        exit;
    }

    public static function handle_checkout_all() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_checkout_all');

        global $wpdb;
        $t = self::tables();
        $event_id = absint($_POST['event_id'] ?? 0);

        if ($event_id) {
            $summaries = self::get_assignment_activity_summaries(['event_id' => (int) $event_id]);
            foreach ($summaries as $row) {
                if (empty($row['is_checked_in'])) {
                    continue;
                }

                $checkout_at = (string) ($row['task_end_datetime'] ?? '');
                if ($checkout_at === '') {
                    $checkout_at = current_time('mysql');
                }

                $wpdb->insert($t['checkins'], [
                    'event_id' => $event_id,
                    'task_id' => !empty($row['task_id']) ? (int) $row['task_id'] : null,
                    'assignment_id' => (int) $row['assignment_id'],
                    'volunteer_id' => (int) $row['volunteer_id'],
                    'action' => 'checkout',
                    'source' => 'admin',
                    'notes' => 'Checked out by admin bulk action.',
                    'created_at' => $checkout_at,
                ]);

                $wpdb->update($t['assignments'], [
                    'updated_at' => current_time('mysql'),
                ], ['id' => (int) $row['assignment_id']]);
            }
        }

        $redirect = wp_get_referer() ?: admin_url('admin.php?page=sc-assignments&event_id=' . $event_id);
        wp_safe_redirect(add_query_arg('sc_msg', 'checkout_all_done', $redirect));
        exit;
    }

    public static function handle_update_volunteer() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_update_volunteer');

        global $wpdb;
        $t = self::tables();
        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        $return_page = sanitize_key($_POST['return_page'] ?? 'sc-reports');
        if (!in_array($return_page, ['sc-reports', 'sc-volunteers'], true)) {
            $return_page = 'sc-reports';
        }

        if ($volunteer_id) {
            $name = sanitize_text_field($_POST['name'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');
            $phone = self::normalize_phone_for_storage($_POST['phone'] ?? '');
            if ($name === '' || !is_email($email) || $phone === '') {
                wp_safe_redirect(admin_url('admin.php?page=' . $return_page . '&edit_volunteer=' . $volunteer_id . '&sc_error=volunteer_contact_invalid'));
                exit;
            }

            $update_data = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'preferred_contact_method' => in_array(sanitize_key($_POST['preferred_contact_method'] ?? 'email'), ['email', 'sms'], true) ? sanitize_key($_POST['preferred_contact_method']) : 'email',
                'updated_at' => current_time('mysql'),
            ];
            if (isset($_POST['is_active'])) {
                $update_data['is_active'] = absint($_POST['is_active']) ? 1 : 0;
            }

            $wpdb->update($t['volunteers'], $update_data, ['id' => $volunteer_id]);
        }

        $redirect = admin_url('admin.php?page=' . $return_page . '&edit_volunteer=' . $volunteer_id . '&sc_msg=volunteer_saved');
        if ($return_page === 'sc-volunteers') {
            $redirect .= '&status=all';
        }
        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_set_volunteer_active() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_set_volunteer_active');

        global $wpdb;
        $t = self::tables();

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        $is_active = absint($_POST['is_active'] ?? 0) ? 1 : 0;
        if ($volunteer_id) {
            $update_data = [
                'is_active' => $is_active,
                'updated_at' => current_time('mysql'),
            ];
            if ($is_active === 1) {
                $update_data['merged_into_volunteer_id'] = null;
                $update_data['merged_at'] = null;
            }

            $wpdb->update($t['volunteers'], $update_data, ['id' => $volunteer_id]);
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-volunteers&status=all&sc_msg=volunteer_saved'));
        exit;
    }

    public static function handle_merge_volunteers() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_merge_volunteers');

        global $wpdb;
        $t = self::tables();

        $source_id = absint($_POST['source_volunteer_id'] ?? 0);
        $target_id = absint($_POST['target_volunteer_id'] ?? 0);
        if (!$source_id || !$target_id || $source_id === $target_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-volunteers&status=all&sc_error=volunteer_merge_invalid'));
            exit;
        }

        $source = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['volunteers']} WHERE id = %d", $source_id));
        $target = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['volunteers']} WHERE id = %d", $target_id));
        if (!$source || !$target) {
            wp_safe_redirect(admin_url('admin.php?page=sc-volunteers&status=all&sc_error=missing_volunteer'));
            exit;
        }

        $merge_time = current_time('mysql');

        $wpdb->query('START TRANSACTION');

        $ok = true;
        $ok = $ok && self::cascade_volunteer_references($source_id, $target_id);

        $target_updates = [
            'updated_at' => $merge_time,
            'merged_into_volunteer_id' => null,
            'merged_at' => null,
        ];
        if (empty($target->email) && !empty($source->email)) {
            $target_updates['email'] = sanitize_email((string) $source->email);
        }
        if (empty($target->phone) && !empty($source->phone)) {
            $target_updates['phone'] = self::normalize_phone_for_storage((string) $source->phone);
        }
        if ((int) ($target->future_substitute_opt_in ?? 0) !== 1 && (int) ($source->future_substitute_opt_in ?? 0) === 1) {
            $target_updates['future_substitute_opt_in'] = 1;
        }
        if ((int) ($target->is_active ?? 1) !== 1) {
            $target_updates['is_active'] = 1;
        }
        $ok = $ok && ($wpdb->update($t['volunteers'], $target_updates, ['id' => $target_id]) !== false);
        $ok = $ok && ($wpdb->update($t['volunteers'], [
            'is_active' => 0,
            'merged_into_volunteer_id' => $target_id,
            'merged_at' => $merge_time,
            'updated_at' => $merge_time,
        ], ['id' => $source_id]) !== false);

        if ($ok) {
            $wpdb->query('COMMIT');
            wp_safe_redirect(admin_url('admin.php?page=sc-volunteers&status=all&sc_msg=volunteers_merged'));
            exit;
        }

        $wpdb->query('ROLLBACK');
        wp_safe_redirect(admin_url('admin.php?page=sc-volunteers&status=all&sc_error=volunteer_merge_failed'));
        exit;
    }

    private static function cascade_volunteer_references($source_volunteer_id, $target_volunteer_id) {
        global $wpdb;
        $t = self::tables();

        $source_volunteer_id = (int) $source_volunteer_id;
        $target_volunteer_id = (int) $target_volunteer_id;
        if ($source_volunteer_id <= 0 || $target_volunteer_id <= 0 || $source_volunteer_id === $target_volunteer_id) {
            return false;
        }

        $reference_tables = ['assignments', 'checkins', 'comm_logs'];
        foreach ($reference_tables as $table_key) {
            $table_name = (string) ($t[$table_key] ?? '');
            if ($table_name === '') {
                continue;
            }

            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$table_name} SET volunteer_id = %d WHERE volunteer_id = %d",
                $target_volunteer_id,
                $source_volunteer_id
            ));
            if ($updated === false) {
                return false;
            }
        }

        return true;
    }

    public static function handle_update_log() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_update_log');

        global $wpdb;
        $t = self::tables();
        $log_id = absint($_POST['log_id'] ?? 0);
        $event_id = absint($_POST['event_id'] ?? 0);

        if ($log_id) {
            $created_at = self::normalize_datetime_cst($_POST['created_at'] ?? '');
            $action = sanitize_key($_POST['log_action'] ?? 'checkin');
            if (!in_array($action, ['checkin', 'checkout'], true)) {
                $action = 'checkin';
            }

            $wpdb->update($t['checkins'], [
                'created_at' => $created_at ?: current_time('mysql'),
                'action' => $action,
                'source' => sanitize_text_field($_POST['source'] ?? ''),
                'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            ], ['id' => $log_id]);
        }

        $url = admin_url('admin.php?page=sc-logs' . ($event_id ? '&event_id=' . $event_id : '') . '&sc_msg=log_updated');
        wp_safe_redirect($url);
        exit;
    }

    public static function handle_delete_log() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_delete_log');

        global $wpdb;
        $t = self::tables();
        $log_id = absint($_POST['log_id'] ?? 0);
        $event_id = absint($_POST['event_id'] ?? 0);
        if ($log_id) {
            $wpdb->delete($t['checkins'], ['id' => $log_id]);
        }

        $url = admin_url('admin.php?page=sc-logs' . ($event_id ? '&event_id=' . $event_id : '') . '&sc_msg=log_deleted');
        wp_safe_redirect($url);
        exit;
    }

    public static function handle_move_assignment_ajax() {
        check_ajax_referer('sc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'schedule-checkin')], 403);
        }

        global $wpdb;
        $t = self::tables();

        $assignment_id = absint($_POST['assignment_id'] ?? 0);
        $target_task_id = absint($_POST['target_task_id'] ?? 0);
        $target_slot = absint($_POST['target_slot'] ?? 0);

        if (!$assignment_id || !$target_task_id || !$target_slot) {
            wp_send_json_error(['message' => __('Invalid request.', 'schedule-checkin')], 400);
        }

        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['assignments']} WHERE task_id = %d AND slot_number = %d", $target_task_id, $target_slot));
        if ($exists && (int) $exists !== $assignment_id) {
            wp_send_json_error(['message' => __('Target slot already filled.', 'schedule-checkin')], 400);
        }

        $assignment = $wpdb->get_row($wpdb->prepare("SELECT id, event_id FROM {$t['assignments']} WHERE id = %d", $assignment_id));
        if (!$assignment) {
            wp_send_json_error(['message' => __('Assignment not found.', 'schedule-checkin')], 404);
        }

        $task = $wpdb->get_row($wpdb->prepare("SELECT event_id, slots FROM {$t['tasks']} WHERE id = %d", $target_task_id));
        if (!$task) {
            wp_send_json_error(['message' => __('Task not found.', 'schedule-checkin')], 404);
        }
        if ((int) $task->event_id !== (int) $assignment->event_id) {
            wp_send_json_error(['message' => __('Cannot move to a different event.', 'schedule-checkin')], 400);
        }
        if ($target_slot > (int) $task->slots) {
            wp_send_json_error(['message' => __('Invalid slot for target task.', 'schedule-checkin')], 400);
        }

        $wpdb->update($t['assignments'], [
            'task_id' => $target_task_id,
            'slot_number' => $target_slot,
            'event_id' => $task->event_id,
            'updated_at' => current_time('mysql'),
        ], ['id' => $assignment_id]);

        wp_send_json_success(['message' => __('Moved.', 'schedule-checkin')]);
    }

    public static function handle_reset_database() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_reset_database');

        $confirm = trim((string) sanitize_text_field($_POST['reset_confirm'] ?? ''));
        if ($confirm !== 'I KNOW WHAT I AM DOING') {
            wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_error=reset_confirm_required'));
            exit;
        }

        $acknowledge = !empty($_POST['reset_acknowledge']);
        if (!$acknowledge) {
            wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_error=reset_acknowledge_required'));
            exit;
        }

        $reset_scope = sanitize_key((string) ($_POST['reset_settings_scope'] ?? 'keep_settings'));
        if (!in_array($reset_scope, ['keep_settings', 'delete_settings'], true)) {
            wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_error=reset_scope_invalid'));
            exit;
        }

        if ($reset_scope === 'delete_settings') {
            $settings_confirm = trim((string) sanitize_text_field($_POST['reset_settings_confirm'] ?? ''));
            if ($settings_confirm !== 'DELETE SETTINGS') {
                wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_error=reset_settings_confirm_required'));
                exit;
            }
        }

        global $wpdb;
        $t = self::tables();

        $wpdb->query("DROP TABLE IF EXISTS {$t['checkins']}");
        $wpdb->query("DROP TABLE IF EXISTS {$t['assignments']}");
        $wpdb->query("DROP TABLE IF EXISTS {$t['tasks']}");
        $wpdb->query("DROP TABLE IF EXISTS {$t['events']}");
        $wpdb->query("DROP TABLE IF EXISTS {$t['volunteers']}");

        SC_Install::activate();
        update_option('sc_plugin_version', SC_PLUGIN_VERSION);

        if ($reset_scope === 'delete_settings') {
            self::delete_settings_page_options();
            wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_msg=database_reset_with_settings'));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_msg=database_reset'));
        exit;
    }

    public static function handle_save_comm_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_save_comm_settings');

        $account_sid = sanitize_text_field($_POST['twilio_account_sid'] ?? ($_POST['twilio_sid'] ?? ''));
        $api_key_sid = sanitize_text_field($_POST['twilio_api_key_sid'] ?? '');
        $api_key_secret = sanitize_text_field($_POST['twilio_api_key_secret'] ?? '');
        $auth_token = sanitize_text_field($_POST['twilio_auth_token'] ?? ($_POST['twilio_token'] ?? ''));
        $gsm_rate = max(0, (float) ($_POST['twilio_sms_cost_gsm_segment'] ?? 0));
        $unicode_rate = max(0, (float) ($_POST['twilio_sms_cost_unicode_segment'] ?? 0));
        $raw_email_from_address = trim((string) ($_POST['email_from_address'] ?? ''));
        $email_from_address = sanitize_email($raw_email_from_address);
        $event_categories = self::sanitize_event_categories((string) ($_POST['event_categories'] ?? ''));
        $settings_section = sanitize_key((string) ($_POST['settings_section'] ?? 'twilio'));

        if ($settings_section !== 'event_categories' && $raw_email_from_address !== '' && !is_email($email_from_address)) {
            wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_error=invalid_from_email'));
            exit;
        }

        if ($settings_section === 'event_categories') {
            self::set_plugin_setting('sc_event_categories', implode("\n", $event_categories));
        } else {
            self::set_plugin_setting('sc_twilio_from', sanitize_text_field($_POST['twilio_from'] ?? ''));
            self::set_plugin_setting('sc_twilio_messaging_service_sid', sanitize_text_field($_POST['twilio_messaging_service_sid'] ?? ''));

            self::set_plugin_setting('sc_twilio_account_sid', $account_sid);
            self::set_plugin_setting('sc_twilio_api_key_sid', $api_key_sid);
            self::set_plugin_setting('sc_twilio_api_key_secret', $api_key_secret);
            self::set_plugin_setting('sc_twilio_auth_token', $auth_token);
            self::set_plugin_setting('sc_twilio_sms_cost_gsm_segment', (string) $gsm_rate);
            self::set_plugin_setting('sc_twilio_sms_cost_unicode_segment', (string) $unicode_rate);
            self::set_plugin_setting('sc_email_from_address', $email_from_address);
            self::set_plugin_setting('sc_twilio_sid', $account_sid);
            self::set_plugin_setting('sc_twilio_token', $auth_token);
        }

        $redirect_page = sanitize_key($_POST['redirect_page'] ?? 'sc-communications');
        if (!in_array($redirect_page, ['sc-communications', 'sc-settings'], true)) {
            $redirect_page = 'sc-communications';
        }

        wp_safe_redirect(admin_url('admin.php?page=' . $redirect_page . '&sc_msg=comm_settings_saved'));
        exit;
    }

    public static function handle_send_test_sms() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_send_test_sms');

        $to = self::normalize_sms_number(sanitize_text_field($_POST['test_sms_to'] ?? ''));
        if ($to === '') {
            wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_error=comm_test_sms_required'));
            exit;
        }

        $message = sanitize_textarea_field($_POST['test_sms_message'] ?? '');
        if ($message === '') {
            $message = sprintf(
                __('Test SMS from %s at %s', 'schedule-checkin'),
                wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
                current_time('mysql')
            );
        }

        $sms_result = self::send_sms_message($to, $message);
        self::log_communication([
            'volunteer_id' => null,
            'event_id' => null,
            'assignment_id' => null,
            'template_id' => null,
            'channel' => 'sms',
            'purpose' => 'manual_test',
            'campaign_key' => null,
            'recipient' => $to,
            'subject' => '',
            'message' => $message,
            'status' => $sms_result['success'] ? 'sent' : 'failed',
            'error_message' => (string) ($sms_result['error'] ?? ''),
            'created_at' => current_time('mysql'),
        ]);

        if ($sms_result['success']) {
            wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_msg=comm_test_sms_sent'));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_error=comm_test_sms_failed'));
        exit;
    }

    public static function handle_send_test_email() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_send_test_email');

        $to = sanitize_email((string) ($_POST['test_email_to'] ?? ''));
        if ($to === '') {
            wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_error=comm_test_email_required'));
            exit;
        }

        $subject = sanitize_text_field($_POST['test_email_subject'] ?? '');
        if ($subject === '') {
            $subject = sprintf(
                __('Test Email from %s', 'schedule-checkin'),
                wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
            );
        }

        $message = sanitize_textarea_field($_POST['test_email_message'] ?? '');
        if ($message === '') {
            $message = sprintf(
                __('Test email generated at %s', 'schedule-checkin'),
                current_time('mysql')
            );
        }

        $mail_ok = self::send_plugin_email($to, $subject, $message);
        self::log_communication([
            'volunteer_id' => null,
            'event_id' => null,
            'assignment_id' => null,
            'template_id' => null,
            'channel' => 'email',
            'purpose' => 'manual_test',
            'campaign_key' => null,
            'recipient' => $to,
            'subject' => $subject,
            'message' => $message,
            'status' => $mail_ok ? 'sent' : 'failed',
            'error_message' => $mail_ok ? '' : 'wp_mail failed.',
            'created_at' => current_time('mysql'),
        ]);

        if ($mail_ok) {
            wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_msg=comm_test_email_sent'));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-settings&sc_error=comm_test_email_failed'));
        exit;
    }

    public static function handle_save_comm_template() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_save_comm_template');

        global $wpdb;
        $t = self::tables();

        $channel = sanitize_key($_POST['channel'] ?? 'email');
        if (!in_array($channel, ['email', 'sms'], true)) {
            $channel = 'email';
        }
        $name = sanitize_text_field($_POST['name'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $body = wp_kses_post($_POST['body'] ?? '');
        $template_id = absint($_POST['template_id'] ?? 0);
        if ($name === '' || trim((string) $body) === '') {
            wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_error=comm_template_required'));
            exit;
        }

        $now = current_time('mysql');
        if ($template_id) {
            $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['comm_templates']} WHERE id = %d", $template_id));
            if (!$existing_id) {
                wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_error=comm_template_required'));
                exit;
            }

            $wpdb->update(
                $t['comm_templates'],
                [
                    'channel' => $channel,
                    'name' => $name,
                    'subject' => $subject,
                    'body' => $body,
                    'updated_at' => $now,
                ],
                ['id' => $template_id]
            );

            wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_msg=comm_template_updated'));
            exit;
        }

        $wpdb->insert($t['comm_templates'], [
            'channel' => $channel,
            'name' => $name,
            'subject' => $subject,
            'body' => $body,
            'is_system' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_msg=comm_template_saved'));
        exit;
    }

    public static function handle_delete_comm_template() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_delete_comm_template');

        global $wpdb;
        $t = self::tables();
        $template_id = absint($_POST['template_id'] ?? 0);
        if ($template_id) {
            $template = $wpdb->get_row($wpdb->prepare("SELECT id, is_system FROM {$t['comm_templates']} WHERE id = %d", $template_id));
            if ($template && (int) $template->is_system !== 1) {
                $wpdb->delete($t['comm_templates'], ['id' => $template_id]);
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_msg=comm_template_deleted'));
        exit;
    }

    public static function handle_send_event_scheduled_message() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_send_event_scheduled_message');

        $event_id = absint($_POST['event_id'] ?? 0);
        $email_template_id = absint($_POST['email_template_id'] ?? 0);
        $sms_template_id = absint($_POST['sms_template_id'] ?? 0);
        if (!$event_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_error=missing_event'));
            exit;
        }

        if (!self::event_allows_substitutes($event_id)) {
            wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_error=substitutes_not_allowed'));
            exit;
        }

        global $wpdb;
        $t = self::tables();
        $volunteers = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT v.*
             FROM {$t['assignments']} a
             INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
             WHERE a.event_id = %d AND a.task_id IS NOT NULL AND a.status <> 'substitute_pool'",
            $event_id
        ));

        $sent_count = 0;
        foreach ($volunteers as $volunteer) {
            if (self::send_preferred_template_message((int) $volunteer->id, $event_id, 0, $email_template_id, $sms_template_id, 'manual_event_scheduled', [])) {
                $sent_count++;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_msg=comm_sent&sent=' . $sent_count));
        exit;
    }

    public static function handle_send_substitute_pool_message() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_send_substitute_pool_message');

        $event_id = absint($_POST['event_id'] ?? 0);
        $email_template_id = absint($_POST['email_template_id'] ?? 0);
        $sms_template_id = absint($_POST['sms_template_id'] ?? 0);
        if (!$event_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_error=missing_event'));
            exit;
        }

        global $wpdb;
        $t = self::tables();
        $volunteers = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT v.*
             FROM {$t['assignments']} a
             INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
             WHERE a.event_id = %d AND a.assignment_type = 'substitute' AND a.task_id IS NULL",
            $event_id
        ));

        $sent_count = 0;
        foreach ($volunteers as $volunteer) {
            if (self::send_preferred_template_message((int) $volunteer->id, $event_id, 0, $email_template_id, $sms_template_id, 'manual_substitute_request', [])) {
                $sent_count++;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_msg=comm_sent&sent=' . $sent_count));
        exit;
    }

    public static function handle_send_mass_message() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_send_mass_message');

        $email_template_id = absint($_POST['email_template_id'] ?? 0);
        $sms_template_id = absint($_POST['sms_template_id'] ?? 0);
        $exclude_event_id = absint($_POST['exclude_event_id'] ?? 0);
        $exclude_scheduled_for_event = !empty($_POST['exclude_scheduled_for_event']);

        global $wpdb;
        $t = self::tables();
        $volunteers = $wpdb->get_results("SELECT * FROM {$t['volunteers']} ORDER BY name ASC");

        $exclude_ids = [];
        if ($exclude_scheduled_for_event && $exclude_event_id) {
            $exclude_rows = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT volunteer_id FROM {$t['assignments']} WHERE event_id = %d AND task_id IS NOT NULL",
                $exclude_event_id
            ));
            $exclude_ids = array_map('absint', $exclude_rows ?: []);
        }

        $sent_count = 0;
        foreach ($volunteers as $volunteer) {
            if (in_array((int) $volunteer->id, $exclude_ids, true)) {
                continue;
            }
            if (self::send_preferred_template_message((int) $volunteer->id, $exclude_event_id, 0, $email_template_id, $sms_template_id, 'manual_mass_message', [])) {
                $sent_count++;
            }
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_msg=comm_sent&sent=' . $sent_count));
        exit;
    }

    public static function handle_send_assignment_report_now() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_send_assignment_report_now');

        global $wpdb;
        $t = self::tables();

        $event_id = absint($_POST['event_id'] ?? 0);
        if ($event_id <= 0) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&sc_error=missing_event'));
            exit;
        }

        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['events']} WHERE id = %d", $event_id));
        if (!$event) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=missing_event'));
            exit;
        }

        $owner_email = sanitize_email((string) ($event->owner_email ?? ''));
        if ($owner_email === '' || !is_email($owner_email)) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=assignment_report_owner_missing'));
            exit;
        }

        $rows = self::query_assignment_report_rows($event_id);
        if (empty($rows)) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=assignment_report_no_rows'));
            exit;
        }

        $campaign_key = 'assignment_report_event_' . $event_id . '_manual_' . gmdate('YmdHis');
        $mail_ok = self::send_assignment_report_email($event, $rows, false, 'manual_assignment_report', $campaign_key);

        if (!$mail_ok) {
            wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_error=assignment_report_send_failed'));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=sc-events&event_id=' . $event_id . '&sc_msg=assignment_report_sent'));
        exit;
    }

    public static function process_scheduled_reminders() {
        global $wpdb;
        $t = self::tables();

        $events = $wpdb->get_results(
            "SELECT * FROM {$t['events']}
             WHERE (reminder_1_enabled = 1 OR reminder_2_enabled = 1)
               AND start_datetime >= NOW()"
        );

        foreach ($events as $event) {
            $days_until = self::days_until_event_start($event->start_datetime);
            $rules = [
                [
                    'idx' => 1,
                    'enabled' => (int) ($event->reminder_1_enabled ?? 0) === 1,
                    'days' => (int) ($event->reminder_1_days_before ?? 0),
                    'email_template_id' => absint($event->reminder_1_email_template_id ?? 0),
                    'sms_template_id' => absint($event->reminder_1_sms_template_id ?? 0),
                ],
                [
                    'idx' => 2,
                    'enabled' => (int) ($event->reminder_2_enabled ?? 0) === 1,
                    'days' => (int) ($event->reminder_2_days_before ?? 0),
                    'email_template_id' => absint($event->reminder_2_email_template_id ?? 0),
                    'sms_template_id' => absint($event->reminder_2_sms_template_id ?? 0),
                ],
            ];

            foreach ($rules as $rule) {
                if (!$rule['enabled'] || $days_until !== $rule['days']) {
                    continue;
                }

                $assignments = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, volunteer_id FROM {$t['assignments']} WHERE event_id = %d AND task_id IS NOT NULL AND status <> 'substitute_pool'",
                    (int) $event->id
                ));

                $sent_for_volunteer = [];
                foreach ($assignments as $assignment) {
                    $volunteer_id = (int) $assignment->volunteer_id;
                    if (isset($sent_for_volunteer[$volunteer_id])) {
                        continue;
                    }

                    $campaign_key = 'event_' . (int) $event->id . '_reminder_' . (int) $rule['idx'];
                    if (self::communication_exists($volunteer_id, (int) $event->id, $campaign_key)) {
                        continue;
                    }

                    self::send_preferred_template_message(
                        $volunteer_id,
                        (int) $event->id,
                        (int) $assignment->id,
                        (int) $rule['email_template_id'],
                        (int) $rule['sms_template_id'],
                        'scheduled_reminder_' . (int) $rule['idx'],
                        ['days_before' => (int) $rule['days']],
                        $campaign_key
                    );

                    $sent_for_volunteer[$volunteer_id] = true;
                }
            }
        }

        self::process_assignment_report_automations();
    }

    private static function process_assignment_report_automations() {
        global $wpdb;
        $t = self::tables();

        $events = $wpdb->get_results(
            "SELECT *
             FROM {$t['events']}
             WHERE assignment_auto_enabled = 1
               AND owner_email IS NOT NULL
               AND owner_email <> ''"
        );

        if (empty($events)) {
            return;
        }

        $now_cst = new DateTime('now', self::cst_timezone());
        $today_key = $now_cst->format('Y-m-d');
        $hour = (int) $now_cst->format('G');
        $weekday = (int) $now_cst->format('N');
        $iso_week = (int) $now_cst->format('W');

        foreach ($events as $event) {
            $event_id = (int) ($event->id ?? 0);
            if ($event_id <= 0) {
                continue;
            }

            $owner_email = sanitize_email((string) ($event->owner_email ?? ''));
            if ($owner_email === '' || !is_email($owner_email)) {
                continue;
            }

            $schedule = self::sanitize_assignment_auto_schedule((string) ($event->assignment_auto_schedule ?? 'weekly_monday'));
            $event_start_cst = self::to_cst_datetime((string) ($event->start_datetime ?? ''));
            if (!$event_start_cst) {
                continue;
            }

            $event_day_key = $event_start_cst->format('Y-m-d');
            $last_sent_key = '';
            if (!empty($event->assignment_auto_last_sent_at)) {
                $last_sent_dt = self::to_cst_datetime((string) $event->assignment_auto_last_sent_at);
                $last_sent_key = $last_sent_dt ? $last_sent_dt->format('Y-m-d') : '';
            }

            $final_sent_key = '';
            if (!empty($event->assignment_auto_final_sent_at)) {
                $final_sent_dt = self::to_cst_datetime((string) $event->assignment_auto_final_sent_at);
                $final_sent_key = $final_sent_dt ? $final_sent_dt->format('Y-m-d') : '';
            }

            $is_event_day = ($today_key === $event_day_key);
            $final_due = $is_event_day && $hour >= 6 && $final_sent_key !== $today_key;
            $recurring_due = false;

            if (!$final_due && $hour >= 6 && $last_sent_key !== $today_key && strcmp($event_day_key, $today_key) >= 0) {
                if ($schedule === 'weekly_monday') {
                    $recurring_due = ($weekday === 1);
                } elseif ($schedule === 'biweekly_monday') {
                    $recurring_due = ($weekday === 1 && ($iso_week % 2) === 0);
                } elseif ($schedule === 'biweekly_monday_thursday') {
                    $recurring_due = (($weekday === 1 || $weekday === 4) && ($iso_week % 2) === 0);
                }
            }

            if (!$final_due && !$recurring_due) {
                continue;
            }

            $rows = self::query_assignment_report_rows($event_id);
            if (empty($rows)) {
                continue;
            }

            $campaign_key = $final_due
                ? ('assignment_report_event_' . $event_id . '_final_' . str_replace('-', '', $today_key))
                : ('assignment_report_event_' . $event_id . '_recurring_' . str_replace('-', '', $today_key));

            if (self::communication_exists(0, $event_id, $campaign_key)) {
                continue;
            }

            $mail_ok = self::send_assignment_report_email(
                $event,
                $rows,
                $final_due,
                $final_due ? 'scheduled_assignment_report_final' : 'scheduled_assignment_report',
                $campaign_key
            );

            if ($mail_ok) {
                $wpdb->update($t['events'], [
                    'assignment_auto_last_sent_at' => current_time('mysql'),
                    'assignment_auto_final_sent_at' => $final_due ? current_time('mysql') : ($event->assignment_auto_final_sent_at ?: null),
                    'updated_at' => current_time('mysql'),
                ], ['id' => $event_id]);
            }
        }
    }

    private static function send_assignment_report_email($event, $rows, $is_final, $purpose, $campaign_key = '') {
        $event_id = (int) ($event->id ?? 0);
        if ($event_id <= 0 || empty($rows)) {
            return false;
        }

        $owner_email = sanitize_email((string) ($event->owner_email ?? ''));
        if ($owner_email === '' || !is_email($owner_email)) {
            return false;
        }

        $subject_prefix = $is_final ? __('Final Assignment Report', 'schedule-checkin') : __('Assignment Report', 'schedule-checkin');
        $subject = $subject_prefix . ': ' . (string) ($event->title ?? ('#' . $event_id));
        $body = self::build_assignment_report_email_body($event, $rows, $is_final);

        add_filter('wp_mail_content_type', [__CLASS__, 'force_html_mail_content_type']);
        $mail_ok = self::send_plugin_email($owner_email, $subject, $body);
        remove_filter('wp_mail_content_type', [__CLASS__, 'force_html_mail_content_type']);

        self::log_communication([
            'volunteer_id' => 0,
            'event_id' => $event_id,
            'assignment_id' => null,
            'template_id' => null,
            'channel' => 'email',
            'purpose' => sanitize_key((string) $purpose) ?: 'assignment_report',
            'campaign_key' => $campaign_key !== '' ? sanitize_text_field($campaign_key) : null,
            'recipient' => $owner_email,
            'subject' => $subject,
            'message' => wp_strip_all_tags($body),
            'status' => $mail_ok ? 'sent' : 'failed',
            'error_message' => $mail_ok ? '' : 'wp_mail failed.',
            'created_at' => current_time('mysql'),
        ]);

        return $mail_ok;
    }

    public static function force_html_mail_content_type() {
        return 'text/html';
    }

    private static function build_assignment_report_email_body($event, $rows, $is_final) {
        $event_title = (string) ($event->title ?? '');
        $owner_name = trim((string) ($event->owner_name ?? ''));
        $header = $is_final
            ? __('Final assignment report for event day.', 'schedule-checkin')
            : __('Automatic assignment report update.', 'schedule-checkin');

        $html = '<div style="font-family:Arial,sans-serif;color:#1f2937;">';
        $html .= '<p>' . esc_html($header) . '</p>';
        if ($owner_name !== '') {
            $html .= '<p>' . esc_html(sprintf(__('Hello %s,', 'schedule-checkin'), $owner_name)) . '</p>';
        }
        $html .= '<p><strong>' . esc_html__('Event:', 'schedule-checkin') . '</strong> ' . esc_html($event_title) . '<br>';
        $html .= '<strong>' . esc_html__('Event Window (CST):', 'schedule-checkin') . '</strong> ' . esc_html(self::format_range_cst((string) ($event->start_datetime ?? ''), (string) ($event->end_datetime ?? ''))) . '</p>';
        $html .= '<table style="border-collapse:collapse;width:100%;">';
        $html .= '<thead><tr>';
        foreach (['Task', 'Start', 'End', 'Slot', 'Status', 'Volunteer', 'Email', 'Phone'] as $header_cell) {
            $html .= '<th style="border:1px solid #d1d5db;padding:6px;text-align:left;background:#f3f4f6;">' . esc_html($header_cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ((array) $rows as $row) {
            $html .= '<tr>';
            $cells = [
                (string) ($row['task_title'] ?? ''),
                self::format_datetime_cst_12h((string) ($row['task_start_datetime'] ?? '')),
                self::format_datetime_cst_12h((string) ($row['task_end_datetime'] ?? '')),
                (string) (int) ($row['slot_number'] ?? 0),
                (string) ($row['status'] ?? ''),
                (string) ($row['volunteer_name'] ?? ''),
                (string) ($row['volunteer_email'] ?? ''),
                self::format_phone_for_display((string) ($row['volunteer_phone'] ?? '')),
            ];
            foreach ($cells as $cell) {
                $html .= '<td style="border:1px solid #d1d5db;padding:6px;text-align:left;">' . esc_html($cell) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    public static function handle_export_event_hours_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_event_hours_csv');

        $event_id = absint($_POST['event_id'] ?? 0);
        $event_category = self::normalize_event_category(sanitize_text_field($_POST['event_category'] ?? ''));
        if (!$event_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=missing_event'));
            exit;
        }

        $rows = self::query_event_hours($event_id, $event_category);

        self::send_csv(self::build_export_filename('event-hours-' . $event_id, 'csv'), ['Volunteer', 'Email', 'Event Category', 'Hours Worked'], $rows, function ($row) {
            return [
                $row['name'] ?? '',
                $row['email'] ?? '',
                $row['event_category'] ?? '',
                isset($row['hours_worked']) ? number_format((float) $row['hours_worked'], 2, '.', '') : '0.00',
            ];
        });
    }

    public static function handle_export_assignment_report_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_assignment_report_csv');

        $event_id = absint($_POST['assignment_event_id'] ?? 0);
        if (!$event_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&report_tab=assignment-report&sc_error=missing_event'));
            exit;
        }

        $rows = self::query_assignment_report_rows($event_id);
        self::send_csv(self::build_export_filename('assignment-report-' . $event_id, 'csv'), ['Task', 'Start', 'End', 'Slot', 'Status', 'Volunteer', 'Email', 'Phone'], $rows, function ($row) {
            return [
                $row['task_title'] ?? '',
                self::format_datetime_cst_12h($row['task_start_datetime'] ?? ''),
                self::format_datetime_cst_12h($row['task_end_datetime'] ?? ''),
                (int) ($row['slot_number'] ?? 0),
                $row['status'] ?? '',
                $row['volunteer_name'] ?? '',
                $row['volunteer_email'] ?? '',
                self::format_phone_for_display($row['volunteer_phone'] ?? ''),
            ];
        });
    }

    public static function handle_export_assignment_report_excel() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_assignment_report_excel');

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&report_tab=assignment-report&sc_error=excel_library_missing'));
            exit;
        }

        $event_id = absint($_POST['assignment_event_id'] ?? 0);
        if (!$event_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&report_tab=assignment-report&sc_error=missing_event'));
            exit;
        }

        $rows = self::query_assignment_report_rows($event_id);
        self::send_excel(
            self::build_export_filename('assignment-report-' . $event_id, 'xlsx'),
            ['Task', 'Start', 'End', 'Slot', 'Status', 'Volunteer', 'Email', 'Phone'],
            $rows,
            function ($row) {
                return [
                    $row['task_title'] ?? '',
                    self::format_datetime_cst_12h($row['task_start_datetime'] ?? ''),
                    self::format_datetime_cst_12h($row['task_end_datetime'] ?? ''),
                    (int) ($row['slot_number'] ?? 0),
                    $row['status'] ?? '',
                    $row['volunteer_name'] ?? '',
                    $row['volunteer_email'] ?? '',
                    self::format_phone_for_display($row['volunteer_phone'] ?? ''),
                ];
            },
            self::build_export_meta_rows(
                __('Assignment Report', 'schedule-checkin'),
                [
                    __('Event', 'schedule-checkin') => self::event_title_by_id($event_id) ?: ('#' . (int) $event_id),
                ]
            )
        );
    }

    public static function handle_export_assignment_report_pdf() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_assignment_report_pdf');

        if (!class_exists('Dompdf\\Dompdf')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&report_tab=assignment-report&sc_error=pdf_library_missing'));
            exit;
        }

        $event_id = absint($_POST['assignment_event_id'] ?? 0);
        if (!$event_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&report_tab=assignment-report&sc_error=missing_event'));
            exit;
        }

        $rows = self::query_assignment_report_rows($event_id);
        self::render_pdf_view(
            __('Assignment Report', 'schedule-checkin'),
            ['Task', 'Start', 'End', 'Slot', 'Status', 'Volunteer', 'Email', 'Phone'],
            $rows,
            function ($row) {
                return [
                    $row['task_title'] ?? '',
                    self::format_datetime_cst_12h($row['task_start_datetime'] ?? ''),
                    self::format_datetime_cst_12h($row['task_end_datetime'] ?? ''),
                    (int) ($row['slot_number'] ?? 0),
                    $row['status'] ?? '',
                    $row['volunteer_name'] ?? '',
                    $row['volunteer_email'] ?? '',
                    self::format_phone_for_display($row['volunteer_phone'] ?? ''),
                ];
            },
            self::build_export_filename('assignment-report-' . $event_id, 'pdf'),
            [
                __('Event', 'schedule-checkin') => self::event_title_by_id($event_id) ?: ('#' . (int) $event_id),
            ]
        );
    }

    public static function handle_export_volunteer_hours_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_volunteer_hours_csv');

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        $period = sanitize_key($_POST['period'] ?? 'month');
        $event_category = self::normalize_event_category(sanitize_text_field($_POST['event_category'] ?? ''));
        if (!$volunteer_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=missing_volunteer'));
            exit;
        }

        $rows = self::query_volunteer_hours($volunteer_id, $period, $event_category);

        self::send_csv(self::build_export_filename('volunteer-hours-' . $volunteer_id . '-' . $period, 'csv', $volunteer_id), ['Event', 'Event Category', 'Check In', 'Check Out', 'Hours'], $rows, function ($row) {
            return [
                $row['title'] ?? '',
                $row['event_category'] ?? '',
                self::format_datetime_cst_12h($row['checked_in_at'] ?? ''),
                self::format_datetime_cst_12h($row['checked_out_at'] ?? ''),
                isset($row['hours_worked']) ? number_format((float) $row['hours_worked'], 2, '.', '') : '0.00',
            ];
        });
    }

    public static function handle_export_logs_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_logs_csv');

        $event_id = absint($_POST['event_id'] ?? 0);
        $event_category = self::normalize_event_category(sanitize_text_field($_POST['event_category'] ?? ''));

        $rows = self::query_logs($event_id, $event_category);

        self::send_csv(self::build_export_filename('checkin-logs' . ($event_id ? '-' . $event_id : ''), 'csv'), ['Date/Time', 'Volunteer', 'Type', 'Task', 'Event Category', 'Action', 'Source', 'Notes'], $rows, function ($row) {
            return [
                self::format_datetime_cst_12h($row['created_at'] ?? ''),
                $row['name'] ?? '',
                ucfirst((string) ($row['assignment_type'] ?? 'scheduled')),
                $row['task_title'] ?: __('No slot assignment', 'schedule-checkin'),
                $row['event_category'] ?? '',
                $row['action'] ?? '',
                $row['source'] ?? '',
                $row['notes'] ?? '',
            ];
        });
    }

    public static function handle_export_event_hours_excel() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_event_hours_excel');

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=excel_library_missing'));
            exit;
        }

        $event_id = absint($_POST['event_id'] ?? 0);
        $event_category = self::normalize_event_category(sanitize_text_field($_POST['event_category'] ?? ''));
        if (!$event_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=missing_event'));
            exit;
        }

        $rows = self::query_event_hours($event_id, $event_category);
        self::send_excel(self::build_export_filename('event-hours-' . $event_id, 'xlsx'), ['Volunteer', 'Email', 'Event Category', 'Hours Worked'], $rows, function ($row) {
            return [
                $row['name'] ?? '',
                $row['email'] ?? '',
                $row['event_category'] ?? '',
                isset($row['hours_worked']) ? number_format((float) $row['hours_worked'], 2, '.', '') : '0.00',
            ];
        }, self::build_export_meta_rows(
            __('Event Hours Report', 'schedule-checkin'),
            [
                __('Event', 'schedule-checkin') => self::event_title_by_id($event_id) ?: ('#' . (int) $event_id),
                __('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin'),
            ]
        ));
    }

    public static function handle_export_volunteer_hours_excel() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_volunteer_hours_excel');

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=excel_library_missing'));
            exit;
        }

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        $period = sanitize_key($_POST['period'] ?? 'month');
        $event_category = self::normalize_event_category(sanitize_text_field($_POST['event_category'] ?? ''));
        if (!$volunteer_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=missing_volunteer'));
            exit;
        }

        $rows = self::query_volunteer_hours($volunteer_id, $period, $event_category);
        self::send_excel(self::build_export_filename('volunteer-hours-' . $volunteer_id . '-' . $period, 'xlsx', $volunteer_id), ['Event', 'Event Category', 'Check In', 'Check Out', 'Hours'], $rows, function ($row) {
            return [
                $row['title'] ?? '',
                $row['event_category'] ?? '',
                self::format_datetime_cst_12h($row['checked_in_at'] ?? ''),
                self::format_datetime_cst_12h($row['checked_out_at'] ?? ''),
                isset($row['hours_worked']) ? number_format((float) $row['hours_worked'], 2, '.', '') : '0.00',
            ];
        }, self::build_export_meta_rows(
            __('Volunteer Hours Report', 'schedule-checkin'),
            [
                __('Volunteer', 'schedule-checkin') => self::volunteer_name_by_id($volunteer_id) ?: ('#' . (int) $volunteer_id),
                __('Period', 'schedule-checkin') => self::report_period_label($period),
                __('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin'),
            ]
        ));
    }

    public static function handle_export_logs_excel() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_logs_excel');

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-logs&sc_error=excel_library_missing'));
            exit;
        }

        $event_id = absint($_POST['event_id'] ?? 0);
        $event_category = self::normalize_event_category(sanitize_text_field($_POST['event_category'] ?? ''));
        $rows = self::query_logs($event_id, $event_category);
        self::send_excel(self::build_export_filename('checkin-logs' . ($event_id ? '-' . $event_id : ''), 'xlsx'), ['Date/Time', 'Volunteer', 'Type', 'Task', 'Event Category', 'Action', 'Source', 'Notes'], $rows, function ($row) {
            return [
                self::format_datetime_cst_12h($row['created_at'] ?? ''),
                $row['name'] ?? '',
                ucfirst((string) ($row['assignment_type'] ?? 'scheduled')),
                $row['task_title'] ?: __('No slot assignment', 'schedule-checkin'),
                $row['event_category'] ?? '',
                $row['action'] ?? '',
                $row['source'] ?? '',
                $row['notes'] ?? '',
            ];
        }, self::build_export_meta_rows(
            __('Check-In Logs', 'schedule-checkin'),
            [
                __('Event', 'schedule-checkin') => $event_id ? (self::event_title_by_id($event_id) ?: ('#' . (int) $event_id)) : __('All Events', 'schedule-checkin'),
                __('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin'),
            ]
        ));
    }

    public static function handle_export_event_hours_pdf() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_event_hours_pdf');

        if (!class_exists('Dompdf\\Dompdf')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=pdf_library_missing'));
            exit;
        }

        $event_id = absint($_POST['event_id'] ?? 0);
        $event_category = self::normalize_event_category(sanitize_text_field($_POST['event_category'] ?? ''));
        if (!$event_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=missing_event'));
            exit;
        }

        $rows = self::query_event_hours($event_id, $event_category);
        $event_title = self::event_title_by_id($event_id);
        self::render_pdf_view(
            __('Event Hours Report', 'schedule-checkin'),
            ['Volunteer', 'Email', 'Event Category', 'Hours Worked'],
            $rows,
            function ($row) {
                return [
                    $row['name'] ?? '',
                    $row['email'] ?? '',
                    $row['event_category'] ?? '',
                    isset($row['hours_worked']) ? number_format((float) $row['hours_worked'], 2, '.', '') : '0.00',
                ];
            },
            self::build_export_filename('event-hours-' . $event_id, 'pdf'),
            [
                __('Event', 'schedule-checkin') => $event_title !== '' ? $event_title : ('#' . (int) $event_id),
                __('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin'),
            ]
        );
    }

    public static function handle_export_volunteer_hours_pdf() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_volunteer_hours_pdf');

        if (!class_exists('Dompdf\\Dompdf')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=pdf_library_missing'));
            exit;
        }

        $volunteer_id = absint($_POST['volunteer_id'] ?? 0);
        $period = sanitize_key($_POST['period'] ?? 'month');
        $event_category = self::normalize_event_category(sanitize_text_field($_POST['event_category'] ?? ''));
        if (!$volunteer_id) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=missing_volunteer'));
            exit;
        }

        $rows = self::query_volunteer_hours($volunteer_id, $period, $event_category);
        $volunteer_name = self::volunteer_name_by_id($volunteer_id);
        self::render_pdf_view(
            __('Volunteer Hours Report', 'schedule-checkin'),
            ['Event', 'Event Category', 'Check In', 'Check Out', 'Hours'],
            $rows,
            function ($row) {
                return [
                    $row['title'] ?? '',
                    $row['event_category'] ?? '',
                    self::format_datetime_cst_12h($row['checked_in_at'] ?? ''),
                    self::format_datetime_cst_12h($row['checked_out_at'] ?? ''),
                    isset($row['hours_worked']) ? number_format((float) $row['hours_worked'], 2, '.', '') : '0.00',
                ];
            },
            self::build_export_filename('volunteer-hours-' . $volunteer_id . '-' . $period, 'pdf', $volunteer_id),
            [
                __('Volunteer', 'schedule-checkin') => $volunteer_name !== '' ? $volunteer_name : ('#' . (int) $volunteer_id),
                __('Period', 'schedule-checkin') => self::report_period_label($period),
                __('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin'),
            ]
        );
    }

    public static function handle_export_logs_pdf() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_logs_pdf');

        if (!class_exists('Dompdf\\Dompdf')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-logs&sc_error=pdf_library_missing'));
            exit;
        }

        $event_id = absint($_POST['event_id'] ?? 0);
        $event_category = self::normalize_event_category(sanitize_text_field($_POST['event_category'] ?? ''));
        $rows = self::query_logs($event_id, $event_category);
        $event_label = $event_id ? (self::event_title_by_id($event_id) ?: ('#' . (int) $event_id)) : __('All Events', 'schedule-checkin');
        self::render_pdf_view(
            __('Check-In Logs', 'schedule-checkin'),
            ['Date/Time', 'Volunteer', 'Type', 'Task', 'Event Category', 'Action', 'Source', 'Notes'],
            $rows,
            function ($row) {
                return [
                    self::format_datetime_cst_12h($row['created_at'] ?? ''),
                    $row['name'] ?? '',
                    ucfirst((string) ($row['assignment_type'] ?? 'scheduled')),
                    $row['task_title'] ?: __('No slot assignment', 'schedule-checkin'),
                    $row['event_category'] ?? '',
                    $row['action'] ?? '',
                    $row['source'] ?? '',
                    $row['notes'] ?? '',
                ];
            },
            self::build_export_filename('checkin-logs' . ($event_id ? '-' . $event_id : ''), 'pdf'),
            [
                __('Event', 'schedule-checkin') => $event_label,
                __('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin'),
            ]
        );
    }

    public static function handle_export_comm_logs_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_comm_logs_csv');

        $filters = [
            'event_id' => absint($_POST['comm_log_event_id'] ?? 0),
            'channel' => sanitize_key((string) ($_POST['comm_log_channel'] ?? '')),
            'status' => sanitize_key((string) ($_POST['comm_log_status'] ?? '')),
        ];
        $rows = self::query_communication_logs($filters);

        self::send_csv(self::build_export_filename('communication-logs', 'csv'), ['When', 'Volunteer', 'Event', 'Channel', 'Purpose', 'Recipient', 'Status', 'Error'], $rows, function ($row) {
            return [
                self::format_datetime_cst_12h($row['created_at'] ?? ''),
                $row['volunteer_name'] ?? '',
                $row['event_title'] ?? '',
                strtoupper((string) ($row['channel'] ?? '')),
                $row['purpose'] ?? '',
                $row['recipient'] ?? '',
                $row['status'] ?? '',
                $row['error_message'] ?? '',
            ];
        });
    }

    public static function handle_export_comm_logs_excel() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_comm_logs_excel');

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_error=excel_library_missing'));
            exit;
        }

        $filters = [
            'event_id' => absint($_POST['comm_log_event_id'] ?? 0),
            'channel' => sanitize_key((string) ($_POST['comm_log_channel'] ?? '')),
            'status' => sanitize_key((string) ($_POST['comm_log_status'] ?? '')),
        ];
        $rows = self::query_communication_logs($filters);
        self::send_excel(
            self::build_export_filename('communication-logs', 'xlsx'),
            ['When', 'Volunteer', 'Event', 'Channel', 'Purpose', 'Recipient', 'Status', 'Error'],
            $rows,
            function ($row) {
                return [
                    self::format_datetime_cst_12h($row['created_at'] ?? ''),
                    $row['volunteer_name'] ?? '',
                    $row['event_title'] ?? '',
                    strtoupper((string) ($row['channel'] ?? '')),
                    $row['purpose'] ?? '',
                    $row['recipient'] ?? '',
                    $row['status'] ?? '',
                    $row['error_message'] ?? '',
                ];
            },
            self::build_export_meta_rows(__('Communication Log', 'schedule-checkin'), [
                __('Event', 'schedule-checkin') => !empty($filters['event_id']) ? (self::event_title_by_id((int) $filters['event_id']) ?: ('#' . (int) $filters['event_id'])) : __('All Events', 'schedule-checkin'),
                __('Channel', 'schedule-checkin') => !empty($filters['channel']) ? strtoupper((string) $filters['channel']) : __('All Channels', 'schedule-checkin'),
                __('Status', 'schedule-checkin') => !empty($filters['status']) ? (string) $filters['status'] : __('All Statuses', 'schedule-checkin'),
            ])
        );
    }

    public static function handle_export_comm_logs_pdf() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_comm_logs_pdf');

        if (!class_exists('Dompdf\\Dompdf')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-communications&sc_error=pdf_library_missing'));
            exit;
        }

        $filters = [
            'event_id' => absint($_POST['comm_log_event_id'] ?? 0),
            'channel' => sanitize_key((string) ($_POST['comm_log_channel'] ?? '')),
            'status' => sanitize_key((string) ($_POST['comm_log_status'] ?? '')),
        ];
        $rows = self::query_communication_logs($filters);
        self::render_pdf_view(
            __('Communication Log', 'schedule-checkin'),
            ['When', 'Volunteer', 'Event', 'Channel', 'Purpose', 'Recipient', 'Status', 'Error'],
            $rows,
            function ($row) {
                return [
                    self::format_datetime_cst_12h($row['created_at'] ?? ''),
                    $row['volunteer_name'] ?? '',
                    $row['event_title'] ?? '',
                    strtoupper((string) ($row['channel'] ?? '')),
                    $row['purpose'] ?? '',
                    $row['recipient'] ?? '',
                    $row['status'] ?? '',
                    $row['error_message'] ?? '',
                ];
            },
            self::build_export_filename('communication-logs', 'pdf'),
            [
                __('Event', 'schedule-checkin') => !empty($filters['event_id']) ? (self::event_title_by_id((int) $filters['event_id']) ?: ('#' . (int) $filters['event_id'])) : __('All Events', 'schedule-checkin'),
                __('Channel', 'schedule-checkin') => !empty($filters['channel']) ? strtoupper((string) $filters['channel']) : __('All Channels', 'schedule-checkin'),
                __('Status', 'schedule-checkin') => !empty($filters['status']) ? (string) $filters['status'] : __('All Statuses', 'schedule-checkin'),
            ]
        );
    }

    public static function handle_export_advanced_report_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_advanced_report_csv');

        $report = self::get_advanced_report_payload(
            sanitize_key($_POST['report_key'] ?? ''),
            [
                'coverage_event_id' => absint($_POST['coverage_event_id'] ?? 0),
                'substitute_event_id' => absint($_POST['substitute_event_id'] ?? 0),
                'substitute_history_event_id' => absint($_POST['substitute_history_event_id'] ?? 0),
                'sms_period' => sanitize_key($_POST['sms_period'] ?? 'month'),
                'sms_start_date' => sanitize_text_field($_POST['sms_start_date'] ?? ''),
                'sms_end_date' => sanitize_text_field($_POST['sms_end_date'] ?? ''),
                'event_category' => sanitize_text_field($_POST['event_category'] ?? ''),
                'volunteers_event_ids' => self::sanitize_id_list($_POST['volunteers_event_ids'] ?? []),
                'volunteers_event_categories' => self::sanitize_event_category_list($_POST['volunteers_event_categories'] ?? []),
                'volunteers_volunteer_id' => absint($_POST['volunteers_volunteer_id'] ?? 0),
                'volunteers_is_active' => self::sanitize_volunteer_active_filter($_POST['volunteers_is_active'] ?? 'all'),
                'volunteers_period' => self::sanitize_volunteers_period($_POST['volunteers_period'] ?? 'all-time'),
                'volunteers_start_date' => self::sanitize_date_yyyy_mm_dd($_POST['volunteers_start_date'] ?? ''),
                'volunteers_end_date' => self::sanitize_date_yyyy_mm_dd($_POST['volunteers_end_date'] ?? ''),
            ]
        );
        self::send_csv(self::build_export_filename($report['slug'], 'csv'), $report['headers'], $report['rows'], $report['map']);
    }

    public static function handle_export_advanced_report_excel() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_advanced_report_excel');

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=excel_library_missing'));
            exit;
        }

        $report = self::get_advanced_report_payload(
            sanitize_key($_POST['report_key'] ?? ''),
            [
                'coverage_event_id' => absint($_POST['coverage_event_id'] ?? 0),
                'substitute_event_id' => absint($_POST['substitute_event_id'] ?? 0),
                'substitute_history_event_id' => absint($_POST['substitute_history_event_id'] ?? 0),
                'sms_period' => sanitize_key($_POST['sms_period'] ?? 'month'),
                'sms_start_date' => sanitize_text_field($_POST['sms_start_date'] ?? ''),
                'sms_end_date' => sanitize_text_field($_POST['sms_end_date'] ?? ''),
                'event_category' => sanitize_text_field($_POST['event_category'] ?? ''),
                'volunteers_event_ids' => self::sanitize_id_list($_POST['volunteers_event_ids'] ?? []),
                'volunteers_event_categories' => self::sanitize_event_category_list($_POST['volunteers_event_categories'] ?? []),
                'volunteers_volunteer_id' => absint($_POST['volunteers_volunteer_id'] ?? 0),
                'volunteers_is_active' => self::sanitize_volunteer_active_filter($_POST['volunteers_is_active'] ?? 'all'),
                'volunteers_period' => self::sanitize_volunteers_period($_POST['volunteers_period'] ?? 'all-time'),
                'volunteers_start_date' => self::sanitize_date_yyyy_mm_dd($_POST['volunteers_start_date'] ?? ''),
                'volunteers_end_date' => self::sanitize_date_yyyy_mm_dd($_POST['volunteers_end_date'] ?? ''),
            ]
        );
        $excel_meta_rows = !empty($report['excel_meta_rows']) && is_array($report['excel_meta_rows'])
            ? $report['excel_meta_rows']
            : self::build_export_meta_rows($report['title'], $report['pdf_filters'] ?? []);
        self::send_excel(
            self::build_export_filename($report['slug'], 'xlsx'),
            $report['headers'],
            $report['rows'],
            $report['map'],
            $excel_meta_rows
        );
    }

    public static function handle_export_advanced_report_pdf() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'schedule-checkin'));
        }
        check_admin_referer('sc_export_advanced_report_pdf');

        if (!class_exists('Dompdf\\Dompdf')) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=pdf_library_missing'));
            exit;
        }

        $report = self::get_advanced_report_payload(
            sanitize_key($_POST['report_key'] ?? ''),
            [
                'coverage_event_id' => absint($_POST['coverage_event_id'] ?? 0),
                'substitute_event_id' => absint($_POST['substitute_event_id'] ?? 0),
                'substitute_history_event_id' => absint($_POST['substitute_history_event_id'] ?? 0),
                'sms_period' => sanitize_key($_POST['sms_period'] ?? 'month'),
                'sms_start_date' => sanitize_text_field($_POST['sms_start_date'] ?? ''),
                'sms_end_date' => sanitize_text_field($_POST['sms_end_date'] ?? ''),
                'event_category' => sanitize_text_field($_POST['event_category'] ?? ''),
                'volunteers_event_ids' => self::sanitize_id_list($_POST['volunteers_event_ids'] ?? []),
                'volunteers_event_categories' => self::sanitize_event_category_list($_POST['volunteers_event_categories'] ?? []),
                'volunteers_volunteer_id' => absint($_POST['volunteers_volunteer_id'] ?? 0),
                'volunteers_is_active' => self::sanitize_volunteer_active_filter($_POST['volunteers_is_active'] ?? 'all'),
                'volunteers_period' => self::sanitize_volunteers_period($_POST['volunteers_period'] ?? 'all-time'),
                'volunteers_start_date' => self::sanitize_date_yyyy_mm_dd($_POST['volunteers_start_date'] ?? ''),
                'volunteers_end_date' => self::sanitize_date_yyyy_mm_dd($_POST['volunteers_end_date'] ?? ''),
            ]
        );
        self::render_pdf_view(
            $report['title'],
            $report['headers'],
            $report['rows'],
            $report['map'],
            self::build_export_filename($report['slug'], 'pdf'),
            $report['pdf_filters'] ?? []
        );
    }

    private static function query_event_hours($event_id, $event_category = '') {
        $summaries = self::get_assignment_activity_summaries([
            'event_id' => (int) $event_id,
            'event_category' => self::normalize_event_category($event_category),
        ]);
        $by_volunteer = [];

        foreach ($summaries as $summary) {
            $minutes = (int) ($summary['total_minutes'] ?? 0);
            if ($minutes <= 0) {
                continue;
            }

            $volunteer_id = (int) ($summary['volunteer_id'] ?? 0);
            if (!isset($by_volunteer[$volunteer_id])) {
                $by_volunteer[$volunteer_id] = [
                    'name' => (string) ($summary['name'] ?? ''),
                    'email' => (string) ($summary['email'] ?? ''),
                    'event_category' => (string) ($summary['event_category'] ?? ''),
                    'hours_worked' => 0,
                ];
            }
            $by_volunteer[$volunteer_id]['hours_worked'] += ($minutes / 60);
        }

        $rows = array_values($by_volunteer);
        usort($rows, static function ($left, $right) {
            return ($right['hours_worked'] <=> $left['hours_worked']);
        });

        return $rows;
    }

    private static function query_volunteer_hours($volunteer_id, $period, $event_category = '') {
        $volunteer_id = (int) $volunteer_id;
        if ($period === 'all') {
            $period_start = '';
        } else {
            $period_start = self::period_start($period);
        }

        $summaries = self::get_assignment_activity_summaries([
            'volunteer_id' => $volunteer_id,
            'period_start' => $period_start,
            'event_category' => self::normalize_event_category($event_category),
        ]);

        $rows = [];
        foreach ($summaries as $summary) {
            $minutes = (int) ($summary['total_minutes'] ?? 0);
            if ($minutes <= 0) {
                continue;
            }

            $rows[] = [
                'title' => (string) ($summary['event_title'] ?? ''),
                'event_category' => (string) ($summary['event_category'] ?? ''),
                'checked_in_at' => (string) ($summary['checked_in_at'] ?? ''),
                'checked_out_at' => (string) ($summary['checked_out_at'] ?? ''),
                'hours_worked' => ($minutes / 60),
            ];
        }

        usort($rows, static function ($left, $right) {
            return strcmp((string) ($right['checked_in_at'] ?? ''), (string) ($left['checked_in_at'] ?? ''));
        });

        return $rows;
    }

    private static function query_assignment_report_rows($event_id) {
        global $wpdb;
        $t = self::tables();
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return [];
        }

        $tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, start_datetime, end_datetime, slots
             FROM {$t['tasks']}
             WHERE event_id = %d
             ORDER BY start_datetime ASC, id ASC",
            $event_id
        ));
        if (empty($tasks)) {
            return [];
        }

        $assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT a.task_id, a.slot_number, v.name, v.email, v.phone
             FROM {$t['assignments']} a
             LEFT JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
             WHERE a.event_id = %d
               AND a.task_id IS NOT NULL
               AND a.status <> 'substitute_pool'",
            $event_id
        ));

        $assignment_map = [];
        foreach ((array) $assignments as $assignment) {
            $task_id = (int) ($assignment->task_id ?? 0);
            $slot_number = (int) ($assignment->slot_number ?? 0);
            if ($task_id <= 0 || $slot_number <= 0) {
                continue;
            }
            $assignment_map[$task_id . ':' . $slot_number] = [
                'volunteer_name' => (string) ($assignment->name ?? ''),
                'volunteer_email' => (string) ($assignment->email ?? ''),
                'volunteer_phone' => (string) ($assignment->phone ?? ''),
            ];
        }

        $rows = [];
        foreach ($tasks as $task) {
            $slot_total = max(1, (int) ($task->slots ?? 1));
            for ($slot = 1; $slot <= $slot_total; $slot++) {
                $key = (int) $task->id . ':' . $slot;
                $assignment = $assignment_map[$key] ?? null;
                $rows[] = [
                    'task_title' => (string) ($task->title ?? ''),
                    'task_start_datetime' => (string) ($task->start_datetime ?? ''),
                    'task_end_datetime' => (string) ($task->end_datetime ?? ''),
                    'slot_number' => $slot,
                    'status' => $assignment ? __('Scheduled', 'schedule-checkin') : __('Open', 'schedule-checkin'),
                    'volunteer_name' => $assignment['volunteer_name'] ?? '',
                    'volunteer_email' => $assignment['volunteer_email'] ?? '',
                    'volunteer_phone' => $assignment['volunteer_phone'] ?? '',
                ];
            }
        }

        return $rows;
    }

    private static function query_logs($event_id = 0, $event_category = '') {
        global $wpdb;
        $t = self::tables();
        $event_category = self::normalize_event_category($event_category);

        $where = [];
        $params = [];
        if ($event_id) {
            $where[] = 'c.event_id = %d';
            $params[] = (int) $event_id;
        }
        if ($event_category !== '') {
            $where[] = 'e.event_category = %s';
            $params[] = $event_category;
        }

        $where_sql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));
        $sql = "SELECT c.created_at, v.name, COALESCE(a.assignment_type, 'scheduled') AS assignment_type, t.title AS task_title, e.event_category, c.action, c.source, c.notes
                FROM {$t['checkins']} c
                INNER JOIN {$t['volunteers']} v ON v.id = c.volunteer_id
                INNER JOIN {$t['events']} e ON e.id = c.event_id
                LEFT JOIN {$t['assignments']} a ON a.id = c.assignment_id
                LEFT JOIN {$t['tasks']} t ON t.id = c.task_id
                {$where_sql}
                ORDER BY c.created_at DESC";

        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    private static function query_communication_logs($filters = [], $limit = 0, $offset = 0, $count_only = false) {
        global $wpdb;
        $t = self::tables();

        $event_id = absint($filters['event_id'] ?? 0);
        $channel = sanitize_key((string) ($filters['channel'] ?? ''));
        $status = sanitize_key((string) ($filters['status'] ?? ''));

        $where = ['1=1'];
        $params = [];
        if ($event_id > 0) {
            $where[] = 'l.event_id = %d';
            $params[] = $event_id;
        }
        if (in_array($channel, ['email', 'sms'], true)) {
            $where[] = 'l.channel = %s';
            $params[] = $channel;
        }
        if ($status !== '') {
            $where[] = 'l.status = %s';
            $params[] = $status;
        }

        $where_sql = implode(' AND ', $where);
        if ($count_only) {
            $sql = "SELECT COUNT(*) FROM {$t['comm_logs']} l WHERE {$where_sql}";
            if (!empty($params)) {
                $sql = $wpdb->prepare($sql, ...$params);
            }
            return (int) $wpdb->get_var($sql);
        }

        $sql = "SELECT l.*, v.name AS volunteer_name, e.title AS event_title
                FROM {$t['comm_logs']} l
                LEFT JOIN {$t['volunteers']} v ON v.id = l.volunteer_id
                LEFT JOIN {$t['events']} e ON e.id = l.event_id
                WHERE {$where_sql}
                ORDER BY l.created_at DESC";
        if ($limit > 0) {
            $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', absint($limit), absint($offset));
        }
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    private static function query_lifetime_hours($event_category = '') {
        $summaries = self::get_assignment_activity_summaries([
            'event_category' => self::normalize_event_category($event_category),
        ]);
        $by_volunteer = [];

        foreach ($summaries as $summary) {
            $minutes = (int) ($summary['total_minutes'] ?? 0);
            if ($minutes <= 0) {
                continue;
            }

            $volunteer_id = (int) ($summary['volunteer_id'] ?? 0);
            if (!isset($by_volunteer[$volunteer_id])) {
                $by_volunteer[$volunteer_id] = [
                    'name' => (string) ($summary['name'] ?? ''),
                    'email' => (string) ($summary['email'] ?? ''),
                    'hours_worked' => 0,
                ];
            }
            $by_volunteer[$volunteer_id]['hours_worked'] += ($minutes / 60);
        }

        $rows = array_values($by_volunteer);
        usort($rows, static function ($left, $right) {
            return ($right['hours_worked'] <=> $left['hours_worked']);
        });
        $rows = array_slice($rows, 0, 50);

        return array_map(static function ($row) {
            return (object) $row;
        }, $rows);
    }

    private static function query_attendance_reliability($event_category = '') {
        global $wpdb;
        $t = self::tables();
        $event_category = self::normalize_event_category($event_category);

        if ($event_category !== '') {
            $scheduled_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT a.id, a.volunteer_id, v.name
                 FROM {$t['assignments']} a
                 INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
                 INNER JOIN {$t['events']} e ON e.id = a.event_id
                 WHERE e.event_category = %s
                 ORDER BY v.name ASC",
                $event_category
            ));
        } else {
            $scheduled_rows = $wpdb->get_results(
                "SELECT a.id, a.volunteer_id, v.name
                 FROM {$t['assignments']} a
                 INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
                 ORDER BY v.name ASC"
            );
        }

        $summaries = self::get_assignment_activity_summaries([
            'event_category' => $event_category,
        ]);
        $summary_by_assignment = [];
        foreach ($summaries as $summary) {
            $summary_by_assignment[(int) $summary['assignment_id']] = $summary;
        }

        $by_volunteer = [];
        foreach ($scheduled_rows as $row) {
            $volunteer_id = (int) $row->volunteer_id;
            if (!isset($by_volunteer[$volunteer_id])) {
                $by_volunteer[$volunteer_id] = [
                    'name' => (string) ($row->name ?? ''),
                    'scheduled_count' => 0,
                    'checked_in_count' => 0,
                    'no_show_count' => 0,
                ];
            }

            $by_volunteer[$volunteer_id]['scheduled_count']++;
            $summary = $summary_by_assignment[(int) $row->id] ?? null;
            $has_checkin = !empty($summary['has_checkin']);
            if ($has_checkin) {
                $by_volunteer[$volunteer_id]['checked_in_count']++;
            } else {
                $by_volunteer[$volunteer_id]['no_show_count']++;
            }
        }

        $rows = array_values($by_volunteer);
        usort($rows, static function ($left, $right) {
            $scheduled_cmp = ((int) $right['scheduled_count']) <=> ((int) $left['scheduled_count']);
            if ($scheduled_cmp !== 0) {
                return $scheduled_cmp;
            }
            return strcmp((string) $left['name'], (string) $right['name']);
        });
        $rows = array_slice($rows, 0, 100);

        return array_map(static function ($row) {
            return (object) $row;
        }, $rows);
    }

    private static function query_late_early_analysis($event_category = '') {
        $summaries = self::get_assignment_activity_summaries([
            'event_category' => self::normalize_event_category($event_category),
        ]);
        $by_volunteer = [];

        foreach ($summaries as $summary) {
            $checkin_at = (string) ($summary['checked_in_at'] ?? '');
            $checkout_at = (string) ($summary['checked_out_at'] ?? '');
            $task_start = (string) ($summary['task_start_datetime'] ?? '');
            $task_end = (string) ($summary['task_end_datetime'] ?? '');
            if ($checkin_at === '' || $checkout_at === '' || $task_start === '' || $task_end === '') {
                continue;
            }

            $volunteer_id = (int) ($summary['volunteer_id'] ?? 0);
            if (!isset($by_volunteer[$volunteer_id])) {
                $by_volunteer[$volunteer_id] = [
                    'name' => (string) ($summary['name'] ?? ''),
                    'late_total' => 0,
                    'early_total' => 0,
                    'count' => 0,
                    'late_arrivals' => 0,
                    'early_leaves' => 0,
                ];
            }

            $late_minutes = max(0, (int) floor((strtotime($checkin_at) - strtotime($task_start)) / 60));
            $early_leave_minutes = max(0, (int) floor((strtotime($task_end) - strtotime($checkout_at)) / 60));

            $by_volunteer[$volunteer_id]['late_total'] += $late_minutes;
            $by_volunteer[$volunteer_id]['early_total'] += $early_leave_minutes;
            $by_volunteer[$volunteer_id]['count']++;
            if ($late_minutes > 0) {
                $by_volunteer[$volunteer_id]['late_arrivals']++;
            }
            if ($early_leave_minutes > 0) {
                $by_volunteer[$volunteer_id]['early_leaves']++;
            }
        }

        $rows = [];
        foreach ($by_volunteer as $row) {
            $count = max(1, (int) $row['count']);
            $rows[] = (object) [
                'name' => $row['name'],
                'avg_late_min' => round(((float) $row['late_total']) / $count, 1),
                'avg_early_leave_min' => round(((float) $row['early_total']) / $count, 1),
                'late_arrivals' => (int) $row['late_arrivals'],
                'early_leaves' => (int) $row['early_leaves'],
            ];
        }

        usort($rows, static function ($left, $right) {
            $late_cmp = ((float) $right->avg_late_min) <=> ((float) $left->avg_late_min);
            if ($late_cmp !== 0) {
                return $late_cmp;
            }
            return ((int) $right->late_arrivals) <=> ((int) $left->late_arrivals);
        });

        return array_slice($rows, 0, 100);
    }

    private static function query_event_role_breakdown($event_id) {
        global $wpdb;
        $t = self::tables();
        $allow_substitutes = self::event_allows_substitutes($event_id);

        $assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT id, assignment_type FROM {$t['assignments']} WHERE event_id = %d",
            $event_id
        ));
        $summaries = self::get_assignment_activity_summaries(['event_id' => (int) $event_id]);
        $summary_by_assignment = [];
        foreach ($summaries as $summary) {
            $summary_by_assignment[(int) $summary['assignment_id']] = $summary;
        }

        $by_type = [];
        foreach ($assignments as $assignment) {
            $type = (string) ($assignment->assignment_type ?: 'scheduled');
            if (!$allow_substitutes && $type === 'substitute') {
                continue;
            }
            if (!isset($by_type[$type])) {
                $by_type[$type] = [
                    'assignment_type' => $type,
                    'total_count' => 0,
                    'checked_in_count' => 0,
                    'completed_count' => 0,
                ];
            }

            $by_type[$type]['total_count']++;
            $summary = $summary_by_assignment[(int) $assignment->id] ?? null;
            if (!empty($summary['has_checkin'])) {
                $by_type[$type]['checked_in_count']++;
            }
            if (!empty($summary['checked_out_at']) && (string) ($summary['last_action'] ?? '') === 'checkout') {
                $by_type[$type]['completed_count']++;
            }
        }

        ksort($by_type);
        return array_map(static function ($row) {
            return (object) $row;
        }, array_values($by_type));
    }

    private static function get_assignment_activity_summaries($filters = []) {
        global $wpdb;
        $t = self::tables();

        $where = ['c.assignment_id IS NOT NULL'];
        $params = [];

        if (!empty($filters['event_id'])) {
            $where[] = 'a.event_id = %d';
            $params[] = (int) $filters['event_id'];
        }
        if (!empty($filters['event_ids']) && is_array($filters['event_ids'])) {
            $event_ids = self::sanitize_id_list($filters['event_ids']);
            if (!empty($event_ids)) {
                $event_placeholders = implode(', ', array_fill(0, count($event_ids), '%d'));
                $where[] = "a.event_id IN ({$event_placeholders})";
                $params = array_merge($params, $event_ids);
            }
        }
        if (!empty($filters['volunteer_id'])) {
            $where[] = 'a.volunteer_id = %d';
            $params[] = (int) $filters['volunteer_id'];
        }
        if (!empty($filters['event_category'])) {
            $where[] = 'e.event_category = %s';
            $params[] = self::normalize_event_category((string) $filters['event_category']);
        }
        if (!empty($filters['event_categories']) && is_array($filters['event_categories'])) {
            $event_categories = self::sanitize_event_category_list($filters['event_categories']);
            if (!empty($event_categories)) {
                $category_placeholders = implode(', ', array_fill(0, count($event_categories), '%s'));
                $where[] = "e.event_category IN ({$category_placeholders})";
                $params = array_merge($params, $event_categories);
            }
        }

        $sql = "SELECT c.id, c.assignment_id, c.task_id, c.action, c.created_at,
                       a.event_id, a.volunteer_id, a.assignment_type,
                   e.title AS event_title, e.event_category,
                       t.start_datetime AS task_start_datetime,
                       t.end_datetime AS task_end_datetime,
                       v.name, v.email
                FROM {$t['checkins']} c
                INNER JOIN {$t['assignments']} a ON a.id = c.assignment_id
                INNER JOIN {$t['events']} e ON e.id = a.event_id
                LEFT JOIN {$t['tasks']} t ON t.id = a.task_id
                INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY c.assignment_id ASC, c.created_at ASC, c.id ASC";

        if (!empty($params)) {
            $logs = $wpdb->get_results($wpdb->prepare($sql, $params));
        } else {
            $logs = $wpdb->get_results($sql);
        }

        $period_start = isset($filters['period_start']) ? (string) $filters['period_start'] : '';
        $period_end = isset($filters['period_end']) ? (string) $filters['period_end'] : '';
        $rows = [];
        foreach ($logs as $log) {
            $assignment_id = (int) $log->assignment_id;
            if (!isset($rows[$assignment_id])) {
                $rows[$assignment_id] = [
                    'assignment_id' => $assignment_id,
                    'event_id' => (int) $log->event_id,
                    'volunteer_id' => (int) $log->volunteer_id,
                    'task_id' => isset($log->task_id) ? (int) $log->task_id : 0,
                    'assignment_type' => (string) ($log->assignment_type ?: 'scheduled'),
                    'event_title' => (string) ($log->event_title ?? ''),
                    'event_category' => (string) ($log->event_category ?? ''),
                    'task_start_datetime' => (string) ($log->task_start_datetime ?? ''),
                    'task_end_datetime' => (string) ($log->task_end_datetime ?? ''),
                    'name' => (string) ($log->name ?? ''),
                    'email' => (string) ($log->email ?? ''),
                    'checked_in_at' => '',
                    'checked_out_at' => '',
                    'last_action' => '',
                    'has_checkin' => false,
                    'is_checked_in' => false,
                    'total_minutes' => 0,
                    '_open_checkin_at' => '',
                ];
            }

            $action = (string) ($log->action ?? '');
            $created_at = (string) ($log->created_at ?? '');

            if ($action === 'checkin') {
                if ($rows[$assignment_id]['checked_in_at'] === '') {
                    $rows[$assignment_id]['checked_in_at'] = $created_at;
                }
                $rows[$assignment_id]['has_checkin'] = true;
                if ($rows[$assignment_id]['_open_checkin_at'] === '') {
                    $rows[$assignment_id]['_open_checkin_at'] = $created_at;
                }
            } elseif ($action === 'checkout') {
                $open_checkin_at = (string) $rows[$assignment_id]['_open_checkin_at'];
                if ($open_checkin_at !== '') {
                    $minutes = (int) floor((strtotime($created_at) - strtotime($open_checkin_at)) / 60);
                    if ($minutes > 0) {
                        $rows[$assignment_id]['total_minutes'] += $minutes;
                    }
                    $rows[$assignment_id]['_open_checkin_at'] = '';
                    $rows[$assignment_id]['checked_out_at'] = $created_at;
                }
            }

            $rows[$assignment_id]['last_action'] = $action;
        }

        $result = [];
        foreach ($rows as $row) {
            $row['is_checked_in'] = ((string) ($row['last_action'] ?? '') === 'checkin');
            unset($row['_open_checkin_at']);

            if ($period_start !== '' && (string) ($row['checked_in_at'] ?? '') !== '' && strcmp((string) $row['checked_in_at'], $period_start) < 0) {
                continue;
            }
            if ($period_end !== '' && (string) ($row['checked_in_at'] ?? '') !== '' && strcmp((string) $row['checked_in_at'], $period_end) > 0) {
                continue;
            }
            $result[] = $row;
        }

        return $result;
    }

    private static function query_task_coverage_health($event_id = 0, $event_category = '') {
        global $wpdb;
        $t = self::tables();
        $event_category = self::normalize_event_category($event_category);

        if ($event_id && $event_category !== '') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT e.title AS event_title,
                    e.event_category,
                        t.title AS task_title,
                        t.start_datetime,
                        t.slots,
                        COUNT(a.id) AS filled_slots,
                        ROUND((COUNT(a.id) / t.slots) * 100, 1) AS fill_rate
                 FROM {$t['tasks']} t
                 INNER JOIN {$t['events']} e ON e.id = t.event_id
                 LEFT JOIN {$t['assignments']} a ON a.task_id = t.id
                 WHERE t.event_id = %d AND e.event_category = %s
                 GROUP BY t.id
                 ORDER BY fill_rate ASC, t.start_datetime DESC
                 LIMIT 150",
                $event_id,
                $event_category
            ));
        }

        if ($event_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT e.title AS event_title,
                    e.event_category,
                        t.title AS task_title,
                        t.start_datetime,
                        t.slots,
                        COUNT(a.id) AS filled_slots,
                        ROUND((COUNT(a.id) / t.slots) * 100, 1) AS fill_rate
                 FROM {$t['tasks']} t
                 INNER JOIN {$t['events']} e ON e.id = t.event_id
                 LEFT JOIN {$t['assignments']} a ON a.task_id = t.id
                 WHERE t.event_id = %d
                 GROUP BY t.id
                 ORDER BY fill_rate ASC, t.start_datetime DESC
                 LIMIT 150",
                $event_id
            ));
        }

        if ($event_category !== '') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT e.title AS event_title,
                    e.event_category,
                        t.title AS task_title,
                        t.start_datetime,
                        t.slots,
                        COUNT(a.id) AS filled_slots,
                        ROUND((COUNT(a.id) / t.slots) * 100, 1) AS fill_rate
                 FROM {$t['tasks']} t
                 INNER JOIN {$t['events']} e ON e.id = t.event_id
                 LEFT JOIN {$t['assignments']} a ON a.task_id = t.id
                 WHERE e.event_category = %s
                 GROUP BY t.id
                 ORDER BY fill_rate ASC, t.start_datetime DESC
                 LIMIT 150",
                $event_category
            ));
        }

        return $wpdb->get_results(
                "SELECT e.title AS event_title,
                    e.event_category,
                    t.title AS task_title,
                    t.start_datetime,
                    t.slots,
                    COUNT(a.id) AS filled_slots,
                    ROUND((COUNT(a.id) / t.slots) * 100, 1) AS fill_rate
             FROM {$t['tasks']} t
             INNER JOIN {$t['events']} e ON e.id = t.event_id
             LEFT JOIN {$t['assignments']} a ON a.task_id = t.id
             GROUP BY t.id
             ORDER BY fill_rate ASC, t.start_datetime DESC
             LIMIT 150"
        );
    }

    private static function query_checkin_method_audit($event_category = '') {
        global $wpdb;
        $t = self::tables();

        $event_category = self::normalize_event_category($event_category);
        if ($event_category !== '') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT c.source,
                        SUM(CASE WHEN c.action = 'checkin' THEN 1 ELSE 0 END) AS checkin_count,
                        SUM(CASE WHEN c.action = 'checkout' THEN 1 ELSE 0 END) AS checkout_count,
                        COUNT(*) AS total_actions
                 FROM {$t['checkins']} c
                 INNER JOIN {$t['events']} e ON e.id = c.event_id
                 WHERE e.event_category = %s
                 GROUP BY c.source
                 ORDER BY total_actions DESC",
                $event_category
            ));
        }

        return $wpdb->get_results(
            "SELECT source,
                    SUM(CASE WHEN action = 'checkin' THEN 1 ELSE 0 END) AS checkin_count,
                    SUM(CASE WHEN action = 'checkout' THEN 1 ELSE 0 END) AS checkout_count,
                    COUNT(*) AS total_actions
             FROM {$t['checkins']}
             GROUP BY source
             ORDER BY total_actions DESC"
        );
    }

    private static function query_sms_analytics($period = 'month', $start_date = '', $end_date = '', $event_category = '') {
        global $wpdb;
        $t = self::tables();

        $period = in_array($period, ['month', 'year', 'date-range', 'all'], true) ? $period : 'month';
        $event_category = self::normalize_event_category($event_category);
        $where_clauses = ["l.channel = 'sms'", "l.status = 'sent'"];
        $params = [];
        $from_sql = "{$t['comm_logs']} l";

        if ($event_category !== '') {
            $from_sql .= " INNER JOIN {$t['events']} e ON e.id = l.event_id";
            $where_clauses[] = 'e.event_category = %s';
            $params[] = $event_category;
        }

        if ($period === 'month') {
            $where_clauses[] = 'l.created_at >= %s';
            $params[] = gmdate('Y-m-d H:i:s', strtotime('-1 month', current_time('timestamp')));
        } elseif ($period === 'year') {
            $where_clauses[] = 'l.created_at >= %s';
            $params[] = gmdate('Y-m-d H:i:s', strtotime('-1 year', current_time('timestamp')));
        } elseif ($period === 'date-range') {
            if ($start_date !== '') {
                $where_clauses[] = 'l.created_at >= %s';
                $params[] = $start_date . ' 00:00:00';
            }
            if ($end_date !== '') {
                $where_clauses[] = 'l.created_at <= %s';
                $params[] = $end_date . ' 23:59:59';
            }
        }

        $where_sql = implode(' AND ', $where_clauses);
        $select_sql = 'l.created_at, l.recipient, l.purpose, l.message, e.title AS event_title, e.event_category';
        if ($event_category === '') {
            $from_sql .= " LEFT JOIN {$t['events']} e ON e.id = l.event_id";
        }
        $order_by = 'l.created_at';
        $sql = "SELECT {$select_sql} FROM {$from_sql} WHERE {$where_sql} ORDER BY {$order_by} DESC LIMIT 2000";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        $logs = $wpdb->get_results($sql);
        $gsm_rate = (float) self::get_plugin_setting('sc_twilio_sms_cost_gsm_segment', '0');
        $unicode_rate = (float) self::get_plugin_setting('sc_twilio_sms_cost_unicode_segment', '0');

        $rows = [];
        $total_sms = 0;
        $total_chars = 0;
        $total_segments = 0;
        $total_cost = 0.0;

        foreach ($logs as $log) {
            $segment = self::sms_segment_info((string) ($log->message ?? ''));
            $encoding = (string) $segment['encoding'];
            $segments = (int) $segment['segments'];
            $char_count = (int) $segment['char_count'];
            $rate = $encoding === 'GSM-7' ? $gsm_rate : $unicode_rate;
            $estimated_cost = $segments * $rate;

            $rows[] = [
                'created_at' => (string) ($log->created_at ?? ''),
                'event_title' => (string) ($log->event_title ?? ''),
                'event_category' => (string) ($log->event_category ?? ''),
                'recipient' => (string) ($log->recipient ?? ''),
                'purpose' => (string) ($log->purpose ?? ''),
                'message' => (string) ($log->message ?? ''),
                'char_count' => $char_count,
                'encoding' => $encoding,
                'segments' => $segments,
                'estimated_cost' => $estimated_cost,
            ];

            $total_sms++;
            $total_chars += $char_count;
            $total_segments += $segments;
            $total_cost += $estimated_cost;
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total_sms' => $total_sms,
                'total_chars' => $total_chars,
                'avg_chars' => $total_sms > 0 ? ($total_chars / $total_sms) : 0,
                'total_segments' => $total_segments,
                'total_cost' => $total_cost,
            ],
        ];
    }

    private static function sms_segment_info($message) {
        $message = (string) $message;
        $char_count = function_exists('mb_strlen') ? (int) mb_strlen($message, 'UTF-8') : strlen($message);
        if ($char_count <= 0) {
            return [
                'encoding' => 'GSM-7',
                'char_count' => 0,
                'segments' => 0,
            ];
        }

        $gsm_basic = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ`¿abcdefghijklmnopqrstuvwxyzäöñüà";
        $gsm_extended = '^{}\\[~]|€';

        $chars = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) {
            $chars = str_split($message);
        }

        $septets = 0;
        $is_gsm = true;
        foreach ($chars as $char) {
            if (strpos($gsm_basic, $char) !== false) {
                $septets += 1;
                continue;
            }
            if (strpos($gsm_extended, $char) !== false) {
                $septets += 2;
                continue;
            }
            $is_gsm = false;
            break;
        }

        if ($is_gsm) {
            $segments = $septets <= 160 ? 1 : (int) ceil($septets / 153);
            return [
                'encoding' => 'GSM-7',
                'char_count' => $char_count,
                'segments' => $segments,
            ];
        }

        $segments = $char_count <= 70 ? 1 : (int) ceil($char_count / 67);
        return [
            'encoding' => 'UCS-2',
            'char_count' => $char_count,
            'segments' => $segments,
        ];
    }

    private static function query_retention_activity_monthly($event_category = '') {
        global $wpdb;
        $t = self::tables();

        $event_category = self::normalize_event_category($event_category);
        if ($event_category !== '') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT month_key,
                        SUM(CASE WHEN first_month = month_key THEN 1 ELSE 0 END) AS new_count,
                        SUM(CASE WHEN first_month < month_key THEN 1 ELSE 0 END) AS returning_count,
                        COUNT(*) AS active_volunteers
                 FROM (
                     SELECT DATE_FORMAT(a.created_at, '%Y-%m') AS month_key,
                            a.volunteer_id,
                            DATE_FORMAT((
                                SELECT MIN(a2.created_at)
                                FROM {$t['assignments']} a2
                                INNER JOIN {$t['events']} e2 ON e2.id = a2.event_id
                                WHERE a2.volunteer_id = a.volunteer_id
                                  AND e2.event_category = %s
                            ), '%Y-%m') AS first_month
                     FROM {$t['assignments']} a
                     INNER JOIN {$t['events']} e ON e.id = a.event_id
                     WHERE e.event_category = %s
                     GROUP BY DATE_FORMAT(a.created_at, '%Y-%m'), a.volunteer_id
                 ) monthly
                 GROUP BY month_key
                 ORDER BY month_key DESC
                 LIMIT 24",
                $event_category,
                $event_category
            ));
        }

        return $wpdb->get_results(
            "SELECT month_key,
                    SUM(CASE WHEN first_month = month_key THEN 1 ELSE 0 END) AS new_count,
                    SUM(CASE WHEN first_month < month_key THEN 1 ELSE 0 END) AS returning_count,
                    COUNT(*) AS active_volunteers
             FROM (
                 SELECT DATE_FORMAT(a.created_at, '%Y-%m') AS month_key,
                        a.volunteer_id,
                        DATE_FORMAT((
                            SELECT MIN(a2.created_at)
                            FROM {$t['assignments']} a2
                            WHERE a2.volunteer_id = a.volunteer_id
                        ), '%Y-%m') AS first_month
                 FROM {$t['assignments']} a
                 GROUP BY DATE_FORMAT(a.created_at, '%Y-%m'), a.volunteer_id
             ) monthly
             GROUP BY month_key
             ORDER BY month_key DESC
             LIMIT 24"
        );
    }

    private static function query_substitute_pool($event_id = 0, $event_category = '') {
        global $wpdb;
        $t = self::tables();
        $event_category = self::normalize_event_category($event_category);

        if ($event_id && !self::event_allows_substitutes($event_id)) {
            return [];
        }

        if ($event_id && $event_category !== '') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT v.id, v.name, v.email, v.phone, v.future_substitute_opt_in,
                        SUM(CASE WHEN a.assignment_type = 'substitute' THEN 1 ELSE 0 END) AS substitute_signup_count,
                        SUM(CASE WHEN a.assignment_type = 'substitute' AND a.task_id IS NULL THEN 1 ELSE 0 END) AS available_pool_count,
                        SUM(CASE WHEN a.assignment_type = 'substitute' AND a.task_id IS NOT NULL THEN 1 ELSE 0 END) AS assigned_slot_count
                 FROM {$t['volunteers']} v
                 LEFT JOIN {$t['assignments']} a ON a.volunteer_id = v.id AND a.event_id = %d
                 LEFT JOIN {$t['events']} e ON e.id = a.event_id
                 WHERE e.event_category = %s
                 GROUP BY v.id
                 HAVING substitute_signup_count > 0 OR v.future_substitute_opt_in = 1
                 ORDER BY v.future_substitute_opt_in DESC, substitute_signup_count DESC, v.name ASC",
                $event_id,
                $event_category
            ));
        }

        if ($event_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT v.id, v.name, v.email, v.phone, v.future_substitute_opt_in,
                        SUM(CASE WHEN a.assignment_type = 'substitute' THEN 1 ELSE 0 END) AS substitute_signup_count,
                        SUM(CASE WHEN a.assignment_type = 'substitute' AND a.task_id IS NULL THEN 1 ELSE 0 END) AS available_pool_count,
                        SUM(CASE WHEN a.assignment_type = 'substitute' AND a.task_id IS NOT NULL THEN 1 ELSE 0 END) AS assigned_slot_count
                 FROM {$t['volunteers']} v
                 LEFT JOIN {$t['assignments']} a ON a.volunteer_id = v.id AND a.event_id = %d
                 GROUP BY v.id
                 HAVING substitute_signup_count > 0 OR v.future_substitute_opt_in = 1
                 ORDER BY v.future_substitute_opt_in DESC, substitute_signup_count DESC, v.name ASC",
                $event_id
            ));
        }

        if ($event_category !== '') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT v.id, v.name, v.email, v.phone, v.future_substitute_opt_in,
                        SUM(CASE WHEN a.assignment_type = 'substitute' THEN 1 ELSE 0 END) AS substitute_signup_count,
                        SUM(CASE WHEN a.assignment_type = 'substitute' AND a.task_id IS NULL THEN 1 ELSE 0 END) AS available_pool_count,
                        SUM(CASE WHEN a.assignment_type = 'substitute' AND a.task_id IS NOT NULL THEN 1 ELSE 0 END) AS assigned_slot_count
                 FROM {$t['volunteers']} v
                 LEFT JOIN {$t['assignments']} a ON a.volunteer_id = v.id
                 LEFT JOIN {$t['events']} e ON e.id = a.event_id
                 WHERE e.event_category = %s
                 GROUP BY v.id
                 HAVING substitute_signup_count > 0 OR v.future_substitute_opt_in = 1
                 ORDER BY v.future_substitute_opt_in DESC, substitute_signup_count DESC, v.name ASC",
                $event_category
            ));
        }

        return $wpdb->get_results(
            "SELECT v.id, v.name, v.email, v.phone, v.future_substitute_opt_in,
                    SUM(CASE WHEN a.assignment_type = 'substitute' THEN 1 ELSE 0 END) AS substitute_signup_count,
                    SUM(CASE WHEN a.assignment_type = 'substitute' AND a.task_id IS NULL THEN 1 ELSE 0 END) AS available_pool_count,
                    SUM(CASE WHEN a.assignment_type = 'substitute' AND a.task_id IS NOT NULL THEN 1 ELSE 0 END) AS assigned_slot_count
             FROM {$t['volunteers']} v
             LEFT JOIN {$t['assignments']} a ON a.volunteer_id = v.id
             GROUP BY v.id
             HAVING substitute_signup_count > 0 OR v.future_substitute_opt_in = 1
             ORDER BY v.future_substitute_opt_in DESC, substitute_signup_count DESC, v.name ASC"
        );
    }

    private static function query_substitute_history($event_id = 0, $event_category = '') {
        global $wpdb;
        $t = self::tables();
        $event_category = self::normalize_event_category($event_category);

        if ($event_id && !self::event_allows_substitutes($event_id)) {
            return [];
        }

        if ($event_id && $event_category !== '') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT v.id,
                        v.name,
                        v.email,
                        e.title AS event_title,
                    e.event_category,
                        e.start_datetime,
                        v.future_substitute_opt_in,
                        CASE WHEN EXISTS (
                            SELECT 1
                            FROM {$t['assignments']} a2
                            INNER JOIN {$t['events']} e2 ON e2.id = a2.event_id
                            WHERE a2.volunteer_id = v.id
                              AND a2.assignment_type = 'substitute'
                              AND a2.task_id IS NOT NULL
                              AND e2.event_category = %s
                        ) THEN 1 ELSE 0 END AS ever_assigned_any_event
                 FROM {$t['assignments']} a
                 INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
                 INNER JOIN {$t['events']} e ON e.id = a.event_id
                 WHERE a.assignment_type = 'substitute'
                   AND a.event_id = %d
                   AND e.event_category = %s
                 GROUP BY v.id, e.id
                 ORDER BY e.start_datetime DESC, v.name ASC",
                $event_category,
                $event_id,
                $event_category
            ));
        }

        if ($event_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT v.id,
                        v.name,
                        v.email,
                        e.title AS event_title,
                    e.event_category,
                        e.start_datetime,
                        v.future_substitute_opt_in,
                        CASE WHEN EXISTS (
                            SELECT 1
                            FROM {$t['assignments']} a2
                            WHERE a2.volunteer_id = v.id
                              AND a2.assignment_type = 'substitute'
                              AND a2.task_id IS NOT NULL
                        ) THEN 1 ELSE 0 END AS ever_assigned_any_event
                 FROM {$t['assignments']} a
                 INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
                 INNER JOIN {$t['events']} e ON e.id = a.event_id
                 WHERE a.assignment_type = 'substitute'
                   AND a.event_id = %d
                 GROUP BY v.id, e.id
                 ORDER BY e.start_datetime DESC, v.name ASC",
                $event_id
            ));
        }

        if ($event_category !== '') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT v.id,
                        v.name,
                        v.email,
                        e.title AS event_title,
                        e.start_datetime,
                        v.future_substitute_opt_in,
                        CASE WHEN EXISTS (
                            SELECT 1
                            FROM {$t['assignments']} a2
                            INNER JOIN {$t['events']} e2 ON e2.id = a2.event_id
                            WHERE a2.volunteer_id = v.id
                              AND a2.assignment_type = 'substitute'
                              AND a2.task_id IS NOT NULL
                              AND e2.event_category = %s
                        ) THEN 1 ELSE 0 END AS ever_assigned_any_event
                 FROM {$t['assignments']} a
                 INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
                 INNER JOIN {$t['events']} e ON e.id = a.event_id
                 WHERE a.assignment_type = 'substitute'
                   AND e.event_category = %s
                 GROUP BY v.id, e.id
                 ORDER BY e.start_datetime DESC, v.name ASC",
                $event_category,
                $event_category
            ));
        }

        return $wpdb->get_results(
                "SELECT v.id,
                    v.name,
                    v.email,
                    e.title AS event_title,
                    e.event_category,
                    e.start_datetime,
                    v.future_substitute_opt_in,
                    CASE WHEN EXISTS (
                        SELECT 1
                        FROM {$t['assignments']} a2
                        WHERE a2.volunteer_id = v.id
                          AND a2.assignment_type = 'substitute'
                          AND a2.task_id IS NOT NULL
                    ) THEN 1 ELSE 0 END AS ever_assigned_any_event
             FROM {$t['assignments']} a
             INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
             INNER JOIN {$t['events']} e ON e.id = a.event_id
             WHERE a.assignment_type = 'substitute'
             GROUP BY v.id, e.id
             ORDER BY e.start_datetime DESC, v.name ASC"
        );
    }

    private static function sanitize_id_list($values) {
        if (!is_array($values)) {
            $values = [$values];
        }

        $sanitized = [];
        foreach ($values as $value) {
            $id = absint($value);
            if ($id > 0) {
                $sanitized[] = $id;
            }
        }

        return array_values(array_unique($sanitized));
    }

    private static function sanitize_date_yyyy_mm_dd($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }
        return $value;
    }

    private static function sanitize_event_category_list($values, $allowed_categories = null) {
        if (!is_array($values)) {
            $values = [$values];
        }

        if ($allowed_categories === null) {
            $allowed_categories = self::get_event_categories();
        }

        $result = [];
        foreach ($values as $value) {
            $normalized = self::normalize_event_category((string) $value, $allowed_categories);
            if ($normalized !== '') {
                $result[] = $normalized;
            }
        }

        return array_values(array_unique($result));
    }

    private static function sanitize_volunteer_active_filter($value) {
        $value = sanitize_key((string) $value);
        return in_array($value, ['all', 'yes', 'no'], true) ? $value : 'all';
    }

    private static function volunteer_active_filter_label($value) {
        $value = self::sanitize_volunteer_active_filter($value);
        if ($value === 'yes') {
            return __('Yes', 'schedule-checkin');
        }
        if ($value === 'no') {
            return __('No', 'schedule-checkin');
        }
        return __('All', 'schedule-checkin');
    }

    private static function sanitize_volunteers_period($period) {
        $period = sanitize_key((string) $period);
        $allowed = ['week', 'month', 'year', 'date-range', 'all-time'];
        return in_array($period, $allowed, true) ? $period : 'all-time';
    }

    private static function volunteers_period_label($period) {
        $period = self::sanitize_volunteers_period($period);
        $labels = [
            'week' => __('This Week', 'schedule-checkin'),
            'month' => __('This Month', 'schedule-checkin'),
            'year' => __('This Year', 'schedule-checkin'),
            'date-range' => __('Date Range', 'schedule-checkin'),
            'all-time' => __('All Time', 'schedule-checkin'),
        ];
        return $labels[$period] ?? __('All Time', 'schedule-checkin');
    }

    private static function volunteer_status_label($volunteer) {
        if ((int) ($volunteer->is_active ?? 1) === 1) {
            return __('Active', 'schedule-checkin');
        }

        $merged_into_name = trim((string) ($volunteer->merged_into_name ?? ''));
        if ($merged_into_name !== '') {
            return sprintf(__('Inactive (Merged into %s)', 'schedule-checkin'), $merged_into_name);
        }

        return __('Inactive', 'schedule-checkin');
    }

    private static function format_selected_event_labels($event_ids) {
        $event_ids = self::sanitize_id_list($event_ids);
        if (empty($event_ids)) {
            return __('All Events', 'schedule-checkin');
        }

        global $wpdb;
        $t = self::tables();
        $placeholders = implode(', ', array_fill(0, count($event_ids), '%d'));
        $sql = "SELECT title FROM {$t['events']} WHERE id IN ({$placeholders}) ORDER BY title ASC";
        $titles = $wpdb->get_col($wpdb->prepare($sql, ...$event_ids));
        if (empty($titles)) {
            return __('Selected Events', 'schedule-checkin');
        }

        return implode(', ', array_map('strval', $titles));
    }

    private static function volunteers_period_window($period, $start_date = '', $end_date = '') {
        $period = self::sanitize_volunteers_period($period);
        $timezone = self::cst_timezone();
        $now = new DateTime('now', $timezone);

        $window_start = '';
        $window_end = '';

        if ($period === 'week') {
            $start = clone $now;
            $start->modify('monday this week');
            $start->setTime(0, 0, 0);
            $window_start = $start->format('Y-m-d H:i:s');
            $window_end = $now->format('Y-m-d H:i:s');
        } elseif ($period === 'month') {
            $start = clone $now;
            $start->modify('first day of this month');
            $start->setTime(0, 0, 0);
            $window_start = $start->format('Y-m-d H:i:s');
            $window_end = $now->format('Y-m-d H:i:s');
        } elseif ($period === 'year') {
            $start = clone $now;
            $start->setDate((int) $now->format('Y'), 1, 1);
            $start->setTime(0, 0, 0);
            $window_start = $start->format('Y-m-d H:i:s');
            $window_end = $now->format('Y-m-d H:i:s');
        } elseif ($period === 'date-range') {
            $safe_start = self::sanitize_date_yyyy_mm_dd($start_date);
            $safe_end = self::sanitize_date_yyyy_mm_dd($end_date);
            if ($safe_start !== '') {
                $window_start = $safe_start . ' 00:00:00';
            }
            if ($safe_end !== '') {
                $window_end = $safe_end . ' 23:59:59';
            }
            if ($window_start !== '' && $window_end !== '' && strcmp($window_start, $window_end) > 0) {
                $tmp = $window_start;
                $window_start = substr($window_end, 0, 10) . ' 00:00:00';
                $window_end = substr($tmp, 0, 10) . ' 23:59:59';
            }
        }

        return [
            'start' => $window_start,
            'end' => $window_end,
        ];
    }

    private static function query_volunteer_count_map($source, $event_ids = [], $event_categories = [], $period_start = '', $period_end = '') {
        global $wpdb;
        $t = self::tables();

        $event_ids = self::sanitize_id_list($event_ids);
        $event_categories = self::sanitize_event_category_list($event_categories);
        $period_start = (string) $period_start;
        $period_end = (string) $period_end;

        $sql = '';
        $where = [];
        $params = [];

        if ($source === 'assignments') {
            $sql = "SELECT a.volunteer_id, COUNT(*) AS total_count
                    FROM {$t['assignments']} a
                    INNER JOIN {$t['events']} e ON e.id = a.event_id";
            $where[] = 'a.volunteer_id > 0';
            if ($period_start !== '') {
                $where[] = 'a.created_at >= %s';
                $params[] = $period_start;
            }
            if ($period_end !== '') {
                $where[] = 'a.created_at <= %s';
                $params[] = $period_end;
            }
        } elseif ($source === 'checkins') {
            $sql = "SELECT c.volunteer_id, COUNT(*) AS total_count
                    FROM {$t['checkins']} c
                    INNER JOIN {$t['events']} e ON e.id = c.event_id";
            $where[] = 'c.volunteer_id > 0';
            if ($period_start !== '') {
                $where[] = 'c.created_at >= %s';
                $params[] = $period_start;
            }
            if ($period_end !== '') {
                $where[] = 'c.created_at <= %s';
                $params[] = $period_end;
            }
        } else {
            $sql = "SELECT l.volunteer_id, COUNT(*) AS total_count
                    FROM {$t['comm_logs']} l
                    LEFT JOIN {$t['events']} e ON e.id = l.event_id";
            $where[] = 'l.volunteer_id > 0';
            if ($period_start !== '') {
                $where[] = 'l.created_at >= %s';
                $params[] = $period_start;
            }
            if ($period_end !== '') {
                $where[] = 'l.created_at <= %s';
                $params[] = $period_end;
            }
        }

        if (!empty($event_ids)) {
            $event_placeholders = implode(', ', array_fill(0, count($event_ids), '%d'));
            if ($source === 'assignments') {
                $where[] = "a.event_id IN ({$event_placeholders})";
            } elseif ($source === 'checkins') {
                $where[] = "c.event_id IN ({$event_placeholders})";
            } else {
                $where[] = "l.event_id IN ({$event_placeholders})";
            }
            $params = array_merge($params, $event_ids);
        }

        if (!empty($event_categories)) {
            $category_placeholders = implode(', ', array_fill(0, count($event_categories), '%s'));
            $where[] = "e.event_category IN ({$category_placeholders})";
            $params = array_merge($params, $event_categories);
        }

        $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= $source === 'assignments' ? ' GROUP BY a.volunteer_id' : ($source === 'checkins' ? ' GROUP BY c.volunteer_id' : ' GROUP BY l.volunteer_id');

        $rows = !empty($params)
            ? $wpdb->get_results($wpdb->prepare($sql, ...$params))
            : $wpdb->get_results($sql);

        $result = [];
        foreach ((array) $rows as $row) {
            $result[(int) ($row->volunteer_id ?? 0)] = (int) ($row->total_count ?? 0);
        }
        return $result;
    }

    private static function query_volunteers_report($filters = []) {
        global $wpdb;
        $t = self::tables();

        $event_ids = self::sanitize_id_list($filters['event_ids'] ?? []);
        $event_categories = self::sanitize_event_category_list($filters['event_categories'] ?? []);
        $volunteer_id = absint($filters['volunteer_id'] ?? 0);
        $is_active = self::sanitize_volunteer_active_filter($filters['is_active'] ?? 'all');
        $period = self::sanitize_volunteers_period($filters['period'] ?? 'all-time');
        $start_date = self::sanitize_date_yyyy_mm_dd($filters['start_date'] ?? '');
        $end_date = self::sanitize_date_yyyy_mm_dd($filters['end_date'] ?? '');
        $window = self::volunteers_period_window($period, $start_date, $end_date);

        $where = ['1=1'];
        $params = [];
        if ($volunteer_id > 0) {
            $where[] = 'v.id = %d';
            $params[] = $volunteer_id;
        }
        if ($is_active === 'yes') {
            $where[] = 'v.is_active = 1';
        } elseif ($is_active === 'no') {
            $where[] = 'v.is_active = 0';
        }

        $volunteer_sql = "SELECT v.id,
                                 v.name,
                                 v.email,
                                 v.phone,
                                 v.preferred_contact_method,
                                 v.is_active,
                                 merged_target.name AS merged_into_name
                          FROM {$t['volunteers']} v
                          LEFT JOIN {$t['volunteers']} merged_target ON merged_target.id = v.merged_into_volunteer_id
                          WHERE " . implode(' AND ', $where) . "
                          ORDER BY v.name ASC";
        $volunteer_rows = !empty($params)
            ? $wpdb->get_results($wpdb->prepare($volunteer_sql, ...$params))
            : $wpdb->get_results($volunteer_sql);

        $assignment_count_map = self::query_volunteer_count_map('assignments', $event_ids, $event_categories, $window['start'], $window['end']);
        $checkin_count_map = self::query_volunteer_count_map('checkins', $event_ids, $event_categories, $window['start'], $window['end']);
        $comm_count_map = self::query_volunteer_count_map('comm', $event_ids, $event_categories, $window['start'], $window['end']);

        $summaries = self::get_assignment_activity_summaries([
            'event_ids' => $event_ids,
            'event_categories' => $event_categories,
            'volunteer_id' => $volunteer_id,
            'period_start' => $window['start'],
            'period_end' => $window['end'],
        ]);

        $hours_by_volunteer = [];
        $category_totals = [];
        $category_columns = !empty($event_categories) ? $event_categories : [];

        foreach ($summaries as $summary) {
            $minutes = (int) ($summary['total_minutes'] ?? 0);
            if ($minutes <= 0) {
                continue;
            }

            $row_volunteer_id = (int) ($summary['volunteer_id'] ?? 0);
            if ($row_volunteer_id <= 0) {
                continue;
            }

            $hours = $minutes / 60;
            $category_label = trim((string) ($summary['event_category'] ?? ''));
            if ($category_label === '') {
                $category_label = __('Uncategorized', 'schedule-checkin');
            }

            if (!in_array($category_label, $category_columns, true)) {
                $category_columns[] = $category_label;
            }

            if (!isset($hours_by_volunteer[$row_volunteer_id])) {
                $hours_by_volunteer[$row_volunteer_id] = [
                    'total' => 0.0,
                    'by_category' => [],
                ];
            }
            if (!isset($hours_by_volunteer[$row_volunteer_id]['by_category'][$category_label])) {
                $hours_by_volunteer[$row_volunteer_id]['by_category'][$category_label] = 0.0;
            }
            if (!isset($category_totals[$category_label])) {
                $category_totals[$category_label] = 0.0;
            }

            $hours_by_volunteer[$row_volunteer_id]['by_category'][$category_label] += $hours;
            $hours_by_volunteer[$row_volunteer_id]['total'] += $hours;
            $category_totals[$category_label] += $hours;
        }

        if (empty($category_columns)) {
            $category_columns = self::get_event_categories();
        }

        sort($category_columns, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($category_columns as $category_column) {
            if (!isset($category_totals[$category_column])) {
                $category_totals[$category_column] = 0.0;
            }
        }

        $rows = [];
        $usage_totals = [
            'assignments' => 0,
            'checkins' => 0,
            'messages' => 0,
        ];
        $grand_total_hours = 0.0;

        foreach ((array) $volunteer_rows as $volunteer_row) {
            $row_volunteer_id = (int) ($volunteer_row->id ?? 0);
            $assignment_count = (int) ($assignment_count_map[$row_volunteer_id] ?? 0);
            $checkin_count = (int) ($checkin_count_map[$row_volunteer_id] ?? 0);
            $comm_count = (int) ($comm_count_map[$row_volunteer_id] ?? 0);
            $hours_data = $hours_by_volunteer[$row_volunteer_id] ?? ['total' => 0.0, 'by_category' => []];

            $hours_by_category = [];
            foreach ($category_columns as $category_column) {
                $hours_by_category[$category_column] = (float) ($hours_data['by_category'][$category_column] ?? 0);
            }

            $usage_totals['assignments'] += $assignment_count;
            $usage_totals['checkins'] += $checkin_count;
            $usage_totals['messages'] += $comm_count;
            $grand_total_hours += (float) ($hours_data['total'] ?? 0);

            $rows[] = [
                'id' => $row_volunteer_id,
                'name' => (string) ($volunteer_row->name ?? ''),
                'email' => (string) ($volunteer_row->email ?? ''),
                'phone' => (string) ($volunteer_row->phone ?? ''),
                'preferred_contact_method' => (string) (($volunteer_row->preferred_contact_method ?: 'email')),
                'status_label' => self::volunteer_status_label($volunteer_row),
                'assignment_count' => $assignment_count,
                'checkin_count' => $checkin_count,
                'comm_count' => $comm_count,
                'hours_by_category' => $hours_by_category,
                'total_hours' => (float) ($hours_data['total'] ?? 0),
            ];
        }

        return [
            'rows' => $rows,
            'category_columns' => $category_columns,
            'usage_totals' => $usage_totals,
            'category_totals' => $category_totals,
            'grand_total_hours' => $grand_total_hours,
        ];
    }

    private static function get_advanced_report_payload($report_key, $context = []) {
        $coverage_event_id = absint($context['coverage_event_id'] ?? 0);
        $substitute_event_id = absint($context['substitute_event_id'] ?? 0);
        $substitute_history_event_id = absint($context['substitute_history_event_id'] ?? 0);
        $sms_period = sanitize_key($context['sms_period'] ?? 'month');
        $sms_start_date = sanitize_text_field($context['sms_start_date'] ?? '');
        $sms_end_date = sanitize_text_field($context['sms_end_date'] ?? '');
        $event_category = self::normalize_event_category(sanitize_text_field($context['event_category'] ?? ''));
        $volunteers_event_ids = self::sanitize_id_list($context['volunteers_event_ids'] ?? []);
        $volunteers_event_categories = self::sanitize_event_category_list($context['volunteers_event_categories'] ?? []);
        $volunteers_volunteer_id = absint($context['volunteers_volunteer_id'] ?? 0);
        $volunteers_is_active = self::sanitize_volunteer_active_filter($context['volunteers_is_active'] ?? 'all');
        $volunteers_period = self::sanitize_volunteers_period($context['volunteers_period'] ?? 'all-time');
        $volunteers_start_date = self::sanitize_date_yyyy_mm_dd($context['volunteers_start_date'] ?? '');
        $volunteers_end_date = self::sanitize_date_yyyy_mm_dd($context['volunteers_end_date'] ?? '');
        $sms_analytics = self::query_sms_analytics($sms_period, $sms_start_date, $sms_end_date, $event_category);
        $volunteers_report = self::query_volunteers_report([
            'event_ids' => $volunteers_event_ids,
            'event_categories' => $volunteers_event_categories,
            'volunteer_id' => $volunteers_volunteer_id,
            'is_active' => $volunteers_is_active,
            'period' => $volunteers_period,
            'start_date' => $volunteers_start_date,
            'end_date' => $volunteers_end_date,
        ]);
        $volunteers_report_category_columns = $volunteers_report['category_columns'] ?? [];
        $volunteers_filter_meta = [
            __('Events', 'schedule-checkin') => self::format_selected_event_labels($volunteers_event_ids),
            __('Categories', 'schedule-checkin') => !empty($volunteers_event_categories) ? implode(', ', $volunteers_event_categories) : __('All Categories', 'schedule-checkin'),
            __('Volunteer', 'schedule-checkin') => $volunteers_volunteer_id ? (self::volunteer_name_by_id($volunteers_volunteer_id) ?: ('#' . (int) $volunteers_volunteer_id)) : __('All Volunteers', 'schedule-checkin'),
            __('Active', 'schedule-checkin') => self::volunteer_active_filter_label($volunteers_is_active),
            __('Period', 'schedule-checkin') => self::volunteers_period_label($volunteers_period),
            __('Date From', 'schedule-checkin') => $volunteers_start_date !== '' ? $volunteers_start_date : __('N/A', 'schedule-checkin'),
            __('Date To', 'schedule-checkin') => $volunteers_end_date !== '' ? $volunteers_end_date : __('N/A', 'schedule-checkin'),
        ];
        $volunteers_excel_meta_rows = self::build_export_meta_rows(__('Volunteers Report', 'schedule-checkin'), $volunteers_filter_meta);
        $volunteers_excel_meta_rows[] = [__('Total Assignments', 'schedule-checkin'), (string) (int) ($volunteers_report['usage_totals']['assignments'] ?? 0)];
        $volunteers_excel_meta_rows[] = [__('Total Check-Ins', 'schedule-checkin'), (string) (int) ($volunteers_report['usage_totals']['checkins'] ?? 0)];
        $volunteers_excel_meta_rows[] = [__('Total Messages', 'schedule-checkin'), (string) (int) ($volunteers_report['usage_totals']['messages'] ?? 0)];
        foreach ((array) ($volunteers_report['category_totals'] ?? []) as $hours_category => $hours_total) {
            $volunteers_excel_meta_rows[] = [
                sprintf(__('Hours - %s', 'schedule-checkin'), (string) $hours_category),
                number_format((float) $hours_total, 2, '.', ''),
            ];
        }
        $volunteers_excel_meta_rows[] = [
            __('Grand Total Volunteered Hours', 'schedule-checkin'),
            number_format((float) ($volunteers_report['grand_total_hours'] ?? 0), 2, '.', ''),
        ];
        $reports = [
            'lifetime_hours' => [
                'title' => __('Volunteer Lifetime Hours', 'schedule-checkin'),
                'slug' => 'volunteer-lifetime-hours',
                'headers' => ['Volunteer', 'Email', 'Lifetime Hours'],
            'rows' => self::query_lifetime_hours($event_category),
            'pdf_filters' => [__('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin')],
                'map' => static function ($row) {
                    return [
                        $row->name ?? '',
                        $row->email ?? '',
                        isset($row->hours_worked) ? number_format((float) $row->hours_worked, 2, '.', '') : '0.00',
                    ];
                },
            ],
            'attendance_reliability' => [
                'title' => __('Attendance Reliability', 'schedule-checkin'),
                'slug' => 'attendance-reliability',
                'headers' => ['Volunteer', 'Scheduled', 'Checked In', 'No Show', 'Attendance %'],
                'rows' => self::query_attendance_reliability($event_category),
                'pdf_filters' => [__('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin')],
                'map' => static function ($row) {
                    $scheduled = max(1, (int) ($row->scheduled_count ?? 0));
                    $pct = ((int) ($row->checked_in_count ?? 0) / $scheduled) * 100;
                    return [
                        $row->name ?? '',
                        (int) ($row->scheduled_count ?? 0),
                        (int) ($row->checked_in_count ?? 0),
                        (int) ($row->no_show_count ?? 0),
                        number_format($pct, 1) . '%',
                    ];
                },
            ],
            'late_early_analysis' => [
                'title' => __('Late / Early Analysis', 'schedule-checkin'),
                'slug' => 'late-early-analysis',
                'headers' => ['Volunteer', 'Avg Late (min)', 'Avg Early Leave (min)', 'Late Arrivals', 'Early Leaves'],
                'rows' => self::query_late_early_analysis($event_category),
                'pdf_filters' => [__('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin')],
                'map' => static function ($row) {
                    return [
                        $row->name ?? '',
                        number_format((float) ($row->avg_late_min ?? 0), 1),
                        number_format((float) ($row->avg_early_leave_min ?? 0), 1),
                        (int) ($row->late_arrivals ?? 0),
                        (int) ($row->early_leaves ?? 0),
                    ];
                },
            ],
            'task_coverage_health' => [
                'title' => __('Task Coverage Health', 'schedule-checkin'),
                'slug' => 'task-coverage-health',
                'headers' => ['Event', 'Event Category', 'Task', 'Start', 'Filled Slots', 'Total Slots', 'Fill %'],
                'rows' => self::query_task_coverage_health($coverage_event_id, $event_category),
                'pdf_filters' => [
                    __('Event', 'schedule-checkin') => $coverage_event_id ? (self::event_title_by_id($coverage_event_id) ?: ('#' . (int) $coverage_event_id)) : __('All Events', 'schedule-checkin'),
                    __('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin'),
                ],
                'map' => static function ($row) {
                    return [
                        $row->event_title ?? '',
                        $row->event_category ?? '',
                        $row->task_title ?? '',
                        self::format_datetime_cst_12h($row->start_datetime ?? ''),
                        (int) ($row->filled_slots ?? 0),
                        (int) ($row->slots ?? 0),
                        number_format((float) ($row->fill_rate ?? 0), 1) . '%',
                    ];
                },
            ],
            'checkin_method_audit' => [
                'title' => __('Check-In Method Audit', 'schedule-checkin'),
                'slug' => 'checkin-method-audit',
                'headers' => ['Source', 'Check In', 'Check Out', 'Total Actions'],
                'rows' => self::query_checkin_method_audit($event_category),
                'pdf_filters' => [__('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin')],
                'map' => static function ($row) {
                    return [
                        $row->source ?? '',
                        (int) ($row->checkin_count ?? 0),
                        (int) ($row->checkout_count ?? 0),
                        (int) ($row->total_actions ?? 0),
                    ];
                },
            ],
            'retention_activity_monthly' => [
                'title' => __('Retention & Activity (Monthly)', 'schedule-checkin'),
                'slug' => 'retention-activity-monthly',
                'headers' => ['Month', 'New Volunteers', 'Returning Volunteers', 'Active Volunteers'],
                'rows' => self::query_retention_activity_monthly($event_category),
                'pdf_filters' => [__('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin')],
                'map' => static function ($row) {
                    return [
                        $row->month_key ?? '',
                        (int) ($row->new_count ?? 0),
                        (int) ($row->returning_count ?? 0),
                        (int) ($row->active_volunteers ?? 0),
                    ];
                },
            ],
            'substitute_pool' => [
                'title' => __('Substitute Pool', 'schedule-checkin'),
                'slug' => 'substitute-pool',
                'headers' => ['Name', 'Email', 'Phone', 'Future Opt-In', 'Substitute Signups', 'Available', 'Assigned'],
                'rows' => self::query_substitute_pool($substitute_event_id, $event_category),
                'pdf_filters' => [
                    __('Event', 'schedule-checkin') => $substitute_event_id ? (self::event_title_by_id($substitute_event_id) ?: ('#' . (int) $substitute_event_id)) : __('All Events', 'schedule-checkin'),
                    __('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin'),
                ],
                'map' => static function ($row) {
                    return [
                        $row->name ?? '',
                        $row->email ?? '',
                        $row->phone ?? '',
                        (int) ($row->future_substitute_opt_in ?? 0) === 1 ? __('Yes', 'schedule-checkin') : __('No', 'schedule-checkin'),
                        (int) ($row->substitute_signup_count ?? 0),
                        (int) ($row->available_pool_count ?? 0),
                        (int) ($row->assigned_slot_count ?? 0),
                    ];
                },
            ],
            'substitute_history' => [
                'title' => __('Substitute History', 'schedule-checkin'),
                'slug' => 'substitute-history',
                'headers' => ['Volunteer', 'Email', 'Event', 'Event Category', 'Future Opt-In', 'Ever Assigned'],
                'rows' => self::query_substitute_history($substitute_history_event_id, $event_category),
                'pdf_filters' => [
                    __('Event', 'schedule-checkin') => $substitute_history_event_id ? (self::event_title_by_id($substitute_history_event_id) ?: ('#' . (int) $substitute_history_event_id)) : __('All Events', 'schedule-checkin'),
                    __('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin'),
                ],
                'map' => static function ($row) {
                    return [
                        $row->name ?? '',
                        $row->email ?: __('No email', 'schedule-checkin'),
                        $row->event_title ?? '',
                        $row->event_category ?? '',
                        (int) ($row->future_substitute_opt_in ?? 0) === 1 ? __('Yes', 'schedule-checkin') : __('No', 'schedule-checkin'),
                        (int) ($row->ever_assigned_any_event ?? 0) === 1 ? __('Yes', 'schedule-checkin') : __('No', 'schedule-checkin'),
                    ];
                },
            ],
            'volunteers' => [
                'title' => __('Volunteers Report', 'schedule-checkin'),
                'slug' => 'volunteers-report',
                'headers' => array_merge(
                    ['Name', 'Email', 'Phone', 'Preferred', 'Status', 'Assignments', 'Check-Ins', 'Messages'],
                    $volunteers_report_category_columns,
                    ['Total Hours']
                ),
                'rows' => $volunteers_report['rows'] ?? [],
                'pdf_filters' => $volunteers_filter_meta,
                'excel_meta_rows' => $volunteers_excel_meta_rows,
                'map' => static function ($row) use ($volunteers_report_category_columns) {
                    $mapped = [
                        $row['name'] ?? '',
                        $row['email'] ?? '',
                        self::format_phone_for_display((string) ($row['phone'] ?? '')),
                        strtoupper((string) ($row['preferred_contact_method'] ?? 'email')),
                        $row['status_label'] ?? '',
                        (int) ($row['assignment_count'] ?? 0),
                        (int) ($row['checkin_count'] ?? 0),
                        (int) ($row['comm_count'] ?? 0),
                    ];
                    foreach ($volunteers_report_category_columns as $category_column) {
                        $mapped[] = number_format((float) (($row['hours_by_category'][$category_column] ?? 0)), 2, '.', '');
                    }
                    $mapped[] = number_format((float) ($row['total_hours'] ?? 0), 2, '.', '');
                    return $mapped;
                },
            ],
            'sms_analytics' => [
                'title' => __('SMS Analytics', 'schedule-checkin'),
                'slug' => 'sms-analytics-' . $sms_period,
                'headers' => ['When', 'Event', 'Event Category', 'Recipient', 'Purpose', 'Characters', 'Encoding', 'Segments', 'Estimated Cost', 'Message'],
                'rows' => $sms_analytics['rows'] ?? [],
                'pdf_filters' => [
                    __('Time Period', 'schedule-checkin') => self::report_period_label($sms_period),
                    __('Date From', 'schedule-checkin') => $sms_start_date !== '' ? $sms_start_date : __('N/A', 'schedule-checkin'),
                    __('Date To', 'schedule-checkin') => $sms_end_date !== '' ? $sms_end_date : __('N/A', 'schedule-checkin'),
                    __('Category', 'schedule-checkin') => $event_category !== '' ? $event_category : __('All Categories', 'schedule-checkin'),
                ],
                'map' => static function ($row) {
                    return [
                        self::format_datetime_cst_12h($row['created_at'] ?? ''),
                        $row['event_title'] ?? '',
                        $row['event_category'] ?? '',
                        $row['recipient'] ?? '',
                        $row['purpose'] ?? '',
                        (int) ($row['char_count'] ?? 0),
                        $row['encoding'] ?? '',
                        (int) ($row['segments'] ?? 0),
                        '$' . number_format((float) ($row['estimated_cost'] ?? 0), 4, '.', ''),
                        $row['message'] ?? '',
                    ];
                },
            ],
        ];

        if (!isset($reports[$report_key])) {
            wp_safe_redirect(admin_url('admin.php?page=sc-reports&sc_error=invalid_report'));
            exit;
        }

        return $reports[$report_key];
    }

    private static function volunteer_export_prefix($volunteer_id) {
        if (!$volunteer_id) {
            return '';
        }

        global $wpdb;
        $t = self::tables();
        $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$t['volunteers']} WHERE id = %d", $volunteer_id));
        if (!$name) {
            return '';
        }

        $slug = sanitize_title($name);
        $slug = str_replace('-', '_', $slug);
        return $slug ? ($slug . '_') : '';
    }

    private static function event_title_by_id($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return '';
        }

        global $wpdb;
        $t = self::tables();
        return (string) $wpdb->get_var($wpdb->prepare("SELECT title FROM {$t['events']} WHERE id = %d", $event_id));
    }

    private static function event_allows_substitutes($event_id) {
        $event_id = (int) $event_id;
        if ($event_id <= 0) {
            return true;
        }

        global $wpdb;
        $t = self::tables();
        $value = $wpdb->get_var($wpdb->prepare("SELECT allow_substitutes FROM {$t['events']} WHERE id = %d", $event_id));
        if ($value === null) {
            return true;
        }

        return (int) $value === 1;
    }

    private static function volunteer_name_by_id($volunteer_id) {
        $volunteer_id = (int) $volunteer_id;
        if ($volunteer_id <= 0) {
            return '';
        }

        global $wpdb;
        $t = self::tables();
        return (string) $wpdb->get_var($wpdb->prepare("SELECT name FROM {$t['volunteers']} WHERE id = %d", $volunteer_id));
    }

    private static function report_period_label($period) {
        $period = sanitize_key((string) $period);
        $map = [
            'day' => __('Day', 'schedule-checkin'),
            'week' => __('Week', 'schedule-checkin'),
            'month' => __('Month', 'schedule-checkin'),
            'year' => __('Year', 'schedule-checkin'),
            'all' => __('All Time', 'schedule-checkin'),
            'date-range' => __('Date Range', 'schedule-checkin'),
        ];
        return $map[$period] ?? ucfirst(str_replace('-', ' ', $period));
    }

    private static function sanitize_assignment_auto_schedule($schedule) {
        $schedule = sanitize_key((string) $schedule);
        $allowed = ['weekly_monday', 'biweekly_monday', 'biweekly_monday_thursday'];
        if (!in_array($schedule, $allowed, true)) {
            return 'weekly_monday';
        }

        return $schedule;
    }

    private static function build_export_meta_rows($title, $filters = []) {
        $rows = [
            [__('Title', 'schedule-checkin'), (string) $title],
            [__('Date', 'schedule-checkin'), self::format_datetime_cst_12h(current_time('mysql'))],
        ];

        if (!empty($filters) && is_array($filters)) {
            foreach ($filters as $label => $value) {
                $rows[] = [(string) $label, (string) $value];
            }
        } else {
            $rows[] = [__('Filters', 'schedule-checkin'), __('None', 'schedule-checkin')];
        }

        return $rows;
    }

    private static function export_timestamp() {
        $dt = new DateTime('now', self::cst_timezone());
        return $dt->format('Ymd-His');
    }

    private static function build_export_filename($base_name, $extension, $volunteer_id = 0) {
        $prefix = self::volunteer_export_prefix($volunteer_id);
        return $prefix . sanitize_file_name($base_name) . '-' . self::export_timestamp() . '.' . ltrim($extension, '.');
    }

    private static function sanitize_visible_fields($fields) {
        $allowed = ['name', 'email', 'phone'];
        $result = [];
        foreach ($fields as $field) {
            $field = sanitize_key($field);
            if (in_array($field, $allowed, true)) {
                $result[] = $field;
            }
        }

        return array_values(array_unique($result));
    }

    private static function sanitize_event_categories($raw) {
        $lines = preg_split('/\r\n|\r|\n/', (string) $raw);
        if (!is_array($lines)) {
            $lines = [];
        }

        $result = [];
        foreach ($lines as $line) {
            $category = trim(wp_strip_all_tags((string) $line));
            if ($category === '') {
                continue;
            }
            $result[] = function_exists('mb_substr') ? mb_substr($category, 0, 100) : substr($category, 0, 100);
        }

        $result = array_values(array_unique($result));
        if (empty($result)) {
            $result = ['General'];
        }

        return $result;
    }

    private static function get_plugin_setting($key, $default = '', $legacy_key = '') {
        global $wpdb;
        $t = self::tables();

        $key = sanitize_key((string) $key);
        if ($key === '') {
            return (string) $default;
        }

        $value = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$t['settings']} WHERE option_key = %s", $key));
        if ($value !== null) {
            return (string) $value;
        }

        if ($legacy_key !== '') {
            $legacy_value = get_option($legacy_key, null);
            if ($legacy_value !== null) {
                self::set_plugin_setting($key, (string) $legacy_value);
                delete_option($legacy_key);
                return (string) $legacy_value;
            }
        }

        $legacy_value = get_option($key, null);
        if ($legacy_value !== null) {
            self::set_plugin_setting($key, (string) $legacy_value);
            delete_option($key);
            return (string) $legacy_value;
        }

        return (string) $default;
    }

    private static function set_plugin_setting($key, $value) {
        global $wpdb;
        $t = self::tables();

        $key = sanitize_key((string) $key);
        if ($key === '') {
            return;
        }

        $wpdb->replace($t['settings'], [
            'option_key' => $key,
            'option_value' => (string) $value,
            'updated_at' => current_time('mysql'),
        ]);
    }

    private static function delete_plugin_setting($key) {
        global $wpdb;
        $t = self::tables();
        $key = sanitize_key((string) $key);
        if ($key === '') {
            return;
        }

        $wpdb->delete($t['settings'], ['option_key' => $key]);
    }

    private static function normalize_phone_for_storage($phone) {
        $phone = trim((string) $phone);
        if (preg_match('/^\d{3}-\d{3}-\d{4}$/', $phone)) {
            $digits = str_replace('-', '', $phone);
            return '+1' . $digits;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }
        if (strlen($digits) === 11 && strpos($digits, '1') === 0) {
            return '+' . $digits;
        }

        return '';
    }

    private static function format_phone_for_input($phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) === 11 && strpos($digits, '1') === 0) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 4);
        }

        return (string) $phone;
    }

    private static function get_event_categories() {
        return self::sanitize_event_categories((string) self::get_plugin_setting('sc_event_categories', 'General'));
    }

    private static function normalize_event_category($value, $allowed_categories = null) {
        $value = trim(wp_strip_all_tags((string) $value));
        if ($value === '') {
            return '';
        }

        $categories = is_array($allowed_categories) ? $allowed_categories : self::get_event_categories();
        return in_array($value, $categories, true) ? $value : '';
    }

    private static function settings_page_option_keys() {
        $keys = [
            'sc_twilio_account_sid',
            'sc_twilio_api_key_sid',
            'sc_twilio_api_key_secret',
            'sc_twilio_auth_token',
            'sc_twilio_from',
            'sc_twilio_messaging_service_sid',
            'sc_twilio_sms_cost_gsm_segment',
            'sc_twilio_sms_cost_unicode_segment',
            'sc_email_from_address',
            'sc_twilio_sid',
            'sc_twilio_token',
            'sc_event_categories',
        ];

        global $wpdb;
        $t = self::tables();
        $prefixed = $wpdb->get_col(
            "SELECT option_key
             FROM {$t['settings']}
             WHERE option_key LIKE 'sc_twilio_%'
                OR option_key LIKE 'sc_settings_%'"
        );
        if (is_array($prefixed) && !empty($prefixed)) {
            $keys = array_merge($keys, $prefixed);
        }

        $keys = array_values(array_unique(array_filter(array_map('sanitize_key', $keys))));
        return apply_filters('sc_settings_option_keys', $keys);
    }

    private static function delete_settings_page_options() {
        foreach (self::settings_page_option_keys() as $option_name) {
            self::delete_plugin_setting($option_name);
            delete_option($option_name);
        }
    }

    private static function send_csv($filename, $headers, $rows, $map_callback) {
        $rows = self::maybe_sort_export_rows($rows, $map_callback, count((array) $headers));
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . sanitize_file_name($filename));

        $output = fopen('php://output', 'w');
        if ($output === false) {
            wp_die(__('Unable to generate CSV.', 'schedule-checkin'));
        }

        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $map_callback($row));
        }
        fclose($output);
        exit;
    }

    private static function send_excel($filename, $headers, $rows, $map_callback, $meta_rows = []) {
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            wp_die(__('Excel library not installed. Run composer install in the plugin folder.', 'schedule-checkin'));
        }

        $rows = self::maybe_sort_export_rows($rows, $map_callback, count((array) $headers));

        nocache_headers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename=' . sanitize_file_name($filename));

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $row_index = 1;

        if (!empty($meta_rows)) {
            foreach ($meta_rows as $meta_row) {
                $sheet->setCellValue('A' . $row_index, (string) ($meta_row[0] ?? ''));
                $sheet->setCellValue('B' . $row_index, (string) ($meta_row[1] ?? ''));
                $row_index++;
            }
            $row_index++;
        }

        $sheet->fromArray($headers, null, 'A' . $row_index);
        $row_index++;
        foreach ($rows as $row) {
            $sheet->fromArray((array) $map_callback($row), null, 'A' . $row_index);
            $row_index++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private static function render_pdf_view($title, $headers, $rows, $map_callback, $filename = '', $filters = []) {
        if (!class_exists('Dompdf\\Dompdf')) {
            wp_die(__('PDF library not installed. Run composer install in the plugin folder.', 'schedule-checkin'));
        }

        $rows = self::maybe_sort_export_rows($rows, $map_callback, count((array) $headers));

        $header_rows = [
            __('Title', 'schedule-checkin') => (string) $title,
            __('Date', 'schedule-checkin') => self::format_datetime_cst_12h(current_time('mysql')),
        ];
        if (!empty($filters) && is_array($filters)) {
            foreach ($filters as $label => $value) {
                $header_rows[(string) $label] = (string) $value;
            }
        } else {
            $header_rows[__('Filters', 'schedule-checkin')] = __('None', 'schedule-checkin');
        }

        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans, Arial, sans-serif;color:#1f2937;} table{width:100%;border-collapse:collapse;margin-top:12px;} th,td{border:1px solid #d1d5db;padding:6px;text-align:left;font-size:12px;} h1{margin:0 0 6px;font-size:20px;} .meta{color:#6b7280;font-size:11px;} .header-box{border:1px solid #d1d5db;border-radius:6px;padding:8px 10px;background:#f9fafb;margin-bottom:10px;} .header-row{font-size:11px;line-height:1.5;margin:2px 0;} .header-label{display:inline-block;min-width:110px;font-weight:700;color:#374151;} .header-value{color:#111827;}</style></head><body>';
        $html .= '<div class="header-box">';
        foreach ($header_rows as $label => $value) {
            $html .= '<div class="header-row"><span class="header-label">' . esc_html($label) . ':</span> <span class="header-value">' . esc_html($value) . '</span></div>';
        }
        $html .= '</div>';
        $html .= '<table><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . esc_html($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if (empty($rows)) {
            $html .= '<tr><td colspan="' . (int) count($headers) . '">' . esc_html__('No data available.', 'schedule-checkin') . '</td></tr>';
        } else {
            foreach ($rows as $row) {
                $cells = (array) $map_callback($row);
                $html .= '<tr>';
                foreach ($cells as $cell) {
                    $html .= '<td>' . esc_html((string) $cell) . '</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table></body></html>';

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        nocache_headers();
        header('Content-Type: application/pdf');
        $download_name = $filename ?: self::build_export_filename(sanitize_title($title), 'pdf');
        header('Content-Disposition: attachment; filename=' . sanitize_file_name($download_name));
        echo $dompdf->output();
        exit;
    }

    private static function maybe_sort_export_rows($rows, $map_callback, $column_count) {
        $column_count = (int) $column_count;
        if ($column_count <= 0 || !is_array($rows)) {
            return $rows;
        }

        if (!isset($_POST['sort_column'])) {
            return $rows;
        }

        $column_index = absint($_POST['sort_column']);
        if ($column_index < 0 || $column_index >= $column_count) {
            return $rows;
        }

        $direction = strtolower(sanitize_text_field($_POST['sort_dir'] ?? 'asc'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        usort($rows, static function ($left, $right) use ($map_callback, $column_index, $direction) {
            $left_cells = (array) $map_callback($left);
            $right_cells = (array) $map_callback($right);

            $left_value = self::sortable_cell_value($left_cells[$column_index] ?? '');
            $right_value = self::sortable_cell_value($right_cells[$column_index] ?? '');

            $comparison = 0;
            if (is_numeric($left_value) && is_numeric($right_value)) {
                $comparison = ((float) $left_value) <=> ((float) $right_value);
            } else {
                $left_time = strtotime((string) $left_value);
                $right_time = strtotime((string) $right_value);
                if ($left_time !== false && $right_time !== false) {
                    $comparison = ((int) $left_time) <=> ((int) $right_time);
                } else {
                    $comparison = strcasecmp((string) $left_value, (string) $right_value);
                }
            }

            return $direction === 'desc' ? (0 - $comparison) : $comparison;
        });

        return $rows;
    }

    private static function sortable_cell_value($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $time_value = strtotime($value);
        if ($time_value !== false) {
            return (int) $time_value;
        }

        $numeric = preg_replace('/[^\d\.-]/', '', $value);
        if ($numeric !== '' && is_numeric($numeric)) {
            return (float) $numeric;
        }

        return $value;
    }

    public static function filter_wp_mail_from($email) {
        return self::$plugin_email_from !== '' ? self::$plugin_email_from : $email;
    }

    public static function filter_wp_mail_from_name($name) {
        return self::$plugin_email_from_name !== '' ? self::$plugin_email_from_name : $name;
    }

    private static function send_plugin_email($to, $subject, $message) {
        $to = sanitize_email((string) $to);
        if ($to === '') {
            return false;
        }

        $configured_from = sanitize_email((string) self::get_plugin_setting('sc_email_from_address', ''));
        $use_custom_from = ($configured_from !== '' && is_email($configured_from));

        self::$plugin_email_from = $use_custom_from ? $configured_from : '';
        self::$plugin_email_from_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        if ($use_custom_from) {
            add_filter('wp_mail_from', [__CLASS__, 'filter_wp_mail_from']);
        }
        add_filter('wp_mail_from_name', [__CLASS__, 'filter_wp_mail_from_name']);

        $sent = wp_mail($to, (string) $subject, (string) $message);

        if ($use_custom_from) {
            remove_filter('wp_mail_from', [__CLASS__, 'filter_wp_mail_from']);
        }
        remove_filter('wp_mail_from_name', [__CLASS__, 'filter_wp_mail_from_name']);

        self::$plugin_email_from = '';
        self::$plugin_email_from_name = '';

        return (bool) $sent;
    }

    private static function cst_timezone() {
        return new DateTimeZone('America/Chicago');
    }

    private static function to_cst_datetime($value) {
        if (empty($value)) {
            return null;
        }

        try {
            $site_tz = wp_timezone();
            $dt = new DateTime($value, $site_tz);
            $dt->setTimezone(self::cst_timezone());
            return $dt;
        } catch (Exception $e) {
            return null;
        }
    }

    private static function from_cst_datetime_to_storage(DateTime $dt) {
        $storage = clone $dt;
        $storage->setTimezone(wp_timezone());
        return $storage->format('Y-m-d H:i:s');
    }

    private static function normalize_datetime_cst($value) {
        if (empty($value)) {
            return '';
        }

        try {
            $dt = DateTime::createFromFormat('Y-m-d\\TH:i', $value, self::cst_timezone());
            if (!$dt) {
                return '';
            }
            return self::from_cst_datetime_to_storage($dt);
        } catch (Exception $e) {
            return '';
        }
    }

    private static function for_input_datetime_cst($value) {
        $dt = self::to_cst_datetime($value);
        if (!$dt) {
            return '';
        }

        return $dt->format('Y-m-d\\TH:i');
    }

    private static function format_range_cst($start, $end) {
        $start_dt = self::to_cst_datetime($start);
        $end_dt = self::to_cst_datetime($end);
        if (!$start_dt || !$end_dt) {
            return '';
        }

        return $start_dt->format('m/d/Y h:i A') . ' CST - ' . $end_dt->format('m/d/Y h:i A') . ' CST';
    }

    private static function format_datetime_cst_12h($value) {
        $dt = self::to_cst_datetime($value);
        if (!$dt) {
            return '';
        }

        return $dt->format('m/d/Y h:i A') . ' CST';
    }

    private static function format_phone_for_display($phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 3), substr($digits, 3, 3), substr($digits, 6));
        }
        if ($digits !== '') {
            return $digits;
        }

        return __('No phone', 'schedule-checkin');
    }

    private static function render_event_category_badge($category) {
        $category = trim((string) $category);
        if ($category === '') {
            return '&mdash;';
        }

        if (function_exists('mb_strtolower')) {
            $category_key = mb_strtolower($category, 'UTF-8');
        } else {
            $category_key = strtolower($category);
        }

        $hash = sprintf('%u', crc32($category_key));
        $palette_index = ((int) $hash) % 25;
        return '<span class="sc-category-badge sc-category-badge-' . (int) $palette_index . '">' . esc_html($category) . '</span>';
    }

    private static function apply_task_title_schema($schema, DateTime $start_cst, DateTime $end_cst) {
        $replacements = [
            '{start_cst}' => $start_cst->format('m/d/Y h:i A') . ' CST',
            '{end_cst}' => $end_cst->format('m/d/Y h:i A') . ' CST',
            '{start_date_cst}' => $start_cst->format('m/d/Y'),
            '{end_date_cst}' => $end_cst->format('m/d/Y'),
            '{start_time_cst}' => $start_cst->format('h:i A') . ' CST',
            '{end_time_cst}' => $end_cst->format('h:i A') . ' CST',
        ];

        return strtr($schema, $replacements);
    }

    private static function normalize_datetime($value) {
        if (empty($value)) {
            return '';
        }

        $timestamp = strtotime(str_replace('T', ' ', $value));
        if (!$timestamp) {
            return '';
        }

        return gmdate('Y-m-d H:i:s', $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS));
    }

    private static function for_input_datetime($value) {
        if (!$value) {
            return '';
        }

        return str_replace(' ', 'T', substr($value, 0, 16));
    }

    private static function period_start($period) {
        $now = current_time('timestamp');
        switch ($period) {
            case 'day':
                return gmdate('Y-m-d H:i:s', strtotime('-1 day', $now));
            case 'week':
                return gmdate('Y-m-d H:i:s', strtotime('-1 week', $now));
            case 'year':
                return gmdate('Y-m-d H:i:s', strtotime('-1 year', $now));
            case 'month':
            default:
                return gmdate('Y-m-d H:i:s', strtotime('-1 month', $now));
        }
    }

    private static function days_until_event_start($event_start_datetime) {
        $start = self::to_cst_datetime($event_start_datetime);
        if (!$start) {
            return -1;
        }

        $now = new DateTime('now', self::cst_timezone());
        $today = new DateTime($now->format('Y-m-d') . ' 00:00:00', self::cst_timezone());
        $start_day = new DateTime($start->format('Y-m-d') . ' 00:00:00', self::cst_timezone());
        return (int) $today->diff($start_day)->format('%r%a');
    }

    private static function communication_exists($volunteer_id, $event_id, $campaign_key) {
        global $wpdb;
        $t = self::tables();

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['comm_logs']} WHERE volunteer_id = %d AND event_id = %d AND campaign_key = %s LIMIT 1",
            $volunteer_id,
            $event_id,
            $campaign_key
        ));
    }

    private static function send_preferred_template_message($volunteer_id, $event_id, $assignment_id, $email_template_id, $sms_template_id, $purpose, $extra_tokens = [], $campaign_key = '') {
        global $wpdb;
        $t = self::tables();

        $volunteer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['volunteers']} WHERE id = %d", $volunteer_id));
        if (!$volunteer) {
            return false;
        }
        if (isset($volunteer->is_active) && (int) $volunteer->is_active !== 1) {
            return false;
        }

        $event = null;
        if ($event_id) {
            $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['events']} WHERE id = %d", $event_id));
        }

        $preferred = in_array((string) ($volunteer->preferred_contact_method ?? 'email'), ['email', 'sms'], true)
            ? (string) $volunteer->preferred_contact_method
            : 'email';

        $email_template = $email_template_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['comm_templates']} WHERE id = %d AND channel = 'email'", $email_template_id)) : null;
        $sms_template = $sms_template_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['comm_templates']} WHERE id = %d AND channel = 'sms'", $sms_template_id)) : null;

        $channel = $preferred;
        $template = $channel === 'sms' ? $sms_template : $email_template;
        if (!$template) {
            $channel = $channel === 'sms' ? 'email' : 'sms';
            $template = $channel === 'sms' ? $sms_template : $email_template;
        }
        if (!$template) {
            return false;
        }

        $tokens = array_merge([
            'volunteer_name' => (string) ($volunteer->name ?? ''),
            'volunteer_first_name' => self::volunteer_first_name((string) ($volunteer->name ?? '')),
            'event_title' => (string) ($event->title ?? ''),
            'task_title' => self::volunteer_task_title($assignment_id),
            'event_start' => self::format_datetime_cst_12h($event->start_datetime ?? ''),
            'event_end' => self::format_datetime_cst_12h($event->end_datetime ?? ''),
            'volunteer_start_datetime_cst' => self::volunteer_start_datetime_cst($assignment_id, $event),
            'volunteer_end_datetime_cst' => self::volunteer_end_datetime_cst($assignment_id, $event),
            'site_name' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            'days_before' => '',
        ], $extra_tokens);

        $subject = self::render_template_tokens((string) ($template->subject ?? ''), $tokens);
        $message = self::render_template_tokens((string) ($template->body ?? ''), $tokens);

        $status = 'sent';
        $error_message = '';
        $recipient = '';

        if ($channel === 'sms') {
            $recipient = self::normalize_sms_number((string) ($volunteer->phone ?? ''));
            if ($recipient === '') {
                $status = 'failed';
                $error_message = 'Missing or invalid phone for SMS.';
            } else {
                $sms_result = self::send_sms_message($recipient, $message);
                $status = $sms_result['success'] ? 'sent' : 'failed';
                $error_message = $sms_result['error'];
            }
        } else {
            $recipient = sanitize_email((string) ($volunteer->email ?? ''));
            if ($recipient === '') {
                $status = 'failed';
                $error_message = 'Missing email address.';
            } else {
                $mail_ok = self::send_plugin_email($recipient, $subject !== '' ? $subject : __('Message from volunteer coordinator', 'schedule-checkin'), $message);
                $status = $mail_ok ? 'sent' : 'failed';
                if (!$mail_ok) {
                    $error_message = 'wp_mail failed.';
                }
            }
        }

        self::log_communication([
            'volunteer_id' => $volunteer_id,
            'event_id' => $event_id ?: null,
            'assignment_id' => $assignment_id ?: null,
            'template_id' => (int) $template->id,
            'channel' => $channel,
            'purpose' => $purpose,
            'campaign_key' => $campaign_key ?: null,
            'recipient' => $recipient,
            'subject' => $subject,
            'message' => $message,
            'status' => $status,
            'error_message' => $error_message,
            'created_at' => current_time('mysql'),
        ]);

        return $status === 'sent';
    }

    private static function render_template_tokens($text, $tokens) {
        $replace = [];
        foreach ($tokens as $key => $value) {
            $replace['{' . $key . '}'] = (string) $value;
        }
        return strtr((string) $text, $replace);
    }

    private static function volunteer_first_name($name) {
        $name = trim((string) $name);
        if ($name === '') {
            return '';
        }

        $parts = preg_split('/\s+/', $name);
        return (string) ($parts[0] ?? '');
    }

    private static function volunteer_start_datetime_cst($assignment_id, $event = null) {
        global $wpdb;
        $t = self::tables();

        $assignment_id = (int) $assignment_id;
        if ($assignment_id > 0) {
            $task_start = $wpdb->get_var($wpdb->prepare(
                "SELECT t.start_datetime
                 FROM {$t['assignments']} a
                 INNER JOIN {$t['tasks']} t ON t.id = a.task_id
                 WHERE a.id = %d
                 LIMIT 1",
                $assignment_id
            ));
            if (!empty($task_start)) {
                return self::format_datetime_cst_12h($task_start);
            }
        }

        if ($event && !empty($event->start_datetime)) {
            return self::format_datetime_cst_12h($event->start_datetime);
        }

        return '';
    }

    private static function volunteer_end_datetime_cst($assignment_id, $event = null) {
        global $wpdb;
        $t = self::tables();

        $assignment_id = (int) $assignment_id;
        if ($assignment_id > 0) {
            $task_end = $wpdb->get_var($wpdb->prepare(
                "SELECT t.end_datetime
                 FROM {$t['assignments']} a
                 INNER JOIN {$t['tasks']} t ON t.id = a.task_id
                 WHERE a.id = %d
                 LIMIT 1",
                $assignment_id
            ));
            if (!empty($task_end)) {
                return self::format_datetime_cst_12h($task_end);
            }
        }

        if ($event && !empty($event->end_datetime)) {
            return self::format_datetime_cst_12h($event->end_datetime);
        }

        return '';
    }

    private static function volunteer_task_title($assignment_id) {
        global $wpdb;
        $t = self::tables();

        $assignment_id = (int) $assignment_id;
        if ($assignment_id <= 0) {
            return '';
        }

        $task_title = $wpdb->get_var($wpdb->prepare(
            "SELECT t.title
             FROM {$t['assignments']} a
             INNER JOIN {$t['tasks']} t ON t.id = a.task_id
             WHERE a.id = %d
             LIMIT 1",
            $assignment_id
        ));

        return (string) ($task_title ?? '');
    }

    public static function send_signup_thank_you_message($volunteer_id, $event_id, $assignment_id, $extra_tokens = []) {
        global $wpdb;
        $t = self::tables();

        $email_template_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['comm_templates']} WHERE channel = 'email' AND name = %s LIMIT 1",
            'Thank you for signing up for this event'
        ));
        $sms_template_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['comm_templates']} WHERE channel = 'sms' AND name = %s LIMIT 1",
            'Thank you for signing up for this event'
        ));

        if ($email_template_id <= 0 && $sms_template_id <= 0) {
            return false;
        }

        return self::send_preferred_template_message(
            (int) $volunteer_id,
            (int) $event_id,
            (int) $assignment_id,
            $email_template_id,
            $sms_template_id,
            'auto_signup_thank_you',
            (array) $extra_tokens
        );
    }

    private static function build_event_sms_estimate_context($events) {
        global $wpdb;
        $t = self::tables();

        $context = [];
        foreach ((array) $events as $event) {
            $event_id = (int) ($event->id ?? 0);
            if ($event_id <= 0) {
                continue;
            }

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT a.id AS assignment_id,
                        v.id AS volunteer_id,
                        v.name,
                        v.phone,
                                                t.title AS task_title,
                                                t.start_datetime AS task_start_datetime,
                                                t.end_datetime AS task_end_datetime
                 FROM {$t['assignments']} a
                 INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
                 LEFT JOIN {$t['tasks']} t ON t.id = a.task_id
                 WHERE a.event_id = %d
                   AND a.status <> 'substitute_pool'",
                $event_id
            ));
            $recipient_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT a.volunteer_id)
                 FROM {$t['assignments']} a
                 INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
                 WHERE a.event_id = %d
                   AND a.assignment_type = 'scheduled'
                   AND a.task_id IS NOT NULL
                   AND a.status <> 'substitute_pool'
                   AND v.preferred_contact_method = 'sms'",
                $event_id
            ));

            $scheduled_ids = [];
            $longest_name = '';
            $longest_first_name = '';
            $longest_task_title = '';
            $longest_start = self::format_datetime_cst_12h((string) ($event->start_datetime ?? ''));
            $longest_end = self::format_datetime_cst_12h((string) ($event->end_datetime ?? ''));

            foreach ($rows as $row) {
                $volunteer_id = (int) ($row->volunteer_id ?? 0);
                if ($volunteer_id > 0) {
                    $scheduled_ids[$volunteer_id] = true;
                }

                $name = (string) ($row->name ?? '');
                if (self::string_length($name) > self::string_length($longest_name)) {
                    $longest_name = $name;
                }

                $first_name = self::volunteer_first_name($name);
                if (self::string_length($first_name) > self::string_length($longest_first_name)) {
                    $longest_first_name = $first_name;
                }

                $task_title = (string) ($row->task_title ?? '');
                if (self::string_length($task_title) > self::string_length($longest_task_title)) {
                    $longest_task_title = $task_title;
                }

                $assignment_start = self::format_datetime_cst_12h((string) ($row->task_start_datetime ?: $event->start_datetime));
                if (self::string_length($assignment_start) > self::string_length($longest_start)) {
                    $longest_start = $assignment_start;
                }

                $assignment_end = self::format_datetime_cst_12h((string) ($row->task_end_datetime ?: $event->end_datetime));
                if (self::string_length($assignment_end) > self::string_length($longest_end)) {
                    $longest_end = $assignment_end;
                }
            }

            $context[(string) $event_id] = [
                'recipient_count' => max(0, $recipient_count),
                'tokens' => [
                    'volunteer_name' => $longest_name !== '' ? $longest_name : 'Alexandria Montgomery',
                    'volunteer_first_name' => $longest_first_name !== '' ? $longest_first_name : 'Alexandria',
                    'event_title' => (string) ($event->title ?? ''),
                    'task_title' => $longest_task_title !== '' ? $longest_task_title : 'Greeting Team',
                    'event_start' => self::format_datetime_cst_12h((string) ($event->start_datetime ?? '')),
                    'event_end' => self::format_datetime_cst_12h((string) ($event->end_datetime ?? '')),
                    'volunteer_start_datetime_cst' => $longest_start,
                    'volunteer_end_datetime_cst' => $longest_end,
                    'days_before' => '30',
                    'site_name' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
                ],
            ];
        }

        return $context;
    }

    private static function string_length($value) {
        $value = (string) $value;
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private static function normalize_sms_number($phone) {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 10) {
            $digits = '1' . $digits;
        }
        if (strlen($digits) < 11) {
            return '';
        }
        return '+' . $digits;
    }

    private static function send_sms_message($to, $body) {
        $account_sid = (string) self::get_plugin_setting('sc_twilio_account_sid', '', 'sc_twilio_sid');
        $api_key_sid = (string) self::get_plugin_setting('sc_twilio_api_key_sid', '');
        $api_key_secret = (string) self::get_plugin_setting('sc_twilio_api_key_secret', '');
        $auth_token = (string) self::get_plugin_setting('sc_twilio_auth_token', '', 'sc_twilio_token');
        $from = (string) self::get_plugin_setting('sc_twilio_from', '');
        $messaging_service_sid = (string) self::get_plugin_setting('sc_twilio_messaging_service_sid', '');

        if ($account_sid === '') {
            return ['success' => false, 'error' => 'Twilio Account SID is not configured.'];
        }

        $auth_user = '';
        $auth_pass = '';
        if ($api_key_sid !== '' && $api_key_secret !== '') {
            $auth_user = $api_key_sid;
            $auth_pass = $api_key_secret;
        } elseif ($auth_token !== '') {
            $auth_user = $account_sid;
            $auth_pass = $auth_token;
        }

        if ($auth_user === '' || $auth_pass === '') {
            return ['success' => false, 'error' => 'Twilio credentials are not configured.'];
        }

        if ($messaging_service_sid === '' && $from === '') {
            return ['success' => false, 'error' => 'Twilio From Number or Messaging Service SID is required.'];
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($account_sid) . '/Messages.json';

        $payload = [
            'To' => $to,
            'Body' => $body,
        ];
        if ($messaging_service_sid !== '') {
            $payload['MessagingServiceSid'] = $messaging_service_sid;
        } else {
            $payload['From'] = $from;
        }

        $response = wp_remote_post($url, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($auth_user . ':' . $auth_pass),
            ],
            'body' => $payload,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return ['success' => false, 'error' => 'Twilio HTTP ' . $status_code];
        }

        return ['success' => true, 'error' => ''];
    }

    private static function log_communication($data) {
        global $wpdb;
        $t = self::tables();
        $wpdb->insert($t['comm_logs'], $data);
    }

    private static function render_admin_notices() {
        $messages = [
            'event_saved' => ['success', __('Event saved.', 'schedule-checkin')],
            'event_deleted' => ['success', __('Event deleted.', 'schedule-checkin')],
            'task_added' => ['success', __('Task added.', 'schedule-checkin')],
            'task_updated' => ['success', __('Task updated.', 'schedule-checkin')],
            'task_deleted' => ['success', __('Task deleted.', 'schedule-checkin')],
            'tasks_deleted_all' => ['success', __('All tasks for this event were deleted.', 'schedule-checkin')],
            'tasks_generated' => ['success', __('Tasks generated from event timeframe.', 'schedule-checkin')],
            'tasks_replaced_generated' => ['success', __('Existing tasks were replaced and new tasks were generated.', 'schedule-checkin')],
            'assignment_saved' => ['success', __('Assignment times saved.', 'schedule-checkin')],
            'substitute_assigned' => ['success', __('Substitute assigned to selected task slot.', 'schedule-checkin')],
            'assignment_moved_to_substitute' => ['success', __('Assignment removed from slot and moved to substitute pool.', 'schedule-checkin')],
            'assignment_deleted' => ['success', __('Assignment deleted from this event.', 'schedule-checkin')],
            'checkout_all_done' => ['success', __('Bulk checkout complete.', 'schedule-checkin')],
            'volunteer_saved' => ['success', __('Volunteer updated.', 'schedule-checkin')],
            'volunteers_merged' => ['success', __('Volunteers merged successfully.', 'schedule-checkin')],
            'log_updated' => ['success', __('Log entry updated.', 'schedule-checkin')],
            'log_deleted' => ['success', __('Log entry deleted.', 'schedule-checkin')],
            'database_reset' => ['success', __('Plugin database was deleted and recreated to the current schema.', 'schedule-checkin')],
            'database_reset_with_settings' => ['success', __('Plugin database was deleted and recreated, and Settings page options were also deleted.', 'schedule-checkin')],
            'comm_settings_saved' => ['success', __('Communication settings saved.', 'schedule-checkin')],
            'comm_template_saved' => ['success', __('Communication template saved.', 'schedule-checkin')],
            'comm_template_updated' => ['success', __('Communication template updated.', 'schedule-checkin')],
            'comm_template_deleted' => ['success', __('Communication template deleted.', 'schedule-checkin')],
            'comm_sent' => ['success', __('Communication delivery attempt completed.', 'schedule-checkin')],
            'comm_test_sms_sent' => ['success', __('Test SMS sent.', 'schedule-checkin')],
            'comm_test_email_sent' => ['success', __('Test email sent.', 'schedule-checkin')],
            'assignment_report_sent' => ['success', __('Assignment report sent to the event owner.', 'schedule-checkin')],
            'invalid_event_data' => ['error', __('Please complete all required event fields.', 'schedule-checkin')],
            'event_time_order' => ['error', __('Event end time must be after start time.', 'schedule-checkin')],
            'invalid_event_owner_email' => ['error', __('Event owner email must be a valid email address.', 'schedule-checkin')],
            'invalid_from_email' => ['error', __('Plugin From Email must be a valid email address.', 'schedule-checkin')],
            'assignment_report_owner_missing' => ['error', __('Set a valid event owner email before sending assignment reports.', 'schedule-checkin')],
            'assignment_report_no_rows' => ['error', __('No assignment slots were found for this event.', 'schedule-checkin')],
            'assignment_report_send_failed' => ['error', __('Assignment report email failed to send. Check site mail settings.', 'schedule-checkin')],
            'invalid_pin' => ['error', __('Admin PIN must be 4 to 10 digits.', 'schedule-checkin')],
            'missing_event' => ['error', __('The selected event was not found.', 'schedule-checkin')],
            'invalid_task_data' => ['error', __('Please complete all required task fields.', 'schedule-checkin')],
            'invalid_task_schema' => ['error', __('Task schema cannot be empty.', 'schedule-checkin')],
            'replace_confirm_required' => ['error', __('Type REPLACE to confirm replacing existing tasks.', 'schedule-checkin')],
            'task_generation_failed' => ['error', __('Unable to generate tasks for this timeframe.', 'schedule-checkin')],
            'task_wizard_event_datetime_invalid' => ['error', __('Task Wizard could not read this event\'s start or end date/time. Re-save the event and try again.', 'schedule-checkin')],
            'task_wizard_event_time_order' => ['error', __('Task Wizard requires event end date/time to be after event start date/time.', 'schedule-checkin')],
            'task_time_order' => ['error', __('Task end time must be after start time.', 'schedule-checkin')],
            'task_outside_event' => ['error', __('Task time must be inside the event time range.', 'schedule-checkin')],
            'invalid_slot' => ['error', __('Selected slot is invalid for this task.', 'schedule-checkin')],
            'slot_filled' => ['error', __('That slot is already filled.', 'schedule-checkin')],
            'assignment_missing' => ['error', __('Assignment not found.', 'schedule-checkin')],
            'reset_confirm_required' => ['error', __('To reset the database, type exactly: I KNOW WHAT I AM DOING', 'schedule-checkin')],
            'reset_acknowledge_required' => ['error', __('Please confirm that this action cannot be undone.', 'schedule-checkin')],
            'reset_scope_invalid' => ['error', __('Please select a valid reset scope option.', 'schedule-checkin')],
            'reset_settings_confirm_required' => ['error', __('To delete Settings page options, type exactly: DELETE SETTINGS', 'schedule-checkin')],
            'comm_test_sms_required' => ['error', __('Please enter a valid test phone number.', 'schedule-checkin')],
            'comm_test_sms_failed' => ['error', __('Test SMS failed. Check Twilio settings and logs for details.', 'schedule-checkin')],
            'comm_test_email_required' => ['error', __('Please enter a valid test email address.', 'schedule-checkin')],
            'comm_test_email_failed' => ['error', __('Test email failed. Check your site mail configuration and logs.', 'schedule-checkin')],
            'comm_template_required' => ['error', __('Template name and message body are required.', 'schedule-checkin')],
            'time_order' => ['error', __('Check-out time must be after check-in time.', 'schedule-checkin')],
            'missing_volunteer' => ['error', __('Please select a volunteer first.', 'schedule-checkin')],
            'volunteer_merge_invalid' => ['error', __('Select two different volunteers to merge.', 'schedule-checkin')],
            'volunteer_merge_failed' => ['error', __('Volunteer merge failed. No changes were saved.', 'schedule-checkin')],
            'volunteer_contact_invalid' => ['error', __('Name, a valid email address, and phone number in ###-###-#### format are required.', 'schedule-checkin')],
            'invalid_report' => ['error', __('The selected report export is invalid.', 'schedule-checkin')],
            'excel_library_missing' => ['error', __('Excel library not installed. Run composer install in the plugin folder.', 'schedule-checkin')],
            'pdf_library_missing' => ['error', __('PDF library not installed. Run composer install in the plugin folder.', 'schedule-checkin')],
        ];

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Excel export requires Composer dependencies. Run composer install in the plugin folder.', 'schedule-checkin') . '</p></div>';
        }
        if (!class_exists('Dompdf\\Dompdf')) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('PDF export requires Composer dependencies. Run composer install in the plugin folder.', 'schedule-checkin') . '</p></div>';
        }

        $code = isset($_GET['sc_msg']) ? sanitize_key($_GET['sc_msg']) : '';
        if (isset($messages[$code])) {
            $type = $messages[$code][0] === 'error' ? 'notice notice-error' : 'notice notice-success is-dismissible';
            echo '<div class="' . esc_attr($type) . '"><p>' . esc_html($messages[$code][1]) . '</p></div>';
        }

        $error = isset($_GET['sc_error']) ? sanitize_key($_GET['sc_error']) : '';
        if ($error && isset($messages[$error])) {
            echo '<div class="notice notice-error"><p>' . esc_html($messages[$error][1]) . '</p></div>';
        }
    }

    private static function render_dependency_status() {
        $has_spreadsheet = class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet');
        $has_dompdf = class_exists('Dompdf\\Dompdf');

        $all_ok = $has_spreadsheet && $has_dompdf;
        $summary = $all_ok
            ? __('Dependency Status: Ready', 'schedule-checkin')
            : __('Dependency Status: Action Needed', 'schedule-checkin');

        echo '<details class="sc-deps-card"' . ($all_ok ? '' : ' open') . '>';
        echo '<summary>' . esc_html($summary) . '</summary>';
        echo '<p>' . esc_html__('Native exports require these Composer libraries:', 'schedule-checkin') . '</p>';

        echo '<ul class="sc-deps-list">';
        echo '<li><span class="sc-dep-dot ' . ($has_spreadsheet ? 'ok' : 'bad') . '"></span><strong>PhpSpreadsheet</strong> <span>' . esc_html($has_spreadsheet ? __('Installed', 'schedule-checkin') : __('Missing', 'schedule-checkin')) . '</span></li>';
        echo '<li><span class="sc-dep-dot ' . ($has_dompdf ? 'ok' : 'bad') . '"></span><strong>Dompdf</strong> <span>' . esc_html($has_dompdf ? __('Installed', 'schedule-checkin') : __('Missing', 'schedule-checkin')) . '</span></li>';
        echo '</ul>';

        if (!$has_spreadsheet || !$has_dompdf) {
            echo '<p><code>composer install --no-dev</code></p>';
        }

        echo '</details>';
    }
}
