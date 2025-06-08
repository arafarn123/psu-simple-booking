<?php
/**
 * Template สำหรับแบบฟอร์มจอง - Luxury Design
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
    'name' => 'ชื่อ-นามสกุล',
    'email' => 'อีเมล',
    'additional_info' => 'รายละเอียดเพิ่มเติม',
    'submit_booking' => 'ยืนยันการจอง',
    'booking_success' => 'จองสำเร็จแล้ว!',
    'next' => 'ถัดไป',
    'previous' => 'ย้อนกลับ',
    'book_now' => 'จองเลย'
);

$texts = array_merge($default_texts, $texts);

// ข้อมูลผู้ใช้ปัจจุบัน
$current_user = wp_get_current_user();

// ดึง custom form fields
$custom_fields_json = $psu_booking->get_setting('custom_form_fields');
$custom_fields = $custom_fields_json ? json_decode($custom_fields_json, true) : array();
?>

<div class="psu-booking-container">
    <div class="psu-booking-form psu-luxury-shadow" id="psu-booking-form">
        
        <!-- Step 1: เลือกบริการ -->
        <div class="psu-step" id="step-1">
            <h3 class="psu-step-title"><?php echo esc_html($texts['select_service']); ?></h3>
            
            <?php if (!empty($services)): ?>
                <div class="psu-services-grid">
                    <?php 
                    $current_category = '';
                    foreach ($services as $service): 
                        if ($current_category !== $service->category && !empty($service->category)):
                            echo '<h4 class="psu-category-title">' . esc_html($service->category) . '</h4>';
                            $current_category = $service->category;
                        endif;
                    ?>
                        <div class="psu-service-card psu-luxury-accent" data-service-id="<?php echo $service->id; ?>" data-price="<?php echo $service->price; ?>">
                            <?php if ($service->image_url): ?>
                                <div class="psu-service-image">
                                    <img src="<?php echo esc_url($service->image_url); ?>" alt="<?php echo esc_attr($service->name); ?>" loading="lazy">
                                </div>
                            <?php endif; ?>
                            <div class="psu-service-content">
                                <h4 class="service-name"><?php echo esc_html($service->name); ?></h4>
                                <p><?php echo esc_html($service->description); ?></p>
                                <div class="psu-service-details">
                                    <span class="psu-price">
                                        <?php echo $service->price > 0 ? number_format($service->price, 0) . ' บาท/ชั่วโมง' : 'ไม่มีค่าบริการ'; ?>
                                    </span>
                                    <span class="psu-duration">
                                        ระยะเวลา: <?php echo $service->duration; ?> นาที
                                    </span>
                                </div>
                                <button type="button" class="psu-btn psu-btn-luxury psu-select-service" data-service-id="<?php echo $service->id; ?>">
                                    <span><?php echo esc_html($texts['book_now']); ?></span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="psu-no-services">
                    <div style="font-size: 48px; margin-bottom: 20px; opacity: 0.6;">🏢</div>
                    <h4>ไม่มีบริการให้จอง</h4>
                    <p>โปรดติดต่อเจ้าหน้าที่เพื่อขอข้อมูลเพิ่มเติม</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Step 2: เลือกวันที่ -->
        <div class="psu-step psu-step-hidden" id="step-2">
            <h3 class="psu-step-title"><?php echo esc_html($texts['select_date']); ?></h3>
            
            <div class="psu-selected-service-info" id="selected-service-info" style="background: linear-gradient(135deg, var(--psu-cream) 0%, var(--psu-off-white) 100%); padding: 25px; margin-bottom: 30px; border-radius: var(--psu-radius-lg); border: 2px solid var(--psu-primary); box-shadow: var(--psu-shadow-soft);">
                <h4 style="color: var(--psu-primary); font-family: var(--psu-font-heading); margin: 0 0 10px 0; font-size: 20px;">บริการที่เลือก</h4>
                <div id="service-details-display" style="color: var(--psu-text); font-size: 16px;"></div>
            </div>
            
            <div class="psu-calendar-container">
                <div class="psu-calendar-header">
                    <button type="button" id="prev-month" class="psu-btn">‹</button>
                    <h4 id="calendar-month-year"></h4>
                    <button type="button" id="next-month" class="psu-btn">›</button>
                </div>
                <div class="psu-calendar">
                    <div class="psu-calendar-grid">
                        <div class="psu-calendar-header-day">อา</div>
                        <div class="psu-calendar-header-day">จ</div>
                        <div class="psu-calendar-header-day">อ</div>
                        <div class="psu-calendar-header-day">พ</div>
                        <div class="psu-calendar-header-day">พฤ</div>
                        <div class="psu-calendar-header-day">ศ</div>
                        <div class="psu-calendar-header-day">ส</div>
                    </div>
                    <div class="psu-calendar-grid" id="psu-calendar"></div>
                </div>
            </div>
            
            <div class="psu-step-actions">
                <button type="button" class="psu-btn psu-btn-secondary" onclick="psuGoToStep(1)">
                    <span>← <?php echo esc_html($texts['previous']); ?></span>
                </button>
                <button type="button" class="psu-btn psu-btn-primary psu-btn-disabled" id="next-to-step-3" disabled>
                    <span><?php echo esc_html($texts['next']); ?> →</span>
                </button>
            </div>
        </div>

        <!-- Step 3: เลือกเวลา -->
        <div class="psu-step psu-step-hidden" id="step-3">
            <h3 class="psu-step-title"><?php echo esc_html($texts['select_time']); ?></h3>
            
            <div id="service-date-summary" style="background: linear-gradient(135deg, var(--psu-cream) 0%, var(--psu-off-white) 100%); padding: 25px; margin-bottom: 30px; border-radius: var(--psu-radius-lg); border: 2px solid var(--psu-primary); box-shadow: var(--psu-shadow-soft);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div>
                        <h4 style="color: var(--psu-primary); font-family: var(--psu-font-heading); margin: 0 0 5px 0; font-size: 18px;">บริการ</h4>
                        <span id="current-service-name" style="color: var(--psu-text); font-weight: 600;">-</span>
                    </div>
                    <div>
                        <h4 style="color: var(--psu-primary); font-family: var(--psu-font-heading); margin: 0 0 5px 0; font-size: 18px;">วันที่เลือก</h4>
                        <span id="selected-date-display" style="color: var(--psu-text); font-weight: 600;">-</span>
                    </div>
                </div>
            </div>
            
            <div class="psu-timeslots-container" id="timeslots-container">
                <div class="psu-loading">
                    <div style="font-size: 24px; margin-bottom: 15px;">⏳</div>
                    กำลังโหลดช่วงเวลา...
                </div>
            </div>
            
            <div class="psu-selected-timeslots" id="selected-timeslots" style="display: none;">
                <h4>🕐 เวลาที่เลือก</h4>
                <ul id="selected-timeslots-list"></ul>
                <div class="psu-total-price">
                    💰 รวมทั้งสิ้น: <span id="total-price">0</span> <span id="price-unit">บาท</span>
                </div>
            </div>
            
            <div class="psu-step-actions">
                <button type="button" class="psu-btn psu-btn-secondary" onclick="psuGoToStep(2)">
                    <span>← <?php echo esc_html($texts['previous']); ?></span>
                </button>
                <button type="button" class="psu-btn psu-btn-primary psu-btn-disabled" id="next-to-step-4" disabled>
                    <span><?php echo esc_html($texts['next']); ?> →</span>
                </button>
            </div>
        </div>

        <!-- Step 4: ข้อมูลผู้จอง -->
        <div class="psu-step psu-step-hidden" id="step-4">
            <h3 class="psu-step-title"><?php echo esc_html($texts['customer_info']); ?></h3>
            
            <form id="psu-customer-form">
                <div class="psu-form-group">
                    <label for="customer_name">
                        <span style="margin-right: 8px;">👤</span>
                        <?php echo esc_html($texts['name']); ?> 
                        <span style="color: var(--psu-error); margin-left: 4px;">*</span>
                    </label>
                    <input type="text" id="customer_name" name="customer_name" value="<?php echo esc_attr($current_user->display_name); ?>" required placeholder="กรุณากรอกชื่อ-นามสกุล">
                </div>
                
                <div class="psu-form-group">
                    <label for="customer_email">
                        <span style="margin-right: 8px;">✉️</span>
                        <?php echo esc_html($texts['email']); ?> 
                        <span style="color: var(--psu-error); margin-left: 4px;">*</span>
                    </label>
                    <input type="email" id="customer_email" name="customer_email" value="<?php echo esc_attr($current_user->user_email); ?>" required placeholder="example@email.com">
                </div>
                
                <div class="psu-form-group">
                    <label for="additional_info">
                        <span style="margin-right: 8px;">💬</span>
                        <?php echo esc_html($texts['additional_info']); ?>
                    </label>
                    <textarea id="additional_info" name="additional_info" rows="4" placeholder="รายละเอียดเพิ่มเติมหรือข้อความพิเศษ..." style="resize: vertical; min-height: 120px;"></textarea>
                </div>
                
                <?php if (!empty($custom_fields)): ?>
                    <div style="margin: 40px 0; padding: 30px; background: linear-gradient(135deg, var(--psu-cream) 0%, var(--psu-off-white) 100%); border-radius: var(--psu-radius-lg); border: 1px solid var(--psu-border-light);">
                        <h4 style="color: var(--psu-primary); font-family: var(--psu-font-heading); margin: 0 0 25px 0; font-size: 20px; display: flex; align-items: center;">
                            <span style="margin-right: 10px;">📝</span>
                            ข้อมูลเพิ่มเติม
                        </h4>
                        
                        <?php foreach ($custom_fields as $index => $field): ?>
                            <div class="psu-form-group">
                                <label for="custom_field_<?php echo $index; ?>">
                                    <?php echo esc_html($field['label']); ?>
                                    <?php if ($field['required']): ?>
                                        <span style="color: var(--psu-error); margin-left: 4px;">*</span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php
                                $field_name = 'custom_field_' . $index;
                                $field_id = 'custom_field_' . $index;
                                $required = $field['required'] ? 'required' : '';
                                $placeholder = !empty($field['placeholder']) ? $field['placeholder'] : '';
                                
                                switch ($field['type']) {
                                    case 'textarea':
                                        echo '<textarea id="' . $field_id . '" name="' . $field_name . '" rows="3" placeholder="' . esc_attr($placeholder) . '" ' . $required . ' style="resize: vertical;"></textarea>';
                                        break;
                                        
                                    case 'select':
                                        echo '<select id="' . $field_id . '" name="' . $field_name . '" ' . $required . '>';
                                        echo '<option value="">เลือก...</option>';
                                        if (!empty($field['options'])) {
                                            $options = explode("\n", $field['options']);
                                            foreach ($options as $option) {
                                                $option = trim($option);
                                                if ($option) {
                                                    echo '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
                                                }
                                            }
                                        }
                                        echo '</select>';
                                        break;
                                        
                                    case 'radio':
                                        if (!empty($field['options'])) {
                                            echo '<div class="psu-radio-group">';
                                            $options = explode("\n", $field['options']);
                                            foreach ($options as $optionIndex => $option) {
                                                $option = trim($option);
                                                if ($option) {
                                                    echo '<label class="psu-radio-label">';
                                                    echo '<input type="radio" name="' . $field_name . '" value="' . esc_attr($option) . '" ' . ($optionIndex === 0 && $field['required'] ? 'required' : '') . '>';
                                                    echo esc_html($option);
                                                    echo '</label>';
                                                }
                                            }
                                            echo '</div>';
                                        }
                                        break;
                                        
                                    case 'checkbox':
                                        if (!empty($field['options'])) {
                                            echo '<div class="psu-checkbox-group">';
                                            $options = explode("\n", $field['options']);
                                            foreach ($options as $option) {
                                                $option = trim($option);
                                                if ($option) {
                                                    echo '<label class="psu-checkbox-label">';
                                                    echo '<input type="checkbox" name="' . $field_name . '[]" value="' . esc_attr($option) . '">';
                                                    echo esc_html($option);
                                                    echo '</label>';
                                                }
                                            }
                                            echo '</div>';
                                        }
                                        break;
                                        
                                    case 'number':
                                        echo '<input type="number" id="' . $field_id . '" name="' . $field_name . '" placeholder="' . esc_attr($placeholder) . '" ' . $required . '>';
                                        break;
                                        
                                    case 'tel':
                                        echo '<input type="tel" id="' . $field_id . '" name="' . $field_name . '" placeholder="' . esc_attr($placeholder) . '" ' . $required . '>';
                                        break;
                                        
                                    case 'date':
                                        echo '<input type="date" id="' . $field_id . '" name="' . $field_name . '" ' . $required . '>';
                                        break;
                                        
                                    case 'time':
                                        echo '<input type="time" id="' . $field_id . '" name="' . $field_name . '" ' . $required . '>';
                                        break;
                                        
                                    case 'file':
                                        echo '<input type="file" id="' . $field_id . '" name="' . $field_name . '" ' . $required . '>';
                                        break;
                                        
                                    default: // text, email
                                        $input_type = in_array($field['type'], ['email']) ? $field['type'] : 'text';
                                        echo '<input type="' . $input_type . '" id="' . $field_id . '" name="' . $field_name . '" placeholder="' . esc_attr($placeholder) . '" ' . $required . '>';
                                        break;
                                }
                                
                                if (!empty($field['description'])) {
                                    echo '<small class="psu-field-description">' . esc_html($field['description']) . '</small>';
                                }
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="psu-step-actions">
                    <button type="button" class="psu-btn psu-btn-secondary" onclick="psuGoToStep(3)">
                        <span>← <?php echo esc_html($texts['previous']); ?></span>
                    </button>
                    <button type="button" class="psu-btn psu-btn-luxury" id="submit-booking">
                        <span>✨ <?php echo esc_html($texts['submit_booking']); ?></span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Step 5: ยืนยันการจอง -->
        <div class="psu-step psu-step-hidden" id="step-5">
            <div class="psu-success-message">
                <div class="psu-success-icon">✅</div>
                <h3><?php echo esc_html($texts['booking_success']); ?></h3>
                <p>ระบบได้รับการจองของคุณเรียบร้อยแล้ว</p>
                <p>คุณจะได้รับอีเมลยืนยันการจองในอีกสักครู่</p>
                
                <div style="margin-top: 30px; padding: 25px; background: var(--psu-white); border-radius: var(--psu-radius-lg); border: 2px solid var(--psu-border-light); text-align: left;">
                    <h4 style="color: var(--psu-primary); margin: 0 0 15px 0; font-family: var(--psu-font-heading);">📋 สรุปการจอง</h4>
                    <div id="booking-summary-details" style="line-height: 1.8; color: var(--psu-text);"></div>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="button" class="psu-btn psu-btn-primary" onclick="location.reload()">
                        <span>🔄 จองใหม่อีกครั้ง</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="psu-loading-overlay" style="display: none; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.95); z-index: 999; border-radius: var(--psu-radius-xl);">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                <div style="width: 60px; height: 60px; border: 4px solid var(--psu-border-light); border-top: 4px solid var(--psu-primary); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                <p style="color: var(--psu-primary); font-weight: 600; font-size: 16px;">กำลังประมวลผล...</p>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Custom Form Field Styles */
.psu-radio-group,
.psu-checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 8px;
}

.psu-radio-label,
.psu-checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-weight: normal !important;
    margin-bottom: 0 !important;
    padding: 15px 20px;
    border: 2px solid var(--psu-border-light);
    border-radius: var(--psu-radius);
    transition: all 0.3s ease;
    background: var(--psu-white);
    font-size: 15px;
}

.psu-radio-label:hover,
.psu-checkbox-label:hover {
    background: var(--psu-cream);
    border-color: var(--psu-primary);
    transform: translateY(-1px);
    box-shadow: var(--psu-shadow-soft);
}

.psu-radio-label input,
.psu-checkbox-label input {
    margin: 0 !important;
    width: auto !important;
    padding: 0 !important;
    border: none !important;
    background: none !important;
    transform: scale(1.2);
}

.psu-field-description {
    display: block;
    margin-top: 8px;
    color: var(--psu-text-light);
    font-style: italic;
    font-size: 14px;
    line-height: 1.5;
    padding: 8px 12px;
    background: var(--psu-cream);
    border-radius: var(--psu-radius);
    border-left: 3px solid var(--psu-primary);
}

/* File Input Styling */
.psu-form-group input[type="file"] {
    padding: 15px;
    border: 2px dashed var(--psu-border);
    border-radius: var(--psu-radius);
    background: var(--psu-cream);
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 15px;
}

.psu-form-group input[type="file"]:hover {
    border-color: var(--psu-primary);
    background: var(--psu-off-white);
}

/* Enhanced responsive */
@media (max-width: 768px) {
    .psu-radio-label,
    .psu-checkbox-label {
        padding: 12px 15px;
        font-size: 14px;
    }
    
    #service-date-summary {
        flex-direction: column;
        text-align: center;
    }
    
    #service-date-summary > div {
        margin-bottom: 15px;
    }
    
    #service-date-summary > div:last-child {
        margin-bottom: 0;
    }
}
</style>

 