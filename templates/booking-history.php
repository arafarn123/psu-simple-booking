<?php
/**
 * Template สำหรับประวัติการจอง - Luxury Design
 */
defined('ABSPATH') || exit;

global $wpdb;
$current_user_id = get_current_user_id();

// ดึงประวัติการจองของผู้ใช้ปัจจุบัน
$bookings = $wpdb->get_results($wpdb->prepare("
    SELECT b.*, s.name as service_name, s.description as service_description, s.image_url as service_image
    FROM {$wpdb->prefix}psu_bookings b 
    LEFT JOIN {$wpdb->prefix}psu_services s ON b.service_id = s.id 
    WHERE b.user_id = %d 
    ORDER BY b.booking_date DESC, b.start_time DESC
", $current_user_id));

// ฟังก์ชันแปลงสถานะ
function psu_get_status_text($status) {
    switch($status) {
        case 'pending': return 'รออนุมัติ';
        case 'approved': return 'อนุมัติแล้ว';
        case 'rejected': return 'ไม่อนุมัติ';
        case 'cancelled': return 'ยกเลิกแล้ว';
        default: return $status;
    }
}

// ฟังก์ชันแปลงวันที่
function psu_format_thai_date($date) {
    $thai_months = array(
        '', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    );
    
    $day = date('d', strtotime($date));
    $month = $thai_months[(int)date('m', strtotime($date))];
    $year = date('Y', strtotime($date)) + 543;
    
    return "$day $month $year";
}
?>

<div class="psu-booking-history-container">
    <h2>📚 ประวัติการจองของฉัน</h2>
    
    <?php if (!empty($bookings)): ?>
        <div class="psu-booking-history-list">
            <?php foreach ($bookings as $booking): ?>
                <div class="psu-booking-history-item psu-luxury-accent">
                    <div class="psu-booking-header">
                        <div class="psu-booking-info">
                            <h3><?php echo esc_html($booking->service_name); ?></h3>
                            <p class="psu-booking-date">
                                <span style="color: #3498db;">📅</span>
                                <?php echo psu_format_thai_date($booking->booking_date); ?>
                                <span style="margin-left: 15px; color: #e67e22;">🕐</span>
                                <?php echo date('H:i', strtotime($booking->start_time)); ?> - <?php echo date('H:i', strtotime($booking->end_time)); ?> น.
                            </p>
                        </div>
                        <div class="psu-booking-status">
                            <span class="psu-status psu-status-<?php echo $booking->status; ?>">
                                <?php echo psu_get_status_text($booking->status); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="psu-booking-details">
                        <?php if ($booking->service_description): ?>
                            <div class="psu-booking-detail-row">
                                <strong>📋 รายละเอียดบริการ:</strong> 
                                <span><?php echo esc_html($booking->service_description); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="psu-booking-detail-row">
                            <strong>💰 ราคา:</strong> 
                            <span style="color: var(--psu-primary); font-weight: 600;">
                                <?php echo number_format($booking->total_price, 2); ?> บาท
                            </span>
                        </div>
                        
                        <?php if ($booking->additional_info): ?>
                            <div class="psu-booking-detail-row">
                                <strong>💬 รายละเอียดเพิ่มเติม:</strong> 
                                <span><?php echo esc_html($booking->additional_info); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($booking->status === 'rejected' && $booking->rejection_reason): ?>
                            <div class="psu-rejection-reason">
                                <strong>❌ เหตุผลที่ไม่อนุมัติ:</strong> 
                                <div style="margin-top: 8px;">
                                    <?php echo esc_html($booking->rejection_reason); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($booking->admin_notes): ?>
                            <div class="psu-booking-detail-row">
                                <strong>📝 หมายเหตุจากผู้ดูแล:</strong> 
                                <span><?php echo esc_html($booking->admin_notes); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="psu-booking-detail-row">
                            <strong>⏰ จองเมื่อ:</strong> 
                            <span><?php echo date('d/m/Y H:i น.', strtotime($booking->created_at)); ?></span>
                        </div>
                        
                        <?php if ($booking->updated_at && $booking->updated_at !== $booking->created_at): ?>
                            <div class="psu-booking-detail-row">
                                <strong>🔄 อัปเดตล่าสุด:</strong> 
                                <span><?php echo date('d/m/Y H:i น.', strtotime($booking->updated_at)); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action buttons for bookings -->
                    <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid var(--psu-border-light); text-align: right;">
                        <?php if ($booking->status === 'pending'): ?>
                            <button type="button" class="psu-btn psu-btn-secondary" style="margin-right: 10px; font-size: 14px; padding: 8px 16px;">
                                <span>📧 ส่งอีเมลแจ้งเตือน</span>
                            </button>
                        <?php endif; ?>
                        
                        <?php if (in_array($booking->status, ['pending', 'approved'])): ?>
                            <button type="button" class="psu-btn psu-btn-secondary" style="font-size: 14px; padding: 8px 16px; background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #991b1b; border-color: #ef4444;">
                                <span>❌ ยกเลิกการจอง</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination (if needed) -->
        <?php if (count($bookings) >= 10): ?>
            <div style="text-align: center; margin-top: 40px;">
                <button type="button" class="psu-btn psu-btn-secondary">
                    <span>📄 โหลดเพิ่มเติม</span>
                </button>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="psu-no-bookings">
            <div class="psu-no-bookings-icon">📅</div>
            <h3>ยังไม่มีประวัติการจอง</h3>
            <p>คุณยังไม่เคยจองบริการใดๆ ในระบบ</p>
            <div style="margin-top: 30px;">
                <button type="button" class="psu-btn psu-btn-primary" onclick="window.location.href='#booking-form'">
                    <span>✨ เริ่มจองบริการ</span>
                </button>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Summary Statistics -->
    <?php if (!empty($bookings)): ?>
        <div style="margin-top: 50px; padding: 30px; background: linear-gradient(135deg, var(--psu-cream) 0%, var(--psu-off-white) 100%); border-radius: var(--psu-radius-lg); border: 1px solid var(--psu-border-light); box-shadow: var(--psu-shadow-soft);">
            <h4 style="color: var(--psu-primary); font-family: var(--psu-font-heading); margin: 0 0 25px 0; font-size: 20px; text-align: center;">
                📊 สถิติการจองของฉัน
            </h4>
            
            <?php
            $total_bookings = count($bookings);
            $pending_count = array_filter($bookings, function($b) { return $b->status === 'pending'; });
            $approved_count = array_filter($bookings, function($b) { return $b->status === 'approved'; });
            $rejected_count = array_filter($bookings, function($b) { return $b->status === 'rejected'; });
            $total_spent = array_sum(array_column($bookings, 'total_price'));
            ?>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="text-align: center; padding: 20px; background: var(--psu-white); border-radius: var(--psu-radius); border: 1px solid var(--psu-border-light);">
                    <div style="font-size: 24px; margin-bottom: 8px;">📋</div>
                    <div style="font-size: 24px; font-weight: 700; color: var(--psu-primary); margin-bottom: 5px;">
                        <?php echo $total_bookings; ?>
                    </div>
                    <div style="color: var(--psu-text-light); font-size: 14px;">การจองทั้งหมด</div>
                </div>
                
                <div style="text-align: center; padding: 20px; background: var(--psu-white); border-radius: var(--psu-radius); border: 1px solid var(--psu-border-light);">
                    <div style="font-size: 24px; margin-bottom: 8px;">⏳</div>
                    <div style="font-size: 24px; font-weight: 700; color: #f39c12; margin-bottom: 5px;">
                        <?php echo count($pending_count); ?>
                    </div>
                    <div style="color: var(--psu-text-light); font-size: 14px;">รออนุมัติ</div>
                </div>
                
                <div style="text-align: center; padding: 20px; background: var(--psu-white); border-radius: var(--psu-radius); border: 1px solid var(--psu-border-light);">
                    <div style="font-size: 24px; margin-bottom: 8px;">✅</div>
                    <div style="font-size: 24px; font-weight: 700; color: #27ae60; margin-bottom: 5px;">
                        <?php echo count($approved_count); ?>
                    </div>
                    <div style="color: var(--psu-text-light); font-size: 14px;">อนุมัติแล้ว</div>
                </div>
                
                <div style="text-align: center; padding: 20px; background: var(--psu-white); border-radius: var(--psu-radius); border: 1px solid var(--psu-border-light);">
                    <div style="font-size: 24px; margin-bottom: 8px;">💰</div>
                    <div style="font-size: 20px; font-weight: 700; color: var(--psu-primary); margin-bottom: 5px;">
                        <?php echo number_format($total_spent, 0); ?>
                    </div>
                    <div style="color: var(--psu-text-light); font-size: 14px;">บาท (รวม)</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Add some interactivity to booking history
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers for action buttons
    const cancelButtons = document.querySelectorAll('.psu-booking-history-item button[style*="color: #991b1b"]');
    cancelButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('คุณต้องการยกเลิกการจองนี้หรือไม่?')) {
                // Handle cancellation
                alert('ฟีเจอร์นี้จะพัฒนาในอนาคต');
            }
        });
    });
    
    // Add smooth hover effects
    const historyItems = document.querySelectorAll('.psu-booking-history-item');
    historyItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});
</script> 