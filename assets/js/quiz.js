/*
Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
Edit by 25 ตุลาคม 2568 18:20 น. จำนวน 350+ บรรทัด
(FIX: Use requestAnimationFrame to ensure repaint before modal alert)
Edit by 27 ตุลาคม 2568 13:06 น. จำนวน 350+ บรรทัด (แก้ไข Alert แสดงผิดจังหวะ)
Edit by 27 ตุลาคม 2568 13:16 น. จำนวน 350+ บรรทัด (ปิด detectDevTools ชั่วคราว)
Edit by 27 ตุลาคม 2568 13:20 น. จำนวน 350+ บรรทัด (เพิ่ม Log และ setTimeout ใน hideLoading)
Edit by 27 ตุลาคม 2568 13:25 น. จำนวน 350+ บรรทัด (ใช้ .remove() บังคับลบ Overlay)
Edit by 27 ตุลาคม 2568 13:42 น. จำนวน 350+ บรรทัด (รีเซ็ต isSubmitting ก่อน loadQuestion)
Edit by 27 ตุลาคม 2568 13:55 น. จำนวน 350+ บรรทัด (แก้ไข finishAttempt ให้แสดงคะแนนก่อน Redirect)
*/

// (ตัวแปร ATTEMPT_ID, QUIZ_START_TIME, QUIZ_TIME_LIMIT_MINUTES, USER_INFO ถูกส่งมาจาก quiz.php)

// --- ตัวแปรสถานะ ---
let currentQuestionData = null; // Store loaded question data
let selectedChoiceId = null;
let timerInterval = null;
let endTime = QUIZ_START_TIME + (QUIZ_TIME_LIMIT_MINUTES * 60);
let isFinished = false;
let watermarkInterval = null;
let isSubmitting = false;
let isAlertShowingQuizJS = false; // Flag for Modal Alerts
let isToastShowingQuizJS = false; // Flag for Toast Alerts

// --- ตัวแปร UI ---
const timerDisplay = $('#timer-display');
const questionNumberDisplay = $('#question-number');
const questionTextDisplay = $('#question-text');
const choicesArea = $('#choices-area');
const nextButton = $('#next-button');
const finishButton = $('#finish-button');
const loadingErrorDisplay = $('#loading-error');
const watermarkOverlay = $('#watermark-overlay');
const questionArea = $('#question-area');

// --- Helper Functions ---

function showToastQuizJS(icon, title, timer = 3000) {
    if (isToastShowingQuizJS) { console.warn("showToastQuizJS skipped:", title); return; }
    isToastShowingQuizJS = true;
    console.log(`Showing Toast: ${icon} - ${title}`);
    if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: timer, timerProgressBar: true,
            didOpen: (toast) => { toast.addEventListener('mouseenter', Swal.stopTimer); toast.addEventListener('mouseleave', Swal.resumeTimer); },
            didClose: () => { isToastShowingQuizJS = false; console.log("Toast closed:", title); }
        });
        Toast.fire({ icon: icon, title: title });
    } else { alert(title); isToastShowingQuizJS = false; console.log("Toast closed (basic):", title); }
}

// (*** MODIFIED ***) Use requestAnimationFrame before showing modal
function showAlertQuizJS(icon, title, text = '') {
    // Prevent Modal stacking & check if quiz finished
    // *** (แก้ไข) อนุญาตให้ Alert แสดงได้แม้ isFinished = true (สำหรับ Alert ผลสอบ) ***
    if (isAlertShowingQuizJS) {
        console.warn("showAlertQuizJS skipped (Another Alert showing):", title);
        return Promise.resolve(); // Still skip if another alert is up
    }
    isAlertShowingQuizJS = true; // Set flag immediately
    console.log(`Showing Modal Alert (will defer with rAF): ${icon} - ${title}`);

    // Pause timer immediately (if it exists)
    if (timerInterval) clearInterval(timerInterval); timerInterval = null;

    // Return a Promise that wraps the deferred alert
    return new Promise((resolve) => {
        // Defer the Swal.fire call using requestAnimationFrame
        const showAlertAction = () => {
            // Double-check flag after deferral (still needed for race conditions)
            if (!isAlertShowingQuizJS) {
                 console.log("Alert display aborted after rAF deferral (flag reset externally?).");
                 resolve(); // Resolve silently
                 return;
            }
            console.log("Executing deferred Swal.fire via rAF:", title);
            if (typeof Swal !== 'undefined') {
                 Swal.fire({ icon: icon, title: title, text: text, allowOutsideClick: false })
                     .finally(() => {
                         console.log("Modal Alert closed:", title);
                         isAlertShowingQuizJS = false; // Reset flag FIRST
                         console.log("Attempting render/timer resume after alert close.");
                         // Only re-render or resume timer if the quiz is NOT finished
                         if (!isFinished) {
                             renderCurrentQuestion(); // Re-render UI
                             console.log("Resuming timer (quiz not finished).");
                             startTimer(); // Resume timer
                         } else {
                             console.log("Skipping render/timer resume (quiz is finished).");
                         }
                         resolve(); // Resolve outer promise
                     });
            } else {
                 alert(title + (text ? "\n" + text : "")); // Fallback
                 console.log("Modal Alert closed (basic):", title);
                 isAlertShowingQuizJS = false;
                 if (!isFinished) {
                     renderCurrentQuestion();
                     console.log("Resuming timer (quiz not finished).");
                     startTimer();
                 } else {
                      console.log("Skipping render/timer resume (quiz is finished).");
                 }
                 resolve(); // Resolve outer promise
            }
        };

        // Use requestAnimationFrame to wait for the next frame (after repaint)
        console.log("Deferring alert with requestAnimationFrame");
        requestAnimationFrame(showAlertAction);
    });
}


function showLoadingQuizJS(message = 'Loading...') {
    if (isAlertShowingQuizJS) { console.log("Skipped Loading:", message); return; }
    if (typeof $.LoadingOverlay !== 'undefined') {
        // ตรวจสอบว่ามี instance อยู่หรือไม่ ก่อนที่จะแสดงซ้ำ
        const instance = $("body").LoadingOverlay("instance");
        if (!instance) {
            // Reset UI state only if showing loading from scratch
            questionTextDisplay.text('กำลังโหลดคำถาม...');
            choicesArea.empty().html('<div class="text-center text-gray-500">กำลังโหลดตัวเลือก...</div>');
            questionNumberDisplay.text('คำถามข้อที่: -/-');
            nextButton.prop('disabled', true).addClass('opacity-50 cursor-not-allowed').removeClass('hidden');
            finishButton.prop('disabled', true).addClass('opacity-50 cursor-not-allowed').addClass('hidden');

            console.log("Showing Loading:", message);
            try {
                 $.LoadingOverlay("show", { text: message });
            } catch(e) {
                 console.error("Error showing LoadingOverlay:", e);
                 // Fallback or additional error handling if needed
            }
        } else {
            // ถ้ามี instance อยู่แล้ว อาจจะแค่ update ข้อความ (ถ้า Library รองรับ) หรือ log ไว้
            console.log("Loading already active, updating text (if supported) or skipping:", message);
            // instance.text(message); // ลอง update text ถ้า library รองรับ
        }
    } else {
        console.warn("LoadingOverlay library not available.");
    }
}

function hideLoadingQuizJS() {
    console.log("hideLoadingQuizJS called.");
    if (typeof $.LoadingOverlay !== 'undefined') {
        // 1. ลองใช้ฟังก์ชันของ Library ก่อน
        try {
            if ($("body").LoadingOverlay("instance")) {
                console.log("Attempting to Hide Loading via $.LoadingOverlay('hide')");
                $.LoadingOverlay("hide");
                console.log("$.LoadingOverlay('hide') executed.");
            } else {
                console.log("LoadingOverlay instance not found on initial check.");
            }
        } catch(e) {
            console.error("Error calling $.LoadingOverlay('hide'):", e);
        }

        // 2. ใช้ setTimeout เพื่อบังคับลบ Element โดยตรง
        setTimeout(function() {
            const overlayElements = $(".loadingoverlay"); // ค้นหา Element ที่ Library สร้าง
            if (overlayElements.length > 0) {
                console.log(`Found ${overlayElements.length} loadingoverlay elements. Forcing REMOVAL via jQuery .remove()`);
                try {
                    overlayElements.remove(); // ใช้ .remove() เพื่อลบออกจาก DOM
                    console.log("Successfully forced REMOVAL of overlay elements.");

                     // ตรวจสอบว่าหายไปจริงไหม
                     if ($(".loadingoverlay").length === 0) {
                         console.log("Verified: No .loadingoverlay elements found after removal.");
                     } else {
                         console.warn("Failed to verify removal of overlay elements.");
                     }

                } catch (domError) {
                    console.error("Error trying to force remove overlay elements:", domError);
                }
            } else {
                console.log("No .loadingoverlay elements found after timeout, likely already hidden/removed.");
            }
             // ตรวจสอบ instance อีกครั้งหลัง timeout
             if ($("body").LoadingOverlay("instance")) {
                 console.warn("LoadingOverlay instance still exists after force remove attempt. Trying hide again.");
                 try { $.LoadingOverlay("hide", true); } catch(e) { console.error("Error on final hide attempt:", e); } // ลอง force hide
             }

        }, 300); // หน่วงเวลา 300ms

    } else {
        console.warn("LoadingOverlay library not available for hiding.");
    }
}


// --- Initialization ---
$(document).ready(function() {
    console.log("Quiz page ready. Attempt ID:", ATTEMPT_ID);
    startTimer();
    loadQuestion();
    initializeWatermark();
    bindEventListeners();
    setTimeout(activateAntiCheat, 1500);

    choicesArea.on('change', '.choice-radio', function() {
        if (!currentQuestionData || isFinished || isAlertShowingQuizJS) return;
        selectedChoiceId = $(this).val();
        console.log("Choice selected:", selectedChoiceId);
        renderCurrentQuestion(); // Re-render to update button state
   });
   console.log("Choice listener attached.");
});

// --- Timer Functions ---
function startTimer() {
    if (isFinished || isAlertShowingQuizJS) return;
    console.log("Starting timer.");
    updateTimerDisplay(); // Call once immediately
    if(timerInterval) clearInterval(timerInterval); // Clear existing interval if any
    timerInterval = setInterval(updateTimerDisplay, 1000);
}
function updateTimerDisplay() {
    // *** (แก้ไข) ไม่ต้องเช็ค isAlertShowingQuizJS ที่นี่แล้ว เพราะ Alert ผลสอบต้องแสดง ***
    let now = Math.floor(Date.now() / 1000);
    let remainingSeconds = endTime - now;

    // หยุด Interval ถ้าหมดเวลา หรือ สอบเสร็จแล้วเท่านั้น
    if (remainingSeconds <= 0 || isFinished) {
        clearInterval(timerInterval); timerInterval = null;
        if (timerDisplay.length) timerDisplay.text('00:00');
        // Check isFinished ก่อนเรียก finishAttempt (ป้องกันเรียกซ้ำจาก Alert ปิด)
        if (!isFinished) {
             console.log("Time ended! Attempting to finish.");
             finishAttempt(true); // Call finishAttempt when time runs out
        }
        return;
    }

    // แสดงเวลาที่เหลือตามปกติ
    let minutes = Math.floor(remainingSeconds / 60);
    let seconds = remainingSeconds % 60;
    if (timerDisplay.length) {
        timerDisplay.text(
            String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0')
        );
    }
}

// --- Question Loading and Rendering ---

// Renders UI based on currentQuestionData
function renderCurrentQuestion() {
    // Postpone rendering if an alert is active (ยกเว้น Alert ผลสอบ ซึ่ง isFinished จะเป็น true)
    if (isAlertShowingQuizJS && !isFinished) { // *** (แก้ไข) เพิ่ม !isFinished ***
        console.log("Render postponed: Alert is flagged.");
        return;
    }
    console.log("renderCurrentQuestion called.");

    // Skip rendering if the quiz is already finished
    if (isFinished) {
        console.log("Render skipped: Quiz is finished.");
        return;
    }
    // Skip rendering if there's no question data available
    if (!currentQuestionData) {
        console.warn("Render skipped: No question data available.");
        return;
    }

    const data = currentQuestionData;
    console.log("Rendering data for Question ID:", data.question_id);

    try {
        // Update question number and text displays
        if (questionNumberDisplay.length) {
            questionNumberDisplay.text(`คำถามข้อที่: ${data.question_number} / ${data.total_questions}`);
        }
        if (questionTextDisplay.length) {
            questionTextDisplay.text(data.question_text);
        }

        // Render choices area
        if (choicesArea.length) {
            choicesArea.empty(); // Clear previous choices
            if (data.choices && data.choices.length > 0) {
                // Use DocumentFragment for performance
                let fragment = $(document.createDocumentFragment());
                data.choices.forEach((choice, index) => {
                    const uniqueId = `choice_${data.question_id}_${index}`;
                    const isChecked = (selectedChoiceId !== null && selectedChoiceId == choice.choice_id);

                    // Create label and input elements
                    const label = $('<label class="flex items-center p-3 border rounded-md cursor-pointer hover:bg-gray-100 transition duration-150 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-300"></label>')
                                  .attr('for', uniqueId);
                    const input = $('<input type="radio" name="choice" class="mr-3 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500 choice-radio">')
                                  .val(choice.choice_id)
                                  .attr('id', uniqueId)
                                  .prop('checked', isChecked);
                    const span = $('<span class="text-gray-700"></span>').text(choice.choice_text);

                    // Append elements to the label, then to the fragment
                    label.append(input).append(span);
                    fragment.append(label);
                });
                choicesArea.append(fragment); // Append the fragment to the DOM once
            } else {
                // Handle case where no choices are available
                choicesArea.html('<p class="text-red-500">ไม่พบตัวเลือกสำหรับคำถามนี้</p>');
            }
        } else {
            console.error("Choices area element (#choices-area) not found!");
        }

        // Update button states based on whether it's the last question and if an answer is selected
        const canProceed = selectedChoiceId !== null;
        if (data.is_last_question) {
            // Show Finish button, hide Next button
            if (nextButton.length) nextButton.addClass('hidden');
            if (finishButton.length) {
                finishButton.removeClass('hidden')
                            .prop('disabled', !canProceed)
                            .toggleClass('opacity-50 cursor-not-allowed', !canProceed);
            }
        } else {
            // Show Next button, hide Finish button
            if (nextButton.length) {
                nextButton.removeClass('hidden')
                          .prop('disabled', !canProceed)
                          .toggleClass('opacity-50 cursor-not-allowed', !canProceed);
            }
            if (finishButton.length) finishButton.addClass('hidden');
        }

        console.log("Rendering finished successfully for Question ID:", data.question_id);

    } catch (e) {
        console.error("Error during rendering:", e);
        // Display an error message if rendering fails
        displayLoadingError("เกิดข้อผิดพลาดในการแสดงผลคำถาม: " + e.message);
    }
}


// Loads the next question data via AJAX
function loadQuestion() {
    // Skip loading if quiz is finished, already submitting, or an alert is showing (ยกเว้น Alert ผลสอบ)
    if (isFinished || isSubmitting || (isAlertShowingQuizJS && !isFinished)) { // *** (แก้ไข) เพิ่ม !isFinished ***
        console.log("loadQuestion skipped (finished, submitting, or alert showing).");
        return;
    }
    console.log("loadQuestion called.");

    // Reset current state before loading
    currentQuestionData = null;
    selectedChoiceId = null;

    // Show loading indicator and hide error message
    showLoadingQuizJS('กำลังโหลดคำถาม...');
    loadingErrorDisplay.addClass('hidden');

    console.log("Sending AJAX request to get_question for attempt:", ATTEMPT_ID);
    $.ajax({
        url: 'api/exam.php?action=get_question',
        type: 'POST',
        data: { attempt_id: ATTEMPT_ID },
        dataType: 'json'
    })
    .done(function(response) {
        console.log("AJAX get_question success. Response:", response);
        try {
            if (response && response.status === 'success') {
                currentQuestionData = response.question; // Store the received question data

                if (currentQuestionData) {
                    console.log("Received Question ID:", currentQuestionData.question_id);
                    // Update the timer's end time based on server response
                    if (response.remaining_time_server && response.remaining_time_server > 0) {
                         endTime = Math.floor(Date.now() / 1000) + response.remaining_time_server;
                         // Start timer only if it's not running and no alert is showing (ยกเว้น Alert ผลสอบ)
                         if (!timerInterval && !(isAlertShowingQuizJS && !isFinished)) startTimer(); // *** (แก้ไข) เพิ่ม !isFinished ***
                    } else if (response.remaining_time_server <= 0 && !isFinished && !(isAlertShowingQuizJS && !isFinished)) { // *** (แก้ไข) เพิ่ม !isFinished ***
                        // If server says time is up, finish the attempt
                        console.log("Server indicated time expired. Finishing attempt.");
                        finishAttempt(true);
                    }
                    // Note: Rendering will happen in the .always() block to ensure loading is hidden first.
                } else if (response.is_last_question === true) {
                    // No more questions, finish the attempt
                    console.log("Server indicated last question was answered. Finishing attempt.");
                    finishAttempt(false);
                } else {
                    // Response format unexpected
                    console.error("Invalid question data received:", response);
                    displayLoadingError('ได้รับข้อมูลคำถามที่ไม่ถูกต้องจากเซิร์ฟเวอร์');
                }
            } else if (response && response.status === 'completed') {
                // Attempt is already completed according to server
                console.log("Server indicated attempt is already completed. Finishing attempt.");
                finishAttempt(false);
            } else {
                // API returned an error status
                console.error("API Error (get_question):", response.message);
                displayLoadingError(response.message || 'ไม่สามารถโหลดคำถามได้');
            }
        } catch (e) {
            // Error processing the successful response
            console.error("Error processing get_question response:", e);
            displayLoadingError('เกิดข้อผิดพลาดในการประมวลผลข้อมูลคำถาม: ' + e.message);
        }
    })
    .fail(function(jqXHR, textStatus, errorThrown) {
        // AJAX request itself failed
        console.error("AJAX get_question failed:", textStatus, errorThrown);
        displayLoadingError('เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์');
    })
    .always(function() {
        console.log("AJAX get_question finished. Calling hideLoadingQuizJS.");
        hideLoadingQuizJS(); // Hide loading indicator regardless of success/failure

        // Attempt to render the question immediately after hiding loading,
        // but only if no alert is currently showing (ยกเว้น Alert ผลสอบ).
        if (!(isAlertShowingQuizJS && !isFinished) && currentQuestionData) { // *** (แก้ไข) เพิ่ม !isFinished ***
            console.log("Attempting immediate render after loading is hidden.");
            renderCurrentQuestion();
        } else if (currentQuestionData) {
             // If an alert is showing, rendering is deferred.
             // The alert's .finally() block will call renderCurrentQuestion later.
             console.log("Initial render deferred (alert might be showing or about to show via rAF).");
        }
    });
}


// Displays an error message in the question area
function displayLoadingError(message) {
     hideLoadingQuizJS(); // Ensure loading is hidden
     console.error("Displaying Loading Error:", message);

     // Reset question state
     currentQuestionData = null;
     selectedChoiceId = null;

     // Update UI to show the error
     questionTextDisplay.html('<i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>เกิดข้อผิดพลาด');
     choicesArea.empty().html(`<p class="text-center text-red-600">${message}</p>`);
     loadingErrorDisplay.text(message).removeClass('hidden'); // Show specific error message area
     nextButton.addClass('hidden'); // Hide navigation buttons
     finishButton.addClass('hidden');
}


// --- Answer Submission ---
function submitAnswerAndLoadNext() {
    // Prevent submission if already submitting, finished, or alert is showing (ยกเว้น Alert ผลสอบ)
    if (isSubmitting || isFinished || (isAlertShowingQuizJS && !isFinished)) { // *** (แก้ไข) เพิ่ม !isFinished ***
        console.log("Submit skipped (submitting, finished, or alert showing).");
        return;
    }
    // Check selectedChoiceId only if it's NOT the last question OR if it IS the last question but the finish button was clicked
    if (selectedChoiceId === null && currentQuestionData && !currentQuestionData.is_last_question) {
        console.log("Submit prevented: No choice selected for non-last question.");
        showAlertQuizJS('warning', 'กรุณาเลือกคำตอบ'); // Show alert if no choice selected for non-last question
        return;
    }
    // Ensure there is valid question data
    if (!currentQuestionData || !currentQuestionData.question_id) {
        console.error("Submit prevented: Invalid currentQuestionData.");
        showAlertQuizJS('error', 'ข้อผิดพลาด', 'ไม่พบข้อมูลคำถามปัจจุบัน ไม่สามารถส่งคำตอบได้');
        return;
    }

    isSubmitting = true; // Set submitting flag
    console.log(`Submitting answer for QID: ${currentQuestionData.question_id}, Choice ID: ${selectedChoiceId}.`);

    // Disable buttons during submission
    nextButton.prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
    finishButton.prop('disabled', true).addClass('opacity-50 cursor-not-allowed');

    // Send answer via AJAX
    $.ajax({
        url: 'api/exam.php?action=submit_answer',
        type: 'POST',
        data: {
            attempt_id: ATTEMPT_ID,
            question_id: currentQuestionData.question_id,
            selected_choice_id: selectedChoiceId
        },
        dataType: 'json'
    })
    .done(function(response) {
        console.log("AJAX submit_answer success:", response);
        if (response && response.status === 'success') {
             // รีเซ็ต isSubmitting ที่นี่ ก่อนเรียก loadQuestion
            isSubmitting = false; // Reset flag HERE
            console.log("isSubmitting reset to false before loading next.");

            // If it was the last question, finish the attempt. Otherwise, load the next question.
            if (currentQuestionData.is_last_question) {
                console.log("Last question answered. Finishing attempt.");
                finishAttempt(false);
            } else {
                console.log("Answer submitted. Loading next question.");
                loadQuestion(); // ตอนนี้ isSubmitting ควรเป็น false แล้ว
            }
        } else {
            // API returned an error
            isSubmitting = false; // รีเซ็ต isSubmitting ในกรณี Error ด้วย
            console.error("API Error (submit_answer):", response.message);
            showAlertQuizJS('error', 'บันทึกคำตอบล้มเหลว', response.message || 'ไม่สามารถบันทึกคำตอบได้ กรุณาลองอีกครั้ง');
            renderCurrentQuestion(); // Re-enable buttons if submission fails
        }
    })
    .fail(function(jqXHR, textStatus, errorThrown) {
        // AJAX request failed
        isSubmitting = false; // *** (เพิ่ม) รีเซ็ต isSubmitting ใน .fail() ด้วย ***
        console.error("AJAX submit_answer failed:", textStatus, errorThrown);
        showAlertQuizJS('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'ไม่สามารถส่งคำตอบได้ กรุณาตรวจสอบการเชื่อมต่อแล้วลองอีกครั้ง');
        renderCurrentQuestion(); // Re-enable buttons if submission fails
    })
    .always(function() {
        // Reset submitting flag after request completes (เป็น fallback)
        if (isSubmitting) { // เช็คก่อน เผื่อ .done() ทำงานไปแล้ว
             isSubmitting = false;
             console.log("isSubmitting reset to false in .always() as fallback.");
        }
        console.log("Submission process finished.");
    });
}


// --- Finishing Attempt ---
function finishAttempt(isTimeout = false) {
    // Prevent multiple finish calls
    if (isFinished) {
        console.log("finishAttempt skipped: Already finished.");
        return;
    }
    isFinished = true; // Set finished flag immediately
    console.log(`Finishing attempt ${ATTEMPT_ID}. Triggered by timeout: ${isTimeout}.`);

    // Stop timers and intervals
    clearInterval(timerInterval); timerInterval = null;
    clearInterval(watermarkInterval); watermarkInterval = null;
    if (devToolsChecker) { clearInterval(devToolsChecker); devToolsChecker = null; }

    // Unbind anti-cheat listeners
    $(document).off('.quizAntiCheat');

    // Update UI to indicate finishing
    questionTextDisplay.text("กำลังส่งคำตอบและประมวลผล...");
    choicesArea.empty();
    nextButton.addClass('hidden');
    finishButton.addClass('hidden');

    // Show loading indicator
    showLoadingQuizJS(isTimeout ? 'หมดเวลาแล้ว! กำลังส่งคำตอบ...' : 'กำลังส่งคำตอบ...');

    // Delay submission slightly to allow UI update
    setTimeout(() => {
        console.log("Sending AJAX request to finish_attempt for attempt:", ATTEMPT_ID);
        $.ajax({
            url: 'api/exam.php?action=finish_attempt',
            type: 'POST',
            data: { attempt_id: ATTEMPT_ID },
            dataType: 'json'
        })
        .done(function(response) {
            hideLoadingQuizJS(); // *** (แก้ไข) ซ่อน Loading ก่อนแสดง Alert ***
            console.log("AJAX finish_attempt success:", response); // Log response ที่ได้รับ

            if (response && response.status === 'success' && typeof response.score !== 'undefined') {
                 // Determine message based on timeout and pass status
                 let title = isTimeout ? 'หมดเวลา!' : 'ส่งคำตอบสำเร็จ!';
                 let text = `คุณได้คะแนน ${response.score}% `;
                 text += response.passed ? ' (ผ่านเกณฑ์)' : ' (ไม่ผ่านเกณฑ์)';
                 let icon = response.passed ? 'success' : 'error';

                 console.log(`Preparing to show result alert: ${icon} - ${title} - ${text}`);
                 // Show result alert (using the deferred showAlertQuizJS)
                 // *** (แก้ไข) ย้าย Redirect เข้าไปใน .then() ***
                 showAlertQuizJS(icon, title, text)
                     .then(() => {
                         // Redirect AFTER alert is closed by user
                         if (response.course_id) {
                             console.log("Redirecting to course page:", response.course_id);
                             window.location.href = `course.php?id=${response.course_id}`;
                         } else {
                             console.log("Redirecting to dashboard (no course_id received).");
                             window.location.href = 'dashboard.php';
                         }
                     });
            } else {
                // API returned an error or unexpected response
                console.error("API Error (finish_attempt):", response ? response.message : 'No response');
                // Allow user to potentially retry or see an error message
                showAlertQuizJS('error', 'ผิดพลาด', (response ? response.message : '') || 'การจบการสอบล้มเหลว กรุณาลองอีกครั้ง')
                    .finally(() => {
                        isFinished = false; // Reset finished flag if finishing failed
                        console.log("Finish failed, attempting to reload question state.");
                        loadQuestion(); // Try reloading the current state
                     });
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            hideLoadingQuizJS(); // *** (แก้ไข) ซ่อน Loading ในกรณี fail ด้วย ***
            console.error("AJAX finish_attempt failed:", textStatus, errorThrown);
            showAlertQuizJS('error', 'ผิดพลาดในการเชื่อมต่อ', 'ไม่สามารถส่งผลการสอบได้ กรุณาลองอีกครั้ง')
                .finally(() => {
                    isFinished = false; // Reset finished flag if finishing failed
                    console.log("Finish failed (AJAX error), attempting to reload question state.");
                    loadQuestion(); // Try reloading the current state
                });
        });
        // .always() ไม่จำเป็นต้องมี hideLoading แล้ว เพราะย้ายไปไว้ใน .done() และ .fail()
    }, 500); // Delay before sending finish request
}


// --- Watermark Functions ---
function initializeWatermark() {
    createWatermark(); // Create initial watermark
    if (!watermarkInterval) {
        // Update watermark time periodically
        watermarkInterval = setInterval(updateWatermarkTime, 60000); // Update every minute
    }
}
function createWatermark() {
     if (!watermarkOverlay.length || !USER_INFO) return;
     watermarkOverlay.empty(); // Clear existing
     const now = new Date();
     const timeString = now.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
     const dateString = now.toLocaleDateString('th-TH', { day: '2-digit', month: 'short', year: 'numeric' });
     const watermarkText = `${USER_INFO.name || 'N/A'} (${USER_INFO.email || 'N/A'}) IP: ${USER_INFO.ip || 'N/A'} - ${dateString} ${timeString}`;

     // Create multiple spread-out watermark elements for better coverage
     for(let i=0; i<15; i++) {
        const top = Math.random() * 90 + 5; // 5% to 95%
        const left = Math.random() * 90 + 5; // 5% to 95%
        const rotation = Math.random() * 60 - 30; // -30 to +30 deg
        const span = $('<span>').text(watermarkText)
                         .css({
                            position: 'absolute',
                            top: `${top}%`,
                            left: `${left}%`,
                            transform: `rotate(${rotation}deg)`,
                            transformOrigin: 'center center',
                            whiteSpace: 'nowrap',
                            fontSize: '10px', // Smaller font size
                            color: '#ccc', // Lighter color
                            opacity: 0.15, // More subtle opacity
                            pointerEvents: 'none',
                            zIndex: 9999
                         });
        watermarkOverlay.append(span);
     }
}
function updateWatermarkTime() {
    // Recreate the watermark with updated time
    createWatermark();
}

// --- Anti-Cheat Functions ---
function activateAntiCheat() {
    // Disable context menu
    $(document).on('contextmenu.quizAntiCheat', '#quiz-container', function(e){
        if(isFinished) return; // Allow if finished
        e.preventDefault();
        sendProctorEvent('context_menu');
        showAlertQuizJS('warning', 'ห้ามคลิกขวา', 'ตรวจพบการพยายามเปิดเมนูคลิกขวา');
        return false;
    });

    // Disable copy, cut, paste
    $(document).on('copy.quizAntiCheat cut.quizAntiCheat paste.quizAntiCheat', '#quiz-container', function(e){
        if(isFinished) return; // Allow if finished
        e.preventDefault();
        sendProctorEvent('copy_paste_attempt');
        showAlertQuizJS('warning', 'ห้ามคัดลอก/ตัด/วาง', 'ตรวจพบการพยายามคัดลอก, ตัด หรือวางเนื้อหา');
        return false;
    });

    // Detect keyboard shortcuts and specific keys
    $(document).on('keydown.quizAntiCheat', function(e) {
        if (isFinished) return; // Allow if finished

        // Allow refresh (F5, Ctrl+R) - browser handles confirmation
        if (e.key === 'F5' || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'r')) {
            console.log("Refresh attempt detected, allowing browser default.");
            return;
        }
         // Allow address bar focus (Ctrl+L)
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'l') {
             console.log("Address bar focus attempt detected, allowing browser default.");
            return;
        }

        // Detect Print Screen related keys
        if (e.key === 'PrintScreen' || (e.metaKey && e.shiftKey && (e.key === '3' || e.key === '4'))) { // Mac screenshot shortcuts
            e.preventDefault();
            sendProctorEvent('print_screen_attempt');
            showAlertQuizJS('error','ห้ามจับภาพหน้าจอ!', 'ตรวจพบการพยายามจับภาพหน้าจอ');
            return false;
        }

        // Detect common Developer Tools shortcuts
        if (e.key === 'F12' ||
           (e.shiftKey && e.ctrlKey && ['I', 'J', 'C'].includes(e.key.toUpperCase())) || // Ctrl+Shift+I/J/C
           (e.metaKey && e.altKey && ['I', 'J', 'C'].includes(e.key.toUpperCase()))) { // Cmd+Option+I/J/C (Mac)
            e.preventDefault();
            sendProctorEvent('devtools_attempt');
            showToastQuizJS('error', 'ห้ามเปิด Developer Tools!'); // Use Toast first
            return false;
        }

        // Detect other Ctrl/Meta key combinations (potential shortcuts)
        if ((e.ctrlKey || e.metaKey) && !['Control', 'Meta', 'Shift', 'Alt'].includes(e.key)) {
             // Allow specific combinations like Ctrl+A (Select All) if needed, otherwise block
             if (e.key.toLowerCase() !== 'a') { // Example: allow select all
                 e.preventDefault();
                 sendProctorEvent('shortcut_attempt', { key: e.key });
                 showAlertQuizJS('warning', 'ห้ามใช้คีย์ลัด!', `ตรวจพบการใช้คีย์ลัด (${e.key})`);
                 return false;
             }
        }
    });

    // Start periodic DevTools check (Temporarily disabled for testing)
    // setTimeout(detectDevTools, 2500); // Start checking after a short delay
    console.log("Developer Tools detection temporarily disabled for testing."); // Log that it's disabled
}

let devToolsChecker = null;
function detectDevTools() {
    if (isFinished) { if(devToolsChecker) clearInterval(devToolsChecker); return; } // Stop checking if finished

    const threshold = 160; // Adjust sensitivity
    let devtoolsOpen = false;

    const check = () => {
         if ((window.outerWidth - window.innerWidth > threshold) || (window.outerHeight - window.innerHeight > threshold)) {
             if (!devtoolsOpen) {
                  devtoolsOpen = true;
                  console.warn("DevTools detected (dimension check).");
                  sendProctorEvent('devtools_opened');
                  showToastQuizJS('error', 'ตรวจพบ Developer Tools!');
             }
         } else { devtoolsOpen = false; }

        // Additional check using console object (less reliable)
        // const element = new Image();
        // Object.defineProperty(element, 'id', { get: () => { devtoolsOpen = true; } });
        // console.log(element);
    };

    // Run check periodically
    if (!devToolsChecker) {
        devToolsChecker = setInterval(check, 1000); // Check every second
    }
}

// Function to send proctoring events to the server
function sendProctorEvent(eventType, eventData = null) {
    if (isFinished) return; // Don't send if finished
    console.log("Sending Proctor Event:", eventType, eventData);
    $.ajax({
        url: 'api/exam.php?action=save_proctor_event',
        type: 'POST',
        data: {
            attempt_id: ATTEMPT_ID,
            event_type: eventType,
            event_data: eventData ? JSON.stringify(eventData) : null
        },
        dataType: 'json'
    })
    .done(function(response) {
        if (response && response.status === 'success') {
            console.log("Proctor event saved:", eventType);
        } else {
            console.warn("Failed to save proctor event:", eventType, response.message);
        }
    })
    .fail(function() {
        console.error("Error sending proctor event:", eventType);
    });
}


// --- Event Listener Binding ---
function bindEventListeners() {
    // Use event delegation for choices if they are dynamically loaded (already done)

    // Bind click event for the Next button
    nextButton.off('click').on('click', function() {
         console.log("Next button clicked.");
         // Double-check if a choice is selected before proceeding
         if (selectedChoiceId === null && currentQuestionData && !currentQuestionData.is_last_question) {
              showAlertQuizJS('warning', 'กรุณาเลือกคำตอบ');
         } else {
              submitAnswerAndLoadNext();
         }
    });

    // Bind click event for the Finish button
    finishButton.off('click').on('click', function() {
        console.log("Finish button clicked.");
        // Prevent action if already submitting, finished, or alert is showing
        if (isSubmitting || isFinished || isAlertShowingQuizJS) return;

        // Confirm submission with the user
        Swal.fire({ // Use Swal directly for confirmation, not showAlertQuizJS
             title: 'ยืนยันการส่งคำตอบ?',
             text: "คุณต้องการส่งคำตอบทั้งหมดใช่หรือไม่?",
             icon: 'question',
             showCancelButton: true,
             confirmButtonColor: '#3085d6',
             cancelButtonColor: '#d33',
             confirmButtonText: 'ใช่, ส่งคำตอบ',
             cancelButtonText: 'ยกเลิก'
        }).then((result) => {
             // Check isFinished again in case finish was triggered during confirmation (e.g., timeout)
             if (result.isConfirmed && !isFinished) {
                 console.log("User confirmed submission.");
                 // Check if the last question's answer is selected before finishing
                 if (selectedChoiceId === null && currentQuestionData && currentQuestionData.is_last_question) {
                     console.log("Finish prevented: No choice selected for last question.");
                     showAlertQuizJS('warning', 'กรุณาเลือกคำตอบข้อสุดท้ายก่อนส่ง');
                 } else {
                     // Submit the last answer (if any selected) then finish, or just finish if already submitted/no selection needed on finish
                     console.log("Proceeding to submit/finish.");
                     // If an answer is selected for the last question, submit it first. finishAttempt will be called in submitAnswerAndLoadNext.
                     if (selectedChoiceId !== null && currentQuestionData && currentQuestionData.is_last_question) {
                         submitAnswerAndLoadNext();
                     } else {
                         // If no answer selected for last Q or already submitted, just call finish
                         finishAttempt(false);
                     }
                 }
             } else if (result.dismiss) {
                 console.log("User cancelled submission.");
             }
        });
    });

    console.log("Button listeners bound.");
}