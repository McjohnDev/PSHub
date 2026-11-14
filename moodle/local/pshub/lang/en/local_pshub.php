<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English strings for local_pshub.
 *
 * @package    local_pshub
 * @copyright  PS Language Hub
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'PS Language Hub';

// Privacy: table purposes.
$string['privacy:metadata:userprofile'] = 'Platform profile settings for a learner.';
$string['privacy:metadata:access'] = 'Which courses and packs a learner may open, and on what basis.';
$string['privacy:metadata:transaction'] = 'Payments made by a learner and their validation. Retained as financial records and unlinked rather than deleted on request.';
$string['privacy:metadata:session'] = 'Live sessions scheduled by a teacher.';
$string['privacy:metadata:attendance'] = 'Attendance at live sessions.';
$string['privacy:metadata:assign'] = 'Competency briefs assigned to a teacher.';
$string['privacy:metadata:xp'] = 'Experience points earned by a learner.';
$string['privacy:metadata:learner'] = 'A summary of a learner\'s progress, derived from the points ledger.';
$string['privacy:metadata:groupmember'] = 'Membership of a learning group.';
$string['privacy:metadata:pushtoken'] = 'Device identifiers used to send session reminders.';
$string['privacy:metadata:audit'] = 'A record of who validated courses and payments. Retained for accountability and unlinked rather than deleted on request.';

// Privacy: fields.
$string['privacy:metadata:userprofile:userid'] = 'The user this profile belongs to.';
$string['privacy:metadata:userprofile:phone'] = 'The mobile number used for Mobile Money payments.';
$string['privacy:metadata:userprofile:lang'] = 'Preferred interface language.';
$string['privacy:metadata:userprofile:audience'] = 'Whether the catalogue is shown for adults or children.';

$string['privacy:metadata:access:userid'] = 'The user holding the access right.';
$string['privacy:metadata:access:target'] = 'The course or pack the right applies to.';
$string['privacy:metadata:access:source'] = 'Whether access was automatic, paid for, or granted by hand.';
$string['privacy:metadata:access:status'] = 'Whether the right is active, expired or revoked.';
$string['privacy:metadata:access:reason'] = 'The reason recorded when access was granted by hand.';

$string['privacy:metadata:transaction:userid'] = 'The user who made the payment.';
$string['privacy:metadata:transaction:amount'] = 'The amount paid.';
$string['privacy:metadata:transaction:method'] = 'The payment method used.';
$string['privacy:metadata:transaction:msisdn'] = 'The mobile number that paid.';
$string['privacy:metadata:transaction:status'] = 'Where the payment reached in validation.';

$string['privacy:metadata:session:teacherid'] = 'The teacher running the session.';
$string['privacy:metadata:session:title'] = 'The title of the session.';
$string['privacy:metadata:session:start'] = 'When the session was scheduled to start.';

$string['privacy:metadata:attendance:userid'] = 'The participant.';
$string['privacy:metadata:attendance:joined'] = 'When they joined.';
$string['privacy:metadata:attendance:duration'] = 'How long they were present.';
$string['privacy:metadata:attendance:present'] = 'Whether this counted as attendance.';

$string['privacy:metadata:assign:teacherid'] = 'The teacher the brief was assigned to.';
$string['privacy:metadata:assign:brief'] = 'What the teacher was asked to cover.';
$string['privacy:metadata:assign:status'] = 'Progress against the brief.';

$string['privacy:metadata:xp:userid'] = 'The learner who earned the points.';
$string['privacy:metadata:xp:event'] = 'What the points were earned for.';
$string['privacy:metadata:xp:points'] = 'How many points were awarded.';

$string['privacy:metadata:learner:userid'] = 'The learner this summary describes.';
$string['privacy:metadata:learner:xp'] = 'Total points earned.';
$string['privacy:metadata:learner:streak'] = 'Consecutive days of activity.';

$string['privacy:metadata:groupmember:userid'] = 'The group member.';
$string['privacy:metadata:groupmember:joined'] = 'When they joined the group.';

$string['privacy:metadata:pushtoken:userid'] = 'The owner of the device.';
$string['privacy:metadata:pushtoken:token'] = 'The device identifier used to deliver notifications.';
$string['privacy:metadata:pushtoken:platform'] = 'The device platform.';

$string['privacy:metadata:audit:actorid'] = 'The person who took the action.';
$string['privacy:metadata:audit:action'] = 'What was done.';
$string['privacy:metadata:audit:ip'] = 'The address the action came from.';

// Capabilities.
$string['pshub:viewteacherportal'] = 'View the teacher portal';
$string['pshub:submitcourse'] = 'Submit a course for validation';
$string['pshub:schedulesession'] = 'Schedule a live session';
$string['pshub:viewadminportal'] = 'View the administration portal';
$string['pshub:publishcourse'] = 'Approve or reject a submitted course';
$string['pshub:validatepayment'] = 'Validate a payment and open access';
$string['pshub:managecoursetypes'] = 'Manage course types and badges';
$string['pshub:manageteachers'] = 'Manage teachers and their briefs';
$string['pshub:managexprules'] = 'Manage the XP scale';
$string['pshub:viewaccounting'] = 'View revenue and transactions';
$string['pshub:grantaccess'] = 'Grant access to a course by hand';
$string['pshub:manageadmins'] = 'Create and manage administrator accounts';

// Roles.
$string['role_pshub_admin'] = 'Principal administrator';
$string['role_pshub_admin_desc'] = 'Validates courses and payments, sets pricing and course types, assigns competency briefs to teachers, and configures XP and certificates.';
$string['role_pshub_teacher'] = 'Teacher';
$string['role_pshub_teacher_desc'] = 'Creates courses within an assigned brief, schedules and runs live sessions, and submits courses for validation. Cannot publish.';

// Gating outcomes. A learner never sees the identifier, only these.
$string['denied_payment_required'] = 'This course is paid. Choose a payment method to continue.';
$string['denied_payment_pending'] = 'Payment received. We are checking it now, usually within the hour. You will be notified as soon as the course opens.';
$string['denied_not_available'] = 'This course is not available at the moment.';
$string['denied_access_expired'] = 'Your access to this course has ended.';

// Audit actions.
$string['audit_course_submitted'] = 'Course submitted for validation';
$string['audit_course_published'] = 'Course approved';
$string['audit_course_rejected'] = 'Course rejected';
$string['audit_course_archived'] = 'Course archived';
$string['audit_payment_received'] = 'Payment received, awaiting validation';
$string['audit_payment_validated'] = 'Payment validated, access opened';
$string['audit_payment_rejected'] = 'Payment rejected';
$string['audit_access_granted'] = 'Access granted by hand';
$string['audit_access_revoked'] = 'Access revoked';

// Settings.
$string['settings_sessions'] = 'Live sessions';
$string['settings_sessioncap'] = 'Maximum session length';
$string['settings_sessioncap_desc'] = 'The hard cap on a live session. Teachers may schedule shorter, never longer. BigBlueButton enforces this server-side, so it holds even when every client has been disconnected.';
$string['settings_presencethreshold'] = 'Presence threshold (%)';
$string['settings_presencethreshold_desc'] = 'Share of the scheduled duration a learner must attend before the session counts as attended. Ninety seconds in a fifty minute class is not attendance.';
$string['settings_monetisation'] = 'Monetisation';
$string['settings_packsenabled'] = 'Enable course packs';
$string['settings_packsenabled_desc'] = 'When off, a pack-level access right grants nothing, even if one exists. Leave off for Phase 1.';
$string['settings_autovalidate'] = 'Auto-validate {$a} payments';
$string['settings_autovalidate_desc'] = 'When on, a verified callback opens access without an administrator reviewing it. Leave off until reconciliation for this method is trusted.';

// Payment methods.
$string['method_mtn_momo'] = 'MTN Mobile Money';
$string['method_orange_money'] = 'Orange Money';
$string['method_wave'] = 'Wave';
$string['method_card'] = 'Card';
$string['method_bank_transfer'] = 'Bank transfer';
$string['method_cash'] = 'Cash';

// Errors.
$string['cannotenrol'] = 'This course could not be opened. Please try again.';

// Course type administration (AC-8).
$string['coursetypes'] = 'Course types and badges';
$string['coursetypes_intro'] = 'Types decide what badge a course carries and what it costs. Adding one here takes effect immediately, with no deployment.';
$string['type_add'] = 'Add a course type';
$string['type_edit'] = 'Edit course type';
$string['type_none'] = 'No course types yet. Add one to start badging the catalogue.';
$string['type_created'] = 'Course type created.';
$string['type_updated'] = 'Course type updated.';
$string['type_deleted'] = 'Course type deleted.';
$string['type_delete_confirm'] = 'Delete this course type?';
$string['type_delete_inuse'] = 'This type cannot be deleted: it is attached to {$a} course(s). Detach it first, or deactivate it instead.';
$string['type_badge'] = 'Badge';
$string['type_code'] = 'Code';
$string['type_code_help'] = 'The stable identifier the platform keys on, for example promo20 or hot. Letters, digits, hyphen and underscore. Changing it later breaks anything referring to the old value, so choose once.';
$string['type_code_taken'] = 'Another course type already uses this code.';
$string['type_name_fr'] = 'Name (French)';
$string['type_name_en'] = 'Name (English)';
$string['type_name_en_help'] = 'Optional. French is the platform default, so a blank English name simply falls back to the French one.';
$string['type_kind'] = 'Kind';
$string['type_kind_help'] = 'What the type does. Only a discount changes the price; highlight, exam and custom are presentation only. Free grants access without payment.';
$string['type_discount'] = 'Discount (%)';
$string['type_discount_range'] = 'A discount must be between 1 and 100.';
$string['type_grantsfree'] = 'Grants free access';
$string['type_grantsfree_label'] = 'This type makes the course free';
$string['type_grantsfree_help'] = 'The badge becomes the price. A course carrying this type opens without payment even if a price is still set on it, so nobody has to remember to change two things.';
$string['type_free_vs_discount'] = 'A type cannot be both free and a discount. Free already sets the price to zero.';
$string['type_badgestyle'] = 'Badge colour';
$string['type_sortorder'] = 'Order';
$string['type_sortorder_help'] = 'Lower numbers appear first when a course carries several badges.';
$string['type_active'] = 'Active';
$string['type_active_label'] = 'Available for use';
$string['type_active_help'] = 'Deactivating a type stops it affecting prices and stops it rendering, without detaching it from every course first.';
$string['type_effect'] = 'Effect';
$string['type_effect_free'] = 'Course is free';
$string['type_effect_discount'] = '{$a}% off';
$string['type_effect_none'] = 'Presentation only';
$string['type_inuse'] = 'Courses';
$string['kind_discount'] = 'Discount';
$string['kind_highlight'] = 'Highlight';
$string['kind_free'] = 'Free';
$string['kind_exam'] = 'Exam';
$string['kind_custom'] = 'Custom';
$string['style_promo'] = 'Pink (promotion)';
$string['style_hot'] = 'Orange (popular)';
$string['style_free'] = 'Blue (free)';
$string['style_exam'] = 'Outline (neutral)';

// Public catalogue (S3).
$string['catalogue'] = 'Courses';
$string['catalogue_count'] = '{$a} course(s)';
$string['catalogue_empty'] = 'No course matches those filters yet. Try widening them.';
$string['filter_search'] = 'Search';
$string['filter_search_placeholder'] = 'Course title';
$string['filter_language'] = 'Language';
$string['filter_level'] = 'Level';
$string['filter_access'] = 'Access';
$string['filter_access_free'] = 'Free';
$string['filter_access_paid'] = 'Paid';
$string['filter_any'] = 'Any';
$string['filter_apply'] = 'Filter';
$string['filter_clear'] = 'Clear filters';
$string['card_free'] = 'Free';
$string['card_duration'] = '{$a} min per session';
$string['card_onetoone'] = 'One to one';
$string['cta_signin_to_enrol'] = 'Sign in to join this course';
$string['cta_browse_freely'] = 'Browsing the catalogue needs no account. You only sign in to join a course.';
$string['cta_open_course'] = 'Open the course';
$string['cta_enrol'] = 'Join this course';
$string['back_to_catalogue'] = 'Back to all courses';
$string['card_view'] = 'See the course';
$string['catalogue_lede'] = 'Every course is taught live by our teachers — pick yours.';
$string['filter_type'] = 'Type';
$string['breadcrumb'] = 'You are here';
$string['course_about'] = 'About this course';
$string['course_included_live'] = 'Live sessions of {$a} minutes with a teacher';
$string['course_included_board'] = 'Shared whiteboard and session materials';
$string['course_included_group'] = 'A small group, so you get to speak';
$string['course_included_onetoone'] = 'One to one with your teacher';
$string['course_pay_with'] = 'Pay with';
$string['course_pay_card'] = 'Bank card';
$string['course_pay_note'] = 'Access to the course opens as soon as your payment is confirmed.';

// -- The public home page -----------------------------------------------------
// Every claim here is one the platform keeps. A visitor in Douala finds out
// within a week whether it was true, so the app links say "coming soon" rather
// than pretending, and no figure appears that the product does not hold.

$string['home_title'] = 'Learn a language live';
$string['home_hero_title'] = 'Learn a language live, with real teachers.';
$string['home_hero_lede'] = '100% live classes: video, a shared whiteboard and personal follow-up — built to keep working on a 2G or 3G connection.';
$string['home_hero_cta'] = 'Browse the courses';
$string['home_pill_live'] = '50-minute live sessions';
$string['home_pill_momo'] = 'Mobile Money payment';
$string['home_pill_certificate'] = 'QR-verifiable certificates';

// The session illustration in the hero. Decorative and hidden from screen
// readers, but still translated: it is on the page in French for a
// French-speaking visitor.
$string['home_demo_course'] = 'English B1 — Conversation';
$string['home_demo_live'] = 'LIVE';
$string['home_demo_teacher'] = 'Your teacher';
$string['home_demo_you'] = 'You';
$string['home_demo_phrase'] = 'In my opinion…';
$string['home_demo_variants'] = 'I think · I believe · From my point of view';
$string['home_demo_board'] = 'Shared whiteboard';
$string['home_demo_join'] = 'Join';

$string['home_featured_title'] = 'Featured courses';
$string['home_featured_lede'] = 'Chosen by the teaching team.';
$string['home_featured_all'] = 'See the whole catalogue';

$string['home_steps_title'] = 'How it works';
$string['home_step1_title'] = 'Create your account';
$string['home_step1_text'] = 'Free, and it takes two minutes.';
$string['home_step2_title'] = 'Choose a course and pay';
$string['home_step2_text'] = 'MTN MoMo, Orange Money or a bank card. Access opens as soon as the payment is confirmed.';
$string['home_step3_title'] = 'Join your live sessions';
$string['home_step3_text'] = 'Video, whiteboard and shared documents, with a reminder before every session.';

$string['home_network_title'] = 'Built for African networks';
$string['home_network_lede'] = 'A live session stays usable from 64 kbit/s; full video from about 300 kbit/s.';
$string['home_network_audio_title'] = 'Audio only, automatically';
$string['home_network_audio_text'] = 'When bandwidth drops the video stops, and the voice and the whiteboard carry on.';
$string['home_network_board_title'] = 'A vector whiteboard';
$string['home_network_board_text'] = 'Strokes and documents travel as light data, never as a video stream.';
$string['home_network_reconnect_title'] = 'Reconnect without losing your place';
$string['home_network_reconnect_text'] = 'A short network drop does not throw you out of the session.';

$string['home_app_title'] = 'Take your courses with you';
$string['home_app_point_size'] = 'A light app, under 25 MB';
$string['home_app_point_offline'] = 'Download materials on Wi-Fi, read them offline';
$string['home_app_point_reminders'] = 'Session reminders 24 h, 1 h and 10 min before the class';

$string['home_certificate_title'] = 'XP, skills and certificates';
$string['home_certificate_text'] = 'Earn XP in every session, validate your skills, and receive a certificate anyone can check by QR code.';
$string['home_teachers_title'] = 'A teacher, not a video library';
$string['home_teachers_text'] = 'Every course here is taught live by someone who answers you. There is no shelf of recordings to work through alone.';

$string['home_faq_title'] = 'Frequent questions';
$string['home_faq_q_live'] = 'Are the classes really live?';
$string['home_faq_a_live'] = 'Yes, every one of them. A session is 50 minutes with a teacher, in a small group or one to one. Nothing on this platform is a pre-recorded video.';
$string['home_faq_q_pay'] = 'How do I pay?';
$string['home_faq_a_pay'] = 'MTN Mobile Money, Orange Money or a bank card. The course opens once the payment is confirmed.';
$string['home_faq_q_missed'] = 'What if I miss a session?';
$string['home_faq_a_missed'] = 'The materials stay in your course and your teacher can tell you what was covered.';
$string['home_faq_q_data'] = 'How much data does a session use?';
$string['home_faq_a_data'] = 'A session works from 64 kbit/s with audio and whiteboard. Video needs about 300 kbit/s and switches itself off when the connection cannot hold it.';
$string['home_faq_cta'] = 'Create a free account';

// The custom profile field that captures the Mobile Money number at signup.
// Stored once as plain text by Moodle, in the site's language.
$string['profilecategory'] = 'Contact';
// The name this category was first created with, kept only so the upgrade
// step can recognise and rename it. Do not use it anywhere else.
$string['profilecategory_legacy'] = 'PS Language Hub';
$string['profilefield_phone'] = 'Phone number';
$string['profilefield_phone_help'] = 'Your Mobile Money number, for paying for a course. You can add it later.';

// -- The learner's own space --------------------------------------------------
// Single quotes where a string carries {$a}: PHP would eat the placeholder
// inside double quotes.
$string['learn_title'] = 'My progress';
$string['learn_greeting'] = 'Hello, {$a}';
$string['learn_tile_courses'] = 'Courses under way';
$string['learn_tile_sessions'] = 'Live sessions coming up';
$string['learn_tile_xp'] = 'XP earned';
$string['learn_courses_title'] = 'My courses';
$string['learn_find_courses'] = 'Find another course';
$string['learn_no_courses'] = 'You have not joined a course yet. Every course here is taught live, by a teacher, in a small group.';
$string['learn_browse'] = 'Browse the courses';
$string['learn_sessions_title'] = 'Coming up';
$string['learn_no_sessions'] = 'No live session is scheduled yet. Your teacher will schedule the next ones, and you will be reminded 24 h, 1 h and 10 min before each one.';
$string['learn_open_course'] = 'Open';
$string['catalogue_explore'] = 'Explore courses';

// -- The learner's own course page --------------------------------------------
// Single quotes where a string carries {$a}.
$string['learn_crumb_detail'] = 'Course detail';
$string['learn_course_level'] = 'Level {$a}';
$string['learn_course_duration'] = '{$a} min maximum per session';
$string['learn_course_group'] = '{$a} learners per group maximum';
$string['learn_course_onetoone'] = 'One-to-one course';
$string['learn_course_teacher'] = 'With {$a}';
$string['learn_course_teacher_pending'] = 'No teacher has been assigned to this course yet.';
$string['learn_course_content'] = 'Course content';
$string['learn_course_nocontent'] = 'This course has no online material yet: everything happens live, with your teacher.';
$string['learn_group_resources'] = 'Resources';
$string['learn_group_quizzes'] = 'Quizzes';
$string['learn_group_assignments'] = 'Assignments';
$string['learn_group_workshops'] = 'Workshops';
$string['learn_group_discussions'] = 'Discussions';
$string['learn_progress_title'] = 'My progress';
$string['learn_progress_sessions'] = '{$a->attended} of {$a->total} sessions';
$string['learn_progress_percent'] = '{$a}%';
$string['learn_live_title'] = 'Live sessions';
$string['learn_live_now'] = 'Live now';
$string['learn_live_join'] = 'Join the live session';
$string['learn_session_attended'] = 'Attended';
$string['learn_session_past'] = 'Past session';
$string['learn_session_upcoming'] = 'Coming up';
$string['learn_session_room_pending'] = 'The room opens shortly before the start.';
$string['learn_course_nosessions'] = 'No session is scheduled on this course yet. Your teacher will schedule the next ones, and you will be reminded 24 h, 1 h and 10 min before each one.';
$string['learn_course_unavailable'] = 'This course does not exist, or it is not published.';

// -- The teacher's console ----------------------------------------------------
// Single quotes where a string carries {$a}.
$string['status_draft'] = 'Draft';
$string['status_pending'] = 'In review';
$string['status_published'] = 'Published';
$string['status_rejected'] = 'Rejected';
$string['status_archived'] = 'Archived';
$string['teacher_title'] = 'Teacher space';
$string['teacher_greeting'] = 'Hello, {$a}';
$string['teacher_lede'] = 'Where your courses stand, and what is coming up.';
$string['teacher_tile_published'] = 'Published courses';
$string['teacher_tile_pending'] = 'Waiting for review';
$string['teacher_tile_learners'] = 'Learners enrolled';
$string['teacher_courses_title'] = 'My courses';
$string['teacher_create'] = 'Create a course in Moodle';
$string['teacher_open'] = 'Open';
$string['teacher_learner_one'] = '{$a} learner';
$string['teacher_learner_count'] = '{$a} learners';
$string['teacher_no_courses'] = 'You have no courses yet. Authoring happens in Moodle; review status and your sessions are followed here.';
$string['teacher_briefs_title'] = 'What the administration asked of you';
$string['teacher_sessions_title'] = 'My next sessions';
$string['teacher_no_sessions'] = 'No session scheduled. Yours will appear here as soon as they are.';
$string['teacher_note_reminders'] = 'Reminders go out automatically 24 h, 1 h and 10 min before each session.';
$string['teacher_note_brief'] = 'Your courses must cover the competencies assigned by the administration before submission.';

// -- The administration console -----------------------------------------------
// Single quotes where a string carries {$a}.
$string['admin_title'] = 'Overview';
$string['admin_lede'] = 'What is waiting for a decision, and what exists on the platform.';
$string['admin_tile_pending'] = 'Courses to review';
$string['admin_tile_published'] = 'Published courses';
$string['admin_tile_learners'] = 'Learners enrolled';
$string['admin_tile_teachers'] = 'Teachers';
$string['admin_queue_title'] = 'Courses waiting for review';
$string['admin_queue_empty'] = 'No course is waiting for a decision.';
$string['admin_queue_readonly'] = 'Approving or rejecting happens on the course itself; this page changes nothing.';
$string['admin_review'] = 'Review';
$string['admin_types_title'] = 'Active types and badges';
$string['admin_types_manage'] = 'Manage types';
$string['admin_money_title'] = 'Payments';
$string['admin_money_validated'] = 'Taken and validated';
$string['admin_money_pending'] = '{$a} payment(s) to validate';
$string['admin_money_none'] = 'No payment has been recorded yet.';
$string['admin_note_access'] = 'Access to a course is opened by validating a payment, never by hand. Every validation is logged.';
$string['admin_note_drafts'] = '{$a} courses are still drafts and have not been submitted.';
$string['nav_admin_console'] = 'PS Hub administration';
$string['nav_teacher_console'] = 'Teacher space';

// -- The course validation workflow -------------------------------------------
// Single quotes where a string carries {$a}.
$string['messageprovider:coursesubmitted'] = 'A course has been submitted for review';
$string['messageprovider:coursereviewed'] = 'Decision on a course you submitted';
$string['review_submit'] = 'Submit for review';
$string['review_approve'] = 'Approve';
$string['review_reject'] = 'Reject';
$string['review_cannot_submit'] = 'This course cannot be submitted as it stands.';
$string['review_not_pending'] = 'This course is not waiting for a decision.';
$string['review_comment_required'] = 'A rejection has to say why. Without a reason the teacher learns nothing.';
$string['review_incomplete'] = 'This course is incomplete: {$a}';
$string['review_problem_notahubcourse'] = 'This course is not part of the platform.';
$string['review_problem_nosummary'] = 'it has no description;';
$string['review_problem_nolanguage'] = 'the language taught is not set;';
$string['review_problem_nolevel'] = 'the CEFR level is not set;';
$string['review_problem_noprice'] = 'it is marked paid but its price is zero;';
$string['review_submitted'] = 'Course submitted for review.';
$string['review_approved'] = 'Course approved and published.';
$string['review_rejected'] = 'Course rejected, and the reason has been sent to its author.';
$string['review_reject_title'] = 'Reject this course';
$string['review_reject_lede'] = 'Say why. This text goes to the teacher: it is their only indication of what to fix.';
$string['review_reject_label'] = 'Reason for rejection';
$string['review_confirm_approve'] = 'Publish "{$a}"?';
$string['review_blocked'] = 'To complete before submitting: {$a}';
$string['notify_submitted_subject'] = 'A course is waiting for your review';
$string['notify_submitted_body'] = 'The course "{$a}" has been submitted and is waiting for a decision.';
$string['notify_approved_subject'] = 'Your course is published';
$string['notify_approved_body'] = 'The course "{$a}" has been approved: it is now visible in the catalogue.';
$string['notify_rejected_subject'] = 'Your course was not accepted';
$string['notify_rejected_body'] = 'The course "{$a->course}" was rejected. Reason: {$a->comment}';
$string['admin_queue_audited'] = 'Approving publishes the course to the catalogue. Rejecting requires a reason, which is sent to its author. Every decision is logged.';

// -- Creating and editing a hub course ----------------------------------------
// Single quotes where a string carries {$a}.
$string['course_form_create'] = 'Create a course';
$string['course_form_edit'] = 'Edit course';
$string['course_form_lede'] = 'What the catalogue needs to know. The course content is then written in Moodle.';
$string['course_form_about'] = 'The course';
$string['course_form_title'] = 'Title';
$string['course_form_summary'] = 'Description';
$string['course_form_summary_help'] = 'This is what a visitor reads before paying. Say what is learned, and who it is for.';
$string['course_form_language'] = 'Language taught';
$string['course_form_level'] = 'CEFR level';
$string['course_form_audience'] = 'Audience';
$string['audience_adult'] = 'Adults';
$string['audience_kid'] = 'Children';
$string['course_form_teaching'] = 'How it runs';
$string['course_form_duration'] = 'Session length (minutes)';
$string['course_form_duration_help'] = 'The platform ends a session at the configured maximum whatever a course claims. You may plan shorter, never longer.';
$string['course_form_duration_short'] = 'A session under 10 minutes does not stand up.';
$string['course_form_duration_long'] = 'The maximum on this platform is {$a} minutes.';
$string['course_form_onetoone'] = 'One-to-one course';
$string['course_form_maxparticipants'] = 'Learners per group (maximum)';
$string['course_form_group_small'] = 'A group of fewer than two people is one-to-one: tick the box instead.';
$string['course_form_price'] = 'The price';
$string['course_form_access'] = 'Access';
$string['course_form_amount'] = 'Price (FCFA)';
$string['course_form_amount_help'] = 'The price shown in the catalogue. A badge discount applies on top of it.';
$string['course_form_price_zero'] = 'A paid course needs a price above zero.';
$string['course_form_save'] = 'Save';
$string['course_form_created'] = 'Course created as a draft. Add your content in Moodle, then submit it for review.';
$string['course_form_updated'] = 'Course updated.';
$string['course_locked'] = 'This course cannot be edited: it is under review, or already published.';
$string['teacher_edit'] = 'Edit';
$string['settings_catalogue'] = 'Catalogue';
$string['settings_languages'] = 'Languages taught';
$string['settings_languages_desc'] = 'The language codes offered when a course is created, comma separated. Adding a language here is all it takes - nothing to deploy.';
$string['settings_category'] = 'Course category';
$string['settings_category_desc'] = 'The Moodle category hub courses are created in. Left empty, the first visible category is used.';

// -- Featured courses ---------------------------------------------------------
// Single quotes where a string carries {$a}.
$string['featured_title'] = 'Featured courses';
$string['featured_lede'] = 'The courses the home page leads with, in this order.';
$string['featured_current'] = 'Featured now';
$string['featured_seehome'] = 'See the home page';
$string['featured_position'] = 'Position {$a}';
$string['featured_up'] = 'Move up';
$string['featured_down'] = 'Move down';
$string['featured_remove'] = 'Remove';
$string['featured_none'] = 'No course is featured. The home page then shows the most recently published ones.';
$string['featured_add'] = 'Add a course';
$string['featured_choose'] = 'Course to feature';
$string['featured_add_action'] = 'Feature it';
$string['featured_nochoices'] = 'Every published course is already featured.';
$string['featured_saved'] = 'Home page updated.';
$string['featured_not_published'] = 'An unpublished course cannot be featured: the catalogue would refuse to show it.';
$string['nav_featured'] = 'Featured';
$string['featured_notlive'] = "Not shown: this course is not published.";

// -- Live sessions ------------------------------------------------------------
// Single quotes where a string carries {$a}.
$string['sessions_title'] = 'Live sessions';
$string['sessions_lede'] = 'The dates this course actually runs. Without them nobody can take it.';
$string['sessions_add'] = 'Schedule a session';
$string['sessions_edit'] = 'Move the session';
$string['sessions_upcoming'] = 'Coming up';
$string['sessions_past'] = 'Past';
$string['sessions_none'] = 'No session scheduled. While this list is empty the course has no date to announce.';
$string['sessions_move'] = 'Move';
$string['sessions_cancel'] = 'Cancel';
$string['sessions_was_cancelled'] = 'cancelled';
$string['sessions_plate'] = 'LIVE';
$string['session_form_title'] = 'Session title';
$string['session_form_title_help'] = 'What learners will see in their diary. "Session 3 - at the restaurant" beats "Session 3".';
$string['session_form_start'] = 'Starts';
$string['session_form_minutes'] = 'Length (minutes)';
$string['session_form_caphint'] = 'The platform closes every session at {$a} minutes. You may plan shorter; anything longer is brought down to this limit.';
$string['session_form_save'] = 'Save the session';
$string['session_form_past'] = 'That date has passed. A session nobody can attend is not worth scheduling.';
$string['session_scheduled'] = 'Session scheduled.';
$string['session_rescheduled'] = 'Session moved.';
$string['session_cancelled'] = 'Session cancelled. Learners will no longer see it as an appointment.';
$string['session_title_required'] = 'A session needs a title.';
$string['session_too_short'] = 'A session under 10 minutes does not stand up.';
$string['session_not_movable'] = 'This session has started or finished: it belongs to the record, not the plan.';
$string['teacher_sessions_link'] = 'Sessions';

// -- Walking into a session ---------------------------------------------------
$string['join_ok'] = 'Join the session';
$string['join_unknown'] = 'No such session.';
$string['join_cancelled'] = 'This session was cancelled.';
$string['join_ended'] = 'This session is over.';
$string['join_tooearly'] = 'The room opens 15 minutes before the start.';
$string['join_full'] = 'The group is full.';
$string['join_noaccess'] = 'Your access to this course no longer covers this session.';
$string['course_dates'] = 'Next sessions';
$string['session_minutes'] = '{$a} min';

// -- The session lobby --------------------------------------------------------
// Single quotes where a string carries {$a}.
$string['lobby_here'] = 'Session';
$string['lobby_joinnote'] = 'The room opens in a new page. If your connection drops, come back here and try again: your place is kept.';
$string['lobby_noroom'] = 'The room for this session is not ready yet. Your teacher creates it before the start; come back in a few minutes.';
$string['lobby_opensat'] = 'The room opens at {$a}.';
$string['lobby_servertime'] = 'It is {$a} on the platform.';
$string['lobby_backtocourse'] = 'Back to the course';
$string['lobby_seecourse'] = 'See this course';
$string['lobby_opensonday'] = 'The room opens {$a}.';
$string['calendar_description'] = 'A PS Language Hub live session.';
$string['task_close_sessions'] = 'Close sessions that run past their end';
$string['lobby_endswarning'] = 'The session ends in {$a}.';

// -- Session reminders --------------------------------------------------------
// Single quotes where a string carries {$a}. The message states WHEN the class
// starts rather than "in one hour": cron can be late, and a late reminder that
// names an hour is worse than one that names the time.
$string['task_send_reminders'] = 'Send session reminders';
$string['messageprovider:sessionreminder'] = 'Reminder before a live session';
$string['notify_reminder_subject'] = 'Reminder: {$a}';
$string['notify_reminder_body'] = 'Your session "{$a->session}" on the course "{$a->course}" starts {$a->when}.';

// -- The payment seam ---------------------------------------------------------
// Single quotes where a string carries {$a}.
$string['payment_noprovider'] = 'No payment provider is configured on this site. Payments are impossible until the merchant account is open.';
$string['payment_callback_unreadable'] = 'This payment callback cannot be read.';
$string['fakepayments_disabled'] = 'The fake payment provider is not enabled. It requires the line \$CFG->pshub_fake_payments = true; in config.php, and must never reach production.';
$string['fakepayments_waiting'] = 'Payment started. No confirmation will arrive: this script exists to exercise what happens when a payment hangs.';
$string['fakepayments_push'] = 'A confirmation request was sent to {$a}. Enter your PIN on the handset.';
$string['fakepayments_declined'] = 'Payment declined by the provider.';
$string['fakepayments_banner'] = '⚠ This site is using a FAKE payment provider. No payment is real and no money changes hands.';
$string['settings_payments'] = 'Payments';
$string['settings_fakescript'] = 'Fake provider behaviour';
$string['settings_fakescript_desc'] = 'What the fake provider answers. Has no effect until \$CFG->pshub_fake_payments is set in config.php.';
$string['fakescript_pay'] = 'Pay';
$string['fakescript_decline'] = 'Decline';
$string['fakescript_silent'] = 'Never answer';
$string['fakescript_wrongamount'] = 'Pay the wrong amount';
$string['fakescript_wrongcurrency'] = 'Pay in another currency';
$string['cta_access_pending_enrolment'] = "Your access to this course is active, but your enrolment has not been recorded yet. Tell us if this lasts.";

// -- The learner's diary (PSH-S08-07) -----------------------------------------
$string['agenda_title'] = 'My diary';
$string['agenda_lead'] = 'Your next live classes, soonest first.';
$string['agenda_today'] = 'Today';
$string['agenda_tomorrow'] = 'Tomorrow';
$string['agenda_yesterday'] = 'Yesterday';
$string['agenda_join'] = 'Join';
$string['agenda_details'] = 'View';
$string['agenda_empty'] = 'No classes scheduled yet. As soon as you join a course, its dates appear here.';
$string['agenda_noroom'] = 'The room is not ready yet.';
$string['agenda_recent_title'] = 'Over the last few days';
$string['agenda_export_summary'] = 'Add this diary to my phone';
$string['agenda_export_help'] = "Paste this link into your phone's calendar app (Google Calendar, iPhone Calendar): your classes appear there and keep themselves up to date.";
$string['agenda_export_warning'] = '⚠ This link is personal: anyone who has it can read your diary. Do not share it.';
$string['agenda_export_link'] = 'Open my diary link';

// -- Notification preferences (PSH-S08-01) ------------------------------------
$string['notif_title'] = 'My notifications';
$string['notif_lead'] = 'What we tell you, and where it reaches you.';
$string['notif_saved'] = 'Saved.';
$string['notif_save'] = 'Save';
$string['notif_recommended'] = 'recommended';
$string['notif_all_legend'] = 'Turn everything off';
$string['notif_all_off'] = 'Send me nothing at all';
$string['notif_all_help'] = 'With this on, no message goes out - not in the app, not by e-mail. Including the reminders before your classes.';
$string['notif_all_on_notice'] = 'You have turned everything off: the settings below are kept but do not apply.';
$string['notif_none'] = 'No channel is available on this site at the moment.';
$string['notif_email_goes_to'] = 'E-mail goes to {$a}.';
$string['notif_scope'] = 'These settings cover PS Language Hub messages. Notifications from Moodle activities (forums, assignments) are set in Moodle.';
$string['notif_channel_popup'] = 'In the app';
$string['notif_channel_email'] = 'By e-mail';
$string['notif_channel_airnotifier'] = 'On my phone';
$string['notif_channel_sms'] = 'By SMS';
$string['notif_sessionreminder'] = 'Before each live class';
$string['notif_sessionreminder_desc'] = 'One reminder the day before, one an hour before, one ten minutes before. This is what stops you missing a class.';
$string['notif_coursereviewed'] = 'A decision on a course you submitted';
$string['notif_coursereviewed_desc'] = 'When your course is published, or turned down with the reason.';
$string['notif_coursesubmitted'] = 'A course is waiting for your decision';
$string['notif_coursesubmitted_desc'] = 'When a teacher submits a course for review.';

// -- Our own application: device registration (PSH-S08-06) --------------------
$string['device_badtoken'] = 'That device token is empty or too long.';
$string['device_badplatform'] = 'Unknown platform. Expected android, ios or web.';

// -- Site health: can this site send mail at all? -----------------------------
$string['check_mailer_name'] = 'Mail delivery';
$string['check_mailer_ok'] = 'The mail server answers ({$a})';
$string['check_mailer_ok_detail'] = 'A connection to {$a} succeeded. That does not guarantee a message will be accepted, only that something is listening.';
$string['check_mailer_down'] = 'The mail server does not answer ({$a})';
$string['check_mailer_down_detail'] = 'No signup confirmation, no review decision and no session reminder will go out. Actions themselves still succeed: an approved course really is approved, even when the notification fails. Detail: {$a}';
$string['check_mailer_nosmtp'] = 'No SMTP host configured';
$string['check_mailer_nosmtp_detail'] = 'The site uses PHP mail(), which cannot be tested from here. Set smtphosts for this check to mean anything.';
