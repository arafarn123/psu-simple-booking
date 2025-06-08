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