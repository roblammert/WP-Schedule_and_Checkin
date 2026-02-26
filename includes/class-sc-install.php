<?php

if (!defined('ABSPATH')) {
    exit;
}

class SC_Install {
    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $events = $wpdb->prefix . 'sc_events';
        $tasks = $wpdb->prefix . 'sc_tasks';
        $volunteers = $wpdb->prefix . 'sc_volunteers';
        $assignments = $wpdb->prefix . 'sc_assignments';
        $checkins = $wpdb->prefix . 'sc_checkins';
        $templates = $wpdb->prefix . 'sc_comm_templates';
        $comm_logs = $wpdb->prefix . 'sc_comm_logs';
        $settings = $wpdb->prefix . 'sc_settings';

        dbDelta("CREATE TABLE {$events} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            event_category VARCHAR(100) NULL,
            description LONGTEXT NULL,
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NOT NULL,
            image_id BIGINT UNSIGNED NULL,
            visible_fields TEXT NULL,
            require_checkout TINYINT(1) NOT NULL DEFAULT 1,
            allow_substitutes TINYINT(1) NOT NULL DEFAULT 1,
            admin_pin VARCHAR(10) NOT NULL,
            reminder_1_enabled TINYINT(1) NOT NULL DEFAULT 0,
            reminder_1_days_before INT UNSIGNED NOT NULL DEFAULT 2,
            reminder_1_email_template_id BIGINT UNSIGNED NULL,
            reminder_1_sms_template_id BIGINT UNSIGNED NULL,
            reminder_2_enabled TINYINT(1) NOT NULL DEFAULT 0,
            reminder_2_days_before INT UNSIGNED NOT NULL DEFAULT 1,
            reminder_2_email_template_id BIGINT UNSIGNED NULL,
            reminder_2_sms_template_id BIGINT UNSIGNED NULL,
            owner_name VARCHAR(255) NULL,
            owner_email VARCHAR(255) NULL,
            assignment_auto_enabled TINYINT(1) NOT NULL DEFAULT 0,
            assignment_auto_schedule VARCHAR(40) NULL,
            assignment_auto_last_sent_at DATETIME NULL,
            assignment_auto_final_sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$tasks} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            start_datetime DATETIME NOT NULL,
            end_datetime DATETIME NOT NULL,
            slots INT UNSIGNED NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY event_id (event_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$volunteers} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NULL,
            phone VARCHAR(50) NULL,
            preferred_contact_method VARCHAR(10) NOT NULL DEFAULT 'email',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            merged_into_volunteer_id BIGINT UNSIGNED NULL,
            merged_at DATETIME NULL,
            future_substitute_opt_in TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY email (email),
            KEY phone (phone),
            KEY merged_into_volunteer_id (merged_into_volunteer_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$assignments} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL,
            task_id BIGINT UNSIGNED NULL,
            volunteer_id BIGINT UNSIGNED NOT NULL,
            slot_number INT UNSIGNED NULL DEFAULT NULL,
            assignment_type VARCHAR(20) NOT NULL DEFAULT 'scheduled',
            planned_minutes INT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'assigned',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_task_slot (task_id, slot_number),
            KEY event_id (event_id),
            KEY volunteer_id (volunteer_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$checkins} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id BIGINT UNSIGNED NOT NULL,
            task_id BIGINT UNSIGNED NULL,
            assignment_id BIGINT UNSIGNED NULL,
            volunteer_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(20) NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT 'kiosk',
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY volunteer_id (volunteer_id),
            KEY created_at (created_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$templates} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            channel VARCHAR(10) NOT NULL,
            name VARCHAR(191) NOT NULL,
            subject VARCHAR(255) NULL,
            body LONGTEXT NOT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY channel (channel)
        ) {$charset};");

        dbDelta("CREATE TABLE {$comm_logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            volunteer_id BIGINT UNSIGNED NOT NULL,
            event_id BIGINT UNSIGNED NULL,
            assignment_id BIGINT UNSIGNED NULL,
            template_id BIGINT UNSIGNED NULL,
            channel VARCHAR(10) NOT NULL,
            purpose VARCHAR(50) NOT NULL,
            campaign_key VARCHAR(191) NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NULL,
            message LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            error_message TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY volunteer_id (volunteer_id),
            KEY event_id (event_id),
            KEY campaign_key (campaign_key),
            KEY created_at (created_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$settings} (
            option_key VARCHAR(191) NOT NULL,
            option_value LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (option_key)
        ) {$charset};");

        self::seed_default_templates($templates);
        self::migrate_legacy_settings_to_table($settings);

        add_option('sc_plugin_version', SC_PLUGIN_VERSION);
    }

    public static function maybe_upgrade() {
        $installed = get_option('sc_plugin_version', '0.0.0');
        if (version_compare((string) $installed, SC_PLUGIN_VERSION, '>=')) {
            return;
        }

        self::activate();
        self::run_compat_migrations();
        update_option('sc_plugin_version', SC_PLUGIN_VERSION);
    }

    public static function deactivate() {
        wp_clear_scheduled_hook('sc_process_reminders');
    }

    private static function run_compat_migrations() {
        global $wpdb;
        $assignments = $wpdb->prefix . 'sc_assignments';
        $volunteers = $wpdb->prefix . 'sc_volunteers';
        $checkins = $wpdb->prefix . 'sc_checkins';
        $events = $wpdb->prefix . 'sc_events';
        $templates = $wpdb->prefix . 'sc_comm_templates';
        $comm_logs = $wpdb->prefix . 'sc_comm_logs';
        $settings = $wpdb->prefix . 'sc_settings';

        $has_assignment_type = $wpdb->get_var("SHOW COLUMNS FROM {$assignments} LIKE 'assignment_type'");
        if (!$has_assignment_type) {
            $wpdb->query("ALTER TABLE {$assignments} ADD assignment_type VARCHAR(20) NOT NULL DEFAULT 'scheduled' AFTER slot_number");
        }

        $has_planned_minutes = $wpdb->get_var("SHOW COLUMNS FROM {$assignments} LIKE 'planned_minutes'");
        if (!$has_planned_minutes) {
            $wpdb->query("ALTER TABLE {$assignments} ADD planned_minutes INT UNSIGNED NULL AFTER assignment_type");
        }

        $has_future_sub = $wpdb->get_var("SHOW COLUMNS FROM {$volunteers} LIKE 'future_substitute_opt_in'");
        if (!$has_future_sub) {
            $wpdb->query("ALTER TABLE {$volunteers} ADD future_substitute_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER phone");
        }

        $has_preferred_contact = $wpdb->get_var("SHOW COLUMNS FROM {$volunteers} LIKE 'preferred_contact_method'");
        if (!$has_preferred_contact) {
            $wpdb->query("ALTER TABLE {$volunteers} ADD preferred_contact_method VARCHAR(10) NOT NULL DEFAULT 'email' AFTER phone");
        }

        $has_is_active = $wpdb->get_var("SHOW COLUMNS FROM {$volunteers} LIKE 'is_active'");
        if (!$has_is_active) {
            $wpdb->query("ALTER TABLE {$volunteers} ADD is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER preferred_contact_method");
        }

        $has_merged_into = $wpdb->get_var("SHOW COLUMNS FROM {$volunteers} LIKE 'merged_into_volunteer_id'");
        if (!$has_merged_into) {
            $wpdb->query("ALTER TABLE {$volunteers} ADD merged_into_volunteer_id BIGINT UNSIGNED NULL AFTER is_active");
        }

        $has_merged_at = $wpdb->get_var("SHOW COLUMNS FROM {$volunteers} LIKE 'merged_at'");
        if (!$has_merged_at) {
            $wpdb->query("ALTER TABLE {$volunteers} ADD merged_at DATETIME NULL AFTER merged_into_volunteer_id");
        }

        $event_columns = [
            'event_category' => "ALTER TABLE {$events} ADD event_category VARCHAR(100) NULL AFTER title",
            'reminder_1_enabled' => "ALTER TABLE {$events} ADD reminder_1_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER admin_pin",
            'reminder_1_days_before' => "ALTER TABLE {$events} ADD reminder_1_days_before INT UNSIGNED NOT NULL DEFAULT 2 AFTER reminder_1_enabled",
            'reminder_1_email_template_id' => "ALTER TABLE {$events} ADD reminder_1_email_template_id BIGINT UNSIGNED NULL AFTER reminder_1_days_before",
            'reminder_1_sms_template_id' => "ALTER TABLE {$events} ADD reminder_1_sms_template_id BIGINT UNSIGNED NULL AFTER reminder_1_email_template_id",
            'reminder_2_enabled' => "ALTER TABLE {$events} ADD reminder_2_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER reminder_1_sms_template_id",
            'reminder_2_days_before' => "ALTER TABLE {$events} ADD reminder_2_days_before INT UNSIGNED NOT NULL DEFAULT 1 AFTER reminder_2_enabled",
            'reminder_2_email_template_id' => "ALTER TABLE {$events} ADD reminder_2_email_template_id BIGINT UNSIGNED NULL AFTER reminder_2_days_before",
            'reminder_2_sms_template_id' => "ALTER TABLE {$events} ADD reminder_2_sms_template_id BIGINT UNSIGNED NULL AFTER reminder_2_email_template_id",
            'owner_name' => "ALTER TABLE {$events} ADD owner_name VARCHAR(255) NULL AFTER reminder_2_sms_template_id",
            'owner_email' => "ALTER TABLE {$events} ADD owner_email VARCHAR(255) NULL AFTER owner_name",
            'assignment_auto_enabled' => "ALTER TABLE {$events} ADD assignment_auto_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER owner_email",
            'assignment_auto_schedule' => "ALTER TABLE {$events} ADD assignment_auto_schedule VARCHAR(40) NULL AFTER assignment_auto_enabled",
            'assignment_auto_last_sent_at' => "ALTER TABLE {$events} ADD assignment_auto_last_sent_at DATETIME NULL AFTER assignment_auto_schedule",
            'assignment_auto_final_sent_at' => "ALTER TABLE {$events} ADD assignment_auto_final_sent_at DATETIME NULL AFTER assignment_auto_last_sent_at",
            'allow_substitutes' => "ALTER TABLE {$events} ADD allow_substitutes TINYINT(1) NOT NULL DEFAULT 1 AFTER require_checkout",
        ];
        foreach ($event_columns as $column_name => $sql) {
            $exists = $wpdb->get_var("SHOW COLUMNS FROM {$events} LIKE '{$column_name}'");
            if (!$exists) {
                $wpdb->query($sql);
            }
        }

        $task_column = $wpdb->get_row("SHOW COLUMNS FROM {$assignments} LIKE 'task_id'");
        if ($task_column && strtoupper((string) ($task_column->Null ?? 'NO')) !== 'YES') {
            $wpdb->query("ALTER TABLE {$assignments} MODIFY task_id BIGINT UNSIGNED NULL");
        }

        $slot_column = $wpdb->get_row("SHOW COLUMNS FROM {$assignments} LIKE 'slot_number'");
        if ($slot_column && strtoupper((string) ($slot_column->Null ?? 'NO')) !== 'YES') {
            $wpdb->query("ALTER TABLE {$assignments} MODIFY slot_number INT UNSIGNED NULL DEFAULT NULL");
        }

        $has_unique = $wpdb->get_var("SHOW INDEX FROM {$assignments} WHERE Key_name = 'uniq_task_slot'");
        if ($has_unique) {
            $wpdb->query("ALTER TABLE {$assignments} DROP INDEX uniq_task_slot");
            $wpdb->query("ALTER TABLE {$assignments} ADD UNIQUE KEY uniq_task_slot (task_id, slot_number)");
        }

        $checkins_task_column = $wpdb->get_row("SHOW COLUMNS FROM {$checkins} LIKE 'task_id'");
        if ($checkins_task_column && strtoupper((string) ($checkins_task_column->Null ?? 'NO')) !== 'YES') {
            $wpdb->query("ALTER TABLE {$checkins} MODIFY task_id BIGINT UNSIGNED NULL");
        }

        $checkins_assignment_column = $wpdb->get_row("SHOW COLUMNS FROM {$checkins} LIKE 'assignment_id'");
        if ($checkins_assignment_column && strtoupper((string) ($checkins_assignment_column->Null ?? 'NO')) !== 'YES') {
            $wpdb->query("ALTER TABLE {$checkins} MODIFY assignment_id BIGINT UNSIGNED NULL");
        }

        $legacy_checked_in = $wpdb->get_var("SHOW COLUMNS FROM {$assignments} LIKE 'checked_in_at'");
        if ($legacy_checked_in) {
            $wpdb->query("ALTER TABLE {$assignments} DROP COLUMN checked_in_at");
        }

        $legacy_checked_out = $wpdb->get_var("SHOW COLUMNS FROM {$assignments} LIKE 'checked_out_at'");
        if ($legacy_checked_out) {
            $wpdb->query("ALTER TABLE {$assignments} DROP COLUMN checked_out_at");
        }

        $legacy_checkout_source = $wpdb->get_var("SHOW COLUMNS FROM {$assignments} LIKE 'checkout_source'");
        if ($legacy_checkout_source) {
            $wpdb->query("ALTER TABLE {$assignments} DROP COLUMN checkout_source");
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$templates} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            channel VARCHAR(10) NOT NULL,
            name VARCHAR(191) NOT NULL,
            subject VARCHAR(255) NULL,
            body LONGTEXT NOT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY channel (channel)
        ) {$charset};");
        dbDelta("CREATE TABLE {$comm_logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            volunteer_id BIGINT UNSIGNED NOT NULL,
            event_id BIGINT UNSIGNED NULL,
            assignment_id BIGINT UNSIGNED NULL,
            template_id BIGINT UNSIGNED NULL,
            channel VARCHAR(10) NOT NULL,
            purpose VARCHAR(50) NOT NULL,
            campaign_key VARCHAR(191) NULL,
            recipient VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NULL,
            message LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'sent',
            error_message TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY volunteer_id (volunteer_id),
            KEY event_id (event_id),
            KEY campaign_key (campaign_key),
            KEY created_at (created_at)
        ) {$charset};
");
        dbDelta("CREATE TABLE {$settings} (
            option_key VARCHAR(191) NOT NULL,
            option_value LONGTEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (option_key)
        ) {$charset};
");

        self::seed_default_templates($templates);
        self::migrate_legacy_settings_to_table($settings);
    }

    private static function settings_option_keys() {
        return [
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
    }

    private static function migrate_legacy_settings_to_table($settings_table) {
        global $wpdb;

        $now = current_time('mysql');
        foreach (self::settings_option_keys() as $key) {
            $legacy_value = get_option($key, null);
            if ($legacy_value === null) {
                continue;
            }

            $stored_value = is_scalar($legacy_value) || $legacy_value === null
                ? (string) $legacy_value
                : wp_json_encode($legacy_value);

            $wpdb->replace($settings_table, [
                'option_key' => (string) $key,
                'option_value' => $stored_value,
                'updated_at' => $now,
            ]);

            delete_option($key);
        }
    }

    private static function seed_default_templates($templates_table) {
        global $wpdb;

        $task_sentence = 'The task you signed up for is {task_title} from {volunteer_start_datetime_cst} to {volunteer_end_datetime_cst}.';
        $defaults = [
            ['channel' => 'email', 'name' => 'Thank you for signing up for this event', 'subject' => 'Thank you for signing up', 'body' => 'Thank you for signing up for our event: {event_title}. The task you signed up for is {task_title} from {volunteer_start_datetime_cst} to {volunteer_end_datetime_cst}. We will see you there!'],
            ['channel' => 'email', 'name' => 'Reminder, you are scheduled in X days', 'subject' => 'Reminder: You are scheduled in {days_before} day(s)', 'body' => "Hi {volunteer_name},\n\nThis is a reminder that you are scheduled for {event_title} in {days_before} day(s).\n{$task_sentence}\nEvent Time: {event_start} to {event_end}\n\n- {site_name}"],
            ['channel' => 'email', 'name' => 'Reminder, you are scheduled tomorrow', 'subject' => 'Reminder: You are scheduled tomorrow', 'body' => "Hi {volunteer_name},\n\nReminder: you are scheduled tomorrow for {event_title}.\n{$task_sentence}\nEvent Time: {event_start} to {event_end}\n\n- {site_name}"],
            ['channel' => 'sms', 'name' => 'Thank you for signing up for this event', 'subject' => '', 'body' => 'Thank you for signing up for our event: {event_title}. The task you signed up for is {task_title} from {volunteer_start_datetime_cst} to {volunteer_end_datetime_cst}. We will see you there!'],
            ['channel' => 'sms', 'name' => 'Reminder, you are scheduled in X days', 'subject' => '', 'body' => 'Reminder: You are scheduled for {event_title} in {days_before} day(s). The task you signed up for is {task_title} from {volunteer_start_datetime_cst} to {volunteer_end_datetime_cst}.' ],
            ['channel' => 'sms', 'name' => 'Reminder, you are scheduled tomorrow', 'subject' => '', 'body' => 'Reminder: You are scheduled tomorrow for {event_title}. The task you signed up for is {task_title} from {volunteer_start_datetime_cst} to {volunteer_end_datetime_cst}.'],
        ];

        $now = current_time('mysql');
        foreach ($defaults as $template) {
            $existing_row = $wpdb->get_row($wpdb->prepare(
                "SELECT id, is_system FROM {$templates_table} WHERE channel = %s AND name = %s LIMIT 1",
                $template['channel'],
                $template['name']
            ));
            if ($existing_row) {
                if ((int) ($existing_row->is_system ?? 0) === 1) {
                    $wpdb->update($templates_table, [
                        'subject' => $template['subject'],
                        'body' => $template['body'],
                        'updated_at' => $now,
                    ], ['id' => (int) $existing_row->id]);
                }
                continue;
            }

            $wpdb->insert($templates_table, [
                'channel' => $template['channel'],
                'name' => $template['name'],
                'subject' => $template['subject'],
                'body' => $template['body'],
                'is_system' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
