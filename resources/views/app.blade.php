<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Credit Report Analyzer</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            .spinner {
                width: 20px;
                height: 20px;
                border: 2px solid rgba(255, 255, 255, 0.4);
                border-top-color: #fff;
                border-radius: 50%;
                animation: spin 0.8s linear infinite;
            }
            @keyframes spin {
                to { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800">
        <div class="max-w-4xl mx-auto px-4 py-10">
            <header class="text-center mb-10">
                <div class="flex flex-col sm:flex-row items-center justify-center sm:justify-between gap-4">
                    <div class="text-center sm:text-left">
                        <h1 class="text-4xl font-extrabold text-slate-900">Credit Report Analyzer</h1>
                        <p class="mt-3 text-lg text-slate-600">Upload a scanned credit report PDF to extract and structure its contents.</p>
                    </div>
                    <form method="POST" action="/logout" class="shrink-0">
                        @csrf
                        <button type="submit" class="px-4 py-2 border border-slate-300 text-slate-700 font-semibold rounded-lg hover:bg-slate-100 transition">
                            Logout
                        </button>
                    </form>
                </div>
            </header>

            <main class="bg-white shadow-xl rounded-2xl p-6 sm:p-10 border border-slate-200">
                <div id="form-section" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="user-id" class="block text-sm font-medium text-slate-700 mb-2">User ID <span class="text-slate-500">(optional)</span></label>
                            <input id="user-id" type="text" readonly class="w-full px-3 py-2 border border-slate-300 rounded-md bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="User ID will be populated from mobile lookup" />
                        </div>
                        <div>
                            <label for="mobile-number" class="block text-sm font-medium text-slate-700 mb-2">Mobile Number <span class="text-slate-500">(optional)</span></label>
                            <input id="mobile-number" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Enter the mobile number" />
                            <p class="mt-2 text-xs text-slate-500">Provide User ID or Mobile Number.</p>
                            <p id="mobile-status" class="mt-2 text-xs text-slate-500 hidden"></p>
                        </div>
                        <div>
                            <label for="customer-name" class="block text-sm font-medium text-slate-700 mb-2">Customer Name</label>
                            <input id="customer-name" type="text" readonly class="w-full px-3 py-2 border border-slate-300 rounded-md bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Customer name will be auto-filled" />
                        </div>
                    </div>
                    <div>
                        <label for="report-type" class="block text-sm font-medium text-slate-700 mb-2">Report Type</label>
                        <select id="report-type" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="TUCIBIL" selected>TUCIBIL</option>
                            <option value="" disabled>Equifax (placeholder)</option>
                            <option value="" disabled>Experian (placeholder)</option>
                            <option value="" disabled>CrifHighmark (placeholder)</option>
                        </select>
                    </div>
                    <div>
                        <label for="pdf-password" class="block text-sm font-medium text-slate-700 mb-2">PDF Password <span class="text-slate-500">(optional)</span></label>
                        <input id="pdf-password" type="password" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Enter password if PDF is protected" />
                        <p class="mt-2 text-xs text-slate-500">Leave blank if the PDF is not password-protected.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Credit Report PDF</label>
                        <div id="dropzone" class="relative w-full p-8 border-2 border-dashed rounded-xl text-center transition-all duration-200 border-slate-300 hover:border-indigo-400 cursor-pointer">
                            <input id="file-input" type="file" accept=".pdf" class="hidden" />
                            <div class="flex flex-col items-center justify-center space-y-3 text-slate-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1">
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <p class="text-lg font-semibold"><span class="text-indigo-500">Click to upload</span> or drag and drop</p>
                                <p class="text-sm">PDF only</p>
                            </div>
                        </div>
                        <p id="selected-file" class="mt-2 text-sm text-slate-600 hidden"></p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <button id="process-btn" class="w-full sm:w-auto px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg shadow-md transition">Process File</button>
                        <a href="/reports" class="w-full sm:w-auto px-6 py-3 border border-slate-300 text-slate-700 font-semibold rounded-lg shadow-sm hover:bg-slate-50 text-center transition">View Existing Reports</a>
                    </div>
                    <p id="error-message" class="text-sm text-red-600 hidden"></p>
                </div>

                <div id="loading-section" class="hidden text-center">
                    <div class="flex items-center justify-center space-x-3">
                        <div class="spinner"></div>
                        <p class="text-lg font-medium text-indigo-600" id="status-message">Processing... please wait.</p>
                    </div>
                </div>

                <div id="result-section" class="hidden">
                    <h2 class="text-2xl font-bold text-slate-800 mb-6 text-center">Extraction Complete</h2>
                    <div id="failed-accounts" class="hidden bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 mb-6 rounded-r-lg"></div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                        @if($isAdmin ?? false)
                            <a id="download-txt" class="flex items-center justify-center bg-sky-500 hover:bg-sky-600 text-white font-semibold py-3 px-4 rounded-lg shadow-md transition">Download .txt</a>
                            <a id="download-json" class="flex items-center justify-center bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 px-4 rounded-lg shadow-md transition">Download .json</a>
                            <a id="download-xlsx" class="flex items-center justify-center bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-3 px-4 rounded-lg shadow-md transition">Download .xlsx</a>
                        @else
                            <div class="col-span-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                Downloads are restricted to admin users.
                            </div>
                        @endif
                        <a id="view-report-btn" class="hidden flex items-center justify-center bg-slate-800 hover:bg-slate-900 text-white font-semibold py-3 px-4 rounded-lg shadow-md transition">View Report</a>
                    </div>

                    <div class="text-center">
                        <button id="reset-btn" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 transition">Process Another File</button>
                    </div>
                </div>

                <div id="overwrite-modal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
                    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
                        <h3 class="text-lg font-semibold text-slate-800">Report Already Exists</h3>
                        <p class="mt-2 text-sm text-slate-600">A report with the same report type and control number already exists. Do you want to overwrite it?</p>
                        <div class="mt-4 space-y-2 text-sm text-slate-700">
                            <div><span class="font-semibold">Report Type:</span> <span id="dup-report-type"></span></div>
                            <div><span class="font-semibold">Control Number:</span> <span id="dup-control-number"></span></div>
                            <div><span class="font-semibold">User ID:</span> <span id="dup-user-id"></span></div>
                            <div><span class="font-semibold">Mobile Number:</span> <span id="dup-mobile-number"></span></div>
                        </div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button id="cancel-overwrite" class="px-4 py-2 text-slate-700 border border-slate-300 rounded-md hover:bg-slate-50">Cancel</button>
                            <button id="confirm-overwrite" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Overwrite</button>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="text-center mt-10 text-sm text-slate-500">
            </footer>
        </div>

        <script>
            const apiBaseUrl = window.location.origin;
            const userIdInput = document.getElementById('user-id');
            const mobileNumberInput = document.getElementById('mobile-number');
            const reportTypeInput = document.getElementById('report-type');
            const passwordInput = document.getElementById('pdf-password');
            const fileInput = document.getElementById('file-input');
            const dropzone = document.getElementById('dropzone');
            const processBtn = document.getElementById('process-btn');
            const selectedFileText = document.getElementById('selected-file');
            const errorMessage = document.getElementById('error-message');
            const formSection = document.getElementById('form-section');
            const loadingSection = document.getElementById('loading-section');
            const resultSection = document.getElementById('result-section');
            const statusMessage = document.getElementById('status-message');
            const failedAccounts = document.getElementById('failed-accounts');
            const downloadTxt = document.getElementById('download-txt');
            const downloadJson = document.getElementById('download-json');
            const downloadXlsx = document.getElementById('download-xlsx');
            const resetBtn = document.getElementById('reset-btn');
            const viewReportBtn = document.getElementById('view-report-btn');
            const overwriteModal = document.getElementById('overwrite-modal');
            const dupReportType = document.getElementById('dup-report-type');
            const dupControlNumber = document.getElementById('dup-control-number');
            const dupUserId = document.getElementById('dup-user-id');
            const dupMobileNumber = document.getElementById('dup-mobile-number');
            const cancelOverwrite = document.getElementById('cancel-overwrite');
            const confirmOverwrite = document.getElementById('confirm-overwrite');
            const mobileStatus = document.getElementById('mobile-status');
            const customerNameInput = document.getElementById('customer-name');

            let selectedFile = null;
            let token = null;
            let lastReportId = null;
            let pendingOverwrite = null;
            let mobileLookupOk = false;
            let mobileLookupTimer = null;

            const setError = (message) => {
                errorMessage.textContent = message;
                errorMessage.classList.remove('hidden');
            };

            const clearError = () => {
                errorMessage.textContent = '';
                errorMessage.classList.add('hidden');
            };

            const setMobileStatus = (message, isError = false) => {
                if (!mobileStatus) {
                    return;
                }
                mobileStatus.textContent = message;
                mobileStatus.classList.remove('hidden');
                mobileStatus.classList.toggle('text-red-600', isError);
                mobileStatus.classList.toggle('text-slate-500', !isError);
            };

            const clearMobileStatus = () => {
                if (!mobileStatus) {
                    return;
                }
                mobileStatus.textContent = '';
                mobileStatus.classList.add('hidden');
                mobileStatus.classList.remove('text-red-600');
                mobileStatus.classList.add('text-slate-500');
            };

            const setUserIdLocked = (locked) => {
                userIdInput.readOnly = true;
                userIdInput.classList.add('bg-slate-100');
            };

            const setCustomerName = (value) => {
                if (!customerNameInput) {
                    return;
                }
                customerNameInput.value = value || '';
            };

            const setLoading = (isLoading) => {
                if (isLoading) {
                    formSection.classList.add('hidden');
                    loadingSection.classList.remove('hidden');
                } else {
                    loadingSection.classList.add('hidden');
                }
            };

            const resetUi = () => {
                selectedFile = null;
                token = null;
                pendingOverwrite = null;
                lastReportId = null;
                mobileLookupOk = false;
                fileInput.value = '';
                selectedFileText.classList.add('hidden');
                selectedFileText.textContent = '';
                setUserIdLocked(false);
                formSection.classList.remove('hidden');
                loadingSection.classList.add('hidden');
                resultSection.classList.add('hidden');
                failedAccounts.classList.add('hidden');
                if (viewReportBtn) {
                    viewReportBtn.classList.add('hidden');
                    viewReportBtn.href = '#';
                }
                clearMobileStatus();
                clearError();
            };

            const showOverwriteModal = (payload) => {
                dupReportType.textContent = payload.report_type || '-';
                dupControlNumber.textContent = payload.control_number || '-';
                dupUserId.textContent = payload.user_id || '-';
                dupMobileNumber.textContent = payload.mobile_number || '-';
                overwriteModal.classList.remove('hidden');
                overwriteModal.classList.add('flex');
            };

            const hideOverwriteModal = () => {
                overwriteModal.classList.add('hidden');
                overwriteModal.classList.remove('flex');
            };

            const updateDownloads = (tokenValue) => {
                if (downloadTxt) {
                    downloadTxt.href = `${apiBaseUrl}/process/${tokenValue}/text`;
                }
                if (downloadJson) {
                    downloadJson.href = `${apiBaseUrl}/process/${tokenValue}/json`;
                }
                if (downloadXlsx) {
                    downloadXlsx.href = `${apiBaseUrl}/process/${tokenValue}/xlsx`;
                }
            };

            const handleFile = (file) => {
                if (!file || file.type !== 'application/pdf') {
                    setError('Please upload a valid PDF file.');
                    return;
                }
                selectedFile = file;
                selectedFileText.textContent = `Selected: ${file.name}`;
                selectedFileText.classList.remove('hidden');
                clearError();
            };

            const lookupMobile = async (mobileNumber) => {
                if (!mobileNumber) {
                    mobileLookupOk = false;
                    setUserIdLocked(false);
                    return;
                }
                setMobileStatus('Checking mobile number...');
                try {
                    const response = await fetch(`${apiBaseUrl}/api/mobile-lookup`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ mobile_number: mobileNumber }),
                    });

                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        mobileLookupOk = false;
                        setUserIdLocked(false);
                        setMobileStatus(data.message || 'User does not exist', true);
                        return;
                    }

                    if (data.user_id) {
                        userIdInput.value = data.user_id;
                        setUserIdLocked(true);
                        mobileLookupOk = true;
                        setCustomerName(data.customer_name || '');
                        setMobileStatus('User found. User ID populated.');
                        return;
                    }

                    mobileLookupOk = false;
                    setUserIdLocked(false);
                    setMobileStatus('User does not exist', true);
                } catch (error) {
                    mobileLookupOk = false;
                    setUserIdLocked(false);
                    setMobileStatus('Unable to verify mobile number. Try again.', true);
                }
            };

            dropzone.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', (e) => handleFile(e.target.files[0]));
            dropzone.addEventListener('dragover', (e) => e.preventDefault());
            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                handleFile(e.dataTransfer.files[0]);
            });

            mobileNumberInput.addEventListener('input', () => {
                const value = mobileNumberInput.value.trim();
                mobileLookupOk = false;
                setUserIdLocked(false);
                clearMobileStatus();
                setCustomerName('');
                if (mobileLookupTimer) {
                    clearTimeout(mobileLookupTimer);
                }
                if (!value) {
                    return;
                }
                mobileLookupTimer = setTimeout(() => lookupMobile(value), 500);
            });

            const submitProcess = async (overwrite = false) => {
                const formData = new FormData();
                if (userIdInput.value.trim()) {
                    formData.append('user_id', userIdInput.value.trim());
                }
                if (mobileNumberInput.value.trim()) {
                    formData.append('mobile_number', mobileNumberInput.value.trim());
                }
                if (reportTypeInput.value.trim()) {
                    formData.append('report_type', reportTypeInput.value.trim());
                }
                formData.append('password', passwordInput.value.trim());
                formData.append('pdf', selectedFile);
                if (overwrite) {
                    formData.append('overwrite', 'true');
                }

                const response = await fetch(`${apiBaseUrl}/api/process`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });

                if (response.status === 409) {
                    const data = await response.json();
                    pendingOverwrite = data;
                    showOverwriteModal(data);
                    return null;
                }

                if (!response.ok) {
                    const errorText = await response.text();
                    let message = 'Processing failed.';
                    try {
                        const errorData = JSON.parse(errorText);
                        message = errorData.message || message;
                    } catch (e) {
                        if (errorText.trim()) {
                            message = errorText.slice(0, 300);
                        }
                    }
                    throw new Error(message);
                }

                return await response.json();
            };

            processBtn.addEventListener('click', async () => {
                clearError();
                const hasUserId = userIdInput.value.trim().length > 0;
                const hasMobile = mobileNumberInput.value.trim().length > 0;
                if (!hasUserId && !hasMobile) {
                    setError('Please enter a User ID or Mobile Number before uploading a file.');
                    return;
                }
                if (hasMobile && !mobileLookupOk) {
                    setError('User does not exist. Please enter a valid mobile number.');
                    return;
                }
                if (!selectedFile) {
                    setError('Please select a PDF file to process.');
                    return;
                }

                setLoading(true);
                statusMessage.textContent = 'Processing... please wait.';

                try {
                    const data = await submitProcess(false);
                    if (!data) {
                        loadingSection.classList.add('hidden');
                        return;
                    }
                    token = data.token;
                    lastReportId = data.storage?.report_id || null;
                    updateDownloads(token);
                    if (viewReportBtn && lastReportId) {
                        viewReportBtn.href = `${apiBaseUrl}/reports/${lastReportId}`;
                        viewReportBtn.classList.remove('hidden');
                    }
                    resultSection.classList.remove('hidden');
                } catch (error) {
                    setError(error.message || 'An unknown error occurred.');
                    formSection.classList.remove('hidden');
                } finally {
                    loadingSection.classList.add('hidden');
                }
            });

            cancelOverwrite.addEventListener('click', () => {
                hideOverwriteModal();
                pendingOverwrite = null;
                formSection.classList.remove('hidden');
            });

            confirmOverwrite.addEventListener('click', async () => {
                hideOverwriteModal();
                if (!selectedFile) {
                    setError('Please select a PDF file to process.');
                    return;
                }
                setLoading(true);
                statusMessage.textContent = 'Overwriting existing report...';
                try {
                    const data = await submitProcess(true);
                    if (!data) {
                        return;
                    }
                    token = data.token;
                    lastReportId = data.storage?.report_id || null;
                    updateDownloads(token);
                    if (viewReportBtn && lastReportId) {
                        viewReportBtn.href = `${apiBaseUrl}/reports/${lastReportId}`;
                        viewReportBtn.classList.remove('hidden');
                    }
                    resultSection.classList.remove('hidden');
                } catch (error) {
                    setError(error.message || 'An unknown error occurred.');
                    formSection.classList.remove('hidden');
                } finally {
                    loadingSection.classList.add('hidden');
                }
            });

            resetBtn.addEventListener('click', resetUi);
        </script>
    </body>
</html>
