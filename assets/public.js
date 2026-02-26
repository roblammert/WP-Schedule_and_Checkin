jQuery(function ($) {
    function initSignupTaskPicker() {
        $('.sc-signup-form').each(function () {
            const $form = $(this);
            const $signupType = $form.find('.sc-signup-type');
            const $task = $form.find('.sc-task-select');
            const $hidden = $form.find('.sc-task-slot-hidden');
            const $scheduledOnly = $form.find('.sc-scheduled-only');
            const $substituteOnly = $form.find('.sc-substitute-only');

            if (!$task.length || !$hidden.length) {
                return;
            }

            let openSlots = {};
            const raw = $form.attr('data-open-slots');
            if (raw) {
                try {
                    openSlots = JSON.parse(raw);
                } catch (_e) {
                    openSlots = {};
                }
            }

            const syncTaskSelection = function () {
                const taskId = String($task.val() || '');
                const slots = taskId && openSlots[taskId] ? openSlots[taskId] : [];
                $hidden.val('');
                if (!taskId || !slots.length) {
                    return;
                }
                $hidden.val(taskId + ':' + String(slots[0]));
            };

            const syncSignupType = function () {
                const mode = String($signupType.val() || 'scheduled');
                const isScheduled = mode !== 'substitute';
                $scheduledOnly.toggle(isScheduled);
                $substituteOnly.toggle(!isScheduled);

                if (!isScheduled) {
                    $task.val('');
                    $hidden.val('');
                } else {
                    syncTaskSelection();
                }
            };

            $task.on('change', syncTaskSelection);

            if ($signupType.length) {
                $signupType.on('change', syncSignupType);
            }

            $form.on('submit', function (e) {
                const isScheduled = !$signupType.length || String($signupType.val()) !== 'substitute';
                if (!isScheduled) {
                    $hidden.val('');
                    return;
                }
                const taskId = $task.val();
                const slots = taskId ? (openSlots[String(taskId)] || []) : [];
                if (!taskId || !slots.length) {
                    e.preventDefault();
                    window.alert('Please select a task with an open slot.');
                    return;
                }
                $hidden.val(taskId + ':' + String(slots[0]));
            });

            syncSignupType();
        });
    }

    function initKioskModeAndActions() {
        const $signedForm = $('.sc-kiosk-signed-form');

        $signedForm.each(function () {
            const $form = $(this);
            const $select = $form.find('select[name="assignment_id"]');
            const $checkin = $form.find('button[name="kiosk_action"][value="checkin"]');
            const $checkout = $form.find('button[name="kiosk_action"][value="checkout"]');
            const $roleBadge = $form.find('[data-kiosk-role-badge]');
            const $statusBadge = $form.find('[data-kiosk-status-badge]');

            const roleClassMap = {
                scheduled: 'sc-role-scheduled',
                substitute: 'sc-role-substitute',
                guest: 'sc-role-guest'
            };

            const updateRoleBadge = function () {
                if (!$roleBadge.length) {
                    return;
                }
                const $opt = $select.find('option:selected');
                const role = String($opt.data('role') || '');

                $roleBadge.removeClass('sc-role-scheduled sc-role-substitute sc-role-guest sc-role-badge-muted');
                if (!role || !$select.val()) {
                    $roleBadge.addClass('sc-role-badge-muted').text('None');
                    return;
                }

                $roleBadge.addClass(roleClassMap[role] || 'sc-role-badge-muted');
                $roleBadge.text(role.charAt(0).toUpperCase() + role.slice(1));
            };

            const syncButtons = function () {
                const $opt = $select.find('option:selected');
                const state = String($opt.data('state') || 'not_checked_in');
                const hasChoice = !!$select.val();
                updateRoleBadge();
                if (!hasChoice) {
                    $checkin.prop('disabled', true);
                    $checkout.prop('disabled', true);
                    return;
                }

                if (state === 'checked_in') {
                    $checkin.prop('disabled', true);
                    $checkout.prop('disabled', false);
                } else {
                    $checkin.prop('disabled', false);
                    $checkout.prop('disabled', true);
                }

                if ($statusBadge.length) {
                    $statusBadge.removeClass('sc-role-badge-muted');
                    if (!hasChoice) {
                        $statusBadge.addClass('sc-role-badge-muted').text('Not Checked In');
                    } else if (state === 'checked_in') {
                        $statusBadge.text('Checked In');
                    } else if (state === 'checked_out') {
                        $statusBadge.text('Checked Out');
                    } else {
                        $statusBadge.text('Not Checked In');
                    }
                }
            };

            $select.on('change', syncButtons);
            syncButtons();
        });
    }

    initSignupTaskPicker();
    initKioskModeAndActions();
});
