/**
 * PSU Simple Booking Frontend JavaScript
 * สคริปต์สำหรับการทำงานของระบบจอง
 */

(function($) {
    'use strict';

    // ตัวแปร global
    let selectedService = null;
    let selectedDate = null;
    let selectedTimeslots = [];
    let currentStep = 1;
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();

    // เริ่มต้นเมื่อ document พร้อม
    $(document).ready(function() {
        initBookingForm();
    });

    function initBookingForm() {
        bindEvents();
        renderCalendar();
    }

    function bindEvents() {
        // เลือกบริการ
        $(document).on('click', '.psu-select-service', function() {
            const serviceId = $(this).data('service-id');
            selectService(serviceId);
        });
        
        // ปฏิทิน
        $(document).on('click', '#prev-month', function() {
            changeMonth(-1);
        });
        
        $(document).on('click', '#next-month', function() {
            changeMonth(1);
        });
        
        $(document).on('click', '.psu-calendar-day-available', function() {
            const date = $(this).data('date');
            selectDate(date);
        });
        
        // ปุ่ม navigation
        $(document).on('click', '#next-to-step-3', function() {
            if (!$(this).hasClass('psu-btn-disabled')) {
                loadTimeslots();
                psuGoToStep(3);
            }
        });
        
        $(document).on('click', '#next-to-step-4', function() {
            if (!$(this).hasClass('psu-btn-disabled')) {
                updateBookingSummary();
                psuGoToStep(4);
            }
        });
        
        // เลือก timeslot
        $(document).on('click', '.psu-timeslot', function() {
            toggleTimeslot(this);
        });
        
        // ส่งการจอง
        $(document).on('click', '#submit-booking', function() {
            submitBooking();
        });
    }

    function selectService(serviceId) {
        $.ajax({
            url: psu_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'psu_get_service',
                service_id: serviceId,
                nonce: psu_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    selectedService = response.data;
                    updateSelectedServiceInfo();
                    psuGoToStep(2);
                } else {
                    alert('ไม่สามารถโหลดข้อมูลบริการได้');
                }
            },
            error: function() {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
            }
        });
    }

    function updateSelectedServiceInfo() {
        const priceText = selectedService.price > 0 ? 
            Number(selectedService.price).toLocaleString() + ' บาท/ชั่วโมง' : 'ฟรี';
        
        const html = `
            <div class="psu-service-summary">
                <h4>${selectedService.name}</h4>
                <p>${selectedService.description}</p>
                <p><strong>ราคา:</strong> ${priceText}</p>
                <p><strong>ระยะเวลา:</strong> ${selectedService.duration} นาที</p>
                ${selectedService.payment_info ? `<p><strong>การชำระเงิน:</strong> ${selectedService.payment_info}</p>` : ''}
            </div>
        `;
        $('#selected-service-info').html(html);
    }

    function changeMonth(direction) {
        currentMonth += direction;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        } else if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        renderCalendar();
    }

    function renderCalendar() {
        const monthNames = [
            'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
            'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
        ];
        
        $('#calendar-month-year').text(monthNames[currentMonth] + ' ' + (currentYear + 543));
        
        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        let calendarHtml = '<div class="psu-calendar-grid">';
        
        // Header วัน
        const dayHeaders = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
        dayHeaders.forEach(function(day) {
            calendarHtml += `<div class="psu-calendar-header-day">${day}</div>`;
        });
        
        // เติมช่องว่างก่อนวันที่ 1
        for (let i = 0; i < firstDay; i++) {
            calendarHtml += '<div class="psu-calendar-day psu-calendar-day-empty"></div>';
        }
        
        // วันที่ในเดือน
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(currentYear, currentMonth, day);
            const dateStr = formatDateString(date);
            
            let classes = 'psu-calendar-day';
            
            if (date < today) {
                classes += ' psu-calendar-day-disabled';
            } else {
                classes += ' psu-calendar-day-available';
            }
            
            if (selectedDate === dateStr) {
                classes += ' psu-calendar-day-selected';
            }
            
            calendarHtml += `<div class="${classes}" data-date="${dateStr}">${day}</div>`;
        }
        
        calendarHtml += '</div>';
        $('#psu-calendar').html(calendarHtml);
    }

    function selectDate(date) {
        selectedDate = date;
        selectedTimeslots = [];
        
        $('.psu-calendar-day-selected').removeClass('psu-calendar-day-selected');
        $(`[data-date="${date}"]`).addClass('psu-calendar-day-selected');
        
        $('#next-to-step-3').removeClass('psu-btn-disabled').prop('disabled', false);
        
        const dateObj = new Date(date);
        const dateText = formatThaiDate(dateObj);
        $('#selected-date-info').html(`<p>วันที่เลือก: <strong>${dateText}</strong></p>`);
        
        $('#selected-timeslots').hide();
        $('#next-to-step-4').addClass('psu-btn-disabled').prop('disabled', true);
    }

    function loadTimeslots() {
        $('#timeslots-container').html('<div class="psu-loading">กำลังโหลดช่วงเวลาที่ว่าง...</div>');
        
        $.ajax({
            url: psu_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'psu_get_timeslots',
                service_id: selectedService.id,
                date: selectedDate,
                nonce: psu_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderTimeslots(response.data);
                } else {
                    $('#timeslots-container').html('<p>ไม่สามารถโหลดข้อมูลเวลาได้</p>');
                }
            },
            error: function() {
                $('#timeslots-container').html('<p>เกิดข้อผิดพลาดในการโหลดข้อมูล</p>');
            }
        });
    }

    function renderTimeslots(timeslots) {
        if (timeslots.length === 0) {
            $('#timeslots-container').html('<p>ไม่มีช่วงเวลาว่างในวันนี้</p>');
            return;
        }
        
        let html = '<div class="psu-timeslots-grid">';
        timeslots.forEach(function(slot) {
            const priceText = Number(slot.price).toLocaleString();
            html += `
                <div class="psu-timeslot" data-start="${slot.start}" data-end="${slot.end}" data-price="${slot.price}">
                    <div class="psu-timeslot-time">${slot.display}</div>
                    <div class="psu-timeslot-price">${priceText} บาท</div>
                </div>
            `;
        });
        html += '</div>';
        
        $('#timeslots-container').html(html);
    }

    function toggleTimeslot(element) {
        const $slot = $(element);
        const start = $slot.data('start');
        const end = $slot.data('end');
        const price = parseFloat($slot.data('price'));
        const display = $slot.find('.psu-timeslot-time').text();
        
        if ($slot.hasClass('psu-timeslot-selected')) {
            $slot.removeClass('psu-timeslot-selected');
            selectedTimeslots = selectedTimeslots.filter(slot => 
                !(slot.start === start && slot.end === end)
            );
        } else {
            $slot.addClass('psu-timeslot-selected');
            selectedTimeslots.push({
                start: start,
                end: end,
                price: price,
                display: display
            });
        }
        
        updateSelectedTimeslots();
    }

    function updateSelectedTimeslots() {
        if (selectedTimeslots.length === 0) {
            $('#selected-timeslots').hide();
            $('#next-to-step-4').addClass('psu-btn-disabled').prop('disabled', true);
            return;
        }
        
        let html = '';
        let totalPrice = 0;
        
        selectedTimeslots.forEach(function(slot) {
            html += `<li>${slot.display} - ${Number(slot.price).toLocaleString()} บาท</li>`;
            totalPrice += slot.price;
        });
        
        $('#selected-timeslots-list').html(html);
        $('#total-price').text(Number(totalPrice).toLocaleString());
        $('#selected-timeslots').show();
        $('#next-to-step-4').removeClass('psu-btn-disabled').prop('disabled', false);
    }

    function updateBookingSummary() {
        const totalPrice = selectedTimeslots.reduce((sum, slot) => sum + slot.price, 0);
        
        let timeslotsHtml = '';
        selectedTimeslots.forEach(function(slot) {
            timeslotsHtml += `<li>${slot.display}</li>`;
        });
        
        const dateText = formatThaiDate(new Date(selectedDate));
        
        const html = `
            <div class="psu-summary-item">
                <strong>บริการ:</strong> ${selectedService.name}
            </div>
            <div class="psu-summary-item">
                <strong>วันที่:</strong> ${dateText}
            </div>
            <div class="psu-summary-item">
                <strong>เวลา:</strong>
                <ul>${timeslotsHtml}</ul>
            </div>
            <div class="psu-summary-item">
                <strong>ราคารวม:</strong> ${Number(totalPrice).toLocaleString()} บาท
            </div>
            ${selectedService.payment_info ? 
                `<div class="psu-summary-item">
                    <strong>การชำระเงิน:</strong> ${selectedService.payment_info}
                </div>` : ''
            }
        `;
        
        $('#booking-summary-content').html(html);
    }

    function submitBooking() {
        const customerName = $('#customer_name').val().trim();
        const customerEmail = $('#customer_email').val().trim();
        
        if (!customerName || !customerEmail) {
            alert('กรุณากรอกชื่อและอีเมลให้ครบถ้วน');
            return;
        }
        
        const formData = {
            action: 'psu_submit_booking',
            service_id: selectedService.id,
            customer_name: customerName,
            customer_email: customerEmail,
            booking_date: selectedDate,
            timeslots: selectedTimeslots,
            additional_info: $('#additional_info').val().trim(),
            nonce: psu_ajax.nonce
        };
        
        const $submitBtn = $('#submit-booking');
        const originalText = $submitBtn.text();
        $submitBtn.prop('disabled', true).text('กำลังจอง...');
        
        $.ajax({
            url: psu_ajax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showSuccessMessage(response.data);
                    psuGoToStep(5);
                } else {
                    alert(response.data.message || 'เกิดข้อผิดพลาดในการจอง');
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                $submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }

    function showSuccessMessage(data) {
        const html = `
            <p>การจองของคุณได้รับการยืนยันแล้ว</p>
            <p><strong>รหัสการจอง:</strong> ${data.booking_ids.join(', ')}</p>
            <p><strong>ราคารวม:</strong> ${Number(data.total_price).toLocaleString()} บาท</p>
            <p>ท่านจะได้รับอีเมลยืนยันการจองในอีกสักครู่</p>
        `;
        $('#success-details').html(html);
    }

    function psuGoToStep(step) {
        $('.psu-step').addClass('psu-step-hidden');
        $('#step-' + step).removeClass('psu-step-hidden');
        currentStep = step;
        
        $('html, body').animate({
            scrollTop: $('.psu-booking-container').offset().top
        }, 500);
    }

    function formatDateString(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function formatThaiDate(date) {
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            weekday: 'long' 
        };
        return date.toLocaleDateString('th-TH', options);
    }

    // Export functions to global scope
    window.psuGoToStep = psuGoToStep;
    
})(jQuery); 