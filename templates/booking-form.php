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

// Debug: แสดงจำนวนบริการ
if (empty($services)) {
    echo '<div class="notice notice-warning"><p>🚨 ไม่พบบริการในระบบ กรุณาเพิ่มบริการในหน้า Admin หรือ Activate plugin ใหม่</p></div>';
}
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
                            echo '<h4 class="psu-category-title" style="grid-column: 1/-1; color: #2B3F6A; margin: 20px 0 10px 0;">' . esc_html($service->category) . '</h4>';
                            $current_category = $service->category;
                        endif;
                    ?>
                        <div class="psu-service-card" data-service-id="<?php echo $service->id; ?>" data-price="<?php echo $service->price; ?>">
                            <?php if ($service->image_url): ?>
                                <div class="psu-service-image">
                                    <img src="<?php echo esc_url($service->image_url); ?>" alt="<?php echo esc_attr($service->name); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="psu-service-content">
                                <h4 class="service-name"><?php echo esc_html($service->name); ?></h4>
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
                </div>
            <?php else: ?>
                <div class="psu-no-services">
                    <h4>ไม่มีบริการให้จอง</h4>
                </div>
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
// ตรวจสอบว่า jQuery และ psu_ajax พร้อมใช้งาน
jQuery(document).ready(function($) {
    console.log('🚀 PSU Booking Form Loading...');
    console.log('AJAX URL:', psu_ajax ? psu_ajax.ajax_url : 'NOT AVAILABLE');
    console.log('Services count:', $('.psu-service-card').length);
    
    // ตรวจสอบว่ามีบริการหรือไม่
    if ($('.psu-service-card').length === 0) {
        console.warn('⚠️ No services found!');
        return;
    }
    
    // ทดสอบ frontend.js
    if (typeof psuGoToStep === 'function') {
        console.log('✅ Frontend JS loaded successfully');
    } else {
        console.error('❌ Frontend JS not loaded properly');
    }
    
    // Event สำหรับเลือกบริการ (backup)
    $('.psu-service-card').off('click').on('click', function() {
        $('.psu-service-card').removeClass('selected');
        $(this).addClass('selected');
        
        const serviceId = $(this).data('service-id');
        console.log('🎯 Service selected:', serviceId);
        
        // ถ้า frontend.js ไม่ทำงาน ให้ทำแบบ manual
        if (typeof selectService === 'function') {
            selectService(serviceId);
        } else {
            console.log('Using manual service selection...');
            manualSelectService(serviceId);
        }
    });
    
    function manualSelectService(serviceId) {
        if (!psu_ajax) {
            alert('ระบบไม่พร้อมใช้งาน กรุณาลองใหม่อีกครั้ง');
            return;
        }
        
        $.ajax({
            url: psu_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'psu_get_service',
                service_id: serviceId,
                nonce: psu_ajax.nonce
            },
            success: function(response) {
                console.log('✅ Service data received:', response);
                if (response.success) {
                    updateServiceInfo(response.data);
                    showStep(2);
                } else {
                    alert('ไม่สามารถโหลดข้อมูลบริการได้');
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ AJAX Error:', error);
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error);
            }
        });
    }
    
    function updateServiceInfo(service) {
        const priceText = service.price > 0 ? 
            Number(service.price).toLocaleString() + ' บาท/ชั่วโมง' : 'ฟรี';
        
        const html = `
            <div class="psu-service-summary" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="color: #2B3F6A; margin-bottom: 10px;">${service.name}</h4>
                <p style="margin-bottom: 10px;">${service.description}</p>
                <p><strong>ราคา:</strong> ${priceText}</p>
                <p><strong>ระยะเวลา:</strong> ${service.duration} นาที</p>
                ${service.payment_info ? `<p><strong>การชำระเงิน:</strong> ${service.payment_info}</p>` : ''}
            </div>
        `;
        $('#selected-service-info').html(html);
    }
    
    function showStep(stepNumber) {
        $('.psu-step').addClass('psu-step-hidden');
        $('#step-' + stepNumber).removeClass('psu-step-hidden');
        $('html, body').animate({
            scrollTop: $('.psu-booking-container').offset().top
        }, 500);
    }
    
    // Global function สำหรับใช้ใน template
    window.psuGoToStep = window.psuGoToStep || showStep;
});
</script> 