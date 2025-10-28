/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 24 ตุลาคม 2568 23:04 น. จำนวน 100 บรรทัด
โค้ด JS สำหรับจัดการ Croppie.js (แปลงเป็น Base64 ส่งให้ PHP)
(โค้ดตัวอย่างข้อ 7/12)
*/

$(document).ready(function() {
	
	let $croppieContainer = $('#croppie-container');
	let $croppieModal = $('#croppie-modal');
	let $uploadInput = $('#upload-image');
	let $uploadResultBtn = $('#upload-result-btn');
	let $croppieCancelBtn = $('#croppie-cancel-btn');
	let croppieInstance = null;

	// 1. ตั้งค่า Croppie เริ่มต้น
	function initializeCroppie(aspectRatio) {
		if (croppieInstance) {
			croppieInstance.destroy();
		}
		
		let width = 300, height = 300;
		if (aspectRatio === '16:9') { width = 400; height = 225; }
		else if (aspectRatio === '4:3') { width = 400; height = 300; }
		else if (aspectRatio === 'free') { width = 300; height = 300; } // อิสระ จะใช้ boundary

		let boundary = { width: Math.max(width, 300), height: Math.max(height, 300) };
		let viewport = { width: width, height: height, type: 'square' };

		if(aspectRatio === 'free') {
			 viewport = { width: 250, height: 250, type: 'square' }; // อิสระ ให้ viewport เล็กกว่า boundary
		} else if (aspectRatio !== '1:1') {
			viewport.type = 'canvas'; // ใช้ canvas สำหรับอัตราส่วนอื่น
		}

		croppieInstance = new Croppie($croppieContainer[0], {
			viewport: viewport,
			boundary: boundary,
			enableExif: true,
			enforceBoundary: (aspectRatio !== 'free') // ล็อกขอบเขตถ้าไม่ใช่อิสระ
		});
	}

	// 2. เมื่อผู้ใช้เลือกไฟล์
	$uploadInput.on('change', function() {
		if (this.files && this.files[0]) {
			let reader = new FileReader();
			reader.onload = function(e) {
				// เริ่มต้นที่ 1:1
				initializeCroppie('1:1');
				
				croppieInstance.bind({
					url: e.target.result
				});
				$croppieModal.removeClass('hidden');
			}
			reader.readAsDataURL(this.files[0]);
		}
	});

	// 3. ปุ่มเปลี่ยนอัตราส่วน (ตามข้อกำหนด)
	$('.croppie-aspect-btn').on('click', function() {
		let ratio = $(this).data('ratio');
		let currentUrl = croppieInstance.element.querySelector('.cr-image').src;
		
		initializeCroppie(ratio);
		
		croppieInstance.bind({ url: currentUrl });
	});

	// 4. ปุ่มยกเลิก
	$croppieCancelBtn.on('click', function() {
		$croppieModal.addClass('hidden');
		if (croppieInstance) {
			croppieInstance.destroy();
			croppieInstance = null;
		}
		$uploadInput.val(''); // รีเซ็ต input file
	});

	// 5. ปุ่มยืนยันและอัปโหลด (หัวใจสำคัญ: Base64)
	$uploadResultBtn.on('click', function() {
		showLoading('กำลังอัปโหลดรูปภาพ...');

		croppieInstance.result({
			type: 'canvas', // เอาต์พุตเป็น Base64
			size: 'viewport', // ขนาดตามที่ครอป
			format: 'png'     // บีบอัดเป็น PNG (หรือ jpeg)
		}).then(function(base64Image) {
			
			// ส่ง Base64 String นี้ไปยัง PHP
			$.ajax({
				url: 'api/profile.php?action=upload_base64_croppie',
				type: 'POST',
				data: {
					image_base64: base64Image
					// (ควรส่ง CSRF Token ที่นี่ด้วย)
				},
				dataType: 'json',
				success: function(response) {
					hideLoading();
					if (response.status === 'success') {
						// อัปเดตรูปหน้าเว็บทันที
						$('#current-profile-pic').attr('src', base64Image); 
						showAlert('success', 'สำเร็จ!', response.message);
						$croppieCancelBtn.click(); // ปิด Modal
					} else {
						showAlert('error', 'ผิดพลาด!', response.message);
					}
				},
				error: function() {
					hideLoading();
					showAlert('error', 'เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์');
				}
			});
		});
	});

	// 6. จัดการการบันทึกข้อมูล (Text)
	$('#profile-form').on('submit', function(e) {
		e.preventDefault();
		showLoading('กำลังบันทึกข้อมูล...');
		
		$.ajax({
			url: 'api/profile.php?action=update_text',
			type: 'POST',
			data: $(this).serialize(),
			dataType: 'json',
			success: function(response) {
				hideLoading();
				if (response.status === 'success') {
					showAlert('success', 'สำเร็จ!', response.message);
				} else {
					showAlert('error', 'ผิดพลาด!', response.message);
				}
			},
			error: function() {
				hideLoading();
				showAlert('error', 'เกิดข้อผิดพลาด');
			}
		});
	});

});