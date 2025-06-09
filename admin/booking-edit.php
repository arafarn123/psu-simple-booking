<?php
// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$table_bookings = $wpdb->prefix . 'psu_bookings';
$table_services = $wpdb->prefix . 'psu_services';

// === จัดการการอัปเดตข้อมูล ===
if (isset($_POST['psu_update_booking_details']) && wp_verify_nonce($_POST['psu_booking_edit_nonce'], 'psu_update_booking_details_nonce')) {
    
    $update_data = array(
        'customer_name' => sanitize_text_field($_POST['customer_name']),
        'customer_email' => sanitize_email($_POST['customer_email']),
        'customer_phone' => sanitize_text_field($_POST['customer_phone']),
        'booking_date' => sanitize_text_field($_POST['booking_date']),
        'start_time' => sanitize_text_field($_POST['start_time']),
        'end_time' => sanitize_text_field($_POST['end_time']),
        'status' => sanitize_text_field($_POST['status']),
        'rejection_reason' => sanitize_textarea_field($_POST['rejection_reason']),
        'admin_notes' => sanitize_textarea_field($_POST['admin_notes']),
        'total_price' => floatval($_POST['total_price']),
    );
    
    // ดึงข้อมูล form_data เดิม
    $old_form_data_json = $wpdb->get_var($wpdb->prepare("SELECT form_data FROM $table_bookings WHERE id = %d", $booking_id));
    $form_data = json_decode($old_form_data_json, true) ?: array();

    // อัปเดต custom fields
    if (isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
        foreach ($_POST['custom_fields'] as $key => $value) {
            $form_data['custom_fields'][$key] = sanitize_text_field($value);
        }
    }
    
    // เข้ารหัส form_data กลับเป็น JSON
    $update_data['form_data'] = json_encode($form_data);
    
    $result = $wpdb->update($table_bookings, $update_data, array('id' => $booking_id));
    
    if ($result !== false) {
        echo '<div class="notice notice-success is-dismissible"><p>อัปเดตข้อมูลการจองสำเร็จ</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>เกิดข้อผิดพลาดในการอัปเดตข้อมูล</p></div>';
    }
}

// === ดึงข้อมูลการจองเพื่อแสดงในฟอร์ม ===
$booking = $wpdb->get_row($wpdb->prepare(
    "SELECT b.*, s.name as service_name
     FROM $table_bookings b
     LEFT JOIN $table_services s ON b.service_id = s.id
     WHERE b.id = %d",
    $booking_id
));

// ถ้าไม่พบการจอง
if (!$booking) {
    echo '<div class="wrap"><h1>ไม่พบการจอง</h1><p>ไม่พบการจองที่คุณต้องการแก้ไข</p><a href="?page=psu-booking-bookings" class="button">&larr; กลับไปที่รายการ</a></div>';
    return;
}

// ถอดรหัส form_data
$form_data = json_decode($booking->form_data, true);
$custom_fields_data = $form_data['custom_fields'] ?? [];

// ดึง Field Labels
$field_labels = array();
$fields = $wpdb->get_results($wpdb->prepare("
    SELECT field_name, field_label 
    FROM {$wpdb->prefix}psu_form_fields 
    WHERE (service_id IS NULL OR service_id = %d) AND status = 1", $booking->service_id
));
foreach ($fields as $field) {
    $field_labels[$field->field_name] = $field->field_label;
}

?>
<div class="wrap">
    <h1>
        แก้ไข/ดูรายละเอียดการจอง #<?php echo str_pad($booking->id, 6, '0', STR_PAD_LEFT); ?>
        <a href="?page=psu-booking-bookings" class="page-title-action">&larr; กลับไปที่รายการ</a>
    </h1>

    <form method="post" action="">
        <?php wp_nonce_field('psu_update_booking_details_nonce', 'psu_booking_edit_nonce'); ?>
        <input type="hidden" name="booking_id" value="<?php echo $booking->id; ?>">

        <div class="psu-booking-edit-grid">
            <!-- Left Column -->
            <div class="psu-booking-edit-left">
                <div class="postbox">
                    <h2 class="hndle"><span>ข้อมูลผู้จอง</span></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th><label for="customer_name">ชื่อ-สกุล</label></th>
                                <td><input type="text" id="customer_name" name="customer_name" class="regular-text" value="<?php echo esc_attr($booking->customer_name); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="customer_email">อีเมล</label></th>
                                <td><input type="email" id="customer_email" name="customer_email" class="regular-text" value="<?php echo esc_attr($booking->customer_email); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="customer_phone">เบอร์โทรศัพท์</label></th>
                                <td><input type="text" id="customer_phone" name="customer_phone" class="regular-text" value="<?php echo esc_attr($booking->customer_phone); ?>"></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if (!empty($custom_fields_data)): ?>
                <div class="postbox">
                    <h2 class="hndle"><span>ข้อมูลเพิ่มเติม</span></h2>
                    <div class="inside">
                        <table class="form-table">
                            <?php foreach ($custom_fields_data as $key => $value): 
                                $label = $field_labels[$key] ?? ucwords(str_replace('_', ' ', $key));
                            ?>
                            <tr>
                                <th><label for="custom_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                                <td><input type="text" id="custom_<?php echo esc_attr($key); ?>" name="custom_fields[<?php echo esc_attr($key); ?>]" class="regular-text" value="<?php echo esc_attr($value); ?>"></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div class="psu-booking-edit-right">
                <div class="postbox">
                    <h2 class="hndle"><span>ข้อมูลการจอง</span></h2>
                    <div class="inside">
                        <p><strong>บริการ:</strong><br><?php echo esc_html($booking->service_name); ?></p>
                        <table class="form-table">
                            <tr>
                                <th><label for="booking_date">วันที่</label></th>
                                <td><input type="date" id="booking_date" name="booking_date" value="<?php echo esc_attr($booking->booking_date); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="start_time">เวลาเริ่ม</label></th>
                                <td><input type="time" id="start_time" name="start_time" value="<?php echo esc_attr($booking->start_time); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="end_time">เวลาสิ้นสุด</label></th>
                                <td><input type="time" id="end_time" name="end_time" value="<?php echo esc_attr($booking->end_time); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="total_price">ราคา (บาท)</label></th>
                                <td><input type="number" step="0.01" id="total_price" name="total_price" class="small-text" value="<?php echo esc_attr($booking->total_price); ?>"></td>
                            </tr>
                            <tr>
                                <th><label for="status">สถานะ</label></th>
                                <td>
                                    <select id="status" name="status">
                                        <option value="pending" <?php selected($booking->status, 'pending'); ?>>รออนุมัติ</option>
                                        <option value="approved" <?php selected($booking->status, 'approved'); ?>>อนุมัติแล้ว</option>
                                        <option value="rejected" <?php selected($booking->status, 'rejected'); ?>>ถูกปฏิเสธ</option>
                                    </select>
                                </td>
                            </tr>
                            <tr id="rejection-reason-row" style="<?php echo $booking->status == 'rejected' ? '' : 'display: none;'; ?>">
                                <th><label for="rejection_reason">เหตุผลที่ปฏิเสธ</label></th>
                                <td><textarea id="rejection_reason" name="rejection_reason" rows="3" class="widefat"><?php echo esc_textarea($booking->rejection_reason); ?></textarea></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle"><span>บันทึกแอดมิน</span></h2>
                    <div class="inside">
                        <textarea name="admin_notes" rows="5" class="widefat"><?php echo esc_textarea($booking->admin_notes); ?></textarea>
                        <p class="description">บันทึกนี้จะแสดงสำหรับผู้ดูแลระบบเท่านั้น</p>
                    </div>
                </div>

                <div class="postbox">
                    <h2 class="hndle"><span>การจัดการ</span></h2>
                    <div class="inside">
                        <div class="submitbox">
                            <div id="major-publishing-actions">
                                <div id="publishing-action">
                                    <input type="submit" name="psu_update_booking_details" class="button button-primary button-large" value="อัปเดตข้อมูล">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    var statusSelect = document.getElementById('status');
    var rejectionRow = document.getElementById('rejection-reason-row');
    
    if (statusSelect && rejectionRow) {
        statusSelect.addEventListener('change', function() {
            rejectionRow.style.display = (this.value === 'rejected') ? 'table-row' : 'none';
        });
    }
});
</script> 