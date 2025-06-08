<?php
// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// แสดงข้อมูล POST เมื่อมีการส่งฟอร์ม
if (isset($_POST['save_service'])) {
    echo '<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 5px;">';
    echo '<h3 style="color: #0073aa;">✅ ข้อมูลที่ส่งมาสำเร็จ!</h3>';
    
    // แสดงข้อมูลทั้งหมด
    echo '<details style="margin: 10px 0;"><summary style="cursor: pointer; font-weight: bold;">ดูข้อมูล $_POST ทั้งหมด</summary>';
    echo '<pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow: auto;">' . htmlspecialchars(print_r($_POST, true)) . '</pre>';
    echo '</details>';
    
    // ตรวจสอบ nonce
    echo '<h4>🔐 การตรวจสอบความปลอดภัย:</h4>';
    if (function_exists('wp_verify_nonce') && isset($_POST['psu_service_nonce'])) {
        $nonce_check = wp_verify_nonce($_POST['psu_service_nonce'], 'psu_save_service');
        echo '<p>Nonce: ' . ($nonce_check ? '<span style="color: green;">✅ ผ่าน</span>' : '<span style="color: red;">❌ ไม่ผ่าน</span>') . '</p>';
    } else {
        echo '<p>Nonce: <span style="color: orange;">⚠️ ไม่พบหรือฟังก์ชัน wp_verify_nonce ไม่พร้อมใช้งาน</span></p>';
    }
    
    // ตรวจสอบข้อมูลที่จำเป็น
    echo '<h4>📋 การตรวจสอบข้อมูลที่จำเป็น:</h4>';
    $name = $_POST['name'] ?? '';
    $timeslot_types = $_POST['timeslot_type'] ?? [];
    $working_days = $_POST['working_days'] ?? [];
    
    echo '<ul style="list-style-type: none; padding-left: 0;">';
    echo '<li>' . (!empty($name) ? '✅' : '❌') . ' ชื่อบริการ: ' . (empty($name) ? '<span style="color: red;">ไม่มี</span>' : '<span style="color: green;">' . htmlspecialchars($name) . '</span>') . '</li>';
    echo '<li>' . (!empty($timeslot_types) ? '✅' : '❌') . ' ประเภทการจอง: ' . (empty($timeslot_types) ? '<span style="color: red;">ไม่มี</span>' : '<span style="color: green;">' . implode(', ', $timeslot_types) . '</span>') . '</li>';
    echo '<li>' . (!empty($working_days) ? '✅' : '❌') . ' วันทำการ: ' . (empty($working_days) ? '<span style="color: red;">ไม่มี</span>' : '<span style="color: green;">' . implode(', ', $working_days) . '</span>') . '</li>';
    echo '</ul>';
    
    // ทดลองบันทึกลงฐานข้อมูล
    if (!empty($name) && !empty($timeslot_types) && !empty($working_days)) {
        echo '<h4>💾 ทดลองบันทึกลงฐานข้อมูล:</h4>';
        
        global $wpdb;
        $test_data = array(
            'name' => sanitize_text_field($name),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'image_url' => esc_url_raw($_POST['image_url'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'duration' => intval($_POST['duration'] ?? 60),
            'available_start_time' => sanitize_text_field($_POST['available_start_time'] ?? '09:00'),
            'available_end_time' => sanitize_text_field($_POST['available_end_time'] ?? '17:00'),
            'break_start_time' => sanitize_text_field($_POST['break_start_time'] ?? '12:00'),
            'break_end_time' => sanitize_text_field($_POST['break_end_time'] ?? '13:00'),
            'working_days' => implode(',', array_map('intval', $working_days)),
            'timeslot_type' => implode(',', array_map('sanitize_text_field', $timeslot_types)),
            'timeslot_duration' => intval($_POST['timeslot_duration'] ?? 60),
            'auto_approve' => isset($_POST['auto_approve']) ? 1 : 0,
            'payment_info' => sanitize_textarea_field($_POST['payment_info'] ?? ''),
            'manager_name' => sanitize_text_field($_POST['manager_name'] ?? ''),
            'status' => isset($_POST['status']) ? 1 : 0
        );
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'psu_services',
            $test_data,
            array('%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d')
        );
        
        if ($result !== false) {
            $new_id = $wpdb->insert_id;
            echo '<p style="color: green;">✅ บันทึกสำเร็จ! ID บริการใหม่: ' . $new_id . '</p>';
        } else {
            echo '<p style="color: red;">❌ เกิดข้อผิดพลาดในการบันทึก: ' . $wpdb->last_error . '</p>';
        }
    } else {
        echo '<p style="color: orange;">⚠️ ข้อมูลไม่ครบถ้วน ไม่สามารถทดลองบันทึกได้</p>';
    }
    
    echo '</div>';
}
?>

<div class="wrap">
    <h1>Debug - การบันทึกบริการ</h1>
    
    <!-- Debug Information -->
    <div style="background: #e7f3ff; padding: 15px; margin: 10px 0; border: 1px solid #bee5eb; border-radius: 5px;">
        <h3>🔍 ข้อมูล Debug เบื้องต้น:</h3>
        <ul>
            <li><strong>Current URL:</strong> <?php echo $_SERVER['REQUEST_URI']; ?></li>
            <li><strong>Request Method:</strong> <?php echo $_SERVER['REQUEST_METHOD']; ?></li>
            <li><strong>POST มีข้อมูลหรือไม่:</strong> <?php echo empty($_POST) ? '❌ ไม่มี' : '✅ มี (' . count($_POST) . ' items)'; ?></li>
            <li><strong>มี save_service หรือไม่:</strong> <?php echo isset($_POST['save_service']) ? '✅ มี' : '❌ ไม่มี'; ?></li>
        </ul>
        
        <?php if (!empty($_POST)): ?>
            <details>
                <summary><strong>ข้อมูล $_POST ทั้งหมด:</strong></summary>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow: auto;"><?php echo htmlspecialchars(print_r($_POST, true)); ?></pre>
            </details>
        <?php endif; ?>
    </div>
    
    <form method="post" action="" id="debug-form" class="debug-service-form">
        <?php if (function_exists('wp_nonce_field')): ?>
            <?php wp_nonce_field('psu_save_service', 'psu_service_nonce'); ?>
        <?php endif; ?>
        
        <table class="form-table">
            <tr>
                <th>ชื่อบริการ</th>
                <td><input type="text" name="name" value="ทดสอบบริการ" required></td>
            </tr>
            <tr>
                <th>ประเภทการจอง</th>
                <td>
                    <label><input type="checkbox" name="timeslot_type[]" value="hourly"> รายชั่วโมง</label><br>
                    <label><input type="checkbox" name="timeslot_type[]" value="morning_afternoon"> ครึ่งวัน</label><br>
                    <label><input type="checkbox" name="timeslot_type[]" value="full_day"> เต็มวัน</label>
                </td>
            </tr>
            <tr>
                <th>วันทำการ</th>
                <td>
                    <label><input type="checkbox" name="working_days[]" value="1"> จันทร์</label><br>
                    <label><input type="checkbox" name="working_days[]" value="2"> อังคาร</label><br>
                    <label><input type="checkbox" name="working_days[]" value="3"> พุธ</label><br>
                    <label><input type="checkbox" name="working_days[]" value="4"> พฤหัสบดี</label><br>
                    <label><input type="checkbox" name="working_days[]" value="5"> ศุกร์</label>
                </td>
            </tr>
            <tr>
                <th>ราคา</th>
                <td><input type="number" name="price" value="100" step="0.01"></td>
            </tr>
            <tr>
                <th>เวลาเปิด-ปิด</th>
                <td>
                    <input type="time" name="available_start_time" value="09:00"> - 
                    <input type="time" name="available_end_time" value="17:00">
                </td>
            </tr>
            <tr>
                <th>เวลาพัก</th>
                <td>
                    <input type="time" name="break_start_time" value="12:00"> - 
                    <input type="time" name="break_end_time" value="13:00">
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="save_service" class="button-primary" value="ทดสอบบันทึก">
            <button type="button" onclick="checkAll()" class="button">เลือกทั้งหมด</button>
        </p>
        
        <script>
        function checkAll() {
            // เลือกทุก checkbox
            document.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox) {
                checkbox.checked = true;
            });
            console.log('✅ เลือก checkbox ทั้งหมดแล้ว');
        }
        
        // เพิ่ม event listener สำหรับ form submit
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('debug-form');
            
            form.addEventListener('submit', function(e) {
                console.log('🚀 Form submit triggered!');
                console.log('Form data:', new FormData(form));
                
                // เก็บข้อมูล form ก่อนส่ง
                const formData = new FormData(form);
                const data = {};
                for (let [key, value] of formData.entries()) {
                    data[key] = value;
                }
                console.log('Form data object:', data);
                
                // ตรวจสอบ checkbox
                const timeslotTypes = formData.getAll('timeslot_type[]');
                const workingDays = formData.getAll('working_days[]');
                console.log('Timeslot types:', timeslotTypes);
                console.log('Working days:', workingDays);
                
                if (timeslotTypes.length === 0) {
                    alert('⚠️ กรุณาเลือกประเภทการจองอย่างน้อย 1 ประเภท');
                    e.preventDefault();
                    return false;
                }
                
                if (workingDays.length === 0) {
                    alert('⚠️ กรุณาเลือกวันทำการอย่างน้อย 1 วัน');
                    e.preventDefault();
                    return false;
                }
                
                console.log('✅ Form validation passed, submitting...');
                return true;
            });
            
            console.log('📝 Debug form event listener added');
        });
        </script>
    </form>
    
    <div style="margin-top: 40px; padding: 20px; background: #fff; border: 1px solid #ccc;">
        <h3>ตรวจสอบข้อมูลในฐานข้อมูล:</h3>
        <?php
        global $wpdb;
        $services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}psu_services ORDER BY created_at DESC LIMIT 5");
        
        if ($services) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>ID</th><th>ชื่อ</th><th>ประเภทการจอง</th><th>วันทำการ</th><th>สถานะ</th></tr></thead>';
            echo '<tbody>';
            foreach ($services as $service) {
                echo '<tr>';
                echo '<td>' . $service->id . '</td>';
                echo '<td>' . esc_html($service->name) . '</td>';
                echo '<td>' . esc_html($service->timeslot_type) . '</td>';
                echo '<td>' . esc_html($service->working_days) . '</td>';
                echo '<td>' . ($service->status ? 'เปิดใช้งาน' : 'ปิดใช้งาน') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>ไม่มีข้อมูลบริการในฐานข้อมูล</p>';
        }
        ?>
    </div>
</div> 