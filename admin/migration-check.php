<?php
// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration & Database Check
 * ตรวจสอบและแก้ไข database schema, settings และปัญหาต่างๆ
 */

// รันการเช็คเมื่อมีการส่ง form
if (isset($_POST['run_check'])) {
    echo '<div class="wrap">';
    echo '<h1>🔧 Database Migration & Settings Check</h1>';
    
    global $wpdb;
    
    // 1. ตรวจสอบตารางและ schema
    echo '<div class="card" style="margin: 20px 0; padding: 20px;">';
    echo '<h2>📊 Database Schema Check</h2>';
    
    $tables_to_check = array(
        'psu_services' => array(
            'expected_columns' => array(
                'id', 'name', 'description', 'image_url', 'category', 'price', 
                'duration', 'available_start_time', 'available_end_time',
                'break_start_time', 'break_end_time', 'working_days', 
                'timeslot_type', 'timeslot_duration', 'auto_approve',
                'payment_info', 'manager_name', 'manager_user_id', 'status', 'created_at'
            )
        ),
        'psu_bookings' => array(
            'expected_columns' => array(
                'id', 'service_id', 'user_id', 'customer_name', 'customer_email',
                'booking_date', 'start_time', 'end_time', 'total_price',
                'status', 'rejection_reason', 'additional_info', 'form_data',
                'created_at', 'updated_at'
            )
        ),
        'psu_settings' => array(
            'expected_columns' => array('id', 'setting_key', 'setting_value')
        )
    );
    
    foreach ($tables_to_check as $table_name => $config) {
        $full_table_name = $wpdb->prefix . $table_name;
        
        // ตรวจสอบว่าตารางมีอยู่หรือไม่
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") == $full_table_name;
        
        if ($table_exists) {
            echo "<p>✅ ตาราง <strong>$table_name</strong> มีอยู่แล้ว</p>";
            
            // ตรวจสอบ columns
            $columns = $wpdb->get_col("DESCRIBE $full_table_name");
            $missing_columns = array_diff($config['expected_columns'], $columns);
            
            if (empty($missing_columns)) {
                echo "<p style='margin-left: 20px;'>✅ คอลัมน์ครบถ้วน</p>";
            } else {
                echo "<p style='margin-left: 20px; color: red;'>❌ คอลัมน์ที่ขาดหายไป: " . implode(', ', $missing_columns) . "</p>";
            }
            
            // นับจำนวนข้อมูล
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
            echo "<p style='margin-left: 20px;'>📊 จำนวนข้อมูล: $count รายการ</p>";
            
        } else {
            echo "<p style='color: red;'>❌ ตาราง <strong>$table_name</strong> ไม่มี</p>";
        }
    }
    echo '</div>';
    
    // 2. ตรวจสอบ Settings
    echo '<div class="card" style="margin: 20px 0; padding: 20px;">';
    echo '<h2>⚙️ Settings Check</h2>';
    
    $expected_settings = array(
        'frontend_texts' => 'ข้อความหน้าเว็บ',
        'email_notifications' => 'การตั้งค่าอีเมล',
        'date_format' => 'รูปแบบวันที่',
        'timezone' => 'เขตเวลา'
    );
    
    foreach ($expected_settings as $key => $description) {
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$wpdb->prefix}psu_settings WHERE setting_key = %s",
            $key
        ));
        
        if ($value !== null) {
            echo "<p>✅ <strong>$description</strong> ($key): มีค่า</p>";
            if (json_decode($value) === null && $value !== 'null') {
                echo "<p style='margin-left: 20px; color: orange;'>⚠️ ข้อมูลอาจไม่ใช่ JSON format</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ <strong>$description</strong> ($key): ไม่มีค่า</p>";
        }
    }
    echo '</div>';
    
    // 3. ตรวจสอบ Date Format Issues
    echo '<div class="card" style="margin: 20px 0; padding: 20px;">';
    echo '<h2>📅 Date Format Issues</h2>';
    
    // ตรวจสอบ booking_date format
    $sample_bookings = $wpdb->get_results(
        "SELECT id, booking_date, start_time, end_time, created_at FROM {$wpdb->prefix}psu_bookings LIMIT 5"
    );
    
    if ($sample_bookings) {
        echo "<h4>ตัวอย่างข้อมูลการจอง:</h4>";
        echo "<table class='wp-list-table widefat'>";
        echo "<thead><tr><th>ID</th><th>Booking Date</th><th>Start Time</th><th>End Time</th><th>Created At</th></tr></thead>";
        echo "<tbody>";
        foreach ($sample_bookings as $booking) {
            echo "<tr>";
            echo "<td>{$booking->id}</td>";
            echo "<td>{$booking->booking_date}</td>";
            echo "<td>{$booking->start_time}</td>";
            echo "<td>{$booking->end_time}</td>";
            echo "<td>{$booking->created_at}</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>ไม่มีข้อมูลการจองในระบบ</p>";
    }
    
    // ตรวจสอบ service available_time format
    $sample_services = $wpdb->get_results(
        "SELECT id, name, available_start_time, available_end_time, working_days, timeslot_type FROM {$wpdb->prefix}psu_services LIMIT 5"
    );
    
    if ($sample_services) {
        echo "<h4>ตัวอย่างข้อมูลบริการ:</h4>";
        echo "<table class='wp-list-table widefat'>";
        echo "<thead><tr><th>ID</th><th>Name</th><th>Start Time</th><th>End Time</th><th>Working Days</th><th>Timeslot Type</th></tr></thead>";
        echo "<tbody>";
        foreach ($sample_services as $service) {
            echo "<tr>";
            echo "<td>{$service->id}</td>";
            echo "<td>{$service->name}</td>";
            echo "<td>{$service->available_start_time}</td>";
            echo "<td>{$service->available_end_time}</td>";
            echo "<td>{$service->working_days}</td>";
            echo "<td>{$service->timeslot_type}</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>ไม่มีข้อมูลบริการในระบบ</p>";
    }
    echo '</div>';
    
    // 4. ตรวจสอบ Query Issues
    echo '<div class="card" style="margin: 20px 0; padding: 20px;">';
    echo '<h2>🔍 Common Query Issues</h2>';
    
    // ตรวจสอบ Foreign Key relationships
    $orphaned_bookings = $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings b 
         LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id 
         WHERE s.id IS NULL"
    );
    
    if ($orphaned_bookings > 0) {
        echo "<p style='color: red;'>❌ พบการจอง $orphaned_bookings รายการที่ไม่มีบริการที่เชื่อมโยง</p>";
    } else {
        echo "<p>✅ ความสัมพันธ์ระหว่างตารางปกติ</p>";
    }
    
    // ตรวจสอบ duplicate services
    $duplicate_services = $wpdb->get_results(
        "SELECT name, COUNT(*) as count FROM {$wpdb->prefix}psu_services 
         GROUP BY name HAVING COUNT(*) > 1"
    );
    
    if ($duplicate_services) {
        echo "<p style='color: orange;'>⚠️ พบบริการซ้ำ:</p>";
        foreach ($duplicate_services as $dup) {
            echo "<p style='margin-left: 20px;'>- {$dup->name} ({$dup->count} รายการ)</p>";
        }
    } else {
        echo "<p>✅ ไม่มีบริการซ้ำ</p>";
    }
    echo '</div>';
    
    // 5. Auto-fix common issues
    echo '<div class="card" style="margin: 20px 0; padding: 20px;">';
    echo '<h2>🔧 Auto-Fix Issues</h2>';
    
    if (isset($_POST['auto_fix'])) {
        // แก้ไข default settings
        $default_settings = array(
            'date_format' => 'd/m/Y',
            'timezone' => 'Asia/Bangkok',
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
            ))
        );
        
        $fixed_count = 0;
        foreach ($default_settings as $key => $value) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT setting_value FROM {$wpdb->prefix}psu_settings WHERE setting_key = %s",
                $key
            ));
            
            if ($existing === null) {
                $result = $wpdb->insert(
                    $wpdb->prefix . 'psu_settings',
                    array('setting_key' => $key, 'setting_value' => $value),
                    array('%s', '%s')
                );
                if ($result) {
                    echo "<p>✅ เพิ่ม setting: $key</p>";
                    $fixed_count++;
                }
            }
        }
        
        echo "<p><strong>แก้ไขเสร็จสิ้น: $fixed_count รายการ</strong></p>";
    } else {
        echo "<p>👆 คลิกปุ่ม 'Auto-Fix' เพื่อแก้ไขปัญหาอัตโนมัติ</p>";
    }
    echo '</div>';
    
    echo '</div>';
}
?>

<div class="wrap">
    <h1>🔧 PSU Booking - Migration & Database Check</h1>
    
    <div class="card" style="margin: 20px 0; padding: 20px;">
        <h2>เครื่องมือตรวจสอบและแก้ไขระบบ</h2>
        <p>เครื่องมือนี้จะตรวจสอบ:</p>
        <ul>
            <li>✅ Database Schema และ Tables</li>
            <li>⚙️ Settings และ Configuration</li>
            <li>📅 Date Format และ Timezone</li>
            <li>🔍 ความสัมพันธ์ของข้อมูล</li>
            <li>🔧 แก้ไขปัญหาอัตโนมัติ</li>
        </ul>
        
        <form method="post" action="">
            <p class="submit">
                <input type="submit" name="run_check" class="button button-primary" value="🔍 เริ่มตรวจสอบ">
                <input type="submit" name="run_check" class="button" value="🔧 Auto-Fix" onclick="this.form.elements['auto_fix'].value='1'">
                <input type="hidden" name="auto_fix" value="">
            </p>
        </form>
    </div>
    
    <?php if (isset($_POST['run_check'])): ?>
        <div class="card" style="margin: 20px 0; padding: 20px; background: #f0f8ff;">
            <h3>💡 คำแนะนำการแก้ไข</h3>
            <ol>
                <li><strong>หากตารางไม่มี:</strong> ให้ deactivate และ activate plugin ใหม่</li>
                <li><strong>หากคอลัมน์ขาดหาย:</strong> ให้รัน Migration Script</li>
                <li><strong>หาก Settings ไม่มี:</strong> ให้คลิก Auto-Fix</li>
                <li><strong>หาก Date format ผิด:</strong> ให้แก้ไขใน frontend.js</li>
                <li><strong>หากมีข้อมูลซ้ำ:</strong> ให้ทำความสะอาดด้วยตนเอง</li>
            </ol>
        </div>
    <?php endif; ?>
</div> 