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

// สร้าง query สำหรับดึงข้อมูล (เฉพาะเดือนปัจจุบัน)
global $wpdb;
$where_conditions = array();
$where_values = array();

// เพิ่มเงื่อนไขเดือนปัจจุบัน
$current_month = date('Y-m');
$where_conditions[] = "DATE_FORMAT(b.booking_date, '%Y-%m') = %s";
$where_values[] = $current_month;

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
                            <span class="legend-color" style="background-color: #28a745;"></span>
                            <span>อนุมัติ</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background-color: #ffc107;"></span>
                            <span>รออนุมัติ</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color" style="background-color: #dc3545;"></span>
                            <span>ปฏิเสธ</span>
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
        $thai_months = [
            '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
            '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
            '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
        ];
        $current_month_num = date('m');
        $current_year = date('Y');
        $thai_year = $current_year + 543;
        ?>
        <h2>รายการจองเดือน <?php echo $thai_months[$current_month_num] . ' ' . $thai_year; ?></h2>
        
        <?php if (empty($bookings)): ?>
            <div class="notice notice-info">
                <p>ไม่มีรายการจองในเดือนนี้</p>
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
// ตัวแปร global สำหรับ calendar
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();
let currentView = 'list';
let adminCalendarData = {};

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
    
    // Initialize calendar
    initAdminCalendar();
});

function initAdminCalendar() {
    renderAdminCalendar();
    loadAdminCalendarData();
    
    // Bind calendar navigation
    document.getElementById('prev-month-admin').addEventListener('click', function() {
        changeAdminMonth(-1);
    });
    
    document.getElementById('next-month-admin').addEventListener('click', function() {
        changeAdminMonth(1);
    });
}



function changeAdminMonth(direction) {
    currentMonth += direction;
    
    if (currentMonth > 11) {
        currentMonth = 0;
        currentYear++;
    } else if (currentMonth < 0) {
        currentMonth = 11;
        currentYear--;
    }
    
    renderAdminCalendar();
    loadAdminCalendarData();
}

function renderAdminCalendar() {
    const monthNames = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    
    // Update header
    document.getElementById('calendar-month-year-admin').textContent = 
        monthNames[currentMonth] + ' ' + (currentYear + 543);
    
    const firstDay = new Date(currentYear, currentMonth, 1);
    const lastDay = new Date(currentYear, currentMonth + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();
    
    const calendarDays = document.getElementById('admin-calendar-days');
    calendarDays.innerHTML = '';
    
    // Add empty cells for days before the first day of the month
    for (let i = 0; i < startingDayOfWeek; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'psu-calendar-day psu-calendar-day-empty';
        calendarDays.appendChild(emptyDay);
    }
    
    // Add days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dayElement = document.createElement('div');
        dayElement.className = 'psu-calendar-day psu-calendar-day-clickable';
        dayElement.textContent = day;
        
        const dateStr = formatDateString(currentYear, currentMonth + 1, day);
        dayElement.setAttribute('data-date', dateStr);
        
        // Add click event
        dayElement.addEventListener('click', function() {
            showDateDetails(dateStr, day);
        });
        
        calendarDays.appendChild(dayElement);
    }
}

function loadAdminCalendarData() {
    // Get current filter values
    const statusFilter = getUrlParameter('status') || '';
    const serviceFilter = getUrlParameter('service_id') || '';
    const dateFrom = getUrlParameter('date_from') || '';
    const dateTo = getUrlParameter('date_to') || '';
    
    const data = new FormData();
    data.append('action', 'psu_get_admin_calendar_bookings');
    data.append('nonce', '<?php echo wp_create_nonce("psu_admin_calendar_nonce"); ?>');
    data.append('year', currentYear);
    data.append('month', currentMonth);
    data.append('status_filter', statusFilter);
    data.append('service_filter', serviceFilter);
    data.append('date_from', dateFrom);
    data.append('date_to', dateTo);
    
    fetch(ajaxurl, {
        method: 'POST',
        body: data
    })
    .then(response => response.json())
    .then(response => {
        if (response.success) {
            adminCalendarData = response.data;
            updateCalendarView();
        }
    })
    .catch(error => {
        console.error('Error loading calendar data:', error);
    });
}

function updateCalendarView() {
    const dayElements = document.querySelectorAll('.psu-calendar-day-clickable');
    
    dayElements.forEach(dayElement => {
        const date = dayElement.getAttribute('data-date');
        const bookings = adminCalendarData[date] || [];
        
        // Remove existing indicators
        dayElement.className = 'psu-calendar-day psu-calendar-day-clickable';
        dayElement.innerHTML = dayElement.textContent;
        
        if (bookings.length > 0) {
            // Add booking count
            const countBadge = document.createElement('div');
            countBadge.className = 'booking-count-badge';
            countBadge.textContent = bookings.length;
            dayElement.appendChild(countBadge);
            
            // Add status indicators
            const statusCounts = {};
            bookings.forEach(booking => {
                statusCounts[booking.status] = (statusCounts[booking.status] || 0) + 1;
            });
            
            // Color priority: rejected > pending > approved
            if (statusCounts.rejected) {
                dayElement.classList.add('has-rejected');
            } else if (statusCounts.pending) {
                dayElement.classList.add('has-pending');
            } else if (statusCounts.approved) {
                dayElement.classList.add('has-approved');
            }
        }
    });
}

function showDateDetails(dateStr, day) {
    const bookings = adminCalendarData[dateStr] || [];
    const detailsContainer = document.getElementById('selected-date-details');
    const titleElement = document.getElementById('selected-date-title');
    const bookingsContainer = document.getElementById('selected-date-bookings');
    
    // Format Thai date
    const thaiDate = formatThaiDate(dateStr);
    titleElement.textContent = `รายการจองวันที่ ${thaiDate}`;
    
    if (bookings.length === 0) {
        bookingsContainer.innerHTML = '<p>ไม่มีการจองในวันนี้</p>';
    } else {
        let html = '';
        bookings.forEach(booking => {
            const statusClass = getStatusClass(booking.status);
            const statusText = getStatusText(booking.status);
            
            html += `
                <div class="booking-item" style="border-left: 4px solid ${getStatusColor(booking.status)}; padding: 15px; margin-bottom: 10px; background: #f9f9f9;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <h4 style="margin: 0 0 5px 0;">#${String(booking.id).padStart(6, '0')} - ${booking.service_name}</h4>
                            <p style="margin: 0 0 5px 0;"><strong>ผู้จอง:</strong> ${booking.customer_name}</p>
                            <p style="margin: 0 0 5px 0;"><strong>เวลา:</strong> ${booking.start_time.substring(0,5)} - ${booking.end_time.substring(0,5)}</p>
                            <p style="margin: 0;"><strong>ราคา:</strong> ${Number(booking.total_price).toLocaleString()} บาท</p>
                        </div>
                        <div style="text-align: right;">
                            <span class="${statusClass}" style="padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white;">${statusText}</span>
                            <div style="margin-top: 10px;">
                                <a href="?page=psu-booking-bookings&action=edit_status&booking_id=${booking.id}" class="button button-small">แก้ไข</a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        bookingsContainer.innerHTML = html;
    }
    
    detailsContainer.style.display = 'block';
}

function getStatusClass(status) {
    switch (status) {
        case 'approved': return 'status-approved';
        case 'pending': return 'status-pending';
        case 'rejected': return 'status-rejected';
        default: return '';
    }
}

function getStatusText(status) {
    switch (status) {
        case 'approved': return 'อนุมัติแล้ว';
        case 'pending': return 'รออนุมัติ';
        case 'rejected': return 'ถูกปฏิเสธ';
        default: return status;
    }
}

function getStatusColor(status) {
    switch (status) {
        case 'approved': return '#28a745';
        case 'pending': return '#ffc107';
        case 'rejected': return '#dc3545';
        default: return '#6c757d';
    }
}

function formatDateString(year, month, day) {
    const yyyy = year.toString();
    const mm = month.toString().padStart(2, '0');
    const dd = day.toString().padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
}

function formatThaiDate(dateStr) {
    const months = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    
    const d = new Date(dateStr);
    const day = d.getDate();
    const month = months[d.getMonth()];
    const year = d.getFullYear() + 543;
    
    return `${day} ${month} ${year}`;
}

function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

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