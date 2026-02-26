# Changelog

All notable changes to this plugin are documented in this file.

## 1.0.0 - 2026-02-26
- Promoted plugin to stable production release `1.0.0`.
- Completed release validation pass:
  - syntax checks across all first-party plugin PHP files,
  - editor diagnostics review for plugin source.
- Finalized Volunteers report improvements for production use, including Excel export metadata with selected filters and totals.

## 0.2.25 - 2026-02-26
- Added a new Reports tab: `Volunteers`.
- New Volunteers report includes all Volunteer List profile fields and separates usage data into dedicated columns.
- Added volunteered hours by event category per volunteer plus per-volunteer total hours.
- Added report filters for:
  - Event (multi-select, includes All),
  - Event Category (multi-select, includes All),
  - Volunteer Name (includes All),
  - Volunteer Active status (Yes / No / All),
  - Period (This Week / This Month / This Year / Date Range / All Time).
- Added grand totals for usage metrics (assignments, check-ins, messages), hours by event category, and grand total volunteered hours.
- Added export support for the new Volunteers report using the same CSV / Excel / PDF advanced report flow as existing reports.
- Added Volunteers Excel export metadata rows (Excel-only) for selected filters and report totals, including category-hour totals and grand total volunteered hours.

## 0.2.24 - 2026-02-26
- Added live Email and SMS preview panes on Communications page for:
  - Message Scheduled Event Members,
  - Request Substitutions,
  - Mass Message Volunteers.
- Preview rendering now applies template token replacements (including selected event context) in real time as templates/events are changed.
- Enforced a global communication guard so inactive volunteers (`is_active = 0`) do not receive email or SMS via the centralized volunteer message sender.

## 0.2.23 - 2026-02-26
- Enhanced volunteer merge behavior with explicit cascade updates of `volunteer_id` across all volunteer-linked plugin tables (`assignments`, `checkins`, `comm_logs`) in a transaction.
- Added volunteer merge metadata fields (`merged_into_volunteer_id`, `merged_at`) to track merge destination/history.
- Merge now explicitly marks source volunteers inactive and records the merge target after successful cascade.
- Volunteers page now displays merge destination context for inactive merged records.

## 0.2.22 - 2026-02-26
- Switched Print Check-In Sheet to native DomPDF generation with stable column widths and page-break-safe task/slot rows.
- Added a new Volunteers admin page for:
  - editing volunteer records,
  - merging duplicate volunteers (re-maps assignments, check-ins, and communication logs),
  - marking volunteers active/inactive.
- Added `is_active` support to volunteers schema with compatibility migration.
- Removed delete functionality from the new Volunteers page UI by design.

## 0.2.21 - 2026-02-26
- Added automatic thank-you message sending after signup and guest kiosk check-in, routed through each volunteer’s preferred communication channel.
- Updated default thank-you email/SMS template body content.
- Added task/time token sentence to default reminder templates.
- Added/expanded template tokens: `{task_title}` and `{volunteer_end_datetime_cst}`.

## 0.2.20 - 2026-02-26
- Added per-event substitute enable/disable setting.
- Enforced substitute visibility/behavior across signup, check-in, reports, and relevant admin actions.
- Updated kiosk attendee ordering and substitute handling.
- Restricted dependency status display to Settings page only.
- Updated SMS estimate recipients logic to count only scheduled volunteers preferring SMS.
