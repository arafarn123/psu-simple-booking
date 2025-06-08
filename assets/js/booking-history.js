jQuery(document).ready(function($) {
    let currentView = 'list';
    let currentPage = 1;
    let currentFilters = {
        search: '',
        status: '',
        month: ''
    };
    let calendarDate = new Date();
    
    // เริ่มต้น
    loadBookings();
    updateViewButtons();
    
    // Event Listeners
    $('#booking-search').on('input', debounce(function() {
        currentFilters.search = $(this).val();
        currentPage = 1;
        loadBookings();
    }, 500));
    
    $('#status-filter, #month-filter').on('change', function() {
        currentFilters.status = $('#status-filter').val();
        currentFilters.month = $('#month-filter').val();
        currentPage = 1;
        loadBookings();
    });
    
    // Functions
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
    
    function updateViewButtons() {
        $('.psu-history-actions button').removeClass('psu-btn-primary').addClass('psu-btn-secondary');
        if (currentView === 'list') {
            $('#btn-list-view').removeClass('psu-btn-secondary').addClass('psu-btn-primary');
        } else {
            $('#btn-calendar-view').removeClass('psu-btn-secondary').addClass('psu-btn-primary');
        }
    }
    
    function loadBookings() {
        $('#bookings-container').html(`
            <div class="psu-loading-center">
                <div class="psu-spinner"></div>
                <p>กำลังโหลดข้อมูล...</p>
            </div>
        `);
        
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
                    $('#bookings-container').html(`
                        <div style="text-align: center; padding: 40px; color: #6b7280;">
                            <p>❌ ${response.data.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูล'}</p>
                        </div>
                    `);
                }
            },
            error: function() {
                $('#bookings-container').html(`
                    <div style="text-align: center; padding: 40px; color: #6b7280;">
                        <p>❌ เกิดข้อผิดพลาดในการเชื่อมต่อ</p>
                    </div>
                `);
            }
        });
    }
    
    function renderBookings(bookings) {
        if (bookings.length === 0) {
            $('#bookings-container').html(`
                <div style="text-align: center; padding: 40px; color: #6b7280;">
                    <h3>📋 ไม่พบข้อมูลการจอง</h3>
                    <p>ยังไม่มีการจองในระบบ</p>
                </div>
            `);
            return;
        }
        
        let html = '';
        bookings.forEach(booking => {
            const statusText = getStatusText(booking.status);
            const statusClass = booking.status;
            
            html += `
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
        });
        
        $('#bookings-container').html(html);
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
        $('#booking-detail-content').html(`
            <div class="psu-loading-center">
                <div class="psu-spinner"></div>
                <p>กำลังโหลดรายละเอียด...</p>
            </div>
        `);
        
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
                    $('#booking-detail-content').html(`
                        <p style="text-align: center; color: #ef4444;">❌ ไม่สามารถโหลดรายละเอียดได้</p>
                    `);
                }
            }
        });
    };
    
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
                        formDataHtml += `<p><strong>${key}:</strong> ${formData.custom_fields[key]}</p>`;
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
    
    window.closeBookingModal = function() {
        $('#booking-detail-modal').hide();
    };
    
    // Calendar functions
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
        const lastDay = new Date(year, month + 1, 0);
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
    
    window.changeCalendarMonth = function(direction) {
        calendarDate.setMonth(calendarDate.getMonth() + direction);
        loadCalendar();
    };
    
    window.showDayBookings = function(date) {
        // Show bookings for specific day
        currentFilters.search = date;
        currentView = 'list';
        toggleView('list');
    };
    
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
});

// Modal close when clicking outside
jQuery(document).on('click', '.psu-modal', function(e) {
    if (e.target === this) {
        jQuery(this).hide();
    }
});

// ESC key to close modal
jQuery(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
        jQuery('.psu-modal').hide();
    }
}); 