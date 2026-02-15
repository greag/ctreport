<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>OTP Login</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800">
        <div class="max-w-md mx-auto px-4 py-12">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-lg p-8">
                <h1 class="text-2xl font-bold text-slate-900">Login with OTP</h1>
                <p class="mt-2 text-sm text-slate-600">Enter your login mobile number to receive an OTP.</p>

                <div class="mt-6 space-y-4">
                    <div>
                        <label for="otp-phone" class="block text-sm font-medium text-slate-700 mb-2">Mobile Number</label>
                        <input id="otp-phone" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Enter login mobile number" />
                    </div>

                    <div>
                        <label for="otp-code" class="block text-sm font-medium text-slate-700 mb-2">OTP</label>
                        <input id="otp-code" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Enter OTP" />
                    </div>

                    <div class="flex gap-3">
                        <button id="send-otp" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Send OTP</button>
                        <button id="verify-otp" class="flex-1 px-4 py-2 bg-slate-800 text-white rounded-md hover:bg-slate-900">Verify OTP</button>
                    </div>

                    <p id="otp-status" class="text-sm text-slate-600"></p>
                </div>
            </div>
        </div>

        <script>
            const apiBaseUrl = @json(url('/'));
            const phoneInput = document.getElementById('otp-phone');
            const otpInput = document.getElementById('otp-code');
            const sendBtn = document.getElementById('send-otp');
            const verifyBtn = document.getElementById('verify-otp');
            const statusLine = document.getElementById('otp-status');

            const setStatus = (message, isError = false) => {
                statusLine.textContent = message;
                statusLine.classList.toggle('text-red-600', isError);
                statusLine.classList.toggle('text-slate-600', !isError);
            };

            sendBtn.addEventListener('click', async () => {
                const phone = phoneInput.value.trim();
                if (!phone) {
                    setStatus('Please enter a mobile number.', true);
                    return;
                }

                setStatus('Sending OTP...');
                const response = await fetch(`${apiBaseUrl}/otp/send`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                    },
                    body: JSON.stringify({ phone }),
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    setStatus(data.message || 'Failed to send OTP.', true);
                    return;
                }
                setStatus(data.message || 'OTP sent.');
            });

            verifyBtn.addEventListener('click', async () => {
                const phone = phoneInput.value.trim();
                const otp = otpInput.value.trim();
                if (!phone || !otp) {
                    setStatus('Please enter both phone and OTP.', true);
                    return;
                }

                setStatus('Verifying OTP...');
                const response = await fetch(`${apiBaseUrl}/otp/verify`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                    },
                    body: JSON.stringify({ phone, otp }),
                });

                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    setStatus(data.message || 'OTP verification failed.', true);
                    return;
                }

                window.location.href = `${apiBaseUrl}/`;
            });
        </script>
    </body>
</html>
