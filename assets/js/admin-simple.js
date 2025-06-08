/**
 * PSU Simple Booking - Simplified Admin JavaScript
 * JavaScript อย่างง่ายไม่ขัดขวาง form submission
 */

jQuery(document).ready(function($) {
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
    window.toggleServiceForm = function() {
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
    $(document).on('change', 'input[name="timeslot_type[]"]', function() {
        const durationRow = $('#timeslot_duration_row');
        const hourlyChecked = $('input[name="timeslot_type[]"][value="hourly"]').is(':checked');
        
        if (hourlyChecked) {
            durationRow.show();
        } else {
            durationRow.hide();
        }
    });

    // เริ่มต้นการแสดงฟิลด์ duration
    $(document).ready(function() {
        $('input[name="timeslot_type[]"][value="hourly"]').trigger('change');
    });

    /**
     * ปิด Modal
     */
    window.closeModal = function() {
        $('.psu-modal').fadeOut(300, function() {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.delete('action');
            currentUrl.searchParams.delete('booking_id');
            window.history.replaceState({}, '', currentUrl);
        });
    };

    // ปิด Modal เมื่อคลิกนอกพื้นที่
    $(document).on('click', '.psu-modal', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // ปิด Modal เมื่อกด ESC
    $(document).keyup(function(e) {
        if (e.keyCode === 27) { // ESC key
            closeModal();
        }
    });
});

/**
 * Admin Calendar Functions
 */
let adminCalendarDate = new Date();
let selectedDate = null;

// เริ่มต้น admin calendar
function initAdminCalendar() {
    if ($('#admin-calendar-days').length) {
        // โหลดปฏิทินเดือนปัจจุบัน
        renderAdminCalendar();
        loadAdminCalendarData();
        
        // โหลดข้อมูลวันปัจจุบันเป็นค่าเริ่มต้น
        const today = new Date();
        const todayStr = formatDateForAPI(today);
        selectAdminCalendarDate(todayStr);
        
        // Bind navigation events
        $('#prev-month-admin').on('click', function() {
            changeAdminMonth(-1);
        });
        
        $('#next-month-admin').on('click', function() {
            changeAdminMonth(1);
        });
    }
}

// แสดงปฏิทิน
function renderAdminCalendar() {
    const year = adminCalendarDate.getFullYear();
    const month = adminCalendarDate.getMonth();
    
    // อัพเดทชื่อเดือน-ปี (แปลงเป็นปี พ.ศ.)
    const thaiMonths = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    const thaiYear = year + 543;
    $('#calendar-month-year-admin').text(`${thaiMonths[month]} ${thaiYear}`);
    
    // สร้างปฏิทิน
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - firstDay.getDay()); // เริ่มจากวันอาทิตย์
    
    let html = '';
    const today = new Date();
    const todayStr = formatDateForAPI(today);
    
    for (let i = 0; i < 42; i++) { // 6 สัปดาห์ x 7 วัน
        const currentDate = new Date(startDate);
        currentDate.setDate(startDate.getDate() + i);
        
        const dateStr = formatDateForAPI(currentDate);
        const dayNumber = currentDate.getDate();
        const isCurrentMonth = currentDate.getMonth() === month;
        const isToday = dateStr === todayStr;
        const isSelected = dateStr === selectedDate;
        
        let dayClass = 'psu-calendar-day';
        if (!isCurrentMonth) dayClass += ' other-month';
        if (isToday) dayClass += ' today';
        if (isSelected) dayClass += ' selected';
        
        html += `
            <div class="${dayClass}" data-date="${dateStr}" onclick="selectAdminCalendarDate('${dateStr}')">
                <div class="calendar-day-number">${dayNumber}</div>
                <div class="calendar-day-bookings" id="bookings-${dateStr}">
                    <!-- จะโหลดข้อมูลการจองด้วย AJAX -->
                </div>
            </div>
        `;
    }
    
    $('#admin-calendar-days').html(html);
}

// โหลดข้อมูลการจองสำหรับปฏิทิน
function loadAdminCalendarData() {
    const year = adminCalendarDate.getFullYear();
    const month = adminCalendarDate.getMonth();
    
    // รับค่า filter จาก form
    const statusFilter = $('#filter-status').val() || '';
    const serviceFilter = $('#filter-service').val() || '';
    const dateFrom = $('#filter-date-from').val() || '';
    const dateTo = $('#filter-date-to').val() || '';
    
    $.ajax({
        url: psu_admin_ajax.ajax_url,
        method: 'POST',
        data: {
            action: 'psu_get_admin_calendar_bookings',
            nonce: wp.create_nonce('psu_admin_calendar_nonce'),
            year: year,
            month: month,
            status_filter: statusFilter,
            service_filter: serviceFilter,
            date_from: dateFrom,
            date_to: dateTo
        },
        success: function(response) {
            if (response.success) {
                updateCalendarWithBookings(response.data);
            }
        },
        error: function() {
            console.error('Error loading calendar data');
        }
    });
}

// อัพเดทปฏิทินด้วยข้อมูลการจอง
function updateCalendarWithBookings(bookingsData) {
    // ล้างข้อมูลเก่า
    $('.psu-calendar-day').removeClass('has-approved has-pending has-rejected');
    $('.calendar-day-bookings').empty();
    
    // เพิ่มข้อมูลใหม่
    Object.keys(bookingsData).forEach(date => {
        const bookings = bookingsData[date];
        const dayElement = $(`.psu-calendar-day[data-date="${date}"]`);
        const bookingsContainer = dayElement.find('.calendar-day-bookings');
        
        if (bookings.length > 0) {
            // นับสถานะ
            const statusCounts = {
                approved: bookings.filter(b => b.status === 'approved').length,
                pending: bookings.filter(b => b.status === 'pending').length,
                rejected: bookings.filter(b => b.status === 'rejected').length
            };
            
            // เพิ่ม class ตามสถานะที่พบมากที่สุด
            if (statusCounts.approved > 0) dayElement.addClass('has-approved');
            if (statusCounts.pending > 0) dayElement.addClass('has-pending');
            if (statusCounts.rejected > 0) dayElement.addClass('has-rejected');
            
            // แสดงจำนวนการจอง
            if (bookings.length <= 3) {
                // แสดง badge สำหรับแต่ละการจอง
                bookings.forEach(booking => {
                    bookingsContainer.append(`
                        <div class="calendar-booking-badge ${booking.status}">
                            ${booking.start_time.substring(0,5)}
                        </div>
                    `);
                });
            } else {
                // แสดงเฉพาะจำนวน
                bookingsContainer.append(`
                    <div class="calendar-booking-count">${bookings.length}</div>
                `);
            }
        }
    });
}

// เปลี่ยนเดือน
function changeAdminMonth(direction) {
    adminCalendarDate.setMonth(adminCalendarDate.getMonth() + direction);
    renderAdminCalendar();
    loadAdminCalendarData();
}

// เลือกวันที่
function selectAdminCalendarDate(dateStr) {
    selectedDate = dateStr;
    
    // อัพเดท UI
    $('.psu-calendar-day').removeClass('selected');
    $(`.psu-calendar-day[data-date="${dateStr}"]`).addClass('selected');
    
    // โหลดรายละเอียดการจองของวันที่เลือก
    showDateDetails(dateStr);
}

// แสดงรายละเอียดของวันที่เลือก
function showDateDetails(dateStr) {
    const thaiDate = formatDateThai(dateStr);
    $('#selected-date-title').text(`รายการจองวันที่ ${thaiDate}`);
    
    const year = adminCalendarDate.getFullYear();
    const month = adminCalendarDate.getMonth();
    
    // รับค่า filter
    const statusFilter = $('#filter-status').val() || '';
    const serviceFilter = $('#filter-service').val() || '';
    
    $.ajax({
        url: psu_admin_ajax.ajax_url,
        method: 'POST',
        data: {
            action: 'psu_get_admin_calendar_bookings',
            nonce: wp.create_nonce('psu_admin_calendar_nonce'),
            year: year,
            month: month,
            status_filter: statusFilter,
            service_filter: serviceFilter,
            date_from: dateStr,
            date_to: dateStr
        },
        success: function(response) {
            if (response.success && response.data[dateStr]) {
                displayDateBookings(response.data[dateStr]);
            } else {
                displayDateBookings([]);
            }
        },
        error: function() {
            $('#selected-date-bookings').html('<p class="no-bookings">เกิดข้อผิดพลาดในการโหลดข้อมูล</p>');
        }
    });
}

// แสดงรายการจองของวันที่เลือก
function displayDateBookings(bookings) {
    const container = $('#selected-date-bookings');
    
    if (bookings.length === 0) {
        container.html('<p class="no-bookings">ไม่มีการจองในวันนี้</p>');
        return;
    }
    
    let html = '';
    bookings.forEach(booking => {
        const statusText = {
            'pending': 'รออนุมัติ',
            'approved': 'อนุมัติแล้ว', 
            'rejected': 'ถูกปฏิเสธ',
            'cancelled': 'ยกเลิก'
        };
        
        html += `
            <div class="booking-item" onclick="window.location.href='admin.php?page=psu-booking-bookings&action=view&booking_id=${booking.id}'">
                <div class="booking-item-header">
                    <span class="booking-item-time">${booking.start_time.substring(0,5)} - ${booking.end_time.substring(0,5)}</span>
                    <span class="booking-item-status ${booking.status}">${statusText[booking.status] || booking.status}</span>
                </div>
                <div class="booking-item-service">${booking.service_name}</div>
                <div class="booking-item-customer">${booking.customer_name} (${booking.customer_email})</div>
            </div>
        `;
    });
    
    container.html(html);
}

// Helper functions
function formatDateForAPI(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatDateThai(dateStr) {
    const [year, month, day] = dateStr.split('-');
    const thaiMonths = [
        'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
        'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
    ];
    const thaiYear = parseInt(year) + 543;
    return `${parseInt(day)} ${thaiMonths[parseInt(month) - 1]} ${thaiYear}`;
}

// อัพเดท calendar view ตาม filters
function updateCalendarView() {
    if ($('#admin-calendar-days').length) {
        loadAdminCalendarData();
        
        // รี-โหลดข้อมูลวันที่เลือกหากมี
        if (selectedDate) {
            showDateDetails(selectedDate);
        }
    }
}

// WordPress Media Library Integration
function openMediaLibrary() {
    // ตรวจสอบว่า wp.media พร้อมใช้งาน
    if (typeof wp === 'undefined' || !wp.media) {
        alert('WordPress Media Library ไม่พร้อมใช้งาน\nกรุณาโหลดหน้าใหม่หรือใส่ URL รูปภาพโดยตรง');
        return;
    }

    // สร้าง Media Uploader
    var mediaUploader = wp.media({
        title: 'เลือกรูปภาพสำหรับบริการ',
        button: {
            text: 'เลือกรูปนี้'
        },
        multiple: false,
        library: {
            type: 'image'
        }
    });

    // เมื่อเลือกรูปภาพแล้ว
    mediaUploader.on('select', function() {
        var attachment = mediaUploader.state().get('selection').first().toJSON();
        
        // ใส่ URL ลงในฟิลด์ (รองรับทั้ง jQuery และ vanilla JS)
        var imageUrlField = document.getElementById('image_url');
        if (imageUrlField) {
            imageUrlField.value = attachment.url;
        }
        if (typeof $ !== 'undefined') {
            $('#image_url').val(attachment.url);
        }
        
        // แสดง preview (รองรับทั้ง jQuery และ vanilla JS)
        var previewHtml = '<img src="' + attachment.url + '" alt="Preview" style="max-width: 200px; height: auto; border-radius: 4px; border: 1px solid #ddd;">';
        var preview = document.getElementById('image-preview');
        if (preview) {
            preview.innerHTML = previewHtml;
        }
        if (typeof $ !== 'undefined') {
            $('#image-preview').html(previewHtml);
        }
    });

    // เปิด Media Library
    mediaUploader.open();
}

// เริ่มต้น calendar เมื่อ document พร้อม
$(document).ready(function() {
    initAdminCalendar();
    
    // เมื่อมีการเปลี่ยนแปลง filter
    $('.psu-filter-form select, .psu-filter-form input').on('change', function() {
        updateCalendarView();
    });
});

// ทำให้ฟังก์ชันเป็น global
window.openMediaLibrary = openMediaLibrary;
window.changeAdminMonth = changeAdminMonth;
window.selectAdminCalendarDate = selectAdminCalendarDate;
window.updateCalendarView = updateCalendarView; 