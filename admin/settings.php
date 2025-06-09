<?php
// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// จัดการการบันทึกการตั้งค่า
if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['psu_settings_nonce'], 'psu_save_settings')) {
    global $wpdb;
    
    $plugin_instance = new PSU_Simple_Booking();
    
    // บันทึกการตั้งค่าข้อความในหน้าเว็บ
    if (isset($_POST['frontend_texts'])) {
        $frontend_texts = array();
        foreach ($_POST['frontend_texts'] as $key => $value) {
            $frontend_texts[$key] = sanitize_text_field($value);
        }
        $plugin_instance->save_setting('frontend_texts', json_encode($frontend_texts));
    }
    
    // บันทึกการตั้งค่าอีเมล
    if (isset($_POST['email_notifications'])) {
        $email_settings = array();
        
        // การแจ้งเตือนผู้ใช้เมื่อจองสำเร็จ
        $email_settings['user_booking_created'] = array(
            'enabled' => isset($_POST['email_notifications']['user_booking_created']['enabled']),
            'subject' => sanitize_text_field($_POST['email_notifications']['user_booking_created']['subject']),
            'message' => sanitize_textarea_field($_POST['email_notifications']['user_booking_created']['message'])
        );
        
        // การแจ้งเตือนแอดมินเมื่อมีการจองใหม่
        $email_settings['admin_new_booking'] = array(
            'enabled' => isset($_POST['email_notifications']['admin_new_booking']['enabled']),
            'subject' => sanitize_text_field($_POST['email_notifications']['admin_new_booking']['subject']),
            'message' => sanitize_textarea_field($_POST['email_notifications']['admin_new_booking']['message'])
        );
        
        // การแจ้งเตือนเมื่อสถานะเปลี่ยน
        $email_settings['user_status_changed'] = array(
            'enabled' => isset($_POST['email_notifications']['user_status_changed']['enabled']),
            'subject' => sanitize_text_field($_POST['email_notifications']['user_status_changed']['subject']),
            'message' => sanitize_textarea_field($_POST['email_notifications']['user_status_changed']['message'])
        );
        
        $plugin_instance->save_setting('email_notifications', json_encode($email_settings));
    }
    
    // บันทึกการตั้งค่าทั่วไป
    if (isset($_POST['general_settings'])) {
        $general_settings = array(
            'booking_advance_days' => intval($_POST['general_settings']['booking_advance_days']),
            'max_bookings_per_user' => intval($_POST['general_settings']['max_bookings_per_user']),
            'require_approval' => isset($_POST['general_settings']['require_approval']),
            'allow_cancellation' => isset($_POST['general_settings']['allow_cancellation']),
            'cancellation_hours' => intval($_POST['general_settings']['cancellation_hours']),
            'admin_email' => sanitize_email($_POST['general_settings']['admin_email'])
        );
        
        $plugin_instance->save_setting('general_settings', json_encode($general_settings));
    }
    
    echo '<div class="notice notice-success"><p>บันทึกการตั้งค่าสำเร็จ!</p></div>';
}

// ดึงการตั้งค่าปัจจุบัน
$plugin_instance = new PSU_Simple_Booking();

$frontend_texts = json_decode($plugin_instance->get_setting('frontend_texts'), true) ?: array();
$email_notifications = json_decode($plugin_instance->get_setting('email_notifications'), true) ?: array();
$general_settings = json_decode($plugin_instance->get_setting('general_settings'), true) ?: array();

// ค่าเริ่มต้น
$default_frontend_texts = array(
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
);

$default_email_notifications = array(
    'user_booking_created' => array(
        'enabled' => true,
        'subject' => 'ยืนยันการจอง - {service_name}',
        'message' => 'สวัสดีครับ/ค่ะ {customer_name},

ขอขอบคุณที่ใช้บริการของเราในการจอง {service_name}

📅 วันที่: {booking_date}
🕒 เวลา: {start_time} – {end_time}
📌 สถานะการจอง: {status}'
    ),
    'admin_new_booking' => array(
        'enabled' => true,
        'subject' => 'มีการจองใหม่ - {service_name}',
        'message' => 'แจ้งเตือนการจองใหม่

📌 บริการ: {service_name}
👤 ผู้จอง: {customer_name}
📅 วันที่: {booking_date}
🕒 เวลา: {start_time} – {end_time}

กรุณาตรวจสอบรายละเอียดการจองในระบบโดยเร็ว'
    ),
    'user_status_changed' => array(
        'enabled' => true,
        'subject' => 'อัปเดตสถานะการจอง - {service_name}',
        'message' => 'สวัสดีครับ/ค่ะ {customer_name},

สถานะการจองบริการ {service_name} ของคุณได้มีการเปลี่ยนแปลงเป็น: {status}

📅 วันที่: {booking_date}
🕒 เวลา: {start_time} – {end_time}'
    )
);

$default_general_settings = array(
    'booking_advance_days' => 365,
    'max_bookings_per_user' => 0,
    'require_approval' => true,
    'allow_cancellation' => true,
    'cancellation_hours' => 24,
    'admin_email' => get_option('admin_email')
);

// ผสานกับค่าเริ่มต้น
$frontend_texts = array_merge($default_frontend_texts, $frontend_texts);
$email_notifications = array_merge($default_email_notifications, $email_notifications);
$general_settings = array_merge($default_general_settings, $general_settings);

?>

<div class="wrap">
    <h1>การตั้งค่าระบบจอง</h1>

    <form method="post" action="">
        <?php wp_nonce_field('psu_save_settings', 'psu_settings_nonce'); ?>
        
        <div class="psu-settings-tabs">
            <h2 class="nav-tab-wrapper">
                <a href="#general" class="nav-tab nav-tab-active" onclick="switchTab(event, 'general')">การตั้งค่าทั่วไป</a>
                <a href="#frontend" class="nav-tab" onclick="switchTab(event, 'frontend')">ข้อความหน้าเว็บ</a>
                <a href="#email" class="nav-tab" onclick="switchTab(event, 'email')">การแจ้งเตือนอีเมล</a>
                <a href="#shortcodes" class="nav-tab" onclick="switchTab(event, 'shortcodes')">Shortcodes</a>
            </h2>

            <!-- แท็บการตั้งค่าทั่วไป -->
            <div id="general-tab" class="psu-tab-content">
                <h3>การตั้งค่าทั่วไป</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="booking_advance_days">จำนวนวันล่วงหน้าที่อนุญาตให้จอง</label></th>
                        <td>
                            <input type="number" id="booking_advance_days" name="general_settings[booking_advance_days]" 
                                   value="<?php echo esc_attr($general_settings['booking_advance_days']); ?>" min="1" max="3650" class="small-text">
                            <span class="description">วัน (สูงสุด 10 ปี)</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="max_bookings_per_user">จำนวนการจองสูงสุดต่อผู้ใช้</label></th>
                        <td>
                            <input type="number" id="max_bookings_per_user" name="general_settings[max_bookings_per_user]" 
                                   value="<?php echo esc_attr($general_settings['max_bookings_per_user']); ?>" min="0" max="100" class="small-text">
                            <span class="description">การจอง (0 = ไม่จำกัด)</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">การอนุมัติ</th>
                        <td>
                            <label>
                                <input type="checkbox" name="general_settings[require_approval]" value="1" 
                                       <?php checked($general_settings['require_approval']); ?>>
                                ต้องการอนุมัติการจองจากแอดมิน
                            </label>
                            <p class="description">หากไม่เลือก การจองจะได้รับการอนุมัติอัตโนมัติ</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">การยกเลิกการจอง</th>
                        <td>
                            <label>
                                <input type="checkbox" name="general_settings[allow_cancellation]" value="1" 
                                       <?php checked($general_settings['allow_cancellation']); ?>>
                                อนุญาตให้ผู้ใช้ยกเลิกการจองได้
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="cancellation_hours">ระยะเวลาล่วงหน้าในการยกเลิก</label></th>
                        <td>
                            <input type="number" id="cancellation_hours" name="general_settings[cancellation_hours]" 
                                   value="<?php echo esc_attr($general_settings['cancellation_hours']); ?>" min="1" max="168" class="small-text">
                            <span class="description">ชั่วโมง (ก่อนเวลาที่จอง)</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="admin_email">อีเมลแอดมิน</label></th>
                        <td>
                            <input type="email" id="admin_email" name="general_settings[admin_email]" 
                                   value="<?php echo esc_attr($general_settings['admin_email']); ?>" class="regular-text">
                            <p class="description">อีเมลที่จะรับการแจ้งเตือนการจองใหม่</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- แท็บข้อความหน้าเว็บ -->
            <div id="frontend-tab" class="psu-tab-content" style="display: none;">
                <h3>ข้อความที่แสดงในหน้าเว็บ</h3>
                <p>ปรับแต่งข้อความต่างๆ ที่แสดงในฟอร์มการจอง</p>
                
                <table class="form-table">
                    <?php foreach ($frontend_texts as $key => $value): ?>
                        <tr>
                            <th scope="row">
                                <label for="frontend_text_<?php echo $key; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $key)); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text" id="frontend_text_<?php echo $key; ?>" 
                                       name="frontend_texts[<?php echo $key; ?>]" 
                                       value="<?php echo esc_attr($value); ?>" class="regular-text">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                
                <div class="psu-note">
                    <h4>หมายเหตุ:</h4>
                    <p>การเปลี่ยนแปลงข้อความเหล่านี้จะมีผลกับฟอร์มการจองทั้งหมดในเว็บไซต์</p>
                </div>
            </div>

            <!-- แท็บการแจ้งเตือนอีเมล -->
            <div id="email-tab" class="psu-tab-content" style="display: none;">
                <h3>การแจ้งเตือนทางอีเมล</h3>
                
                <!-- อีเมลยืนยันการจองสำหรับผู้ใช้ -->
                <div class="psu-email-setting">
                    <h4>อีเมลยืนยันการจองสำหรับผู้ใช้</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row">เปิดใช้งาน</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="email_notifications[user_booking_created][enabled]" value="1" 
                                           <?php checked($email_notifications['user_booking_created']['enabled'] ?? false); ?>>
                                    ส่งอีเมลยืนยันให้ผู้ใช้เมื่อทำการจอง
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="user_booking_subject">หัวข้ออีเมล</label></th>
                            <td>
                                <input type="text" id="user_booking_subject" 
                                       name="email_notifications[user_booking_created][subject]" 
                                       value="<?php echo esc_attr($email_notifications['user_booking_created']['subject'] ?? ''); ?>" 
                                       class="large-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="user_booking_message">เนื้อหาอีเมล</label></th>
                            <td>
                                <textarea id="user_booking_message" 
                                          name="email_notifications[user_booking_created][message]" 
                                          rows="6" class="large-text"><?php echo esc_textarea($email_notifications['user_booking_created']['message'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- อีเมลแจ้งเตือนแอดมินการจองใหม่ -->
                <div class="psu-email-setting">
                    <h4>อีเมลแจ้งเตือนแอดมินการจองใหม่</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row">เปิดใช้งาน</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="email_notifications[admin_new_booking][enabled]" value="1" 
                                           <?php checked($email_notifications['admin_new_booking']['enabled'] ?? false); ?>>
                                    ส่งอีเมลแจ้งเตือนแอดมินเมื่อมีการจองใหม่
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="admin_booking_subject">หัวข้ออีเมล</label></th>
                            <td>
                                <input type="text" id="admin_booking_subject" 
                                       name="email_notifications[admin_new_booking][subject]" 
                                       value="<?php echo esc_attr($email_notifications['admin_new_booking']['subject'] ?? ''); ?>" 
                                       class="large-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="admin_booking_message">เนื้อหาอีเมล</label></th>
                            <td>
                                <textarea id="admin_booking_message" 
                                          name="email_notifications[admin_new_booking][message]" 
                                          rows="6" class="large-text"><?php echo esc_textarea($email_notifications['admin_new_booking']['message'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- อีเมลแจ้งเตือนการเปลี่ยนสถานะ -->
                <div class="psu-email-setting">
                    <h4>อีเมลแจ้งเตือนการเปลี่ยนสถานะ</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row">เปิดใช้งาน</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="email_notifications[user_status_changed][enabled]" value="1" 
                                           <?php checked($email_notifications['user_status_changed']['enabled'] ?? false); ?>>
                                    ส่งอีเมลแจ้งเตือนผู้ใช้เมื่อสถานะการจองเปลี่ยน
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="status_changed_subject">หัวข้ออีเมล</label></th>
                            <td>
                                <input type="text" id="status_changed_subject" 
                                       name="email_notifications[user_status_changed][subject]" 
                                       value="<?php echo esc_attr($email_notifications['user_status_changed']['subject'] ?? ''); ?>" 
                                       class="large-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="status_changed_message">เนื้อหาอีเมล</label></th>
                            <td>
                                <textarea id="status_changed_message" 
                                          name="email_notifications[user_status_changed][message]" 
                                          rows="6" class="large-text"><?php echo esc_textarea($email_notifications['user_status_changed']['message'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="psu-note">
                    <h4>ตัวแปรที่สามารถใช้ได้ในอีเมล:</h4>
                    <ul>
                        <li><code>{customer_name}</code> - ชื่อผู้จอง</li>
                        <li><code>{customer_email}</code> - อีเมลผู้จอง</li>
                        <li><code>{service_name}</code> - ชื่อบริการ</li>
                        <li><code>{booking_date}</code> - วันที่จอง</li>
                        <li><code>{start_time}</code> - เวลาเริ่มต้น</li>
                        <li><code>{end_time}</code> - เวลาสิ้นสุด</li>
                        <li><code>{status}</code> - สถานะการจอง</li>
                        <li><code>{total_price}</code> - ราคารวม</li>
                    </ul>
                </div>
            </div>

            <!-- แท็บ Shortcodes -->
            <div id="shortcodes-tab" class="psu-tab-content" style="display: none;">
                <h3>Shortcodes ที่ใช้ได้</h3>
                <p>คุณสามารถใช้ shortcodes เหล่านี้ในหน้าหรือโพสต์ของคุณ</p>
                
                <div class="psu-shortcode-list">
                    <div class="psu-shortcode-item">
                        <h4>ฟอร์มการจอง</h4>
                        <div class="psu-shortcode-code">
                            <code>[psu_booking_form]</code>
                            <button type="button" class="button button-small" onclick="copyToClipboard('[psu_booking_form]')">คัดลอก</button>
                        </div>
                        <p class="description">แสดงฟอร์มการจองทั้งหมด</p>
                    </div>
                    
                    <div class="psu-shortcode-item">
                        <h4>ประวัติการจอง</h4>
                        <div class="psu-shortcode-code">
                            <code>[psu_booking_history]</code>
                            <button type="button" class="button button-small" onclick="copyToClipboard('[psu_booking_history]')">คัดลอก</button>
                        </div>
                        <p class="description">แสดงประวัติการจองของผู้ใช้ที่เข้าสู่ระบบ (ต้องเข้าสู่ระบบ)</p>
                    </div>
                </div>
                
                <div class="psu-note">
                    <h4>วิธีใช้งาน:</h4>
                    <ol>
                        <li>คัดลอก shortcode ที่ต้องการ</li>
                        <li>ไปยังหน้าหรือโพสต์ที่ต้องการแสดง</li>
                        <li>วาง shortcode ในเนื้อหา</li>
                        <li>บันทึกและดูผลลัพธ์</li>
                    </ol>
                </div>
            </div>
        </div>

        <p class="submit">
            <input type="submit" name="save_settings" class="button-primary" value="บันทึกการตั้งค่า">
            <button type="button" class="button" onclick="resetToDefaults()">คืนค่าเริ่มต้น</button>
        </p>
    </form>
</div>

<script>
function switchTab(evt, tabName) {
    var i, tabcontent, tablinks;
    
    // ซ่อนเนื้อหาแท็บทั้งหมด
    tabcontent = document.getElementsByClassName("psu-tab-content");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    
    // ลบคลาส active จากแท็บทั้งหมด
    tablinks = document.getElementsByClassName("nav-tab");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].classList.remove("nav-tab-active");
    }
    
    // แสดงแท็บที่เลือกและเพิ่มคลาส active
    document.getElementById(tabName + "-tab").style.display = "block";
    evt.currentTarget.classList.add("nav-tab-active");
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert('คัดลอก shortcode สำเร็จ: ' + text);
    }, function(err) {
        alert('ไม่สามารถคัดลอกได้: ' + err);
    });
}

function resetToDefaults() {
    if (confirm('คุณต้องการคืนค่าเริ่มต้นหรือไม่? การเปลี่ยนแปลงที่ยังไม่ได้บันทึกจะหายไป')) {
        // TODO: Reset to default values
        location.reload();
    }
}

// เริ่มต้นด้วยแท็บแรก
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('general-tab').style.display = 'block';
});
</script>