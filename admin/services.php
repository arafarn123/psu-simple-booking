<?php
// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// Debug information - แสดงข้อมูลทุกครั้งที่โหลดหน้า
echo '<div style="background: #e7f3ff; padding: 15px; margin: 10px 0; border: 1px solid #bee5eb; border-radius: 4px;">';
echo '<h4>🔍 Debug Services Page:</h4>';
echo '<p><strong>Request Method:</strong> ' . $_SERVER['REQUEST_METHOD'] . '</p>';
echo '<p><strong>Current Time:</strong> ' . date('Y-m-d H:i:s') . '</p>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffeaa7; border-radius: 4px;">';
    echo '<h5>📨 POST Request detected!</h5>';
    echo '<p>Has save_service: ' . (isset($_POST['save_service']) ? '✅ Yes' : '❌ No') . '</p>';
    echo '<p>POST count: ' . count($_POST) . ' items</p>';
    
    if (!empty($_POST)) {
        echo '<details><summary>All POST data:</summary>';
        echo '<pre>' . htmlspecialchars(print_r($_POST, true)) . '</pre>';
        echo '</details>';
    }
    
    if (isset($_POST['save_service'])) {
        echo '<p>Has nonce: ' . (isset($_POST['psu_service_nonce']) ? '✅ Yes' : '❌ No') . '</p>';
        if (isset($_POST['psu_service_nonce'])) {
            $nonce_valid = wp_verify_nonce($_POST['psu_service_nonce'], 'psu_save_service');
            echo '<p>Nonce valid: ' . ($nonce_valid ? '✅ Yes' : '❌ No') . '</p>';
        }
    }
    echo '</div>';
} else {
    echo '<p>👀 Waiting for form submission...</p>';
}
echo '</div>';

// จัดการการบันทึกข้อมูล
if (isset($_POST['save_service']) && wp_verify_nonce($_POST['psu_service_nonce'], 'psu_save_service')) {
    global $wpdb;
    
    // ตรวจสอบข้อมูลพื้นฐาน
    $name = sanitize_text_field($_POST['name']);
    $timeslot_types = $_POST['timeslot_type'] ?? array();
    $working_days = $_POST['working_days'] ?? array();
    
    if (empty($name)) {
        echo '<div class="notice notice-error"><p>กรุณากรอกชื่อบริการ</p></div>';
    } elseif (empty($timeslot_types)) {
        echo '<div class="notice notice-error"><p>กรุณาเลือกประเภทการจองอย่างน้อย 1 ประเภท</p></div>';
    } elseif (empty($working_days)) {
        echo '<div class="notice notice-error"><p>กรุณาเลือกวันทำการอย่างน้อย 1 วัน</p></div>';
    } else {
        $service_data = array(
            'name' => $name,
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

// เตรียมข้อมูลสำหรับแก้ไข
$selected_timeslot_types = array();
$selected_working_days = array();

if ($edit_service) {
    $selected_timeslot_types = !empty($edit_service->timeslot_type) ? explode(',', $edit_service->timeslot_type) : array();
    $selected_working_days = !empty($edit_service->working_days) ? explode(',', $edit_service->working_days) : array();
} else {
    $selected_timeslot_types = array('hourly', 'morning_afternoon', 'full_day');
    $selected_working_days = array('1', '2', '3', '4', '5');
}
?>

<div class="wrap">
    <h1>จัดการบริการ</h1>

    <div class="psu-admin-container">
        <!-- ฟอร์มเพิ่ม/แก้ไขบริการ -->
        <div class="psu-form-section" id="service-form" style="<?php echo $edit_service ? 'display: block;' : 'display: none;'; ?>">
            <div class="psu-card">
                <div class="psu-card-header">
                    <h2><?php echo $edit_service ? 'แก้ไขบริการ' : 'เพิ่มบริการใหม่'; ?></h2>
                    <button type="button" class="button" onclick="toggleServiceForm()">
                        <?php echo $edit_service ? 'ยกเลิก' : 'ปิด'; ?>
                    </button>
                </div>
                
                <form method="post" action="" class="psu-service-form">
                    <?php wp_nonce_field('psu_save_service', 'psu_service_nonce'); ?>
                    <?php if ($edit_service): ?>
                        <input type="hidden" name="service_id" value="<?php echo $edit_service->id; ?>">
                    <?php endif; ?>
                    
                    <div class="psu-form-grid">
                        <!-- คอลัมน์ซ้าย -->
                        <div class="psu-form-column">
                            <div class="psu-form-group">
                                <label for="name" class="psu-label required">ชื่อบริการ</label>
                                <input type="text" id="name" name="name" class="psu-input" 
                                       value="<?php echo $edit_service ? esc_attr($edit_service->name) : ''; ?>" required>
                            </div>
                            
                            <div class="psu-form-group">
                                <label for="description" class="psu-label">คำอธิบาย</label>
                                <textarea id="description" name="description" rows="4" class="psu-textarea"><?php echo $edit_service ? esc_textarea($edit_service->description) : ''; ?></textarea>
                            </div>
                            
                            <div class="psu-form-group">
                                <label for="image_url" class="psu-label">รูปภาพบริการ</label>
                                <div class="psu-image-upload">
                                    <input type="url" id="image_url" name="image_url" class="psu-input" 
                                           value="<?php echo $edit_service ? esc_url($edit_service->image_url) : ''; ?>" 
                                           placeholder="URL รูปภาพ">
                                    <button type="button" class="button psu-upload-btn" onclick="openMediaLibrary()">เลือกรูปภาพ</button>
                                    <div id="image-preview" class="psu-image-preview">
                                        <?php if ($edit_service && $edit_service->image_url): ?>
                                            <img src="<?php echo esc_url($edit_service->image_url); ?>" alt="Preview">
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="psu-form-row">
                                <div class="psu-form-group">
                                    <label for="category" class="psu-label">หมวดหมู่</label>
                                    <input type="text" id="category" name="category" class="psu-input" 
                                           value="<?php echo $edit_service ? esc_attr($edit_service->category) : ''; ?>" 
                                           list="category-list" placeholder="เช่น ห้องประชุม, อุปกรณ์">
                                    <datalist id="category-list">
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo esc_attr($cat); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                
                                <div class="psu-form-group">
                                    <label for="price" class="psu-label required">ราคา (บาท/ชั่วโมง)</label>
                                    <input type="number" id="price" name="price" step="0.01" min="0" class="psu-input" 
                                           value="<?php echo $edit_service ? $edit_service->price : '0'; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- คอลัมน์ขวา -->
                        <div class="psu-form-column">
                            <div class="psu-form-group">
                                <label class="psu-label">ประเภทการจอง</label>
                                <div class="psu-checkbox-group">
                                    <label class="psu-checkbox-label">
                                        <input type="checkbox" name="timeslot_type[]" value="hourly" 
                                               <?php checked(in_array('hourly', $selected_timeslot_types)); ?> >
                                        <span class="checkmark"></span>
                                        รายชั่วโมง
                                    </label>
                                    <label class="psu-checkbox-label">
                                        <input type="checkbox" name="timeslot_type[]" value="morning_afternoon" 
                                               <?php checked(in_array('morning_afternoon', $selected_timeslot_types)); ?>>
                                        <span class="checkmark"></span>
                                        ครึ่งวัน (เช้า/บ่าย)
                                    </label>
                                    <label class="psu-checkbox-label">
                                        <input type="checkbox" name="timeslot_type[]" value="full_day" 
                                               <?php checked(in_array('full_day', $selected_timeslot_types)); ?>>
                                        <span class="checkmark"></span>
                                        เต็มวัน
                                    </label>
                                </div>
                            </div>
                            
                            <div class="psu-form-group" id="timeslot_duration_row">
                                <label for="timeslot_duration" class="psu-label">ระยะเวลาต่อช่วง (นาที)</label>
                                <select id="timeslot_duration" name="timeslot_duration" class="psu-select">
                                    <option value="30" <?php selected($edit_service ? $edit_service->timeslot_duration : 60, 30); ?>>30 นาที</option>
                                    <option value="60" <?php selected($edit_service ? $edit_service->timeslot_duration : 60, 60); ?>>1 ชั่วโมง</option>
                                    <option value="120" <?php selected($edit_service ? $edit_service->timeslot_duration : 60, 120); ?>>2 ชั่วโมง</option>
                                    <option value="180" <?php selected($edit_service ? $edit_service->timeslot_duration : 60, 180); ?>>3 ชั่วโมง</option>
                                </select>
                            </div>
                            
                            <div class="psu-form-group">
                                <label class="psu-label">เวลาทำการ</label>
                                <div class="psu-time-group">
                                    <div class="psu-time-field">
                                        <label for="available_start_time">เปิด</label>
                                        <input type="time" id="available_start_time" name="available_start_time" class="psu-input"
                                               value="<?php echo $edit_service ? $edit_service->available_start_time : '09:00'; ?>">
                                    </div>
                                    <div class="psu-time-field">
                                        <label for="available_end_time">ปิด</label>
                                        <input type="time" id="available_end_time" name="available_end_time" class="psu-input"
                                               value="<?php echo $edit_service ? $edit_service->available_end_time : '16:00'; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="psu-form-group">
                                <label class="psu-label">เวลาพัก</label>
                                <div class="psu-time-group">
                                    <div class="psu-time-field">
                                        <label for="break_start_time">เริ่ม</label>
                                        <input type="time" id="break_start_time" name="break_start_time" class="psu-input"
                                               value="<?php echo $edit_service ? $edit_service->break_start_time : '12:00'; ?>">
                                    </div>
                                    <div class="psu-time-field">
                                        <label for="break_end_time">สิ้นสุด</label>
                                        <input type="time" id="break_end_time" name="break_end_time" class="psu-input"
                                               value="<?php echo $edit_service ? $edit_service->break_end_time : '13:00'; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="psu-form-group">
                                <label class="psu-label">วันทำการ</label>
                                <div class="psu-checkbox-group psu-days-group">
                                    <?php 
                                    $days = array(
                                        '1' => 'จันทร์',
                                        '2' => 'อังคาร', 
                                        '3' => 'พุธ',
                                        '4' => 'พฤหัสบดี',
                                        '5' => 'ศุกร์',
                                        '6' => 'เสาร์',
                                        '0' => 'อาทิตย์'
                                    );
                                    foreach ($days as $day_num => $day_name): ?>
                                        <label class="psu-checkbox-label psu-day-label">
                                            <input type="checkbox" name="working_days[]" value="<?php echo $day_num; ?>" 
                                                   <?php checked(in_array($day_num, $selected_working_days)); ?>>
                                            <span class="checkmark"></span>
                                            <?php echo $day_name; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ส่วนล่าง -->
                    <div class="psu-form-bottom">
                        <div class="psu-form-group">
                            <label for="payment_info" class="psu-label">ข้อมูลการชำระเงิน</label>
                            <textarea id="payment_info" name="payment_info" rows="3" class="psu-textarea" 
                                      placeholder="วิธีการชำระเงิน เช่น เงินสด โอนเงิน บัตรเครดิต"><?php echo $edit_service ? esc_textarea($edit_service->payment_info) : ''; ?></textarea>
                        </div>
                        
                        <div class="psu-form-row">
                            <div class="psu-form-group">
                                <label for="manager_name" class="psu-label">ผู้รับผิดชอบ</label>
                                <input type="text" id="manager_name" name="manager_name" class="psu-input" 
                                       value="<?php echo $edit_service ? esc_attr($edit_service->manager_name) : (function_exists('get_current_user_id') && function_exists('get_userdata') ? get_userdata(get_current_user_id())->display_name : 'ผู้ดูแลระบบ'); ?>">
                            </div>
                            
                            <div class="psu-form-group">
                                <div class="psu-checkbox-group">
                                    <label class="psu-checkbox-label">
                                        <input type="checkbox" name="auto_approve" value="1" 
                                               <?php checked($edit_service ? $edit_service->auto_approve : false); ?>>
                                        <span class="checkmark"></span>
                                        อนุมัติอัตโนมัติ
                                    </label>
                                    <label class="psu-checkbox-label">
                                        <input type="checkbox" name="status" value="1" 
                                               <?php checked($edit_service ? $edit_service->status : true); ?>>
                                        <span class="checkmark"></span>
                                        เปิดใช้งาน
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="psu-form-actions">
                        <input type="submit" name="save_service" value="<?php echo $edit_service ? 'อัปเดตบริการ' : 'เพิ่มบริการ'; ?>" class="button button-primary button-large">
                        <button type="button" class="button button-large" onclick="toggleServiceForm()">ยกเลิก</button>
                    </div>
                </form>
                
                <!-- Minimal JavaScript - ไม่ขัดขวาง form submission -->
                <script>
                console.log('🔧 Services page loaded - NO form interception');
                
                // เช็คเฉพาะว่า form มีอยู่หรือไม่
                document.addEventListener('DOMContentLoaded', function() {
                    const form = document.querySelector('.psu-service-form');
                    if (form) {
                        console.log('✅ Found service form, allowing native submission');
                    } else {
                        console.log('❌ Service form not found');
                    }
                });
                </script>
            </div>
        </div>

        <!-- รายการบริการ -->
        <div class="psu-list-section" id="services-list">
            <div class="psu-list-header">
                <h2>รายการบริการทั้งหมด</h2>
                <div>
                    <button class="button" onclick="testFormSubmission()">🧪 ทดสอบ Form</button>
                    <button class="button button-primary" onclick="toggleServiceForm()">เพิ่มบริการใหม่</button>
                </div>
            </div>
            
            <script>
            function testFormSubmission() {
                console.log('🧪 Testing form submission...');
                const form = document.querySelector('.psu-service-form');
                if (form) {
                    // แสดงฟอร์มก่อน
                    document.getElementById('service-form').style.display = 'block';
                    document.getElementById('services-list').style.display = 'none';
                    
                    // กรอกข้อมูลทดสอบ
                    setTimeout(() => {
                        form.querySelector('input[name="name"]').value = 'ทดสอบบริการ ' + Date.now();
                        form.querySelector('input[name="price"]').value = '100';
                        
                        // เลือก checkbox
                        form.querySelector('input[name="timeslot_type[]"][value="hourly"]').checked = true;
                        form.querySelector('input[name="working_days[]"][value="1"]').checked = true;
                        
                        console.log('✅ Test data filled');
                        alert('✅ กรอกข้อมูลทดสอบแล้ว กรุณาคลิก "เพิ่มบริการ" เพื่อ submit');
                    }, 500);
                } else {
                    console.log('❌ Form not found');
                    alert('ไม่พบฟอร์ม');
                }
            }
            </script>
            
            <?php if (empty($services)): ?>
                <div class="psu-empty-state">
                    <p>ยังไม่มีบริการในระบบ</p>
                    <button class="button button-primary" onclick="toggleServiceForm()">เพิ่มบริการแรก</button>
                </div>
            <?php else: ?>
                <div class="psu-services-grid">
                    <?php foreach ($services as $service): ?>
                        <div class="psu-service-card">
                            <?php if ($service->image_url): ?>
                                <div class="psu-service-image">
                                    <img src="<?php echo esc_url($service->image_url); ?>" alt="<?php echo esc_attr($service->name); ?>">
                                </div>
                            <?php endif; ?>
                            
                            <div class="psu-service-content">
                                <div class="psu-service-header">
                                    <h3><?php echo esc_html($service->name); ?></h3>
                                    <span class="psu-service-status <?php echo $service->status ? 'active' : 'inactive'; ?>">
                                        <?php echo $service->status ? 'เปิดใช้งาน' : 'ปิดใช้งาน'; ?>
                                    </span>
                                </div>
                                
                                <?php if ($service->category): ?>
                                    <div class="psu-service-category"><?php echo esc_html($service->category); ?></div>
                                <?php endif; ?>
                                
                                <?php if ($service->description): ?>
                                    <p class="psu-service-description"><?php echo esc_html(wp_trim_words($service->description, 20)); ?></p>
                                <?php endif; ?>
                                
                                <div class="psu-service-details">
                                    <div class="psu-service-price">฿<?php echo number_format($service->price, 2); ?>/ชั่วโมง</div>
                                    <div class="psu-service-time">
                                        <?php echo date('H:i', strtotime($service->available_start_time)); ?> - 
                                        <?php echo date('H:i', strtotime($service->available_end_time)); ?>
                                    </div>
                                </div>
                                
                                <div class="psu-service-types">
                                    <?php 
                                    $types = explode(',', $service->timeslot_type);
                                    $type_labels = array(
                                        'hourly' => 'รายชั่วโมง',
                                        'morning_afternoon' => 'ครึ่งวัน',
                                        'full_day' => 'เต็มวัน'
                                    );
                                    foreach ($types as $type) {
                                        if (isset($type_labels[$type])) {
                                            echo '<span class="psu-type-badge">' . $type_labels[$type] . '</span>';
                                        }
                                    }
                                    ?>
                                </div>
                                
                                <div class="psu-service-actions">
                                    <a href="?page=psu-booking-services&action=edit&service_id=<?php echo $service->id; ?>" 
                                       class="button button-small">แก้ไข</a>
                                    <a href="<?php echo wp_nonce_url('?page=psu-booking-services&action=delete&service_id=' . $service->id, 'delete_service_' . $service->id); ?>" 
                                       class="button button-small button-link-delete"
                                       onclick="return confirm('คุณต้องการลบบริการ <?php echo esc_js($service->name); ?> หรือไม่?');">ลบ</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.psu-admin-container {
    margin: 20px 0;
}

.psu-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.psu-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #eee;
}

.psu-card-header h2 {
    margin: 0;
    color: #2B3F6A;
}

.psu-service-form {
    padding: 20px;
}

.psu-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 20px;
}

.psu-form-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.psu-form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.psu-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.psu-label {
    font-weight: 600;
    color: #1d2327;
}

.psu-label.required::after {
    content: ' *';
    color: #d63638;
}

.psu-input, .psu-textarea, .psu-select {
    padding: 8px 12px;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.psu-input:focus, .psu-textarea:focus, .psu-select:focus {
    border-color: #2B3F6A;
    box-shadow: 0 0 0 2px rgba(43, 63, 106, 0.1);
    outline: none;
}

.psu-checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.psu-days-group {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 10px;
}

.psu-checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    user-select: none;
}

.psu-checkbox-label input[type="checkbox"] {
    margin: 0;
}

.psu-time-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.psu-time-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.psu-time-field label {
    font-size: 12px;
    color: #50575e;
    font-weight: 500;
}

.psu-image-upload {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.psu-upload-btn {
    align-self: flex-start;
}

.psu-image-preview {
    max-width: 200px;
}

.psu-image-preview img {
    width: 100%;
    height: auto;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.psu-form-bottom {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 20px;
}

.psu-form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.psu-list-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.psu-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

.psu-services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.psu-service-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    overflow: hidden;
    transition: box-shadow 0.2s ease;
}

.psu-service-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.psu-service-image {
    height: 150px;
    overflow: hidden;
}

.psu-service-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.psu-service-content {
    padding: 15px;
}

.psu-service-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.psu-service-header h3 {
    margin: 0;
    color: #1d2327;
    font-size: 16px;
}

.psu-service-status {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.psu-service-status.active {
    background: #d1e7dd;
    color: #0f5132;
}

.psu-service-status.inactive {
    background: #f8d7da;
    color: #721c24;
}

.psu-service-category {
    color: #2B3F6A;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 8px;
}

.psu-service-description {
    color: #50575e;
    font-size: 13px;
    line-height: 1.4;
    margin-bottom: 15px;
}

.psu-service-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.psu-service-price {
    font-weight: 600;
    color: #2B3F6A;
    font-size: 14px;
}

.psu-service-time {
    font-size: 12px;
    color: #50575e;
}

.psu-service-types {
    display: flex;
    gap: 5px;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.psu-type-badge {
    padding: 2px 8px;
    background: #e8f4fd;
    color: #2B3F6A;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.psu-service-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

@media (max-width: 768px) {
    .psu-form-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .psu-form-row {
        grid-template-columns: 1fr;
    }
    
    .psu-time-group {
        grid-template-columns: 1fr;
    }
    
    .psu-services-grid {
        grid-template-columns: 1fr;
    }
    
    .psu-list-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .psu-service-header {
        flex-direction: column;
        gap: 8px;
    }
    
    .psu-form-actions {
        flex-direction: column;
    }
}
</style>

<script>
function openMediaLibrary() {
    if (typeof wp !== 'undefined' && wp.media) {
        const mediaUploader = wp.media({
            title: 'เลือกรูปภาพสำหรับบริการ',
            button: {
                text: 'เลือกรูปนี้'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            document.getElementById('image_url').value = attachment.url;
            
            // แสดงตัวอย่างรูป
            const preview = document.getElementById('image-preview');
            preview.innerHTML = '<img src="' + attachment.url + '" alt="Preview">';
        });

        mediaUploader.open();
    } else {
        alert('WordPress Media Library ไม่พร้อมใช้งาน');
    }
}

// ตรวจสอบการเลือกประเภทการจอง
document.addEventListener('DOMContentLoaded', function() {
    const hourlyCheckbox = document.querySelector('input[name="timeslot_type[]"][value="hourly"]');
    const durationRow = document.getElementById('timeslot_duration_row');
    
    function toggleDurationField() {
        if (hourlyCheckbox && hourlyCheckbox.checked) {
            durationRow.style.display = 'block';
        } else {
            durationRow.style.display = 'none';
        }
    }
    
    if (hourlyCheckbox) {
        hourlyCheckbox.addEventListener('change', toggleDurationField);
        toggleDurationField(); // เรียกใช้ครั้งแรก
    }
});
</script>