<?php
// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// จัดการการเปลี่ยนสถานะ
if (isset($_POST['update_status']) && wp_verify_nonce($_POST['psu_booking_nonce'], 'psu_update_booking')) {
    global $wpdb;
    
    $booking_id = intval($_POST['booking_id']);
    $new_status = sanitize_text_field($_POST['status']);
    $rejection_reason = sanitize_textarea_field($_POST['rejection_reason']);
    
    // ดึงสถานะเดิม
    $old_status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM {$wpdb->prefix}psu_bookings WHERE id = %d",
        $booking_id
    ));
    
    $update_data = array('status' => $new_status);
    if ($new_status == 'rejected' && $rejection_reason) {
        $update_data['rejection_reason'] = $rejection_reason;
    }
    
    $result = $wpdb->update(
        $wpdb->prefix . 'psu_bookings',
        $update_data,
        array('id' => $booking_id),
        array('%s', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        // ส่ง hook สำหรับการเปลี่ยนสถานะ
        do_action('psu_booking_status_changed', $booking_id, $old_status, $new_status);
        echo '<div class="notice notice-success"><p>อัปเดตสถานะสำเร็จ!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>เกิดข้อผิดพลาดในการอัปเดตสถานะ</p></div>';
    }
}

// จัดการการลบ
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['booking_id']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_booking_' . $_GET['booking_id'])) {
    global $wpdb;
    $booking_id = intval($_GET['booking_id']);
    
    $result = $wpdb->delete(
        $wpdb->prefix . 'psu_bookings',
        array('id' => $booking_id),
        array('%d')
    );
    
    if ($result) {
        echo '<div class="notice notice-success"><p>ลบการจองสำเร็จ!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>เกิดข้อผิดพลาดในการลบการจอง</p></div>';
    }
}

// ตัวแปรสำหรับการกรอง
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$service_filter = isset($_GET['service_id']) ? intval($_GET['service_id']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

// สร้าง query สำหรับดึงข้อมูล
global $wpdb;
$where_conditions = array();
$where_values = array();

if ($status_filter) {
    $where_conditions[] = "b.status = %s";
    $where_values[] = $status_filter;
}

if ($service_filter) {
    $where_conditions[] = "b.service_id = %d";
    $where_values[] = $service_filter;
}

if ($date_from) {
    $where_conditions[] = "b.booking_date >= %s";
    $where_values[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "b.booking_date <= %s";
    $where_values[] = $date_to;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

$query = "
    SELECT 
        b.*,
        s.name as service_name,
        s.category as service_category
    FROM {$wpdb->prefix}psu_bookings b
    LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id
    $where_clause
    ORDER BY b.created_at DESC
";

if (!empty($where_values)) {
    $bookings = $wpdb->get_results($wpdb->prepare($query, $where_values));
} else {
    $bookings = $wpdb->get_results($query);
}

// ดึงข้อมูลบริการสำหรับ dropdown
$services = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}psu_services WHERE status = 1 ORDER BY name");

// ดึงข้อมูลสำหรับ modal แก้ไขสถานะ
$edit_booking = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit_status' && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    $edit_booking = $wpdb->get_row($wpdb->prepare(
        "SELECT b.*, s.name as service_name 
         FROM {$wpdb->prefix}psu_bookings b
         LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id
         WHERE b.id = %d",
        $booking_id
    ));
}

// สถิติพื้นฐาน
$stats = array(
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings"),
    'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings WHERE status = 'pending'"),
    'approved' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings WHERE status = 'approved'"),
    'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings WHERE status = 'rejected'")
);
?>

<div class="wrap">
    <h1>จัดการรายการจอง</h1>

    <!-- สถิติด่วน -->
    <div class="psu-stats-cards">
        <div class="psu-stat-card">
            <div class="psu-stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="psu-stat-label">ทั้งหมด</div>
        </div>
        <div class="psu-stat-card">
            <div class="psu-stat-number psu-stat-pending"><?php echo number_format($stats['pending']); ?></div>
            <div class="psu-stat-label">รออนุมัติ</div>
        </div>
        <div class="psu-stat-card">
            <div class="psu-stat-number psu-stat-approved"><?php echo number_format($stats['approved']); ?></div>
            <div class="psu-stat-label">อนุมัติแล้ว</div>
        </div>
        <div class="psu-stat-card">
            <div class="psu-stat-number psu-stat-rejected"><?php echo number_format($stats['rejected']); ?></div>
            <div class="psu-stat-label">ถูกปฏิเสธ</div>
        </div>
    </div>

    <!-- ตัวกรอง -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <form method="get" style="display: inline-block;">
                <input type="hidden" name="page" value="psu-booking-bookings">
                
                <select name="status">
                    <option value="">ทุกสถานะ</option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>>รออนุมัติ</option>
                    <option value="approved" <?php selected($status_filter, 'approved'); ?>>อนุมัติแล้ว</option>
                    <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>ถูกปฏิเสธ</option>
                </select>
                
                <select name="service_id">
                    <option value="">ทุกบริการ</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo $service->id; ?>" <?php selected($service_filter, $service->id); ?>>
                            <?php echo esc_html($service->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="วันที่เริ่ม">
                <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="วันที่สิ้นสุด">
                
                <input type="submit" class="button" value="กรอง">
                <a href="?page=psu-booking-bookings" class="button">รีเซ็ต</a>
            </form>
        </div>
        
        <div class="alignright actions">
            <a href="#" class="button" onclick="exportBookings()">ส่งออก Excel</a>
        </div>
    </div>

    <!-- รายการจอง -->
    <?php if (empty($bookings)): ?>
        <div class="notice notice-info">
            <p>ไม่พบรายการจองตามเงื่อนไขที่กำหนด</p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="manage-column column-cb check-column">
                        <input type="checkbox">
                    </th>
                    <th class="manage-column">รหัสจอง</th>
                    <th class="manage-column">บริการ</th>
                    <th class="manage-column">ผู้จอง</th>
                    <th class="manage-column">วันที่จอง</th>
                    <th class="manage-column">เวลา</th>
                    <th class="manage-column">ราคา</th>
                    <th class="manage-column">สถานะ</th>
                    <th class="manage-column">วันที่สร้าง</th>
                    <th class="manage-column">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" value="<?php echo $booking->id; ?>">
                        </th>
                        <td>
                            <strong>#<?php echo str_pad($booking->id, 6, '0', STR_PAD_LEFT); ?></strong>
                        </td>
                        <td>
                            <strong><?php echo esc_html($booking->service_name); ?></strong>
                            <?php if ($booking->service_category): ?>
                                <br><small class="description"><?php echo esc_html($booking->service_category); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($booking->customer_name); ?></strong>
                            <br><a href="mailto:<?php echo esc_attr($booking->customer_email); ?>"><?php echo esc_html($booking->customer_email); ?></a>
                        </td>
                        <td>
                            <strong><?php echo date('d/m/Y', strtotime($booking->booking_date)); ?></strong>
                            <br><small><?php echo date('l', strtotime($booking->booking_date)); ?></small>
                        </td>
                        <td>
                            <?php echo date('H:i', strtotime($booking->start_time)); ?> - 
                            <?php echo date('H:i', strtotime($booking->end_time)); ?>
                        </td>
                        <td>
                            <strong><?php echo number_format($booking->total_price, 2); ?> บาท</strong>
                        </td>
                        <td>
                            <?php
                            $status_class = '';
                            $status_text = '';
                            switch ($booking->status) {
                                case 'pending':
                                    $status_class = 'psu-status-pending';
                                    $status_text = 'รออนุมัติ';
                                    break;
                                case 'approved':
                                    $status_class = 'psu-status-approved';
                                    $status_text = 'อนุมัติแล้ว';
                                    break;
                                case 'rejected':
                                    $status_class = 'psu-status-rejected';
                                    $status_text = 'ถูกปฏิเสธ';
                                    break;
                            }
                            ?>
                            <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                            
                            <?php if ($booking->status == 'rejected' && $booking->rejection_reason): ?>
                                <br><small class="description" title="<?php echo esc_attr($booking->rejection_reason); ?>">
                                    <?php echo esc_html(substr($booking->rejection_reason, 0, 30)) . (strlen($booking->rejection_reason) > 30 ? '...' : ''); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date('d/m/Y H:i', strtotime($booking->created_at)); ?>
                        </td>
                        <td>
                            <a href="?page=psu-booking-bookings&action=edit_status&booking_id=<?php echo $booking->id; ?>" 
                               class="button button-small">แก้ไขสถานะ</a>
                            
                            <a href="#" class="button button-small" onclick="viewBookingDetails(<?php echo $booking->id; ?>)">ดูรายละเอียด</a>
                            
                            <a href="<?php echo wp_nonce_url('?page=psu-booking-bookings&action=delete&booking_id=' . $booking->id, 'delete_booking_' . $booking->id); ?>" 
                               class="button button-small button-link-delete" 
                               onclick="return confirm('คุณต้องการลบการจองนี้หรือไม่?');">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal แก้ไขสถานะ -->
<?php if ($edit_booking): ?>
<div id="edit-status-modal" class="psu-modal" style="display: block;">
    <div class="psu-modal-content">
        <div class="psu-modal-header">
            <h2>แก้ไขสถานะการจอง #<?php echo str_pad($edit_booking->id, 6, '0', STR_PAD_LEFT); ?></h2>
            <span class="psu-modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div class="psu-modal-body">
            <div class="booking-info">
                <p><strong>บริการ:</strong> <?php echo esc_html($edit_booking->service_name); ?></p>
                <p><strong>ผู้จอง:</strong> <?php echo esc_html($edit_booking->customer_name); ?></p>
                <p><strong>วันที่จอง:</strong> <?php echo date('d/m/Y', strtotime($edit_booking->booking_date)); ?></p>
                <p><strong>เวลา:</strong> <?php echo date('H:i', strtotime($edit_booking->start_time)); ?> - <?php echo date('H:i', strtotime($edit_booking->end_time)); ?></p>
                <p><strong>ราคา:</strong> <?php echo number_format($edit_booking->total_price, 2); ?> บาท</p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('psu_update_booking', 'psu_booking_nonce'); ?>
                <input type="hidden" name="booking_id" value="<?php echo $edit_booking->id; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="status">สถานะ</label></th>
                        <td>
                            <select id="status" name="status" required>
                                <option value="pending" <?php selected($edit_booking->status, 'pending'); ?>>รออนุมัติ</option>
                                <option value="approved" <?php selected($edit_booking->status, 'approved'); ?>>อนุมัติ</option>
                                <option value="rejected" <?php selected($edit_booking->status, 'rejected'); ?>>ปฏิเสธ</option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr id="rejection-reason-row" style="<?php echo $edit_booking->status == 'rejected' ? '' : 'display: none;'; ?>">
                        <th scope="row"><label for="rejection_reason">เหตุผลที่ปฏิเสธ</label></th>
                        <td>
                            <textarea id="rejection_reason" name="rejection_reason" rows="3" class="large-text"><?php echo esc_textarea($edit_booking->rejection_reason); ?></textarea>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="update_status" class="button-primary" value="อัปเดตสถานะ">
                    <button type="button" class="button" onclick="closeModal()">ยกเลิก</button>
                </p>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// แสดง/ซ่อนฟิลด์เหตุผลการปฏิเสธ
document.addEventListener('DOMContentLoaded', function() {
    var statusSelect = document.getElementById('status');
    var rejectionRow = document.getElementById('rejection-reason-row');
    
    if (statusSelect && rejectionRow) {
        statusSelect.addEventListener('change', function() {
            if (this.value === 'rejected') {
                rejectionRow.style.display = 'table-row';
            } else {
                rejectionRow.style.display = 'none';
            }
        });
    }
});

function closeModal() {
    window.location.href = '?page=psu-booking-bookings';
}

function viewBookingDetails(bookingId) {
    // TODO: เปิด modal แสดงรายละเอียดการจอง
    alert('ฟีเจอร์นี้จะพัฒนาในเวอร์ชั่นถัดไป');
}

function exportBookings() {
    // TODO: ส่งออกข้อมูลเป็น Excel
    alert('ฟีเจอร์ส่งออก Excel จะพัฒนาในเวอร์ชั่นถัดไป');
}
</script>