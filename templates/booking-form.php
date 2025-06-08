<?php
/**
 * Template สำหรับแบบฟอร์มจอง
 */
defined('ABSPATH') || exit;

global $wpdb;

// ดึงบริการทั้งหมด
$services = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}psu_services WHERE status = 1 ORDER BY category, name");

// ดึงการตั้งค่าข้อความ
$psu_booking = new PSU_Simple_Booking();
$texts_json = $psu_booking->get_setting('frontend_texts');
$texts = $texts_json ? json_decode($texts_json, true) : array();

// ข้อความเริ่มต้น
$default_texts = array(
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

$texts = array_merge($default_texts, $texts);

// ข้อมูลผู้ใช้ปัจจุบัน
$current_user = wp_get_current_user();
?>

<div class="psu-booking-container">
    <div class="psu-booking-form" id="psu-booking-form">
        
        <!-- Step 1: เลือกบริการ -->
        <div class="psu-step" id="step-1">
            <h3 class="psu-step-title"><?php echo esc_html($texts['select_service']); ?></h3>
            
            <?php if (!empty($services)): ?>
                <div class="psu-services-grid">
                    <?php 
                    $current_category = '';
                    foreach ($services as $service): 
                        if ($current_category !== $service->category && !empty($service->category)):
                            if ($current_category !== '') echo '</div>';
                            echo '<h4 class="psu-category-title">' . esc_html($service->category) . '</h4>';
                            echo '<div class="psu-category-services">';
                            $current_category = $service->category;
                        endif;
                    ?>
                        <div class="psu-service-card" data-service-id="<?php echo $service->id; ?>">
                            <?php if ($service->image_url): ?>
                                <div class="psu-service-image">
                                    <img src="<?php echo esc_url($service->image_url); ?>" alt="<?php echo esc_attr($service->name); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="psu-service-content">
                                <h4><?php echo esc_html($service->name); ?></h4>
                                <p><?php echo esc_html($service->description); ?></p>
                                <div class="psu-service-details">
                                    <span class="psu-price">
                                        <?php echo $service->price > 0 ? number_format($service->price, 0) . ' บาท/ชั่วโมง' : 'ฟรี'; ?>
                                    </span>
                                    <span class="psu-duration">
                                        ระยะเวลา: <?php echo $service->duration; ?> นาที
                                    </span>
                                </div>
                                <button type="button" class="psu-btn psu-btn-primary psu-select-service" data-service-id="<?php echo $service->id; ?>">
                                    <?php echo esc_html($texts['book_now']); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($current_category !== '') echo '</div>'; ?>
                </div>
            <?php else: ?>
                <p class="psu-no-services">ไม่มีบริการให้จอง</p>
            <?php endif; ?>
        </div>

        <!-- Step 2: เลือกวันที่ -->
        <div class="psu-step psu-step-hidden" id="step-2">
            <h3 class="psu-step-title"><?php echo esc_html($texts['select_date']); ?></h3>
            
            <div class="psu-selected-service-info" id="selected-service-info"></div>
            
            <div class="psu-calendar-container">
                <div class="psu-calendar-header">
                    <button type="button" id="prev-month" class="psu-btn psu-btn-secondary">‹</button>
                    <h4 id="calendar-month-year"></h4>
                    <button type="button" id="next-month" class="psu-btn psu-btn-secondary">›</button>
                </div>
                <div class="psu-calendar" id="psu-calendar"></div>
            </div>
            
            <div class="psu-step-actions">
                <button type="button" class="psu-btn psu-btn-secondary" onclick="psuGoToStep(1)">
                    <?php echo esc_html($texts['previous']); ?>
                </button>
                <button type="button" class="psu-btn psu-btn-primary psu-btn-disabled" id="next-to-step-3" disabled>
                    <?php echo esc_html($texts['next']); ?>
                </button>
            </div>
        </div>

        <!-- Step 3: เลือกเวลา -->
        <div class="psu-step psu-step-hidden" id="step-3">
            <h3 class="psu-step-title"><?php echo esc_html($texts['select_time']); ?></h3>
            
            <div class="psu-selected-date-info" id="selected-date-info"></div>
            
            <div class="psu-timeslots-container" id="timeslots-container">
                <div class="psu-loading">กำลังโหลดช่วงเวลาที่ว่าง...</div>
            </div>
            
            <div class="psu-selected-timeslots" id="selected-timeslots">
                <h4>เวลาที่เลือก:</h4>
                <ul id="selected-timeslots-list"></ul>
                <div class="psu-total-price">รวม: <span id="total-price">0</span> บาท</div>
            </div>
            
            <div class="psu-step-actions">
                <button type="button" class="psu-btn psu-btn-secondary" onclick="psuGoToStep(2)">
                    <?php echo esc_html($texts['previous']); ?>
                </button>
                <button type="button" class="psu-btn psu-btn-primary psu-btn-disabled" id="next-to-step-4" disabled>
                    <?php echo esc_html($texts['next']); ?>
                </button>
            </div>
        </div>

        <!-- Step 4: ข้อมูลผู้จอง -->
        <div class="psu-step psu-step-hidden" id="step-4">
            <h3 class="psu-step-title"><?php echo esc_html($texts['customer_info']); ?></h3>
            
            <form id="psu-customer-form">
                <div class="psu-form-group">
                    <label for="customer_name"><?php echo esc_html($texts['name']); ?> *</label>
                    <input type="text" id="customer_name" name="customer_name" value="<?php echo esc_attr($current_user->display_name); ?>" required>
                </div>
                
                <div class="psu-form-group">
                    <label for="customer_email"><?php echo esc_html($texts['email']); ?> *</label>
                    <input type="email" id="customer_email" name="customer_email" value="<?php echo esc_attr($current_user->user_email); ?>" required>
                </div>
                
                <div class="psu-form-group">
                    <label for="additional_info"><?php echo esc_html($texts['additional_info']); ?></label>
                    <textarea id="additional_info" name="additional_info" rows="4"></textarea>
                </div>
            </form>
            
            <div class="psu-booking-summary">
                <h4>สรุปการจอง</h4>
                <div id="booking-summary-content"></div>
            </div>
            
            <div class="psu-step-actions">
                <button type="button" class="psu-btn psu-btn-secondary" onclick="psuGoToStep(3)">
                    <?php echo esc_html($texts['previous']); ?>
                </button>
                <button type="button" class="psu-btn psu-btn-primary" id="submit-booking">
                    <?php echo esc_html($texts['submit_booking']); ?>
                </button>
            </div>
        </div>

        <!-- Step 5: ยืนยันการจอง -->
        <div class="psu-step psu-step-hidden" id="step-5">
            <div class="psu-success-message">
                <div class="psu-success-icon">✓</div>
                <h3><?php echo esc_html($texts['booking_success']); ?></h3>
                <div id="success-details"></div>
                <button type="button" class="psu-btn psu-btn-primary" onclick="location.reload()">
                    จองใหม่
                </button>
            </div>
        </div>

    </div>
</div>

<script>
// ตัวแปร global สำหรับการจอง
let selectedService = null;
let selectedDate = null;
let selectedTimeslots = [];
let currentStep = 1;
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

// เริ่มต้น
jQuery(document).ready(function($) {
    initBookingForm();
});

function initBookingForm() {
    // เลือกบริการ
    jQuery('.psu-select-service').on('click', function() {
        const serviceId = jQuery(this).data('service-id');
        selectService(serviceId);
    });
    
    // ปฏิทิน
    jQuery('#prev-month').on('click', function() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        renderCalendar();
    });
    
    jQuery('#next-month').on('click', function() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        renderCalendar();
    });
    
    // ปุ่ม next step 3
    jQuery('#next-to-step-3').on('click', function() {
        if (!jQuery(this).hasClass('psu-btn-disabled')) {
            loadTimeslots();
            psuGoToStep(3);
        }
    });
    
    // ปุ่ม next step 4
    jQuery('#next-to-step-4').on('click', function() {
        if (!jQuery(this).hasClass('psu-btn-disabled')) {
            updateBookingSummary();
            psuGoToStep(4);
        }
    });
    
    // ส่งการจอง
    jQuery('#submit-booking').on('click', function() {
        submitBooking();
    });
    
    renderCalendar();
}

function selectService(serviceId) {
    // ดึงข้อมูลบริการ
    jQuery.ajax({
        url: psu_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'psu_get_service',
            service_id: serviceId,
            nonce: psu_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                selectedService = response.data;
                updateSelectedServiceInfo();
                psuGoToStep(2);
            }
        }
    });
}

function updateSelectedServiceInfo() {
    const html = `
        <div class="psu-service-summary">
            <h4>${selectedService.name}</h4>
            <p>${selectedService.description}</p>
            <p>ราคา: ${selectedService.price > 0 ? Number(selectedService.price).toLocaleString() + ' บาท/ชั่วโมง' : 'ฟรี'}</p>
        </div>
    `;
    jQuery('#selected-service-info').html(html);
}

function renderCalendar() {
    const monthNames = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    
    jQuery('#calendar-month-year').text(monthNames[currentMonth] + ' ' + (currentYear + 543));
    
    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
    const today = new Date();
    
    let calendarHtml = '<div class="psu-calendar-grid">';
    calendarHtml += '<div class="psu-calendar-header-day">อา</div>';
    calendarHtml += '<div class="psu-calendar-header-day">จ</div>';
    calendarHtml += '<div class="psu-calendar-header-day">อ</div>';
    calendarHtml += '<div class="psu-calendar-header-day">พ</div>';
    calendarHtml += '<div class="psu-calendar-header-day">พฤ</div>';
    calendarHtml += '<div class="psu-calendar-header-day">ศ</div>';
    calendarHtml += '<div class="psu-calendar-header-day">ส</div>';
    
    // เติมช่องว่างก่อนวันที่ 1
    for (let i = 0; i < firstDay; i++) {
        calendarHtml += '<div class="psu-calendar-day psu-calendar-day-empty"></div>';
    }
    
    // วันที่ในเดือน
    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(currentYear, currentMonth, day);
        const dateStr = date.getFullYear() + '-' + 
                       String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                       String(day).padStart(2, '0');
        
        let classes = 'psu-calendar-day';
        
        // ตรวจสอบวันที่ผ่านมาแล้ว
        if (date < today.setHours(0,0,0,0)) {
            classes += ' psu-calendar-day-disabled';
        } else {
            classes += ' psu-calendar-day-available';
        }
        
        // วันที่ถูกเลือก
        if (selectedDate === dateStr) {
            classes += ' psu-calendar-day-selected';
        }
        
        calendarHtml += `<div class="${classes}" data-date="${dateStr}">${day}</div>`;
    }
    
    calendarHtml += '</div>';
    jQuery('#psu-calendar').html(calendarHtml);
    
    // Event click วันที่
    jQuery('.psu-calendar-day-available').on('click', function() {
        const date = jQuery(this).data('date');
        selectDate(date);
    });
}

function selectDate(date) {
    selectedDate = date;
    selectedTimeslots = [];
    
    // อัพเดท UI
    jQuery('.psu-calendar-day-selected').removeClass('psu-calendar-day-selected');
    jQuery(`[data-date="${date}"]`).addClass('psu-calendar-day-selected');
    
    // เปิดใช้งานปุ่ม next
    jQuery('#next-to-step-3').removeClass('psu-btn-disabled').prop('disabled', false);
    
    // อัพเดทข้อมูลวันที่เลือก
    const dateObj = new Date(date);
    const options = { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' };
    const dateText = dateObj.toLocaleDateString('th-TH', options);
    jQuery('#selected-date-info').html(`<p>วันที่เลือก: <strong>${dateText}</strong></p>`);
}

function loadTimeslots() {
    jQuery('#timeslots-container').html('<div class="psu-loading">กำลังโหลดช่วงเวลาที่ว่าง...</div>');
    
    jQuery.ajax({
        url: psu_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'psu_get_timeslots',
            service_id: selectedService.id,
            date: selectedDate,
            nonce: psu_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                renderTimeslots(response.data);
            } else {
                jQuery('#timeslots-container').html('<p>ไม่สามารถโหลดข้อมูลเวลาได้</p>');
            }
        },
        error: function() {
            jQuery('#timeslots-container').html('<p>เกิดข้อผิดพลาดในการโหลดข้อมูล</p>');
        }
    });
}

function renderTimeslots(timeslots) {
    if (timeslots.length === 0) {
        jQuery('#timeslots-container').html('<p>ไม่มีช่วงเวลาว่างในวันนี้</p>');
        return;
    }
    
    let html = '<div class="psu-timeslots-grid">';
    timeslots.forEach(function(slot) {
        html += `
            <div class="psu-timeslot" data-start="${slot.start}" data-end="${slot.end}" data-price="${slot.price}">
                <div class="psu-timeslot-time">${slot.display}</div>
                <div class="psu-timeslot-price">${Number(slot.price).toLocaleString()} บาท</div>
            </div>
        `;
    });
    html += '</div>';
    
    jQuery('#timeslots-container').html(html);
    
    // Event click timeslot
    jQuery('.psu-timeslot').on('click', function() {
        toggleTimeslot(this);
    });
}

function toggleTimeslot(element) {
    const $slot = jQuery(element);
    const start = $slot.data('start');
    const end = $slot.data('end');
    const price = parseFloat($slot.data('price'));
    
    if ($slot.hasClass('psu-timeslot-selected')) {
        // ยกเลิกการเลือก
        $slot.removeClass('psu-timeslot-selected');
        selectedTimeslots = selectedTimeslots.filter(slot => !(slot.start === start && slot.end === end));
    } else {
        // เลือก timeslot
        $slot.addClass('psu-timeslot-selected');
        selectedTimeslots.push({
            start: start,
            end: end,
            price: price,
            display: $slot.find('.psu-timeslot-time').text()
        });
    }
    
    updateSelectedTimeslots();
}

function updateSelectedTimeslots() {
    if (selectedTimeslots.length === 0) {
        jQuery('#selected-timeslots').hide();
        jQuery('#next-to-step-4').addClass('psu-btn-disabled').prop('disabled', true);
        return;
    }
    
    let html = '';
    let totalPrice = 0;
    
    selectedTimeslots.forEach(function(slot) {
        html += `<li>${slot.display} - ${Number(slot.price).toLocaleString()} บาท</li>`;
        totalPrice += slot.price;
    });
    
    jQuery('#selected-timeslots-list').html(html);
    jQuery('#total-price').text(Number(totalPrice).toLocaleString());
    jQuery('#selected-timeslots').show();
    jQuery('#next-to-step-4').removeClass('psu-btn-disabled').prop('disabled', false);
}

function updateBookingSummary() {
    const totalPrice = selectedTimeslots.reduce((sum, slot) => sum + slot.price, 0);
    
    let timeslotsHtml = '';
    selectedTimeslots.forEach(function(slot) {
        timeslotsHtml += `<li>${slot.display}</li>`;
    });
    
    const html = `
        <div class="psu-summary-item">
            <strong>บริการ:</strong> ${selectedService.name}
        </div>
        <div class="psu-summary-item">
            <strong>วันที่:</strong> ${new Date(selectedDate).toLocaleDateString('th-TH')}
        </div>
        <div class="psu-summary-item">
            <strong>เวลา:</strong>
            <ul>${timeslotsHtml}</ul>
        </div>
        <div class="psu-summary-item">
            <strong>ราคารวม:</strong> ${Number(totalPrice).toLocaleString()} บาท
        </div>
        ${selectedService.payment_info ? `<div class="psu-summary-item"><strong>การชำระเงิน:</strong> ${selectedService.payment_info}</div>` : ''}
    `;
    
    jQuery('#booking-summary-content').html(html);
}

function submitBooking() {
    const formData = {
        action: 'psu_submit_booking',
        service_id: selectedService.id,
        customer_name: jQuery('#customer_name').val(),
        customer_email: jQuery('#customer_email').val(),
        booking_date: selectedDate,
        timeslots: selectedTimeslots,
        additional_info: jQuery('#additional_info').val(),
        nonce: psu_ajax.nonce
    };
    
    // แสดง loading
    jQuery('#submit-booking').prop('disabled', true).text('กำลังจอง...');
    
    jQuery.ajax({
        url: psu_ajax.ajax_url,
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                showSuccessMessage(response.data);
                psuGoToStep(5);
            } else {
                alert('เกิดข้อผิดพลาด: ' + response.data.message);
                jQuery('#submit-booking').prop('disabled', false).text('<?php echo esc_js($texts['submit_booking']); ?>');
            }
        },
        error: function() {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            jQuery('#submit-booking').prop('disabled', false).text('<?php echo esc_js($texts['submit_booking']); ?>');
        }
    });
}

function showSuccessMessage(data) {
    const html = `
        <p>การจองของคุณได้รับการยืนยันแล้ว</p>
        <p>รหัสการจอง: ${data.booking_ids.join(', ')}</p>
        <p>ราคารวม: ${Number(data.total_price).toLocaleString()} บาท</p>
        <p>ท่านจะได้รับอีเมลยืนยันการจองในอีกสักครู่</p>
    `;
    jQuery('#success-details').html(html);
}

function psuGoToStep(step) {
    jQuery('.psu-step').addClass('psu-step-hidden');
    jQuery('#step-' + step).removeClass('psu-step-hidden');
    currentStep = step;
}
</script> 