<?php
/**
 * Template สำหรับประวัติการจอง
 */
defined('ABSPATH') || exit;

global $wpdb;
$current_user_id = get_current_user_id();

// ดึงประวัติการจองของผู้ใช้ปัจจุบัน
$bookings = $wpdb->get_results($wpdb->prepare("
    SELECT b.*, s.name as service_name, s.description as service_description
    FROM {$wpdb->prefix}psu_bookings b 
    LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id 
    WHERE b.user_id = %d 
    ORDER BY b.booking_date DESC, b.start_time DESC
", $current_user_id));
?>

<div class="psu-booking-history-container">
    <h2>ประวัติการจองของฉัน</h2>
    
    <?php if (!empty($bookings)): ?>
        <div class="psu-booking-history-list">
            <?php foreach ($bookings as $booking): ?>
                <div class="psu-booking-history-item">
                    <div class="psu-booking-header">
                        <div class="psu-booking-info">
                            <h3><?php echo esc_html($booking->service_name); ?></h3>
                            <p class="psu-booking-date">
                                📅 <?php echo date('d/m/Y', strtotime($booking->booking_date)); ?> 
                                🕐 <?php echo date('H:i', strtotime($booking->start_time)); ?> - <?php echo date('H:i', strtotime($booking->end_time)); ?>
                            </p>
                        </div>
                        <div class="psu-booking-status">
                            <span class="psu-status psu-status-<?php echo $booking->status; ?>">
                                <?php 
                                switch($booking->status) {
                                    case 'pending': echo 'รออนุมัติ'; break;
                                    case 'approved': echo 'อนุมัติแล้ว'; break;
                                    case 'rejected': echo 'ไม่อนุมัติ'; break;
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="psu-booking-details">
                        <div class="psu-booking-detail-row">
                            <strong>รายละเอียดบริการ:</strong> 
                            <?php echo esc_html($booking->service_description); ?>
                        </div>
                        
                        <div class="psu-booking-detail-row">
                            <strong>ราคา:</strong> 
                            <?php echo number_format($booking->total_price, 2); ?> บาท
                        </div>
                        
                        <?php if ($booking->additional_info): ?>
                            <div class="psu-booking-detail-row">
                                <strong>รายละเอียดเพิ่มเติม:</strong> 
                                <?php echo esc_html($booking->additional_info); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($booking->status === 'rejected' && $booking->rejection_reason): ?>
                            <div class="psu-booking-detail-row psu-rejection-reason">
                                <strong>เหตุผลที่ไม่อนุมัติ:</strong> 
                                <?php echo esc_html($booking->rejection_reason); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="psu-booking-detail-row">
                            <strong>จองเมื่อ:</strong> 
                            <?php echo date('d/m/Y H:i น.', strtotime($booking->created_at)); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="psu-no-bookings">
            <div class="psu-no-bookings-icon">📅</div>
            <h3>ยังไม่มีประวัติการจอง</h3>
            <p>คุณยังไม่เคยจองบริการใดๆ</p>
        </div>
    <?php endif; ?>
</div>

<style>
.psu-booking-history-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.psu-booking-history-container h2 {
    text-align: center;
    color: #2B3F6A;
    margin-bottom: 30px;
    font-size: 24px;
}

.psu-booking-history-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.psu-booking-history-item {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: box-shadow 0.2s ease;
}

.psu-booking-history-item:hover {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.psu-booking-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #f3f4f6;
}

.psu-booking-info h3 {
    margin: 0 0 8px 0;
    color: #2B3F6A;
    font-size: 18px;
    font-weight: 600;
}

.psu-booking-date {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}

.psu-booking-status {
    flex-shrink: 0;
}

.psu-status {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.psu-status-pending {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #f59e0b;
}

.psu-status-approved {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}

.psu-status-rejected {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #ef4444;
}

.psu-booking-details {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.psu-booking-detail-row {
    font-size: 14px;
    line-height: 1.5;
}

.psu-booking-detail-row strong {
    color: #374151;
}

.psu-rejection-reason {
    background: #fef2f2;
    padding: 10px;
    border-radius: 6px;
    border-left: 3px solid #ef4444;
}

.psu-rejection-reason strong {
    color: #991b1b;
}

.psu-no-bookings {
    text-align: center;
    padding: 60px 20px;
    background: #ffffff;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
}

.psu-no-bookings-icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.psu-no-bookings h3 {
    margin: 0 0 10px 0;
    color: #6b7280;
    font-size: 20px;
}

.psu-no-bookings p {
    margin: 0;
    color: #9ca3af;
    font-size: 16px;
}

/* Responsive */
@media (max-width: 768px) {
    .psu-booking-history-container {
        padding: 15px;
    }
    
    .psu-booking-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .psu-booking-status {
        align-self: flex-start;
    }
    
    .psu-booking-history-item {
        padding: 15px;
    }
}

@media (max-width: 480px) {
    .psu-booking-history-container h2 {
        font-size: 20px;
    }
    
    .psu-booking-info h3 {
        font-size: 16px;
    }
    
    .psu-booking-date {
        font-size: 13px;
    }
    
    .psu-status {
        font-size: 11px;
        padding: 4px 8px;
    }
}
</style> 