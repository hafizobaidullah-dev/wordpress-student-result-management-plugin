<?php
/**
 * Plugin Name: Enterprise Student Result Management System (SRMS)
 * Description: High-Performance, Standalone Student Result Management System with custom database tables, real-time AJAX computation, theme bypass, and print-optimized report cards.
 * Version: 1.0.0
 * Author: Sikandar Hayat Baba
 * Text Domain: student-result-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * PHASE A: DATABASE SCHEMA & AUTOMATIC ZERO-TOUCH SETUP
 */
register_activation_hook( __FILE__, 'srms_compile_and_install_db' );

function srms_compile_and_install_db() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $table_students = $wpdb->prefix . 'srms_students';
    $table_subjects = $wpdb->prefix . 'srms_subjects';
    $table_results  = $wpdb->prefix . 'srms_results';
    $table_audit    = $wpdb->prefix . 'srms_audit_logs';

    $sql_students = "CREATE TABLE $table_students (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        roll_number varchar(50) NOT NULL,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        class_id varchar(50) NOT NULL,
        parent_email varchar(100) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY roll_number (roll_number)
    ) $charset_collate;";

    $sql_subjects = "CREATE TABLE $table_subjects (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        subject_name varchar(100) NOT NULL,
        subject_code varchar(50) NOT NULL,
        max_marks int(11) NOT NULL DEFAULT 100,
        pass_marks int(11) NOT NULL DEFAULT 40,
        PRIMARY KEY  (id),
        UNIQUE KEY subject_code (subject_code)
    ) $charset_collate;";

    $sql_results = "CREATE TABLE $table_results (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        student_id bigint(20) NOT NULL,
        subject_id bigint(20) NOT NULL,
        term_name varchar(100) NOT NULL,
        academic_year varchar(20) NOT NULL,
        marks_obtained decimal(5,2) NOT NULL,
        percentage decimal(5,2) NOT NULL,
        grade varchar(10) NOT NULL,
        status varchar(20) NOT NULL,
        recorded_by bigint(20) NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY student_subject_term_year (student_id, subject_id, term_name, academic_year)
    ) $charset_collate;";

    $sql_audit = "CREATE TABLE $table_audit (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        action varchar(255) NOT NULL,
        details text NOT NULL,
        user_id bigint(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_students );
    dbDelta( $sql_subjects );
    dbDelta( $sql_results );
    dbDelta( $sql_audit );

    // Check if pages exist, if not, create
    $page_slug = 'student-dashboard';
    $page_title = 'Student Dashboard';
    
    $query = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_name = %s AND post_type = 'page' LIMIT 1", $page_slug);
    $page_id = $wpdb->get_var($query);

    if ( ! $page_id ) {
        $page_data = array(
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'post_author'    => 1,
            'post_name'      => $page_slug,
            'post_title'     => $page_title,
            'post_content'   => '[srms_frontend_application]',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        );
        wp_insert_post( $page_data );
    }

    // Seed default data
    srms_seed_default_data();
}

function srms_seed_default_data() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'srms_students';
    $table_subjects = $wpdb->prefix . 'srms_subjects';

    // Seed default subjects
    $subjects_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_subjects");
    if ($subjects_count == 0) {
        $default_subjects = [
            ['subject_name' => 'Mathematics', 'subject_code' => 'MATH101', 'max_marks' => 100, 'pass_marks' => 40],
            ['subject_name' => 'Science', 'subject_code' => 'SCI101', 'max_marks' => 100, 'pass_marks' => 40],
            ['subject_name' => 'English Literature', 'subject_code' => 'ENG101', 'max_marks' => 100, 'pass_marks' => 40],
            ['subject_name' => 'History', 'subject_code' => 'HIST101', 'max_marks' => 100, 'pass_marks' => 40],
            ['subject_name' => 'Computer Science', 'subject_code' => 'CS101', 'max_marks' => 100, 'pass_marks' => 40],
        ];
        foreach ($default_subjects as $sub) {
            $wpdb->insert($table_subjects, $sub);
        }
    }

    // Seed default students
    $students_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_students");
    if ($students_count == 0) {
        $default_students = [
            ['roll_number' => 'SRMS-2026-001', 'first_name' => 'Alexander', 'last_name' => 'Pierce', 'class_id' => 'Grade 10-A', 'parent_email' => 'alexander.pierce@example.com'],
            ['roll_number' => 'SRMS-2026-002', 'first_name' => 'Sophia', 'last_name' => 'Loren', 'class_id' => 'Grade 10-A', 'parent_email' => 'sophia.loren@example.com'],
            ['roll_number' => 'SRMS-2026-003', 'first_name' => 'Ethan', 'last_name' => 'Hunt', 'class_id' => 'Grade 10-B', 'parent_email' => 'ethan.hunt@example.com'],
            ['roll_number' => 'SRMS-2026-004', 'first_name' => 'Emma', 'last_name' => 'Watson', 'class_id' => 'Grade 11-A', 'parent_email' => 'emma.watson@example.com'],
            ['roll_number' => 'SRMS-2026-005', 'first_name' => 'Liam', 'last_name' => 'Neeson', 'class_id' => 'Grade 12-B', 'parent_email' => 'liam.neeson@example.com'],
        ];
        foreach ($default_students as $stud) {
            $wpdb->insert($table_students, $stud);
        }
    }
}

/**
 * PHASE B: BACKEND API & COMPUTATION LOGIC
 */

// Grading Engine
function srms_calculate_grade($percentage) {
    if ($percentage >= 90) {
        return ['grade' => 'A+', 'gpa' => '4.0', 'status' => 'Pass'];
    } elseif ($percentage >= 80) {
        return ['grade' => 'A', 'gpa' => '3.7', 'status' => 'Pass'];
    } elseif ($percentage >= 70) {
        return ['grade' => 'B', 'gpa' => '3.0', 'status' => 'Pass'];
    } elseif ($percentage >= 60) {
        return ['grade' => 'C', 'gpa' => '2.0', 'status' => 'Pass'];
    } elseif ($percentage >= 40) {
        return ['grade' => 'D', 'gpa' => '1.0', 'status' => 'Pass'];
    } else {
        return ['grade' => 'F', 'gpa' => '0.0', 'status' => 'Fail'];
    }
}

// Audit trail Logger
function srms_log_audit($action, $details) {
    global $wpdb;
    $table = $wpdb->prefix . 'srms_audit_logs';
    $user_id = get_current_user_id();
    $wpdb->insert($table, [
        'action' => sanitize_text_field($action),
        'details' => sanitize_text_field($details),
        'user_id' => $user_id,
        'created_at' => current_time('mysql')
    ]);
}

// Enforce AJAX security and capability check for admins
function srms_verify_ajax_request() {
    check_ajax_referer('srms_secure_vault', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized capability. Log in with admin permissions.']);
    }
}

// Register AJAX hooks
$ajax_actions = [
    'srms_get_students',
    'srms_save_student',
    'srms_delete_student',
    'srms_get_subjects',
    'srms_save_subject',
    'srms_delete_subject',
    'srms_get_student_marks',
    'srms_save_marks',
    'srms_get_dashboard_stats',
    'srms_get_audit_logs',
];

foreach ($ajax_actions as $action) {
    add_action("wp_ajax_$action", "srms_ajax_handler_$action");
}

// Search result actions (accessible by guest and logged-in users)
add_action("wp_ajax_srms_search_results", "srms_ajax_handler_srms_search_results");
add_action("wp_ajax_nopriv_srms_search_results", "srms_ajax_handler_srms_search_results");

// Handler: Get Students
function srms_ajax_handler_srms_get_students() {
    srms_verify_ajax_request();
    global $wpdb;
    $students = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}srms_students ORDER BY id DESC", ARRAY_A);
    wp_send_json_success(['students' => $students]);
}

// Handler: Save Student
function srms_ajax_handler_srms_save_student() {
    srms_verify_ajax_request();
    global $wpdb;

    $student_id   = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $roll_number  = sanitize_text_field($_POST['roll_number']);
    $first_name   = sanitize_text_field($_POST['first_name']);
    $last_name    = sanitize_text_field($_POST['last_name']);
    $class_id     = sanitize_text_field($_POST['class_id']);
    $parent_email = sanitize_email($_POST['parent_email']);

    if (empty($roll_number) || empty($first_name) || empty($last_name) || empty($class_id)) {
        wp_send_json_error(['message' => 'Please fill in all required fields.']);
    }

    $table = $wpdb->prefix . 'srms_students';

    // Verify unique roll number
    if ($student_id) {
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE roll_number = %s AND id != %d", $roll_number, $student_id));
    } else {
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE roll_number = %s", $roll_number));
    }

    if ($existing) {
        wp_send_json_error(['message' => 'Roll number must be unique. This roll number is already assigned.']);
    }

    $data = [
        'roll_number'  => $roll_number,
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'class_id'     => $class_id,
        'parent_email' => $parent_email,
    ];

    if ($student_id) {
        $wpdb->update($table, $data, ['id' => $student_id]);
        srms_log_audit("Updated Student Profile", "Modified student profile: $first_name $last_name (Roll: $roll_number)");
        wp_send_json_success(['message' => 'Student updated successfully.']);
    } else {
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($table, $data);
        srms_log_audit("Created Student Profile", "Added new student: $first_name $last_name (Roll: $roll_number)");
        wp_send_json_success(['message' => 'Student enrolled successfully.']);
    }
}

// Handler: Delete Student
function srms_ajax_handler_srms_delete_student() {
    srms_verify_ajax_request();
    global $wpdb;

    $student_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$student_id) {
        wp_send_json_error(['message' => 'Invalid student ID.']);
    }

    $table_students = $wpdb->prefix . 'srms_students';
    $table_results  = $wpdb->prefix . 'srms_results';

    $student = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_students WHERE id = %d", $student_id));
    if (!$student) {
        wp_send_json_error(['message' => 'Student not found.']);
    }

    $wpdb->delete($table_students, ['id' => $student_id]);
    $wpdb->delete($table_results, ['student_id' => $student_id]);

    srms_log_audit("Deleted Student Profile", "Deleted student {$student->first_name} {$student->last_name} (Roll: {$student->roll_number}) and all academic results.");
    wp_send_json_success(['message' => 'Student profile and associated results deleted.']);
}

// Handler: Get Subjects
function srms_ajax_handler_srms_get_subjects() {
    srms_verify_ajax_request();
    global $wpdb;
    $subjects = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}srms_subjects ORDER BY subject_name ASC", ARRAY_A);
    wp_send_json_success(['subjects' => $subjects]);
}

// Handler: Save Subject
function srms_ajax_handler_srms_save_subject() {
    srms_verify_ajax_request();
    global $wpdb;

    $subject_id   = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $subject_name = sanitize_text_field($_POST['subject_name']);
    $subject_code = sanitize_text_field($_POST['subject_code']);
    $max_marks    = intval($_POST['max_marks']);
    $pass_marks   = intval($_POST['pass_marks']);

    if (empty($subject_name) || empty($subject_code) || $max_marks <= 0 || $pass_marks <= 0) {
        wp_send_json_error(['message' => 'Please fill in all subject details. Marks must be greater than 0.']);
    }

    $table = $wpdb->prefix . 'srms_subjects';

    // Verify unique subject code
    if ($subject_id) {
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE subject_code = %s AND id != %d", $subject_code, $subject_id));
    } else {
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE subject_code = %s", $subject_code));
    }

    if ($existing) {
        wp_send_json_error(['message' => 'Subject code must be unique. This code already exists.']);
    }

    $data = [
        'subject_name' => $subject_name,
        'subject_code' => $subject_code,
        'max_marks'    => $max_marks,
        'pass_marks'   => $pass_marks,
    ];

    if ($subject_id) {
        $wpdb->update($table, $data, ['id' => $subject_id]);
        srms_log_audit("Updated Subject Registry", "Modified subject: $subject_name ($subject_code)");
        wp_send_json_success(['message' => 'Subject updated successfully.']);
    } else {
        $wpdb->insert($table, $data);
        srms_log_audit("Created Subject Registry", "Registered new subject: $subject_name ($subject_code)");
        wp_send_json_success(['message' => 'Subject added successfully.']);
    }
}

// Handler: Delete Subject
function srms_ajax_handler_srms_delete_subject() {
    srms_verify_ajax_request();
    global $wpdb;

    $subject_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$subject_id) {
        wp_send_json_error(['message' => 'Invalid subject ID.']);
    }

    $table_subjects = $wpdb->prefix . 'srms_subjects';
    $table_results  = $wpdb->prefix . 'srms_results';

    $subject = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_subjects WHERE id = %d", $subject_id));
    if (!$subject) {
        wp_send_json_error(['message' => 'Subject not found.']);
    }

    $wpdb->delete($table_subjects, ['id' => $subject_id]);
    $wpdb->delete($table_results, ['subject_id' => $subject_id]);

    srms_log_audit("Deleted Subject Registry", "Deleted subject {$subject->subject_name} ($subject_code) and associated marks database records.");
    wp_send_json_success(['message' => 'Subject and all its registered marks deleted.']);
}

// Handler: Get Student Marks
function srms_ajax_handler_srms_get_student_marks() {
    srms_verify_ajax_request();
    global $wpdb;

    $student_id    = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $term_name     = sanitize_text_field($_POST['term_name']);
    $academic_year = sanitize_text_field($_POST['academic_year']);

    if (!$student_id || empty($term_name) || empty($academic_year)) {
        wp_send_json_error(['message' => 'Missing student, term, or academic year selector.']);
    }

    $table_subjects = $wpdb->prefix . 'srms_subjects';
    $table_results  = $wpdb->prefix . 'srms_results';

    $subjects = $wpdb->get_results("SELECT * FROM $table_subjects ORDER BY subject_name ASC", ARRAY_A);
    $results  = $wpdb->get_results($wpdb->prepare(
        "SELECT subject_id, marks_obtained FROM $table_results WHERE student_id = %d AND term_name = %s AND academic_year = %s",
        $student_id, $term_name, $academic_year
    ), ARRAY_A);

    $marks_map = [];
    foreach ($results as $res) {
        $marks_map[$res['subject_id']] = floatval($res['marks_obtained']);
    }

    $list = [];
    foreach ($subjects as $sub) {
        $list[] = [
            'subject_id'     => $sub['id'],
            'subject_name'   => $sub['subject_name'],
            'subject_code'   => $sub['subject_code'],
            'max_marks'      => $sub['max_marks'],
            'pass_marks'     => $sub['pass_marks'],
            'marks_obtained' => isset($marks_map[$sub['id']]) ? $marks_map[$sub['id']] : ''
        ];
    }

    wp_send_json_success(['subjects' => $list]);
}

// Handler: Save Marks
function srms_ajax_handler_srms_save_marks() {
    srms_verify_ajax_request();
    global $wpdb;

    $student_id    = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $term_name     = sanitize_text_field($_POST['term_name']);
    $academic_year = sanitize_text_field($_POST['academic_year']);
    $marks_data    = isset($_POST['marks']) ? $_POST['marks'] : []; // [sub_id => val, ...]

    if (!$student_id || empty($term_name) || empty($academic_year) || empty($marks_data)) {
        wp_send_json_error(['message' => 'Invalid parameters. Please complete student and terms details.']);
    }

    $table_students = $wpdb->prefix . 'srms_students';
    $table_subjects = $wpdb->prefix . 'srms_subjects';
    $table_results  = $wpdb->prefix . 'srms_results';

    $student = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_students WHERE id = %d", $student_id));
    if (!$student) {
        wp_send_json_error(['message' => 'Student record not found.']);
    }

    $recorded_by = get_current_user_id();
    $saved_count = 0;

    foreach ($marks_data as $sub_id => $val) {
        $sub_id = intval($sub_id);
        if ($val === '') {
            // Delete mark if field is emptied out
            $wpdb->delete($table_results, [
                'student_id'    => $student_id,
                'subject_id'    => $sub_id,
                'term_name'     => $term_name,
                'academic_year' => $academic_year
            ]);
            continue;
        }

        $marks_obtained = floatval($val);
        $subject = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_subjects WHERE id = %d", $sub_id));
        if (!$subject) continue;

        if ($marks_obtained < 0 || $marks_obtained > $subject->max_marks) {
            wp_send_json_error(['message' => "Marks for {$subject->subject_name} must be between 0 and {$subject->max_marks}."]);
        }

        $percentage = ($marks_obtained / $subject->max_marks) * 100;
        $grade_info = srms_calculate_grade($percentage);

        // Override Pass/Fail status if below the subject-specific minimum passing marks
        if ($marks_obtained < $subject->pass_marks) {
            $grade_info['grade']  = 'F';
            $grade_info['status'] = 'Fail';
        }

        $result_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_results WHERE student_id = %d AND subject_id = %d AND term_name = %s AND academic_year = %s",
            $student_id, $sub_id, $term_name, $academic_year
        ));

        $data = [
            'student_id'     => $student_id,
            'subject_id'     => $sub_id,
            'term_name'      => $term_name,
            'academic_year'  => $academic_year,
            'marks_obtained' => $marks_obtained,
            'percentage'     => $percentage,
            'grade'          => $grade_info['grade'],
            'status'         => $grade_info['status'],
            'recorded_by'    => $recorded_by,
            'updated_at'     => current_time('mysql')
        ];

        if ($result_id) {
            $wpdb->update($table_results, $data, ['id' => $result_id]);
        } else {
            $wpdb->insert($table_results, $data);
        }
        $saved_count++;
    }

    srms_log_audit("Saved Academic Marks", "Recorded result marks for student: {$student->first_name} {$student->last_name} (Roll: {$student->roll_number}) | Term: $term_name ($academic_year)");
    wp_send_json_success(['message' => "Academic marks successfully saved for $saved_count subjects."]);
}

// Handler: Get Dashboard Stats
function srms_ajax_handler_srms_get_dashboard_stats() {
    srms_verify_ajax_request();
    global $wpdb;

    $table_students = $wpdb->prefix . 'srms_students';
    $table_subjects = $wpdb->prefix . 'srms_subjects';
    $table_results  = $wpdb->prefix . 'srms_results';
    $table_audit    = $wpdb->prefix . 'srms_audit_logs';

    $total_students = $wpdb->get_var("SELECT COUNT(*) FROM $table_students");
    $total_subjects = $wpdb->get_var("SELECT COUNT(*) FROM $table_subjects");

    // Pass rate of results
    $total_results_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_results");
    $passed_results_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_results WHERE status = 'Pass'");
    $overall_pass_rate = ($total_results_count > 0) ? ($passed_results_count / $total_results_count) * 100 : 0;

    // Highest scoring academic record
    $highest_score = $wpdb->get_row("
        SELECT r.percentage, r.grade, s.first_name, s.last_name, s.roll_number, sub.subject_name
        FROM $table_results r
        JOIN $table_students s ON r.student_id = s.id
        JOIN $table_subjects sub ON r.subject_id = sub.id
        ORDER BY r.percentage DESC LIMIT 1
    ", ARRAY_A);

    // Recent Audit activities
    $audit_logs = $wpdb->get_results("
        SELECT a.*, u.display_name 
        FROM $table_audit a
        LEFT JOIN {$wpdb->prefix}users u ON a.user_id = u.ID
        ORDER BY a.id DESC LIMIT 5
    ", ARRAY_A);

    // Class average performance
    $class_performance = $wpdb->get_results("
        SELECT s.class_id, AVG(r.percentage) as avg_pct, COUNT(DISTINCT s.id) as student_count
        FROM $table_results r
        JOIN $table_students s ON r.student_id = s.id
        GROUP BY s.class_id
        ORDER BY avg_pct DESC
    ", ARRAY_A);

    wp_send_json_success([
        'total_students'    => intval($total_students),
        'total_subjects'    => intval($total_subjects),
        'overall_pass_rate' => round($overall_pass_rate, 2),
        'highest_score'     => $highest_score,
        'audit_logs'        => $audit_logs,
        'class_performance' => $class_performance
    ]);
}

// Handler: Get Audit Logs (full list)
function srms_ajax_handler_srms_get_audit_logs() {
    srms_verify_ajax_request();
    global $wpdb;
    $table_audit = $wpdb->prefix . 'srms_audit_logs';

    $logs = $wpdb->get_results("
        SELECT a.*, u.display_name 
        FROM $table_audit a
        LEFT JOIN {$wpdb->prefix}users u ON a.user_id = u.ID
        ORDER BY a.id DESC LIMIT 100
    ", ARRAY_A);

    wp_send_json_success(['logs' => $logs]);
}

// Handler: Search Results
function srms_ajax_handler_srms_search_results() {
    check_ajax_referer('srms_secure_vault', 'nonce');
    global $wpdb;

    $roll_number   = sanitize_text_field($_POST['roll_number']);
    $term_name     = sanitize_text_field($_POST['term_name']);
    $academic_year = sanitize_text_field($_POST['academic_year']);

    if (empty($roll_number)) {
        wp_send_json_error(['message' => 'Student Roll Number is required for looking up results.']);
    }

    $table_students = $wpdb->prefix . 'srms_students';
    $table_subjects = $wpdb->prefix . 'srms_subjects';
    $table_results  = $wpdb->prefix . 'srms_results';

    // Find student profile details
    $student = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_students WHERE roll_number = %s", $roll_number), ARRAY_A);
    if (!$student) {
        wp_send_json_error(['message' => 'No student found with the given Roll Number. Please verify credentials.']);
    }

    // Build matching results query
    $query = "SELECT r.*, s.subject_name, s.subject_code, s.max_marks, s.pass_marks 
              FROM $table_results r 
              JOIN $table_subjects s ON r.subject_id = s.id 
              WHERE r.student_id = %d";
    $params = [$student['id']];

    if (!empty($term_name)) {
        $query .= " AND r.term_name = %s";
        $params[] = $term_name;
    }
    if (!empty($academic_year)) {
        $query .= " AND r.academic_year = %s";
        $params[] = $academic_year;
    }

    $query .= " ORDER BY s.subject_name ASC";
    $results = $wpdb->get_results($wpdb->prepare($query, ...$params), ARRAY_A);

    if (empty($results)) {
        wp_send_json_error([
            'student' => $student,
            'message' => 'No academic results recorded for this search parameters.'
        ]);
    }

    // Calculate report statistics
    $total_max = 0;
    $total_obtained = 0;
    $passed_subjects = 0;
    $failed_subjects = 0;
    $total_gpa_points = 0;
    $total_subjects = count($results);

    $gpa_map = ['A+' => 4.0, 'A' => 3.7, 'B' => 3.0, 'C' => 2.0, 'D' => 1.0, 'F' => 0.0];

    foreach ($results as &$res) {
        $total_max += $res['max_marks'];
        $total_obtained += $res['marks_obtained'];
        if ($res['status'] === 'Pass') {
            $passed_subjects++;
        } else {
            $failed_subjects++;
        }
        $gpa_points = isset($gpa_map[$res['grade']]) ? $gpa_map[$res['grade']] : 0.0;
        $res['gpa_points'] = $gpa_points;
        $total_gpa_points += $gpa_points;
    }

    $overall_percentage = ($total_max > 0) ? ($total_obtained / $total_max) * 100 : 0;
    $overall_gpa = ($total_subjects > 0) ? ($total_gpa_points / $total_subjects) : 0;
    $final_status = ($failed_subjects > 0) ? 'Fail' : 'Pass';

    // Overall grade translation
    $overall_grade_info = srms_calculate_grade($overall_percentage);
    
    // Fetch examiner details
    $recorder_id = $results[0]['recorded_by'];
    $updated_at  = $results[0]['updated_at'];
    $recorder_user = get_userdata($recorder_id);
    $recorder_name = $recorder_user ? $recorder_user->display_name : 'System Controller';

    wp_send_json_success([
        'student' => $student,
        'results' => $results,
        'summary' => [
            'total_max'          => $total_max,
            'total_obtained'     => $total_obtained,
            'overall_percentage' => round($overall_percentage, 2),
            'overall_gpa'        => round($overall_gpa, 2),
            'overall_grade'      => $overall_grade_info['grade'],
            'final_status'       => $final_status,
            'recorded_by'        => $recorder_name,
            'updated_at'         => mysql2date('M d, Y h:i A', $updated_at),
            'term_name'          => $results[0]['term_name'],
            'academic_year'      => $results[0]['academic_year']
        ]
    ]);
}

/**
 * PHASE C: STANDALONE FRONTEND APP ENGINE (THE THEME BYPASS)
 */
add_action( 'template_redirect', 'srms_standalone_template_redirect' );

function srms_standalone_template_redirect() {
    if ( is_page( 'student-dashboard' ) || get_query_var('pagename') === 'student-dashboard' ) {
        // Clear all active output buffering
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }
        ob_start();
        
        srms_render_standalone_app();
        exit();
    }
}

// Add simple shortcode fallback (if needed elsewhere)
add_shortcode( 'srms_frontend_application', 'srms_frontend_application_shortcode' );
function srms_frontend_application_shortcode() {
    ob_start();
    ?>
    <div class="srms-shortcode-fallback" style="padding: 20px; text-align: center; border: 1px solid #ddd; border-radius: 8px; font-family: sans-serif;">
        <h3>Student Result Management Portal</h3>
        <p>This system operates inside a standalone environment optimized for SaaS metrics and page-layout controls.</p>
        <a href="<?php echo esc_url( home_url( '/student-dashboard' ) ); ?>" class="button" style="background:#6366f1; color:#fff; padding:10px 20px; border-radius:5px; text-decoration:none;">Go to Standalone Portal</a>
    </div>
    <?php
    return ob_get_clean();
}

// Add admin menu redirect
add_action( 'admin_menu', 'srms_add_admin_menu' );
function srms_add_admin_menu() {
    add_menu_page(
        'SRMS Portal',
        'SRMS Portal',
        'edit_posts',
        'srms-portal-redirect',
        'srms_render_admin_redirect_page',
        'dashicons-education',
        6
    );
}

function srms_render_admin_redirect_page() {
    $dashboard_url = home_url( '/student-dashboard' );
    ?>
    <div class="wrap">
        <h1>Enterprise Student Result Management System (SRMS)</h1>
        <p>Designed and Developed by <strong>Sikandar Hayat Baba</strong></p>
        <hr>
        <div style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 600px; margin-top: 20px;">
            <h2>Launch Management Dashboard</h2>
            <p>The SRMS operates as a standalone workspace layout entirely separate from standard theme containers.</p>
            <p>Admin and editor roles can record student grades, register subjects, and analyze school results.</p>
            <a href="<?php echo esc_url( $dashboard_url ); ?>" target="_blank" class="button button-primary button-large" style="background: #6366f1; border-color: #4f46e5; font-size: 16px; padding: 8px 24px; height: auto; border-radius: 6px;">Launch SRMS Dashboard</a>
        </div>
    </div>
    <?php
}

// Register scripts dependencies
add_action( 'wp_enqueue_scripts', 'srms_enqueue_scripts' );
function srms_enqueue_scripts() {
    wp_enqueue_script( 'jquery' );
}

/**
 * PHASE D: UI/UX EXCELLENCE (CUSTOM APP SHELL)
 */
function srms_render_standalone_app() {
    $is_admin = current_user_can('edit_posts');
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Enterprise Student Result Management System (SRMS)</title>
        
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <?php wp_head(); ?>
        
        <style>
            :root {
                --sidebar-bg: #0f172a;
                --sidebar-hover: #1e293b;
                --sidebar-active: #312e81;
                --primary: #6366f1;
                --primary-hover: #4f46e5;
                --workspace-bg: #f8fafc;
                --text-main: #1e293b;
                --text-muted: #64748b;
                --success: #10b981;
                --danger: #ef4444;
                --card-glass: rgba(255, 255, 255, 0.85);
                --card-border: rgba(255, 255, 255, 0.4);
                --font-sans: 'Plus Jakarta Sans', 'Outfit', system-ui, sans-serif;
            }

            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            body {
                font-family: var(--font-sans);
                background-color: var(--workspace-bg);
                color: var(--text-main);
                overflow-x: hidden;
                -webkit-font-smoothing: antialiased;
            }

            /* Container layout */
            .srms-app-container {
                display: flex;
                min-height: 100vh;
                width: 100vw;
            }

            /* Sidebar */
            .srms-sidebar {
                width: 280px;
                background-color: var(--sidebar-bg);
                color: #fff;
                display: flex;
                flex-direction: column;
                flex-shrink: 0;
                border-right: 1px solid rgba(255, 255, 255, 0.08);
                padding: 24px;
                position: relative;
            }

            .srms-brand {
                display: flex;
                align-items: center;
                gap: 12px;
                padding-bottom: 24px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                margin-bottom: 24px;
            }

            .srms-brand svg {
                width: 32px;
                height: 32px;
                color: var(--primary);
            }

            .srms-brand-details h2 {
                font-size: 18px;
                font-weight: 700;
                line-height: 1.2;
                letter-spacing: -0.02em;
                color: #fff;
            }

            .srms-brand-details span {
                font-size: 11px;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .srms-nav {
                display: flex;
                flex-direction: column;
                gap: 8px;
                flex-grow: 1;
            }

            .srms-nav-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                color: #94a3b8;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                border-radius: 8px;
                transition: all 0.2s ease;
            }

            .srms-nav-item:hover {
                background-color: var(--sidebar-hover);
                color: #fff;
            }

            .srms-nav-item.active {
                background-color: var(--sidebar-active);
                color: #fff;
                box-shadow: 0 4px 12px rgba(49, 46, 129, 0.3);
            }

            .srms-nav-item .icon {
                font-size: 16px;
            }

            .srms-sidebar-footer {
                margin-top: auto;
                padding-top: 24px;
                border-top: 1px solid rgba(255, 255, 255, 0.08);
            }

            .srms-user-badge {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 8px;
                background: rgba(255, 255, 255, 0.05);
                border-radius: 8px;
                margin-bottom: 16px;
            }

            .srms-user-badge .avatar {
                font-size: 20px;
                background: var(--primary);
                width: 36px;
                height: 36px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .srms-user-badge .user-info {
                display: flex;
                flex-direction: column;
            }

            .srms-user-badge .user-info .name {
                font-size: 13px;
                font-weight: 600;
                color: #fff;
            }

            .srms-user-badge .user-info .logout-link {
                font-size: 11px;
                color: var(--danger);
                text-decoration: none;
            }

            .srms-login-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                padding: 12px;
                background-color: var(--primary);
                color: #fff;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                border-radius: 8px;
                transition: background-color 0.2s;
                margin-bottom: 16px;
            }

            .srms-login-btn:hover {
                background-color: var(--primary-hover);
            }

            .srms-branding {
                font-size: 11px;
                color: var(--text-muted);
                text-align: center;
                line-height: 1.5;
            }

            .srms-branding strong {
                color: #cbd5e1;
            }

            /* Main Workspace */
            .srms-workspace {
                flex-grow: 1;
                display: flex;
                flex-direction: column;
                overflow-y: auto;
                height: 100vh;
            }

            .srms-workspace-header {
                background-color: #fff;
                border-bottom: 1px solid #e2e8f0;
                padding: 24px 40px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                flex-shrink: 0;
            }

            .srms-header-title h1 {
                font-size: 24px;
                font-weight: 700;
                color: #0f172a;
                letter-spacing: -0.02em;
            }

            .srms-header-title p {
                font-size: 14px;
                color: var(--text-muted);
                margin-top: 4px;
            }

            .srms-date {
                font-size: 14px;
                font-weight: 500;
                color: var(--text-muted);
                background: #f1f5f9;
                padding: 8px 16px;
                border-radius: 30px;
            }

            /* Content Area */
            .srms-content-area {
                padding: 40px;
                flex-grow: 1;
            }

            .srms-view {
                display: none;
            }

            .srms-view.active {
                display: block;
                animation: fadeIn 0.3s ease;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(8px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Glassmorphic Cards */
            .srms-card {
                background: var(--card-glass);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border: 1px solid var(--card-border);
                border-radius: 16px;
                padding: 24px;
                box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.04);
                margin-bottom: 30px;
            }

            .srms-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .srms-card-title {
                font-size: 18px;
                font-weight: 700;
                color: #0f172a;
            }

            /* Metrics grid */
            .srms-metrics-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                gap: 24px;
                margin-bottom: 30px;
            }

            .srms-metric-card {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 20px;
                display: flex;
                align-items: center;
                gap: 16px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            }

            .srms-metric-icon {
                font-size: 24px;
                width: 48px;
                height: 48px;
                background-color: #f1f5f9;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .srms-metric-details {
                display: flex;
                flex-direction: column;
            }

            .srms-metric-label {
                font-size: 12px;
                color: var(--text-muted);
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .srms-metric-value {
                font-size: 24px;
                font-weight: 700;
                color: #0f172a;
                margin-top: 4px;
            }

            /* Secondary panels split */
            .srms-split-panels {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }

            /* Responsive tables */
            .srms-table-responsive {
                overflow-x: auto;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                background: #fff;
            }

            .srms-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
                font-size: 14px;
            }

            .srms-table th {
                background-color: #f8fafc;
                padding: 16px;
                font-weight: 600;
                color: var(--text-muted);
                border-bottom: 1px solid #e2e8f0;
            }

            .srms-table td {
                padding: 16px;
                border-bottom: 1px solid #e2e8f0;
                color: var(--text-main);
                vertical-align: middle;
            }

            .srms-table tr:last-child td {
                border-bottom: none;
            }

            .srms-table tr {
                transition: background-color 0.2s;
            }

            .srms-table tr:hover {
                background-color: #f8fafc;
            }

            /* Badges */
            .srms-badge {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 30px;
                font-size: 12px;
                font-weight: 600;
                line-height: 1;
            }

            .srms-badge-success {
                background-color: #d1fae5;
                color: #065f46;
            }

            .srms-badge-danger {
                background-color: #fee2e2;
                color: #991b1b;
            }

            .srms-badge-info {
                background-color: #e0e7ff;
                color: #3730a3;
            }

            /* Buttons */
            .srms-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 18px;
                font-size: 14px;
                font-weight: 600;
                border-radius: 8px;
                border: 1px solid transparent;
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
            }

            .srms-btn-primary {
                background-color: var(--primary);
                color: #fff;
            }

            .srms-btn-primary:hover {
                background-color: var(--primary-hover);
            }

            .srms-btn-secondary {
                background-color: #e2e8f0;
                color: var(--text-main);
            }

            .srms-btn-secondary:hover {
                background-color: #cbd5e1;
            }

            .srms-btn-danger {
                background-color: var(--danger);
                color: #fff;
            }

            .srms-btn-danger:hover {
                background-color: #dc2626;
            }

            .srms-btn-sm {
                padding: 6px 12px;
                font-size: 12px;
                border-radius: 6px;
            }

            /* Inputs & Forms */
            .srms-form-group {
                margin-bottom: 20px;
            }

            .srms-form-group label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                color: #475569;
                margin-bottom: 8px;
            }

            .srms-form-control {
                width: 100%;
                padding: 10px 14px;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                font-family: var(--font-sans);
                font-size: 14px;
                background-color: #fff;
                color: var(--text-main);
                transition: all 0.2s;
            }

            .srms-form-control:focus {
                outline: none;
                border-color: var(--primary);
                box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
            }

            .srms-form-control:disabled {
                background-color: #f1f5f9;
                cursor: not-allowed;
            }

            /* Marks Entry Grid elements */
            .srms-marks-grid-table input {
                width: 80px;
                text-align: center;
                padding: 6px;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
            }

            .srms-marks-grid-table input:focus {
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
                outline: none;
            }

            /* Modal */
            .srms-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(15, 23, 42, 0.4);
                backdrop-filter: blur(8px);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                animation: modalFadeIn 0.2s ease;
            }

            .srms-modal {
                background: #fff;
                border-radius: 16px;
                width: 500px;
                max-width: 90%;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
                border: 1px solid #e2e8f0;
            }

            .srms-modal-header {
                padding: 20px 24px;
                border-bottom: 1px solid #e2e8f0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .srms-modal-header h3 {
                font-size: 18px;
                font-weight: 700;
                color: #0f172a;
            }

            .srms-modal-close {
                font-size: 24px;
                color: var(--text-muted);
                cursor: pointer;
                border: none;
                background: none;
            }

            .srms-modal-body {
                padding: 24px;
            }

            .srms-modal-footer {
                padding: 16px 24px;
                border-top: 1px solid #e2e8f0;
                display: flex;
                justify-content: flex-end;
                gap: 12px;
                background-color: #f8fafc;
                border-bottom-left-radius: 16px;
                border-bottom-right-radius: 16px;
            }

            @keyframes modalFadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }

            /* Toast Notification */
            .srms-toast-container {
                position: fixed;
                bottom: 24px;
                right: 24px;
                z-index: 1050;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .srms-toast {
                background: #fff;
                border-left: 4px solid var(--primary);
                box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
                border-radius: 6px;
                padding: 16px;
                width: 320px;
                font-size: 14px;
                font-weight: 500;
                display: flex;
                align-items: center;
                justify-content: space-between;
                transform: translateX(120%);
                transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            }

            .srms-toast.show {
                transform: translateX(0);
            }

            .srms-toast-success {
                border-left-color: var(--success);
            }

            .srms-toast-error {
                border-left-color: var(--danger);
            }

            /* Printable Report Card Design */
            .srms-report-card-container {
                display: none;
                margin-top: 20px;
            }

            .srms-report-card {
                background: #fff;
                border: 1px solid #cbd5e1;
                border-radius: 12px;
                padding: 40px;
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
                max-width: 800px;
                margin: 0 auto;
                position: relative;
            }

            .srms-report-header {
                text-align: center;
                border-bottom: 2px solid #0f172a;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }

            .srms-report-header h2 {
                font-size: 24px;
                font-weight: 800;
                color: #0f172a;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .srms-report-header p {
                font-size: 12px;
                color: var(--text-muted);
                margin-top: 4px;
                font-weight: 600;
                letter-spacing: 0.1em;
                text-transform: uppercase;
            }

            .srms-report-meta {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 30px;
                font-size: 14px;
            }

            .srms-report-meta div p {
                margin-bottom: 6px;
            }

            .srms-report-meta strong {
                color: #0f172a;
            }

            .srms-report-summary {
                margin-top: 30px;
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                margin-bottom: 40px;
            }

            .srms-summary-box {
                border: 1px solid #e2e8f0;
                padding: 16px;
                border-radius: 8px;
                text-align: center;
                background-color: #f8fafc;
            }

            .srms-summary-box span {
                font-size: 11px;
                color: var(--text-muted);
                text-transform: uppercase;
                font-weight: 600;
            }

            .srms-summary-box h4 {
                font-size: 20px;
                font-weight: 700;
                color: #0f172a;
                margin-top: 4px;
            }

            .srms-report-signatures {
                display: flex;
                justify-content: space-between;
                margin-top: 60px;
                border-top: 1px solid #e2e8f0;
                padding-top: 20px;
            }

            .srms-signature-block {
                text-align: center;
                width: 200px;
            }

            .srms-signature-line {
                border-top: 1px solid #94a3b8;
                margin-bottom: 8px;
            }

            .srms-signature-block span {
                font-size: 12px;
                color: var(--text-muted);
                font-weight: 600;
            }

            .srms-report-footer-branding {
                text-align: center;
                margin-top: 40px;
                font-size: 11px;
                color: var(--text-muted);
                border-top: 1px dashed #cbd5e1;
                padding-top: 15px;
            }

            /* PRINT STYLES */
            @media print {
                body {
                    background: #fff !important;
                    color: #000 !important;
                }
                .srms-sidebar, 
                .srms-workspace-header, 
                .srms-search-controls, 
                .srms-btn,
                .no-print {
                    display: none !important;
                }
                .srms-app-container {
                    display: block !important;
                }
                .srms-workspace {
                    height: auto !important;
                    overflow: visible !important;
                }
                .srms-content-area {
                    padding: 0 !important;
                }
                .srms-report-card-container {
                    display: block !important;
                }
                .srms-report-card {
                    border: none !important;
                    padding: 0 !important;
                    box-shadow: none !important;
                    max-width: 100% !important;
                }
                .srms-summary-box {
                    border: 1px solid #000 !important;
                    background: transparent !important;
                }
                .srms-table th {
                    background-color: #f1f5f9 !important;
                    color: #000 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .srms-badge-success {
                    background-color: transparent !important;
                    color: #000 !important;
                    border: 1px solid #000 !important;
                }
                .srms-badge-danger {
                    background-color: transparent !important;
                    color: #000 !important;
                    border: 1px dashed #000 !important;
                }
            }
        </style>
    </head>
    <body>
        <div class="srms-app-container">
            <!-- Sidebar -->
            <aside class="srms-sidebar">
                <div class="srms-brand">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <div class="srms-brand-details">
                        <h2>SRMS Portal</h2>
                        <span>Enterprise System</span>
                    </div>
                </div>

                <nav class="srms-nav">
                    <?php if ( $is_admin ) : ?>
                        <a href="#dashboard" class="srms-nav-item active" data-view="dashboard">
                            <span class="icon">📊</span> Dashboard Hub
                        </a>
                        <a href="#students" class="srms-nav-item" data-view="students">
                            <span class="icon">👥</span> Student Roster
                        </a>
                        <a href="#subjects" class="srms-nav-item" data-view="subjects">
                            <span class="icon">📚</span> Subject Registry
                        </a>
                        <a href="#add-marks" class="srms-nav-item" data-view="add-marks">
                            <span class="icon">✏️</span> Record Marks
                        </a>
                        <a href="#search" class="srms-nav-item" data-view="search">
                            <span class="icon">🔍</span> Search Reports
                        </a>
                        <a href="#audit-logs" class="srms-nav-item" data-view="audit-logs">
                            <span class="icon">📜</span> Audit Logs
                        </a>
                    <?php else : ?>
                        <a href="#search" class="srms-nav-item active" data-view="search">
                            <span class="icon">🔍</span> Search Portal
                        </a>
                    <?php endif; ?>
                </nav>

                <div class="srms-sidebar-footer">
                    <?php if ( is_user_logged_in() ) : ?>
                        <div class="srms-user-badge">
                            <span class="avatar">👤</span>
                            <div class="user-info">
                                <span class="name"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
                                <a href="<?php echo esc_url( wp_logout_url( home_url('/student-dashboard') ) ); ?>" class="logout-link">Log out</a>
                            </div>
                        </div>
                    <?php else : ?>
                        <a href="<?php echo esc_url( wp_login_url( home_url('/student-dashboard') ) ); ?>" class="srms-login-btn">
                            <span>🔑</span> Staff Login
                        </a>
                    <?php endif; ?>
                    <div class="srms-branding">
                        Designed & Developed by<br>
                        <strong>Sikandar Hayat Baba</strong>
                    </div>
                </div>
            </aside>

            <!-- Main Work Area -->
            <main class="srms-workspace">
                <header class="srms-workspace-header no-print">
                    <div class="srms-header-title">
                        <h1 id="view-title">Dashboard Hub</h1>
                        <p id="view-subtitle">Real-time metrics and school averages</p>
                    </div>
                    <div class="srms-header-actions">
                        <span class="srms-date"><?php echo date('F d, Y'); ?></span>
                    </div>
                </header>

                <div class="srms-content-area">
                    
                    <!-- TOASTS -->
                    <div class="srms-toast-container"></div>

                    <!-- VIEW: DASHBOARD (ADMIN ONLY) -->
                    <?php if ( $is_admin ) : ?>
                    <div id="view-dashboard" class="srms-view active">
                        <div class="srms-metrics-grid">
                            <div class="srms-metric-card">
                                <div class="srms-metric-icon">👥</div>
                                <div class="srms-metric-details">
                                    <span class="srms-metric-label">Students Enrolled</span>
                                    <span class="srms-metric-value" id="stat-total-students">-</span>
                                </div>
                            </div>
                            <div class="srms-metric-card">
                                <div class="srms-metric-icon">📚</div>
                                <div class="srms-metric-details">
                                    <span class="srms-metric-label">Subject Profiles</span>
                                    <span class="srms-metric-value" id="stat-total-subjects">-</span>
                                </div>
                            </div>
                            <div class="srms-metric-card">
                                <div class="srms-metric-icon">📈</div>
                                <div class="srms-metric-details">
                                    <span class="srms-metric-label">Overall Pass Rate</span>
                                    <span class="srms-metric-value" id="stat-pass-rate">-</span>
                                </div>
                            </div>
                            <div class="srms-metric-card">
                                <div class="srms-metric-icon">🏆</div>
                                <div class="srms-metric-details">
                                    <span class="srms-metric-label">Highest Score</span>
                                    <span class="srms-metric-value" id="stat-highest-pct">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="srms-split-panels">
                            <!-- Class Performance -->
                            <div class="srms-card">
                                <div class="srms-card-header">
                                    <h3 class="srms-card-title">Class Performance Summary</h3>
                                </div>
                                <div class="srms-table-responsive">
                                    <table class="srms-table" id="dashboard-class-performance">
                                        <thead>
                                            <tr>
                                                <th>Class / Grade</th>
                                                <th>Students Count</th>
                                                <th>Average Mark Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td colspan="3" style="text-align:center;">Analyzing performance logs...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Audit logs short -->
                            <div class="srms-card">
                                <div class="srms-card-header">
                                    <h3 class="srms-card-title">Recent Modifications Audit</h3>
                                </div>
                                <div class="srms-table-responsive">
                                    <table class="srms-table" id="dashboard-recent-audits">
                                        <thead>
                                            <tr>
                                                <th>Timestamp</th>
                                                <th>Staff Member</th>
                                                <th>Activity Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td colspan="3" style="text-align:center;">Retrieving audit trail logs...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- VIEW: STUDENTS (ADMIN ONLY) -->
                    <?php if ( $is_admin ) : ?>
                    <div id="view-students" class="srms-view">
                        <div class="srms-card">
                            <div class="srms-card-header">
                                <h3 class="srms-card-title">Student Registry Database</h3>
                                <button class="srms-btn srms-btn-primary" id="btn-add-student">+ Enroll Student</button>
                            </div>
                            <div class="srms-table-responsive">
                                <table class="srms-table" id="table-students-list">
                                    <thead>
                                        <tr>
                                            <th>Roll Number</th>
                                            <th>First Name</th>
                                            <th>Last Name</th>
                                            <th>Class / Batch</th>
                                            <th>Parent Contact</th>
                                            <th>Enrollment Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Loaded dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- VIEW: SUBJECTS (ADMIN ONLY) -->
                    <?php if ( $is_admin ) : ?>
                    <div id="view-subjects" class="srms-view">
                        <div class="srms-card">
                            <div class="srms-card-header">
                                <h3 class="srms-card-title">Registered Subjects Directory</h3>
                                <button class="srms-btn srms-btn-primary" id="btn-add-subject">+ Add Subject</button>
                            </div>
                            <div class="srms-table-responsive">
                                <table class="srms-table" id="table-subjects-list">
                                    <thead>
                                        <tr>
                                            <th>Subject Code</th>
                                            <th>Subject Title</th>
                                            <th>Max Marks</th>
                                            <th>Passing Threshold</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Loaded dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- VIEW: RECORD MARKS (ADMIN ONLY) -->
                    <?php if ( $is_admin ) : ?>
                    <div id="view-add-marks" class="srms-view">
                        <div class="srms-card">
                            <div class="srms-card-header">
                                <h3 class="srms-card-title">Marks Registration Engine</h3>
                            </div>
                            <form id="form-marks-selection">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: end;">
                                    <div class="srms-form-group" style="margin-bottom:0;">
                                        <label>Select Student</label>
                                        <select class="srms-form-control" id="marks-select-student" required>
                                            <option value="">-- Choose Student --</option>
                                        </select>
                                    </div>
                                    <div class="srms-form-group" style="margin-bottom:0;">
                                        <label>Academic Year</label>
                                        <select class="srms-form-control" id="marks-academic-year" required>
                                            <option value="2025-2026">2025-2026</option>
                                            <option value="2026-2027">2026-2027</option>
                                            <option value="2027-2028">2027-2028</option>
                                        </select>
                                    </div>
                                    <div class="srms-form-group" style="margin-bottom:0;">
                                        <label>Term / Exam Period</label>
                                        <select class="srms-form-control" id="marks-term" required>
                                            <option value="First Term">First Term</option>
                                            <option value="Mid Term">Mid Term</option>
                                            <option value="Final Term">Final Term</option>
                                        </select>
                                    </div>
                                    <div>
                                        <button type="submit" class="srms-btn srms-btn-primary" style="width:100%;">Load Grading Grid</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Marks grid table loaded dynamically -->
                        <div class="srms-card" id="marks-entry-container" style="display:none;">
                            <div class="srms-card-header">
                                <div>
                                    <h3 class="srms-card-title">Grading Score Sheet</h3>
                                    <p style="font-size: 13px; color: var(--text-muted); margin-top:4px;" id="marks-student-info-subtitle"></p>
                                </div>
                            </div>
                            <form id="form-save-marks">
                                <div class="srms-table-responsive">
                                    <table class="srms-table srms-marks-grid-table" id="table-marks-entry-grid">
                                        <thead>
                                            <tr>
                                                <th>Subject Name</th>
                                                <th>Subject Code</th>
                                                <th>Max Marks</th>
                                                <th>Pass Marks</th>
                                                <th>Marks Obtained</th>
                                                <th>Subject GPA</th>
                                                <th>Subject Grade</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Dynamically generated rows -->
                                        </tbody>
                                        <tfoot>
                                            <tr style="background:#f8fafc; font-weight:700;">
                                                <td colspan="4">Overall Aggregates & Transcript Summary</td>
                                                <td style="text-align:center;" id="entry-summary-total">-</td>
                                                <td style="text-align:center;" id="entry-summary-gpa">-</td>
                                                <td style="text-align:center;" id="entry-summary-grade">-</td>
                                                <td style="text-align:center;"><span class="srms-badge" id="entry-summary-status">-</span></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:12px;">
                                    <button type="button" class="srms-btn srms-btn-secondary" id="btn-cancel-marks">Clear Grid</button>
                                    <button type="submit" class="srms-btn srms-btn-primary">Save Marks Securely</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- VIEW: SEARCH PORTAL -->
                    <div id="view-search" class="srms-view <?php echo !$is_admin ? 'active' : ''; ?>">
                        <div class="srms-card srms-search-controls no-print">
                            <div class="srms-card-header">
                                <h3 class="srms-card-title">Academic Result Search Portal</h3>
                            </div>
                            <form id="form-search-results">
                                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 20px; align-items: end;">
                                    <div class="srms-form-group" style="margin-bottom:0;">
                                        <label>Student Roll Number</label>
                                        <input type="text" class="srms-form-control" id="search-roll" placeholder="e.g. SRMS-2026-001" required>
                                    </div>
                                    <div class="srms-form-group" style="margin-bottom:0;">
                                        <label>Academic Year</label>
                                        <select class="srms-form-control" id="search-year">
                                            <option value="">-- All Years --</option>
                                            <option value="2025-2026">2025-2026</option>
                                            <option value="2026-2027">2026-2027</option>
                                            <option value="2027-2028">2027-2028</option>
                                        </select>
                                    </div>
                                    <div class="srms-form-group" style="margin-bottom:0;">
                                        <label>Term / Exam</label>
                                        <select class="srms-form-control" id="search-term">
                                            <option value="">-- All Terms --</option>
                                            <option value="First Term">First Term</option>
                                            <option value="Mid Term">Mid Term</option>
                                            <option value="Final Term">Final Term</option>
                                        </select>
                                    </div>
                                    <div>
                                        <button type="submit" class="srms-btn srms-btn-primary" style="width:120px;">Search</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Search error card -->
                        <div class="srms-card no-print" id="search-error-card" style="display:none; border-left: 4px solid var(--danger);">
                            <h4 id="search-error-title" style="color:var(--danger); font-weight:700;">Lookup failed</h4>
                            <p id="search-error-message" style="margin-top:8px; font-size:14px;"></p>
                        </div>

                        <!-- Printable Report Card Container -->
                        <div class="srms-report-card-container" id="report-card-result">
                            <div style="text-align: right; margin-bottom: 20px;" class="no-print">
                                <button class="srms-btn srms-btn-secondary" id="btn-print-report">
                                    <span>🖨️</span> Print Report Card
                                </button>
                            </div>
                            <div class="srms-report-card">
                                <div class="srms-report-header">
                                    <h2>Official Academic Transcript</h2>
                                    <p>Enterprise Student Result Management System (SRMS)</p>
                                </div>

                                <div class="srms-report-meta">
                                    <div>
                                        <p>Student Name: <strong id="report-student-name">-</strong></p>
                                        <p>Roll Number: <strong id="report-roll-number">-</strong></p>
                                        <p>Class / Grade: <strong id="report-class">-</strong></p>
                                    </div>
                                    <div style="text-align: right;">
                                        <p>Academic Year: <strong id="report-year">-</strong></p>
                                        <p>Assessment Period: <strong id="report-term">-</strong></p>
                                        <p>Date Generated: <strong id="report-date">-</strong></p>
                                    </div>
                                </div>

                                <table class="srms-table" id="report-table-marks" style="margin-bottom:30px;">
                                    <thead>
                                        <tr>
                                            <th>Subject Title</th>
                                            <th>Subject Code</th>
                                            <th>Max Marks</th>
                                            <th>Passing Marks</th>
                                            <th>Obtained Marks</th>
                                            <th>GPA</th>
                                            <th>Grade</th>
                                            <th>Result Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Injected -->
                                    </tbody>
                                </table>

                                <div class="srms-report-summary">
                                    <div class="srms-summary-box">
                                        <span>Total Marks</span>
                                        <h4 id="summary-total-marks">-</h4>
                                    </div>
                                    <div class="srms-summary-box">
                                        <span>Overall Percentage</span>
                                        <h4 id="summary-percentage">-</h4>
                                    </div>
                                    <div class="srms-summary-box">
                                        <span>Cumulative GPA</span>
                                        <h4 id="summary-gpa">-</h4>
                                    </div>
                                    <div class="srms-summary-box">
                                        <span>Final Grade</span>
                                        <h4 id="summary-grade">-</h4>
                                    </div>
                                </div>

                                <div class="srms-report-signatures">
                                    <div class="srms-signature-block">
                                        <div class="srms-signature-line"></div>
                                        <span>Student Counselor</span>
                                    </div>
                                    <div class="srms-signature-block">
                                        <div class="srms-signature-line"></div>
                                        <span id="report-recorded-by">Examinations Controller</span>
                                    </div>
                                </div>

                                <div class="srms-report-footer-branding">
                                    Designed & Developed by <strong>Sikandar Hayat Baba</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- VIEW: AUDIT LOGS (ADMIN ONLY) -->
                    <?php if ( $is_admin ) : ?>
                    <div id="view-audit-logs" class="srms-view">
                        <div class="srms-card">
                            <div class="srms-card-header">
                                <h3 class="srms-card-title">Full Administrative Audit Trail</h3>
                            </div>
                            <div class="srms-table-responsive">
                                <table class="srms-table" id="table-audit-logs">
                                    <thead>
                                        <tr>
                                            <th>Log ID</th>
                                            <th>Timestamp</th>
                                            <th>Action Event</th>
                                            <th>Activity Details</th>
                                            <th>User / Operator</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Loaded dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </main>
        </div>

        <!-- MODAL: ADD/EDIT STUDENT -->
        <?php if ( $is_admin ) : ?>
        <div class="srms-modal-overlay" id="modal-student">
            <div class="srms-modal">
                <div class="srms-modal-header">
                    <h3 id="modal-student-title">Enroll Student</h3>
                    <button class="srms-modal-close" id="modal-student-close">&times;</button>
                </div>
                <form id="form-student">
                    <input type="hidden" id="student-id" value="">
                    <div class="srms-modal-body">
                        <div class="srms-form-group">
                            <label>Student Roll Number *</label>
                            <input type="text" class="srms-form-control" id="student-roll" placeholder="e.g. SRMS-2026-001" required>
                        </div>
                        <div class="srms-form-group">
                            <label>First Name *</label>
                            <input type="text" class="srms-form-control" id="student-first-name" placeholder="e.g. Alexander" required>
                        </div>
                        <div class="srms-form-group">
                            <label>Last Name *</label>
                            <input type="text" class="srms-form-control" id="student-last-name" placeholder="e.g. Pierce" required>
                        </div>
                        <div class="srms-form-group">
                            <label>Class / Batch Identifier *</label>
                            <input type="text" class="srms-form-control" id="student-class" placeholder="e.g. Grade 10-A" required>
                        </div>
                        <div class="srms-form-group">
                            <label>Parent Email Address</label>
                            <input type="email" class="srms-form-control" id="student-email" placeholder="e.g. parent@example.com">
                        </div>
                    </div>
                    <div class="srms-modal-footer">
                        <button type="button" class="srms-btn srms-btn-secondary" id="modal-student-cancel">Cancel</button>
                        <button type="submit" class="srms-btn srms-btn-primary">Save Profile</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: ADD/EDIT SUBJECT -->
        <div class="srms-modal-overlay" id="modal-subject">
            <div class="srms-modal">
                <div class="srms-modal-header">
                    <h3 id="modal-subject-title">Add Subject Profile</h3>
                    <button class="srms-modal-close" id="modal-subject-close">&times;</button>
                </div>
                <form id="form-subject">
                    <input type="hidden" id="subject-id" value="">
                    <div class="srms-modal-body">
                        <div class="srms-form-group">
                            <label>Subject Code *</label>
                            <input type="text" class="srms-form-control" id="subject-code" placeholder="e.g. MATH101" required>
                        </div>
                        <div class="srms-form-group">
                            <label>Subject Title *</label>
                            <input type="text" class="srms-form-control" id="subject-name" placeholder="e.g. Mathematics" required>
                        </div>
                        <div class="srms-form-group">
                            <label>Maximum Score Possible *</label>
                            <input type="number" class="srms-form-control" id="subject-max-marks" value="100" min="1" required>
                        </div>
                        <div class="srms-form-group">
                            <label>Passing Threshold Score *</label>
                            <input type="number" class="srms-form-control" id="subject-pass-marks" value="40" min="1" required>
                        </div>
                    </div>
                    <div class="srms-modal-footer">
                        <button type="button" class="srms-btn srms-btn-secondary" id="modal-subject-cancel">Cancel</button>
                        <button type="submit" class="srms-btn srms-btn-primary">Register Subject</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <script>
            jQuery(document).ready(function($) {
                const ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
                const nonce = '<?php echo esc_attr( wp_create_nonce( "srms_secure_vault" ) ); ?>';
                const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;

                // --- TOAST NOTIFICATIONS ---
                function showToast(message, type = 'success') {
                    const id = 'toast-' + Math.random().toString(36).substr(2, 9);
                    const toastHtml = `
                        <div class="srms-toast srms-toast-${type}" id="${id}">
                            <span>${message}</span>
                            <button style="background:none;border:none;cursor:pointer;font-size:16px;" onclick="jQuery('#${id}').removeClass('show').delay(300).remove()">&times;</button>
                        </div>
                    `;
                    $('.srms-toast-container').append(toastHtml);
                    
                    // Trigger reflow/animation
                    setTimeout(() => {
                        $(`#${id}`).addClass('show');
                    }, 50);

                    // Auto dismiss
                    setTimeout(() => {
                        $(`#${id}`).removeClass('show');
                        setTimeout(() => {
                            $(`#${id}`).remove();
                        }, 300);
                    }, 4000);
                }

                // --- ROTATOR ENGINE ---
                function router() {
                    const defaultHash = isAdmin ? '#dashboard' : '#search';
                    const hash = window.location.hash || defaultHash;
                    
                    // Restrict guests from accessing admin views
                    if (!isAdmin && hash !== '#search') {
                        window.location.hash = '#search';
                        return;
                    }

                    $('.srms-view').removeClass('active');
                    $('.srms-nav-item').removeClass('active');

                    const activeNavItem = $(`.srms-nav-item[href="${hash}"]`);
                    activeNavItem.addClass('active');

                    const viewId = 'view-' + hash.replace('#', '');
                    const targetView = $('#' + viewId);

                    if (targetView.length) {
                        targetView.addClass('active');
                        
                        // Set headers
                        let viewTitle = activeNavItem.text().trim();
                        let viewSubtitle = "Result Management workspace";

                        if (hash === '#dashboard') {
                            viewTitle = "Dashboard Hub";
                            viewSubtitle = "Real-time metrics and school averages";
                            loadDashboardStats();
                        } else if (hash === '#students') {
                            viewTitle = "Student Roster";
                            viewSubtitle = "Manage enrolled student profiles and contact information";
                            loadStudents();
                        } else if (hash === '#subjects') {
                            viewTitle = "Subject Registry";
                            viewSubtitle = "Course configuration database";
                            loadSubjects();
                        } else if (hash === '#add-marks') {
                            viewTitle = "Marks Entry Engine";
                            viewSubtitle = "Record and compute grading marks sheets";
                            initMarksEntry();
                        } else if (hash === '#search') {
                            viewTitle = "Academic Result Portal";
                            viewSubtitle = "Verify student marks sheets and print transcripts";
                        } else if (hash === '#audit-logs') {
                            viewTitle = "Audit Trail Log";
                            viewSubtitle = "Data logs of all recorded modifications";
                            loadAuditLogs();
                        }

                        $('#view-title').text(viewTitle);
                        $('#view-subtitle').text(viewSubtitle);
                    }
                }

                $(window).on('hashchange', router);
                router(); // initial run

                // Navigation clicks
                $('.srms-nav-item').on('click', function(e) {
                    if ($(this).attr('href').startsWith('#')) {
                        // Let hash router handle it
                    } else {
                        // Standard redirects
                    }
                });

                // --- CALCULATION RULES (JS PORTION) ---
                function calculateSubjectGrade(percentage) {
                    if (percentage >= 90) return { grade: 'A+', gpa: '4.0', status: 'Pass' };
                    if (percentage >= 80) return { grade: 'A', gpa: '3.7', status: 'Pass' };
                    if (percentage >= 70) return { grade: 'B', gpa: '3.0', status: 'Pass' };
                    if (percentage >= 60) return { grade: 'C', gpa: '2.0', status: 'Pass' };
                    if (percentage >= 40) return { grade: 'D', gpa: '1.0', status: 'Pass' };
                    return { grade: 'F', gpa: '0.0', status: 'Fail' };
                }

                // --- MODULE: DASHBOARD STATS ---
                function loadDashboardStats() {
                    if (!isAdmin) return;
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'srms_get_dashboard_stats',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                const data = response.data;
                                $('#stat-total-students').text(data.total_students);
                                $('#stat-total-subjects').text(data.total_subjects);
                                $('#stat-pass-rate').text(data.overall_pass_rate + '%');
                                
                                if (data.highest_score) {
                                    $('#stat-highest-pct').text(data.highest_score.percentage + '% (' + data.highest_score.first_name + ')');
                                } else {
                                    $('#stat-highest-pct').text('N/A');
                                }

                                // Render Class Performance
                                let classHtml = '';
                                if (data.class_performance && data.class_performance.length > 0) {
                                    data.class_performance.forEach(function(row) {
                                        classHtml += `
                                            <tr>
                                                <td><strong>${row.class_id}</strong></td>
                                                <td>${row.student_count} Students</td>
                                                <td>
                                                    <div style="display:flex; align-items:center; gap:10px;">
                                                        <span style="font-weight:600; width:45px;">${parseFloat(row.avg_pct).toFixed(1)}%</span>
                                                        <div style="flex-grow:1; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                                                            <div style="width:${row.avg_pct}%; height:100%; background:var(--primary);"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        `;
                                    });
                                } else {
                                    classHtml = '<tr><td colspan="3" style="text-align:center; color:var(--text-muted);">No records found.</td></tr>';
                                }
                                $('#dashboard-class-performance tbody').html(classHtml);

                                // Render Recent Audit logs
                                let auditHtml = '';
                                if (data.audit_logs && data.audit_logs.length > 0) {
                                    data.audit_logs.forEach(function(row) {
                                        auditHtml += `
                                            <tr>
                                                <td style="font-size:12px; color:var(--text-muted);">${row.created_at}</td>
                                                <td><strong>${row.display_name || 'System'}</strong></td>
                                                <td>${row.action}: <span style="font-size:13px; color:var(--text-muted);">${row.details}</span></td>
                                            </tr>
                                        `;
                                    });
                                } else {
                                    auditHtml = '<tr><td colspan="3" style="text-align:center; color:var(--text-muted);">No modifications logged.</td></tr>';
                                }
                                $('#dashboard-recent-audits tbody').html(auditHtml);
                            }
                        }
                    });
                }

                // --- MODULE: STUDENT ROSTER ---
                function loadStudents() {
                    if (!isAdmin) return;
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'srms_get_students',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                let html = '';
                                response.data.students.forEach(function(s) {
                                    html += `
                                        <tr>
                                            <td><strong>${s.roll_number}</strong></td>
                                            <td>${s.first_name}</td>
                                            <td>${s.last_name}</td>
                                            <td><span class="srms-badge srms-badge-info">${s.class_id}</span></td>
                                            <td><a href="mailto:${s.parent_email}" style="color:var(--primary);text-decoration:none;">${s.parent_email || 'N/A'}</a></td>
                                            <td style="font-size:12px; color:var(--text-muted);">${s.created_at}</td>
                                            <td>
                                                <div style="display:flex; gap:8px;">
                                                    <button class="srms-btn srms-btn-secondary srms-btn-sm btn-edit-student" data-student='${JSON.stringify(s)}'>✏️ Edit</button>
                                                    <button class="srms-btn srms-btn-danger srms-btn-sm btn-delete-student" data-id="${s.id}">🗑️ Delete</button>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                });
                                if (response.data.students.length === 0) {
                                    html = '<tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No student profiles recorded.</td></tr>';
                                }
                                $('#table-students-list tbody').html(html);
                            }
                        }
                    });
                }

                // Modal Student Events
                $('#btn-add-student').click(function() {
                    $('#form-student')[0].reset();
                    $('#student-id').val('');
                    $('#modal-student-title').text('Enroll Student');
                    $('#student-roll').prop('disabled', false);
                    $('#modal-student').css('display', 'flex');
                });

                $('#modal-student-close, #modal-student-cancel').click(function() {
                    $('#modal-student').css('display', 'none');
                });

                $('#form-student').submit(function(e) {
                    e.preventDefault();
                    const payload = {
                        action: 'srms_save_student',
                        nonce: nonce,
                        id: $('#student-id').val(),
                        roll_number: $('#student-roll').val(),
                        first_name: $('#student-first-name').val(),
                        last_name: $('#student-last-name').val(),
                        class_id: $('#student-class').val(),
                        parent_email: $('#student-email').val()
                    };

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: payload,
                        success: function(response) {
                            if (response.success) {
                                showToast(response.data.message);
                                $('#modal-student').css('display', 'none');
                                loadStudents();
                            } else {
                                showToast(response.data.message, 'error');
                            }
                        }
                    });
                });

                $(document).on('click', '.btn-edit-student', function() {
                    const s = $(this).data('student');
                    $('#student-id').val(s.id);
                    $('#student-roll').val(s.roll_number).prop('disabled', true);
                    $('#student-first-name').val(s.first_name);
                    $('#student-last-name').val(s.last_name);
                    $('#student-class').val(s.class_id);
                    $('#student-email').val(s.parent_email);
                    
                    $('#modal-student-title').text('Edit Student Profile');
                    $('#modal-student').css('display', 'flex');
                });

                $(document).on('click', '.btn-delete-student', function() {
                    const id = $(this).data('id');
                    if (confirm('Are you absolutely sure you want to delete this student and all recorded academic marks? This action is permanent!')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'srms_delete_student',
                                nonce: nonce,
                                id: id
                            },
                            success: function(response) {
                                if (response.success) {
                                    showToast(response.data.message);
                                    loadStudents();
                                } else {
                                    showToast(response.data.message, 'error');
                                }
                            }
                        });
                    }
                });


                // --- MODULE: SUBJECT REGISTRY ---
                function loadSubjects() {
                    if (!isAdmin) return;
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'srms_get_subjects',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                let html = '';
                                response.data.subjects.forEach(function(sub) {
                                    html += `
                                        <tr>
                                            <td><strong>${sub.subject_code}</strong></td>
                                            <td>${sub.subject_name}</td>
                                            <td>${sub.max_marks}</td>
                                            <td>${sub.pass_marks}</td>
                                            <td>
                                                <div style="display:flex; gap:8px;">
                                                    <button class="srms-btn srms-btn-secondary srms-btn-sm btn-edit-subject" data-subject='${JSON.stringify(sub)}'>✏️ Edit</button>
                                                    <button class="srms-btn srms-btn-danger srms-btn-sm btn-delete-subject" data-id="${sub.id}">🗑️ Delete</button>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                });
                                if (response.data.subjects.length === 0) {
                                    html = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No courses registered in academic schedule.</td></tr>';
                                }
                                $('#table-subjects-list tbody').html(html);
                            }
                        }
                    });
                }

                // Modal Subject Events
                $('#btn-add-subject').click(function() {
                    $('#form-subject')[0].reset();
                    $('#subject-id').val('');
                    $('#subject-code').prop('disabled', false);
                    $('#modal-subject-title').text('Register Course Subject');
                    $('#modal-subject').css('display', 'flex');
                });

                $('#modal-subject-close, #modal-subject-cancel').click(function() {
                    $('#modal-subject').css('display', 'none');
                });

                $('#form-subject').submit(function(e) {
                    e.preventDefault();
                    const payload = {
                        action: 'srms_save_subject',
                        nonce: nonce,
                        id: $('#subject-id').val(),
                        subject_code: $('#subject-code').val(),
                        subject_name: $('#subject-name').val(),
                        max_marks: $('#subject-max-marks').val(),
                        pass_marks: $('#subject-pass-marks').val()
                    };

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: payload,
                        success: function(response) {
                            if (response.success) {
                                showToast(response.data.message);
                                $('#modal-subject').css('display', 'none');
                                loadSubjects();
                            } else {
                                showToast(response.data.message, 'error');
                            }
                        }
                    });
                });

                $(document).on('click', '.btn-edit-subject', function() {
                    const sub = $(this).data('subject');
                    $('#subject-id').val(sub.id);
                    $('#subject-code').val(sub.subject_code).prop('disabled', true);
                    $('#subject-name').val(sub.subject_name);
                    $('#subject-max-marks').val(sub.max_marks);
                    $('#subject-pass-marks').val(sub.pass_marks);

                    $('#modal-subject-title').text('Edit Subject Profile');
                    $('#modal-subject').css('display', 'flex');
                });

                $(document).on('click', '.btn-delete-subject', function() {
                    const id = $(this).data('id');
                    if (confirm('Are you sure you want to remove this course and delete all recorded grade marks linked to it?')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'srms_delete_subject',
                                nonce: nonce,
                                id: id
                            },
                            success: function(response) {
                                if (response.success) {
                                    showToast(response.data.message);
                                    loadSubjects();
                                } else {
                                    showToast(response.data.message, 'error');
                                }
                            }
                        });
                    }
                });


                // --- MODULE: MARKS ENTRY ENGINE ---
                function initMarksEntry() {
                    if (!isAdmin) return;
                    // Prepopulate students dropdown list
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'srms_get_students',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                let dropdown = '<option value="">-- Choose Student --</option>';
                                response.data.students.forEach(function(s) {
                                    dropdown += `<option value="${s.id}">${s.first_name} ${s.last_name} (Roll: ${s.roll_number})</option>`;
                                });
                                $('#marks-select-student').html(dropdown);
                            }
                        }
                    });
                }

                $('#form-marks-selection').submit(function(e) {
                    e.preventDefault();
                    const studentId = $('#marks-select-student').val();
                    const term = $('#marks-term').val();
                    const year = $('#marks-academic-year').val();

                    if (!studentId) return;

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'srms_get_student_marks',
                            nonce: nonce,
                            student_id: studentId,
                            term_name: term,
                            academic_year: year
                        },
                        success: function(response) {
                            if (response.success) {
                                const selectedName = $('#marks-select-student option:selected').text();
                                $('#marks-student-info-subtitle').html(`Recording results for student <strong>${selectedName}</strong> | <strong>${term} (${year})</strong>`);
                                
                                let rowsHtml = '';
                                response.data.subjects.forEach(function(sub) {
                                    let obtained = sub.marks_obtained;
                                    let percentage = obtained !== '' ? (parseFloat(obtained) / parseFloat(sub.max_marks) * 100) : 0;
                                    let gradeDetails = obtained !== '' ? calculateSubjectGrade(percentage) : { grade: '-', gpa: '-', status: '-' };

                                    // Status Badge styling
                                    let badgeClass = 'srms-badge-info';
                                    if (gradeDetails.status === 'Pass') badgeClass = 'srms-badge-success';
                                    if (gradeDetails.status === 'Fail') badgeClass = 'srms-badge-danger';

                                    rowsHtml += `
                                        <tr class="marks-row" data-sub-id="${sub.subject_id}" data-max="${sub.max_marks}" data-pass="${sub.pass_marks}">
                                            <td><strong>${sub.subject_name}</strong></td>
                                            <td><span class="srms-badge srms-badge-info">${sub.subject_code}</span></td>
                                            <td style="text-align:center;">${sub.max_marks}</td>
                                            <td style="text-align:center;">${sub.pass_marks}</td>
                                            <td style="text-align:center;">
                                                <input type="number" class="marks-input-val srms-form-control" 
                                                       value="${obtained}" 
                                                       max="${sub.max_marks}" min="0" step="0.5" placeholder="-" 
                                                       name="marks[${sub.subject_id}]">
                                            </td>
                                            <td style="text-align:center;" class="cell-gpa">${gradeDetails.gpa}</td>
                                            <td style="text-align:center;" class="cell-grade">${gradeDetails.grade}</td>
                                            <td style="text-align:center;">
                                                <span class="srms-badge ${badgeClass} cell-status">${gradeDetails.status}</span>
                                            </td>
                                        </tr>
                                    `;
                                });

                                if (response.data.subjects.length === 0) {
                                    rowsHtml = '<tr><td colspan="8" style="text-align:center; color:var(--text-muted);">Please create academic subjects first before accessing the Marks Registry Grid.</td></tr>';
                                }

                                $('#table-marks-entry-grid tbody').html(rowsHtml);
                                $('#marks-entry-container').show();
                                updateGridAggregates();
                            }
                        }
                    });
                });

                // Keyup / change auto recalculation in grading sheet
                $(document).on('input', '.marks-input-val', function() {
                    const row = $(this).closest('.marks-row');
                    const val = $(this).val();
                    const maxMarks = parseFloat(row.data('max'));
                    const passMarks = parseFloat(row.data('pass'));

                    const cellGpa = row.find('.cell-gpa');
                    const cellGrade = row.find('.cell-grade');
                    const cellStatus = row.find('.cell-status');

                    if (val === '') {
                        cellGpa.text('-');
                        cellGrade.text('-');
                        cellStatus.text('-').attr('class', 'srms-badge cell-status');
                        updateGridAggregates();
                        return;
                    }

                    const marksObtained = parseFloat(val);
                    if (marksObtained < 0 || marksObtained > maxMarks) {
                        $(this).css('border-color', 'var(--danger)');
                        return;
                    } else {
                        $(this).css('border-color', '');
                    }

                    const percentage = (marksObtained / maxMarks) * 100;
                    let calculated = calculateSubjectGrade(percentage);

                    // Min passing check override
                    if (marksObtained < passMarks) {
                        calculated.grade = 'F';
                        calculated.gpa = '0.0';
                        calculated.status = 'Fail';
                    }

                    cellGpa.text(calculated.gpa);
                    cellGrade.text(calculated.grade);
                    cellStatus.text(calculated.status);

                    cellStatus.attr('class', 'srms-badge cell-status');
                    if (calculated.status === 'Pass') cellStatus.addClass('srms-badge-success');
                    if (calculated.status === 'Fail') cellStatus.addClass('srms-badge-danger');

                    updateGridAggregates();
                });

                function updateGridAggregates() {
                    let totalMax = 0;
                    let totalObtained = 0;
                    let failedCount = 0;
                    let activeSubjects = 0;
                    let sumGpa = 0;

                    $('.marks-row').each(function() {
                        const maxMarks = parseFloat($(this).data('max'));
                        const val = $(this).find('.marks-input-val').val();

                        if (val !== '') {
                            activeSubjects++;
                            const marksObtained = parseFloat(val);
                            totalMax += maxMarks;
                            totalObtained += marksObtained;

                            const passMarks = parseFloat($(this).data('pass'));
                            let percentage = (marksObtained / maxMarks) * 100;
                            let calc = calculateSubjectGrade(percentage);

                            if (marksObtained < passMarks) {
                                failedCount++;
                                sumGpa += 0.0;
                            } else {
                                sumGpa += parseFloat(calc.gpa);
                            }
                        }
                    });

                    if (activeSubjects === 0) {
                        $('#entry-summary-total').text('-');
                        $('#entry-summary-gpa').text('-');
                        $('#entry-summary-grade').text('-');
                        $('#entry-summary-status').text('-').attr('class', 'srms-badge');
                        return;
                    }

                    const overallPercentage = (totalObtained / totalMax) * 100;
                    const overallGpa = sumGpa / activeSubjects;
                    const finalStatus = failedCount > 0 ? 'Fail' : 'Pass';

                    const finalGradeInfo = calculateSubjectGrade(overallPercentage);

                    $('#entry-summary-total').text(`${totalObtained.toFixed(1)} / ${totalMax}`);
                    $('#entry-summary-gpa').text(overallGpa.toFixed(2));
                    $('#entry-summary-grade').text(finalGradeInfo.grade);
                    
                    const statusBadge = $('#entry-summary-status');
                    statusBadge.text(finalStatus).attr('class', 'srms-badge');
                    if (finalStatus === 'Pass') statusBadge.addClass('srms-badge-success');
                    if (finalStatus === 'Fail') statusBadge.addClass('srms-badge-danger');
                }

                $('#btn-cancel-marks').click(function() {
                    $('#marks-entry-container').hide();
                    $('#form-marks-selection')[0].reset();
                });

                $('#form-save-marks').submit(function(e) {
                    e.preventDefault();
                    const studentId = $('#marks-select-student').val();
                    const term = $('#marks-term').val();
                    const year = $('#marks-academic-year').val();

                    // Collect input marks map
                    const marks = {};
                    $('.marks-row').each(function() {
                        const subId = $(this).data('sub-id');
                        const val = $(this).find('.marks-input-val').val();
                        marks[subId] = val;
                    });

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'srms_save_marks',
                            nonce: nonce,
                            student_id: studentId,
                            term_name: term,
                            academic_year: year,
                            marks: marks
                        },
                        success: function(response) {
                            if (response.success) {
                                showToast(response.data.message);
                                $('#marks-entry-container').hide();
                                $('#form-marks-selection')[0].reset();
                            } else {
                                showToast(response.data.message, 'error');
                            }
                        }
                    });
                });


                // --- MODULE: SEARCH & REPORT CARD ---
                $('#form-search-results').submit(function(e) {
                    e.preventDefault();
                    const roll = $('#search-roll').val();
                    const term = $('#search-term').val();
                    const year = $('#search-year').val();

                    $('#search-error-card').hide();
                    $('#report-card-result').hide();

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'srms_search_results',
                            nonce: nonce,
                            roll_number: roll,
                            term_name: term,
                            academic_year: year
                        },
                        success: function(response) {
                            if (response.success) {
                                const data = response.data;
                                const student = data.student;
                                const results = data.results;
                                const summary = data.summary;

                                // Header meta
                                $('#report-student-name').text(student.first_name + ' ' + student.last_name);
                                $('#report-roll-number').text(student.roll_number);
                                $('#report-class').text(student.class_id);
                                $('#report-year').text(summary.academic_year);
                                $('#report-term').text(summary.term_name);
                                $('#report-date').text(summary.updated_at);
                                $('#report-recorded-by').text('Examinations Controller: ' + summary.recorded_by);

                                // Table rows
                                let rowsHtml = '';
                                results.forEach(function(row) {
                                    let badgeClass = 'srms-badge-info';
                                    if (row.status === 'Pass') badgeClass = 'srms-badge-success';
                                    if (row.status === 'Fail') badgeClass = 'srms-badge-danger';

                                    rowsHtml += `
                                        <tr>
                                            <td><strong>${row.subject_name}</strong></td>
                                            <td><span class="srms-badge srms-badge-info">${row.subject_code}</span></td>
                                            <td style="text-align:center;">${row.max_marks}</td>
                                            <td style="text-align:center;">${row.pass_marks}</td>
                                            <td style="text-align:center;"><strong>${row.marks_obtained}</strong></td>
                                            <td style="text-align:center;">${row.gpa_points}</td>
                                            <td style="text-align:center;">${row.grade}</td>
                                            <td style="text-align:center;"><span class="srms-badge ${badgeClass}">${row.status}</span></td>
                                        </tr>
                                    `;
                                });
                                $('#report-table-marks tbody').html(rowsHtml);

                                // Summaries boxes
                                $('#summary-total-marks').text(summary.total_obtained + ' / ' + summary.total_max);
                                $('#summary-percentage').text(summary.overall_percentage + '%');
                                $('#summary-gpa').text(summary.overall_gpa);
                                $('#summary-grade').text(summary.overall_grade);

                                $('#report-card-result').fadeIn();
                            } else {
                                const msg = response.data.message || 'Verification search returned empty records.';
                                $('#search-error-message').text(msg);
                                $('#search-error-card').show();
                            }
                        }
                    });
                });

                $('#btn-print-report').click(function() {
                    window.print();
                });


                // --- MODULE: AUDIT LOG VIEWER ---
                function loadAuditLogs() {
                    if (!isAdmin) return;
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'srms_get_audit_logs',
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                let html = '';
                                response.data.logs.forEach(function(log) {
                                    html += `
                                        <tr>
                                            <td>#${log.id}</td>
                                            <td style="font-size:12px; color:var(--text-muted);">${log.created_at}</td>
                                            <td><span class="srms-badge srms-badge-info">${log.action}</span></td>
                                            <td style="font-size:13px;">${log.details}</td>
                                            <td><strong>${log.display_name || 'System Operator'}</strong></td>
                                        </tr>
                                    `;
                                });
                                if (response.data.logs.length === 0) {
                                    html = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted);">No activity recorded in administrative logs.</td></tr>';
                                }
                                $('#table-audit-logs tbody').html(html);
                            }
                        }
                    });
                }
            });
        </script>
    </body>
    </html>
    <?php
}
