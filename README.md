# Schedule and Check-In (WordPress Plugin)

A volunteer scheduling and attendance plugin for events of different types (adoration schedules, fundraiser work days, and more).

## Included Features

- Event management:
  - Event category support and per-event substitute enable/disable.
  - Event owner fields and assignment-report delivery configuration.
  - Event image support via WordPress media picker.
  - Admin PIN support for kiosk override.
- Task and slot management:
  - Task CRUD with start/end windows and slot counts.
  - Task generation wizard for interval-based schedules.
  - Task title schema tokens (for generated task naming).
  - Assignment management with drag-and-drop movement.
- Public signup (`[sc_signup event_id="123"]`):
  - Scheduled and substitute-aware signup behavior.
  - Auto-assignment to first available slot for scheduled signup.
  - Current schedule slot-capacity display.
  - Optional volunteer field visibility controls.
- Kiosk check-in (`[sc_kiosk event_id="123"]`):
  - Standalone kiosk rendering (no theme header/footer/sidebar wrappers).
  - Volunteer check-in/check-out and guest check-in flows.
  - PIN verification (volunteer phone last 4 or admin PIN override).
- Communications:
  - Email/SMS template management with token support.
  - Live email/SMS previews for campaign send forms.
  - Campaign actions for scheduled members, substitute requests, and mass messaging.
  - Preferred-channel sending with fallback behavior.
  - Inactive volunteer communication suppression.
  - Automated thank-you messages for signup and guest kiosk check-in.
- Volunteer administration:
  - Dedicated Volunteers admin page.
  - Volunteer edit and active/inactive status controls.
  - Merge workflow with volunteer-id cascade across assignments, check-ins, and communication logs.
  - Merge metadata tracking (`merged_into_volunteer_id`, `merged_at`).
- Reporting and analytics (CST-oriented views):
  - Event Hours, Assignment Report, Volunteer Period, Volunteer Lifetime Hours.
  - Attendance Reliability, Late/Early Analysis, Task Coverage Health.
  - Substitute Pool and Substitute History.
  - Check-In Method Audit, SMS Analytics, Retention & Activity (Monthly).
  - Volunteers report with profile fields, separated usage data, category-hour totals, and grand totals.
- Exports:
  - CSV / Excel / PDF export coverage for core and advanced reports.
  - DomPDF-based printable check-in sheet.
  - Volunteers Excel export includes filter metadata and totals summary rows.
- Operational tools:
  - Check-in log editing and pagination.
  - `Check Out All Remaining` bulk action (records `admin_manual` source).
  - Assignment report send-now and scheduled/final automated assignment report sends.

## Install

1. Copy this folder into your WordPress plugins directory.
2. In the plugin folder, install dependencies with `composer install --no-dev --optimize-autoloader`.
3. Activate **Schedule and Check-In** in WordPress admin.
4. Review **Schedule & Check-In > Settings** and verify dependency status, Twilio values (if used), email sender settings, and event categories.
5. Create an event from **Schedule & Check-In > Events** and add tasks.
6. Place shortcodes on pages and run the Production Release Checklist before go-live.

> Note: `.xlsx` and native PDF exports require the Composer dependencies. Without them, those export actions are disabled with admin warnings.

## Shortcodes

- Signup page: `[sc_signup event_id="EVENT_ID"]`
- Kiosk page: `[sc_kiosk event_id="EVENT_ID"]`

## Production Release Checklist

Run this checklist in a staging WordPress site before promoting to production:

1. **Dependencies and activation**
  - Confirm plugin activates without warnings.
  - Confirm dependency status is healthy in Settings.
  - Confirm `.xlsx` and PDF exports work (dependencies loaded).

2. **Core event flow**
  - Create/edit event (owner info, category, substitute toggle, reminders, admin PIN).
  - Create/edit/delete tasks and verify slot counts.
  - Verify assignment management and drag/drop slot movement.

3. **Public signup + kiosk flow**
  - Verify signup page renders and registers volunteers.
  - Verify kiosk standalone rendering (no theme wrappers).
  - Verify volunteer check-in/check-out and guest check-in.
  - Verify thank-you communications for signup and guest check-in.

4. **Communications**
  - Verify template CRUD behavior and preview rendering.
  - Verify campaign sends for scheduled, substitute request, and mass messaging.
  - Verify inactive volunteers are not contacted.
  - Verify communication logs and export actions.

5. **Reports and exports**
  - Run each report tab with valid filters and empty-state filters.
  - Verify CSV/Excel/PDF exports for each report.
  - Verify Volunteers report filters, totals, and Excel metadata rows.
  - Verify DomPDF check-in sheet generation.

6. **Volunteer administration**
  - Verify volunteer edit and active/inactive status updates.
  - Verify merge workflow and downstream data remap (assignments/checkins/comms).

7. **Regression pass**
  - Re-run one complete end-to-end event cycle after all config updates.
  - Validate no PHP errors/warnings in debug logs.

## Notes

- Stable release: `1.0.0`.
- Recommended deployment flow: validate on staging first, then promote to production.
- `.xlsx` and PDF exports require Composer dependencies to be present in the deployed plugin.
