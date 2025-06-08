<?php
/**
 * Plugin Name: PSU Simple Booking
 * Description: ระบบจองแบบเรียบง่าย สำหรับ WordPress ใช้งานง่าย รองรับการจองทุกประเภท
 * Version: 2.0
 * Author: PSU Team
 * Text Domain: psu-simple-booking
 * Domain Path: /languages
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
        
        // Email hooks
        add_action('psu_booking_created', array($this, 'send_booking_notification'), 10, 2);
        add_action('psu_booking_status_changed', array($this, 'send_status_notification'), 10, 3);
    }
    
    /**
     * สร้างตาราง database เมื่อเปิดใช้งาน plugin
     */
    public function activate() {
        $this->create_tables();
        $this->insert_default_data();
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
            timeslot_type varchar(20) DEFAULT 'hourly' COMMENT 'hourly, morning_afternoon, full_day',
            timeslot_duration int(11) DEFAULT 60 COMMENT 'minutes per slot',
            auto_approve tinyint(1) DEFAULT 0,
            payment_info text,
            manager_name varchar(255),
            manager_user_id int(11),
            status tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // ตาราง Bookings
        $table_bookings = $wpdb->prefix . 'psu_bookings';
        $sql_bookings = "CREATE TABLE $table_bookings (
            id int(11) NOT NULL AUTO_INCREMENT,
            service_id int(11) NOT NULL,
            user_id int(11),
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            booking_date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            total_price decimal(10,2) DEFAULT 0.00,
            status varchar(20) DEFAULT 'pending' COMMENT 'pending, approved, rejected',
            rejection_reason text,
            additional_info text,
            form_data text COMMENT 'JSON format for custom form fields',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY service_id (service_id),
            KEY user_id (user_id),
            KEY booking_date (booking_date),
            KEY status (status)
        ) $charset_collate;";
        
        // ตาราง Settings
        $table_settings = $wpdb->prefix . 'psu_settings';
        $sql_settings = "CREATE TABLE $table_settings (
            id int(11) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_services);
        dbDelta($sql_bookings);
        dbDelta($sql_settings);
    }
    
    /**
     * เพิ่มข้อมูลเริ่มต้น
     */
    public function insert_default_data() {
        global $wpdb;
        
        // ตรวจสอบว่ามีข้อมูลอยู่แล้วหรือไม่
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psu_services");
        if ($existing > 0) return;
        
        // เพิ่มการตั้งค่าเริ่มต้น
        $default_settings = array(
            'frontend_texts' => json_encode(array(
                'select_service' => 'เลือกบริการ',
                'select_date' => 'เลือกวันที่',
                'select_time' => 'เลือกเวลา',
                'customer_info' => 'ข้อมูลผู้จอง',
                'name' => 'ชื่อ',
                'email' => 'อีเมล',
                'additional_info' => 'รายละเอียดเพิ่มเติม',
                'submit_booking' => 'ยืนยันการจอง',
                'booking_success' => 'จองสำเร็จแล้ว!',
                'next' => 'ถัดไป',
                'previous' => 'ก่อนหน้า',
                'book_now' => 'จองเลย'
            )),
            'email_notifications' => json_encode(array(
                'user_booking_created' => array(
                    'enabled' => true,
                    'subject' => 'ยืนยันการจอง - {service_name}',
                    'message' => 'สวัสดีครับ/ค่ะ {customer_name}\n\nขอบคุณสำหรับการจอง {service_name}\nวันที่: {booking_date}\nเวลา: {start_time} - {end_time}\n\nสถานะการจอง: {status}'
                ),
                'admin_new_booking' => array(
                    'enabled' => true,
                    'subject' => 'มีการจองใหม่ - {service_name}',
                    'message' => 'มีการจองใหม่เข้ามาในระบบ\n\nบริการ: {service_name}\nผู้จอง: {customer_name}\nวันที่: {booking_date}\nเวลา: {start_time} - {end_time}'
                )
            ))
        );
        
        foreach ($default_settings as $key => $value) {
            $wpdb->replace(
                $wpdb->prefix . 'psu_settings',
                array('setting_key' => $key, 'setting_value' => $value),
                array('%s', '%s')
            );
        }
        
        // เพิ่มบริการตัวอย่าง
        $sample_service = array(
            'name' => 'ห้องประชุม A',
            'description' => 'ห้องประชุมขนาดกลาง รองรับ 20 คน พร้อมอุปกรณ์ AV',
            'category' => 'ห้องประชุม',
            'price' => 500.00,
            'duration' => 60,
            'available_start_time' => '08:00:00',
            'available_end_time' => '18:00:00',
            'break_start_time' => '12:00:00',
            'break_end_time' => '13:00:00',
            'working_days' => '1,2,3,4,5',
            'timeslot_type' => 'hourly',
            'timeslot_duration' => 60,
            'auto_approve' => 0,
            'payment_info' => 'โอนเงินผ่านบัญชี xxx-x-xxxxx-x พร้อมแนบสลิป',
            'manager_name' => 'ผู้ดูแลระบบ',
            'status' => 1
        );
        
        $wpdb->insert($wpdb->prefix . 'psu_services', $sample_service);
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
            'PSU Booking',
            'PSU Booking',
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
    }
    
    /**
     * โหลด CSS และ JS สำหรับ Frontend
     */
    public function enqueue_scripts() {
        wp_enqueue_style('psu-booking-style', PSU_BOOKING_PLUGIN_URL . 'assets/css/frontend.css', array(), PSU_BOOKING_VERSION);
        wp_enqueue_script('psu-booking-script', PSU_BOOKING_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), PSU_BOOKING_VERSION, true);
        
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
            wp_enqueue_style('psu-booking-admin-style', PSU_BOOKING_PLUGIN_URL . 'assets/css/admin.css', array(), PSU_BOOKING_VERSION);
            wp_enqueue_script('psu-booking-admin-script', PSU_BOOKING_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), PSU_BOOKING_VERSION, true);
            
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
        check_ajax_referer('psu_booking_nonce', 'nonce');
        
        $data = array(
            'service_id' => intval($_POST['service_id']),
            'customer_name' => sanitize_text_field($_POST['customer_name']),
            'customer_email' => sanitize_email($_POST['customer_email']),
            'booking_date' => sanitize_text_field($_POST['booking_date']),
            'timeslots' => $_POST['timeslots'], // array of selected timeslots
            'additional_info' => sanitize_textarea_field($_POST['additional_info'])
        );
        
        $result = $this->create_booking($data);
        
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
        
        // ตรวจสอบข้อมูล
        if (empty($data['service_id']) || empty($data['customer_name']) || empty($data['customer_email']) || empty($data['booking_date']) || empty($data['timeslots'])) {
            return array('success' => false, 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน');
        }
        
        // ดึงข้อมูลบริการ
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}psu_services WHERE id = %d AND status = 1",
            $data['service_id']
        ));
        
        if (!$service) {
            return array('success' => false, 'message' => 'ไม่พบบริการที่เลือก');
        }
        
        // สร้างการจองสำหรับแต่ละ timeslot
        $booking_ids = array();
        $total_price = 0;
        
        foreach ($data['timeslots'] as $timeslot) {
            $start_time = sanitize_text_field($timeslot['start']);
            $end_time = sanitize_text_field($timeslot['end']);
            
            // ตรวจสอบว่า timeslot ยังว่างอยู่
            if (!$this->is_timeslot_available($data['service_id'], $data['booking_date'], $start_time, $end_time)) {
                return array('success' => false, 'message' => 'ช่วงเวลา ' . $start_time . '-' . $end_time . ' ไม่ว่างแล้ว');
            }
            
            // คำนวณราคา
            $duration_hours = (strtotime($end_time) - strtotime($start_time)) / 3600;
            $slot_price = $service->price * $duration_hours;
            $total_price += $slot_price;
            
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
            
            $result = $wpdb->insert($wpdb->prefix . 'psu_bookings', $booking_data);
            
            if ($result) {
                $booking_id = $wpdb->insert_id;
                $booking_ids[] = $booking_id;
                
                // ส่ง hook สำหรับการจองใหม่
                do_action('psu_booking_created', $booking_id, $booking_data);
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
     * ดึง timeslots ที่ว่าง
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
        
        // ตรวจสอบวันทำการ
        $day_of_week = date('w', strtotime($date));
        $working_days = explode(',', $service->working_days);
        if (!in_array($day_of_week, $working_days)) {
            return array();
        }
        
        // สร้าง timeslots ตามประเภท
        $timeslots = array();
        
        switch ($service->timeslot_type) {
            case 'hourly':
                $timeslots = $this->generate_hourly_timeslots($service, $date);
                break;
            case 'morning_afternoon':
                $timeslots = $this->generate_morning_afternoon_timeslots($service, $date);
                break;
            case 'full_day':
                $timeslots = $this->generate_full_day_timeslots($service, $date);
                break;
        }
        
        // กรองเฉพาะที่ว่าง
        $available_timeslots = array();
        foreach ($timeslots as $slot) {
            if ($this->is_timeslot_available($service_id, $date, $slot['start'], $slot['end'])) {
                $available_timeslots[] = $slot;
            }
        }
        
        return $available_timeslots;
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
}

// เริ่มต้น plugin
new PSU_Simple_Booking(); 