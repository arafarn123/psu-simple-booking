<?php
// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

// === ACTION HANDLERS ===
// จัดการ Quick Actions (Approve, Reject) และ Delete ผ่าน GET parameter
if (isset($_GET['action']) && in_array($_GET['action'], ['approve', 'reject', 'delete']) && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    $action = sanitize_key($_GET['action']);
    
    // ตรวจสอบ nonce
    if (isset($_GET['_wpnonce']) && wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), $action . '_booking_' . $booking_id)) {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'psu_bookings';
        $result = false;
        $message = '';
        $message_type = 'success';

        switch ($action) {
            case 'approve':
                $result = $wpdb->update($table_bookings, ['status' => 'approved'], ['id' => $booking_id]);
                $message = 'การจองได้รับการอนุมัติแล้ว';
                // ส่ง hook
                if ($result) do_action('psu_booking_status_changed', $booking_id, 'pending', 'approved');
                break;
            case 'reject':
                $reason = isset($_GET['reason']) ? sanitize_textarea_field(urldecode($_GET['reason'])) : '';
                $data = ['status' => 'rejected'];
                if (!empty($reason)) {
                    $data['rejection_reason'] = $reason;
                }
                $result = $wpdb->update($table_bookings, $data, ['id' => $booking_id]);
                $message = 'การจองถูกปฏิเสธแล้ว';
                // ส่ง hook
                if ($result) do_action('psu_booking_status_changed', $booking_id, 'any', 'rejected');
                break;
            case 'delete':
                $result = $wpdb->delete($table_bookings, ['id' => $booking_id]);
                $message = 'ลบการจองสำเร็จ!';
                break;
        }

        if ($result !== false) {
            echo '<div class="notice notice-'.$message_type.' is-dismissible"><p>'.$message.'</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>เกิดข้อผิดพลาดในการดำเนินการ</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>การดำเนินการไม่ปลอดภัยหรือไม่ถูกต้อง</p></div>';
    }
}

// === VIEW ROUTER ===
// ตรวจสอบว่าควรจะแสดงหน้าแก้ไข หรือหน้ารายการ
if (isset($_GET['action']) && $_GET['action'] == 'view_details' && isset($_GET['booking_id'])) {
    // โหลดหน้าแก้ไข
    include_once('booking-edit.php');

} elseif (isset($_GET['action']) && $_GET['action'] == 'add_new') {
    // โหลดหน้าเพิ่มการจองใหม่
    include_once('booking-add.php');

} else {
    // แสดงหน้ารายการปกติ

    // ตัวแปรสำหรับการกรอง
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $service_filter = isset($_GET['service_id']) ? intval($_GET['service_id']) : '';
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

    // สร้าง query สำหรับดึงข้อมูล
    global $wpdb;
    $where_conditions = array();
    $where_values = array();

    // ถ้าไม่ได้ระบุช่วงวันที่ ให้แสดงเดือนปัจจุบันเป็นค่าเริ่มต้น
    if (empty($date_from) && empty($date_to)) {
        $current_month = date('Y-m');
        $where_conditions[] = "DATE_FORMAT(b.booking_date, '%Y-%m') = %s";
        $where_values[] = $current_month;
    }

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
            <form method="get" class="psu-filter-form" style="display: inline-block;">
                <input type="hidden" name="page" value="psu-booking-bookings">
                
                <select id="filter-status" name="status">
                    <option value="">ทุกสถานะ</option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>>รออนุมัติ</option>
                    <option value="approved" <?php selected($status_filter, 'approved'); ?>>อนุมัติแล้ว</option>
                    <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>ถูกปฏิเสธ</option>
                </select>
                
                <select id="filter-service" name="service_id">
                    <option value="">ทุกบริการ</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo $service->id; ?>" <?php selected($service_filter, $service->id); ?>>
                            <?php echo esc_html($service->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="date" id="filter-date-from" name="date_from" value="<?php echo esc_attr($date_from); ?>" placeholder="วันที่เริ่ม">
                <input type="date" id="filter-date-to" name="date_to" value="<?php echo esc_attr($date_to); ?>" placeholder="วันที่สิ้นสุด">
                
                <input type="submit" class="button" value="กรอง">
                <a href="?page=psu-booking-bookings" class="button">รีเซ็ต</a>
            </form>
        </div>
        
        <div class="alignright actions">
            <a href="?page=psu-booking-bookings&action=add_new" class="button button-primary">เพิ่มการจองใหม่</a>
            <a href="#" class="button" onclick="exportBookings()">ส่งออก CSV</a>
        </div>
    </div>

    <!-- Calendar View -->
    <div id="calendar-view">
        <div class="psu-calendar-layout">
            <!-- Left Column: Calendar -->
            <div class="psu-calendar-left">
                <div class="psu-calendar-container compact">
                    <div class="psu-calendar-header">
                        <button type="button" id="prev-month-admin" class="button button-small">‹</button>
                        <h4 id="calendar-month-year-admin"></h4>
                        <button type="button" id="next-month-admin" class="button button-small">›</button>
                    </div>
                    
                    <div class="psu-calendar-grid-container">
                        <div class="psu-calendar-weekdays">
                            <div class="psu-calendar-weekday">อา</div>
                            <div class="psu-calendar-weekday">จ</div>
                            <div class="psu-calendar-weekday">อ</div>
                            <div class="psu-calendar-weekday">พ</div>
                            <div class="psu-calendar-weekday">พฤ</div>
                            <div class="psu-calendar-weekday">ศ</div>
                            <div class="psu-calendar-weekday">ส</div>
                        </div>
                        <div class="psu-calendar-days" id="admin-calendar-days">
                            <!-- Calendar days will be generated by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="psu-calendar-legend">
                        <div class="legend-item">
                            <span class="legend-color status-available"></span>
                            <span>ว่าง</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color status-partial"></span>
                            <span>จองบางส่วน</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color status-full"></span>
                            <span>เต็ม</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Selected Date Details -->
            <div class="psu-calendar-right">
                <div class="psu-card">
                    <div class="psu-card-header">
                        <h4 id="selected-date-title">รายการจองวันที่</h4>
                    </div>
                    <div class="psu-card-body">
                        <div id="selected-date-bookings">
                            <div class="loading-placeholder">
                                <p style="text-align: center; color: #666; padding: 20px;">
                                    <span style="font-size: 24px;">📅</span><br>
                                    เลือกวันที่ในปฏิทินเพื่อดูรายการจอง
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- รายการจอง - List View (เดือนปัจจุบัน) -->
    <div id="list-view">
        <?php
        $has_filters = !empty($status_filter) || !empty($service_filter) || !empty($date_from) || !empty($date_to);

        if ($has_filters) {
            $list_title = "ผลการค้นหารายการจอง";
        } else {
            $thai_months = [
                '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
                '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
                '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
            ];
            $current_month_num = date('m');
            $current_year_num = date('Y');
            $thai_year = $current_year_num + 543;
            $list_title = 'รายการจองเดือน ' . $thai_months[$current_month_num] . ' ' . $thai_year;
        }
        ?>
        <h2><?php echo esc_html($list_title); ?></h2>
        
        <?php if (empty($bookings)): ?>
            <div class="notice notice-info">
                <p>ไม่มีรายการจองที่ตรงตามเงื่อนไข (หรือไม่มีรายการจองในเดือนนี้)</p>
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
                            <strong><a href="?page=psu-booking-bookings&action=view_details&booking_id=<?php echo $booking->id; ?>">#<?php echo str_pad($booking->id, 6, '0', STR_PAD_LEFT); ?></a></strong>
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
                            <div class="psu-booking-actions" style="display: flex; gap: 5px; align-items: center; flex-wrap: wrap;">
                                <?php
                                $view_url = '?page=psu-booking-bookings&action=view_details&booking_id=' . $booking->id;
                                echo '<a href="' . $view_url . '" class="button button-secondary button-small">แก้ไข/ดู</a>';

                                // Quick Status Actions
                                if ($booking->status == 'pending') {
                                    $approve_url = wp_nonce_url('?page=psu-booking-bookings&action=approve&booking_id=' . $booking->id, 'approve_booking_' . $booking->id);
                                    echo '<a href="' . $approve_url . '" class="button button-primary button-small" onclick="return confirm(\'คุณต้องการอนุมัติการจองนี้ใช่หรือไม่?\');">อนุมัติ</a>';
                                    
                                    $reject_nonce_url = wp_nonce_url('?page=psu-booking-bookings&action=reject&booking_id=' . $booking->id, 'reject_booking_' . $booking->id);
                                    echo '<a href="#" onclick="rejectBooking(event, \'' . $reject_nonce_url . '\')" class="button button-link-delete button-small">ปฏิเสธ</a>';
                                }

                                // Delete Action
                                $delete_url = wp_nonce_url('?page=psu-booking-bookings&action=delete&booking_id=' . $booking->id, 'delete_booking_' . $booking->id);
                                echo '<a href="' . $delete_url . '" class="button button-link-delete button-small" onclick="return confirm(\'คุณต้องการลบการจองนี้ถาวรใช่หรือไม่?\');">ลบ</a>';
                                ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
function rejectBooking(event, baseUrl) {
    event.preventDefault();
    const reason = prompt("กรุณาใส่เหตุผลที่ปฏิเสธ (ไม่บังคับ):");
    
    if (reason !== null) { // User clicked OK or Cancel. Null means Cancel.
        let finalUrl = baseUrl;
        if (reason) { // If user provided a reason
            finalUrl += '&reason=' + encodeURIComponent(reason);
        }
        window.location.href = finalUrl;
    }
    // If reason is null (user clicked Cancel), do nothing.
}

function exportBookings() {
    // รับค่าฟิลเตอร์ปัจจุบัน
    const statusFilter = document.getElementById('filter-status')?.value || '';
    const serviceFilter = document.getElementById('filter-service')?.value || '';
    const dateFrom = document.getElementById('filter-date-from')?.value || '';
    const dateTo = document.getElementById('filter-date-to')?.value || '';

    // สร้าง form สำหรับส่งออก CSV
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo admin_url('admin-ajax.php'); ?>';
    form.style.display = 'none';

    // เพิ่มฟิลด์ต่างๆ
    const fields = {
        'action': 'psu_export_bookings_csv',
        'nonce': '<?php echo wp_create_nonce('psu_admin_nonce'); ?>',
        'status': statusFilter,
        'service_id': serviceFilter,
        'date_from': dateFrom,
        'date_to': dateTo
    };

    Object.keys(fields).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>
<?php
} // End else for view router
?>