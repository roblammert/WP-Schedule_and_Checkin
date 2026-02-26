jQuery(function ($) {
    function showAssignmentsNotice(message, isError) {
        const $wrap = $('.sc-admin-wrap').first();
        if (!$wrap.length) {
            return;
        }

        let $notice = $wrap.find('.sc-dd-notice').first();
        if (!$notice.length) {
            $notice = $('<div class="sc-dd-notice" role="status" aria-live="polite"></div>');
            $wrap.prepend($notice);
        }

        $notice.removeClass('sc-dd-success sc-dd-error');
        $notice.addClass(isError ? 'sc-dd-error' : 'sc-dd-success');
        $notice.text(message).show();
    }

    function initTaskWizardConfirm() {
        const $toggle = $('#sc_replace_existing_toggle');
        const $row = $('#sc_replace_confirm_row');
        const $input = $('#sc_replace_confirm_input');

        if (!$toggle.length || !$row.length || !$input.length) {
            return;
        }

        const sync = function () {
            const enabled = $toggle.is(':checked');
            $row.toggleClass('is-hidden', !enabled);
            $row.attr('aria-hidden', enabled ? 'false' : 'true');
            $input.prop('disabled', !enabled);
            if (!enabled) {
                $input.val('');
            }
        };

        $toggle.on('change', sync);
        sync();
    }

    function initMediaPicker() {
        const $pick = $('#sc_pick_image');
        const $clear = $('#sc_clear_image');
        const $imageId = $('#sc_image_id');
        const $preview = $('#sc_image_preview');
        const $previewImg = $preview.find('img');

        if (!$pick.length || typeof wp === 'undefined' || !wp.media) {
            return;
        }

        let frame;
        $pick.on('click', function () {
            if (!frame) {
                frame = wp.media({
                    title: scAdmin.mediaTitle,
                    button: { text: scAdmin.mediaButton },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function () {
                    const attachment = frame.state().get('selection').first().toJSON();
                    if (!attachment || !attachment.id) {
                        return;
                    }

                    $imageId.val(attachment.id);
                    $previewImg.attr('src', attachment.url || '');
                    $preview.show();
                });
            }

            frame.open();
        });

        $clear.on('click', function () {
            $imageId.val('');
            $previewImg.attr('src', '');
            $preview.hide();
        });
    }

    function initDragDrop() {
        const $slots = $('.sc-slot.sc-slot-assigned');
        const $taskColumns = $('.sc-task-column');

        const findFirstOpenSlot = function ($taskColumn) {
            return $taskColumn.find('.sc-slot').filter(function () {
                return parseInt($(this).data('assignment-id'), 10) === 0;
            }).first();
        };

        const handleDrop = function ($dropTarget, $dragged) {
            const assignmentId = parseInt($dragged.data('assignment-id'), 10);
            if (!assignmentId) {
                return;
            }

            const sourceTaskId = parseInt($dragged.closest('.sc-task-column').data('task-id'), 10);
            const sourceSlot = parseInt($dragged.data('slot-number'), 10);

            const $targetColumn = $dropTarget.hasClass('sc-task-column')
                ? $dropTarget
                : $dropTarget.closest('.sc-task-column');

            if (!$targetColumn.length) {
                return;
            }

            const targetTaskId = parseInt($targetColumn.data('task-id'), 10);
            const $targetSlot = findFirstOpenSlot($targetColumn);

            if (!$targetSlot.length) {
                showAssignmentsNotice('No open slots in the target task. Drop ignored.', true);
                return;
            }

            const targetSlot = parseInt($targetSlot.data('slot-number'), 10);
            if (!targetTaskId || !targetSlot) {
                return;
            }

            if (sourceTaskId === targetTaskId && sourceSlot === targetSlot) {
                return;
            }

            $.post(scAdmin.ajaxUrl, {
                action: 'sc_move_assignment',
                nonce: scAdmin.nonce,
                assignment_id: assignmentId,
                target_task_id: targetTaskId,
                target_slot: targetSlot
            }).done(function (response) {
                if (response && response.success) {
                    window.location.reload();
                } else {
                    const message = response && response.data && response.data.message ? response.data.message : 'Move failed.';
                    showAssignmentsNotice(message, true);
                }
            }).fail(function () {
                showAssignmentsNotice('Unable to move assignment.', true);
            });
        };

        $slots.draggable({
            helper: 'clone',
            revert: 'invalid',
            appendTo: 'body',
            zIndex: 9999,
            cursor: 'move',
            start: function () {
                $(this).addClass('sc-dragging');
            },
            stop: function () {
                $(this).removeClass('sc-dragging');
            }
        });

        $taskColumns.droppable({
            accept: '.sc-slot',
            hoverClass: 'sc-drop-target',
            tolerance: 'pointer',
            drop: function (_event, ui) {
                handleDrop($(this), ui.draggable);
            }
        });
    }

    function initReportsTabs() {
        const $tabWrap = $('.sc-reports-tabs').first();
        if (!$tabWrap.length) {
            return;
        }

        const $buttons = $tabWrap.find('[data-tab]');
        const $cards = $('.sc-report-card[data-report-tab]');
        if (!$buttons.length || !$cards.length) {
            return;
        }

        const setActiveTab = function (tab) {
            const target = String(tab || '');
            $buttons.removeClass('button-primary').addClass('button');
            $buttons.filter('[data-tab="' + target + '"]').removeClass('button').addClass('button-primary');

            $cards.hide();
            $cards.filter('[data-report-tab="' + target + '"]').show();
        };

        const defaultTab = $tabWrap.data('default-tab') || $buttons.first().data('tab');
        setActiveTab(defaultTab);

        $buttons.on('click', function () {
            const tab = $(this).data('tab');
            setActiveTab(tab);
        });
    }

    function initReportTableSorting() {
        const parseComparable = function (rawText) {
            const text = String(rawText || '').trim();
            if (!text) {
                return '';
            }

            const dateValue = Date.parse(text.replace(/\s+CST$/i, ' GMT-0600'));
            if (!Number.isNaN(dateValue)) {
                return dateValue;
            }

            const numericText = text.replace(/[^\d.-]/g, '');
            if (numericText && !Number.isNaN(Number(numericText))) {
                return Number(numericText);
            }

            return text.toLowerCase();
        };

        $('.sc-report-card').each(function () {
            const $card = $(this);
            const $tables = $card.find('table.widefat');

            $tables.each(function (tableIndex) {
                const $table = $(this);
                const $headers = $table.find('thead th');
                if (!$headers.length) {
                    return;
                }

                $headers.each(function (columnIndex) {
                    const $header = $(this);
                    $header.css('cursor', 'pointer');
                    $header.attr('title', 'Sort');

                    $header.on('click', function () {
                        const $tbody = $table.find('tbody').first();
                        const $rows = $tbody.find('tr').filter(function () {
                            const $cells = $(this).children('td');
                            return !($cells.length === 1 && Number($cells.eq(0).attr('colspan') || 0) > 1);
                        });

                        if ($rows.length <= 1) {
                            return;
                        }

                        const currentColumn = Number($table.attr('data-sort-column') || -1);
                        const currentDir = String($table.attr('data-sort-dir') || 'asc');
                        const nextDir = currentColumn === columnIndex && currentDir === 'asc' ? 'desc' : 'asc';

                        const sorted = $rows.get().sort(function (leftRow, rightRow) {
                            const leftText = $(leftRow).children().eq(columnIndex).text();
                            const rightText = $(rightRow).children().eq(columnIndex).text();
                            const leftValue = parseComparable(leftText);
                            const rightValue = parseComparable(rightText);

                            let compare = 0;
                            if (typeof leftValue === 'number' && typeof rightValue === 'number') {
                                compare = leftValue - rightValue;
                            } else {
                                compare = String(leftValue).localeCompare(String(rightValue), undefined, { numeric: true, sensitivity: 'base' });
                            }

                            return nextDir === 'desc' ? -compare : compare;
                        });

                        $tbody.append(sorted);
                        $table.attr('data-sort-column', String(columnIndex));
                        $table.attr('data-sort-dir', nextDir);
                        $card.attr('data-export-sort-column', String(columnIndex));
                        $card.attr('data-export-sort-dir', nextDir);
                        $card.attr('data-export-sort-table-index', String(tableIndex));
                    });
                });
            });

            $card.find('form[action*="admin-post.php"]').on('submit', function () {
                const sortColumn = $card.attr('data-export-sort-column');
                const sortDir = $card.attr('data-export-sort-dir');
                if (typeof sortColumn === 'undefined' || typeof sortDir === 'undefined') {
                    return;
                }

                const $form = $(this);
                $form.find('input[name="sort_column"]').remove();
                $form.find('input[name="sort_dir"]').remove();
                $form.append('<input type="hidden" name="sort_column" value="' + String(sortColumn) + '">');
                $form.append('<input type="hidden" name="sort_dir" value="' + String(sortDir) + '">');
            });
        });
    }

    function initTemplateEditor() {
        const $body = $('#sc_template_body');
        const $channel = $('#sc_template_channel');
        const $eventContext = $('#sc_template_event_context');
        const $smsContext = $('#sc_template_sms_context');
        const $count = $('#sc_template_char_count');
        const $encoding = $('#sc_template_encoding');
        const $segments = $('#sc_template_segments');
        const $smsEncodingBlock = $('#sc_template_sms_encoding_block');
        const $smsSegmentsBlock = $('#sc_template_sms_segments_block');
        const $costPerMessage = $('#sc_template_cost_per_message');
        const $eventRecipients = $('#sc_template_event_recipients');
        const $costTotal = $('#sc_template_cost_total');
        const $smsCostEstimates = $('#sc_template_sms_cost_estimates');
        const $insertToken = $('#sc_template_insert_token');
        const $preview = $('#sc_template_preview');

        if (!$body.length) {
            return;
        }

        const gsmBasic = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ`¿abcdefghijklmnopqrstuvwxyzäöñüà';
        const gsmExtended = '^{}\\[~]|€';

        const splitChars = function (text) {
            return Array.from(text || '');
        };

        const estimate = function (text) {
            const chars = splitChars(text);
            if (!chars.length) {
                return { charCount: 0, encoding: 'GSM-7', segments: 0 };
            }

            let septets = 0;
            let isGsm = true;
            for (let i = 0; i < chars.length; i++) {
                const ch = chars[i];
                if (gsmBasic.indexOf(ch) !== -1) {
                    septets += 1;
                    continue;
                }
                if (gsmExtended.indexOf(ch) !== -1) {
                    septets += 2;
                    continue;
                }
                isGsm = false;
                break;
            }

            if (isGsm) {
                return {
                    charCount: chars.length,
                    encoding: 'GSM-7',
                    segments: septets <= 160 ? 1 : Math.ceil(septets / 153)
                };
            }

            return {
                charCount: chars.length,
                encoding: 'UCS-2',
                segments: chars.length <= 70 ? 1 : Math.ceil(chars.length / 67)
            };
        };

        const renderPreview = function (text) {
            if (!$preview.length) {
                return;
            }

            const tokenMapRaw = $preview.attr('data-token-preview') || '{}';
            let tokenMap = {};
            try {
                tokenMap = JSON.parse(tokenMapRaw);
            } catch (_e) {
                tokenMap = {};
            }

            const replaced = String(text || '').replace(/\{([a-z0-9_]+)\}/gi, function (_match, key) {
                return Object.prototype.hasOwnProperty.call(tokenMap, key) ? String(tokenMap[key]) : '{' + key + '}';
            });
            $preview.val(replaced);
        };

        const replaceTokens = function (text, tokenMap) {
            return String(text || '').replace(/\{([a-z0-9_]+)\}/gi, function (_match, key) {
                return Object.prototype.hasOwnProperty.call(tokenMap, key) ? String(tokenMap[key]) : '{' + key + '}';
            });
        };

        const getSmsEstimateContext = function () {
            let eventEstimateMap = {};
            if ($smsContext.length) {
                try {
                    eventEstimateMap = JSON.parse(String($smsContext.attr('data-event-estimates') || '{}'));
                } catch (_e) {
                    eventEstimateMap = {};
                }
            }

            const selectedEventId = String($eventContext.val() || '');
            const selectedEventEstimate = selectedEventId && eventEstimateMap[selectedEventId] ? eventEstimateMap[selectedEventId] : null;

            let defaultTokens = {};
            if ($preview.length) {
                try {
                    defaultTokens = JSON.parse(String($preview.attr('data-token-preview') || '{}'));
                } catch (_e) {
                    defaultTokens = {};
                }
            }

            return {
                tokenMap: selectedEventEstimate && selectedEventEstimate.tokens ? selectedEventEstimate.tokens : defaultTokens,
                recipientCount: selectedEventEstimate && selectedEventEstimate.recipient_count ? parseInt(selectedEventEstimate.recipient_count, 10) || 0 : 0,
                gsmRate: parseFloat(String($smsContext.attr('data-gsm-rate') || '0')) || 0,
                unicodeRate: parseFloat(String($smsContext.attr('data-unicode-rate') || '0')) || 0
            };
        };

        const sync = function () {
            const text = $body.val() || '';
            const context = getSmsEstimateContext();
            const channel = String($channel.val() || 'email');
            const previewBase = replaceTokens(text, context.tokenMap || {});
            const info = estimate(channel === 'sms' ? previewBase : text);
            if ($count.length) {
                $count.text(String(info.charCount));
            }
            if ($encoding.length) {
                $encoding.text(info.encoding);
            }
            if ($segments.length) {
                $segments.text(String(info.segments));
            }

            const perSegmentRate = info.encoding === 'GSM-7' ? context.gsmRate : context.unicodeRate;
            const costPerMessage = info.segments * perSegmentRate;
            const recipientCount = channel === 'sms' ? context.recipientCount : 0;
            const totalCost = channel === 'sms' ? (costPerMessage * recipientCount) : 0;

            if ($smsCostEstimates.length) {
                $smsCostEstimates.toggle(channel === 'sms');
            }
            if ($smsEncodingBlock.length) {
                $smsEncodingBlock.toggle(channel === 'sms');
            }
            if ($smsSegmentsBlock.length) {
                $smsSegmentsBlock.toggle(channel === 'sms');
            }

            if ($costPerMessage.length) {
                $costPerMessage.text(costPerMessage.toFixed(4));
            }
            if ($eventRecipients.length) {
                $eventRecipients.text(String(recipientCount));
            }
            if ($costTotal.length) {
                $costTotal.text(totalCost.toFixed(4));
            }

            renderPreview(previewBase);
        };

        if ($insertToken.length) {
            $insertToken.on('change', function () {
                const token = String($(this).val() || '');
                if (!token) {
                    return;
                }

                const input = $body.get(0);
                if (!input) {
                    return;
                }

                const start = input.selectionStart || 0;
                const end = input.selectionEnd || start;
                const text = String($body.val() || '');
                const updated = text.substring(0, start) + token + text.substring(end);
                $body.val(updated);
                input.focus();
                const nextPos = start + token.length;
                input.setSelectionRange(nextPos, nextPos);
                $(this).val('');
                sync();
            });
        }

        $body.on('input', sync);
        $channel.on('change', sync);
        $eventContext.on('change', sync);
        sync();
    }

    function initCampaignPreviews() {
        const $previewData = $('#sc_campaign_preview_data');
        if (!$previewData.length) {
            return;
        }

        const parseJsonAttr = function (name) {
            try {
                return JSON.parse(String($previewData.attr(name) || '{}'));
            } catch (_e) {
                return {};
            }
        };

        const emailTemplates = parseJsonAttr('data-email-templates');
        const smsTemplates = parseJsonAttr('data-sms-templates');
        const eventTitles = parseJsonAttr('data-event-titles');
        const tokenPreview = parseJsonAttr('data-token-preview');

        const replaceTokens = function (text, tokenMap) {
            return String(text || '').replace(/\{([a-z0-9_]+)\}/gi, function (_match, key) {
                return Object.prototype.hasOwnProperty.call(tokenMap, key) ? String(tokenMap[key]) : '{' + key + '}';
            });
        };

        $('.sc-card form').each(function () {
            const $form = $(this);
            const scope = String($form.find('input[name="sc_preview_scope"]').val() || '');
            if (!scope) {
                return;
            }

            const $emailSelect = $form.find('select[name="email_template_id"]');
            const $smsSelect = $form.find('select[name="sms_template_id"]');
            const $eventSelect = $form.find('select[name="event_id"]');
            const $excludeEventSelect = $form.find('select[name="exclude_event_id"]');
            const $emailPreview = $form.find('.sc-live-email-preview');
            const $smsPreview = $form.find('.sc-live-sms-preview');

            if (!$emailPreview.length || !$smsPreview.length) {
                return;
            }

            const sync = function () {
                const selectedEventId = String(($eventSelect.val() || $excludeEventSelect.val() || ''));
                const tokens = Object.assign({}, tokenPreview);
                if (selectedEventId && Object.prototype.hasOwnProperty.call(eventTitles, selectedEventId)) {
                    tokens.event_title = String(eventTitles[selectedEventId] || tokens.event_title || '');
                }

                const emailId = String($emailSelect.val() || '0');
                const smsId = String($smsSelect.val() || '0');
                const emailTemplate = emailTemplates[emailId] || null;
                const smsTemplate = smsTemplates[smsId] || null;

                if (emailTemplate) {
                    const renderedSubject = replaceTokens(String(emailTemplate.subject || ''), tokens);
                    const renderedBody = replaceTokens(String(emailTemplate.body || ''), tokens);
                    const subjectPrefix = renderedSubject ? ('Subject: ' + renderedSubject + '\n\n') : '';
                    $emailPreview.val(subjectPrefix + renderedBody);
                } else {
                    $emailPreview.val('No email template selected.');
                }

                if (smsTemplate) {
                    const renderedSms = replaceTokens(String(smsTemplate.body || ''), tokens);
                    $smsPreview.val(renderedSms);
                } else {
                    $smsPreview.val('No SMS template selected.');
                }
            };

            $emailSelect.on('change', sync);
            $smsSelect.on('change', sync);
            $eventSelect.on('change', sync);
            $excludeEventSelect.on('change', sync);
            sync();
        });
    }

    initTaskWizardConfirm();
    initMediaPicker();
    initDragDrop();
    initReportsTabs();
    initReportTableSorting();
    initTemplateEditor();
    initCampaignPreviews();
});
