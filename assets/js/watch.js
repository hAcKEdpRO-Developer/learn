/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 25 ตุลาคม 2568 02:32 น. จำนวน 320+ บรรทัด
(FIX: Timing Issue - รอ Server บันทึกก่อน Redirect - ฉบับเต็ม)
*/

// (ตัวแปร PHP_DATA และ PLAYER_VARS ถูกส่งมาจาก watch.php)
const LESSON_ID = PHP_DATA.lesson_id;
const VIDEO_ID = PHP_DATA.video_id;
const START_TIME = PHP_DATA.start_time;
const MIN_PERCENT = PHP_DATA.min_watch_percent;
const COURSE_ID = PHP_DATA.course_id;
const IS_REVIEW_MODE = PHP_DATA.is_completed; // รับสถานะ Review Mode

// --- ตัวแปรสถานะ ---
let player; // ตัวแปร Player (จะถูกกำหนดค่าใน onYouTubeIframeAPIReady)
let videoDuration = 0;
let allowUntil = START_TIME;
let lastHeartbeatTime = 0;
let mainInterval = null; // ตัวจับเวลาหลัก
let isCompleted = IS_REVIEW_MODE; // ตั้งค่าเริ่มต้นตาม Review Mode
let violationCount = 0;
let isSeeking = false; // สถานะว่ากำลัง Seek/Buffer หรือไม่
let inactivityTimer = null; // ตัวจับเวลาการไม่โต้ตอบ
const INACTIVITY_TIMEOUT = 180000; // 3 นาที
let isPausedForInactivity = false; // Flag ว่าหยุดเพราะไม่โต้ตอบหรือไม่
let isPausedByViolation = false; // Flag ว่าหยุดเพราะโกงหรือไม่
let isAlertShowing = false;      // Flag ว่า Alert กำลังแสดงหรือไม่

// --- ตัวแปร UI ---
const statusDisplay = $('#player-status');
const currentTimeDisplay = $('#current-time-display');
const allowUntilDisplay = $('#allow-until-display');
const completedDisplay = $('#completed-display');
const violationDisplay = $('#violation-count');
const progressPercentDisplay = $('#progress-percent');
const progressRemainingDisplay = $('#progress-remaining');
const progressBarInner = $('#progress-bar-inner');

// 1. ฟังก์ชันนี้จะถูกเรียกโดย YouTube API เมื่อพร้อม
function onYouTubeIframeAPIReady() {
    try {
        player = new YT.Player('youtube-player', {
            height: '100%',
            width: '100%',
            videoId: VIDEO_ID,
            playerVars: PLAYER_VARS,
            events: {
                'onReady': onPlayerReady,
                'onStateChange': onPlayerStateChange,
                // Anti-Cheat rate change จะผูกใน onPlayerReady
                'onError': onPlayerError
            }
        });
    } catch (e) {
        console.error("Error creating YouTube player:", e);
        statusDisplay.text('Error creating player.');
        if (typeof showAlert === 'function') {
            showAlert('error', 'เกิดข้อผิดพลาด', 'ไม่สามารถสร้างตัวเล่นวิดีโอได้');
        }
    }
}

// 2. เมื่อ Player พร้อม
function onPlayerReady(event) {
    statusDisplay.text('Ready.');
    try {
        videoDuration = player.getDuration();
        if (isNaN(videoDuration) || videoDuration <= 0) {
            console.warn("Video duration is invalid or zero.");
            // Consider setting a default or showing an error
        }
        allowUntil = Math.max(START_TIME, allowUntil);
        if (allowUntil === 0 && videoDuration > 0) allowUntil = 0.1; // Prevent division by zero or negative values

        // ถ้าไม่ใช่ Review Mode ถึงจะผูก Anti-Cheat Rate Change และเริ่ม Inactivity Timer
        if (!IS_REVIEW_MODE) {
            if (player.getPlaybackRate() != 1) {
                 player.setPlaybackRate(1);
                 showAlert('error', 'ห้ามปรับความเร็ว!', 'ระบบจะบังคับใช้ความเร็ว 1x');
            }
            // ผูก Event Listener การเปลี่ยนความเร็ว
            player.addEventListener('onPlaybackRateChange', onPlayerRateChange);
            // ผูก Event Listener การไม่โต้ตอบ
            document.addEventListener('mousemove', resetInactivityTimer);
            document.addEventListener('keypress', resetInactivityTimer);
            statusDisplay.text('Learning Mode (Anti-Cheat Enabled)');
        } else {
            statusDisplay.text('Review Mode (Anti-Cheat Disabled)');
        }

        player.playVideo(); // เล่นอัตโนมัติ
        updateProgressUI(); // อัปเดต UI ครั้งแรก
    } catch (e) {
        console.error("Error in onPlayerReady:", e);
        statusDisplay.text('Error during ready.');
    }
}

// 3. เมื่อสถานะ Player เปลี่ยน
function onPlayerStateChange(event) {
    try {
        let state = event.data;
        if (state == YT.PlayerState.PLAYING) {
            statusDisplay.text('PLAYING');
            // ตรวจสอบ Focus/Visibility ก่อนเริ่ม Interval
            if (!IS_REVIEW_MODE && (!document.hasFocus() || document.hidden)) {
                // ถ้าพยายามกด Play ตอนที่หน้าต่างไม่ Active หรือ Hidden
                if (!isAlertShowing) { // แสดง Alert ถ้ายังไม่มี
                    try {
                        player.pauseVideo(); // หยุดทันที
                        handleViolation('FOCUS/VISIBILITY'); // เรียกฟังก์ชันจัดการ Violation
                    } catch(e) { console.error("Error re-pausing video on play attempt:", e); }
                }
            } else {
                // ถ้าหน้าต่าง Active และ Visible ปกติ
                isPausedByViolation = false; // รีเซ็ต Flag Violation (สำคัญ)
                if (isSeeking) { isSeeking = false; }
                startMainInterval(); // เริ่ม Interval (จะเช็ก Review Mode ข้างใน)
            }
        } else if (state == YT.PlayerState.ENDED) {
            statusDisplay.text('ENDED');
            stopMainInterval();
            allowUntil = videoDuration; // บังคับให้จบ
            isCompleted = true; // ตั้งค่าว่าจบแล้ว
            updateProgressUI(); // อัปเดต UI ให้เต็ม 100%
            // จัดการตอนจบ (จะเช็ก Review Mode ข้างใน)
            handleVideoEnd();
        } else if (state == YT.PlayerState.BUFFERING) {
            statusDisplay.text('BUFFERING');
            isSeeking = true; // ตั้งค่าว่ากำลัง Seek/Buffer
            stopMainInterval(); // หยุด Interval ชั่วคราว
        } else { // PAUSED หรือสถานะอื่นๆ
            statusDisplay.text('PAUSED');
            isSeeking = false; // หยุด Seek/Buffer แล้ว
            stopMainInterval();
            // ส่ง Heartbeat เฉพาะเมื่อไม่ใช่ Pause เพราะ Violation หรือ Inactivity
            // และไม่ใช่ Review Mode
            if (!IS_REVIEW_MODE && !isPausedByViolation && !isPausedForInactivity) {
                sendHeartbeat(true); // บันทึกจุดที่หยุด
            }
        }
    } catch (e) {
        console.error("Error in onPlayerStateChange:", e);
    }
}

// 4. ตัวจับเวลาหลัก (รวม Continuous Anti-Cheat Check)
function startMainInterval() {
    if (mainInterval) return; // ป้องกันการเรียกซ้ำ

    // เริ่ม Inactivity Timer เฉพาะเมื่อไม่ใช่ Review Mode
    if (!IS_REVIEW_MODE) {
        resetInactivityTimer();
    }

    mainInterval = setInterval(function() {
        // ตรวจสอบพื้นฐาน
        if (!player || typeof player.getCurrentTime !== 'function' || videoDuration === 0 || isSeeking || isPausedForInactivity || isPausedByViolation || isAlertShowing) {
            // ไม่ทำงานถ้า Player ไม่พร้อม, กำลัง Seek, หรือหยุดเพราะ Inactive/Violation/Alert
            return;
        }

        try {
            // --- 4A. Anti-Cheat: Continuous Focus/Visibility Check ---
            // ทำงานเฉพาะ Learning Mode
            if (!IS_REVIEW_MODE) {
                // ตรวจสอบทันทีว่าหน้าต่าง Active และ Visible หรือไม่
                if (!document.hasFocus() || document.hidden) {
                    // ถ้าไม่ Active หรือ ถูกซ่อน ให้หยุดวิดีโอและแสดง Alert
                    if (typeof player.getPlayerState === 'function' && player.getPlayerState() === YT.PlayerState.PLAYING) {
                        try {
                            player.pauseVideo(); // หยุดวิดีโอ
                            handleViolation('FOCUS/VISIBILITY'); // เรียกฟังก์ชันกลาง
                        } catch (e) { console.error("Error pausing video from interval check:", e); }
                    }
                    return; // ออกจาก Interval รอบนี้ทันทีที่เจอ Violation
                }
            }

            // --- ถ้าผ่านการตรวจสอบ Focus/Visibility มาได้ ให้ทำงานต่อ ---
            let currentTime = player.getCurrentTime();

            // --- 4B. Logic การล็อก Seek (ทำงานเฉพาะเมื่อไม่ใช่ Review Mode) ---
            if (!IS_REVIEW_MODE) {
                if (currentTime > allowUntil && currentTime < (allowUntil + 1.0)) {
                    allowUntil = currentTime;
                }
                if (currentTime > (allowUntil + 1.5)) {
                    player.pauseVideo();
                    player.seekTo(allowUntil);
                    isSeeking = true;
                    handleViolation('SEEK');
                    return; // ออกจาก Interval รอบนี้
                }
            } else {
                allowUntil = currentTime; // Review Mode อัปเดตตามจริง
            }

            // --- 4C. (Heartbeat) ส่งข้อมูล (ทำงานเฉพาะเมื่อไม่ใช่ Review Mode) ---
            if (!IS_REVIEW_MODE && currentTime - lastHeartbeatTime > 5) {
                sendHeartbeat(false);
                lastHeartbeatTime = currentTime;
            }

            // --- 4D. (UI) อัปเดต % และ Progress Bar (ทำงานเสมอ) ---
            updateProgressUI();

        } catch (e) {
            console.error("Error in mainInterval:", e);
            stopMainInterval(); // หยุด Interval ถ้าเกิด Error
        }
    }, 500); // ตรวจสอบทุก 0.5 วินาที
}

function stopMainInterval() {
    clearInterval(mainInterval);
    mainInterval = null;
    // หยุด Inactivity Timer เฉพาะเมื่อไม่ใช่ Review Mode
    if (!IS_REVIEW_MODE) {
        clearTimeout(inactivityTimer);
    }
}

// 5. (FIX) (Heartbeat) ฟังก์ชันส่งข้อมูล (AJAX) - ให้คืนค่า Promise
function sendHeartbeat(isStopping = false) {
    // เพิ่มการตรวจสอบ player และ videoDuration
    if (!player || typeof player.getCurrentTime !== 'function' || videoDuration === 0) {
        // คืนค่า Promise ที่ reject ทันที ถ้าเงื่อนไขไม่ผ่าน
        return $.Deferred().reject('Player not ready or invalid duration').promise();
    }

    let timeToSend = isStopping ? player.getCurrentTime() : allowUntil;

    // ตรวจสอบ isCompleted อีกครั้งก่อนส่ง
    let watchedPercent = (allowUntil / videoDuration) * 100;
    // ปรับเงื่อนไขให้แม่นยำขึ้น สำหรับวิดีโอสั้น
    if (watchedPercent >= MIN_PERCENT || (videoDuration > 0 && Math.abs(videoDuration - allowUntil) < 1.5)) {
        isCompleted = true;
    }

    // (FIX) คืนค่า $.ajax object ซึ่งเป็น Promise
    return $.ajax({
        url: 'api/watch.php',
        type: 'POST',
        data: {
            lesson_id: LESSON_ID,
            last_watched_time: timeToSend,
            is_completed: isCompleted ? 1 : 0,
            violation_count: violationCount
        },
        dataType: 'json'
    }); // ไม่ต้องมี .then() ที่นี่
}

// 6. (Anti-Cheat) ตรวจจับการเร่งความเร็ว (ทำงานเฉพาะเมื่อไม่ใช่ Review Mode)
function onPlayerRateChange(event) {
    if (IS_REVIEW_MODE || isAlertShowing) return;
    if (!player) return;
    if (event.data != 1) {
        try {
            player.setPlaybackRate(1); // บังคับกลับ
            handleViolation('RATE_CHANGE'); // เรียกฟังก์ชันกลาง
        } catch(e) { console.error("Error handling rate change:", e); }
    }
}

// 7. ฟังก์ชันกลางสำหรับจัดการ Violation (แสดง Alert)
function handleViolation(type) {
    if (isAlertShowing || IS_REVIEW_MODE) return;
    stopMainInterval(); // หยุด Interval หลัก

    isPausedByViolation = true;
    isAlertShowing = true;
    violationCount++;
    violationDisplay.text(violationCount);

    let title = 'ตรวจพบพฤติกรรมที่ไม่เหมาะสม!';
    let text = 'ระบบได้หยุดการเรียนของคุณแล้ว กรุณากลับมาตั้งใจเรียน';

    if (type === 'FOCUS/VISIBILITY') {
        title = 'คุณ "ไม่ได้โฟกัส" ที่การเรียน!';
        text = 'กรุณากลับมาที่หน้าต่างนี้ ระบบได้หยุดการเรียนของคุณแล้ว';
        statusDisplay.text('VIOLATION (FOCUS/VISIBILITY)');
    } else if (type === 'SEEK') {
        title = 'ห้ามข้ามบทเรียน!';
        text = 'คุณพยายามข้ามไปยังส่วนที่ยังไม่ได้ดู ระบบได้ย้อนกลับให้แล้ว';
        statusDisplay.text('VIOLATION (SEEK)');
    } else if (type === 'RATE_CHANGE') {
        title = 'ห้ามปรับความเร็ว!';
        text = 'ตรวจพบการปรับความเร็ว ระบบได้บังคับกลับเป็น 1x และหยุดวิดีโอ';
        statusDisplay.text('VIOLATION (RATE CHANGE)');
    }

    // หยุดวิดีโอ
    if (player && typeof player.pauseVideo === 'function') {
        try { player.pauseVideo(); } catch(e) { console.warn("Could not pause video during violation:", e); }
    }

    Swal.fire({
        icon: 'error', title: title, text: text, allowOutsideClick: false
    }).then(() => {
        isAlertShowing = false; // รีเซ็ต Flag Alert เมื่อกด OK
        // isPausedByViolation จะถูกรีเซ็ตใน onPlayerStateChange เมื่อกด Play ใหม่
    });
}


// 8. (Anti-Cheat) ตัวจับเวลาการ "ไม่โต้ตอบ" (ทำงานเฉพาะเมื่อไม่ใช่ Review Mode)
function resetInactivityTimer() {
    if (IS_REVIEW_MODE) return;
    clearTimeout(inactivityTimer);
    if (player && typeof player.getPlayerState === 'function') {
        // เพิ่มเงื่อนไข !isAlertShowing
        if (player.getPlayerState() === YT.PlayerState.PLAYING && !isPausedForInactivity && !isPausedByViolation && !isAlertShowing) {
            inactivityTimer = setTimeout(handleInactivity, INACTIVITY_TIMEOUT);
        }
    }
}

function handleInactivity() {
    if (IS_REVIEW_MODE || isAlertShowing) return;
    if (player && typeof player.getPlayerState === 'function') {
        if (player.getPlayerState() === YT.PlayerState.PLAYING) {
            try {
                player.pauseVideo();
                isPausedForInactivity = true;
                statusDisplay.text('PAUSED (INACTIVE)');
                isAlertShowing = true; // ตั้ง Flag Alert
                Swal.fire({
                    icon: 'question', title: 'คุณยังเรียนอยู่หรือไม่?', text: 'ไม่พบการโต้ตอบ กรุณากด OK เพื่อเรียนต่อ', allowOutsideClick: false
                }).then((result) => {
                    isAlertShowing = false; // รีเซ็ต Flag Alert
                    if (result.isConfirmed) {
                        isPausedForInactivity = false;
                        if (player && typeof player.playVideo === 'function') player.playVideo();
                    }
                });
            } catch (e) { console.error("Error handling inactivity:", e); isAlertShowing = false; }
        }
    }
}
// (Listener mousemove, keypress จะถูกผูกใน onPlayerReady ถ้าไม่ใช่ Review Mode)

// 9. (FIX) ฟังก์ชันจัดการเมื่อวิดีโอจบ (รอ sendHeartbeat ก่อน)
function handleVideoEnd() {
    // ถ้าเป็น Review Mode (โค้ดเดิม)
    if (IS_REVIEW_MODE) {
         isAlertShowing = true;
         Swal.fire({ icon: 'info', title: 'จบบทเรียน (ทบทวน)', text: 'กำลังกลับไปหน้าหลักสูตร...', timer: 10000, timerProgressBar: true, showConfirmButton: true, allowOutsideClick: false })
            .then(() => { isAlertShowing = false; window.location.href = 'course.php?id=' + COURSE_ID; });
         return;
    }

    // ถ้าเป็น Learning Mode
    showLoading('กำลังบันทึกสถานะและตรวจสอบ...');

    // (FIX) เรียก sendHeartbeat(true) และรอให้เสร็จ (ใช้ .done() หรือ .then())
    sendHeartbeat(true)
        .done(function(saveResponse) {
            // เมื่อ Server บันทึกสำเร็จแล้ว (saveResponse คือ JSON จาก api/watch.php)
            // ให้ไปถามหารหัสสอบต่อ
            $.ajax({
                url: 'api/exam.php?action=get_my_code', type: 'POST', data: { course_id: COURSE_ID }, dataType: 'json',
                success: function(codeResponse) {
                    hideLoading();
                    let title = 'เรียนจบบทเรียน!'; let text = 'บันทึกความก้าวหน้าแล้ว'; let timer = 3000;
                    if (codeResponse.status === 'success' && codeResponse.exam_code) {
                        title = 'เรียนจบหลักสูตร!'; text = 'ยินดีด้วย! รหัสเข้าสอบของคุณคือ: ' + codeResponse.exam_code; timer = 10000;
                    }
                    isAlertShowing = true;
                    Swal.fire({ icon: 'success', title: title, text: text, timer: timer, timerProgressBar: true, showConfirmButton: true, allowOutsideClick: false })
                        .then(() => { isAlertShowing = false; window.location.href = 'course.php?id=' + COURSE_ID; });
                },
                error: function() { // Error ตอนถามหารหัสสอบ
                    hideLoading();
                    isAlertShowing = true;
                    Swal.fire({ icon: 'success', title: 'เรียนจบบทเรียน!', text: 'บันทึกความก้าวหน้าแล้ว (แต่ดึงรหัสสอบไม่ได้) กำลังกลับไปหน้าหลักสูตร...', timer: 5000, timerProgressBar: true, showConfirmButton: false, allowOutsideClick: false })
                        .then(() => { isAlertShowing = false; window.location.href = 'course.php?id=' + COURSE_ID; });
                }
            });
        })
        .fail(function() {
            // (FIX) ถ้า sendHeartbeat ล้มเหลว (Server บันทึกไม่สำเร็จ)
            hideLoading();
            showAlert('error', 'บันทึกข้อมูลล้มเหลว', 'ไม่สามารถบันทึกสถานะการเรียนจบได้ กรุณาลองอีกครั้ง หรือติดต่อผู้ดูแล');
            // ไม่ต้อง Redirect ให้ผู้ใช้ลองกดจบใหม่
        });
}


// 10. ฟังก์ชันอัปเดต UI % และ Progress Bar - ไม่ต้องแก้ไข
function updateProgressUI() {
    if(!player || typeof player.getCurrentTime !== 'function' || videoDuration === 0) return;
    let currentProgressTime = IS_REVIEW_MODE ? player.getCurrentTime() : allowUntil;
    let watchedPercent = (currentProgressTime / videoDuration) * 100;
    let remainingPercent = 100 - watchedPercent;

    watchedPercent = Math.max(0, Math.min(watchedPercent, 100));
    remainingPercent = Math.max(0, Math.min(remainingPercent, 100));

    progressPercentDisplay.text(watchedPercent.toFixed(1) + '%');
    progressRemainingDisplay.text(remainingPercent.toFixed(1) + '%');
    if (progressBarInner.length) { progressBarInner.css('width', watchedPercent + '%'); }

    // อัปเดต Debug UI
    if (typeof player.getCurrentTime === 'function') {
        try { currentTimeDisplay.text(player.getCurrentTime().toFixed(2)); } catch(e) { /* ignore */ }
    }
    allowUntilDisplay.text(allowUntil.toFixed(2));
    completedDisplay.text(isCompleted ? 'ใช่' : 'ยัง');
}

// 11. Error Handler สำหรับ Player - ไม่ต้องแก้ไข
function onPlayerError(event) {
    console.error('YouTube Player Error:', event.data);
    statusDisplay.text('Player Error: ' + event.data);
    if (typeof showAlert === 'function') { showAlert('error', 'เกิดข้อผิดพลาดกับวิดีโอ', '(Code: ' + event.data + ')'); }
    stopMainInterval();
}

// 12. Helper functions - ไม่ต้องแก้ไข
if (typeof showAlert === 'undefined') {
    window.showAlert = function(icon, title, text = '') {
        if (isAlertShowing) return;
        isAlertShowing = true;
        if (typeof Swal !== 'undefined') {
             Swal.fire({ icon: icon, title: title, text: text, allowOutsideClick: false })
                 .then(() => { isAlertShowing = false; });
        } else {
             alert(title + (text ? "\n" + text : ""));
             isAlertShowing = false;
        }
    }
}
if (typeof showLoading === 'undefined') {
    window.showLoading = function(message = 'Loading...') { if (!isAlertShowing && typeof $.LoadingOverlay !== 'undefined') $.LoadingOverlay("show", { text: message }); }
    window.hideLoading = function() { if (typeof $.LoadingOverlay !== 'undefined') $.LoadingOverlay("hide"); }
}