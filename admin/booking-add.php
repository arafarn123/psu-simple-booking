<?php
// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_services = $wpdb->prefix . 'psu_services';

// === จัดการการสร้างการจองใหม่ ===
if (isset($_POST['psu_create_booking']) && wp_verify_nonce($_POST['psu_booking_add_nonce'], 'psu_create_booking_nonce')) {
    
    $service_id = intval($_POST['service_id']);
    $customer_name = sanitize_text_field($_POST['customer_name']);
    $customer_email = sanitize_email($_POST['customer_email']);
    $customer_phone = sanitize_text_field($_POST['customer_phone']);
    $booking_date = sanitize_text_field($_POST['booking_date']);
    $start_time = sanitize_text_field($_POST['start_time']);
    $end_time = sanitize_text_field($_POST['end_time']);
    $total_price = floatval($_POST['total_price']);
    $status = sanitize_text_field($_POST['status']);
    $admin_notes = sanitize_textarea_field($_POST['admin_notes']);

    // ตรวจสอบข้อมูลที่จำเป็น
    $errors = array();
    if (empty($service_id)) $errors[] = 'กรุณาเลือกบริการ';
    if (empty($customer_name)) $errors[] = 'กรุณาใส่ชื่อผู้จอง';
    if (empty($customer_email) || !is_email($customer_email)) $errors[] = 'กรุณาใส่อีเมลที่ถูกต้อง';
    if (empty($booking_date)) $errors[] = 'กรุณาเลือกวันที่จอง';
    if (empty($start_time)) $errors[] = 'กรุณาเลือกเวลาเริ่ม';
    if (empty($end_time)) $errors[] = 'กรุณาเลือกเวลาสิ้นสุด';
    
    // ตรวจสอบวันที่ไม่ให้เป็นอดีต
    if (!empty($booking_date) && $booking_date < date('Y-m-d')) {
        $errors[] = 'ไม่สามารถจองในวันที่ผ่านมาแล้ว';
    }
    
    // ตรวจสอบเวลาเริ่มต้องน้อยกว่าเวลาสิ้นสุด
    if (!empty($start_time) && !empty($end_time) && $start_time >= $end_time) {
        $errors[] = 'เวลาเริ่มต้องน้อยกว่าเวลาสิ้นสุด';
    }

    if (empty($errors)) {
        // ตรวจสอบความชนกันของการจอง
        $existing_booking = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings 
             WHERE service_id = %d AND booking_date = %s 
             AND status IN ('approved', 'pending')
             AND (
                 (start_time < %s AND end_time > %s) OR
                 (start_time < %s AND end_time > %s) OR
                 (start_time >= %s AND end_time <= %s)
             )",
            $service_id, $booking_date,
            $end_time, $start_time,
            $end_time, $end_time,
            $start_time, $end_time
        ));
        
        if ($existing_booking > 0) {
            $errors[] = 'มีการจองในช่วงเวลานี้แล้ว กรุณาเลือกช่วงเวลาอื่น';
        }
        
        // เตรียม custom fields data
        $form_data = array('custom_fields' => array());
        if (isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
            foreach ($_POST['custom_fields'] as $key => $value) {
                $form_data['custom_fields'][$key] = sanitize_text_field($value);
            }
        }

        $booking_data = array(
            'service_id' => $service_id,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'booking_date' => $booking_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'total_price' => $total_price,
            'status' => $status,
            'admin_notes' => $admin_notes,
            'form_data' => json_encode($form_data),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $result = $wpdb->insert($wpdb->prefix . 'psu_bookings', $booking_data);
        
        if ($result !== false) {
            $booking_id = $wpdb->insert_id;
            echo '<div class="notice notice-success is-dismissible"><p>สร้างการจองสำเร็จ! รหัสจอง: #' . str_pad($booking_id, 6, '0', STR_PAD_LEFT) . '</p></div>';
            
            // ส่ง hook สำหรับการแจ้งเตือน
            do_action('psu_booking_created', $booking_id, $booking_data);
            
            // รีไดเรกต์ไปหน้ารายการ
            echo '<script>setTimeout(function(){ window.location.href = "?page=psu-booking-bookings"; }, 2000);</script>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>เกิดข้อผิดพลาดในการสร้างการจอง</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . implode('<br>', $errors) . '</p></div>';
    }
}

// ดึงข้อมูลบริการ
$services = $wpdb->get_results("SELECT * FROM $table_services WHERE status = 1 ORDER BY name");

?>
<div class="wrap">
    <h1>
        เพิ่มการจองใหม่
        <a href="?page=psu-booking-bookings" class="page-title-action">&larr; กลับไปที่รายการ</a>
    </h1>

    <form method="post" action="" id="add-booking-form">
        <?php wp_nonce_field('psu_create_booking_nonce', 'psu_booking_add_nonce'); ?>

        <div class="psu-booking-add-grid">
            <!-- Left Column -->
            <div class="psu-booking-add-left">
                <div class="postbox">
                    <h2 class="hndle"><span>เลือกบริการ</span></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label for="service_id">บริการ <span class="required">*</span></label></th>
                                <td>
                                    <select id="service_id" name="service_id" class="regular-text" required onchange="loadServiceDetails()">
                                        <option value="">-- เลือกบริการ --</option>
                                        <?php foreach ($services as $service): ?>
                                            <option value="<?php echo $service->id; ?>" 
                                                    data-price="<?php echo $service->price; ?>"
                                                    data-duration="<?php echo $service->duration; ?>"
                                                    data-start-time="<?php echo $service->available_start_time; ?>"
                                                    data-end-time="<?php echo $service->available_end_time; ?>"
                                                    data-working-days="<?php echo $service->working_days; ?>">
                                                <?php echo esc_html($service->name); ?>
                                                <?php if ($service->category): ?>
                                                    (<?php echo esc_html($service->category); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="service-details" style="display: none; margin-top: 10px; padding: 10px; background-color: #f0f0f1; border-radius: 4px;">
                                        <p><strong>รายละเอียดบริการ:</strong></p>
                                        <div id="service-info"></div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle"><span>ข้อมูลผู้จอง</span></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label for="customer_name">ชื่อ-สกุล <span class="required">*</span></label></th>
                                <td><input type="text" id="customer_name" name="customer_name" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="customer_email">อีเมล <span class="required">*</span></label></th>
                                <td><input type="email" id="customer_email" name="customer_email" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label for="customer_phone">เบอร์โทรศัพท์</label></th>
                                <td><input type="text" id="customer_phone" name="customer_phone" class="regular-text"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox" id="custom-fields-section" style="display: none;">
                    <h2 class="hndle"><span>ข้อมูลเพิ่มเติม</span></h2>
                    <div class="inside">
                        <div id="custom-fields-container">
                            <!-- Custom fields จะถูกโหลดด้วย JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="psu-booking-add-right">
                <div class="postbox">
                    <h2 class="hndle"><span>วันที่และเวลา</span></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label for="booking_date">วันที่จอง <span class="required">*</span></label></th>
                                <td>
                                    <input type="date" id="booking_date" name="booking_date" required onchange="loadAvailableSlots()" min="<?php echo date('Y-m-d'); ?>">
                                    <p class="description">กรุณาเลือกวันที่ต้องการจอง (ตั้งแต่วันนี้เป็นต้นไป)</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="start_time">เวลาเริ่ม <span class="required">*</span></label></th>
                                <td>
                                    <select id="start_time" name="start_time" required onchange="updateEndTime()">
                                        <option value="">-- เลือกเวลาเริ่ม --</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="end_time">เวลาสิ้นสุด <span class="required">*</span></label></th>
                                <td>
                                    <input type="time" id="end_time" name="end_time" required onchange="calculatePrice()">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle"><span>ราคาและสถานะ</span></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label for="total_price">ราคา (บาท)</label></th>
                                <td>
                                    <input type="number" step="0.01" id="total_price" name="total_price" class="small-text" value="0.00">
                                    <p class="description">ราคาจะคำนวณอัตโนมัติเมื่อเลือกบริการและเวลา</p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="status">สถานะ</label></th>
                                <td>
                                    <select id="status" name="status">
                                        <option value="pending">รออนุมัติ</option>
                                        <option value="approved" selected>อนุมัติแล้ว</option>
                                        <option value="rejected">ถูกปฏิเสธ</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle"><span>บันทึกแอดมิน</span></h2>
                    <div class="inside">
                        <textarea name="admin_notes" rows="4" class="widefat" placeholder="บันทึกสำหรับผู้ดูแลระบบ (ไม่บังคับ)"></textarea>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle"><span>การจัดการ</span></h2>
                    <div class="inside">
                        <div class="submitbox">
                            <div id="major-publishing-actions">
                                <div id="publishing-action">
                                    <input type="button" onclick="previewBooking()" class="button button-secondary button-large" value="ตรวจสอบข้อมูล" style="margin-right: 10px;">
                                    <input type="submit" name="psu_create_booking" class="button button-primary button-large" value="สร้างการจอง">
                                </div>
                                <div class="clear"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.psu-booking-add-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.psu-booking-add-left,
.psu-booking-add-right {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.psu-booking-add-grid .postbox {
    margin-bottom: 0;
}

.psu-booking-add-grid .form-table th {
    width: 150px;
    padding-left: 0;
}

.psu-booking-add-grid .form-table td {
    padding-left: 10px;
}

.required {
    color: #d63638;
}

@media (max-width: 1024px) {
    .psu-booking-add-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}
</style>

<script>
let selectedService = null;
let customFields = [];

// โหลดรายละเอียดบริการ
function loadServiceDetails() {
    const serviceSelect = document.getElementById('service_id');
    const serviceDetailsDiv = document.getElementById('service-details');
    const serviceInfoDiv = document.getElementById('service-info');
    
    if (serviceSelect.value) {
        const option = serviceSelect.options[serviceSelect.selectedIndex];
        selectedService = {
            id: serviceSelect.value,
            price: parseFloat(option.dataset.price || 0),
            duration: parseInt(option.dataset.duration || 60),
            startTime: option.dataset.startTime,
            endTime: option.dataset.endTime,
            workingDays: option.dataset.workingDays.split(',')
        };
        
        serviceInfoDiv.innerHTML = `
            <p><strong>ราคา:</strong> ${selectedService.price.toFixed(2)} บาท</p>
            <p><strong>ระยะเวลา:</strong> ${selectedService.duration} นาที</p>
            <p><strong>เวลาทำการ:</strong> ${selectedService.startTime} - ${selectedService.endTime}</p>
        `;
        
        serviceDetailsDiv.style.display = 'block';
        
        // อัปเดตราคา
        document.getElementById('total_price').value = selectedService.price.toFixed(2);
        
        // โหลด custom fields
        loadCustomFields();
        
        // รีเซ็ตเวลา
        resetTimeSlots();
    } else {
        serviceDetailsDiv.style.display = 'none';
        selectedService = null;
        document.getElementById('total_price').value = '0.00';
        document.getElementById('custom-fields-section').style.display = 'none';
        resetTimeSlots();
    }
}

// โหลด custom fields
function loadCustomFields() {
    if (!selectedService) return;
    
    const customFieldsSection = document.getElementById('custom-fields-section');
    const customFieldsContainer = document.getElementById('custom-fields-container');
    
    // สำหรับตัวอย่าง เราจะแสดง custom fields section แต่ไม่มี fields
    // ในการใช้งานจริง ควรเรียก AJAX เพื่อดึง custom fields ของบริการนั้นๆ
    customFieldsSection.style.display = 'none';
}

// รีเซ็ต time slots
function resetTimeSlots() {
    const startTimeSelect = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    
    startTimeSelect.innerHTML = '<option value="">-- เลือกเวลาเริ่ม --</option>';
    endTimeInput.value = '';
}

// โหลด available slots
function loadAvailableSlots() {
    if (!selectedService) {
        alert('กรุณาเลือกบริการก่อน');
        return;
    }
    
    const dateInput = document.getElementById('booking_date');
    const startTimeSelect = document.getElementById('start_time');
    
    if (!dateInput.value) return;
    
    // เรียก AJAX เพื่อดึงช่วงเวลาที่ว่าง
    const formData = new FormData();
    formData.append('action', 'psu_check_available_timeslots');
    formData.append('nonce', '<?php echo wp_create_nonce('psu_admin_nonce'); ?>');
    formData.append('service_id', selectedService.id);
    formData.append('date', dateInput.value);
    
    // แสดงสถานะกำลังโหลด
    startTimeSelect.innerHTML = '<option value="">กำลังโหลด...</option>';
    
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            startTimeSelect.innerHTML = '<option value="">-- เลือกเวลาเริ่ม --</option>';
            
            if (data.data.length === 0) {
                startTimeSelect.innerHTML = '<option value="">ไม่มีช่วงเวลาว่าง</option>';
                alert('ไม่มีช่วงเวลาว่างสำหรับวันที่นี้');
            } else {
                data.data.forEach(slot => {
                    const option = document.createElement('option');
                    option.value = slot.time;
                    option.textContent = slot.label;
                    startTimeSelect.appendChild(option);
                });
            }
        } else {
            alert(data.data || 'เกิดข้อผิดพลาดในการโหลดช่วงเวลา');
            startTimeSelect.innerHTML = '<option value="">-- เลือกเวลาเริ่ม --</option>';
            dateInput.value = '';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        startTimeSelect.innerHTML = '<option value="">-- เลือกเวลาเริ่ม --</option>';
    });
}

// ฟังก์ชันตรวจสอบความถูกต้องของฟอร์ม
function validateForm() {
    const requiredFields = ['service_id', 'customer_name', 'customer_email', 'booking_date', 'start_time', 'end_time'];
    let isValid = true;
    let errorMessages = [];
    
    requiredFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (!field.value.trim()) {
            isValid = false;
            const label = document.querySelector(`label[for="${fieldName}"]`);
            const fieldLabel = label ? label.textContent.replace(' *', '') : fieldName;
            errorMessages.push(`กรุณากรอก${fieldLabel}`);
        }
    });
    
    // ตรวจสอบอีเมล
    const email = document.getElementById('customer_email').value;
    if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        isValid = false;
        errorMessages.push('รูปแบบอีเมลไม่ถูกต้อง');
    }
    
    if (!isValid) {
        alert(errorMessages.join('\n'));
    }
    
    return isValid;
}

// เพิ่มการตรวจสอบก่อนส่งฟอร์ม
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('add-booking-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
        });
    }
});

// อัปเดตเวลาสิ้นสุด
function updateEndTime() {
    if (!selectedService) return;
    
    const startTimeSelect = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    
    if (startTimeSelect.value) {
        const startTime = new Date(`2000-01-01T${startTimeSelect.value}`);
        startTime.setMinutes(startTime.getMinutes() + selectedService.duration);
        
        const endTimeString = startTime.toTimeString().substring(0, 5);
        endTimeInput.value = endTimeString;
        
        calculatePrice();
    }
}

// คำนวณราคา
function calculatePrice() {
    if (!selectedService) return;
    
    const startTimeSelect = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const priceInput = document.getElementById('total_price');
    
    if (startTimeSelect.value && endTimeInput.value) {
        // สำหรับตอนนี้ใช้ราคาพื้นฐานของบริการ
        // ในอนาคตอาจคำนวณตามระยะเวลาที่ใช้จริง
        priceInput.value = selectedService.price.toFixed(2);
    }
}

// แสดงตัวอย่างข้อมูลการจอง
function previewBooking() {
    if (!validateForm()) {
        return;
    }
    
    const serviceName = document.getElementById('service_id').options[document.getElementById('service_id').selectedIndex].text;
    const customerName = document.getElementById('customer_name').value;
    const customerEmail = document.getElementById('customer_email').value;
    const customerPhone = document.getElementById('customer_phone').value;
    const bookingDate = document.getElementById('booking_date').value;
    const startTime = document.getElementById('start_time').value;
    const endTime = document.getElementById('end_time').value;
    const totalPrice = document.getElementById('total_price').value;
    const status = document.getElementById('status').options[document.getElementById('status').selectedIndex].text;
    
    // แปลงวันที่เป็นภาษาไทย
    const thaiDate = new Date(bookingDate).toLocaleDateString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'long'
    });
    
    const previewContent = `
📋 ตรวจสอบข้อมูลการจอง

🏢 บริการ: ${serviceName}
👤 ชื่อผู้จอง: ${customerName}
📧 อีเมล: ${customerEmail}
📞 เบอร์โทร: ${customerPhone || '-'}

📅 วันที่จอง: ${thaiDate}
⏰ เวลา: ${startTime} - ${endTime}
💰 ราคา: ${parseFloat(totalPrice).toFixed(2)} บาท
📊 สถานะ: ${status}

คุณต้องการดำเนินการสร้างการจองนี้ใช่หรือไม่?
    `;
    
    if (confirm(previewContent)) {
        document.getElementById('add-booking-form').submit();
    }
}
</script> 