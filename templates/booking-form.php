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

// ดึง custom form fields
$custom_fields_json = $psu_booking->get_setting('custom_form_fields');
$custom_fields = $custom_fields_json ? json_decode($custom_fields_json, true) : array();


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
            
            <div id="service-name-display" style="background: #f0f8ff; padding: 15px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid #2B3F6A;">
                <strong>บริการ: </strong><span id="current-service-name">-</span>
            </div>
            
            <div class="psu-selected-date-info" id="selected-date-info"></div>
            
            <div class="psu-timeslots-container" id="timeslots-container">
                <div class="psu-loading">กำลังโหลดช่วงเวลา...</div>
            </div>
            
            <div class="psu-selected-timeslots" id="selected-timeslots" style="display: none;">
                <h4>เวลาที่เลือก:</h4>
                <ul id="selected-timeslots-list"></ul>
                <div class="psu-total-price">รวม: <span id="total-price">0</span> <span id="price-unit">บาท</span></div>
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
                
                <?php if (!empty($custom_fields)): ?>
                    <hr style="margin: 30px 0; border: none; border-top: 1px solid #e0e0e0;">
                    <h4 style="color: #2B3F6A; margin-bottom: 20px;">ข้อมูลเพิ่มเติม</h4>
                    
                    <?php foreach ($custom_fields as $index => $field): ?>
                        <div class="psu-form-group">
                            <label for="custom_field_<?php echo $index; ?>">
                                <?php echo esc_html($field['label']); ?>
                                <?php if ($field['required']): ?>
                                    <span style="color: #d63638;">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php
                            $field_name = 'custom_field_' . $index;
                            $field_id = 'custom_field_' . $index;
                            $required = $field['required'] ? 'required' : '';
                            $placeholder = !empty($field['placeholder']) ? $field['placeholder'] : '';
                            
                            switch ($field['type']) {
                                case 'textarea':
                                    echo '<textarea id="' . $field_id . '" name="' . $field_name . '" rows="3" placeholder="' . esc_attr($placeholder) . '" ' . $required . '></textarea>';
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
                                        foreach ($options as $opt_index => $option) {
                                            $option = trim($option);
                                            if ($option) {
                                                echo '<label class="psu-radio-label">';
                                                echo '<input type="radio" name="' . $field_name . '" value="' . esc_attr($option) . '" ' . $required . '> ';
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
                                        foreach ($options as $opt_index => $option) {
                                            $option = trim($option);
                                            if ($option) {
                                                echo '<label class="psu-checkbox-label">';
                                                echo '<input type="checkbox" name="' . $field_name . '[]" value="' . esc_attr($option) . '"> ';
                                                echo esc_html($option);
                                                echo '</label>';
                                            }
                                        }
                                        echo '</div>';
                                    }
                                    break;
                                    
                                case 'file':
                                    echo '<input type="file" id="' . $field_id . '" name="' . $field_name . '" ' . $required . '>';
                                    break;
                                    
                                default:
                                    echo '<input type="' . esc_attr($field['type']) . '" id="' . $field_id . '" name="' . $field_name . '" placeholder="' . esc_attr($placeholder) . '" ' . $required . '>';
                            }
                            ?>
                            
                            <?php if (!empty($field['description'])): ?>
                                <small class="psu-field-description" style="display: block; margin-top: 5px; color: #666; font-style: italic;">
                                    <?php echo esc_html($field['description']); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
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

 