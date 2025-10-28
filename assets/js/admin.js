/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 27 ตุลาคม 2568 21:00 น.
JavaScript สำหรับระบบหลังบ้าน (Admin Panel)
Edit by 27 ตุลาคม 2568 22:30 น. (เพิ่ม Handler ปุ่ม Delete, Reset Pass)
Edit by 27 ตุลาคม 2568 22:50 น. (ย้าย Logic Admin Profile มาไว้ที่นี่)
Edit by 27 ตุลาคม 2568 23:00 น. (Final Admin JS - เพิ่ม Reset Email Modal)
*/

$(document).ready(function() {
    
    // ===========================================
    // 1. Logic หน้า Courses (courses.php, course_edit.php)
    // ===========================================
    $('body').on('click', '.toggle-cert-btn', function() {
        const $button = $(this);
        const courseId = $button.data('course-id');
        const currentState = $button.data('current-state'); // 0 หรือ 1
        
        if (!courseId) return;

        const newStateText = (currentState == 1) ? 'ปิด' : 'เปิด';
        
        Swal.fire({
            title: `ยืนยันการ${newStateText}เกียรติบัตร?`,
            text: `คุณต้องการ${newStateText}การพิมพ์เกียรติบัตรสำหรับคอร์สนี้ใช่หรือไม่?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: `ใช่, ${newStateText}เลย`,
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading('กำลังอัปเดตสถานะ...');
                
                $.ajax({
                    url: 'api_admin.php?action=toggle_cert',
                    type: 'POST',
                    data: { 
                        course_id: courseId
                        // (ควรส่ง CSRF Token ที่นี่ด้วย)
                    },
                    dataType: 'json',
                    success: function(response) {
                        hideLoading();
                        if (response.status === 'success') {
                            showAlert('success', 'อัปเดตสำเร็จ!', response.message);
                            // อัปเดต UI ของปุ่ม โดยไม่ต้อง Reload หน้า
                            if (response.new_state == 1) {
                                $button.removeClass('bg-gray-500 hover:bg-gray-600').addClass('bg-green-500 hover:bg-green-600');
                                $button.html('<i class="fas fa-check-circle mr-1"></i> เปิดอยู่');
                                $button.data('current-state', 1);
                                $('#cert-status-' + courseId).html('<span class="text-green-600 font-semibold">เปิด</span>');
                            } else {
                                $button.removeClass('bg-green-500 hover:bg-green-600').addClass('bg-gray-500 hover:bg-gray-600');
                                $button.html('<i class="fas fa-times-circle mr-1"></i> ปิดอยู่');
                                $button.data('current-state', 0);
                                $('#cert-status-' + courseId).html('<span class="text-red-600 font-semibold">ปิด</span>');
                            }
                        } else {
                            showAlert('error', 'ผิดพลาด!', response.message);
                        }
                    },
                    error: function(jqXHR) {
                        hideLoading();
                        const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้";
                        showAlert('error', 'เกิดข้อผิดพลาด', errorMsg);
                    }
                });
            }
        });
    });

    // (เพิ่ม) Logic สำหรับ Form Add/Edit Course (course_edit.php)
    $("#course-edit-form").validate({
        rules: {
            title: { required: true },
            short_code: { required: true },
            academic_year: { required: true, digits: true, minlength: 4, maxlength: 4 },
            status: { required: true },
            // (เพิ่ม) ตรวจสอบ Date Format (ถ้าต้องการ)
            enroll_start: { dateISO: true },
            enroll_end: { dateISO: true },
            learn_start: { dateISO: true },
            learn_end: { dateISO: true },
            exam_start: { dateISO: true },
            exam_end: { dateISO: true },
            cert_start: { dateISO: true },
            cert_end: { dateISO: true }
        },
        messages: {
            title: "กรุณากรอกชื่อหลักสูตร",
            short_code: "กรุณากรอกรหัสย่อ",
            academic_year: "กรุณากรอกปี พ.ศ. 4 หลัก",
            status: "กรุณาเลือกสถานะ"
        },
        submitHandler: function(form) {
            showLoading("กำลังบันทึกข้อมูลหลักสูตร...");
            
            const action = $(form).find("input[name='course_id']").val() ? 'update_course' : 'add_course';
            
            $.ajax({
                type: "POST",
                url: "api_admin.php?action=" + action,
                data: $(form).serialize(),
                dataType: "json",
                success: function(response) {
                    hideLoading();
                    if (response.status === "success") {
                        Swal.fire({
                            icon: 'success',
                            title: 'บันทึกสำเร็จ!',
                            text: response.message,
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.href = "courses.php"; // กลับไปหน้ารายการ
                        });
                    } else {
                        showAlert("error", "ผิดพลาด!", response.message);
                    }
                },
                error: function(jqXHR) {
                    hideLoading();
                    const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้";
                    showAlert("error", "เกิดข้อผิดพลาด", errorMsg);
                }
            });
            return false;
        }
    });

    // ===========================================
    // 2. Logic หน้า Users (users.php, user_add.php, user_edit.php)
    // ===========================================

    // --- 2.1 จัดการปุ่มลบผู้ใช้งาน ---
    $('body').on('click', '.delete-user-btn', function() {
        const $button = $(this);
        const userId = $button.data('user-id');
        const userName = $button.data('user-name');
        
        if (!userId) return;

        Swal.fire({
            title: `ยืนยันการลบผู้ใช้งาน?`,
            text: `คุณต้องการลบ "${userName}" (ID: ${userId}) ออกจากระบบใช่หรือไม่? การกระทำนี้ไม่สามารถย้อนกลับได้!`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoading('กำลังลบผู้ใช้งาน...');
                
                $.ajax({
                    url: 'api_admin.php?action=delete_user',
                    type: 'POST',
                    data: { user_id: userId },
                    dataType: 'json',
                    success: function(response) {
                        hideLoading();
                        if (response.status === 'success') {
                            showAlert('success', 'ลบสำเร็จ!', response.message);
                            // ลบแถวออกจากตาราง
                            $button.closest('tr').fadeOut(500, function() { $(this).remove(); });
                        } else {
                            showAlert('error', 'ผิดพลาด!', response.message);
                        }
                    },
                    error: function(jqXHR) {
                        hideLoading();
                        const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้";
                        showAlert('error', 'เกิดข้อผิดพลาด', errorMsg);
                    }
                });
            }
        });
    });

    // --- 2.2 จัดการปุ่ม Reset รหัสผ่าน ---
    $('body').on('click', '.reset-pass-btn', function() {
        const $button = $(this);
        const userId = $button.data('user-id');
        const userEmail = $button.data('user-email');
        
        if (!userId) return;

        // (เพิ่ม) Modal ถามตัวเลือก
        Swal.fire({
            title: `Reset รหัสผ่านสำหรับ ${userEmail}`,
            text: 'เลือกวิธีการ Reset รหัสผ่าน:',
            icon: 'question',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: '1. ส่งอีเมล Reset (แนะนำ)',
            denyButtonText: '2. กำหนดรหัสผ่านใหม่ (Manual)',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#3085d6',
            denyButtonColor: '#f59e0b' // สีเหลือง
        }).then((result) => {
            
            // --- 2.2.1 กรณีส่ง Email (เรียก API) ---
            if (result.isConfirmed) {
                showLoading('กำลังส่งอีเมล Reset...');
                $.ajax({
                    url: 'api_admin.php?action=reset_pass_email',
                    type: 'POST',
                    data: { user_id: userId, email: userEmail },
                    dataType: 'json',
                    success: function(response) {
                        hideLoading();
                        if (response.status === 'success') {
                            showAlert('success', 'ส่งอีเมลสำเร็จ!', response.message);
                        } else {
                            showAlert('error', 'ผิดพลาด!', response.message);
                        }
                    },
                    error: function(jqXHR) {
                        hideLoading();
                        const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้";
                        showAlert('error', 'เกิดข้อผิดพลาด', errorMsg);
                    }
                });
            
            // --- 2.2.2 กรณีกำหนดเอง (Manual) ---
            } else if (result.isDenied) {
                Swal.fire({
                    title: `กำหนดรหัสผ่านใหม่สำหรับ ${userEmail}`,
                    text: 'กรุณาป้อนรหัสผ่านใหม่ (อย่างน้อย 6 ตัวอักษร):',
                    input: 'text',
                    inputAttributes: {
                        minlength: 6,
                        autocapitalize: 'off',
                        autocorrect: 'off'
                    },
                    showCancelButton: true,
                    confirmButtonText: 'ยืนยัน',
                    cancelButtonText: 'ยกเลิก',
                    showLoaderOnConfirm: true,
                    preConfirm: (newPassword) => {
                        if (!newPassword || newPassword.length < 6) {
                            Swal.showValidationMessage('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
                            return false;
                        }
                        
                        return $.ajax({
                            url: 'api_admin.php?action=reset_pass_manual',
                            type: 'POST',
                            data: { 
                                user_id: userId,
                                new_password: newPassword
                            },
                            dataType: 'json'
                        })
                        .catch(error => {
                            const errorMsg = error.responseJSON ? error.responseJSON.message : "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้";
                            Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${errorMsg}`);
                        });
                    },
                    allowOutsideClick: () => !Swal.isLoading()
                }).then((result) => {
                    if (result.isConfirmed && result.value && result.value.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Reset รหัสผ่านสำเร็จ!',
                            text: result.value.message
                        });
                    }
                });
            }
        });
    });
    
    // --- 2.3 (เพิ่ม) Logic หน้า Add User (user_add.php) ---
    $("#user-add-form").validate({
        rules: {
            email: { required: true, email: true },
            password: { required: true, minlength: 6 },
            firstname: { required: true },
            lastname: { required: true }
        },
        messages: {
            email: "กรุณากรอกอีเมลให้ถูกต้อง",
            password: "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร"
        },
        submitHandler: function(form) {
            showLoading("กำลังสร้างผู้ใช้งาน...");
            
            $.ajax({
                type: "POST",
                url: "api_admin.php?action=add_user",
                data: $(form).serialize(),
                dataType: "json",
                success: function(response) {
                    hideLoading();
                    if (response.status === "success") {
                        Swal.fire({
                            icon: 'success',
                            title: 'สร้างสำเร็จ!',
                            text: response.message,
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.href = "users.php"; 
                        });
                    } else {
                        showAlert("error", "ผิดพลาด!", response.message);
                    }
                },
                error: function(jqXHR) {
                    hideLoading();
                    const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้";
                    showAlert("error", "เกิดข้อผิดพลาด", errorMsg);
                }
            });
            return false;
        }
    });
    
    // --- 2.4 (เพิ่ม) Logic หน้า Edit User (user_edit.php) ---
    $("#user-edit-form").validate({
         rules: {
            firstname: { required: true },
            lastname: { required: true }
        },
        messages: {
            firstname: "กรุณากรอกชื่อจริง",
            lastname: "กรุณากรอกนามสกุล"
        },
        submitHandler: function(form) {
            showLoading("กำลังบันทึกข้อมูล...");

            $.ajax({
                type: "POST",
                url: "api_admin.php?action=update_user",
                data: $(form).serialize(),
                dataType: "json",
                success: function(response) {
                    hideLoading();
                    if (response.status === "success") {
                        Swal.fire({
                            icon: 'success',
                            title: 'บันทึกสำเร็จ!',
                            text: response.message,
                            timer: 2000,
                            timerProgressBar: true
                        }).then(() => {
                            window.location.href = "users.php"; 
                        });
                    } else {
                        showAlert("error", "ผิดพลาด!", response.message);
                    }
                },
                error: function(jqXHR) {
                    hideLoading();
                    const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้";
                    showAlert("error", "เกิดข้อผิดพลาด", errorMsg);
                }
            });
            return false;
        }
    });

    // ===========================================
    // 3. Logic หน้า Profile (admin/profile.php)
    // ===========================================
   
   let $croppieContainer = $('#croppie-container');
   let $croppieModal = $('#croppie-modal');
   let $uploadInput = $('#upload-image');
   let $uploadResultBtn = $('#upload-result-btn');
   let $croppieCancelBtn = $('#croppie-cancel-btn');
   let croppieInstance = null;

   // 3.1 ตั้งค่า Croppie เริ่มต้น
   function initializeCroppie(aspectRatio) {
        // (ตรวจสอบว่า $croppieContainer มีในหน้านี้หรือไม่)
        if ($croppieContainer.length === 0) return; 
        
      if (croppieInstance) {
         croppieInstance.destroy();
      }
      
      let width = 300, height = 300;
      if (aspectRatio === '16:9') { width = 400; height = 225; }
      else if (aspectRatio === '4:3') { width = 400; height = 300; }
      else if (aspectRatio === 'free') { width = 300; height = 300; }

      let boundary = { width: Math.max(width, 300), height: Math.max(height, 300) };
      let viewport = { width: width, height: height, type: 'square' };

      if(aspectRatio === 'free') {
          viewport = { width: 250, height: 250, type: 'square' };
      } else if (aspectRatio !== '1:1') {
         viewport.type = 'canvas';
      }

      croppieInstance = new Croppie($croppieContainer[0], {
         viewport: viewport,
         boundary: boundary,
         enableExif: true,
         enforceBoundary: (aspectRatio !== 'free')
      });
   }

   // 3.2 เมื่อผู้ใช้เลือกไฟล์ (Profile Pic)
   $uploadInput.on('change', function() {
      if (this.files && this.files[0]) {
         let reader = new FileReader();
         reader.onload = function(e) {
            initializeCroppie('1:1');
            croppieInstance.bind({ url: e.target.result });
            $croppieModal.removeClass('hidden');
         }
         reader.readAsDataURL(this.files[0]);
      }
   });

   // 3.3 ปุ่มเปลี่ยนอัตราส่วน (Profile Pic)
   $('.croppie-aspect-btn').on('click', function() {
        if (!croppieInstance) return;
      let ratio = $(this).data('ratio');
      let currentUrl = croppieInstance.element.querySelector('.cr-image').src;
      initializeCroppie(ratio);
      croppieInstance.bind({ url: currentUrl });
   });

   // 3.4 ปุ่มยกเลิก (Profile Pic)
   $croppieCancelBtn.on('click', function() {
      $croppieModal.addClass('hidden');
      if (croppieInstance) {
         croppieInstance.destroy();
         croppieInstance = null;
      }
      $uploadInput.val('');
   });

   // 3.5 ปุ่มยืนยันและอัปโหลด (Profile Pic)
   $uploadResultBtn.on('click', function() {
        if (!croppieInstance) return;
      showLoading('กำลังอัปโหลดรูปภาพ...');

      croppieInstance.result({
         type: 'canvas', size: 'viewport', format: 'png'
      }).then(function(base64Image) {
         
         // ส่งไปที่ api_admin.php
         $.ajax({
            url: 'api_admin.php?action=upload_admin_picture',
            type: 'POST',
            data: { image_base64: base64Image },
            dataType: 'json',
            success: function(response) {
               hideLoading();
               if (response.status === 'success') {
                  // อัปเดตรูปหน้าเว็บทันที (ใช้ URL ที่ส่งกลับมา ถ้ามี)
                        if (response.new_image_url) {
                      $('#current-profile-pic').attr('src', response.new_image_url + '?' + new Date().getTime()); // เพิ่ม timestamp กัน Cache
                        } else {
                            $('#current-profile-pic').attr('src', base64Image); 
                        }
                  showAlert('success', 'สำเร็จ!', response.message);
                  $croppieCancelBtn.click(); // ปิด Modal
               } else {
                  showAlert('error', 'ผิดพลาด!', response.message);
               }
            },
            error: function(jqXHR) {
               hideLoading();
                    const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้";
               showAlert('error', 'เกิดข้อผิดพลาด', errorMsg);
            }
         });
      });
   });

   // 3.6 จัดการการบันทึกข้อมูล (Text) (admin/profile.php)
    // (เพิ่ม) jQuery Validation
    $("#profile-form").validate({
        rules: {
            firstname: { required: true },
            lastname: { required: true }
        },
        messages: {
            firstname: "กรุณากรอกชื่อจริง",
            lastname: "กรุณากรอกนามสกุล"
        },
        submitHandler: function(form) {
            showLoading('กำลังบันทึกข้อมูล...');
            
            $.ajax({
                url: 'api_admin.php?action=update_admin_text',
                type: 'POST',
                data: $(form).serialize(),
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    if (response.status === 'success') {
                        showAlert('success', 'สำเร็จ!', response.message);
                        // (อัปเดตชื่อใน Header)
                        // $('.navbar-username').text(response.new_firstname); 
                    } else {
                        showAlert('error', 'ผิดพลาด!', response.message);
                    }
                },
                error: function(jqXHR) {
                    hideLoading();
                    const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้";
                    showAlert('error', 'เกิดข้อผิดพลาด', errorMsg);
                }
            });
            return false;
        }
    });

    
    // 3.7 (เพิ่ม) จัดการการเปลี่ยนรหัสผ่าน (admin/profile.php)
    $("#password-change-form").validate({
        rules: {
            old_password: { required: true },
            new_password: { required: true, minlength: 6 },
            confirm_new_password: { required: true, minlength: 6, equalTo: "#new_password" }
        },
        messages: {
            old_password: "กรุณากรอกรหัสผ่านเดิม",
            new_password: "รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร",
            confirm_new_password: "รหัสผ่านใหม่ไม่ตรงกัน"
        },
        submitHandler: function(form) {
            showLoading("กำลังเปลี่ยนรหัสผ่าน...");
            
            $.ajax({
                type: "POST",
                url: "api_admin.php?action=change_admin_password",
                data: $(form).serialize(),
                dataType: "json",
                success: function(response) {
                    hideLoading();
                    if (response.status === "success") {
                        Swal.fire({
                            icon: "success",
                            title: "เปลี่ยนรหัสผ่านสำเร็จ!",
                            text: response.message
                        });
                        form.reset(); // ล้างฟอร์ม
                    } else {
                        showAlert("error", "ผิดพลาด!", response.message);
                    }
                },
                error: function(jqXHR) {
                    hideLoading();
                    const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : "ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้";
                    showAlert("error", "เกิดข้อผิดพลาด", errorMsg);
                }
            });
            return false;
        }
    });

});