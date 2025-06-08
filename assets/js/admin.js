/**
 * PSU Simple Booking - Admin JavaScript
 * JavaScript สำหรับหน้า Admin ของระบบจอง
 */

jQuery(document).ready(function($) {
    'use strict';

    // ตัวแปรสำหรับ AJAX
    const ajaxUrl = psu_admin_ajax.ajax_url;
    const nonce = psu_admin_ajax.nonce;

    /**
     * ฟังก์ชันทั่วไป
     */
    
    // แสดง Loading state
    function showLoading(element) {
        $(element).addClass('psu-loading');
    }

    // ซ่อน Loading state
    function hideLoading(element) {
        $(element).removeClass('psu-loading');
    }

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

    // ยืนยันการกระทำ
    function confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }

    /**
     * หน้าจัดการบริการ (Services)
     */
    
    // สลับการแสดงฟอร์มบริการ
    window.toggleServiceForm = function() {
        const form = $('#service-form');
        const list = $('#services-list');
        
        if (form.is(':visible')) {
            form.hide();
            list.show();
            // รีเซ็ตฟอร์มหากไม่ใช่การแก้ไข
            if (!$('input[name="service_id"]').length) {
                form.find('form')[0].reset();
            }
        } else {
            form.show();
            list.hide();
        }
    };

    // แสดง/ซ่อนฟิลด์ timeslot duration ตามประเภทการจอง
    $(document).on('change', '#timeslot_type', function() {
        const durationRow = $('#timeslot_duration_row');
        if ($(this).val() === 'hourly') {
            durationRow.show();
        } else {
            durationRow.hide();
        }
    });

    // เริ่มต้นการแสดงฟิลด์ duration
    if ($('#timeslot_type').length) {
        $('#timeslot_type').trigger('change');
    }

    // การลบบริการ
    $(document).on('click', '.delete-service', function(e) {
        e.preventDefault();
        
        const serviceId = $(this).data('service-id');
        const serviceName = $(this).data('service-name');
        
        confirmAction(`คุณต้องการลบบริการ "${serviceName}" หรือไม่?\n\nหากมีการจองที่ใช้บริการนี้ จะไม่สามารถลบได้`, () => {
            window.location.href = $(this).attr('href');
        });
    });

    /**
     * หน้าจัดการการจอง (Bookings)
     */
    
    // แสดง/ซ่อนฟิลด์เหตุผลการปฏิเสธ
    $(document).on('change', '#status', function() {
        const rejectionRow = $('#rejection-reason-row');
        if ($(this).val() === 'rejected') {
            rejectionRow.show();
            $('#rejection_reason').prop('required', true);
        } else {
            rejectionRow.hide();
            $('#rejection_reason').prop('required', false);
        }
    });

    // ปิด Modal
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

    // การเปลี่ยนสถานะการจองแบบ Quick Action
    $(document).on('click', '.quick-approve', function(e) {
        e.preventDefault();
        
        const bookingId = $(this).data('booking-id');
        const row = $(this).closest('tr');
        
        showLoading(row);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'psu_quick_status_change',
                booking_id: bookingId,
                status: 'approved',
                nonce: nonce
            },
            success: function(response) {
                hideLoading(row);
                if (response.success) {
                    showNotice('อนุมัติการจองสำเร็จ');
                    // รีเฟรชหน้า
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotice(response.data.message || 'เกิดข้อผิดพลาด', 'error');
                }
            },
            error: function() {
                hideLoading(row);
                showNotice('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
            }
        });
    });

    // การปฏิเสธการจองแบบ Quick Action
    $(document).on('click', '.quick-reject', function(e) {
        e.preventDefault();
        
        const bookingId = $(this).data('booking-id');
        const reason = prompt('กรุณาระบุเหตุผลในการปฏิเสธ:');
        
        if (reason === null) return; // ยกเลิกการกระทำ
        
        const row = $(this).closest('tr');
        showLoading(row);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'psu_quick_status_change',
                booking_id: bookingId,
                status: 'rejected',
                rejection_reason: reason,
                nonce: nonce
            },
            success: function(response) {
                hideLoading(row);
                if (response.success) {
                    showNotice('ปฏิเสธการจองสำเร็จ');
                    // รีเฟรชหน้า
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotice(response.data.message || 'เกิดข้อผิดพลาด', 'error');
                }
            },
            error: function() {
                hideLoading(row);
                showNotice('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
            }
        });
    });

    // ดูรายละเอียดการจอง
    window.viewBookingDetails = function(bookingId) {
        showLoading('body');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'psu_get_booking_details',
                booking_id: bookingId,
                nonce: nonce
            },
            success: function(response) {
                hideLoading('body');
                if (response.success) {
                    showBookingDetailsModal(response.data);
                } else {
                    showNotice(response.data.message || 'ไม่พบข้อมูลการจอง', 'error');
                }
            },
            error: function() {
                hideLoading('body');
                showNotice('เกิดข้อผิดพลาดในการโหลดข้อมูล', 'error');
            }
        });
    };

    // แสดง Modal รายละเอียดการจอง
    function showBookingDetailsModal(booking) {
        const modal = $(`
            <div class="psu-modal" style="display: block;">
                <div class="psu-modal-content">
                    <div class="psu-modal-header">
                        <h2>รายละเอียดการจอง #${String(booking.id).padStart(6, '0')}</h2>
                        <span class="psu-modal-close" onclick="closeBookingDetailsModal()">&times;</span>
                    </div>
                    <div class="psu-modal-body">
                        <div class="booking-details">
                            <div class="detail-section">
                                <h4>ข้อมูลบริการ</h4>
                                <p><strong>บริการ:</strong> ${booking.service_name}</p>
                                <p><strong>หมวดหมู่:</strong> ${booking.service_category || '-'}</p>
                                <p><strong>ราคา:</strong> ${parseFloat(booking.total_price).toLocaleString()} บาท</p>
                            </div>
                            
                            <div class="detail-section">
                                <h4>ข้อมูลผู้จอง</h4>
                                <p><strong>ชื่อ:</strong> ${booking.customer_name}</p>
                                <p><strong>อีเมล:</strong> <a href="mailto:${booking.customer_email}">${booking.customer_email}</a></p>
                            </div>
                            
                            <div class="detail-section">
                                <h4>ข้อมูลการจอง</h4>
                                <p><strong>วันที่จอง:</strong> ${formatDate(booking.booking_date)}</p>
                                <p><strong>เวลา:</strong> ${formatTime(booking.start_time)} - ${formatTime(booking.end_time)}</p>
                                <p><strong>สถานะ:</strong> <span class="psu-status-${booking.status}">${getStatusText(booking.status)}</span></p>
                                <p><strong>วันที่สร้าง:</strong> ${formatDateTime(booking.created_at)}</p>
                            </div>
                            
                            ${booking.additional_info ? `
                                <div class="detail-section">
                                    <h4>ข้อมูลเพิ่มเติม</h4>
                                    <p>${booking.additional_info}</p>
                                </div>
                            ` : ''}
                            
                            ${booking.rejection_reason ? `
                                <div class="detail-section">
                                    <h4>เหตุผลการปฏิเสธ</h4>
                                    <p>${booking.rejection_reason}</p>
                                </div>
                            ` : ''}
                        </div>
                        
                        <div class="modal-actions">
                            <button type="button" class="button" onclick="closeBookingDetailsModal()">ปิด</button>
                            <a href="?page=psu-booking-bookings&action=edit_status&booking_id=${booking.id}" class="button button-primary">แก้ไขสถานะ</a>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
    }

    // ปิด Modal รายละเอียดการจอง
    window.closeBookingDetailsModal = function() {
        $('.psu-modal').fadeOut(300, function() {
            $(this).remove();
        });
    };

    /**
     * หน้าสถิติ (Statistics)
     */
    
    // ส่งออกรายงาน
    window.exportReport = function(format) {
        const params = new URLSearchParams(window.location.search);
        params.set('export', format);
        
        // สร้าง form สำหรับส่งออก
        const form = $('<form>', {
            method: 'POST',
            action: ajaxUrl
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'psu_export_report'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'format',
            value: format
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: nonce
        }));
        
        // เพิ่มพารามิเตอร์การกรอง
        params.forEach((value, key) => {
            if (key !== 'page' && key !== 'export') {
                form.append($('<input>', {
                    type: 'hidden',
                    name: key,
                    value: value
                }));
            }
        });
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        showNotice(`กำลังเตรียมไฟล์ ${format.toUpperCase()} กรุณารอสักครู่...`);
    };

    /**
     * หน้าการตั้งค่า (Settings)
     */
    
    // สลับแท็บการตั้งค่า
    window.switchTab = function(evt, tabName) {
        // ซ่อนเนื้อหาแท็บทั้งหมด
        $('.psu-tab-content').hide();
        
        // ลบคลาส active จากแท็บทั้งหมด
        $('.nav-tab').removeClass('nav-tab-active');
        
        // แสดงแท็บที่เลือกและเพิ่มคลาส active
        $(`#${tabName}-tab`).show();
        $(evt.currentTarget).addClass('nav-tab-active');
        
        // บันทึกแท็บปัจจุบันใน localStorage
        localStorage.setItem('psu_admin_active_tab', tabName);
    };

    // โหลดแท็บที่บันทึกไว้
    function loadSavedTab() {
        const savedTab = localStorage.getItem('psu_admin_active_tab');
        if (savedTab && $(`#${savedTab}-tab`).length) {
            const tabLink = $(`.nav-tab[onclick*="${savedTab}"]`);
            if (tabLink.length) {
                tabLink.click();
                return;
            }
        }
        // แสดงแท็บแรกเป็นค่าเริ่มต้น
        $('.nav-tab:first').click();
    }

    // คัดลอก shortcode
    window.copyToClipboard = function(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                showNotice(`คัดลอก shortcode สำเร็จ: ${text}`);
            }).catch(() => {
                fallbackCopyToClipboard(text);
            });
        } else {
            fallbackCopyToClipboard(text);
        }
    };

    // Fallback สำหรับการคัดลอก
    function fallbackCopyToClipboard(text) {
        const textArea = $('<textarea>');
        textArea.val(text);
        $('body').append(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            showNotice(`คัดลอก shortcode สำเร็จ: ${text}`);
        } catch (err) {
            showNotice('ไม่สามารถคัดลอกได้ กรุณาคัดลอกด้วยตนเอง', 'error');
        }
        textArea.remove();
    }

    // คืนค่าเริ่มต้น
    window.resetToDefaults = function() {
        confirmAction('คุณต้องการคืนค่าเริ่มต้นหรือไม่? การเปลี่ยนแปลงที่ยังไม่ได้บันทึกจะหายไป', () => {
            showLoading('body');
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'psu_reset_settings',
                    nonce: nonce
                },
                success: function(response) {
                    hideLoading('body');
                    if (response.success) {
                        showNotice('คืนค่าเริ่มต้นสำเร็จ');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotice(response.data.message || 'เกิดข้อผิดพลาด', 'error');
                    }
                },
                error: function() {
                    hideLoading('body');
                    showNotice('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
                }
            });
        });
    };

    /**
     * ฟังก์ชันช่วยเหลือ
     */
    
    // จัดรูปแบบวันที่
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('th-TH', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    // จัดรูปแบบเวลา
    function formatTime(timeString) {
        const [hours, minutes] = timeString.split(':');
        return `${hours}:${minutes}`;
    }

    // จัดรูปแบบวันที่และเวลา
    function formatDateTime(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleString('th-TH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // แปลงสถานะเป็นข้อความ
    function getStatusText(status) {
        const statusMap = {
            'pending': 'รออนุมัติ',
            'approved': 'อนุมัติแล้ว',
            'rejected': 'ถูกปฏิเสธ'
        };
        return statusMap[status] || status;
    }

    /**
     * Bulk Actions
     */
    
    // เลือกทั้งหมด/ยกเลิกเลือกทั้งหมด
    $(document).on('change', '.wp-list-table thead .check-column input[type="checkbox"]', function() {
        const isChecked = $(this).prop('checked');
        $('.wp-list-table tbody .check-column input[type="checkbox"]').prop('checked', isChecked);
    });

    // การกระทำจำนวนมาก
    $(document).on('click', '.bulkactions .button.action', function(e) {
        e.preventDefault();
        
        const action = $(this).siblings('select').val();
        const checkedItems = $('.wp-list-table tbody .check-column input[type="checkbox"]:checked');
        
        if (action === '-1') {
            showNotice('กรุณาเลือกการดำเนินการ', 'warning');
            return;
        }
        
        if (checkedItems.length === 0) {
            showNotice('กรุณาเลือกรายการที่ต้องการดำเนินการ', 'warning');
            return;
        }
        
        const itemIds = checkedItems.map(function() {
            return $(this).val();
        }).get();
        
        const actionText = $(this).siblings('select option:selected').text();
        
        confirmAction(`คุณต้องการ${actionText}รายการที่เลือก ${itemIds.length} รายการ หรือไม่?`, () => {
            performBulkAction(action, itemIds);
        });
    });

    // ดำเนินการกระทำจำนวนมาก
    function performBulkAction(action, itemIds) {
        showLoading('body');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'psu_bulk_action',
                bulk_action: action,
                item_ids: itemIds,
                nonce: nonce
            },
            success: function(response) {
                hideLoading('body');
                if (response.success) {
                    showNotice(response.data.message || 'ดำเนินการสำเร็จ');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotice(response.data.message || 'เกิดข้อผิดพลาด', 'error');
                }
            },
            error: function() {
                hideLoading('body');
                showNotice('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
            }
        });
    }

    /**
     * เริ่มต้นระบบ
     */
    
    // โหลดแท็บที่บันทึกไว้เมื่อมีการตั้งค่า
    if ($('.psu-settings-tabs').length) {
        loadSavedTab();
    }

    // เริ่มต้นการทำงานของฟิลด์ต่างๆ
    if ($('#status').length) {
        $('#status').trigger('change');
    }

    // ป้องกันการส่งฟอร์มซ้ำ
    $('form').on('submit', function() {
        const submitButton = $(this).find('input[type="submit"], button[type="submit"]');
        submitButton.prop('disabled', true);
        
        setTimeout(() => {
            submitButton.prop('disabled', false);
        }, 3000);
    });

    // เพิ่ม tooltip สำหรับปุ่มต่างๆ
    $('[title]').each(function() {
        $(this).tooltip();
    });

    console.log('PSU Simple Booking Admin JS loaded successfully');
});