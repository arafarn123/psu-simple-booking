<?php
/**
 * Admin Dashboard
 */
defined('ABSPATH') || exit;

global $wpdb;

// ดึงสถิติต่างๆ
$total_services = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psu_services WHERE status = 1");
$total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings");
$pending_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings WHERE status = 'pending'");
$approved_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}psu_bookings WHERE status = 'approved'");

// การจองล่าสุด
$recent_bookings = $wpdb->get_results("
    SELECT b.*, s.name as service_name 
    FROM {$wpdb->prefix}psu_bookings b 
    LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id 
    ORDER BY b.created_at DESC 
    LIMIT 10
");

// การจองวันนี้
$today_bookings = $wpdb->get_results("
    SELECT b.*, s.name as service_name 
    FROM {$wpdb->prefix}psu_bookings b 
    LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id 
    WHERE DATE(b.booking_date) = CURDATE()
    ORDER BY b.start_time ASC
");
?>

<div class="wrap">
    <h1>PSU Simple Booking - แดชบอร์ด</h1>
    
    <!-- สถิติภาพรวม -->
    <div class="psu-dashboard-stats">
        <div class="psu-stat-card">
            <div class="psu-stat-icon">📅</div>
            <div class="psu-stat-content">
                <h3><?php echo number_format($total_bookings); ?></h3>
                <p>การจองทั้งหมด</p>
            </div>
        </div>
        
        <div class="psu-stat-card pending">
            <div class="psu-stat-icon">⏰</div>
            <div class="psu-stat-content">
                <h3><?php echo number_format($pending_bookings); ?></h3>
                <p>รออนุมัติ</p>
            </div>
        </div>
        
        <div class="psu-stat-card approved">
            <div class="psu-stat-icon">✅</div>
            <div class="psu-stat-content">
                <h3><?php echo number_format($approved_bookings); ?></h3>
                <p>อนุมัติแล้ว</p>
            </div>
        </div>
        
        <div class="psu-stat-card">
            <div class="psu-stat-icon">🛠️</div>
            <div class="psu-stat-content">
                <h3><?php echo number_format($total_services); ?></h3>
                <p>บริการ</p>
            </div>
        </div>
    </div>
    
    <div class="psu-dashboard-content">
        <!-- การจองวันนี้ -->
        <div class="psu-dashboard-section">
            <h2>การจองวันนี้</h2>
            <?php if (!empty($today_bookings)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>เวลา</th>
                            <th>บริการ</th>
                            <th>ผู้จอง</th>
                            <th>สถานะ</th>
                            <th>ราคา</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($today_bookings as $booking): ?>
                            <tr>
                                <td>
                                    <?php echo date('H:i', strtotime($booking->start_time)); ?> - 
                                    <?php echo date('H:i', strtotime($booking->end_time)); ?>
                                </td>
                                <td><?php echo esc_html($booking->service_name); ?></td>
                                <td><?php echo esc_html($booking->customer_name); ?></td>
                                <td>
                                    <span class="psu-status psu-status-<?php echo $booking->status; ?>">
                                        <?php 
                                        switch($booking->status) {
                                            case 'pending': echo 'รออนุมัติ'; break;
                                            case 'approved': echo 'อนุมัติ'; break;
                                            case 'rejected': echo 'ไม่อนุมัติ'; break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($booking->total_price, 2); ?> บาท</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>ไม่มีการจองวันนี้</p>
            <?php endif; ?>
        </div>
        
        <!-- การจองล่าสุด -->
        <div class="psu-dashboard-section">
            <h2>การจองล่าสุด</h2>
            <?php if (!empty($recent_bookings)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>วันที่จอง</th>
                            <th>เวลา</th>
                            <th>บริการ</th>
                            <th>ผู้จอง</th>
                            <th>สถานะ</th>
                            <th>วันที่สร้าง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bookings as $booking): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($booking->booking_date)); ?></td>
                                <td>
                                    <?php echo date('H:i', strtotime($booking->start_time)); ?> - 
                                    <?php echo date('H:i', strtotime($booking->end_time)); ?>
                                </td>
                                <td><?php echo esc_html($booking->service_name); ?></td>
                                <td><?php echo esc_html($booking->customer_name); ?></td>
                                <td>
                                    <span class="psu-status psu-status-<?php echo $booking->status; ?>">
                                        <?php 
                                        switch($booking->status) {
                                            case 'pending': echo 'รออนุมัติ'; break;
                                            case 'approved': echo 'อนุมัติ'; break;
                                            case 'rejected': echo 'ไม่อนุมัติ'; break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($booking->created_at)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p>
                    <a href="<?php echo admin_url('admin.php?page=psu-booking-bookings'); ?>" class="button button-primary">
                        ดูการจองทั้งหมด
                    </a>
                </p>
            <?php else: ?>
                <p>ยังไม่มีการจอง</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- เมนูด่วน -->
    <div class="psu-quick-actions">
        <h2>เมนูด่วน</h2>
        <div class="psu-quick-actions-grid">
            <a href="<?php echo admin_url('admin.php?page=psu-booking-services'); ?>" class="psu-quick-action">
                <div class="psu-quick-action-icon">🛠️</div>
                <div class="psu-quick-action-title">จัดการบริการ</div>
                <div class="psu-quick-action-desc">เพิ่ม แก้ไข ลบบริการ</div>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=psu-booking-bookings'); ?>" class="psu-quick-action">
                <div class="psu-quick-action-icon">📅</div>
                <div class="psu-quick-action-title">จัดการการจอง</div>
                <div class="psu-quick-action-desc">อนุมัติ ยกเลิก การจอง</div>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=psu-booking-stats'); ?>" class="psu-quick-action">
                <div class="psu-quick-action-icon">📊</div>
                <div class="psu-quick-action-title">ดูสถิติ</div>
                <div class="psu-quick-action-desc">รายงานและสถิติการจอง</div>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=psu-booking-settings'); ?>" class="psu-quick-action">
                <div class="psu-quick-action-icon">⚙️</div>
                <div class="psu-quick-action-title">ตั้งค่า</div>
                <div class="psu-quick-action-desc">ตั้งค่าระบบและการแจ้งเตือน</div>
            </a>
        </div>
    </div>
</div>
