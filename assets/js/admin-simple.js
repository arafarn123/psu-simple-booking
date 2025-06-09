/**
 * PSU Simple Booking - Simplified Admin JavaScript
 * JavaScript อย่างง่ายไม่ขัดขวาง form submission
 */

jQuery(document).ready(function ($) {
    'use strict';



    /**
     * ฟังก์ชันทั่วไป
     */

    // แสดงข้อความแจ้งเตือน
    function showNotice(message, type = 'success') {
        const noticeClass = `notice notice-${type} psu-notice psu-notice-${type}`;
        const notice = $(`<div class="${noticeClass}"><p>${message}</p></div>`);

        $('.wrap h1').after(notice);

        // ซ่อนข้อความหลังจาก 5 วินาที
        setTimeout(() => {
            notice.fadeOut(() => notice.remove());
        }, 5000);
    }

    /**
     * หน้าจัดการบริการ (Services)
     */

    // สลับการแสดงฟอร์มบริการ - ไม่ขัดขวาง form submission
    window.toggleServiceForm = function () {
        const form = $('#service-form');
        const list = $('#services-list');

        if (form.is(':visible')) {
            form.hide();
            list.show();
            // รีเซ็ตฟอร์มหากไม่ใช่การแก้ไข
            if (!$('input[name="service_id"]').length) {
                form.find('form')[0].reset();
                // รีเซ็ต image preview
                $('#image-preview').empty();
            }
        } else {
            form.show();
            list.hide();
        }
    };

    // แสดง/ซ่อนฟิลด์ timeslot duration ตามประเภทการจอง
    $(document).on('change', 'input[name="timeslot_type[]"]', function () {
        const durationRow = $('#timeslot_duration_row');
        const hourlyChecked = $('input[name="timeslot_type[]"][value="hourly"]').is(':checked');

        if (hourlyChecked) {
            durationRow.show();
        } else {
            durationRow.hide();
        }
    });

    // เริ่มต้นการแสดงฟิลด์ duration
    $(document).ready(function () {
        $('input[name="timeslot_type[]"][value="hourly"]').trigger('change');
    });

    /**
     * ปิด Modal
     */
    window.closeModal = function () {
        $('.psu-modal').fadeOut(300, function () {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.delete('action');
            currentUrl.searchParams.delete('booking_id');
            window.history.replaceState({}, '', currentUrl);
        });
    };

    // ปิด Modal เมื่อคลิกนอกพื้นที่
    $(document).on('click', '.psu-modal', function (e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // ปิด Modal เมื่อกด ESC
    $(document).keyup(function (e) {
        if (e.keyCode === 27) { // ESC key
            closeModal();
        }
    });

    /**
     * Admin Calendar Functions
     */
    let adminCalendarDate = new Date();
    let adminBookingsData = {}; // Store monthly booking data
    let selectedDate = null;

    // Helper to format date as YYYY-MM-DD (timezone-safe)
    function formatDateForAPI(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    // เริ่มต้น admin calendar
    function initAdminCalendar() {
        const calendarContainer = $('#admin-calendar-days');
        if (calendarContainer.length) {
            const today = new Date();
            selectedDate = formatDateForAPI(today);

            renderAdminCalendar(); // Initial render
            loadAdminCalendarData(); // Load data for the current month

            // Bind navigation events
            $('#prev-month-admin').on('click', () => changeAdminMonth(-1));
            $('#next-month-admin').on('click', () => changeAdminMonth(1));

            // The filter form will now submit via standard GET request,
            // so no special JavaScript handling is needed here.
        }
    }

    // เปลี่ยนเดือน
    function changeAdminMonth(direction) {
        adminCalendarDate.setMonth(adminCalendarDate.getMonth() + direction);
        renderAdminCalendar();
        loadAdminCalendarData();
    }
    
    // เลือกวันในปฏิทิน
    window.selectAdminCalendarDate = function(dateStr) {
        selectedDate = dateStr;
        renderAdminCalendar(); // Re-render to show selection
        showDateDetails(dateStr); // Show details for the selected date
    }

    // แสดงปฏิทิน
    function renderAdminCalendar() {
        const year = adminCalendarDate.getFullYear();
        const month = adminCalendarDate.getMonth();
        const calendarDaysContainer = $('#admin-calendar-days');

        const thaiMonths = [
            'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
            'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
        ];
        const thaiYear = year + 543;
        $('#calendar-month-year-admin').text(`${thaiMonths[month]} ${thaiYear}`);

        const firstDayOfMonth = new Date(year, month, 1);
        const lastDayOfMonth = new Date(year, month + 1, 0);
        const firstDayOfGrid = new Date(firstDayOfMonth);
        firstDayOfGrid.setDate(firstDayOfGrid.getDate() - firstDayOfMonth.getDay());

        let html = '';
        const todayStr = formatDateForAPI(new Date());

        for (let i = 0; i < 42; i++) { // Render 6 weeks
            const currentDate = new Date(firstDayOfGrid);
            currentDate.setDate(firstDayOfGrid.getDate() + i);

            const dateStr = formatDateForAPI(currentDate);
            const dayNumber = currentDate.getDate();
            const isCurrentMonth = currentDate.getMonth() === month;
            const isToday = dateStr === todayStr;
            const isSelected = dateStr === selectedDate;

            let dayClass = 'psu-calendar-day';
            if (!isCurrentMonth) dayClass += ' other-month';
            if (isToday) dayClass += ' today';
            if (isSelected) dayClass += ' selected';

            // Get status and text from pre-calculated data
            const dayData = adminBookingsData[dateStr] || { status: 'available', bookings: [] };
            const statusClass = `status-${dayData.status}`;
            let statusText;
            switch(dayData.status) {
                case 'partial':
                    statusText = 'จองบางส่วน';
                    break;
                case 'full':
                    statusText = 'เต็ม';
                    break;
                default:
                    statusText = 'ว่าง';
            }

            dayClass += ` ${statusClass}`;

            html += `
                <div class="${dayClass}" data-date="${dateStr}" onclick="selectAdminCalendarDate('${dateStr}')">
                    <div class="calendar-day-number">${dayNumber}</div>
                    <div class="calendar-day-bookings">
                        <span class="calendar-day-status-text">${statusText}</span>
                    </div>
                </div>`;
        }

        calendarDaysContainer.html(html);
    }

    // โหลดข้อมูลการจองสำหรับปฏิทิน
    function loadAdminCalendarData() {
        const year = adminCalendarDate.getFullYear();
        const month = adminCalendarDate.getMonth();
        const detailsContainer = $('#selected-date-bookings');
        const statusFilter = $('#filter-status').val() || '';
        const serviceFilter = $('#filter-service').val() || '';
        const dateFrom = $('#filter-date-from').val() || '';
        const dateTo = $('#filter-date-to').val() || '';

        // Show loading indicator
        $('.psu-calendar-grid-container').addClass('loading');
        detailsContainer.html('<div class="loading-placeholder"><p>กำลังโหลดข้อมูล...</p></div>');

        $.ajax({
            url: psu_admin_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'psu_get_admin_calendar_bookings',
                nonce: psu_admin_ajax.nonce,
                year: year,
                month: month, // JS month is 0-11, which the PHP handler expects
                status_filter: statusFilter,
                service_filter: serviceFilter,
                date_from: dateFrom,
                date_to: dateTo
            },
            success: function (response) {
                $('.psu-calendar-grid-container').removeClass('loading');
                if (response.success) {
                    adminBookingsData = response.data;
                    renderAdminCalendar(); // Re-render with new data
                    if (selectedDate) {
                       showDateDetails(selectedDate); // Refresh the details pane
                    } else {
                        // If no date is selected, show today's details or a default message
                        const todayStr = formatDateForAPI(new Date());
                        selectAdminCalendarDate(todayStr);
                    }
                } else {
                    detailsContainer.html(`<div class="notice notice-error"><p>${response.data.message || 'เกิดข้อผิดพลาดในการโหลดข้อมูล'}</p></div>`);
                }
            },
            error: function () {
                $('.psu-calendar-grid-container').removeClass('loading');
                detailsContainer.html('<div class="notice notice-error"><p>เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์</p></div>');
            }
        });
    }

    // แสดงรายละเอียดของวันที่เลือก
    function showDateDetails(dateStr) {
        const dayData = adminBookingsData[dateStr] || { status: 'available', bookings: [] };
        const bookings = dayData.bookings;
        const detailsContainer = $('#selected-date-bookings');
        
        // Format date for title (e.g., 15 มกราคม 2567)
        const dateObj = new Date(dateStr + 'T00:00:00');
        const thaiMonths = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        const thaiDay = dateObj.getDate();
        const thaiMonth = thaiMonths[dateObj.getMonth()];
        const thaiYear = dateObj.getFullYear() + 543;
        $('#selected-date-title').text(`รายการจองวันที่ ${thaiDay} ${thaiMonth} ${thaiYear}`);

        detailsContainer.empty();

        if (bookings.length === 0) {
            detailsContainer.html(`
                <div class="no-bookings-placeholder">
                    <p style="text-align: center; color: #666; padding: 20px;">
                        <span style="font-size: 24px;">✓</span><br>
                        ไม่มีรายการจองในวันนี้
                    </p>
                </div>
            `);
            return;
        }

        let html = '<ul class="psu-daily-booking-list">';
        bookings.forEach(booking => {
            const statusClass = `status-${booking.status}`;
            const statusText = {
                'pending': 'รออนุมัติ',
                'approved': 'อนุมัติแล้ว',
                'rejected': 'ถูกปฏิเสธ'
            }[booking.status] || booking.status;

            const viewUrl = `?page=psu-booking-bookings&action=view_details&booking_id=${booking.id}`;
            // Use nonce URLs from the server
            const approveUrl = booking.approve_url;
            const rejectUrl  = booking.reject_url;

            html += `
                <li class="psu-daily-booking-item">
                    <div class="booking-item-header">
                        <span class="booking-item-time">${booking.start_time.substring(0, 5)} - ${booking.end_time.substring(0, 5)}</span>
                        <span class="booking-item-status ${statusClass}">${statusText}</span>
                    </div>
                    <div class="booking-item-body">
                        <strong>${booking.service_name}</strong>
                        <small>${booking.customer_name} (${booking.customer_email})</small>
                    </div>
                    <div class="booking-item-actions">
                        <a href="${viewUrl}" class="button button-secondary button-small">ดู/แก้ไข</a>
                        ${booking.status === 'pending' ? `<a href="${approveUrl}" class="button button-primary button-small" onclick="return confirm('ยืนยันการอนุมัติ?')">อนุมัติ</a>` : ''}
                        ${booking.status === 'pending' ? `<a href="#" onclick="rejectBooking(event, '${rejectUrl}')" class="button button-link-delete button-small">ปฏิเสธ</a>` : ''}
                    </div>
                </li>
            `;
        });
        html += '</ul>';

        detailsContainer.html(html);
    }

    // Run on page load
    initAdminCalendar();

});

// ทำให้ฟังก์ชันเป็น global
window.openMediaLibrary = openMediaLibrary;
window.changeAdminMonth = changeAdminMonth;
window.selectAdminCalendarDate = selectAdminCalendarDate;
window.updateCalendarView = updateCalendarView; 
window.updateCalendarView = updateCalendarView; 