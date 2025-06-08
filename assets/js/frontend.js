/**
 * PSU Simple Booking Frontend JavaScript - Luxury Edition
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
    let selectedTimeslotCategory = null;

    // เริ่มต้นเมื่อ document พร้อม
    $(document).ready(function() {
        initBookingForm();
        addLuxuryEffects();
    });

    function initBookingForm() {
        bindEvents();
        renderCalendar();
    }

    function addLuxuryEffects() {
        // เพิ่มเอฟเฟคสำหรับ service cards
        $('.psu-service-card').each(function() {
            $(this).on('mouseenter', function() {
                $(this).find('.psu-service-image img').css('transform', 'scale(1.05)');
            }).on('mouseleave', function() {
                $(this).find('.psu-service-image img').css('transform', 'scale(1)');
            });
        });

        // เพิ่ม particle effect เมื่อ hover ปุ่ม
        $('.psu-btn').on('mouseenter', function() {
            if (!$(this).hasClass('psu-btn-disabled')) {
                createSparkle(this);
            }
        });
    }

    function createSparkle(button) {
        const sparkle = $('<div class="psu-sparkle">✨</div>');
        sparkle.css({
            position: 'absolute',
            top: Math.random() * 100 + '%',
            left: Math.random() * 100 + '%',
            fontSize: '12px',
            pointerEvents: 'none',
            animation: 'sparkleFloat 1s ease-out forwards',
            zIndex: 1000
        });
        
        $(button).css('position', 'relative').append(sparkle);
        
        setTimeout(() => sparkle.remove(), 1000);
        
        // เพิ่ม CSS animation
        if (!$('#sparkle-animation').length) {
            $('head').append(`
                <style id="sparkle-animation">
                @keyframes sparkleFloat {
                    0% { opacity: 1; transform: translateY(0) scale(1); }
                    100% { opacity: 0; transform: translateY(-20px) scale(0); }
                }
                </style>
            `);
        }
    }

    function showLoadingOverlay() {
        $('#psu-loading-overlay').fadeIn(300);
    }

    function hideLoadingOverlay() {
        $('#psu-loading-overlay').fadeOut(300);
    }

    function bindEvents() {
        // เลือกบริการ
        $(document).on('click', '.psu-select-service', function() {
            const serviceId = $(this).data('service-id');
            const $card = $(this).closest('.psu-service-card');
            
            // เพิ่มเอฟเฟค selection
            $('.psu-service-card').removeClass('psu-service-selected');
            $card.addClass('psu-service-selected');
            
            selectService(serviceId);
        });
        
        // ปฏิทิน
        $(document).on('click', '#prev-month', function() {
            $(this).addClass('psu-btn-loading');
            setTimeout(() => {
                changeMonth(-1);
                $(this).removeClass('psu-btn-loading');
            }, 200);
        });
        
        $(document).on('click', '#next-month', function() {
            $(this).addClass('psu-btn-loading');
            setTimeout(() => {
                changeMonth(1);
                $(this).removeClass('psu-btn-loading');
            }, 200);
        });
        
        $(document).on('click', '.psu-calendar-day-available', function() {
            const date = $(this).data('date');
            
            // ตรวจสอบว่าวันนี้เต็มหรือไม่
            if ($(this).hasClass('psu-calendar-day-full')) {
                showNotification('🚫 วันที่เลือกเต็มแล้ว กรุณาเลือกวันอื่น', 'error');
                return;
            }
            
            // ตรวจสอบว่าวันนี้ไม่ทำการหรือไม่
            if ($(this).hasClass('psu-calendar-day-unavailable')) {
                showNotification('⏹️ วันที่เลือกไม่ทำการ กรุณาเลือกวันอื่น', 'error');
                return;
            }
            
            // เพิ่มเอฟเฟค ripple
            createRippleEffect(this);
            
            selectDate(date);
        });
        
        // ปุ่ม navigation
        $(document).on('click', '#next-to-step-3', function() {
            if (!$(this).hasClass('psu-btn-disabled')) {
                showLoadingOverlay();
                loadTimeslots();
                setTimeout(() => {
                    psuGoToStep(3);
                    hideLoadingOverlay();
                }, 500);
            }
        });
        
        $(document).on('click', '#next-to-step-4', function() {
            if (!$(this).hasClass('psu-btn-disabled')) {
                updateBookingSummary();
                psuGoToStep(4);
            }
        });
        
        // เลือก timeslot (เฉพาะที่ว่าง)
        $(document).on('click', '.psu-timeslot-available', function() {
            if ($(this).data('clickable') === true) {
                createRippleEffect(this);
                toggleTimeslot(this);
            }
        });
        
        // ส่งการจอง
        $(document).on('click', '#submit-booking', function() {
            if (validateCustomFields()) {
                showLoadingOverlay();
                submitBooking();
            }
        });
        
        // Bind custom field validation
        bindCustomFieldValidation();
        
        // เพิ่ม auto-save draft
        bindAutoSave();
    }

    function createRippleEffect(element) {
        const $element = $(element);
        const ripple = $('<span class="psu-ripple"></span>');
        
        $element.css('position', 'relative').append(ripple);
        
        const size = Math.max($element.outerWidth(), $element.outerHeight());
        ripple.css({
            width: size,
            height: size,
            position: 'absolute',
            borderRadius: '50%',
            background: 'rgba(43, 63, 106, 0.3)',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%) scale(0)',
            animation: 'ripple 0.6s ease-out',
            pointerEvents: 'none',
            zIndex: 1
        });
        
        setTimeout(() => ripple.remove(), 600);
        
        // เพิ่ม CSS animation
        if (!$('#ripple-animation').length) {
            $('head').append(`
                <style id="ripple-animation">
                @keyframes ripple {
                    to { transform: translate(-50%, -50%) scale(2); opacity: 0; }
                }
                </style>
            `);
        }
    }
    
    function bindAutoSave() {
        // Auto-save form data ทุก 30 วินาที
        setInterval(() => {
            if (currentStep >= 2) {
                saveFormDraft();
            }
        }, 30000);
        
        // Save on form change
        $(document).on('change input', '#psu-customer-form input, #psu-customer-form textarea, #psu-customer-form select', function() {
            debounce(saveFormDraft, 2000)();
        });
    }

    function saveFormDraft() {
        const formData = {
            selectedService: selectedService,
            selectedDate: selectedDate,
            selectedTimeslots: selectedTimeslots,
            customerInfo: $('#psu-customer-form').serializeArray()
        };
        
        localStorage.setItem('psu_booking_draft', JSON.stringify(formData));
    }

    function loadFormDraft() {
        const draft = localStorage.getItem('psu_booking_draft');
        if (draft) {
            const data = JSON.parse(draft);
            // Restore draft data if needed
            return data;
        }
        return null;
    }

    function clearFormDraft() {
        localStorage.removeItem('psu_booking_draft');
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
    
    function bindCustomFieldValidation() {
        // Real-time validation สำหรับ custom fields
        $(document).on('blur change', '[name^="custom_field_"][required]', function() {
            validateField($(this));
        });
        
        $(document).on('change', '[name^="custom_field_"][required][type="radio"], [name^="custom_field_"][required][type="checkbox"]', function() {
            validateField($(this));
        });
    }
    
    function validateField($field) {
        const fieldType = $field.attr('type');
        const $formGroup = $field.closest('.psu-form-group');
        
        // ลบ error message เก่า
        $formGroup.find('.psu-field-error').remove();
        $formGroup.removeClass('psu-field-error-group');
        
        let isValid = true;
        let errorMessage = '';
        
        if (fieldType === 'checkbox') {
            const fieldName = $field.attr('name').replace('[]', '');
            const checkedCount = $(`[name="${fieldName}[]"]:checked`).length;
            if (checkedCount === 0) {
                isValid = false;
                errorMessage = 'กรุณาเลือกอย่างน้อย 1 ตัวเลือก';
            }
        } else if (fieldType === 'radio') {
            const fieldName = $field.attr('name');
            const checkedCount = $(`[name="${fieldName}"]:checked`).length;
            if (checkedCount === 0) {
                isValid = false;
                errorMessage = 'กรุณาเลือก 1 ตัวเลือก';
            }
        } else {
            const value = $field.val().trim();
            if (!value) {
                isValid = false;
                errorMessage = 'กรุณากรอกข้อมูลในฟิลด์นี้';
            }
        }
        
        if (!isValid) {
            $formGroup.addClass('psu-field-error-group');
            $formGroup.append(`<div class="psu-field-error">${errorMessage}</div>`);
            
            // เพิ่ม shake animation
            $formGroup.addClass('psu-shake');
            setTimeout(() => $formGroup.removeClass('psu-shake'), 500);
        }
        
        return isValid;
    }

    function validateCustomFields() {
        let allValid = true;
        
        // Validate required custom fields
        $('[name^="custom_field_"][required]').each(function() {
            if (!validateField($(this))) {
                allValid = false;
            }
        });
        
        return allValid;
    }

    function selectService(serviceId) {
        showLoadingOverlay();
        
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
                    
                    // เพิ่ม success animation
                    setTimeout(() => {
                        hideLoadingOverlay();
                        psuGoToStep(2);
                        showNotification('✅ เลือกบริการสำเร็จ!', 'success');
                        
                        // โหลดสถานะปฏิทินหลังจากเลือกบริการ
                        renderCalendar();
                    }, 800);
                } else {
                    hideLoadingOverlay();
                    showNotification('❌ ไม่สามารถโหลดข้อมูลบริการได้', 'error');
                }
            },
            error: function() {
                hideLoadingOverlay();
                showNotification('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
            }
        });
    }

    function showNotification(message, type = 'info') {
        const notification = $(`
            <div class="psu-notification psu-notification-${type}">
                <span>${message}</span>
                <button class="psu-notification-close">✕</button>
            </div>
        `);
        
        // เพิ่ม CSS สำหรับ notification
        if (!$('#notification-styles').length) {
            $('head').append(`
                <style id="notification-styles">
                .psu-notification {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 15px 20px;
                    border-radius: 12px;
                    color: white;
                    font-weight: 600;
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    gap: 15px;
                    box-shadow: var(--psu-shadow-medium);
                    animation: slideInRight 0.3s ease;
                }
                .psu-notification-success { background: linear-gradient(135deg, #27ae60, #2ecc71); }
                .psu-notification-error { background: linear-gradient(135deg, #e74c3c, #c0392b); }
                .psu-notification-info { background: linear-gradient(135deg, #3498db, #2980b9); }
                .psu-notification-close {
                    background: none;
                    border: none;
                    color: white;
                    font-size: 16px;
                    cursor: pointer;
                    padding: 0;
                    width: 20px;
                    height: 20px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                .psu-shake {
                    animation: shake 0.5s ease-in-out;
                }
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-5px); }
                    75% { transform: translateX(5px); }
                }
                </style>
            `);
        }
        
        $('body').append(notification);
        
        notification.find('.psu-notification-close').on('click', function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    function updateSelectedServiceInfo() {
        const priceText = selectedService.price > 0 ? 
            Number(selectedService.price).toLocaleString() + ' บาท/ชั่วโมง' : 'ไม่มีค่าบริการ';
        
        const html = `
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 18px; color: var(--psu-primary); margin-bottom: 5px;">
                        ${selectedService.name}
                    </div>
                    <div style="color: var(--psu-text-light); margin-bottom: 10px;">
                        ${selectedService.description}
                    </div>
                    <div style="display: flex; gap: 20px; font-size: 14px;">
                        <span><strong>💰 ราคา:</strong> ${priceText}</span>
                        <span><strong>⏱️ ระยะเวลา:</strong> ${selectedService.duration} นาที</span>
                    </div>
                </div>
                <div style="color: var(--psu-primary); font-size: 24px;">✅</div>
            </div>
        `;
        $('#service-details-display').html(html);
        
        // อัปเดตชื่อบริการในทุก step
        $('#current-service-name').text(selectedService.name);
        $('#booking-summary-service').text(selectedService.name);
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
        
        // เคลียร์การเลือกวันเมื่อเปลี่ยนเดือน
        selectedDate = null;
        selectedTimeslots = [];
        selectedTimeslotCategory = null;
        $('#next-to-step-3').addClass('psu-btn-disabled').prop('disabled', true);
        
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
        
        let calendarHtml = '';
        
        // เติมวันว่างก่อนวันที่ 1
        for (let i = 0; i < firstDay; i++) {
            calendarHtml += '<div class="psu-calendar-day psu-calendar-day-empty"></div>';
        }
        
        // สร้างวันที่ในเดือน
        for (let day = 1; day <= daysInMonth; day++) {
            const currentDate = new Date(currentYear, currentMonth, day);
            currentDate.setHours(0, 0, 0, 0);
            
            // ใช้ local timezone แทน UTC เพื่อป้องกันปัญหาการเลื่อนวันที่
            const dateString = formatDateString(currentYear, currentMonth + 1, day);
            let dayClass = 'psu-calendar-day';
            
            if (currentDate < today) {
                dayClass += ' psu-calendar-day-disabled';
            } else {
                dayClass += ' psu-calendar-day-available';
            }
            
            if (selectedDate === dateString) {
                dayClass += ' psu-calendar-day-selected';
            }
            
            calendarHtml += `
                <div class="${dayClass}" data-date="${dateString}">
                    <span class="psu-day-number">${day}</span>
                    <div class="psu-calendar-day-indicator">⏳</div>
                </div>
            `;
        }
        
        $('#psu-calendar').html(calendarHtml);
        
        // เพิ่ม calendar legend หากยังไม่มี
        if (!$('.psu-calendar-legend').length) {
            $('.psu-calendar-container').append(`
                <div class="psu-calendar-legend">
                    <div class="psu-legend-item">
                        <span>🟢 ว่าง</span>
                    </div>
                    <div class="psu-legend-item">
                        <span>🟡 จองบางส่วน</span>
                    </div>
                    <div class="psu-legend-item">
                        <span>🔴 เต็ม</span>
                    </div>
                    <div class="psu-legend-item">
                        <span>⚫ ไม่ทำการ</span>
                    </div>
                </div>
            `);
        }
        
        // โหลดสถานะการจองหลังจากสร้างปฏิทิน
        if (selectedService) {
            loadCalendarStatus();
        }
        
        // เพิ่ม animation สำหรับวันที่
        setTimeout(() => {
            $('.psu-calendar-day').each(function(index) {
                $(this).css({
                    'animation': `fadeInScale 0.3s ease forwards`,
                    'animation-delay': (index * 0.02) + 's',
                    'opacity': '0'
                });
            });
            
            // เพิ่ม CSS animation
            if (!$('#calendar-animation').length) {
                $('head').append(`
                    <style id="calendar-animation">
                    @keyframes fadeInScale {
                        from { opacity: 0; transform: scale(0.8); }
                        to { opacity: 1; transform: scale(1); }
                    }
                    </style>
                `);
            }
        }, 100);
    }

    function loadCalendarStatus() {
        if (!selectedService) return;
        
        // แสดง loading indicator
        $('.psu-calendar-day-available .psu-calendar-day-indicator').text('⏳');
        
        // ดึงสถานะทั้งเดือนครั้งเดียว
        getMonthBookingStatus(currentYear, currentMonth).then(statuses => {
            console.log('📊 สถานะที่ได้จาก Backend:', statuses);
            
            $('.psu-calendar-day-available').each(function() {
                const $dayElement = $(this);
                const dateString = $dayElement.data('date');
                
                if (dateString) {
                    const status = statuses[dateString] || 'available';
                    console.log(`📅 วันที่ ${dateString}: สถานะ = ${status}`);
                    updateDateStatus($dayElement, status);
                } else {
                    // ถ้าไม่มีข้อมูล แสดงเป็น available (สำหรับวันที่ผ่านมาแล้ว)
                    updateDateStatus($dayElement, 'available');
                }
            });
            
            // แจ้งให้ผู้ใช้ทราบว่าโหลดเสร็จแล้ว
            const statusCount = Object.keys(statuses).length;
            console.log('📅 โหลดสถานะปฏิทินเสร็จแล้ว - รวม', statusCount, 'วัน');
            
            // แสดง notification สำหรับผู้ใช้
            if (statusCount > 0) {
                // showNotification(`📊 โหลดข้อมูล ${statusCount} วันเสร็จแล้ว (1 request แทน ${statusCount} requests!)`, 'info');
            }
        }).catch(error => {
            console.error('Error loading calendar status:', error);
            // ถ้าเกิดข้อผิดพลาด ให้แสดงทุกวันเป็น available
            $('.psu-calendar-day-available').each(function() {
                updateDateStatus($(this), 'available');
            });
        });
    }

    async function getMonthBookingStatus(year, month) {
        if (!selectedService) return {};
        
        const startTime = performance.now();
        
        try {
            console.log(`📊 ดึงสถานะเดือน: ${year}-${month+1} (JS month: ${month}, Year: ${year})`);
            
            const response = await $.ajax({
                url: psu_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'psu_get_month_booking_status',
                    service_id: selectedService.id,
                    year: year,
                    month: month, // 0-11 (JavaScript month)
                    nonce: psu_ajax.nonce
                }
            });
            
            if (response.success) {
                const endTime = performance.now();
                console.log(`🚀 ดึงสถานะทั้งเดือนใน ${(endTime - startTime).toFixed(2)}ms (แทนที่ 31 requests!)`);
                return response.data || {};
            }
        } catch (error) {
            console.error('Error getting month booking status:', error);
        }
        
        return {};
    }
    
    // เก็บฟังก์ชันเก่าไว้สำหรับใช้แยกเฉพาะวัน (ถ้าจำเป็น)
    async function getDateBookingStatus(date) {
        if (!selectedService) return 'available';
        
        try {
            const response = await $.ajax({
                url: psu_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'psu_get_date_booking_status',
                    service_id: selectedService.id,
                    date: date,
                    nonce: psu_ajax.nonce
                }
            });
            
            if (response.success) {
                return response.data.status || 'available';
            }
        } catch (error) {
            console.error('Error getting booking status:', error);
        }
        
        return 'available';
    }

    function updateDateStatus($dayElement, status) {
        const $indicator = $dayElement.find('.psu-calendar-day-indicator');
        
        // ลบ class เก่า
        $dayElement.removeClass('psu-calendar-day-partial psu-calendar-day-full psu-calendar-day-unavailable');
        $indicator.removeClass('available partial full unavailable');
        
        // เพิ่ม class ใหม่ตามสถานะ
        switch (status) {
            case 'partial':
                $dayElement.addClass('psu-calendar-day-partial');
                $indicator.addClass('partial');
                break;
            case 'full':
                $dayElement.addClass('psu-calendar-day-full');
                $dayElement.removeClass('psu-calendar-day-available'); // ไม่ให้คลิกได้
                $indicator.addClass('full');
                break;
            case 'unavailable':
                $dayElement.addClass('psu-calendar-day-unavailable');
                $dayElement.removeClass('psu-calendar-day-available'); // ไม่ให้คลิกได้
                $indicator.addClass('unavailable');
                break;
            default: // available
                $indicator.addClass('available');
                break;
        }
        
        // เพิ่ม tooltip สำหรับแสดงสถานะ
        let tooltipText = '';
        switch (status) {
            case 'available':
                tooltipText = '✅ ว่าง - มีช่วงเวลาให้เลือก';
                break;
            case 'partial':
                tooltipText = '⚠️ จองบางส่วน - มีช่วงเวลาบางส่วนว่าง';
                break;
            case 'full':
                tooltipText = '🚫 เต็ม - ไม่มีช่วงเวลาว่าง';
                break;
            case 'unavailable':
                tooltipText = '⏹️ ไม่ทำการ - วันหยุดหรือปิดบริการ';
                break;
        }
        
        $dayElement.attr('title', tooltipText);
    }

    function selectDate(date) {
        // รีเซ็ตตัวแปรเมื่อเปลี่ยนวัน
        selectedDate = date;
        selectedTimeslots = [];
        selectedTimeslotCategory = null;
        
        // อัพเดต UI
        $('.psu-calendar-day').removeClass('psu-calendar-day-selected');
        $(`.psu-calendar-day[data-date="${date}"]`).addClass('psu-calendar-day-selected');
        
        // แสดงวันที่เลือกใน step 3
        $('#selected-date-display').text(formatThaiDate(date));
        
        // อัปเดตสรุปในส่วนอื่นๆ
        $('#summary-date').text(formatThaiDate(date));
        
        // เปิดใช้งานปุ่มถัดไป
        $('#next-to-step-3').removeClass('psu-btn-disabled').prop('disabled', false);
        
        showNotification('📅 เลือกวันที่สำเร็จ!', 'success');
    }

    function loadTimeslots() {
        if (!selectedService || !selectedDate) return;
        
        $('#timeslots-container').html(`
            <div class="psu-loading">
                <div style="font-size: 32px; margin-bottom: 20px;">⏳</div>
                <div style="font-size: 18px; font-weight: 600;">กำลังโหลดช่วงเวลาที่ว่าง...</div>
                <div style="margin-top: 10px; color: var(--psu-text-light);">โปรดรอสักครู่</div>
            </div>
        `);
        
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
                    setTimeout(() => {
                        renderTimeslots(response.data);
                    }, 500);
                } else {
                    $('#timeslots-container').html(`
                        <div class="psu-no-services">
                            <div style="font-size: 48px; margin-bottom: 20px;">⚠️</div>
                            <h4>ไม่พบช่วงเวลาที่ว่าง</h4>
                            <p>วันที่เลือกไม่มีช่วงเวลาให้จองหรือเต็มแล้ว</p>
                        </div>
                    `);
                }
            },
            error: function() {
                $('#timeslots-container').html(`
                    <div class="psu-no-services">
                        <div style="font-size: 48px; margin-bottom: 20px;">❌</div>
                        <h4>เกิดข้อผิดพลาด</h4>
                        <p>ไม่สามารถโหลดข้อมูลช่วงเวลาได้</p>
                    </div>
                `);
            }
        });
    }

    function renderTimeslots(categories) {
        let html = '';
        
        categories.forEach((category, categoryIndex) => {
            html += `
                <div class="psu-timeslot-category" style="animation: slideInUp 0.5s ease forwards; animation-delay: ${categoryIndex * 0.1}s; opacity: 0;">
                    <h4>🕐 ${category.category}</h4>
                    <div class="psu-timeslots-grid">
            `;
            
            category.slots.forEach((slot, slotIndex) => {
                const availableClass = slot.available ? 'psu-timeslot-available' : 'psu-timeslot-booked';
                const clickable = slot.available ? 'true' : 'false';
                
                html += `
                    <div class="psu-timeslot ${availableClass}" 
                         data-start="${slot.start}" 
                         data-end="${slot.end}" 
                         data-price="${slot.price}" 
                         data-display="${slot.display}"
                         data-category="${category.category}"
                         data-clickable="${clickable}"
                         style="animation: slideInUp 0.4s ease forwards; animation-delay: ${(categoryIndex * 0.1) + (slotIndex * 0.05)}s; opacity: 0;">
                        <div class="psu-timeslot-time">${slot.display}</div>
                        <div class="psu-timeslot-price">${slot.price_display}</div>
                        ${!slot.available ? '<div style="color: var(--psu-error); font-size: 12px; margin-top: 5px;">🚫 ไม่ว่าง</div>' : ''}
                    </div>
                `;
            });
            
            html += '</div></div>';
        });
        
        $('#timeslots-container').html(html);
        
        // เพิ่ม CSS animation
        if (!$('#timeslot-animation').length) {
            $('head').append(`
                <style id="timeslot-animation">
                @keyframes slideInUp {
                    from { opacity: 0; transform: translateY(30px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                </style>
            `);
        }
    }

    function toggleTimeslot(element) {
        const $element = $(element);
        const isSelected = $element.hasClass('psu-timeslot-selected');
        const currentCategory = $element.data('category');
        
        if (isSelected) {
            // ยกเลิกการเลือก
            $element.removeClass('psu-timeslot-selected');
            selectedTimeslots = selectedTimeslots.filter(slot => 
                !(slot.start === $element.data('start') && slot.end === $element.data('end'))
            );
            
            // ถ้าไม่มี timeslot เหลือ ให้รีเซ็ต category
            if (selectedTimeslots.length === 0) {
                selectedTimeslotCategory = null;
            }
        } else {
            // ตรวจสอบว่าเป็นหมวดหมู่เดียวกันหรือไม่
            if (selectedTimeslotCategory && selectedTimeslotCategory !== currentCategory) {
                // showNotification('🔄 เปลี่ยนหมวดหมู่การจอง - รีเซ็ตการเลือกก่อนหน้า', 'info');
                
                // รีเซ็ตการเลือกทั้งหมวดหมู่
                $('.psu-timeslot-selected').removeClass('psu-timeslot-selected');
                selectedTimeslots = [];
            }
            
            // ตั้งหมวดหมู่ใหม่
            selectedTimeslotCategory = currentCategory;
            
            // เลือก timeslot
            $element.addClass('psu-timeslot-selected');
            selectedTimeslots.push({
                start: $element.data('start'),
                end: $element.data('end'),
                price: parseFloat($element.data('price')),
                display: $element.data('display'),
                category: currentCategory
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
        
        // จัดกลุ่มตาม category
        const groupedSlots = selectedTimeslots.reduce((groups, slot) => {
            if (!groups[slot.category]) {
                groups[slot.category] = [];
            }
            groups[slot.category].push(slot);
            return groups;
        }, {});
        
        let listHtml = '';
        let totalPrice = 0;
        
        Object.keys(groupedSlots).forEach(category => {
            listHtml += `<li style="margin-bottom: 15px;">
                <div style="font-weight: 600; color: var(--psu-primary); margin-bottom: 8px;">
                    📂 ${category}
                </div>
            `;
            
            groupedSlots[category].forEach(slot => {
                listHtml += `
                    <div style="margin-left: 20px; margin-bottom: 5px; font-size: 14px;">
                        🕐 ${slot.display} - 💰 ${slot.price > 0 ? Number(slot.price).toLocaleString() + ' บาท' : 'ฟรี'}
                    </div>
                `;
                totalPrice += slot.price;
            });
            
            listHtml += '</li>';
        });
        
        $('#selected-timeslots-list').html(listHtml);
        $('#total-price').text(Number(totalPrice).toLocaleString());
        $('#price-unit').text(totalPrice > 0 ? 'บาท' : '');
        
        $('#selected-timeslots').show();
        $('#next-to-step-4').removeClass('psu-btn-disabled').prop('disabled', false);
        
        // อัปเดตสรุปใน step 4
        updateFinalSummary();
    }

    function updateBookingSummary() {
        // Update service name และ date ใน step 4
        $('#current-service-name').text(selectedService ? selectedService.name : '-');
        $('#booking-summary-service').text(selectedService ? selectedService.name : '-');
        $('#booking-summary-date').text(selectedDate ? formatThaiDate(selectedDate) : '-');
        $('#booking-summary-timeslots').text(selectedTimeslots.map(s => s.display).join(', '));
        
        const totalPrice = selectedTimeslots.reduce((sum, slot) => sum + slot.price, 0);
        $('#booking-summary-total').text(Number(totalPrice).toLocaleString() + ' บาท');
        
        // อัปเดตสรุปการจองใน step 4
        updateFinalSummary();
    }
    
    function updateFinalSummary() {
        if (selectedService && selectedDate && selectedTimeslots.length > 0) {
            const totalPrice = selectedTimeslots.reduce((sum, slot) => sum + slot.price, 0);
            const timeslotsText = selectedTimeslots.map(s => s.display).join(', ');
            
            $('#summary-service').text(selectedService.name);
            $('#summary-date').text(formatThaiDate(selectedDate));
            $('#summary-timeslots').text(timeslotsText);
            $('#summary-total').text(Number(totalPrice).toLocaleString() + ' บาท');
        }
    }

    function submitBooking() {
        console.log('=== PSU Booking: Starting submission ===');
        console.log('Selected service:', selectedService);
        console.log('Selected date:', selectedDate);
        console.log('Selected timeslots:', selectedTimeslots);
        
        if (!validateCustomFields()) {
            hideLoadingOverlay();
            showNotification('❌ กรุณากรอกข้อมูลให้ครบถ้วน', 'error');
            return;
        }
        
        // เตรียมข้อมูล timeslots
        const timeslotsForSubmission = selectedTimeslots.map(slot => ({
            start: slot.start,
            end: slot.end,
            price: slot.price,
            display: slot.display,
            category: slot.category
        }));
        
        console.log('Timeslots for submission:', timeslotsForSubmission);
        const timeslotsJson = JSON.stringify(timeslotsForSubmission);
        console.log('Timeslots JSON string:', timeslotsJson);
        
        const formData = new FormData();
        formData.append('action', 'psu_submit_booking');
        formData.append('nonce', psu_ajax.nonce);
        formData.append('service_id', selectedService.id);
        formData.append('booking_date', selectedDate);
        formData.append('timeslots', timeslotsJson);
        
        // เพิ่มข้อมูลจากฟอร์ม
        $('#psu-customer-form').serializeArray().forEach(field => {
            console.log('Form field:', field.name, '=', field.value);
            formData.append(field.name, field.value);
        });
        
        // เพิ่มไฟล์ถ้ามี
        $('#psu-customer-form input[type="file"]').each(function() {
            if (this.files[0]) {
                console.log('File field:', this.name, '=', this.files[0].name);
                formData.append(this.name, this.files[0]);
            }
        });
        
        // Debug FormData contents
        console.log('=== FormData Contents ===');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ':', pair[1]);
        }
        
        $.ajax({
            url: psu_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                console.log('Sending AJAX request to:', psu_ajax.ajax_url);
            },
            success: function(response) {
                console.log('=== AJAX Response ===');
                console.log('Full response:', response);
                console.log('Success:', response.success);
                console.log('Data:', response.data);
                
                hideLoadingOverlay();
                if (response.success) {
                    clearFormDraft();
                    showSuccessMessage(response.data);
                    psuGoToStep(5);
                } else {
                    console.error('Booking failed:', response.data.message);
                    showNotification('❌ ' + (response.data.message || 'เกิดข้อผิดพลาดในการจอง'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('=== AJAX Error ===');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                
                hideLoadingOverlay();
                showNotification('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ: ' + error, 'error');
            }
        });
    }

    function showSuccessMessage(data) {
        const totalPrice = selectedTimeslots.reduce((sum, slot) => sum + slot.price, 0);
        
        const summaryHtml = `
            <div style="display: grid; gap: 12px;">
                <div><strong>🏢 บริการ:</strong> ${selectedService.name}</div>
                <div><strong>📅 วันที่:</strong> ${formatThaiDate(selectedDate)}</div>
                <div><strong>🕐 เวลา:</strong> ${selectedTimeslots.map(s => s.display).join(', ')}</div>
                <div><strong>💰 ราคารวม:</strong> ${Number(totalPrice).toLocaleString()} บาท</div>
                <div><strong>📋 สถานะ:</strong> <span style="color: var(--psu-warning);">รออนุมัติ</span></div>
                ${data.booking_ids ? `<div><strong>🔢 หมายเลขการจอง:</strong> ${data.booking_ids.join(', ')}</div>` : ''}
            </div>
        `;
        
        $('#booking-summary-details').html(summaryHtml);
        
        // เพิ่ม confetti effect
        createConfetti();
    }

    function createConfetti() {
        const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#f39c12', '#e74c3c', '#9b59b6'];
        
        for (let i = 0; i < 50; i++) {
            const confetti = $('<div class="confetti">🎉</div>');
            confetti.css({
                position: 'fixed',
                left: Math.random() * 100 + '%',
                top: '-10px',
                fontSize: Math.random() * 20 + 15 + 'px',
                color: colors[Math.floor(Math.random() * colors.length)],
                pointerEvents: 'none',
                zIndex: 10000,
                animation: `confettiFall ${Math.random() * 2 + 3}s linear forwards`
            });
            
            $('body').append(confetti);
            
            setTimeout(() => confetti.remove(), 5000);
        }
        
        // เพิ่ม CSS animation
        if (!$('#confetti-animation').length) {
            $('head').append(`
                <style id="confetti-animation">
                @keyframes confettiFall {
                    to {
                        transform: translateY(100vh) rotate(360deg);
                        opacity: 0;
                    }
                }
                </style>
            `);
        }
    }

    function psuGoToStep(step) {
        $('.psu-step').addClass('psu-step-hidden');
        $(`#step-${step}`).removeClass('psu-step-hidden');
        currentStep = step;
        
        // อัปเดตการแสดงผลตาม step
        if (step === 1 && selectedService) {
            // ไฮไลท์บริการที่เลือกแล้วใน step 1
            $('.psu-service-card').removeClass('psu-service-selected');
            $(`.psu-service-card[data-service-id="${selectedService.id}"]`).addClass('psu-service-selected');
        } else if (step === 4) {
            // อัปเดตสรุปการจองใน step 4
            updateFinalSummary();
        }
        
        // Scroll to top
        $('.psu-booking-form')[0].scrollIntoView({ 
            behavior: 'smooth', 
            block: 'start' 
        });
    }

    function formatDateString(year, month, day) {
        // สร้าง date string แบบ YYYY-MM-DD โดยใช้ local timezone
        const yyyy = year.toString();
        const mm = month.toString().padStart(2, '0');
        const dd = day.toString().padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }
    
    function formatThaiDate(date) {
        const months = [
            'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
            'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
        ];
        
        const d = new Date(date);
        const day = d.getDate();
        const month = months[d.getMonth()];
        const year = d.getFullYear() + 543;
        
        return `${day} ${month} ${year}`;
    }

    // Export functions สำหรับให้ HTML เรียกใช้
    window.psuGoToStep = psuGoToStep;

})(jQuery); 