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
                // รีเซ็ต image preview
                $('#image-preview').empty();
            }
        } else {
            form.show();
            list.hide();
        }
    };

    // ปิด form submission handler ชั่วคราวเพื่อแก้ปัญหา
    // $(document).on('submit', '.psu-service-form', function(e) {
    //     console.log('🚀 Service form submitted!');
    //     console.log('Form data:', $(this).serialize());
        
    //     // แสดง loading บนปุ่ม submit
    //     const submitBtn = $(this).find('button[type="submit"]');
    //     submitBtn.prop('disabled', true).text('กำลังบันทึก...');
        
    //     // อนุญาตให้ form submit ไปยัง PHP
    //     return true;
    // });
    
    // อนุญาตให้ form submit ปกติ
    console.log('📝 Admin.js loaded - forms will work normally');

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

    // Tab switching functionality
    $('.psu-nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).data('tab');
        
        // Remove active class from all tabs and contents
        $('.psu-nav-tab').removeClass('nav-tab-active');
        $('.psu-tab-content').removeClass('active');
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Show target content
        $('#' + target).addClass('active');
        
        // Update URL hash
        window.location.hash = target;
    });
    
    // Initialize tabs from URL hash
    if (window.location.hash) {
        var activeTab = window.location.hash.substring(1);
        $('.psu-nav-tab[data-tab="' + activeTab + '"]').trigger('click');
    } else {
        $('.psu-nav-tab:first').trigger('click');
    }
    
    // Booking status quick update
    $('.psu-quick-status').on('click', function(e) {
        e.preventDefault();
        
        var bookingId = $(this).data('booking-id');
        var newStatus = $(this).data('status');
        var row = $(this).closest('tr');
        
        if (confirm('คุณต้องการเปลี่ยนสถานะการจองนี้หรือไม่?')) {
            $.ajax({
                url: psu_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'psu_update_booking_status',
                    booking_id: bookingId,
                    status: newStatus,
                    nonce: psu_admin_ajax.nonce
                },
                beforeSend: function() {
                    row.addClass('psu-updating');
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                },
                complete: function() {
                    row.removeClass('psu-updating');
                }
            });
        }
    });
    
    // Bulk actions
    $('#doaction').on('click', function(e) {
        var action = $('#bulk-action-selector-top').val();
        var selected = $('.psu-checkbox:checked');
        
        if (action === '-1') {
            alert('กรุณาเลือกการดำเนินการ');
            e.preventDefault();
            return;
        }
        
        if (selected.length === 0) {
            alert('กรุณาเลือกรายการที่ต้องการดำเนินการ');
            e.preventDefault();
            return;
        }
        
        if (!confirm('คุณต้องการดำเนินการกับรายการที่เลือกหรือไม่?')) {
            e.preventDefault();
            return;
        }
    });
    
    // Select all checkboxes
    $('#cb-select-all-1').on('change', function() {
        $('.psu-checkbox').prop('checked', $(this).is(':checked'));
    });
    
    // Modal functionality
    $('.psu-modal-trigger').on('click', function(e) {
        e.preventDefault();
        var modalId = $(this).data('modal');
        $('#' + modalId).fadeIn();
    });
    
    $('.psu-modal-close, .psu-modal-overlay').on('click', function() {
        $('.psu-modal').fadeOut();
    });
    
    // Form validation
    $('.psu-form').on('submit', function(e) {
        var form = $(this);
        var isValid = true;
        
        // Check required fields
        form.find('[required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('error');
                isValid = false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        if (!isValid) {
            alert('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน');
            e.preventDefault();
        }
    });
    
    // Real-time search/filter
    $('#service-search').on('keyup', function() {
        var searchTerm = $(this).val().toLowerCase();
        
        $('.psu-service-card').each(function() {
            var serviceName = $(this).find('h3').text().toLowerCase();
            var serviceCategory = $(this).find('.psu-service-category').text().toLowerCase();
            
            if (serviceName.includes(searchTerm) || serviceCategory.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
    
    // Date range picker initialization
    if ($.fn.datepicker) {
        $('.psu-datepicker').datepicker({
            dateFormat: 'dd/mm/yy',
            changeMonth: true,
            changeYear: true
        });
    }
    
    // Chart initialization (if Chart.js is available)
    if (typeof Chart !== 'undefined') {
        initializeCharts();
    }
    
    // Copy to clipboard functionality
    $('.psu-copy-btn').on('click', function() {
        var text = $(this).data('copy');
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                showNotification('คัดลอกแล้ว!', 'success');
            });
        } else {
            // Fallback for older browsers
            var textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showNotification('คัดลอกแล้ว!', 'success');
        }
    });
    
    // Auto-save functionality
    $('.psu-auto-save').on('input', debounce(function() {
        var field = $(this);
        var data = {
            action: 'psu_auto_save',
            key: field.data('key'),
            value: field.val(),
            nonce: psu_admin_ajax.nonce
        };
        
        $.post(psu_admin_ajax.ajax_url, data, function(response) {
            if (response.success) {
                field.addClass('saved');
                setTimeout(function() {
                    field.removeClass('saved');
                }, 1000);
            }
        });
    }, 1000));
    
    // Loading states
    $('.button[type="submit"]').on('click', function() {
        var btn = $(this);
        var originalText = btn.text();
        
        btn.text('กำลังประมวลผล...').prop('disabled', true);
        
        setTimeout(function() {
            btn.text(originalText).prop('disabled', false);
        }, 3000);
    });
    
    // Initialize sortable tables
    if ($.fn.sortable) {
        $('.psu-sortable tbody').sortable({
            handle: '.psu-sort-handle',
            placeholder: 'psu-sort-placeholder',
            update: function(event, ui) {
                var order = $(this).sortable('toArray', {attribute: 'data-id'});
                // Save new order via AJAX
                $.post(psu_admin_ajax.ajax_url, {
                    action: 'psu_update_order',
                    order: order,
                    nonce: psu_admin_ajax.nonce
                });
            }
        });
    }
});

// Service Form Toggle Function
function toggleServiceForm() {
    var form = document.getElementById('service-form');
    var list = document.getElementById('services-list');
    
    if (form.style.display === 'none' || !form.style.display) {
        form.style.display = 'block';
        list.style.display = 'none';
        window.scrollTo(0, 0);
    } else {
        form.style.display = 'none';
        list.style.display = 'block';
        
        // Reset form if it's not editing
        if (!document.querySelector('input[name="service_id"]')) {
            document.querySelector('.psu-service-form').reset();
            
            // Clear image preview
            const preview = document.getElementById('image-preview');
            if (preview) {
                preview.innerHTML = '';
            }
        }
    }
}

// Custom Form Fields Management
function addCustomField() {
    var container = document.getElementById('custom-fields-container');
    var fieldCount = container.children.length;
    
    var fieldHtml = `
        <div class="psu-custom-field" data-field-id="${fieldCount}">
            <div class="psu-field-header">
                <input type="text" name="custom_fields[${fieldCount}][label]" placeholder="ป้ายกำกับฟิลด์" class="psu-input" required>
                <select name="custom_fields[${fieldCount}][type]" class="psu-select" onchange="toggleFieldOptions(this)">
                    <option value="text">ข้อความ</option>
                    <option value="textarea">ข้อความแบบยาว</option>
                    <option value="email">อีเมล</option>
                    <option value="number">ตัวเลข</option>
                    <option value="tel">เบอร์โทรศัพท์</option>
                    <option value="select">เลือกจากรายการ</option>
                    <option value="radio">เลือกหนึ่งตัวเลือก</option>
                    <option value="checkbox">เลือกได้หลายตัวเลือก</option>
                    <option value="date">วันที่</option>
                    <option value="time">เวลา</option>
                    <option value="file">อัปโหลดไฟล์</option>
                </select>
                <button type="button" onclick="removeCustomField(this)" class="button button-small psu-remove-field">ลบ</button>
            </div>
            
            <div class="psu-field-options">
                <div class="psu-field-settings">
                    <label>
                        <input type="checkbox" name="custom_fields[${fieldCount}][required]" value="1">
                        จำเป็นต้องกรอก
                    </label>
                    <input type="text" name="custom_fields[${fieldCount}][placeholder]" placeholder="ข้อความตัวอย่าง" class="psu-input">
                    <input type="text" name="custom_fields[${fieldCount}][description]" placeholder="คำอธิบายเพิ่มเติม" class="psu-input">
                </div>
                
                <div class="psu-field-options-list" style="display: none;">
                    <label>ตัวเลือก (แยกด้วยบรรทัดใหม่):</label>
                    <textarea name="custom_fields[${fieldCount}][options]" rows="3" class="psu-textarea" placeholder="ตัวเลือก 1&#10;ตัวเลือก 2&#10;ตัวเลือก 3"></textarea>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', fieldHtml);
}

function removeCustomField(button) {
    if (confirm('คุณต้องการลบฟิลด์นี้หรือไม่?')) {
        button.closest('.psu-custom-field').remove();
        reorderCustomFields();
    }
}

function toggleFieldOptions(select) {
    var fieldContainer = select.closest('.psu-custom-field');
    var optionsList = fieldContainer.querySelector('.psu-field-options-list');
    
    if (['select', 'radio', 'checkbox'].includes(select.value)) {
        optionsList.style.display = 'block';
    } else {
        optionsList.style.display = 'none';
    }
}

function reorderCustomFields() {
    var fields = document.querySelectorAll('.psu-custom-field');
    fields.forEach(function(field, index) {
        field.setAttribute('data-field-id', index);
        
        // Update input names
        var inputs = field.querySelectorAll('input, select, textarea');
        inputs.forEach(function(input) {
            var name = input.getAttribute('name');
            if (name && name.includes('custom_fields[')) {
                var newName = name.replace(/custom_fields\[\d+\]/, 'custom_fields[' + index + ']');
                input.setAttribute('name', newName);
            }
        });
    });
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

// ทำให้ฟังก์ชันเป็น global
window.openMediaLibrary = openMediaLibrary;

// Chart initialization function
function initializeCharts() {
    // Monthly bookings chart
    var monthlyCtx = document.getElementById('monthlyChart');
    if (monthlyCtx && typeof monthlyData !== 'undefined') {
        new Chart(monthlyCtx, {
            type: 'line',
            data: monthlyData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'การจองรายเดือน'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    // Service distribution chart
    var serviceCtx = document.getElementById('serviceChart');
    if (serviceCtx && typeof serviceData !== 'undefined') {
        new Chart(serviceCtx, {
            type: 'doughnut',
            data: serviceData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'การใช้งานบริการ'
                    }
                }
            }
        });
    }
}

// Utility functions
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

function showNotification(message, type) {
    type = type || 'info';
    
    var notification = document.createElement('div');
    notification.className = 'psu-notification psu-notification-' + type;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(function() {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(function() {
        notification.classList.remove('show');
        setTimeout(function() {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Export functions
function exportData(format) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    var formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'export_format';
    formatInput.value = format;
    
    var actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'export_data';
    
    form.appendChild(formatInput);
    form.appendChild(actionInput);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}