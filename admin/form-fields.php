<?php
// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// จัดการการบันทึกฟิลด์แบบกำหนดเอง
if (isset($_POST['save_custom_fields']) && wp_verify_nonce($_POST['psu_form_fields_nonce'], 'psu_save_form_fields')) {
    $custom_fields = array();
    
    if (isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
        foreach ($_POST['custom_fields'] as $index => $field) {
            $custom_fields[] = array(
                'label' => sanitize_text_field($field['label']),
                'type' => sanitize_text_field($field['type']),
                'placeholder' => sanitize_text_field($field['placeholder']),
                'description' => sanitize_text_field($field['description']),
                'required' => isset($field['required']) ? 1 : 0,
                'options' => isset($field['options']) ? sanitize_textarea_field($field['options']) : '',
                'order' => intval($index)
            );
        }
    }
    
    // บันทึกลงฐานข้อมูล
    global $wpdb;
    $result = $wpdb->replace(
        $wpdb->prefix . 'psu_settings',
        array('setting_key' => 'custom_form_fields', 'setting_value' => json_encode($custom_fields)),
        array('%s', '%s')
    );
    
    if ($result) {
        echo '<div class="notice notice-success"><p>บันทึกฟิลด์ฟอร์มสำเร็จ!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>เกิดข้อผิดพลาดในการบันทึกข้อมูล</p></div>';
    }
}

// ดึงฟิลด์ที่มีอยู่
global $wpdb;
$custom_fields_json = $wpdb->get_var($wpdb->prepare(
    "SELECT setting_value FROM {$wpdb->prefix}psu_settings WHERE setting_key = %s",
    'custom_form_fields'
));
$custom_fields = $custom_fields_json ? json_decode($custom_fields_json, true) : array();

// ฟิลด์เริ่มต้น
$default_fields = array(
    array(
        'label' => 'ชื่อ-นามสกุล',
        'type' => 'text',
        'placeholder' => 'กรุณากรอกชื่อ-นามสกุล',
        'description' => '',
        'required' => 1,
        'options' => '',
        'order' => 0,
        'is_default' => true
    ),
    array(
        'label' => 'อีเมล',
        'type' => 'email',
        'placeholder' => 'example@domain.com',
        'description' => '',
        'required' => 1,
        'options' => '',
        'order' => 1,
        'is_default' => true
    ),
    array(
        'label' => 'รายละเอียดเพิ่มเติม',
        'type' => 'textarea',
        'placeholder' => 'กรุณาระบุรายละเอียดเพิ่มเติม (ถ้ามี)',
        'description' => 'ข้อมูลเพิ่มเติมที่ต้องการสื่อสาร',
        'required' => 0,
        'options' => '',
        'order' => 2,
        'is_default' => true
    )
);
?>

<div class="wrap">
    <h1>ปรับแต่งฟอร์ม</h1>
    
    <div class="psu-admin-container">
        <div class="psu-card">
            <div class="psu-card-header">
                <h2>ปรับแต่งฟิลด์ในฟอร์มจอง</h2>
                <p class="description">กำหนดฟิลด์ที่ต้องการให้ผู้ใช้กรอกในฟอร์มจอง</p>
            </div>
            
            <form method="post" action="" class="psu-form-fields-form">
                <?php wp_nonce_field('psu_save_form_fields', 'psu_form_fields_nonce'); ?>
                
                <div class="psu-form-content">
                    <!-- ฟิลด์เริ่มต้น -->
                    <div class="psu-default-fields">
                        <h3>ฟิลด์เริ่มต้น (ไม่สามารถลบได้)</h3>
                        <div class="psu-fields-container">
                            <?php foreach ($default_fields as $index => $field): ?>
                                <div class="psu-field-item psu-default-field">
                                    <div class="psu-field-header">
                                        <span class="psu-field-icon">📝</span>
                                        <strong><?php echo esc_html($field['label']); ?></strong>
                                        <span class="psu-field-type"><?php echo esc_html($field['type']); ?></span>
                                        <?php if ($field['required']): ?>
                                            <span class="psu-required-badge">จำเป็น</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="psu-field-preview">
                                        <?php echo esc_html($field['placeholder']); ?>
                                        <?php if ($field['description']): ?>
                                            <small><?php echo esc_html($field['description']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- ฟิลด์แบบกำหนดเอง -->
                    <div class="psu-custom-fields">
                        <div class="psu-section-header">
                            <h3>ฟิลด์เพิ่มเติม</h3>
                            <button type="button" onclick="addCustomField()" class="button button-primary">
                                เพิ่มฟิลด์ใหม่
                            </button>
                        </div>
                        
                        <div id="custom-fields-container" class="psu-sortable">
                            <?php if (!empty($custom_fields)): ?>
                                <?php foreach ($custom_fields as $index => $field): ?>
                                    <div class="psu-custom-field" data-field-id="<?php echo $index; ?>">
                                        <div class="psu-field-header">
                                            <span class="psu-sort-handle">⋮⋮</span>
                                            <input type="text" name="custom_fields[<?php echo $index; ?>][label]" 
                                                   value="<?php echo esc_attr($field['label']); ?>" 
                                                   placeholder="ป้ายกำกับฟิลด์" class="psu-input" required>
                                            <select name="custom_fields[<?php echo $index; ?>][type]" class="psu-select" 
                                                    onchange="toggleFieldOptions(this)">
                                                <option value="text" <?php selected($field['type'], 'text'); ?>>ข้อความ</option>
                                                <option value="textarea" <?php selected($field['type'], 'textarea'); ?>>ข้อความแบบยาว</option>
                                                <option value="email" <?php selected($field['type'], 'email'); ?>>อีเมล</option>
                                                <option value="number" <?php selected($field['type'], 'number'); ?>>ตัวเลข</option>
                                                <option value="tel" <?php selected($field['type'], 'tel'); ?>>เบอร์โทรศัพท์</option>
                                                <option value="select" <?php selected($field['type'], 'select'); ?>>เลือกจากรายการ</option>
                                                <option value="radio" <?php selected($field['type'], 'radio'); ?>>เลือกหนึ่งตัวเลือก</option>
                                                <option value="checkbox" <?php selected($field['type'], 'checkbox'); ?>>เลือกได้หลายตัวเลือก</option>
                                                <option value="date" <?php selected($field['type'], 'date'); ?>>วันที่</option>
                                                <option value="time" <?php selected($field['type'], 'time'); ?>>เวลา</option>
                                                <option value="file" <?php selected($field['type'], 'file'); ?>>อัปโหลดไฟล์</option>
                                            </select>
                                            <button type="button" onclick="removeCustomField(this)" 
                                                    class="button button-small psu-remove-field">ลบ</button>
                                        </div>
                                        
                                        <div class="psu-field-options">
                                            <div class="psu-field-settings">
                                                <label class="psu-checkbox-label">
                                                    <input type="checkbox" name="custom_fields[<?php echo $index; ?>][required]" 
                                                           value="1" <?php checked($field['required']); ?>>
                                                    จำเป็นต้องกรอก
                                                </label>
                                                <input type="text" name="custom_fields[<?php echo $index; ?>][placeholder]" 
                                                       value="<?php echo esc_attr($field['placeholder']); ?>"
                                                       placeholder="ข้อความตัวอย่าง" class="psu-input">
                                                <input type="text" name="custom_fields[<?php echo $index; ?>][description]" 
                                                       value="<?php echo esc_attr($field['description']); ?>"
                                                       placeholder="คำอธิบายเพิ่มเติม" class="psu-input">
                                            </div>
                                            
                                            <div class="psu-field-options-list" style="<?php echo in_array($field['type'], ['select', 'radio', 'checkbox']) ? 'display: block;' : 'display: none;'; ?>">
                                                <label>ตัวเลือก (แยกด้วยบรรทัดใหม่):</label>
                                                <textarea name="custom_fields[<?php echo $index; ?>][options]" rows="3" class="psu-textarea" 
                                                          placeholder="ตัวเลือก 1&#10;ตัวเลือก 2&#10;ตัวเลือก 3"><?php echo esc_textarea($field['options']); ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <!-- ตัวอย่างฟิลด์ -->
                                        <div class="psu-field-preview">
                                            <strong>ตัวอย่าง:</strong>
                                            <div class="psu-preview-content">
                                                <?php 
                                                    $field_type = isset($field['type']) ? $field['type'] : 'text';
                                                    $placeholder = isset($field['placeholder']) ? $field['placeholder'] : '';
                                                    
                                                    switch ($field_type) {
                                                        case 'textarea':
                                                            echo '<textarea class="psu-textarea" placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '" disabled></textarea>';
                                                            break;
                                                        case 'select':
                                                            echo '<select class="psu-select" disabled><option>เลือก...</option></select>';
                                                            break;
                                                        case 'radio':
                                                        case 'checkbox':
                                                            echo '<em>ตัวอย่างจะแสดงเมื่อเพิ่มตัวเลือก</em>';
                                                            break;
                                                        default:
                                                            echo '<input type="' . htmlspecialchars($field_type, ENT_QUOTES, 'UTF-8') . '" class="psu-input" placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '" disabled>';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (empty($custom_fields)): ?>
                            <div id="no-custom-fields" class="psu-empty-state">
                                <p>ยังไม่มีฟิลด์เพิ่มเติม</p>
                                <button type="button" onclick="addCustomField()" class="button button-primary">
                                    เพิ่มฟิลด์ใหม่
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="psu-form-actions">
                    <button type="submit" name="save_custom_fields" class="button button-primary button-large">
                        บันทึกการเปลี่ยนแปลง
                    </button>
                    <button type="button" class="button button-large" onclick="previewForm()">
                        ดูตัวอย่างฟอร์ม
                    </button>
                </div>
            </form>
        </div>
        
        <!-- ตัวอย่างฟอร์ม -->
        <div class="psu-card psu-form-preview" id="form-preview" style="display: none;">
            <div class="psu-card-header">
                <h2>ตัวอย่างฟอร์มจอง</h2>
                <button type="button" class="button" onclick="closePreview()">ปิด</button>
            </div>
            
            <div class="psu-preview-form">
                <div class="psu-booking-form-preview">
                    <!-- ฟิลด์เริ่มต้น -->
                    <?php foreach ($default_fields as $field): ?>
                        <div class="psu-form-group">
                            <label class="psu-label <?php echo $field['required'] ? 'required' : ''; ?>">
                                <?php echo esc_html($field['label']); ?>
                            </label>
                            <?php if ($field['type'] === 'textarea'): ?>
                                <textarea class="psu-textarea" placeholder="<?php echo esc_attr($field['placeholder']); ?>" 
                                          <?php echo $field['required'] ? 'required' : ''; ?>></textarea>
                            <?php else: ?>
                                <input type="<?php echo esc_attr($field['type']); ?>" class="psu-input" 
                                       placeholder="<?php echo esc_attr($field['placeholder']); ?>" 
                                       <?php echo $field['required'] ? 'required' : ''; ?>>
                            <?php endif; ?>
                            <?php if ($field['description']): ?>
                                <small class="psu-field-description"><?php echo esc_html($field['description']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- ฟิลด์แบบกำหนดเอง -->
                    <div id="preview-custom-fields">
                        <!-- จะถูกอัปเดตด้วย JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.psu-form-fields-form {
    padding: 20px;
}

.psu-default-fields {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #eee;
}

.psu-default-fields h3 {
    color: #2B3F6A;
    margin-bottom: 15px;
}

.psu-field-item {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 10px;
}

.psu-default-field {
    opacity: 0.8;
}

.psu-field-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.psu-field-icon {
    font-size: 16px;
}

.psu-field-type {
    background: #e8f4fd;
    color: #2B3F6A;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.psu-required-badge {
    background: #d63638;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
}

.psu-field-preview {
    font-size: 13px;
    color: #666;
}

.psu-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.psu-section-header h3 {
    color: #2B3F6A;
    margin: 0;
}

.psu-custom-field {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 15px;
    position: relative;
}

.psu-sort-handle {
    cursor: move;
    color: #999;
    font-size: 14px;
    user-select: none;
}

.psu-sort-handle:hover {
    color: #2B3F6A;
}

.psu-field-header input[type="text"] {
    flex: 1;
    min-width: 200px;
}

.psu-field-header select {
    min-width: 150px;
}

.psu-remove-field {
    background: #d63638 !important;
    color: white !important;
    border-color: #d63638 !important;
}

.psu-remove-field:hover {
    background: #b32d2e !important;
}

.psu-field-options {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.psu-field-settings {
    display: grid;
    grid-template-columns: auto 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
    align-items: center;
}

.psu-field-options-list {
    margin-top: 10px;
}

.psu-field-options-list label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.psu-field-preview {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
    border-left: 4px solid #2B3F6A;
}

.psu-preview-content {
    margin-top: 8px;
}

.psu-form-preview {
    margin-top: 20px;
}

.psu-booking-form-preview {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 4px;
}

.psu-booking-form-preview .psu-form-group {
    margin-bottom: 20px;
}

.psu-booking-form-preview .psu-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #1d2327;
}

.psu-booking-form-preview .psu-label.required::after {
    content: ' *';
    color: #d63638;
}

.psu-booking-form-preview .psu-input,
.psu-booking-form-preview .psu-textarea,
.psu-booking-form-preview .psu-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    font-size: 14px;
}

.psu-field-description {
    display: block;
    margin-top: 5px;
    color: #666;
    font-style: italic;
}

.psu-empty-state {
    text-align: center;
    padding: 40px 20px;
    background: #f9f9f9;
    border: 2px dashed #c3c4c7;
    border-radius: 4px;
    color: #666;
}

.psu-sortable {
    min-height: 100px;
}

.psu-sort-placeholder {
    background: #e8f4fd;
    border: 2px dashed #2B3F6A;
    height: 100px;
    margin-bottom: 15px;
    border-radius: 4px;
}

@media (max-width: 768px) {
    .psu-field-header {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
    
    .psu-field-settings {
        grid-template-columns: 1fr;
    }
    
    .psu-section-header {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
}
</style>

<script>
// ฟังก์ชันสำหรับดูตัวอย่างฟอร์ม
function previewForm() {
    var preview = document.getElementById('form-preview');
    var customFieldsContainer = document.getElementById('preview-custom-fields');
    
    // ล้างฟิลด์เดิม
    customFieldsContainer.innerHTML = '';
    
    // เพิ่มฟิลด์แบบกำหนดเอง
    var customFields = document.querySelectorAll('.psu-custom-field');
    customFields.forEach(function(field) {
        var label = field.querySelector('input[name*="[label]"]').value;
        var type = field.querySelector('select[name*="[type]"]').value;
        var placeholder = field.querySelector('input[name*="[placeholder]"]').value;
        var description = field.querySelector('input[name*="[description]"]').value;
        var required = field.querySelector('input[name*="[required]"]').checked;
        var options = field.querySelector('textarea[name*="[options]"]');
        
        if (label) {
            var fieldHtml = '<div class="psu-form-group">';
            fieldHtml += '<label class="psu-label' + (required ? ' required' : '') + '">' + label + '</label>';
            
            if (type === 'textarea') {
                fieldHtml += '<textarea class="psu-textarea" placeholder="' + placeholder + '"' + (required ? ' required' : '') + '></textarea>';
            } else if (type === 'select' && options) {
                fieldHtml += '<select class="psu-select"' + (required ? ' required' : '') + '>';
                fieldHtml += '<option value="">เลือก...</option>';
                options.value.split('\n').forEach(function(option) {
                    if (option.trim()) {
                        fieldHtml += '<option value="' + option.trim() + '">' + option.trim() + '</option>';
                    }
                });
                fieldHtml += '</select>';
            } else if (type === 'radio' && options) {
                options.value.split('\n').forEach(function(option) {
                    if (option.trim()) {
                        fieldHtml += '<label><input type="radio" name="' + label + '" value="' + option.trim() + '"> ' + option.trim() + '</label><br>';
                    }
                });
            } else if (type === 'checkbox' && options) {
                options.value.split('\n').forEach(function(option) {
                    if (option.trim()) {
                        fieldHtml += '<label><input type="checkbox" name="' + label + '[]" value="' + option.trim() + '"> ' + option.trim() + '</label><br>';
                    }
                });
            } else {
                fieldHtml += '<input type="' + type + '" class="psu-input" placeholder="' + placeholder + '"' + (required ? ' required' : '') + '>';
            }
            
            if (description) {
                fieldHtml += '<small class="psu-field-description">' + description + '</small>';
            }
            
            fieldHtml += '</div>';
            
            customFieldsContainer.innerHTML += fieldHtml;
        }
    });
    
    preview.style.display = 'block';
    preview.scrollIntoView({ behavior: 'smooth' });
}

function closePreview() {
    document.getElementById('form-preview').style.display = 'none';
}

// ตัวแปร global สำหรับ field counter
var fieldCounter = <?php echo count($custom_fields); ?>;

// ฟังก์ชันเพิ่มฟิลด์ใหม่
function addCustomField() {
    var container = document.getElementById('custom-fields-container');
    var newFieldHtml = `
        <div class="psu-custom-field" data-field-id="${fieldCounter}">
            <div class="psu-field-header">
                <span class="psu-sort-handle">⋮⋮</span>
                <input type="text" name="custom_fields[${fieldCounter}][label]" 
                       value="" placeholder="ป้ายกำกับฟิลด์" class="psu-input" required>
                <select name="custom_fields[${fieldCounter}][type]" class="psu-select" 
                        onchange="toggleFieldOptions(this)">
                    <option value="text">ข้อความ</option>
                    <option value="textarea">ข้อความแบบยาว</option>
                    <option value="email">อีเมล</option>
                    <option value="number">ตัวเลข</option>
                    <option value="tel">เบอร์โทรศัพท์</option>
                    <option value="select">เลือกจากรายการ</option>
                    <option value="radio">เลือกหนึ่งตัวเลือก</option>
                    <option value="checkbox">เลือกได้หลายตัวเลือก</option>
                    <option value="date">วันที่</option>
                    <option value="time">เวลา</option>
                    <option value="file">อัปโหลดไฟล์</option>
                </select>
                <button type="button" onclick="removeCustomField(this)" 
                        class="button button-small psu-remove-field">ลบ</button>
            </div>
            
            <div class="psu-field-options">
                <div class="psu-field-settings">
                    <label class="psu-checkbox-label">
                        <input type="checkbox" name="custom_fields[${fieldCounter}][required]" value="1">
                        จำเป็นต้องกรอก
                    </label>
                    <input type="text" name="custom_fields[${fieldCounter}][placeholder]" 
                           value="" placeholder="ข้อความตัวอย่าง" class="psu-input">
                    <input type="text" name="custom_fields[${fieldCounter}][description]" 
                           value="" placeholder="คำอธิบายเพิ่มเติม" class="psu-input">
                </div>
                
                <div class="psu-field-options-list" style="display: none;">
                    <label>ตัวเลือก (แยกด้วยบรรทัดใหม่):</label>
                    <textarea name="custom_fields[${fieldCounter}][options]" rows="3" class="psu-textarea" 
                              placeholder="ตัวเลือก 1&#10;ตัวเลือก 2&#10;ตัวเลือก 3"></textarea>
                </div>
            </div>
            
            <div class="psu-field-preview">
                <strong>ตัวอย่าง:</strong>
                <div class="psu-preview-content">
                    <input type="text" class="psu-input" placeholder="ป้ายกำกับฟิลด์" disabled>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', newFieldHtml);
    fieldCounter++;
    
    // อัปเดต sortable
    if (typeof jQuery !== 'undefined' && jQuery.fn.sortable) {
        jQuery('#custom-fields-container').sortable('refresh');
    }
    
    toggleEmptyState();
}

// ฟังก์ชันลบฟิลด์
function removeCustomField(button) {
    if (confirm('คุณต้องการลบฟิลด์นี้หรือไม่?')) {
        var fieldElement = button.closest('.psu-custom-field');
        fieldElement.remove();
        toggleEmptyState();
        reorderCustomFields();
    }
}

// ฟังก์ชันแสดง/ซ่อนตัวเลือกสำหรับ select, radio, checkbox
function toggleFieldOptions(selectElement) {
    var fieldElement = selectElement.closest('.psu-custom-field');
    var optionsContainer = fieldElement.querySelector('.psu-field-options-list');
    var previewContainer = fieldElement.querySelector('.psu-preview-content');
    var fieldType = selectElement.value;
    var labelInput = fieldElement.querySelector('input[name*="[label]"]');
    var placeholderInput = fieldElement.querySelector('input[name*="[placeholder]"]');
    
    // แสดง/ซ่อนตัวเลือก
    if (['select', 'radio', 'checkbox'].includes(fieldType)) {
        optionsContainer.style.display = 'block';
    } else {
        optionsContainer.style.display = 'none';
    }
    
    // อัปเดตตัวอย่าง
    updateFieldPreview(fieldElement);
}

// ฟังก์ชันอัปเดตตัวอย่างฟิลด์
function updateFieldPreview(fieldElement) {
    var typeSelect = fieldElement.querySelector('select[name*="[type]"]');
    var labelInput = fieldElement.querySelector('input[name*="[label]"]');
    var placeholderInput = fieldElement.querySelector('input[name*="[placeholder]"]');
    var optionsTextarea = fieldElement.querySelector('textarea[name*="[options]"]');
    var previewContainer = fieldElement.querySelector('.psu-preview-content');
    
    var fieldType = typeSelect.value;
    var label = labelInput.value || 'ป้ายกำกับฟิลด์';
    var placeholder = placeholderInput.value || 'ตัวอย่าง';
    var options = optionsTextarea ? optionsTextarea.value : '';
    
    var previewHtml = '';
    
    switch (fieldType) {
        case 'textarea':
            previewHtml = `<textarea class="psu-textarea" placeholder="${placeholder}" disabled></textarea>`;
            break;
        case 'select':
            previewHtml = '<select class="psu-select" disabled><option>เลือก...</option>';
            if (options) {
                options.split('\n').forEach(function(option) {
                    option = option.trim();
                    if (option) {
                        previewHtml += `<option>${option}</option>`;
                    }
                });
            }
            previewHtml += '</select>';
            break;
        case 'radio':
            if (options) {
                options.split('\n').forEach(function(option) {
                    option = option.trim();
                    if (option) {
                        previewHtml += `<label><input type="radio" disabled> ${option}</label><br>`;
                    }
                });
            }
            break;
        case 'checkbox':
            if (options) {
                options.split('\n').forEach(function(option) {
                    option = option.trim();
                    if (option) {
                        previewHtml += `<label><input type="checkbox" disabled> ${option}</label><br>`;
                    }
                });
            }
            break;
        default:
            previewHtml = `<input type="${fieldType}" class="psu-input" placeholder="${placeholder}" disabled>`;
    }
    
    previewContainer.innerHTML = previewHtml;
}

// ฟังก์ชันจัดลำดับฟิลด์ใหม่
function reorderCustomFields() {
    var fields = document.querySelectorAll('.psu-custom-field');
    fields.forEach(function(field, index) {
        // อัปเดต name attributes
        var inputs = field.querySelectorAll('input, select, textarea');
        inputs.forEach(function(input) {
            var name = input.getAttribute('name');
            if (name) {
                var newName = name.replace(/custom_fields\[\d+\]/, `custom_fields[${index}]`);
                input.setAttribute('name', newName);
            }
        });
        
        // อัปเดต data-field-id
        field.setAttribute('data-field-id', index);
    });
    
    fieldCounter = fields.length;
}

// ฟังก์ชันสำหรับจัดการฟิลด์แบบกำหนดเอง
document.addEventListener('DOMContentLoaded', function() {
    // เปิดใช้งาน sortable
    if (typeof jQuery !== 'undefined' && jQuery.fn.sortable) {
        jQuery('#custom-fields-container').sortable({
            handle: '.psu-sort-handle',
            placeholder: 'psu-sort-placeholder',
            update: function() {
                reorderCustomFields();
            }
        });
    }
    
    // ซ่อน/แสดง empty state
    toggleEmptyState();
    
    // เพิ่ม event listeners สำหรับฟิลด์ที่มีอยู่
    document.querySelectorAll('input[name*="[label]"], input[name*="[placeholder]"], textarea[name*="[options]"]').forEach(function(input) {
        input.addEventListener('input', function() {
            var fieldElement = this.closest('.psu-custom-field');
            if (fieldElement) {
                updateFieldPreview(fieldElement);
            }
        });
    });
});

function toggleEmptyState() {
    var container = document.getElementById('custom-fields-container');
    var emptyState = document.getElementById('no-custom-fields');
    
    if (container.children.length === 0) {
        if (emptyState) emptyState.style.display = 'block';
    } else {
        if (emptyState) emptyState.style.display = 'none';
    }
}
</script>

<?php
// สิ้นสุดไฟล์ form-fields.php
?> 