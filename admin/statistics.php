<?php
// ป้องกันการเข้าถึงโดยตรง
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// รับค่าพารามิเตอร์สำหรับกรอง
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : '';
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : '';

// สร้างเงื่อนไขสำหรับการกรอง
$where_conditions = array();
$where_params = array();

$where_conditions[] = "YEAR(booking_date) = %d";
$where_params[] = $year;

if ($month) {
    $where_conditions[] = "MONTH(booking_date) = %d";
    $where_params[] = $month;
}

if ($service_id) {
    $where_conditions[] = "service_id = %d";
    $where_params[] = $service_id;
}

$where_clause = implode(' AND ', $where_conditions);

// สถิติรวม
$total_bookings = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings WHERE $where_clause",
    $where_params
));

$total_revenue = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(total_price) FROM {$wpdb->prefix}psu_bookings WHERE $where_clause AND status = 'approved'",
    $where_params
));

$approved_bookings = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings WHERE $where_clause AND status = 'approved'",
    $where_params
));

$pending_bookings = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings WHERE $where_clause AND status = 'pending'",
    $where_params
));

$rejected_bookings = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings WHERE $where_clause AND status = 'rejected'",
    $where_params
));

// สถิติตามบริการ
$service_stats = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        s.name as service_name,
        s.category,
        COUNT(b.id) as booking_count,
        SUM(CASE WHEN b.status = 'approved' THEN b.total_price ELSE 0 END) as revenue,
        SUM(CASE WHEN b.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN b.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM {$wpdb->prefix}psu_services s
    LEFT JOIN {$wpdb->prefix}psu_bookings b ON s.id = b.service_id AND $where_clause
    WHERE s.status = 1
    GROUP BY s.id, s.name, s.category
    ORDER BY booking_count DESC",
    $where_params
));

// สถิติรายเดือน (สำหรับ chart)
$monthly_stats = $wpdb->get_results($wpdb->prepare(
    "SELECT 
        MONTH(booking_date) as month,
        MONTHNAME(booking_date) as month_name,
        COUNT(*) as booking_count,
        SUM(CASE WHEN status = 'approved' THEN total_price ELSE 0 END) as revenue
    FROM {$wpdb->prefix}psu_bookings 
    WHERE YEAR(booking_date) = %d
    GROUP BY MONTH(booking_date), MONTHNAME(booking_date)
    ORDER BY MONTH(booking_date)",
    $year
));

// สถิติรายวัน (สำหรับปฏิทิน)
$daily_stats = array();
if ($month) {
    $daily_results = $wpdb->get_results($wpdb->prepare(
        "SELECT 
            DAY(booking_date) as day,
            COUNT(*) as booking_count,
            SUM(CASE WHEN status = 'approved' THEN total_price ELSE 0 END) as revenue
        FROM {$wpdb->prefix}psu_bookings 
        WHERE YEAR(booking_date) = %d AND MONTH(booking_date) = %d
        GROUP BY DAY(booking_date)
        ORDER BY DAY(booking_date)",
        $year, $month
    ));
    
    foreach ($daily_results as $day_stat) {
        $daily_stats[$day_stat->day] = $day_stat;
    }
}

// ดึงข้อมูลบริการสำหรับ dropdown
$services = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}psu_services WHERE status = 1 ORDER BY name");

// สร้างข้อมูลสำหรับ charts
$monthly_labels = array();
$monthly_bookings = array();
$monthly_revenues = array();

for ($i = 1; $i <= 12; $i++) {
    $monthly_labels[] = date('M', mktime(0, 0, 0, $i, 1));
    $found = false;
    foreach ($monthly_stats as $stat) {
        if ($stat->month == $i) {
            $monthly_bookings[] = intval($stat->booking_count);
            $monthly_revenues[] = floatval($stat->revenue);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $monthly_bookings[] = 0;
        $monthly_revenues[] = 0;
    }
}

?>

<div class="wrap">
    <h1>สถิติและรายงาน</h1>

    <!-- ตัวกรอง -->
    <div class="psu-filter-section">
        <form method="get" class="psu-filter-form">
            <input type="hidden" name="page" value="psu-booking-stats">
            
            <div class="psu-filter-group">
                <label for="year">ปี:</label>
                <select name="year" id="year">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>><?php echo $y + 543; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="psu-filter-group">
                <label for="month">เดือน:</label>
                <select name="month" id="month">
                    <option value="">ทุกเดือน</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php selected($month, $m); ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="psu-filter-group">
                <label for="service_id">บริการ:</label>
                <select name="service_id" id="service_id">
                    <option value="">ทุกบริการ</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?php echo $service->id; ?>" <?php selected($service_id, $service->id); ?>>
                            <?php echo esc_html($service->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" class="button button-primary">กรอง</button>
            <a href="?page=psu-booking-stats" class="button">รีเซ็ต</a>
        </form>
    </div>

    <!-- สถิติรวม -->
    <div class="psu-stats-overview">
        <div class="psu-stat-card psu-stat-primary">
            <div class="psu-stat-icon">📊</div>
            <div class="psu-stat-content">
                <div class="psu-stat-number"><?php echo number_format($total_bookings); ?></div>
                <div class="psu-stat-label">จำนวนการจองทั้งหมด</div>
            </div>
        </div>
        
        <div class="psu-stat-card psu-stat-success">
            <div class="psu-stat-icon">💰</div>
            <div class="psu-stat-content">
                <div class="psu-stat-number"><?php echo number_format($total_revenue ?: 0, 2); ?></div>
                <div class="psu-stat-label">รายได้รวม (บาท)</div>
            </div>
        </div>
        
        <div class="psu-stat-card psu-stat-info">
            <div class="psu-stat-icon">✅</div>
            <div class="psu-stat-content">
                <div class="psu-stat-number"><?php echo number_format($approved_bookings); ?></div>
                <div class="psu-stat-label">อนุมัติแล้ว</div>
            </div>
        </div>
        
        <div class="psu-stat-card psu-stat-warning">
            <div class="psu-stat-icon">⏳</div>
            <div class="psu-stat-content">
                <div class="psu-stat-number"><?php echo number_format($pending_bookings); ?></div>
                <div class="psu-stat-label">รออนุมัติ</div>
            </div>
        </div>
        
        <div class="psu-stat-card psu-stat-danger">
            <div class="psu-stat-icon">❌</div>
            <div class="psu-stat-content">
                <div class="psu-stat-number"><?php echo number_format($rejected_bookings); ?></div>
                <div class="psu-stat-label">ถูกปฏิเสธ</div>
            </div>
        </div>
    </div>

    <!-- แผนภูมิ -->
    <div class="psu-charts-section">
        <div class="psu-chart-container">
            <div class="psu-chart-half">
                <h3>จำนวนการจองรายเดือน</h3>
                <canvas id="bookingChart"></canvas>
            </div>
            <div class="psu-chart-half">
                <h3>รายได้รายเดือน</h3>
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <!-- ปฏิทินสถิติ (แสดงเมื่อเลือกเดือนเฉพาะ) -->
    <?php if ($month): ?>
    <div class="psu-calendar-section">
        <h3>ปฏิทินการจอง - <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h3>
        <div class="psu-calendar">
            <?php
            $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $first_day = date('w', mktime(0, 0, 0, $month, 1, $year));
            
            echo '<div class="psu-calendar-header">';
            $day_names = array('อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส');
            foreach ($day_names as $day_name) {
                echo '<div class="psu-calendar-day-name">' . $day_name . '</div>';
            }
            echo '</div>';
            
            echo '<div class="psu-calendar-body">';
            
            // เว้นวันก่อนวันที่ 1
            for ($i = 0; $i < $first_day; $i++) {
                echo '<div class="psu-calendar-day psu-calendar-empty"></div>';
            }
            
            // แสดงวันในเดือน
            for ($day = 1; $day <= $days_in_month; $day++) {
                $stat = isset($daily_stats[$day]) ? $daily_stats[$day] : null;
                $booking_count = $stat ? $stat->booking_count : 0;
                $revenue = $stat ? $stat->revenue : 0;
                
                $class = 'psu-calendar-day';
                if ($booking_count > 0) {
                    $class .= ' psu-calendar-has-booking';
                }
                
                echo '<div class="' . $class . '" title="วันที่ ' . $day . ': ' . $booking_count . ' การจอง, รายได้ ' . number_format($revenue, 2) . ' บาท">';
                echo '<div class="psu-calendar-date">' . $day . '</div>';
                if ($booking_count > 0) {
                    echo '<div class="psu-calendar-bookings">' . $booking_count . '</div>';
                }
                echo '</div>';
            }
            
            echo '</div>';
            ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- สถิติตามบริการ -->
    <div class="psu-service-stats">
        <h3>สถิติตามบริการ</h3>
        
        <?php if (empty($service_stats)): ?>
            <p>ไม่มีข้อมูลสถิติ</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="manage-column">บริการ</th>
                        <th class="manage-column">หมวดหมู่</th>
                        <th class="manage-column">จำนวนการจอง</th>
                        <th class="manage-column">อนุมัติแล้ว</th>
                        <th class="manage-column">รออนุมัติ</th>
                        <th class="manage-column">ถูกปฏิเสธ</th>
                        <th class="manage-column">รายได้</th>
                        <th class="manage-column">อัตราการอนุมัติ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($service_stats as $stat): ?>
                        <tr>
                            <td><strong><?php echo esc_html($stat->service_name); ?></strong></td>
                            <td><?php echo esc_html($stat->category); ?></td>
                            <td><?php echo number_format($stat->booking_count); ?></td>
                            <td class="psu-stat-approved"><?php echo number_format($stat->approved_count); ?></td>
                            <td class="psu-stat-pending"><?php echo number_format($stat->pending_count); ?></td>
                            <td class="psu-stat-rejected"><?php echo number_format($stat->rejected_count); ?></td>
                            <td><strong><?php echo number_format($stat->revenue, 2); ?> บาท</strong></td>
                            <td>
                                <?php 
                                $approval_rate = $stat->booking_count > 0 ? ($stat->approved_count / $stat->booking_count) * 100 : 0;
                                echo number_format($approval_rate, 1) . '%';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- ส่งออกรายงาน -->
    <div class="psu-export-section">
        <h3>ส่งออกรายงาน</h3>
        <p>ส่งออกข้อมูลสถิติและรายงานในรูปแบบต่างๆ</p>
        
        <div class="psu-export-buttons">
            <button class="button button-primary" onclick="exportReport('excel')">ส่งออก Excel</button>
            <button class="button" onclick="exportReport('pdf')">ส่งออก PDF</button>
            <button class="button" onclick="exportReport('csv')">ส่งออก CSV</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ข้อมูลสำหรับ charts
const monthlyLabels = <?php echo json_encode($monthly_labels); ?>;
const monthlyBookings = <?php echo json_encode($monthly_bookings); ?>;
const monthlyRevenues = <?php echo json_encode($monthly_revenues); ?>;

// แผนภูมิจำนวนการจอง
const bookingCtx = document.getElementById('bookingChart').getContext('2d');
new Chart(bookingCtx, {
    type: 'line',
    data: {
        labels: monthlyLabels,
        datasets: [{
            label: 'จำนวนการจอง',
            data: monthlyBookings,
            borderColor: '#2B3F6A',
            backgroundColor: 'rgba(43, 63, 106, 0.1)',
            borderWidth: 2,
            fill: true
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// แผนภูมิรายได้
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: monthlyLabels,
        datasets: [{
            label: 'รายได้ (บาท)',
            data: monthlyRevenues,
            backgroundColor: '#2B3F6A',
            borderColor: '#2B3F6A',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + ' บาท';
                    }
                }
            }
        }
    }
});

function exportReport(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    
    // TODO: Implement export functionality
    alert('ฟีเจอร์ส่งออกรายงาน ' + format.toUpperCase() + ' จะพัฒนาในเวอร์ชั่นถัดไป');
}
</script>

<style>
.psu-filter-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.psu-filter-form {
    display: flex;
    gap: 20px;
    align-items: end;
}

.psu-filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.psu-filter-group label {
    font-weight: 600;
    color: #50575e;
}

.psu-stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.psu-stat-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.psu-stat-primary { border-left: 4px solid #2B3F6A; }
.psu-stat-success { border-left: 4px solid #00a32a; }
.psu-stat-info { border-left: 4px solid #2271b1; }
.psu-stat-warning { border-left: 4px solid #dba617; }
.psu-stat-danger { border-left: 4px solid #d63638; }

.psu-stat-icon {
    font-size: 24px;
}

.psu-stat-number {
    font-size: 24px;
    font-weight: 700;
    line-height: 1.2;
    color: #1d2327;
}

.psu-stat-label {
    color: #50575e;
    font-size: 14px;
}

.psu-charts-section {
    margin: 30px 0;
}

.psu-chart-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.psu-chart-half {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
}

.psu-chart-half h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #1d2327;
}

.psu-calendar-section {
    margin: 30px 0;
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
}

.psu-calendar {
    max-width: 700px;
}

.psu-calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    margin-bottom: 10px;
}

.psu-calendar-day-name {
    text-align: center;
    font-weight: 600;
    padding: 10px;
    background: #f1f1f1;
}

.psu-calendar-body {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
}

.psu-calendar-day {
    aspect-ratio: 1;
    border: 1px solid #e1e1e1;
    padding: 5px;
    position: relative;
    background: #fff;
    cursor: default;
}

.psu-calendar-day.psu-calendar-has-booking {
    background: #e8f4fd;
    border-color: #2B3F6A;
}

.psu-calendar-empty {
    background: #f9f9f9;
}

.psu-calendar-date {
    font-weight: 600;
    font-size: 12px;
}

.psu-calendar-bookings {
    position: absolute;
    bottom: 2px;
    right: 2px;
    background: #2B3F6A;
    color: white;
    font-size: 10px;
    padding: 1px 4px;
    border-radius: 2px;
}

.psu-service-stats {
    margin: 30px 0;
}

.psu-service-stats h3 {
    margin-bottom: 15px;
}

.psu-stat-approved { color: #00a32a; font-weight: 600; }
.psu-stat-pending { color: #dba617; font-weight: 600; }
.psu-stat-rejected { color: #d63638; font-weight: 600; }

.psu-export-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    margin: 30px 0;
}

.psu-export-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

@media (max-width: 768px) {
    .psu-filter-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .psu-chart-container {
        grid-template-columns: 1fr;
    }
    
    .psu-stats-overview {
        grid-template-columns: 1fr;
    }
}
</style> 