<?php
// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// จัดการการบันทึกข้อมูล
if (isset($_POST['save_service']) && wp_verify_nonce($_POST['psu_service_nonce'], 'psu_save_service')) {
    global $wpdb;
    
    $service_data = array(
        'name' => sanitize_text_field($_POST['name']),
        'description' => sanitize_textarea_field($_POST['description']),
        'image_url' => esc_url_raw($_POST['image_url']),
        'category' => sanitize_text_field($_POST['category']),
        'price' => floatval($_POST['price']),
        'duration' => intval($_POST['duration']),
        'available_start_time' => sanitize_text_field($_POST['available_start_time']),
        'available_end_time' => sanitize_text_field($_POST['available_end_time']),
        'break_start_time' => sanitize_text_field($_POST['break_start_time']),
        'break_end_time' => sanitize_text_field($_POST['break_end_time']),
        'working_days' => implode(',', array_map('intval', $_POST['working_days'])),
        'timeslot_type' => sanitize_text_field($_POST['timeslot_type']),
        'timeslot_duration' => intval($_POST['timeslot_duration']),
        'auto_approve' => isset($_POST['auto_approve']) ? 1 : 0,
        'payment_info' => sanitize_textarea_field($_POST['payment_info']),
        'manager_name' => sanitize_text_field($_POST['manager_name']),
        'status' => isset($_POST['status']) ? 1 : 0
    );
    
    if (isset($_POST['service_id']) && $_POST['service_id']) {
        // แก้ไขบริการ
        $service_id = intval($_POST['service_id']);
        $result = $wpdb->update(
            $wpdb->prefix . 'psu_services',
            $service_data,
            array('id' => $service_id),
            array('%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d'),
            array('%d')
        );
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>แก้ไขบริการสำเร็จ!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>เกิดข้อผิดพลาดในการแก้ไขบริการ</p></div>';
        }
    } else {
        // เพิ่มบริการใหม่
        $result = $wpdb->insert(
            $wpdb->prefix . 'psu_services',
            $service_data,
            array('%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d')
        );
        
        if ($result) {
            echo '<div class="notice notice-success"><p>เพิ่มบริการใหม่สำเร็จ!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>เกิดข้อผิดพลาดในการเพิ่มบริการ</p></div>';
        }
    }
}

// จัดการการลบ
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['service_id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_service_' . $_GET['service_id'])) {
    global $wpdb;
    $service_id = intval($_GET['service_id']);
    
    // ตรวจสอบว่ามีการจองที่ใช้บริการนี้หรือไม่
    $booking_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings WHERE service_id = %d",
        $service_id
    ));
    
    if ($booking_count > 0) {
        echo '<div class="notice notice-error"><p>ไม่สามารถลบบริการที่มีการจองอยู่ได้</p></div>';
    } else {
        $result = $wpdb->delete(
            $wpdb->prefix . 'psu_services',
            array('id' => $service_id),
            array('%d')
        );
        
        if ($result) {
            echo '<div class="notice notice-success"><p>ลบบริการสำเร็จ!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>เกิดข้อผิดพลาดในการลบบริการ</p></div>';
        }
    }
}

// ดึงข้อมูลสำหรับแก้ไข
$edit_service = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['service_id'])) {
    global $wpdb;
    $service_id = intval($_GET['service_id']);
    $edit_service = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}psu_services WHERE id = %d",
        $service_id
    ));
}

// ดึงรายการบริการ
global $wpdb;
$services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}psu_services ORDER BY created_at DESC");

// ดึงหมวดหมู่ที่มีอยู่
$categories = $wpdb->get_col("SELECT DISTINCT category FROM {$wpdb->prefix}psu_services WHERE category != '' ORDER BY category");
?>

<div class="wrap">
    <h1>จัดการบริการ 
        <a href="#" class="page-title-action" onclick="toggleServiceForm()">เพิ่มบริการใหม่</a>
    </h1>

    <!-- ฟอร์มเพิ่ม/แก้ไขบริการ -->
    <div id="service-form" style="<?php echo $edit_service ? 'display: block;' : 'display: none;'; ?>">
        <div class="card">
            <h2><?php echo $edit_service ? 'แก้ไขบริการ' : 'เพิ่มบริการใหม่'; ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('psu_save_service', 'psu_service_nonce'); ?>
                <?php if ($edit_service): ?>
                    <input type="hidden" name="service_id" value="<?php echo $edit_service->id; ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="name">ชื่อบริการ</label></th>
                        <td>
                            <input type="text" id="name" name="name" class="regular-text" 
                                   value="<?php echo $edit_service ? esc_attr($edit_service->name) : ''; ?>" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="description">คำอธิบาย</label></th>
                        <td>
                            <textarea id="description" name="description" rows="4" class="large-text"><?php echo $edit_service ? esc_textarea($edit_service->description) : ''; ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="image_url">URL รูปภาพ</label></th>
                        <td>
                            <input type="url" id="image_url" name="image_url" class="regular-text" 
                                   value="<?php echo $edit_service ? esc_url($edit_service->image_url) : ''; ?>">
                            <p class="description">URL ของรูปภาพที่แสดงในหน้าจอง</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="category">หมวดหมู่</label></th>
                        <td>
                            <input type="text" id="category" name="category" class="regular-text" 
                                   value="<?php echo $edit_service ? esc_attr($edit_service->category) : ''; ?>" 
                                   list="category-list">
                            <datalist id="category-list">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="price">ราคา (บาท/ชั่วโมง)</label></th>
                        <td>
                            <input type="number" id="price" name="price" step="0.01" min="0" class="small-text" 
                                   value="<?php echo $edit_service ? $edit_service->price : '0'; ?>">
                            <span class="description">บาท</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="duration">ระยะเวลาต่อรอบ</label></th>
                        <td>
                            <input type="number" id="duration" name="duration" min="15" step="15" class="small-text" 
                                   value="<?php echo $edit_service ? $edit_service->duration : '60'; ?>">
                            <span class="description">นาที</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">เวลาทำการ</th>
                        <td>
                            <label for="available_start_time">เปิด:</label>
                            <input type="time" id="available_start_time" name="available_start_time" 
                                   value="<?php echo $edit_service ? $edit_service->available_start_time : '09:00'; ?>">
                            
                            <label for="available_end_time" style="margin-left: 20px;">ปิด:</label>
                            <input type="time" id="available_end_time" name="available_end_time" 
                                   value="<?php echo $edit_service ? $edit_service->available_end_time : '17:00'; ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">เวลาพัก</th>
                        <td>
                            <label for="break_start_time">เริ่ม:</label>
                            <input type="time" id="break_start_time" name="break_start_time" 
                                   value="<?php echo $edit_service ? $edit_service->break_start_time : '12:00'; ?>">
                            
                            <label for="break_end_time" style="margin-left: 20px;">สิ้นสุด:</label>
                            <input type="time" id="break_end_time" name="break_end_time" 
                                   value="<?php echo $edit_service ? $edit_service->break_end_time : '13:00'; ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">วันทำการ</th>
                        <td>
                            <?php
                            $working_days = $edit_service ? explode(',', $edit_service->working_days) : array('1','2','3','4','5');
                            $days = array('0' => 'อาทิตย์', '1' => 'จันทร์', '2' => 'อังคาร', '3' => 'พุธ', '4' => 'พฤหัสบดี', '5' => 'ศุกร์', '6' => 'เสาร์');
                            ?>
                            <?php foreach ($days as $day_num => $day_name): ?>
                                <label style="margin-right: 15px;">
                                    <input type="checkbox" name="working_days[]" value="<?php echo $day_num; ?>" 
                                           <?php echo in_array($day_num, $working_days) ? 'checked' : ''; ?>>
                                    <?php echo $day_name; ?>
                                </label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="timeslot_type">ประเภทการจอง</label></th>
                        <td>
                            <select id="timeslot_type" name="timeslot_type">
                                <option value="hourly" <?php echo ($edit_service && $edit_service->timeslot_type == 'hourly') ? 'selected' : ''; ?>>รายชั่วโมง</option>
                                <option value="morning_afternoon" <?php echo ($edit_service && $edit_service->timeslot_type == 'morning_afternoon') ? 'selected' : ''; ?>>ครึ่งวัน (เช้า/บ่าย)</option>
                                <option value="full_day" <?php echo ($edit_service && $edit_service->timeslot_type == 'full_day') ? 'selected' : ''; ?>>เต็มวัน</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr id="timeslot_duration_row">
                        <th scope="row"><label for="timeslot_duration">ระยะเวลาต่อช่วง</label></th>
                        <td>
                            <input type="number" id="timeslot_duration" name="timeslot_duration" min="15" step="15" class="small-text" 
                                   value="<?php echo $edit_service ? $edit_service->timeslot_duration : '60'; ?>">
                            <span class="description">นาที (สำหรับการจองรายชั่วโมง)</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">การอนุมัติ</th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_approve" value="1" 
                                       <?php echo ($edit_service && $edit_service->auto_approve) ? 'checked' : ''; ?>>
                                อนุมัติอัตโนมัติ
                            </label>
                            <p class="description">หากไม่เลือก จะต้องอนุมัติด้วยตนเอง</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="payment_info">ข้อมูลการชำระเงิน</label></th>
                        <td>
                            <textarea id="payment_info" name="payment_info" rows="4" class="large-text"><?php echo $edit_service ? esc_textarea($edit_service->payment_info) : ''; ?></textarea>
                            <p class="description">ข้อมูลธนาคาร หรือวิธีการชำระเงิน</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="manager_name">ผู้ดูแล</label></th>
                        <td>
                            <input type="text" id="manager_name" name="manager_name" class="regular-text" 
                                   value="<?php echo $edit_service ? esc_attr($edit_service->manager_name) : ''; ?>">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">สถานะ</th>
                        <td>
                            <label>
                                <input type="checkbox" name="status" value="1" 
                                       <?php echo (!$edit_service || $edit_service->status) ? 'checked' : ''; ?>>
                                เปิดใช้งาน
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_service" class="button-primary" value="<?php echo $edit_service ? 'อัปเดต' : 'บันทึก'; ?>">
                    <button type="button" class="button" onclick="toggleServiceForm()">ยกเลิก</button>
                </p>
            </form>
        </div>
    </div>

    <!-- รายการบริการ -->
    <div id="services-list">
        <h2>รายการบริการ</h2>
        
        <?php if (empty($services)): ?>
            <p>ยังไม่มีบริการในระบบ</p>
        <?php else: ?>
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select id="bulk-action-selector-top">
                        <option value="-1">การดำเนินการจำนวนมาก</option>
                        <option value="activate">เปิดใช้งาน</option>
                        <option value="deactivate">ปิดใช้งาน</option>
                    </select>
                    <input type="submit" class="button action" value="ดำเนินการ">
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox">
                        </td>
                        <th class="manage-column">ชื่อบริการ</th>
                        <th class="manage-column">หมวดหมู่</th>
                        <th class="manage-column">ราคา</th>
                        <th class="manage-column">ประเภทการจอง</th>
                        <th class="manage-column">สถานะ</th>
                        <th class="manage-column">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" value="<?php echo $service->id; ?>">
                            </th>
                            <td>
                                <strong><?php echo esc_html($service->name); ?></strong>
                                <?php if ($service->description): ?>
                                    <br><small><?php echo esc_html(substr($service->description, 0, 100)) . (strlen($service->description) > 100 ? '...' : ''); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($service->category); ?></td>
                            <td><?php echo number_format($service->price, 2); ?> บาท</td>
                            <td>
                                <?php
                                switch ($service->timeslot_type) {
                                    case 'hourly': echo 'รายชั่วโมง'; break;
                                    case 'morning_afternoon': echo 'ครึ่งวัน'; break;
                                    case 'full_day': echo 'เต็มวัน'; break;
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($service->status): ?>
                                    <span class="status-active">เปิดใช้งาน</span>
                                <?php else: ?>
                                    <span class="status-inactive">ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=psu-booking-services&action=edit&service_id=<?php echo $service->id; ?>" class="button button-small">แก้ไข</a>
                                <a href="<?php echo wp_nonce_url('?page=psu-booking-services&action=delete&service_id=' . $service->id, 'delete_service_' . $service->id); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('คุณต้องการลบบริการนี้หรือไม่?');">ลบ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleServiceForm() {
    var form = document.getElementById('service-form');
    var list = document.getElementById('services-list');
    
    if (form.style.display === 'none') {
        form.style.display = 'block';
        list.style.display = 'none';
    } else {
        form.style.display = 'none';
        list.style.display = 'block';
        // รีเซ็ตฟอร์ม
        if (!document.querySelector('input[name="service_id"]')) {
            document.querySelector('form').reset();
        }
    }
}

// ซ่อน/แสดงฟิลด์ timeslot_duration ตามประเภทการจอง
document.addEventListener('DOMContentLoaded', function() {
    var timeslotType = document.getElementById('timeslot_type');
    var durationRow = document.getElementById('timeslot_duration_row');
    
    function toggleDurationField() {
        if (timeslotType.value === 'hourly') {
            durationRow.style.display = 'table-row';
        } else {
            durationRow.style.display = 'none';
        }
    }
    
    timeslotType.addEventListener('change', toggleDurationField);
    toggleDurationField(); // เรียกครั้งแรก
});
</script>

<style>
.card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-left: 4px solid #2271b1;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin: 20px 0;
    padding: 20px;
}

.status-active {
    color: #00a32a;
    font-weight: 600;
}

.status-inactive {
    color: #d63638;
    font-weight: 600;
}

.form-table th {
    width: 200px;
}

#service-form {
    margin-bottom: 30px;
}
</style>