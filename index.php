<?php
session_start();

// Configuration
$API_KEY = '';
$SENDER_ID = '';
$MESSAGE_ID = '';  // DLT Template ID

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'send_otp':
                sendOTP();
                break;
            case 'verify_otp':
                verifyOTP();
                break;
            case 'resend_otp':
                sendOTP();
                break;
        }
    }
    exit;
}

function sendOTP() {
    global $API_KEY, $SENDER_ID, $MESSAGE_ID;
    $mobile = $_POST['mobile_no'];
    
    // Validate mobile number
    if (!preg_match("/^[6-9][0-9]{9}$/", $mobile)) {
        echo json_encode([
            'status' => false,
            'message' => 'Please enter a valid mobile number'
        ]);
        return;
    }

    // Generate OTP
    $otp = rand(100000, 999999);
    $_SESSION['otp'] = $otp;
    $_SESSION['mobile'] = $mobile;
    
    // Call Fast2SMS API with DLT route
    $params = [
        'authorization' => $API_KEY,
        'route' => 'dlt',
        'sender_id' => $SENDER_ID,
        'message' => $MESSAGE_ID,
        'variables_values' => $otp,
        'flash' => 0,
        'numbers' => $mobile
    ];
    
    $url = 'https://www.fast2sms.com/dev/bulkV2?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    
    if(curl_errno($ch)){
        echo json_encode([
            'status' => false,
            'message' => 'SMS sending failed: ' . curl_error($ch)
        ]);
        curl_close($ch);
        return;
    }
    
    curl_close($ch);
    $result = json_decode($response, true);
    
    if (isset($result['return']) && $result['return'] === true) {
        echo json_encode([
            'status' => true,
            'message' => 'OTP sent successfully'
        ]);
    } else {
        echo json_encode([
            'status' => false,
            'message' => 'Failed to send OTP: ' . ($result['message'] ?? 'Unknown error')
        ]);
    }
}

function verifyOTP() {
    $userOtp = $_POST['otp'];
    $mobile = $_POST['mobile_no'];
    
    if (isset($_SESSION['otp']) && isset($_SESSION['mobile']) && 
        $_SESSION['otp'] == $userOtp && $_SESSION['mobile'] == $mobile) {
        unset($_SESSION['otp']);
        unset($_SESSION['mobile']);
        echo json_encode([
            'status' => true,
            'message' => 'OTP verified successfully!'
        ]);
    } else {
        echo json_encode([
            'status' => false,
            'message' => 'Invalid OTP. Please try again.'
        ]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DLT OTP Verification</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8 bg-white p-10 rounded-xl shadow-lg">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Phone Verification
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Verify your phone number to continue
                </p>
            </div>

            <!-- Phone Number Form -->
            <div id="phoneForm" class="mt-8 space-y-6">
                <div class="rounded-md shadow-sm">
                    <div>
                        <label for="mobile" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                        <input id="mobile" name="mobile" type="tel" required 
                               class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                               placeholder="Enter 10-digit mobile number">
                    </div>
                </div>

                <div>
                    <button onclick="sendOTP()" 
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Send Verification Code
                    </button>
                </div>
            </div>

            <!-- OTP Form -->
            <div id="otpForm" class="mt-8 space-y-6 hidden">
                <div class="rounded-md shadow-sm">
                    <div>
                        <label for="otp" class="block text-sm font-medium text-gray-700 mb-2">Verification Code</label>
                        <input id="otp" name="otp" type="text" maxlength="6" required
                               class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm text-center tracking-widest"
                               placeholder="Enter 6-digit code">
                    </div>
                </div>

                <div class="flex flex-col space-y-3">
                    <button onclick="verifyOTP()" 
                            class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Verify Code
                    </button>
                    
                    <button onclick="resendOTP()" id="resendButton"
                            class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Resend Code
                    </button>
                </div>
            </div>

            <!-- Status Messages -->
            <div id="statusMessage" class="hidden">
                <div class="rounded-md p-4">
                    <p class="text-sm font-medium text-center"></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let resendTimer;

        function showMessage(message, isError = false) {
            const statusDiv = document.getElementById('statusMessage');
            const messageP = statusDiv.querySelector('p');
            messageP.textContent = message;
            messageP.className = `text-sm font-medium ${isError ? 'text-red-600' : 'text-green-600'}`;
            statusDiv.classList.remove('hidden');
        }

        function startResendTimer() {
            const button = document.getElementById('resendButton');
            let timeLeft = 30;
            button.disabled = true;
            
            clearInterval(resendTimer);
            resendTimer = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(resendTimer);
                    button.textContent = 'Resend Code';
                    button.disabled = false;
                    return;
                }
                button.textContent = `Resend Code (${timeLeft}s)`;
                timeLeft--;
            }, 1000);
        }

        function sendOTP() {
            const mobile = document.getElementById('mobile').value;
            
            if (!/^[6-9][0-9]{9}$/.test(mobile)) {
                showMessage('Please enter a valid 10-digit mobile number', true);
                return;
            }
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: { 
                    action: 'send_otp',
                    mobile_no: mobile 
                },
                success: function(response) {
                    if(response.status) {
                        document.getElementById('phoneForm').classList.add('hidden');
                        document.getElementById('otpForm').classList.remove('hidden');
                        showMessage('Verification code sent successfully!');
                        startResendTimer();
                    } else {
                        showMessage(response.message, true);
                    }
                },
                error: function() {
                    showMessage('Error sending verification code. Please try again.', true);
                }
            });
        }

        function verifyOTP() {
            const mobile = document.getElementById('mobile').value;
            const otp = document.getElementById('otp').value;
            
            if (otp.length !== 6) {
                showMessage('Please enter a valid 6-digit verification code', true);
                return;
            }
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: { 
                    action: 'verify_otp',
                    mobile_no: mobile,
                    otp: otp 
                },
                success: function(response) {
                    showMessage(response.message, !response.status);
                    if(response.status) {
                        // Handle successful verification here
                        setTimeout(() => {
                            alert('Verification successful! You can proceed now.');
                            // Add your redirect or next step logic here
                        }, 1000);
                    }
                },
                error: function() {
                    showMessage('Error verifying code. Please try again.', true);
                }
            });
        }

        function resendOTP() {
            sendOTP();
        }

        // Add input validation
        document.getElementById('mobile').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });

        document.getElementById('otp').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    </script>
</body>
</html>