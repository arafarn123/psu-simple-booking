<?php
/**
 * Plugin Name: PSU Simple Booking
 * Description: ระบบการจอง ใช้ภายใน PSU
 * Version: 1.0
 * Author: DIIS PSU
 * Text Domain: psu-simple-booking
 * Domain Path: /languages
 * Requires PHP: 8.0
 * Author URI: https://diis.psu.ac.th/
 */

// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// กำหนดค่าคงที่
define('PSU_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PSU_BOOKING_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PSU_BOOKING_VERSION', '2.0');

/**
 * คลาสหลักของ Plugin
 */
class PSU_Simple_Booking {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // โหลดภาษา
        load_plugin_textdomain('psu-simple-booking', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // สร้างตาราง database
        $this->create_tables();
        
        // เพิ่ม shortcodes
        $this->register_shortcodes();
        
        // เพิ่ม admin menus
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // โหลด CSS และ JS
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_psu_get_service', array($this, 'ajax_get_service'));
        add_action('wp_ajax_nopriv_psu_get_service', array($this, 'ajax_get_service'));
        add_action('wp_ajax_psu_get_timeslots', array($this, 'ajax_get_timeslots'));
        add_action('wp_ajax_nopriv_psu_get_timeslots', array($this, 'ajax_get_timeslots'));
        add_action('wp_ajax_psu_submit_booking', array($this, 'ajax_submit_booking'));
        add_action('wp_ajax_nopriv_psu_submit_booking', array($this, 'ajax_submit_booking'));
        add_action('wp_ajax_psu_get_date_booking_status', array($this, 'ajax_get_date_booking_status'));
        add_action('wp_ajax_nopriv_psu_get_date_booking_status', array($this, 'ajax_get_date_booking_status'));
        add_action('wp_ajax_psu_get_month_booking_status', array($this, 'ajax_get_month_booking_status'));
        add_action('wp_ajax_nopriv_psu_get_month_booking_status', array($this, 'ajax_get_month_booking_status'));
        add_action('wp_ajax_psu_get_user_bookings', array($this, 'ajax_get_user_bookings'));
        add_action('wp_ajax_nopriv_psu_get_user_bookings', array($this, 'ajax_get_user_bookings'));
        add_action('wp_ajax_psu_get_booking_detail', array($this, 'ajax_get_booking_detail'));
        add_action('wp_ajax_nopriv_psu_get_booking_detail', array($this, 'ajax_get_booking_detail'));
        add_action('wp_ajax_psu_get_calendar_bookings', array($this, 'ajax_get_calendar_bookings'));
        add_action('wp_ajax_nopriv_psu_get_calendar_bookings', array($this, 'ajax_get_calendar_bookings'));
        add_action('wp_ajax_psu_get_admin_calendar_bookings', array($this, 'ajax_get_admin_calendar_bookings'));
        add_action('wp_ajax_psu_export_bookings_csv', array($this, 'ajax_export_bookings_csv'));
        add_action('wp_ajax_psu_check_available_timeslots', array($this, 'ajax_check_available_timeslots'));
        
        // Email hooks
        add_action('psu_booking_created', array($this, 'send_booking_notification'), 10, 2);
        add_action('psu_booking_status_changed', array($this, 'send_status_notification'), 10, 3);
    }
    
    /**
     * สร้างตาราง database เมื่อเปิดใช้งาน plugin
     */
    public function activate() {
        $this->create_tables();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * สร้างตาราง database
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // ตาราง Services
        $table_services = $wpdb->prefix . 'psu_services';
        $sql_services = "CREATE TABLE $table_services (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            image_url varchar(500),
            category varchar(100),
            price decimal(10,2) DEFAULT 0.00,
            duration int(11) DEFAULT 60 COMMENT 'duration in minutes',
            available_start_time time DEFAULT '09:00:00',
            available_end_time time DEFAULT '17:00:00',
            break_start_time time DEFAULT '12:00:00',
            break_end_time time DEFAULT '13:00:00',
            working_days varchar(20) DEFAULT '1,2,3,4,5' COMMENT 'comma separated days (0=Sunday)',
            timeslot_type varchar(50) DEFAULT 'hourly' COMMENT 'hourly, morning_afternoon, full_day - can be multiple',
            timeslot_duration int(11) DEFAULT 60 COMMENT 'minutes per slot',
            auto_approve tinyint(1) DEFAULT 0,
            payment_info text,
            manager_name varchar(255),
            manager_user_id int(11),
            status tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name),
            KEY category (category),
            KEY status (status)
        ) $charset_collate;";
        
        // ตาราง Bookings
        $table_bookings = $wpdb->prefix . 'psu_bookings';
        $sql_bookings = "CREATE TABLE $table_bookings (
            id int(11) NOT NULL AUTO_INCREMENT,
            service_id int(11) NOT NULL,
            user_id int(11),
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            customer_phone varchar(20),
            booking_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            total_price decimal(10,2) DEFAULT 0.00,
            status enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
            rejection_reason text,
            additional_info text,
            form_data longtext COMMENT 'JSON format for custom form fields',
            admin_notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY service_id (service_id),
            KEY user_id (user_id),
            KEY booking_date (booking_date),
            KEY status (status),
            KEY customer_email (customer_email),
            FOREIGN KEY (service_id) REFERENCES $table_services(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // ตาราง Settings
        $table_settings = $wpdb->prefix . 'psu_settings';
        $sql_settings = "CREATE TABLE $table_settings (
            id int(11) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            setting_type varchar(20) DEFAULT 'string' COMMENT 'string, json, int, bool',
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        // ตาราง Form Fields (สำหรับ custom form fields)
        $table_form_fields = $wpdb->prefix . 'psu_form_fields';
        $sql_form_fields = "CREATE TABLE $table_form_fields (
            id int(11) NOT NULL AUTO_INCREMENT,
            service_id int(11) DEFAULT NULL COMMENT 'NULL for global fields',
            field_name varchar(100) NOT NULL,
            field_label varchar(255) NOT NULL,
            field_type enum('text','textarea','email','number','tel','select','radio','checkbox','date','time','file') NOT NULL,
            field_options longtext COMMENT 'JSON for select/radio/checkbox options',
            is_required tinyint(1) DEFAULT 0,
            field_order int(11) DEFAULT 0,
            placeholder varchar(255),
            validation_rules longtext COMMENT 'JSON for validation rules',
            status tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY service_id (service_id),
            KEY field_order (field_order),
            KEY status (status),
            FOREIGN KEY (service_id) REFERENCES $table_services(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_services);
        dbDelta($sql_bookings);
        dbDelta($sql_settings);
        dbDelta($sql_form_fields);
        
        // อัพเดทเวอร์ชั่น database
        update_option('psu_booking_db_version', '2.0');
    }
    
    /**
     * ลงทะเบียน Shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('psu_booking_form', array($this, 'booking_form_shortcode'));
        add_shortcode('psu_booking_history', array($this, 'booking_history_shortcode'));
    }
    
    /**
     * เพิ่ม Admin Menu
     */
    public function admin_menu() {
        add_menu_page(
            'PSU Simple Booking',
            'PSU Simple Booking',
            'manage_options',
            'psu-booking',
            array($this, 'admin_dashboard'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'psu-booking',
            'จัดการบริการ',
            'บริการ',
            'manage_options',
            'psu-booking-services',
            array($this, 'admin_services')
        );
        
        add_submenu_page(
            'psu-booking',
            'รายการจอง',
            'รายการจอง',
            'manage_options',
            'psu-booking-bookings',
            array($this, 'admin_bookings')
        );
        
        add_submenu_page(
            'psu-booking',
            'สถิติ',
            'สถิติ',
            'manage_options',
            'psu-booking-stats',
            array($this, 'admin_statistics')
        );
        
        add_submenu_page(
            'psu-booking',
            'การตั้งค่า',
            'การตั้งค่า',
            'manage_options',
            'psu-booking-settings',
            array($this, 'admin_settings')
        );
        
        add_submenu_page(
            'psu-booking',
            'ฟิลด์ฟอร์ม',
            'ปรับแต่งฟอร์ม',
            'manage_options',
            'psu-booking-form-fields',
            array($this, 'admin_form_fields')
        );
    }
    
    /**
     * โหลด CSS และ JS สำหรับ Frontend
     */
    public function enqueue_scripts() {
        wp_enqueue_style('psu-booking-style', PSU_BOOKING_PLUGIN_URL . 'assets/css/frontend.css', array(), PSU_BOOKING_VERSION);
        wp_enqueue_script('psu-booking-script', PSU_BOOKING_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), PSU_BOOKING_VERSION, true);
        
        // โหลด CSS และ JS สำหรับ booking history
        if (is_user_logged_in() && (is_page() || is_single())) {
            global $post;
            if ($post && (has_shortcode($post->post_content, 'psu_booking_history') || strpos($post->post_content, 'psu_booking_history') !== false)) {
                wp_enqueue_style('psu-booking-history-style', PSU_BOOKING_PLUGIN_URL . 'assets/css/booking-history.css', array(), PSU_BOOKING_VERSION);
            }
        }
        
        wp_localize_script('psu-booking-script', 'psu_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('psu_booking_nonce')
        ));
    }
    
    /**
     * โหลด CSS และ JS สำหรับ Admin
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'psu-booking') !== false) {
            // โหลด Media Library
            wp_enqueue_media();
            
            wp_enqueue_style('psu-booking-admin-style', PSU_BOOKING_PLUGIN_URL . 'assets/css/admin.css', array(), PSU_BOOKING_VERSION);
            wp_enqueue_script('psu-booking-admin-script', PSU_BOOKING_PLUGIN_URL . 'assets/js/admin-simple.js', array('jquery'), PSU_BOOKING_VERSION, true);
            
            wp_localize_script('psu-booking-admin-script', 'psu_admin_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('psu_admin_nonce')
            ));
        }
    }
    
    /**
     * Shortcode สำหรับแบบฟอร์มจอง
     */
    public function booking_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'service_id' => '',
            'category' => ''
        ), $atts);
        
        ob_start();
        if (file_exists(PSU_BOOKING_PLUGIN_PATH . 'templates/booking-form.php')) {
            include PSU_BOOKING_PLUGIN_PATH . 'templates/booking-form.php';
        } else {
            echo '<div class="psu-booking-form">กำลังโหลด...</div>';
        }
        return ob_get_clean();
    }
    
    /**
     * Shortcode สำหรับประวัติการจอง
     */
    public function booking_history_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>กรุณาเข้าสู่ระบบเพื่อดูประวัติการจอง</p>';
        }
        
        ob_start();
        if (file_exists(PSU_BOOKING_PLUGIN_PATH . 'templates/booking-history.php')) {
            include PSU_BOOKING_PLUGIN_PATH . 'templates/booking-history.php';
        } else {
            echo '<div class="psu-booking-history">กำลังโหลด...</div>';
        }
        return ob_get_clean();
    }
    
    // Include ไฟล์ที่เหลือ
    public function admin_dashboard() { 
        if (file_exists(PSU_BOOKING_PLUGIN_PATH . 'admin/dashboard.php')) {
            include PSU_BOOKING_PLUGIN_PATH . 'admin/dashboard.php';
        }
    }
    
    public function admin_services() { 
        if (file_exists(PSU_BOOKING_PLUGIN_PATH . 'admin/services.php')) {
            include PSU_BOOKING_PLUGIN_PATH . 'admin/services.php';
        }
    }
    
    public function admin_bookings() { 
        if (file_exists(PSU_BOOKING_PLUGIN_PATH . 'admin/bookings.php')) {
            include PSU_BOOKING_PLUGIN_PATH . 'admin/bookings.php';
        }
    }
    
    public function admin_statistics() { 
        if (file_exists(PSU_BOOKING_PLUGIN_PATH . 'admin/statistics.php')) {
            include PSU_BOOKING_PLUGIN_PATH . 'admin/statistics.php';
        }
    }
    
    public function admin_settings() { 
        if (file_exists(PSU_BOOKING_PLUGIN_PATH . 'admin/settings.php')) {
            include PSU_BOOKING_PLUGIN_PATH . 'admin/settings.php';
        }
    }
    
    public function admin_form_fields() { 
        if (file_exists(PSU_BOOKING_PLUGIN_PATH . 'admin/form-fields.php')) {
            include PSU_BOOKING_PLUGIN_PATH . 'admin/form-fields.php';
        }
    }
    
    /**
     * AJAX: ดึงข้อมูลบริการ
     */
    public function ajax_get_service() {
        check_ajax_referer('psu_booking_nonce', 'nonce');
        
        $service_id = intval($_POST['service_id']);
        
        global $wpdb;
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psu_services WHERE id = %d AND status = 1",
            $service_id
        ));
        
        if ($service) {
            wp_send_json_success($service);
        } else {
            wp_send_json_error(array('message' => 'ไม่พบบริการที่เลือก'));
        }
    }
    
    /**
     * AJAX: ดึง timeslots ที่ว่าง
     */
    public function ajax_get_timeslots() {
        check_ajax_referer('psu_booking_nonce', 'nonce');
        
        $service_id = intval($_POST['service_id']);
        $date = sanitize_text_field($_POST['date']);
        
        $timeslots = $this->get_available_timeslots($service_id, $date);
        
        wp_send_json_success($timeslots);
    }
    
    /**
     * AJAX: ส่งการจอง
     */
    public function ajax_submit_booking() {
        // Debug logging
        error_log('PSU Booking: ajax_submit_booking called');
        error_log('POST data: ' . print_r($_POST, true));
        
        try {
            check_ajax_referer('psu_booking_nonce', 'nonce');
        } catch (Exception $e) {
            error_log('PSU Booking: Nonce verification failed - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'การตรวจสอบความปลอดภัยล้มเหลว'));
            return;
        }
        
        // ประมวลผล timeslots (อาจเป็น JSON string)
        $timeslots = $_POST['timeslots'];
        error_log('PSU Booking: Raw timeslots data: ' . print_r($timeslots, true));
        error_log('PSU Booking: Timeslots data type: ' . gettype($timeslots));
        error_log('PSU Booking: Timeslots string length: ' . strlen($timeslots));
        
        if (is_string($timeslots)) {
            // ทำความสะอาด JSON string
            $timeslots = trim($timeslots);
            $timeslots = stripslashes($timeslots);
            
            // ตรวจสอบ encoding และแปลงเป็น UTF-8
            if (!mb_check_encoding($timeslots, 'UTF-8')) {
                $timeslots = mb_convert_encoding($timeslots, 'UTF-8');
                error_log('PSU Booking: Converted encoding to UTF-8');
            }
            
            // ลบ BOM ถ้ามี
            $timeslots = preg_replace('/^\xEF\xBB\xBF/', '', $timeslots);
            
            // ลบ invisible characters
            $timeslots = preg_replace('/[\x00-\x1F\x7F]/', '', $timeslots);
            
            error_log('PSU Booking: Cleaned JSON string: ' . $timeslots);
            error_log('PSU Booking: Cleaned string length: ' . strlen($timeslots));
            
            // ลองใช้ regular expression เพื่อ validate JSON structure
            if (!preg_match('/^\[.*\]$/', $timeslots)) {
                error_log('PSU Booking: JSON structure validation failed');
                wp_send_json_error(array('message' => 'รูปแบบ JSON ไม่ถูกต้อง'));
                return;
            }
            
            $timeslots = json_decode($timeslots, true);
            $json_error = json_last_error();
            error_log('PSU Booking: JSON decode result: ' . print_r($timeslots, true));
            error_log('PSU Booking: JSON error code: ' . $json_error);
            
            if ($json_error !== JSON_ERROR_NONE) {
                $error_msg = json_last_error_msg();
                error_log('PSU Booking: JSON decode error: ' . $error_msg);
                
                // ลอง fallback parsing
                error_log('PSU Booking: Attempting manual parsing fallback');
                $timeslots = $this->manual_parse_timeslots($_POST['timeslots']);
                
                if ($timeslots === false) {
                    wp_send_json_error(array('message' => 'ไม่สามารถประมวลผลข้อมูลช่วงเวลาได้: ' . $error_msg));
                    return;
                } else {
                    error_log('PSU Booking: Manual parsing succeeded');
                }
            }
        } elseif (is_array($timeslots)) {
            error_log('PSU Booking: Timeslots is already an array');
        } else {
            error_log('PSU Booking: Unexpected timeslots data type: ' . gettype($timeslots));
            wp_send_json_error(array('message' => 'รูปแบบข้อมูลช่วงเวลาไม่ถูกต้อง'));
            return;
        }
        
        // ประมวลผล custom fields
        $custom_fields = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'custom_field_') === 0) {
                $custom_fields[$key] = sanitize_text_field($value);
            }
        }
        
        $data = array(
            'service_id' => intval($_POST['service_id']),
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'booking_date' => sanitize_text_field($_POST['booking_date']),
            'timeslots' => $timeslots,
            'additional_info' => sanitize_textarea_field($_POST['additional_info']),
            'custom_fields' => $custom_fields
        );
        
        // ตรวจสอบโครงสร้างข้อมูล timeslots
        if (!is_array($timeslots) || empty($timeslots)) {
            error_log('PSU Booking: Invalid timeslots - not array or empty');
            wp_send_json_error(array('message' => 'กรุณาเลือกช่วงเวลาสำหรับการจอง'));
            return;
        }
        
        // ตรวจสอบแต่ละ timeslot
        foreach ($timeslots as $index => $slot) {
            if (!is_array($slot)) {
                error_log('PSU Booking: Invalid timeslot at index ' . $index . ' - not array');
                wp_send_json_error(array('message' => 'รูปแบบข้อมูลช่วงเวลาไม่ถูกต้อง'));
                return;
            }
            
            $required_fields = ['start', 'end'];
            foreach ($required_fields as $field) {
                if (!isset($slot[$field]) || empty($slot[$field])) {
                    error_log('PSU Booking: Missing required field in timeslot: ' . $field);
                    wp_send_json_error(array('message' => 'ข้อมูลช่วงเวลาไม่ครบถ้วน'));
                    return;
                }
            }
        }
        
        error_log('PSU Booking: Processed data: ' . print_r($data, true));
        
        $result = $this->create_booking($data);
        
        error_log('PSU Booking: Create booking result: ' . print_r($result, true));
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * สร้างการจอง
     */
    public function create_booking($data) {
        global $wpdb;
        
        error_log('PSU Booking: create_booking called with data: ' . print_r($data, true));
        
        // ตรวจสอบข้อมูล
        if (empty($data['service_id']) || empty($data['customer_name']) || empty($data['customer_email']) || empty($data['booking_date']) || empty($data['timeslots'])) {
            $missing = array();
            if (empty($data['service_id'])) $missing[] = 'service_id';
            if (empty($data['customer_name'])) $missing[] = 'customer_name';
            if (empty($data['customer_email'])) $missing[] = 'customer_email';
            if (empty($data['booking_date'])) $missing[] = 'booking_date';
            if (empty($data['timeslots'])) $missing[] = 'timeslots';
            
            error_log('PSU Booking: Missing required fields: ' . implode(', ', $missing));
            return array('success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน (ขาด: ' . implode(', ', $missing) . ')');
        }
        
        // ดึงข้อมูลบริการ
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psu_services WHERE id = %d AND status = 1",
            $data['service_id']
        ));
        
        error_log('PSU Booking: Service query result: ' . print_r($service, true));
        
        if (!$service) {
            error_log('PSU Booking: Service not found for ID: ' . $data['service_id']);
            return array('success' => false, 'message' => 'ไม่พบบริการที่เลือก');
        }
        
        // สร้างการจองสำหรับแต่ละ timeslot
        $booking_ids = array();
        $total_price = 0;
        
        error_log('PSU Booking: Processing ' . count($data['timeslots']) . ' timeslots');
        
        foreach ($data['timeslots'] as $index => $timeslot) {
            error_log('PSU Booking: Processing timeslot ' . $index . ': ' . print_r($timeslot, true));
            
            $start_time = sanitize_text_field($timeslot['start']);
            $end_time = sanitize_text_field($timeslot['end']);
            
            // ตรวจสอบว่า timeslot ยังว่างอยู่
            $is_available = $this->is_timeslot_available($data['service_id'], $data['booking_date'], $start_time, $end_time);
            error_log('PSU Booking: Timeslot availability check: ' . ($is_available ? 'AVAILABLE' : 'NOT AVAILABLE'));
            
            if (!$is_available) {
                return array('success' => false, 'message' => 'ช่วงเวลา ' . $start_time . '-' . $end_time . ' ไม่ว่างแล้ว');
            }
            
            // คำนวณราคา
            $duration_hours = (strtotime($end_time) - strtotime($start_time)) / 3600;
            $slot_price = $service->price * $duration_hours;
            $total_price += $slot_price;
            
            error_log('PSU Booking: Calculated price for slot: ' . $slot_price . ' (duration: ' . $duration_hours . ' hours)');
            
            // สร้างการจอง
            $booking_data = array(
                'service_id' => $data['service_id'],
                'user_id' => get_current_user_id(),
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'booking_date' => $data['booking_date'],
                'start_time' => $start_time,
                'end_time' => $end_time,
                'total_price' => $slot_price,
                'status' => $service->auto_approve ? 'approved' : 'pending',
                'additional_info' => $data['additional_info'],
                'form_data' => json_encode($data)
            );
            
            error_log('PSU Booking: Inserting booking data: ' . print_r($booking_data, true));
            
            $result = $wpdb->insert($wpdb->prefix . 'psu_bookings', $booking_data);
            
            if ($result) {
                $booking_id = $wpdb->insert_id;
                $booking_ids[] = $booking_id;
                error_log('PSU Booking: Successfully created booking ID: ' . $booking_id);
                
                // ส่ง hook สำหรับการจองใหม่
                do_action('psu_booking_created', $booking_id, $booking_data);
            } else {
                $error = $wpdb->last_error;
                error_log('PSU Booking: Database insert failed: ' . $error);
                return array('success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $error);
            }
        }
        
        if (!empty($booking_ids)) {
            return array(
                'success' => true, 
                'message' => 'จองสำเร็จแล้ว!',
                'booking_ids' => $booking_ids,
                'total_price' => $total_price
            );
        } else {
            return array('success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
        }
    }
    
    /**
     * ตรวจสอบว่า timeslot ว่างหรือไม่
     */
    public function is_timeslot_available($service_id, $date, $start_time, $end_time) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings 
             WHERE service_id = %d 
             AND booking_date = %s 
             AND status != 'rejected'
             AND (
                 (start_time < %s AND end_time > %s) OR
                 (start_time < %s AND end_time > %s) OR
                 (start_time >= %s AND end_time <= %s)
             )",
            $service_id, $date, $end_time, $start_time, $start_time, $start_time, $end_time, $end_time, $start_time, $end_time
        ));
        
        return $count == 0;
    }
    
    /**
     * ดึง timeslots ทั้งหมด (ทั้งว่างและไม่ว่าง)
     */
    public function get_available_timeslots($service_id, $date) {
        global $wpdb;
        
        // ดึงข้อมูลบริการ
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psu_services WHERE id = %d AND status = 1",
            $service_id
        ));
        
        if (!$service) {
            return array();
        }
        
        // ตรวจสอบวันทำการ (0=อาทิตย์, 1=จันทร์, ..., 6=เสาร์)
        $day_of_week = date('w', strtotime($date)); // w = 0(อาทิตย์) ถึง 6(เสาร์)
        $working_days = explode(',', $service->working_days);
        $working_days = array_map('intval', $working_days); // แปลงเป็น integer
        if (!in_array((int)$day_of_week, $working_days)) {
            return array();
        }
        
        // สร้าง timeslots ตามประเภท (รองรับหลายประเภท)
        $all_timeslots = array();
        $types = explode(',', $service->timeslot_type);
        
        foreach ($types as $type) {
            $type = trim($type);
            $category_name = '';
            $slots = array();
            
            switch ($type) {
                case 'hourly':
                    $category_name = 'รายชั่วโมง';
                    $slots = $this->generate_hourly_timeslots($service, $date);
                    break;
                case 'morning_afternoon':
                    $category_name = 'ครึ่งวัน';
                    $slots = $this->generate_morning_afternoon_timeslots($service, $date);
                    break;
                case 'full_day':
                    $category_name = 'เต็มวัน';
                    $slots = $this->generate_full_day_timeslots($service, $date);
                    break;
            }
            
            // เพิ่มข้อมูลสถานะและหมวดหมู่
            foreach ($slots as &$slot) {
                $slot['category'] = $category_name;
                $slot['type'] = $type;
                $slot['available'] = $this->is_timeslot_available($service_id, $date, $slot['start'], $slot['end']);
                $slot['service_name'] = $service->name;
                
                // แสดงราคาถูกต้อง
                if ($slot['price'] == 0) {
                    $slot['price_display'] = 'ไม่มีค่าบริการ';
                } else {
                    $slot['price_display'] = number_format($slot['price'], 0) . ' บาท';
                }
            }
            
            if (!empty($slots)) {
                $all_timeslots[] = array(
                    'category' => $category_name,
                    'type' => $type,
                    'slots' => $slots
                );
            }
        }
        
        return $all_timeslots;
    }
    
    /**
     * สร้าง timeslots แบบรายชั่วโมง
     */
    private function generate_hourly_timeslots($service, $date) {
        $timeslots = array();
        $start = strtotime($date . ' ' . $service->available_start_time);
        $end = strtotime($date . ' ' . $service->available_end_time);
        $break_start = strtotime($date . ' ' . $service->break_start_time);
        $break_end = strtotime($date . ' ' . $service->break_end_time);
        $duration = $service->timeslot_duration * 60; // convert to seconds
        
        $current = $start;
        while ($current + $duration <= $end) {
            $slot_end = $current + $duration;
            
            // ข้ามช่วง break time
            if ($current >= $break_start && $current < $break_end) {
                $current = $break_end;
                continue;
            }
            
            if ($slot_end > $break_start && $slot_end <= $break_end) {
                $current = $break_end;
                continue;
            }
            
            $timeslots[] = array(
                'start' => date('H:i:s', $current),
                'end' => date('H:i:s', $slot_end),
                'display' => date('H:i', $current) . ' - ' . date('H:i', $slot_end),
                'price' => $service->price
            );
            
            $current += $duration;
        }
        
        return $timeslots;
    }
    
    /**
     * สร้าง timeslots แบบช่วงเช้า/บ่าย
     */
    private function generate_morning_afternoon_timeslots($service, $date) {
        $timeslots = array();
        
        // ช่วงเช้า
        $morning_start = $service->available_start_time;
        $morning_end = $service->break_start_time;
        $morning_hours = (strtotime($date . ' ' . $morning_end) - strtotime($date . ' ' . $morning_start)) / 3600;
        
        $timeslots[] = array(
            'start' => $morning_start,
            'end' => $morning_end,
            'display' => 'ช่วงเช้า (' . date('H:i', strtotime($morning_start)) . ' - ' . date('H:i', strtotime($morning_end)) . ')',
            'price' => $service->price * $morning_hours
        );
        
        // ช่วงบ่าย
        $afternoon_start = $service->break_end_time;
        $afternoon_end = $service->available_end_time;
        $afternoon_hours = (strtotime($date . ' ' . $afternoon_end) - strtotime($date . ' ' . $afternoon_start)) / 3600;
        
        $timeslots[] = array(
            'start' => $afternoon_start,
            'end' => $afternoon_end,
            'display' => 'ช่วงบ่าย (' . date('H:i', strtotime($afternoon_start)) . ' - ' . date('H:i', strtotime($afternoon_end)) . ')',
            'price' => $service->price * $afternoon_hours
        );
        
        return $timeslots;
    }
    
    /**
     * สร้าง timeslots แบบทั้งวัน
     */
    private function generate_full_day_timeslots($service, $date) {
        $start_time = $service->available_start_time;
        $end_time = $service->available_end_time;
        $break_start = strtotime($date . ' ' . $service->break_start_time);
        $break_end = strtotime($date . ' ' . $service->break_end_time);
        
        // คำนวณจำนวนชั่วโมงทั้งหมด (ลบช่วงพัก)
        $total_minutes = (strtotime($date . ' ' . $end_time) - strtotime($date . ' ' . $start_time)) / 60;
        $break_minutes = ($break_end - $break_start) / 60;
        $work_hours = ($total_minutes - $break_minutes) / 60;
        
        return array(
            array(
                'start' => $start_time,
                'end' => $end_time,
                'display' => 'ทั้งวัน (' . date('H:i', strtotime($start_time)) . ' - ' . date('H:i', strtotime($end_time)) . ') ยกเว้นช่วงพัก',
                'price' => $service->price * $work_hours
            )
        );
    }
    
    /**
     * ส่ง email แจ้งเตือนการจอง
     */
    public function send_booking_notification($booking_id, $booking_data) {
        global $wpdb;
        
        // ดึงการตั้งค่า email
        $email_settings = $this->get_setting('email_notifications');
        if (!$email_settings) return;
        
        $email_settings = json_decode($email_settings, true);
        
        // ดึงข้อมูลบริการ
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psu_services WHERE id = %d",
            $booking_data['service_id']
        ));
        
        // ตัวแปรสำหรับแทนที่ใน email template
        $placeholders = array(
            '{customer_name}' => $booking_data['customer_name'],
            '{service_name}' => $service->name,
            '{booking_date}' => date('d/m/Y', strtotime($booking_data['booking_date'])),
            '{start_time}' => date('H:i', strtotime($booking_data['start_time'])),
            '{end_time}' => date('H:i', strtotime($booking_data['end_time'])),
            '{status}' => $booking_data['status'] == 'pending' ? 'รออนุมัติ' : 'อนุมัติแล้ว',
            '{total_price}' => number_format($booking_data['total_price'], 2)
        );
        
        // ส่งอีเมลให้ลูกค้า
        if ($email_settings['user_booking_created']['enabled']) {
            $subject = str_replace(array_keys($placeholders), array_values($placeholders), $email_settings['user_booking_created']['subject']);
            $message = str_replace(array_keys($placeholders), array_values($placeholders), $email_settings['user_booking_created']['message']);
            
            wp_mail($booking_data['customer_email'], $subject, $message);
        }
        
        // ส่งอีเมลให้แอดมิน
        if ($email_settings['admin_new_booking']['enabled']) {
            $admin_email = get_option('admin_email');
            $subject = str_replace(array_keys($placeholders), array_values($placeholders), $email_settings['admin_new_booking']['subject']);
            $message = str_replace(array_keys($placeholders), array_values($placeholders), $email_settings['admin_new_booking']['message']);
            
            wp_mail($admin_email, $subject, $message);
        }
    }
    
    /**
     * ส่ง email เมื่อสถานะการจองเปลี่ยน
     */
    public function send_status_notification($booking_id, $old_status, $new_status) {
        // TODO: Implement status change notification
    }
    
    /**
     * AJAX: ดึงสถานะการจองของวันที่เฉพาะ
     */
    public function ajax_get_date_booking_status() {
        check_ajax_referer('psu_booking_nonce', 'nonce');
        
        $service_id = intval($_POST['service_id']);
        $date = sanitize_text_field($_POST['date']);
        
        $status = $this->get_date_booking_status($service_id, $date);
        
        wp_send_json_success(array('status' => $status));
    }
    
    /**
     * AJAX: ดึงสถานะการจองของทั้งเดือน
     */
    public function ajax_get_month_booking_status() {
        check_ajax_referer('psu_booking_nonce', 'nonce');
        
        $service_id = intval($_POST['service_id']);
        $year = intval($_POST['year']);
        $month = intval($_POST['month']); // 0-11 (JavaScript month)
        
        $statuses = $this->get_month_booking_status($service_id, $year, $month);
        
        wp_send_json_success($statuses);
    }
    
    /**
     * ดึงสถานะการจองของวันที่เฉพาะ
     */
    public function get_date_booking_status($service_id, $date) {
        global $wpdb;
        
        // ดึงข้อมูลบริการ
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psu_services WHERE id = %d AND status = 1",
            $service_id
        ));
        
        if (!$service) {
            return 'unavailable';
        }
        
        // ตรวจสอบวันทำการ (0=อาทิตย์, 1=จันทร์, ..., 6=เสาร์)
        $day_of_week = date('w', strtotime($date)); // w = 0(อาทิตย์) ถึง 6(เสาร์)
        $working_days = explode(',', $service->working_days);
        $working_days = array_map('intval', $working_days); // แปลงเป็น integer
        if (!in_array((int)$day_of_week, $working_days)) {
            return 'unavailable';
        }
        
        // ดึง timeslots ทั้งหมดที่เป็นไปได้
        $all_timeslots = $this->get_available_timeslots($service_id, $date);
        
        if (empty($all_timeslots)) {
            return 'unavailable';
        }
        
        // นับจำนวน timeslots ทั้งหมด และที่ถูกจองแล้ว
        $total_slots = 0;
        $booked_slots = 0;
        
        foreach ($all_timeslots as $category) {
            foreach ($category['slots'] as $slot) {
                $total_slots++;
                if (!$slot['available']) {
                    $booked_slots++;
                }
            }
        }
        
        if ($total_slots === 0) {
            return 'unavailable';
        }
        
        if ($booked_slots === 0) {
            return 'available';
        } else if ($booked_slots === $total_slots) {
            return 'full';
        } else {
            return 'partial';
        }
    }
    
    /**
     * ดึงสถานะการจองของทั้งเดือน
     */
    public function get_month_booking_status($service_id, $year, $month) {
        global $wpdb;
        
        // ดึงข้อมูลบริการ
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psu_services WHERE id = %d AND status = 1",
            $service_id
        ));
        
        if (!$service) {
            return array();
        }
        
        $working_days = explode(',', $service->working_days);
        $working_days = array_map('intval', $working_days); // แปลงเป็น integer
        
        // แปลง JavaScript month (0-11) เป็น PHP month (1-12)
        $php_month = $month + 1;
        
        // หาจำนวนวันในเดือน
        $days_in_month = date('t', mktime(0, 0, 0, $php_month, 1, $year));
        
        $statuses = array();
        
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf('%04d-%02d-%02d', $year, $php_month, $day);
            
            // ตรวจสอบว่าเป็นวันที่ผ่านมาแล้วหรือไม่
            if (strtotime($date) < strtotime('today')) {
                continue; // ข้ามวันที่ผ่านมาแล้ว
            }
            
            // ตรวจสอบวันทำการ (0=อาทิตย์, 1=จันทร์, ..., 6=เสาร์)
            $day_of_week = date('w', strtotime($date));
            
            if (!in_array((int)$day_of_week, $working_days)) {
                $statuses[$date] = 'unavailable';
                continue;
            }
            
            // ดึงสถานะการจองของวันนี้
            $statuses[$date] = $this->get_date_booking_status($service_id, $date);
        }
        
        return $statuses;
    }
    
    /**
     * ดึงการตั้งค่า
     */
    public function get_setting($key) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$wpdb->prefix}psu_settings WHERE setting_key = %s",
            $key
        ));
    }
    
    /**
     * บันทึกการตั้งค่า
     */
    public function save_setting($key, $value) {
        global $wpdb;
        return $wpdb->replace(
            $wpdb->prefix . 'psu_settings',
            array('setting_key' => $key, 'setting_value' => $value),
            array('%s', '%s')
        );
    }
    
    /**
     * Manual parsing สำหรับ timeslots เป็น fallback
     */
    private function manual_parse_timeslots($json_string) {
        error_log('PSU Booking: Manual parsing input: ' . $json_string);
        
        // ลองใช้ regex เพื่อดึงข้อมูลหลัก
        $pattern = '/\{"start":"([^"]+)","end":"([^"]+)","price":([^,}]+),"display":"([^"]+)"\}/';
        
        if (preg_match_all($pattern, $json_string, $matches, PREG_SET_ORDER)) {
            $timeslots = array();
            
            foreach ($matches as $match) {
                $timeslots[] = array(
                    'start' => $match[1],
                    'end' => $match[2],
                    'price' => floatval($match[3]),
                    'display' => $match[4],
                    'category' => $match[5]
                );
            }
            
            error_log('PSU Booking: Manual parsing result: ' . print_r($timeslots, true));
            return $timeslots;
        }
        
        // ลอง pattern ที่ง่ายกว่า
        $simple_pattern = '/start":"([^"]+)".*?end":"([^"]+)"/';
        if (preg_match_all($simple_pattern, $json_string, $simple_matches, PREG_SET_ORDER)) {
            $timeslots = array();
            
            foreach ($simple_matches as $match) {
                $timeslots[] = array(
                    'start' => $match[1],
                    'end' => $match[2],
                    'price' => 0,
                    'display' => $match[1] . ' - ' . $match[2],
                    'category' => 'รายชั่วโมง'
                );
            }
            
            error_log('PSU Booking: Simple manual parsing result: ' . print_r($timeslots, true));
            return $timeslots;
        }
        
        error_log('PSU Booking: Manual parsing failed');
        return false;
    }
    
    /**
     * AJAX: ดึงรายการจองของผู้ใช้
     */
    public function ajax_get_user_bookings() {
        check_ajax_referer('psu_booking_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $user_email = wp_get_current_user()->user_email;
        $page = intval($_POST['page'] ?? 1);
        $per_page = 10;
        $filters = $_POST['filters'] ?? array();
        
        global $wpdb;
        
        // Base query
        $where_conditions = array();
        $where_params = array();
        
        // User condition
        $where_conditions[] = "(user_id = %d OR customer_email = %s)";
        $where_params[] = $user_id;
        $where_params[] = $user_email;
        
        // Filter conditions
        if (!empty($filters['status'])) {
            $where_conditions[] = "b.status = %s";
            $where_params[] = $filters['status'];
        }
        
        if (!empty($filters['month'])) {
            $where_conditions[] = "MONTH(booking_date) = %d";
            $where_params[] = intval($filters['month']);
        }
        
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where_conditions[] = "(
                s.name LIKE %s OR 
                b.customer_name LIKE %s OR 
                b.id LIKE %s OR
                DATE_FORMAT(b.booking_date, '%%Y-%%m-%%d') LIKE %s
            )";
            $where_params[] = $search;
            $where_params[] = $search;
            $where_params[] = $search;
            $where_params[] = $search;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Count total
        $count_query = "
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}psu_bookings b
            LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id
            WHERE {$where_clause}
        ";
        
        $total = $wpdb->get_var($wpdb->prepare($count_query, $where_params));
        
        // Get bookings
        $offset = ($page - 1) * $per_page;
        $bookings_query = "
            SELECT b.*, s.name as service_name
            FROM {$wpdb->prefix}psu_bookings b
            LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id
            WHERE {$where_clause}
            ORDER BY b.created_at DESC
            LIMIT %d OFFSET %d
        ";
        
        $query_params = array_merge($where_params, array($per_page, $offset));
        $bookings = $wpdb->get_results($wpdb->prepare($bookings_query, $query_params));
        
        $pagination = array(
            'current_page' => $page,
            'per_page' => $per_page,
            'total_items' => $total,
            'total_pages' => ceil($total / $per_page)
        );
        
        wp_send_json_success(array(
            'bookings' => $bookings,
            'pagination' => $pagination
        ));
    }
    
    /**
     * AJAX: ดึงรายละเอียดการจอง
     */
    public function ajax_get_booking_detail() {
        check_ajax_referer('psu_booking_nonce', 'nonce');
        
        $booking_id = intval($_POST['booking_id']);
        $user_id = get_current_user_id();
        $user_email = wp_get_current_user()->user_email;
        
        global $wpdb;
        
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT b.*, s.name as service_name
            FROM {$wpdb->prefix}psu_bookings b
            LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id
            WHERE b.id = %d AND (b.user_id = %d OR b.customer_email = %s)
        ", $booking_id, $user_id, $user_email));
        
        if (!$booking) {
            wp_send_json_error(array('message' => 'ไม่พบการจองที่เลือก'));
            return;
        }
        
        // ดึง custom field labels สำหรับบริการนี้
        $field_labels = array();
        
        // ดึง global fields และ service-specific fields
        $fields = $wpdb->get_results($wpdb->prepare("
            SELECT field_name, field_label, field_order
            FROM {$wpdb->prefix}psu_form_fields 
            WHERE (service_id IS NULL OR service_id = %d) AND status = 1
            ORDER BY field_order ASC
        ", $booking->service_id));
        
        foreach ($fields as $field) {
            $field_labels[$field->field_name] = $field->field_label;
            // รองรับ custom_field_ prefix
            $field_labels['custom_field_' . $field->field_name] = $field->field_label;
        }
        
        // ดึง field labels จากหมายเลข (สำหรับ custom_field_0, custom_field_1, etc.)
        foreach ($fields as $index => $field) {
            $field_labels['custom_field_' . $index] = $field->field_label;
            $field_labels['custom_field_' . $field->field_order] = $field->field_label;
        }
        
        // เพิ่ม fallback labels สำหรับ fields ที่อาจไม่มีใน database
        $common_labels = array(
            'customer_name' => 'ชื่อผู้จอง',
            'customer_email' => 'อีเมล',
            'customer_phone' => 'เบอร์โทรศัพท์',
            'additional_info' => 'ข้อมูลเพิ่มเติม'
        );
        
        $field_labels = array_merge($common_labels, $field_labels);
        
        // เพิ่ม field labels ให้กับ booking object
        $booking->field_labels = $field_labels;
        
        // Debug logging
        error_log('PSU Booking Detail: Field labels - ' . print_r($field_labels, true));
        if (!empty($booking->form_data)) {
            error_log('PSU Booking Detail: Form data - ' . $booking->form_data);
        }
        
        wp_send_json_success($booking);
    }
    
    /**
     * AJAX: ดึงการจองสำหรับปฏิทิน
     */
    public function ajax_get_calendar_bookings() {
        check_ajax_referer('psu_booking_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $user_email = wp_get_current_user()->user_email;
        $year = intval($_POST['year']);
        $month = intval($_POST['month']); // JavaScript month (0-11)
        
        // Convert JS month to PHP month
        $php_month = $month + 1;
        
        global $wpdb;
        
        $bookings = $wpdb->get_results($wpdb->prepare("
            SELECT 
                b.id,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.status,
                s.name as service_name
            FROM {$wpdb->prefix}psu_bookings b
            LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id
            WHERE (b.user_id = %d OR b.customer_email = %s)
            AND YEAR(b.booking_date) = %d
            AND MONTH(b.booking_date) = %d
            ORDER BY b.booking_date, b.start_time
        ", $user_id, $user_email, $year, $php_month));
        
        // Group by date
        $calendar_data = array();
        foreach ($bookings as $booking) {
            $date = $booking->booking_date;
            if (!isset($calendar_data[$date])) {
                $calendar_data[$date] = array();
            }
            $calendar_data[$date][] = $booking;
        }
        
        wp_send_json_success($calendar_data);
    }
    
    /**
     * AJAX: ดึงการจองสำหรับปฏิทิน admin
     */
    public function ajax_get_admin_calendar_bookings() {
        // ตรวจสอบ nonce แบบยืดหยุ่น
        if (!wp_verify_nonce($_POST['nonce'], 'psu_admin_calendar_nonce') && 
            !check_ajax_referer('psu_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'การตรวจสอบความปลอดภัยล้มเหลว'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'ไม่มีสิทธิ์เข้าถึง'));
            return;
        }
        
        $year = intval($_POST['year']);
        $month = intval($_POST['month']); // JavaScript month (0-11)
        $status_filter = sanitize_text_field($_POST['status_filter']);
        $service_filter = intval($_POST['service_filter']);
        $date_from = sanitize_text_field($_POST['date_from']);
        $date_to = sanitize_text_field($_POST['date_to']);
        
        // Convert JS month to PHP month
        $php_month = $month + 1;
        
        global $wpdb;
        
        // สร้าง where conditions
        $where_conditions = array();
        $where_params = array();
        
        // เพิ่มเงื่อนไขเดือนและปี
        $where_conditions[] = "YEAR(b.booking_date) = %d";
        $where_params[] = $year;
        $where_conditions[] = "MONTH(b.booking_date) = %d";
        $where_params[] = $php_month;
        
        // เพิ่มเงื่อนไขจาก filter
        if (!empty($status_filter)) {
            $where_conditions[] = "b.status = %s";
            $where_params[] = $status_filter;
        }
        
        if (!empty($service_filter)) {
            $where_conditions[] = "b.service_id = %d";
            $where_params[] = $service_filter;
        }
        
        if (!empty($date_from)) {
            $where_conditions[] = "b.booking_date >= %s";
            $where_params[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = "b.booking_date <= %s";
            $where_params[] = $date_to;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "
            SELECT 
                b.id,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.status,
                b.customer_name,
                b.customer_email,
                b.total_price,
                s.name as service_name
            FROM {$wpdb->prefix}psu_bookings b
            LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id
            WHERE {$where_clause}
            ORDER BY b.booking_date, b.start_time
        ";
        
        $bookings = $wpdb->get_results($wpdb->prepare($query, $where_params));
        
        // Get service details if a specific service is filtered
        $service = null;
        if ($service_filter) {
            $service = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}psu_services WHERE id = %d", $service_filter));
        }

        // Pre-group bookings by date
        $bookings_by_date = array();
        foreach ($bookings as $booking) {
            $date = $booking->booking_date;
            if (!isset($bookings_by_date[$date])) {
                $bookings_by_date[$date] = array();
            }
            // Add nonce URLs for quick actions
            $booking->approve_url = wp_nonce_url('?page=psu-booking-bookings&action=approve&booking_id=' . $booking->id, 'approve_booking_' . $booking->id);
            $booking->reject_url  = wp_nonce_url('?page=psu-booking-bookings&action=reject&booking_id=' . $booking->id, 'reject_booking_' . $booking->id);
            $booking->delete_url  = wp_nonce_url('?page=psu-booking-bookings&action=delete&booking_id=' . $booking->id, 'delete_booking_' . $booking->id);
            $bookings_by_date[$date][] = $booking;
        }

        // Determine status for each day of the month
        $calendar_data = array();
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $php_month, $year);

        for ($day = 1; $day <= $days_in_month; $day++) {
            $date_str = sprintf('%d-%02d-%02d', $year, $php_month, $day);
            $day_bookings = isset($bookings_by_date[$date_str]) ? $bookings_by_date[$date_str] : [];
            $status = 'available';

            if ($service) {
                // Logic based on service working hours
                $day_of_week = date('w', strtotime($date_str));
                $working_days = explode(',', $service->working_days);

                if (in_array($day_of_week, $working_days)) {
                    $start_time = new DateTime($service->available_start_time);
                    $end_time = new DateTime($service->available_end_time);
                    $available_minutes = ($end_time->getTimestamp() - $start_time->getTimestamp()) / 60;
                    
                    if ($service->break_start_time && $service->break_end_time) {
                        $break_start = new DateTime($service->break_start_time);
                        $break_end = new DateTime($service->break_end_time);
                        $available_minutes -= ($break_end->getTimestamp() - $break_start->getTimestamp()) / 60;
                    }

                    $booked_minutes = 0;
                    foreach ($day_bookings as $booking) {
                        if (in_array($booking->status, ['approved', 'pending']) && $booking->service_id == $service_filter) {
                            $booking_start = new DateTime($booking->start_time);
                            $booking_end = new DateTime($booking->end_time);
                            $booked_minutes += ($booking_end->getTimestamp() - $booking_start->getTimestamp()) / 60;
                        }
                    }
                    
                    if ($booked_minutes <= 0) {
                        $status = 'available';
                    } elseif ($booked_minutes >= $available_minutes) {
                        $status = 'full';
                    } else {
                        $status = 'partial';
                    }
                } else {
                     $status = 'available'; // Non-working day is considered available (no bookings possible)
                }
            } else {
                // Fallback logic based on booking count
                $active_bookings_count = 0;
                foreach ($day_bookings as $booking) {
                    if (in_array($booking->status, ['approved', 'pending'])) {
                        $active_bookings_count++;
                    }
                }

                if ($active_bookings_count <= 0) {
                    $status = 'available';
                } elseif ($active_bookings_count > 0 && $active_bookings_count < 5) {
                    $status = 'partial';
                } else {
                    $status = 'full';
                }
            }

            $calendar_data[$date_str] = array(
                'status'   => $status,
                'bookings' => $day_bookings,
            );
        }
        
        wp_send_json_success($calendar_data);
    }

    /**
     * AJAX: ส่งออกการจองเป็น CSV
     */
    public function ajax_export_bookings_csv() {
        if (!wp_verify_nonce($_POST['nonce'], 'psu_admin_nonce') || !current_user_can('manage_options')) {
            wp_die('การตรวจสอบความปลอดภัยล้มเหลว');
        }

        // รับพารามิเตอร์การกรอง (เหมือนกับหน้ารายการจอง)
        $status_filter = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $service_filter = isset($_POST['service_id']) ? intval($_POST['service_id']) : '';
        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';

        global $wpdb;
        $where_conditions = array();
        $where_values = array();

        // ถ้าไม่ได้ระบุช่วงวันที่ ให้แสดงเดือนปัจจุบันเป็นค่าเริ่มต้น
        if (empty($date_from) && empty($date_to)) {
            $current_month = date('Y-m');
            $where_conditions[] = "DATE_FORMAT(b.booking_date, '%Y-%m') = %s";
            $where_values[] = $current_month;
        }

        if ($status_filter) {
            $where_conditions[] = "b.status = %s";
            $where_values[] = $status_filter;
        }

        if ($service_filter) {
            $where_conditions[] = "b.service_id = %d";
            $where_values[] = $service_filter;
        }

        if ($date_from) {
            $where_conditions[] = "b.booking_date >= %s";
            $where_values[] = $date_from;
        }

        if ($date_to) {
            $where_conditions[] = "b.booking_date <= %s";
            $where_values[] = $date_to;
        }

        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $query = "
            SELECT 
                b.id,
                b.customer_name,
                b.customer_email,
                b.customer_phone,
                s.name as service_name,
                s.category as service_category,
                b.booking_date,
                b.start_time,
                b.end_time,
                b.total_price,
                b.status,
                b.rejection_reason,
                b.admin_notes,
                b.created_at
            FROM {$wpdb->prefix}psu_bookings b
            LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id
            $where_clause
            ORDER BY b.created_at DESC
        ";

        if (!empty($where_values)) {
            $bookings = $wpdb->get_results($wpdb->prepare($query, $where_values));
        } else {
            $bookings = $wpdb->get_results($query);
        }

        // ส่งออกเป็น CSV
        $filename = 'bookings_export_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // เพิ่ม BOM สำหรับ UTF-8 เพื่อให้ Excel แสดงผลภาษาไทยได้ถูกต้อง
        echo "\xEF\xBB\xBF";

        $output = fopen('php://output', 'w');

        // หัวข้อคอลัมน์
        $headers = array(
            'รหัสจอง',
            'ชื่อผู้จอง',
            'อีเมล',
            'เบอร์โทรศัพท์',
            'บริการ',
            'หมวดหมู่',
            'วันที่จอง',
            'เวลาเริ่ม',
            'เวลาสิ้นสุด',
            'ราคา',
            'สถานะ',
            'เหตุผลปฏิเสธ',
            'บันทึกแอดมิน',
            'วันที่สร้าง'
        );
        fputcsv($output, $headers);

        // ข้อมูลการจอง
        foreach ($bookings as $booking) {
            $status_text = '';
            switch ($booking->status) {
                case 'pending': $status_text = 'รออนุมัติ'; break;
                case 'approved': $status_text = 'อนุมัติแล้ว'; break;
                case 'rejected': $status_text = 'ถูกปฏิเสธ'; break;
                default: $status_text = $booking->status;
            }

            $row = array(
                str_pad($booking->id, 6, '0', STR_PAD_LEFT),
                $booking->customer_name,
                $booking->customer_email,
                $booking->customer_phone ?: '-',
                $booking->service_name,
                $booking->service_category ?: '-',
                date('d/m/Y', strtotime($booking->booking_date)),
                date('H:i', strtotime($booking->start_time)),
                date('H:i', strtotime($booking->end_time)),
                number_format($booking->total_price, 2),
                $status_text,
                $booking->rejection_reason ?: '-',
                $booking->admin_notes ?: '-',
                date('d/m/Y H:i', strtotime($booking->created_at))
            );
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX: ตรวจสอบช่วงเวลาที่ว่าง สำหรับหน้าเพิ่มการจองใหม่
     */
    public function ajax_check_available_timeslots() {
        if (!wp_verify_nonce($_POST['nonce'], 'psu_admin_nonce') || !current_user_can('manage_options')) {
            wp_send_json_error('การตรวจสอบความปลอดภัยล้มเหลว');
        }

        $service_id = intval($_POST['service_id']);
        $date = sanitize_text_field($_POST['date']);

        if (!$service_id || !$date) {
            wp_send_json_error('ข้อมูลไม่ครบถ้วน');
        }

        global $wpdb;
        
        // ดึงข้อมูลบริการ
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psu_services WHERE id = %d AND status = 1", 
            $service_id
        ));

        if (!$service) {
            wp_send_json_error('ไม่พบบริการ');
        }

        // ตรวจสอบว่าเป็นวันทำการหรือไม่
        $date_obj = new DateTime($date);
        $day_of_week = $date_obj->format('w'); // 0 = Sunday
        $working_days = explode(',', $service->working_days);
        
        if (!in_array($day_of_week, $working_days)) {
            wp_send_json_error('วันที่เลือกไม่ใช่วันทำการของบริการนี้');
        }

        // ดึงการจองที่มีอยู่ในวันนั้น
        $existing_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT start_time, end_time FROM {$wpdb->prefix}psu_bookings 
             WHERE service_id = %d AND booking_date = %s AND status IN ('approved', 'pending')",
            $service_id, $date
        ));

        // สร้าง array ของช่วงเวลาที่ถูกจอง
        $booked_slots = array();
        foreach ($existing_bookings as $booking) {
            $booked_slots[] = array(
                'start' => $booking->start_time,
                'end' => $booking->end_time
            );
        }

        // สร้างช่วงเวลาที่ว่าง
        $available_slots = $this->generate_available_slots_for_admin($service, $date, $booked_slots);

        wp_send_json_success($available_slots);
    }

    /**
     * สร้างช่วงเวลาที่ว่างสำหรับแอดมิน (ไม่เข้มงวดเหมือน frontend)
     */
    private function generate_available_slots_for_admin($service, $date, $booked_slots) {
        $available_slots = array();
        
        $start_time = new DateTime($date . ' ' . $service->available_start_time);
        $end_time = new DateTime($date . ' ' . $service->available_end_time);
        $duration = $service->duration; // นาที
        
        while ($start_time < $end_time) {
            $slot_end = clone $start_time;
            $slot_end->add(new DateInterval('PT' . $duration . 'M'));
            
            if ($slot_end > $end_time) {
                break;
            }
            
            // ตรวจสอบว่าช่วงเวลานี้ชนกับการจองที่มีอยู่หรือไม่
            $is_available = true;
            $slot_start_str = $start_time->format('H:i:s');
            $slot_end_str = $slot_end->format('H:i:s');
            
            foreach ($booked_slots as $booked) {
                // ตรวจสอบการชนกัน
                if (($slot_start_str < $booked['end'] && $slot_end_str > $booked['start'])) {
                    $is_available = false;
                    break;
                }
            }
            
            // ตรวจสอบช่วงพัก (ถ้ามี)
            if ($is_available && $service->break_start_time && $service->break_end_time) {
                $break_start = $service->break_start_time;
                $break_end = $service->break_end_time;
                
                if (($slot_start_str < $break_end && $slot_end_str > $break_start)) {
                    $is_available = false;
                }
            }
            
            if ($is_available) {
                $available_slots[] = array(
                    'time' => $start_time->format('H:i'),
                    'label' => $start_time->format('H:i') . ' - ' . $slot_end->format('H:i')
                );
            }
            
            // เลื่อนไปช่วงเวลาถัดไป
            $start_time->add(new DateInterval('PT' . $duration . 'M'));
        }
        
        return $available_slots;
    }
}

// เริ่มต้น plugin
new PSU_Simple_Booking(); 