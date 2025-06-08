<?php
/**
 * Template สำหรับประวัติการจอง
 */

if (!defined('ABSPATH')) {
    exit;
}

$user_id = get_current_user_id();
global $wpdb;

// ดึงสถิติการจอง
$stats = array(
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'cancelled' => 0,
    'this_month' => 0,
    'total_spent' => 0
);

$stats_query = $wpdb->get_results($wpdb->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) THEN 1 ELSE 0 END) as this_month,
        SUM(CASE WHEN status IN ('approved', 'pending') THEN total_price ELSE 0 END) as total_spent
    FROM {$wpdb->prefix}psu_bookings 
    WHERE user_id = %d OR customer_email = %s
", $user_id, wp_get_current_user()->user_email));

if (!empty($stats_query)) {
    $stats = (array) $stats_query[0];
}
?>

<div class="psu-booking-history-container">
    <!-- Header -->
    <div class="psu-history-header">
        <h2>📋 ประวัติการจองของฉัน</h2>
        <div class="psu-history-actions">
            <button class="psu-btn psu-btn-secondary" onclick="toggleView('list')" id="btn-list-view">
                📄 รายการ
            </button>
            <button class="psu-btn psu-btn-secondary" onclick="toggleView('calendar')" id="btn-calendar-view">
                📅 ปฏิทิน
            </button>
        </div>
    </div>

    <!-- สถิติ Dashboard -->
    <div class="psu-stats-dashboard">
        <div class="psu-stats-grid">
            <div class="psu-stat-card total">
                <div class="psu-stat-icon">📊</div>
                <div class="psu-stat-content">
                    <div class="psu-stat-number"><?php echo number_format($stats['total']); ?></div>
                    <div class="psu-stat-label">การจองทั้งหมด</div>
                </div>
            </div>
            
            <div class="psu-stat-card pending">
                <div class="psu-stat-icon">⏳</div>
                <div class="psu-stat-content">
                    <div class="psu-stat-number"><?php echo number_format($stats['pending']); ?></div>
                    <div class="psu-stat-label">รออนุมัติ</div>
                </div>
            </div>
            
            <div class="psu-stat-card approved">
                <div class="psu-stat-icon">✅</div>
                <div class="psu-stat-content">
                    <div class="psu-stat-number"><?php echo number_format($stats['approved']); ?></div>
                    <div class="psu-stat-label">อนุมัติแล้ว</div>
                </div>
            </div>
            
            <div class="psu-stat-card month">
                <div class="psu-stat-icon">📅</div>
                <div class="psu-stat-content">
                    <div class="psu-stat-number"><?php echo number_format($stats['this_month']); ?></div>
                    <div class="psu-stat-label">เดือนนี้</div>
                </div>
            </div>
            
            <div class="psu-stat-card money">
                <div class="psu-stat-icon">💰</div>
                <div class="psu-stat-content">
                    <div class="psu-stat-number"><?php echo number_format($stats['total_spent']); ?></div>
                    <div class="psu-stat-label">ค่าใช้จ่ายรวม (บาท)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="psu-history-controls">
        <div class="psu-search-section">
            <div class="psu-search-box">
                <input type="text" 
                       id="booking-search" 
                       placeholder="🔍 ค้นหาตามชื่อบริการ, วันที่, หรือหมายเลขการจอง..." 
                       class="psu-search-input">
            </div>
            
            <div class="psu-filter-section">
                <select id="status-filter" class="psu-filter-select">
                    <option value="">🔽 สถานะทั้งหมด</option>
                    <option value="pending">⏳ รออนุมัติ</option>
                    <option value="approved">✅ อนุมัติแล้ว</option>
                    <option value="rejected">❌ ปฏิเสธ</option>
                    <option value="cancelled">🚫 ยกเลิก</option>
                </select>
                
                <select id="month-filter" class="psu-filter-select">
                    <option value="">📅 เดือนทั้งหมด</option>
                    <option value="1">มกราคม</option>
                    <option value="2">กุมภาพันธ์</option>
                    <option value="3">มีนาคม</option>
                    <option value="4">เมษายน</option>
                    <option value="5">พฤษภาคม</option>
                    <option value="6">มิถุนายน</option>
                    <option value="7">กรกฎาคม</option>
                    <option value="8">สิงหาคม</option>
                    <option value="9">กันยายน</option>
                    <option value="10">ตุลาคม</option>
                    <option value="11">พฤศจิกายน</option>
                    <option value="12">ธันวาคม</option>
                </select>
                
                <button class="psu-btn psu-btn-secondary" onclick="clearFilters()">
                    🔄 ล้างตัวกรอง
                </button>
            </div>
        </div>
    </div>

    <!-- List View -->
    <div id="list-view" class="psu-view-container">
        <div class="psu-bookings-list">
            <div id="bookings-container">
                <div class="psu-loading-center">
                    <div class="psu-spinner"></div>
                    <p>กำลังโหลดข้อมูล...</p>
                </div>
            </div>
            <div id="pagination-container" class="psu-pagination"></div>
        </div>
    </div>

    <!-- Calendar View -->
    <div id="calendar-view" class="psu-view-container" style="display: none;">
        <div class="psu-calendar-section">
            <div class="psu-calendar-header">
                <button class="psu-btn psu-btn-icon" onclick="changeCalendarMonth(-1)">‹</button>
                <h3 id="calendar-month-year"></h3>
                <button class="psu-btn psu-btn-icon" onclick="changeCalendarMonth(1)">›</button>
            </div>
            
            <div class="psu-calendar-grid" id="calendar-grid">
                <!-- จะถูกสร้างด้วย JavaScript -->
            </div>
            
            <div class="psu-calendar-legend">
                <div class="psu-legend-item">
                    <span class="psu-legend-color approved"></span>
                    <span>อนุมัติแล้ว</span>
                </div>
                <div class="psu-legend-item">
                    <span class="psu-legend-color pending"></span>
                    <span>รออนุมัติ</span>
                </div>
                <div class="psu-legend-item">
                    <span class="psu-legend-color rejected"></span>
                    <span>ปฏิเสธ</span>
                </div>
                <div class="psu-legend-item">
                    <span class="psu-legend-color cancelled"></span>
                    <span>ยกเลิก</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal สำหรับดูรายละเอียด -->
<div id="booking-detail-modal" class="psu-modal" style="display: none;">
    <div class="psu-modal-content">
        <div class="psu-modal-header">
            <h3>📋 รายละเอียดการจอง</h3>
            <button class="psu-modal-close" onclick="closeBookingModal()">&times;</button>
        </div>
        
        <div class="psu-modal-body" id="booking-detail-content">
            <!-- เนื้อหาจะถูกโหลดด้วย AJAX -->
        </div>
        
        <div class="psu-modal-footer">
            <button class="psu-btn psu-btn-secondary" onclick="closeBookingModal()">
                ปิด
            </button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo PSU_BOOKING_PLUGIN_URL; ?>assets/css/booking-history.css?v=<?php echo PSU_BOOKING_VERSION; ?>">

<script type="text/javascript">
// PSU Booking History JavaScript
(function($) {
    'use strict';
    
    let currentView = 'list';
    let currentPage = 1;
    let currentFilters = {
        search: '',
        status: '',
        month: ''
    };
    let calendarDate = new Date();
    
    $(document).ready(function() {
        initBookingHistory();
    });
    
    function initBookingHistory() {
        loadBookings();
        updateViewButtons();
        bindEvents();
    }
    
    function bindEvents() {
        // Search input
        $('#booking-search').on('input', debounce(function() {
            currentFilters.search = $(this).val();
            currentPage = 1;
            loadBookings();
        }, 500));
        
        // Filter changes
        $('#status-filter, #month-filter').on('change', function() {
            currentFilters.status = $('#status-filter').val();
            currentFilters.month = $('#month-filter').val();
            currentPage = 1;
            loadBookings();
        });
        
        // Modal close events
        $(document).on('click', '.psu-modal', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
        
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                $('.psu-modal').hide();
            }
        });
    }
    
    // Global functions
    window.toggleView = function(view) {
        currentView = view;
        updateViewButtons();
        
        if (view === 'list') {
            $('#list-view').show();
            $('#calendar-view').hide();
            loadBookings();
        } else {
            $('#list-view').hide();
            $('#calendar-view').show();
            loadCalendar();
        }
    };
    
    window.changePage = function(page) {
        currentPage = page;
        loadBookings();
    };
    
    window.clearFilters = function() {
        $('#booking-search').val('');
        $('#status-filter').val('');
        $('#month-filter').val('');
        currentFilters = { search: '', status: '', month: '' };
        currentPage = 1;
        loadBookings();
    };
    
    window.showBookingDetail = function(bookingId) {
        $('#booking-detail-modal').show();
        $('#booking-detail-content').html(getLoadingHTML());
        
        $.ajax({
            url: psu_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'psu_get_booking_detail',
                nonce: psu_ajax.nonce,
                booking_id: bookingId
            },
            success: function(response) {
                if (response.success) {
                    renderBookingDetail(response.data);
                } else {
                    $('#booking-detail-content').html(getErrorHTML('ไม่สามารถโหลดรายละเอียดได้'));
                }
            },
            error: function() {
                $('#booking-detail-content').html(getErrorHTML('เกิดข้อผิดพลาดในการเชื่อมต่อ'));
            }
        });
    };
    
    window.closeBookingModal = function() {
        $('#booking-detail-modal').hide();
    };
    
    window.changeCalendarMonth = function(direction) {
        calendarDate.setMonth(calendarDate.getMonth() + direction);
        loadCalendar();
    };
    
    window.showDayBookings = function(date) {
        currentFilters.search = date;
        currentView = 'list';
        toggleView('list');
    };
    
    // Private functions
    function updateViewButtons() {
        $('.psu-history-actions button').removeClass('psu-btn-primary').addClass('psu-btn-secondary');
        if (currentView === 'list') {
            $('#btn-list-view').removeClass('psu-btn-secondary').addClass('psu-btn-primary');
        } else {
            $('#btn-calendar-view').removeClass('psu-btn-secondary').addClass('psu-btn-primary');
        }
    }
    
    function loadBookings() {
        $('#bookings-container').html(getLoadingHTML());
        
        $.ajax({
            url: psu_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'psu_get_user_bookings',
                nonce: psu_ajax.nonce,
                page: currentPage,
                filters: currentFilters
            },
            success: function(response) {
                if (response.success) {
                    renderBookings(response.data.bookings);
                    renderPagination(response.data.pagination);
                } else {
                    $('#bookings-container').html(getErrorHTML(response.data.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูล'));
                }
            },
            error: function() {
                $('#bookings-container').html(getErrorHTML('เกิดข้อผิดพลาดในการเชื่อมต่อ'));
            }
        });
    }
    
    function renderBookings(bookings) {
        if (bookings.length === 0) {
            $('#bookings-container').html(getEmptyHTML());
            return;
        }
        
        let html = '';
        bookings.forEach(booking => {
            html += renderBookingItem(booking);
        });
        
        $('#bookings-container').html(html);
    }
    
    function renderBookingItem(booking) {
        const statusText = getStatusText(booking.status);
        const statusClass = booking.status;
        
        return `
            <div class="psu-booking-item" onclick="showBookingDetail(${booking.id})">
                <div class="psu-booking-header">
                    <div>
                        <h4 class="psu-booking-title">${booking.service_name}</h4>
                        <span class="psu-booking-id">#${booking.id}</span>
                    </div>
                    <span class="psu-status-badge ${statusClass}">${statusText}</span>
                </div>
                
                <div class="psu-booking-details">
                    <div class="psu-booking-detail">
                        <span>📅</span>
                        <span>${formatThaiDate(booking.booking_date)}</span>
                    </div>
                    <div class="psu-booking-detail">
                        <span>🕐</span>
                        <span>${booking.start_time.substring(0,5)} - ${booking.end_time.substring(0,5)}</span>
                    </div>
                    <div class="psu-booking-detail">
                        <span>💰</span>
                        <span>${Number(booking.total_price).toLocaleString()} บาท</span>
                    </div>
                    <div class="psu-booking-detail">
                        <span>📝</span>
                        <span>${formatThaiDate(booking.created_at)}</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    function renderPagination(pagination) {
        if (pagination.total_pages <= 1) {
            $('#pagination-container').html('');
            return;
        }
        
        let html = '';
        
        // Previous button
        html += `<button ${pagination.current_page <= 1 ? 'disabled' : ''} onclick="changePage(${pagination.current_page - 1})">‹ ก่อนหน้า</button>`;
        
        // Page numbers
        for (let i = 1; i <= pagination.total_pages; i++) {
            if (i === pagination.current_page) {
                html += `<button class="active">${i}</button>`;
            } else if (i === 1 || i === pagination.total_pages || Math.abs(i - pagination.current_page) <= 2) {
                html += `<button onclick="changePage(${i})">${i}</button>`;
            } else if (i === 2 || i === pagination.total_pages - 1) {
                html += `<span>...</span>`;
            }
        }
        
        // Next button
        html += `<button ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''} onclick="changePage(${pagination.current_page + 1})">ถัดไป ›</button>`;
        
        $('#pagination-container').html(html);
    }
    
         function renderBookingDetail(booking) {
         const statusText = getStatusText(booking.status);
         const statusClass = booking.status;
         
         let formDataHtml = '';
         if (booking.form_data) {
             try {
                 const formData = JSON.parse(booking.form_data);
                 if (formData.custom_fields) {
                     formDataHtml = '<h4>ข้อมูลเพิ่มเติม</h4>';
                     Object.keys(formData.custom_fields).forEach(key => {
                         // ใช้ field label ถ้ามี ไม่งั้นใช้ key เดิม
                         let fieldLabel = key;
                         
                         if (booking.field_labels) {
                             // ลองหา label โดยใช้ key ตรงๆ หรือโดยไม่มี custom_field_ prefix
                             if (booking.field_labels[key]) {
                                 fieldLabel = booking.field_labels[key];
                             } else if (key.startsWith('custom_field_') && booking.field_labels[key.replace('custom_field_', '')]) {
                                 fieldLabel = booking.field_labels[key.replace('custom_field_', '')];
                             }
                         }
                         
                         formDataHtml += `<p><strong>${fieldLabel}:</strong> ${formData.custom_fields[key]}</p>`;
                     });
                 }
             } catch (e) {
                 // Ignore JSON parse errors
             }
         }
        
        const html = `
            <div class="psu-booking-detail-content">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
                    <div>
                        <h4>📋 ข้อมูลการจอง</h4>
                        <p><strong>หมายเลขการจอง:</strong> #${booking.id}</p>
                        <p><strong>บริการ:</strong> ${booking.service_name}</p>
                        <p><strong>สถานะ:</strong> <span class="psu-status-badge ${statusClass}">${statusText}</span></p>
                    </div>
                    
                    <div>
                        <h4>📅 วันที่และเวลา</h4>
                        <p><strong>วันที่จอง:</strong> ${formatThaiDate(booking.booking_date)}</p>
                        <p><strong>เวลา:</strong> ${booking.start_time.substring(0,5)} - ${booking.end_time.substring(0,5)}</p>
                        <p><strong>วันที่สร้าง:</strong> ${formatThaiDate(booking.created_at)}</p>
                    </div>
                    
                    <div>
                        <h4>👤 ข้อมูลผู้จอง</h4>
                        <p><strong>ชื่อ:</strong> ${booking.customer_name}</p>
                        <p><strong>อีเมล:</strong> ${booking.customer_email}</p>
                        ${booking.customer_phone ? `<p><strong>โทรศัพท์:</strong> ${booking.customer_phone}</p>` : ''}
                    </div>
                    
                    <div>
                        <h4>💰 ข้อมูลการชำระ</h4>
                        <p><strong>ราคารวม:</strong> ${Number(booking.total_price).toLocaleString()} บาท</p>
                    </div>
                </div>
                
                ${booking.additional_info ? `
                    <div style="margin-bottom: 20px;">
                        <h4>📝 ข้อมูลเพิ่มเติม</h4>
                        <p>${booking.additional_info}</p>
                    </div>
                ` : ''}
                
                                 ${formDataHtml}
                 ${renderAdditionalCustomFields(booking)}
                 
                 ${booking.admin_notes ? `
                    <div style="margin-bottom: 20px;">
                        <h4>📋 หมายเหตุจากผู้ดูแล</h4>
                        <p>${booking.admin_notes}</p>
                    </div>
                ` : ''}
                
                ${booking.rejection_reason ? `
                    <div style="margin-bottom: 20px; background: #fee2e2; padding: 15px; border-radius: 8px;">
                        <h4 style="color: #991b1b;">❌ เหตุผลการปฏิเสธ</h4>
                        <p style="color: #991b1b;">${booking.rejection_reason}</p>
                    </div>
                ` : ''}
            </div>
        `;
        
        $('#booking-detail-content').html(html);
         }
     
     function renderAdditionalCustomFields(booking) {
         let html = '';
         
         // ค้นหา custom fields ที่อาจเก็บเป็น properties แยก
         const customFieldPrefix = 'custom_field_';
         const customFields = {};
         
         // วนผ่าน properties ทั้งหมดของ booking object
         Object.keys(booking).forEach(key => {
             if (key.startsWith(customFieldPrefix) && booking[key]) {
                 customFields[key] = booking[key];
             }
         });
         
         // ถ้ามี custom fields เพิ่มเติม ให้แสดง
         if (Object.keys(customFields).length > 0) {
             html += '<h4>ข้อมูลเพิ่มเติม (Custom Fields)</h4>';
             
             Object.keys(customFields).forEach(key => {
                 let fieldLabel = key;
                 
                 if (booking.field_labels) {
                     // ลองหา label โดยใช้ key ตรงๆ หรือโดยไม่มี custom_field_ prefix
                     if (booking.field_labels[key]) {
                         fieldLabel = booking.field_labels[key];
                     } else if (key.startsWith(customFieldPrefix)) {
                         const cleanKey = key.replace(customFieldPrefix, '');
                         if (booking.field_labels[cleanKey]) {
                             fieldLabel = booking.field_labels[cleanKey];
                         }
                     }
                 }
                 
                 html += `<p><strong>${fieldLabel}:</strong> ${customFields[key]}</p>`;
             });
         }
         
         return html;
     }
     
     function loadCalendar() {
        const year = calendarDate.getFullYear();
        const month = calendarDate.getMonth();
        
        $('#calendar-month-year').text(formatMonthYear(year, month));
        
        $.ajax({
            url: psu_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'psu_get_calendar_bookings',
                nonce: psu_ajax.nonce,
                year: year,
                month: month
            },
            success: function(response) {
                if (response.success) {
                    renderCalendar(year, month, response.data);
                }
            }
        });
    }
    
    function renderCalendar(year, month, bookings) {
        const firstDay = new Date(year, month, 1);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());
        
        let html = '';
        
        // Header days
        const dayNames = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
        dayNames.forEach(day => {
            html += `<div class="psu-calendar-day header">${day}</div>`;
        });
        
        // Calendar days
        const currentDate = new Date(startDate);
        for (let i = 0; i < 42; i++) {
            const dayBookings = bookings[formatDateISO(currentDate)] || [];
            const isCurrentMonth = currentDate.getMonth() === month;
            const isToday = isDateToday(currentDate);
            
            let dayClass = 'psu-calendar-day';
            if (!isCurrentMonth) dayClass += ' other-month';
            if (isToday) dayClass += ' today';
            
            let eventsHtml = '';
            dayBookings.forEach(booking => {
                eventsHtml += `<div class="psu-calendar-event ${booking.status}" title="${booking.service_name} - ${booking.start_time.substring(0,5)}"></div>`;
            });
            
            html += `
                <div class="${dayClass}" onclick="showDayBookings('${formatDateISO(currentDate)}')">
                    <span>${currentDate.getDate()}</span>
                    <div class="psu-calendar-events">${eventsHtml}</div>
                </div>
            `;
            
            currentDate.setDate(currentDate.getDate() + 1);
        }
        
        $('#calendar-grid').html(html);
    }
    
    // Utility functions
    function getStatusText(status) {
        const statusMap = {
            'pending': 'รออนุมัติ',
            'approved': 'อนุมัติแล้ว',
            'rejected': 'ปฏิเสธ',
            'cancelled': 'ยกเลิก'
        };
        return statusMap[status] || status;
    }
    
    function formatThaiDate(dateString) {
        const months = [
            'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
            'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
        ];
        
        const date = new Date(dateString);
        const day = date.getDate();
        const month = months[date.getMonth()];
        const year = date.getFullYear() + 543;
        
        return `${day} ${month} ${year}`;
    }
    
    function formatMonthYear(year, month) {
        const months = [
            'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
            'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
        ];
        return `${months[month]} ${year + 543}`;
    }
    
    function formatDateISO(date) {
        return date.toISOString().split('T')[0];
    }
    
    function isDateToday(date) {
        const today = new Date();
        return date.toDateString() === today.toDateString();
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function getLoadingHTML() {
        return `
            <div class="psu-loading-center">
                <div class="psu-spinner"></div>
                <p>กำลังโหลดข้อมูล...</p>
            </div>
        `;
    }
    
    function getErrorHTML(message) {
        return `
            <div style="text-align: center; padding: 40px; color: #6b7280;">
                <p>❌ ${message}</p>
            </div>
        `;
    }
    
    function getEmptyHTML() {
        return `
            <div style="text-align: center; padding: 40px; color: #6b7280;">
                <h3>📋 ไม่พบข้อมูลการจอง</h3>
                <p>ยังไม่มีการจองในระบบ</p>
            </div>
        `;
    }
    
})(jQuery);
</script> 