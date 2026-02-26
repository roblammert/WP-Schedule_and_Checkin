<?php

if (!defined('ABSPATH')) {
    exit;
}

class SC_Shortcodes {
    public static function init() {
        add_shortcode('sc_signup', [__CLASS__, 'signup_shortcode']);
        add_shortcode('sc_kiosk', [__CLASS__, 'kiosk_shortcode']);

        add_action('admin_post_nopriv_sc_signup_submit', [__CLASS__, 'handle_signup_submit']);
        add_action('admin_post_sc_signup_submit', [__CLASS__, 'handle_signup_submit']);

        add_action('admin_post_nopriv_sc_kiosk_submit', [__CLASS__, 'handle_kiosk_submit']);
        add_action('admin_post_sc_kiosk_submit', [__CLASS__, 'handle_kiosk_submit']);
    }

    private static function tables() {
        global $wpdb;
        return [
            'events' => $wpdb->prefix . 'sc_events',
            'tasks' => $wpdb->prefix . 'sc_tasks',
            'volunteers' => $wpdb->prefix . 'sc_volunteers',
            'assignments' => $wpdb->prefix . 'sc_assignments',
            'checkins' => $wpdb->prefix . 'sc_checkins',
        ];
    }

    public static function signup_shortcode($atts) {
        $atts = shortcode_atts(['event_id' => 0], $atts, 'sc_signup');
        $event_id = absint($atts['event_id']);
        if (!$event_id) {
            return '<p>' . esc_html__('Missing event_id.', 'schedule-checkin') . '</p>';
        }

        global $wpdb;
        $t = self::tables();

        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['events']} WHERE id = %d", $event_id));
        if (!$event) {
            return '<p>' . esc_html__('Event not found.', 'schedule-checkin') . '</p>';
        }
        $allow_substitutes = isset($event->allow_substitutes) ? ((int) $event->allow_substitutes === 1) : true;

        $tasks = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['tasks']} WHERE event_id = %d ORDER BY start_datetime ASC", $event_id));
        $assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, v.name, v.email, v.phone FROM {$t['assignments']} a
             LEFT JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
                         WHERE a.event_id = %d
                             AND a.task_id IS NOT NULL
                             AND a.slot_number IS NOT NULL
                             AND a.status <> 'substitute_pool'",
            $event_id
        ));

        $visible = json_decode($event->visible_fields ?: '[]', true);
        if (!is_array($visible) || empty($visible)) {
            $visible = ['name', 'email', 'phone'];
        }

        $image = '';
        if (!empty($event->image_id)) {
            $image = wp_get_attachment_image_url((int) $event->image_id, [300, 200]);
        }

        $by_task_slot = [];
        foreach ($assignments as $a) {
            $by_task_slot[$a->task_id . ':' . $a->slot_number] = $a;
        }

        $open_slots_by_task = [];
        foreach ($tasks as $task) {
            $open_slots_by_task[(string) $task->id] = [];
            for ($slot = 1; $slot <= (int) $task->slots; $slot++) {
                $key = $task->id . ':' . $slot;
                if (!isset($by_task_slot[$key])) {
                    $open_slots_by_task[(string) $task->id][] = $slot;
                }
            }
        }

        ob_start();
        ?>
        <div class="sc-public-wrap">
            <?php self::render_public_notice(); ?>
            <div class="sc-public-card">
                <h2><?php echo esc_html($event->title); ?></h2>
                <p><em><?php esc_html_e('All times shown in CST.', 'schedule-checkin'); ?></em></p>
                <?php if ($image) : ?>
                    <img class="sc-event-image" src="<?php echo esc_url($image); ?>" width="300" height="200" alt="" />
                <?php endif; ?>
                <p><?php echo esc_html(self::format_time_range_12h($event->start_datetime, $event->end_datetime)); ?></p>
                <p><?php echo esc_html($event->description); ?></p>
            </div>

            <div class="sc-public-card">
                <h3><?php esc_html_e('Volunteer Signup', 'schedule-checkin'); ?></h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-signup-form" data-open-slots="<?php echo esc_attr(wp_json_encode($open_slots_by_task)); ?>">
                    <?php wp_nonce_field('sc_signup_submit'); ?>
                    <input type="hidden" name="action" value="sc_signup_submit" />
                    <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                    <input type="hidden" name="task_slot" class="sc-task-slot-hidden" value="" />

                    <label><span><?php esc_html_e('Name *', 'schedule-checkin'); ?></span><input required type="text" name="name" /></label>
                    <label><span><?php esc_html_e('Email Address *', 'schedule-checkin'); ?></span><input required type="email" name="email" /></label>
                    <label><span><?php esc_html_e('Phone Number *', 'schedule-checkin'); ?></span><input required type="text" name="phone" placeholder="123-456-7890" pattern="\d{3}-\d{3}-\d{4}" title="Phone number must be in the format 123-456-7890" /></label>
                    <label>
                        <span><?php esc_html_e('Preferred Contact Method', 'schedule-checkin'); ?></span>
                        <select name="preferred_contact_method" required>
                            <option value="email"><?php esc_html_e('Email', 'schedule-checkin'); ?></option>
                            <option value="sms"><?php esc_html_e('SMS', 'schedule-checkin'); ?></option>
                        </select>
                    </label>

                    <?php if ($allow_substitutes) : ?>
                        <label>
                            <span><?php esc_html_e('Signup Type', 'schedule-checkin'); ?></span>
                            <select name="signup_type" class="sc-signup-type" required>
                                <option value="scheduled"><?php esc_html_e('Scheduled Volunteer (choose task)', 'schedule-checkin'); ?></option>
                                <option value="substitute"><?php esc_html_e('Substitute (available as needed)', 'schedule-checkin'); ?></option>
                            </select>
                        </label>
                    <?php else : ?>
                        <input type="hidden" name="signup_type" value="scheduled" />
                    <?php endif; ?>

                    <label class="sc-scheduled-only">
                        <span><?php esc_html_e('Task', 'schedule-checkin'); ?></span>
                        <select name="task_id" class="sc-task-select">
                            <option value=""><?php esc_html_e('Select a task', 'schedule-checkin'); ?></option>
                            <?php foreach ($tasks as $task) :
                                $open_count = count($open_slots_by_task[(string) $task->id] ?? []);
                                if ($open_count < 1) {
                                    continue;
                                }
                                ?>
                                <option value="<?php echo (int) $task->id; ?>">
                                    <?php echo esc_html($task->title . ' | ' . self::format_time_range_12h($task->start_datetime, $task->end_datetime) . ' | Open Slots: ' . $open_count); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <p class="description sc-scheduled-only"><?php esc_html_e('The first open slot for the selected task will be assigned automatically.', 'schedule-checkin'); ?></p>

                    <?php if ($allow_substitutes) : ?>
                        <label class="sc-substitute-only" style="display:none;">
                            <span>
                                <input type="checkbox" name="future_substitute_opt_in" value="1" />
                                <?php esc_html_e('I am willing to substitute for future events like this one.', 'schedule-checkin'); ?>
                            </span>
                        </label>
                    <?php endif; ?>

                    <button class="sc-button" type="submit"><?php esc_html_e('Sign Up', 'schedule-checkin'); ?></button>
                </form>
            </div>

            <div class="sc-public-card">
                <h3><?php esc_html_e('Current Schedule', 'schedule-checkin'); ?></h3>
                <?php foreach ($tasks as $task) : ?>
                    <?php
                    $task_open_count = count($open_slots_by_task[(string) $task->id] ?? []);
                    if ($task_open_count <= 0) {
                        $task_status_label = __('All Slots Filled', 'schedule-checkin');
                        $task_status_class = 'sc-role-full';
                    } elseif ($task_open_count === 1) {
                        $task_status_label = __('1 Slot Open', 'schedule-checkin');
                        $task_status_class = 'sc-role-guest';
                    } else {
                        $task_status_label = sprintf(__('%d Slots Open', 'schedule-checkin'), (int) $task_open_count);
                        $task_status_class = 'sc-role-substitute';
                    }
                    ?>
                    <details class="sc-task-block">
                        <summary>
                            <?php echo esc_html($task->title . ' | ' . self::format_time_range_12h($task->start_datetime, $task->end_datetime)); ?>
                            <span class="sc-role-badge <?php echo esc_attr($task_status_class); ?>"><?php echo esc_html($task_status_label); ?></span>
                        </summary>
                        <p><?php echo esc_html($task->description); ?></p>
                        <ul>
                            <?php for ($slot = 1; $slot <= (int) $task->slots; $slot++) :
                                $key = $task->id . ':' . $slot;
                                $filled = $by_task_slot[$key] ?? null;
                                ?>
                                <li>
                                    <strong><?php echo esc_html__('Slot', 'schedule-checkin') . ' ' . (int) $slot; ?>:</strong>
                                    <?php if ($filled) :
                                        $parts = [];
                                        if (in_array('name', $visible, true) && !empty($filled->name)) {
                                            $parts[] = $filled->name;
                                        }
                                        if (in_array('email', $visible, true) && !empty($filled->email)) {
                                            $parts[] = $filled->email;
                                        }
                                        if (in_array('phone', $visible, true) && !empty($filled->phone)) {
                                            $parts[] = $filled->phone;
                                        }
                                        echo esc_html(!empty($parts) ? implode(' | ', $parts) : __('Filled', 'schedule-checkin'));
                                    else :
                                        echo esc_html__('Open', 'schedule-checkin');
                                    endif; ?>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    public static function kiosk_shortcode($atts) {
        $atts = shortcode_atts(['event_id' => 0], $atts, 'sc_kiosk');
        $event_id = absint($atts['event_id']);
        if (!$event_id) {
            return '<p>' . esc_html__('Missing event_id.', 'schedule-checkin') . '</p>';
        }

        $kiosk_url = add_query_arg([
            'sc_kiosk' => 1,
            'event_id' => $event_id,
        ], home_url('/'));

        return '<div class="sc-kiosk-embed"><iframe src="' . esc_url($kiosk_url) . '" title="Kiosk" loading="lazy"></iframe></div>';
    }

    public static function render_kiosk($event_id, $standalone = false) {
        global $wpdb;
        $t = self::tables();

        $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['events']} WHERE id = %d", $event_id));
        if (!$event) {
            return '<p>' . esc_html__('Event not found.', 'schedule-checkin') . '</p>';
        }
        $allow_substitutes = isset($event->allow_substitutes) ? ((int) $event->allow_substitutes === 1) : true;

        $assignments = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, v.name, v.phone, v.email, t.title as task_title, t.start_datetime, t.end_datetime
             FROM {$t['assignments']} a
             INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
             LEFT JOIN {$t['tasks']} t ON t.id = a.task_id
             WHERE a.event_id = %d
             " . (!$allow_substitutes ? "AND a.assignment_type <> 'substitute'" : "") . "
             ORDER BY
                CASE WHEN a.assignment_type = 'substitute' AND a.task_id IS NULL THEN 1 ELSE 0 END ASC,
                CASE WHEN t.start_datetime IS NULL THEN 1 ELSE 0 END ASC,
                t.start_datetime ASC,
                CASE WHEN a.slot_number IS NULL THEN 1 ELSE 0 END ASC,
                a.slot_number ASC,
                a.created_at ASC",
            $event_id
        ));

        $checkin_state_map = self::get_assignment_checkin_state_map($event_id);

        ob_start();
        if ($standalone) {
            ?><!doctype html><html><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/><title><?php echo esc_html($event->title); ?> Kiosk</title><link rel="stylesheet" href="<?php echo esc_url(SC_PLUGIN_URL . 'assets/public.css'); ?>" /></head><body class="sc-kiosk-only"><?php
        }
        ?>
        <div class="sc-kiosk-wrap">
            <?php self::render_public_notice(); ?>
            <div class="sc-public-card">
                <h2><?php echo esc_html($event->title); ?> - <?php esc_html_e('Check-In Kiosk', 'schedule-checkin'); ?></h2>
                <p><em><?php esc_html_e('All times shown in CST.', 'schedule-checkin'); ?></em></p>
                <div class="sc-kiosk-tabs-wrap">
                    <input class="sc-kiosk-tab-input" type="radio" name="sc_kiosk_tab" id="sc_kiosk_tab_signed" checked />
                    <label class="sc-kiosk-tab-label" for="sc_kiosk_tab_signed"><?php esc_html_e('I Signed Up (Previously Or Today)', 'schedule-checkin'); ?></label>

                    <input class="sc-kiosk-tab-input" type="radio" name="sc_kiosk_tab" id="sc_kiosk_tab_guest" />
                    <label class="sc-kiosk-tab-label" for="sc_kiosk_tab_guest"><?php esc_html_e("I'm a Guest, Sign Me Up", 'schedule-checkin'); ?></label>

                    <div class="sc-kiosk-tab-panels">
                        <div class="sc-kiosk-tab-panel sc-kiosk-tab-panel-signed">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-signup-form sc-kiosk-signed-form">
                    <?php wp_nonce_field('sc_kiosk_submit'); ?>
                    <input type="hidden" name="action" value="sc_kiosk_submit" />
                    <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />

                    <label>
                        <span><?php esc_html_e('Volunteer', 'schedule-checkin'); ?></span>
                        <select required name="assignment_id">
                            <option value=""><?php esc_html_e('Select your name', 'schedule-checkin'); ?></option>
                            <?php foreach ($assignments as $assignment) :
                                $role_label = ucfirst((string) ($assignment->assignment_type ?: 'scheduled'));
                                $task_label = $assignment->task_title ? $assignment->task_title : __('No slot assignment', 'schedule-checkin');
                                $state = $checkin_state_map[(int) $assignment->id] ?? [
                                    'has_checkin' => false,
                                    'is_checked_in' => false,
                                    'first_checkin_at' => '',
                                ];
                                if ((string) ($assignment->assignment_type ?: '') === 'guest' && !empty($state['first_checkin_at'])) {
                                    $time_label = __('Started', 'schedule-checkin') . ': ' . self::format_datetime_12h($state['first_checkin_at']);
                                } else {
                                    $time_label = ($assignment->start_datetime && $assignment->end_datetime)
                                        ? self::format_time_range_12h($assignment->start_datetime, $assignment->end_datetime)
                                        : __('Anytime during event', 'schedule-checkin');
                                }
                                $slot_label = $assignment->slot_number ? (' | Slot ' . (int) $assignment->slot_number) : '';
                                if (!empty($state['is_checked_in'])) {
                                    $state_name = 'checked_in';
                                } elseif (!empty($state['has_checkin']) && (string) ($state['last_action'] ?? '') === 'checkout') {
                                    $state_name = 'checked_out';
                                } else {
                                    $state_name = 'not_checked_in';
                                }
                                $role_value = (string) ($assignment->assignment_type ?: 'scheduled');
                                ?>
                                <option value="<?php echo (int) $assignment->id; ?>" data-state="<?php echo esc_attr($state_name); ?>" data-role="<?php echo esc_attr($role_value); ?>"><?php echo esc_html($assignment->name . ' | ' . $role_label . ' | ' . $task_label . ' | ' . $time_label . $slot_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="sc-kiosk-selection-meta"><?php esc_html_e('Selected Type:', 'schedule-checkin'); ?> <span class="sc-role-badge sc-role-badge-muted" data-kiosk-role-badge><?php esc_html_e('None', 'schedule-checkin'); ?></span></small>
                        <small class="sc-kiosk-selection-meta"><?php esc_html_e('Status:', 'schedule-checkin'); ?> <span class="sc-role-badge sc-role-badge-muted" data-kiosk-status-badge><?php esc_html_e('Not Checked In', 'schedule-checkin'); ?></span></small>
                    </label>

                    <label>
                        <span><?php esc_html_e('PIN', 'schedule-checkin'); ?></span>
                        <input type="password" inputmode="numeric" maxlength="10" name="pin" required />
                        <small class="sc-kiosk-selection-meta"><?php esc_html_e('Use the last 4 digits of the phone number used during registration.', 'schedule-checkin'); ?></small>
                    </label>

                    <div class="sc-kiosk-actions">
                        <button class="sc-button" type="submit" name="kiosk_action" value="checkin"><?php esc_html_e('Check In', 'schedule-checkin'); ?></button>
                        <button class="sc-button" type="submit" name="kiosk_action" value="checkout"><?php esc_html_e('Check Out', 'schedule-checkin'); ?></button>
                    </div>
                </form>
                        </div>

                        <div class="sc-kiosk-tab-panel sc-kiosk-tab-panel-guest">
                <h3><?php esc_html_e('Guest Check-In', 'schedule-checkin'); ?></h3>
                <p><?php esc_html_e('Guests can attend without a slot assignment.', 'schedule-checkin'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sc-signup-form sc-kiosk-guest-form">
                    <?php wp_nonce_field('sc_kiosk_submit'); ?>
                    <input type="hidden" name="action" value="sc_kiosk_submit" />
                    <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>" />
                    <input type="hidden" name="checkin_mode" value="guest" />

                    <label><span><?php esc_html_e('Name *', 'schedule-checkin'); ?></span><input required type="text" name="guest_name" /></label>
                    <label><span><?php esc_html_e('Email Address *', 'schedule-checkin'); ?></span><input required type="email" name="guest_email" /></label>
                    <label><span><?php esc_html_e('Phone Number *', 'schedule-checkin'); ?></span><input required type="text" name="guest_phone" placeholder="123-456-7890" pattern="\d{3}-\d{3}-\d{4}" title="Phone number must be in the format 123-456-7890" /></label>
                    <label>
                        <span><?php esc_html_e('Preferred Contact Method', 'schedule-checkin'); ?></span>
                        <select name="guest_preferred_contact_method" required>
                            <option value="email"><?php esc_html_e('Email', 'schedule-checkin'); ?></option>
                            <option value="sms"><?php esc_html_e('SMS', 'schedule-checkin'); ?></option>
                        </select>
                    </label>
                    <label>
                        <span><?php esc_html_e('Planned Stay', 'schedule-checkin'); ?></span>
                        <select required name="guest_planned_minutes">
                            <?php foreach ([15, 30, 45, 60, 90, 120] as $minutes) : ?>
                                <option value="<?php echo (int) $minutes; ?>"><?php echo esc_html($minutes . ' ' . __('minutes', 'schedule-checkin')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <button class="sc-button" type="submit"><?php esc_html_e('Check In as Guest', 'schedule-checkin'); ?></button>
                </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sc-public-card">
                <h3><?php esc_html_e('Attendees', 'schedule-checkin'); ?></h3>
                <table class="sc-table">
                    <thead><tr><th><?php esc_html_e('Name', 'schedule-checkin'); ?></th><th><?php esc_html_e('Type', 'schedule-checkin'); ?></th><th><?php esc_html_e('Task', 'schedule-checkin'); ?></th><th><?php esc_html_e('Start', 'schedule-checkin'); ?></th><th><?php esc_html_e('Slot', 'schedule-checkin'); ?></th><th><?php esc_html_e('Status', 'schedule-checkin'); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($assignments as $assignment) :
                            $state = $checkin_state_map[(int) $assignment->id] ?? [
                                'has_checkin' => false,
                                'is_checked_in' => false,
                                'first_checkin_at' => '',
                                'last_action' => '',
                            ];
                            $status = 'Not Checked In';
                            if (!empty($state['is_checked_in'])) {
                                $status = 'Checked In';
                            } elseif (!empty($state['has_checkin']) && (string) ($state['last_action'] ?? '') === 'checkout') {
                                $status = 'Checked Out';
                            }
                            ?>
                            <tr>
                                <td><?php echo esc_html($assignment->name); ?></td>
                                <td><span class="sc-role-badge sc-role-<?php echo esc_attr((string) ($assignment->assignment_type ?: 'scheduled')); ?>"><?php echo esc_html(ucfirst((string) ($assignment->assignment_type ?: 'scheduled'))); ?></span></td>
                                <td><?php echo esc_html($assignment->task_title ?: __('No slot assignment', 'schedule-checkin')); ?></td>
                                <td>
                                    <?php
                                    if ((string) ($assignment->assignment_type ?: '') === 'guest' && !empty($state['first_checkin_at'])) {
                                        echo esc_html(self::format_datetime_12h($state['first_checkin_at']));
                                    } else {
                                        echo esc_html($assignment->start_datetime ? self::format_datetime_12h($assignment->start_datetime) : __('Anytime', 'schedule-checkin'));
                                    }
                                    ?>
                                </td>
                                <td><?php echo $assignment->slot_number ? (int) $assignment->slot_number : '&mdash;'; ?></td>
                                <td>
                                    <span class="sc-role-badge<?php echo $status === 'Not Checked In' ? ' sc-role-badge-muted' : ''; ?>">
                                        <?php echo esc_html($status); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
        (function () {
            function runKioskUiSync() {
                var signedForm = document.querySelector('.sc-kiosk-signed-form');

                function syncSignedButtons() {
                    if (!signedForm) {
                        return;
                    }

                    var select = signedForm.querySelector('select[name="assignment_id"]');
                    var checkInBtn = signedForm.querySelector('button[name="kiosk_action"][value="checkin"]');
                    var checkOutBtn = signedForm.querySelector('button[name="kiosk_action"][value="checkout"]');
                    var roleBadge = signedForm.querySelector('[data-kiosk-role-badge]');
                    var statusBadge = signedForm.querySelector('[data-kiosk-status-badge]');

                    if (!select || !checkInBtn || !checkOutBtn) {
                        return;
                    }

                    var selectedOption = select.options[select.selectedIndex];
                    var hasChoice = !!select.value;
                    var state = selectedOption ? (selectedOption.getAttribute('data-state') || 'not_checked_in') : 'not_checked_in';
                    var role = selectedOption ? (selectedOption.getAttribute('data-role') || '') : '';

                    if (!hasChoice) {
                        checkInBtn.disabled = true;
                        checkOutBtn.disabled = true;
                    } else if (state === 'checked_in') {
                        checkInBtn.disabled = true;
                        checkOutBtn.disabled = false;
                    } else {
                        checkInBtn.disabled = false;
                        checkOutBtn.disabled = true;
                    }

                    if (roleBadge) {
                        roleBadge.classList.remove('sc-role-scheduled', 'sc-role-substitute', 'sc-role-guest', 'sc-role-badge-muted');
                        if (!hasChoice || !role) {
                            roleBadge.classList.add('sc-role-badge-muted');
                            roleBadge.textContent = 'None';
                        } else {
                            roleBadge.classList.add('sc-role-' + role);
                            roleBadge.textContent = role.charAt(0).toUpperCase() + role.slice(1);
                        }
                    }

                    if (statusBadge) {
                        statusBadge.classList.remove('sc-role-badge-muted');
                        if (!hasChoice) {
                            statusBadge.classList.add('sc-role-badge-muted');
                            statusBadge.textContent = <?php echo wp_json_encode(__('Not Checked In', 'schedule-checkin')); ?>;
                        } else if (state === 'checked_in') {
                            statusBadge.textContent = <?php echo wp_json_encode(__('Checked In', 'schedule-checkin')); ?>;
                        } else if (state === 'checked_out') {
                            statusBadge.textContent = <?php echo wp_json_encode(__('Checked Out', 'schedule-checkin')); ?>;
                        } else {
                            statusBadge.textContent = <?php echo wp_json_encode(__('Not Checked In', 'schedule-checkin')); ?>;
                        }
                    }
                }

                if (signedForm) {
                    var assignmentSelect = signedForm.querySelector('select[name="assignment_id"]');
                    if (assignmentSelect) {
                        assignmentSelect.addEventListener('change', syncSignedButtons);
                    }
                }

                syncSignedButtons();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', runKioskUiSync);
            } else {
                runKioskUiSync();
            }
        })();
        </script>
        <?php
        if ($standalone) {
            echo '</body></html>';
        }

        return ob_get_clean();
    }

    public static function handle_signup_submit() {
        check_admin_referer('sc_signup_submit');

        global $wpdb;
        $t = self::tables();

        $event_id = absint($_POST['event_id'] ?? 0);
        $signup_type = sanitize_key($_POST['signup_type'] ?? 'scheduled');
        if (!in_array($signup_type, ['scheduled', 'substitute'], true)) {
            $signup_type = 'scheduled';
        }

        if (!$event_id) {
            wp_safe_redirect(self::safe_referer());
            exit;
        }

        $event = $wpdb->get_row($wpdb->prepare("SELECT id, allow_substitutes FROM {$t['events']} WHERE id = %d", $event_id));
        if (!$event) {
            wp_safe_redirect(add_query_arg('sc_msg', 'invalid_event', self::safe_referer()));
            exit;
        }

        $allow_substitutes = isset($event->allow_substitutes) ? ((int) $event->allow_substitutes === 1) : true;
        if (!$allow_substitutes && $signup_type === 'substitute') {
            $signup_type = 'scheduled';
        }

        $name = sanitize_text_field($_POST['name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = self::normalize_phone_for_storage($_POST['phone'] ?? '');
        $preferred_contact_method = sanitize_key($_POST['preferred_contact_method'] ?? 'email');
        if (!in_array($preferred_contact_method, ['email', 'sms'], true)) {
            $preferred_contact_method = 'email';
        }
        if ($name === '' || $email === '' || $phone === '') {
            wp_safe_redirect(add_query_arg('sc_msg', 'required_fields_missing', self::safe_referer()));
            exit;
        }
        if (!is_email($email)) {
            wp_safe_redirect(add_query_arg('sc_msg', 'invalid_contact_format', self::safe_referer()));
            exit;
        }
        if (!self::is_valid_public_phone_input($_POST['phone'] ?? '')) {
            wp_safe_redirect(add_query_arg('sc_msg', 'invalid_contact_format', self::safe_referer()));
            exit;
        }

        $volunteer_id = self::find_or_create_volunteer(
            $name,
            $email,
            $phone,
            $allow_substitutes && !empty($_POST['future_substitute_opt_in']),
            $preferred_contact_method
        );

        $task_id = null;
        $slot_number = null;
        if ($signup_type === 'scheduled') {
            $task_id = absint($_POST['task_id'] ?? 0);
            if (!$task_id) {
                wp_safe_redirect(add_query_arg('sc_msg', 'invalid_slot', self::safe_referer()));
                exit;
            }

            $task = $wpdb->get_row($wpdb->prepare("SELECT id, event_id, slots FROM {$t['tasks']} WHERE id = %d", $task_id));
            if (!$task || (int) $task->event_id !== $event_id) {
                wp_safe_redirect(add_query_arg('sc_msg', 'invalid_slot', self::safe_referer()));
                exit;
            }

            $occupied_rows = $wpdb->get_col($wpdb->prepare(
                "SELECT slot_number
                 FROM {$t['assignments']}
                 WHERE task_id = %d
                   AND slot_number IS NOT NULL
                   AND status <> 'substitute_pool'",
                $task_id
            ));
            $occupied = [];
            foreach ((array) $occupied_rows as $occupied_slot) {
                $occupied[(int) $occupied_slot] = true;
            }

            for ($candidate_slot = 1; $candidate_slot <= (int) $task->slots; $candidate_slot++) {
                if (!isset($occupied[$candidate_slot])) {
                    $slot_number = $candidate_slot;
                    break;
                }
            }

            if (!$slot_number) {
                wp_safe_redirect(add_query_arg('sc_msg', 'slot_filled', self::safe_referer()));
                exit;
            }
        }

        $now = current_time('mysql');

        $wpdb->insert($t['assignments'], [
            'event_id' => $event_id,
            'task_id' => $task_id,
            'volunteer_id' => $volunteer_id,
            'slot_number' => $slot_number,
            'assignment_type' => $signup_type,
            'status' => $signup_type === 'substitute' ? 'substitute_pool' : 'assigned',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $assignment_id = (int) $wpdb->insert_id;
        if ($assignment_id > 0 && class_exists('SC_Admin')) {
            $thank_you_tokens = [];
            if ($signup_type === 'substitute') {
                $thank_you_tokens['task_title'] = __('Substitute Pool', 'schedule-checkin');
            }
            SC_Admin::send_signup_thank_you_message($volunteer_id, $event_id, $assignment_id, $thank_you_tokens);
        }

        $message = $signup_type === 'substitute' ? 'substitute_signed_up' : 'signed_up';
        wp_safe_redirect(add_query_arg('sc_msg', $message, self::safe_referer()));
        exit;
    }

    public static function handle_kiosk_submit() {
        check_admin_referer('sc_kiosk_submit');

        global $wpdb;
        $t = self::tables();

        $event_id = absint($_POST['event_id'] ?? 0);
        $checkin_mode = sanitize_key($_POST['checkin_mode'] ?? 'volunteer');

        if ($checkin_mode === 'guest') {
            $name = sanitize_text_field($_POST['guest_name'] ?? '');
            $email = sanitize_email($_POST['guest_email'] ?? '');
            $phone = self::normalize_phone_for_storage($_POST['guest_phone'] ?? '');
            $preferred_contact_method = sanitize_key($_POST['guest_preferred_contact_method'] ?? 'email');
            if (!in_array($preferred_contact_method, ['email', 'sms'], true)) {
                $preferred_contact_method = 'email';
            }
            $planned_minutes = absint($_POST['guest_planned_minutes'] ?? 0);
            if (!$event_id || $name === '' || $email === '' || $phone === '' || !in_array($planned_minutes, [15, 30, 45, 60, 90, 120], true)) {
                wp_safe_redirect(add_query_arg('sc_msg', 'required_fields_missing', self::safe_referer()));
                exit;
            }
            if (!is_email($email) || !self::is_valid_public_phone_input($_POST['guest_phone'] ?? '')) {
                wp_safe_redirect(add_query_arg('sc_msg', 'invalid_contact_format', self::safe_referer()));
                exit;
            }

            $volunteer_id = self::find_or_create_volunteer($name, $email, $phone, false, $preferred_contact_method);
            $now = current_time('mysql');

            $wpdb->insert($t['assignments'], [
                'event_id' => $event_id,
                'task_id' => null,
                'volunteer_id' => $volunteer_id,
                'slot_number' => null,
                'assignment_type' => 'guest',
                'planned_minutes' => $planned_minutes,
                'status' => 'guest_checked_in',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $assignment_id = (int) $wpdb->insert_id;
            $wpdb->insert($t['checkins'], [
                'event_id' => $event_id,
                'task_id' => null,
                'assignment_id' => $assignment_id,
                'volunteer_id' => $volunteer_id,
                'action' => 'checkin',
                'source' => 'guest',
                'notes' => sprintf(__('Guest planned stay: %d minutes', 'schedule-checkin'), $planned_minutes),
                'created_at' => $now,
            ]);

            if ($assignment_id > 0 && class_exists('SC_Admin')) {
                SC_Admin::send_signup_thank_you_message($volunteer_id, $event_id, $assignment_id, [
                    'task_title' => __('Guest Check-In', 'schedule-checkin'),
                ]);
            }

            wp_safe_redirect(add_query_arg('sc_msg', 'guest_checkin_ok', self::safe_referer()));
            exit;
        }

        $assignment_id = absint($_POST['assignment_id'] ?? 0);
        $pin = preg_replace('/\D+/', '', (string) ($_POST['pin'] ?? ''));
        $kiosk_action = sanitize_key($_POST['kiosk_action'] ?? '');

        if (!$event_id || !$assignment_id || !$pin) {
            wp_safe_redirect(self::safe_referer());
            exit;
        }

        $assignment = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, v.phone, t.id as task_id, e.require_checkout, e.admin_pin
             FROM {$t['assignments']} a
             INNER JOIN {$t['volunteers']} v ON v.id = a.volunteer_id
             LEFT JOIN {$t['tasks']} t ON t.id = a.task_id
             INNER JOIN {$t['events']} e ON e.id = a.event_id
             WHERE a.id = %d AND a.event_id = %d",
            $assignment_id,
            $event_id
        ));

        if (!$assignment) {
            wp_safe_redirect(add_query_arg('sc_msg', 'invalid_assignment', self::safe_referer()));
            exit;
        }

        $phone_digits = preg_replace('/\D+/', '', (string) $assignment->phone);
        $last4 = substr($phone_digits, -4);
        $is_admin_pin = hash_equals((string) $assignment->admin_pin, $pin);
        $is_valid = ($last4 && hash_equals($last4, $pin)) || $is_admin_pin;

        if (!$is_valid) {
            wp_safe_redirect(add_query_arg('sc_msg', 'bad_pin', self::safe_referer()));
            exit;
        }

        $action = '';
        $source = $is_admin_pin ? 'admin_pin' : 'volunteer_pin';

        $checkin_state = self::get_assignment_checkin_state((int) $assignment_id, $event_id);
        $currently_checked_in = !empty($checkin_state['is_checked_in']);
        $currently_checked_out = !$currently_checked_in;

        if ($kiosk_action && !in_array($kiosk_action, ['checkin', 'checkout'], true)) {
            wp_safe_redirect(add_query_arg('sc_msg', 'invalid_kiosk_action', self::safe_referer()));
            exit;
        }

        $effective_action = $kiosk_action;
        if ($effective_action === '') {
            $effective_action = $currently_checked_out ? 'checkin' : 'checkout';
        }

        if ($effective_action === 'checkin' && $currently_checked_in) {
            wp_safe_redirect(add_query_arg('sc_msg', 'already_checked_in', self::safe_referer()));
            exit;
        }
        if ($effective_action === 'checkout' && $currently_checked_out) {
            wp_safe_redirect(add_query_arg('sc_msg', 'already_checked_out', self::safe_referer()));
            exit;
        }

        if ($effective_action === 'checkin') {
            $action = 'checkin';
        } else {
            if ((int) $assignment->require_checkout === 0 && (string) $assignment->assignment_type !== 'guest') {
                wp_safe_redirect(add_query_arg('sc_msg', 'checkout_disabled', self::safe_referer()));
                exit;
            }
            $action = 'checkout';
        }

        $wpdb->insert($t['checkins'], [
            'event_id' => $event_id,
            'task_id' => $assignment->task_id,
            'assignment_id' => $assignment_id,
            'volunteer_id' => $assignment->volunteer_id,
            'action' => $action,
            'source' => $source,
            'notes' => $is_admin_pin ? 'Universal admin pin used.' : '',
            'created_at' => current_time('mysql'),
        ]);

        wp_safe_redirect(add_query_arg('sc_msg', $action . '_ok', self::safe_referer()));
        exit;
    }

    private static function safe_referer() {
        return wp_get_referer() ?: home_url('/');
    }

    private static function is_valid_public_phone_input($phone) {
        return (bool) preg_match('/^\d{3}-\d{3}-\d{4}$/', trim((string) $phone));
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

    private static function format_time_range_12h($start_datetime, $end_datetime) {
        return self::format_datetime_12h($start_datetime) . ' - ' . self::format_datetime_12h($end_datetime);
    }

    private static function format_datetime_12h($value) {
        $dt = self::to_cst_datetime($value);
        if (!$dt) {
            return (string) $value;
        }

        return $dt->format('m/d/Y g:i A') . ' CST';
    }

    private static function to_cst_datetime($value) {
        if (empty($value)) {
            return null;
        }

        try {
            $dt = new DateTime((string) $value, wp_timezone());
            $dt->setTimezone(new DateTimeZone('America/Chicago'));
            return $dt;
        } catch (Exception $e) {
            return null;
        }
    }

    private static function render_public_notice() {
        $messages = [
            'signed_up' => ['success', __('Signup successful.', 'schedule-checkin')],
            'substitute_signed_up' => ['success', __('Substitute signup successful. You can be contacted to fill open needs.', 'schedule-checkin')],
            'guest_checkin_ok' => ['success', __('Guest check-in recorded.', 'schedule-checkin')],
            'required_fields_missing' => ['error', __('Name, email, and phone are required.', 'schedule-checkin')],
            'invalid_contact_format' => ['error', __('Enter a valid email address and phone number in ###-###-#### format.', 'schedule-checkin')],
            'slot_filled' => ['error', __('That slot is already filled. Please choose another.', 'schedule-checkin')],
            'invalid_slot' => ['error', __('That slot is invalid. Please refresh and try again.', 'schedule-checkin')],
            'invalid_kiosk_action' => ['error', __('Invalid kiosk action.', 'schedule-checkin')],
            'bad_pin' => ['error', __('Invalid PIN. Please try again.', 'schedule-checkin')],
            'invalid_assignment' => ['error', __('Selection not found. Please try again.', 'schedule-checkin')],
            'checkout_disabled' => ['error', __('Check-out is not enabled for this event.', 'schedule-checkin')],
            'checkin_ok' => ['success', __('Check-in recorded.', 'schedule-checkin')],
            'checkout_ok' => ['success', __('Check-out recorded.', 'schedule-checkin')],
            'already_checked_in' => ['error', __('This attendee is already checked in.', 'schedule-checkin')],
            'already_checked_out' => ['error', __('This attendee is already checked out.', 'schedule-checkin')],
        ];

        $code = isset($_GET['sc_msg']) ? sanitize_key($_GET['sc_msg']) : '';
        if (!$code || !isset($messages[$code])) {
            return;
        }

        $type = $messages[$code][0] === 'error' ? 'sc-notice-error' : 'sc-notice-success';
        echo '<div class="sc-notice ' . esc_attr($type) . '">' . esc_html($messages[$code][1]) . '</div>';
    }

    private static function find_or_create_volunteer($name, $email, $phone, $future_substitute_opt_in = false, $preferred_contact_method = 'email') {
        global $wpdb;
        $t = self::tables();

        $email = sanitize_email($email);
        $phone = self::normalize_phone_for_storage($phone);
        $name = sanitize_text_field($name);
        $preferred_contact_method = in_array(sanitize_key($preferred_contact_method), ['email', 'sms'], true)
            ? sanitize_key($preferred_contact_method)
            : 'email';

        $volunteer = null;
                if ($name !== '' && $email !== '' && $phone !== '') {
            $legacy_phone = trim((string) $phone);
            if (preg_match('/^\+1(\d{10})$/', $legacy_phone, $matches)) {
                $legacy_phone = $matches[1];
            }
            $volunteer = $wpdb->get_row($wpdb->prepare(
                "SELECT *
                 FROM {$t['volunteers']}
                                 WHERE name = %s
                                     AND email = %s
                   AND (phone = %s OR phone = %s)
                 ORDER BY id ASC
                 LIMIT 1",
                                $name,
                $email,
                $phone,
                $legacy_phone
            ));
        }

        $now = current_time('mysql');
        if ($volunteer) {
            $update_data = [
                'preferred_contact_method' => $preferred_contact_method,
                'updated_at' => $now,
            ];
            if ($future_substitute_opt_in) {
                $update_data['future_substitute_opt_in'] = 1;
            }
            $wpdb->update($t['volunteers'], $update_data, ['id' => (int) $volunteer->id]);
            return (int) $volunteer->id;
        }

        $wpdb->insert($t['volunteers'], [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'preferred_contact_method' => $preferred_contact_method,
            'future_substitute_opt_in' => $future_substitute_opt_in ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    private static function find_volunteer_by_email($email) {
        global $wpdb;
        $t = self::tables();
        $email = sanitize_email($email);
        if ($email === '') {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['volunteers']} WHERE email = %s ORDER BY id ASC LIMIT 1", $email));
    }

    private static function get_assignment_checkin_state_map($event_id) {
        global $wpdb;
        $t = self::tables();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT assignment_id, action, created_at, id
             FROM {$t['checkins']}
             WHERE event_id = %d AND assignment_id IS NOT NULL
             ORDER BY assignment_id ASC, created_at ASC, id ASC",
            $event_id
        ));

        $map = [];
        foreach ($rows as $row) {
            $assignment_id = (int) $row->assignment_id;
            if (!isset($map[$assignment_id])) {
                $map[$assignment_id] = [
                    'has_checkin' => false,
                    'is_checked_in' => false,
                    'first_checkin_at' => '',
                    'last_action' => '',
                ];
            }

            $action = (string) ($row->action ?? '');
            if ($action === 'checkin') {
                $map[$assignment_id]['has_checkin'] = true;
                if ($map[$assignment_id]['first_checkin_at'] === '') {
                    $map[$assignment_id]['first_checkin_at'] = (string) ($row->created_at ?? '');
                }
            }
            $map[$assignment_id]['last_action'] = $action;
            $map[$assignment_id]['is_checked_in'] = $action === 'checkin';
        }

        return $map;
    }

    private static function get_assignment_checkin_state($assignment_id, $event_id) {
        global $wpdb;
        $t = self::tables();

        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT action, created_at
             FROM {$t['checkins']}
             WHERE event_id = %d AND assignment_id = %d
             ORDER BY created_at ASC, id ASC",
            $event_id,
            $assignment_id
        ));

        if (empty($logs)) {
            return [
                'has_checkin' => false,
                'is_checked_in' => false,
                'first_checkin_at' => '',
                'last_action' => '',
            ];
        }

        $first_checkin_at = '';
        $last_action = '';
        foreach ($logs as $log) {
            $action = (string) ($log->action ?? '');
            if ($action === 'checkin' && $first_checkin_at === '') {
                $first_checkin_at = (string) ($log->created_at ?? '');
            }
            $last_action = $action;
        }

        return [
            'has_checkin' => $first_checkin_at !== '',
            'is_checked_in' => $last_action === 'checkin',
            'first_checkin_at' => $first_checkin_at,
            'last_action' => $last_action,
        ];
    }
}
