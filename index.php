<?php
// Change to the directory where the git repo is located
$repo_dir = '/var/www/poeticoasis.com';  // Change this to the directory of your repository

// Execute git commands as www-data
chdir($repo_dir);

// Fetch latest changes from the remote repository
shell_exec('git fetch origin 2>&1');

// Get the local HEAD commit hash
$local_commit = trim(shell_exec('git rev-parse HEAD'));

// Get the remote HEAD commit hash
$remote_commit = trim(shell_exec('git rev-parse origin/main'));

// Compare local and remote commits
if ($local_commit !== $remote_commit) {
    // If the commits don't match, pull the latest changes
    $update_output = shell_exec('git pull origin main 2>&1');
    echo "<pre>Repository updated:\n$update_output</pre>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Existing head content -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Encryption</title>
    <!-- Include Google Fonts -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Global Styles */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f7f9;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        h1, h2 {
            color: #2c3e50;
            text-align: center;
        }
        h1 {
            margin-bottom: 40px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        textarea, input[type="text"], select {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ccd0d5;
            border-radius: 4px;
            font-size: 16px;
            resize: none; /* Disable manual resizing */
            overflow: hidden; /* Disable scroll bars */
            box-sizing: border-box;
        }
        textarea:focus, input[type="text"]:focus, select:focus {
            border-color: #3498db;
            outline: none;
        }
        h3 {
            margin-top: 25px;
            margin-bottom: 10px;
            color: #2c3e50;
            font-weight: 500;
        }
        #qrcodeCanvas {
            display: block;
            margin: 20px auto;
            max-width: 100%; /* Ensure it doesn't exceed the screen width */
            height: auto;   /* Maintain aspect ratio */
        }
        .link {
            margin-top: 10px;
            font-size: 1em;
            font-weight: 500;
            color: #3498db;
            text-decoration: none;
            display: inline-block; /* or block */
            text-align: center;
            width: 100%; /* Ensure the link takes the full width */
        }
        .link:hover {
            text-decoration: underline; /* Optional: Add underline on hover */
            color: #2980b9; /* Change the color on hover */
        }
        footer {
            text-align: center;
            padding: 20px;
            margin-top: 40px;
            color: #95a5a6;
        }
        /* Added styles for data info */
        #dataInfo {
            margin-top: 10px;
            font-size: 1em;
            color: #2c3e50;
        }
        /* Center the buttons */
        .center-button {
            display: block;
            margin: 10px auto;
            width: auto;
            padding: 12px;
            background-color: #3498db;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .center-button:hover {
            background-color: #2980b9;
        }
        .center-button:active {
            background-color: #1f6391;
        }
        /* Error message styling */
        #qrErrorMessage {
            color: red;
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
        }
        /* Trimming Overlay Styles */
        #trimmingOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        #trimmingOverlay .animation-container {
            text-align: center;
            color: #fff;
        }
        /* Spinner Animation */
        .spinner {
            margin: 0 auto 20px auto;
            width: 80px;
            height: 80px;
            border: 10px solid #f3f3f3;
            border-top: 10px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* Byte counter styling */
        #byteInfo {
            margin-top: 10px;
            font-size: 1.2em;
        }
    </style>
</head>
<body>

<div class="container">

    <div class="section">
        <h1>QR & Data Encryption</h1>
        <textarea id="inputTextEncrypt" placeholder="Enter Data to Encrypt (Text, Emojis, Symbols, etc.)"></textarea>
        <input type="text" id="keyEncrypt" placeholder="Enter Key"/>

        <!-- New Error Correction Level Selection -->
        <select id="errorCorrectionLevel">
            <option value="L">Low (L) - 7% error correction</option>
            <option value="M" selected>Medium (M) - 15% error correction</option>
            <option value="Q">Quartile (Q) - 25% error correction</option>
            <option value="H">High (H) - 30% error correction</option>
        </select>

        <!-- Encrypted Output Section -->
        <div id="encryptedOutputSection" style="display:none;">
            <h3>Base64 (Raw Data):</h3>
            <textarea id="encryptedOutput" readonly onclick="this.select()"></textarea>
            <!-- New section for data size and QR code capacity -->
            <div id="dataInfo">
                <p style="display:none;">Data Size: <span id="dataSize"></span> bytes</p>
                <p>QR Code Capacity: <span id="qrCapacity"></span> bytes</p>
                <p>Used: <span id="dataUsed"></span> / <span id="qrCapacityCopy"></span> bytes</p>
            </div>
            <!-- Error message for data too big -->
            <div id="qrErrorMessage" style="display:none;">
                Data is too big to encode in a QR code.
            </div>
            <!-- Trim Button -->
            <button id="trimButton" class="center-button" style="display:none;">Automatically Trim Data to Fit</button>
        </div>

        <!-- QR Code Display Section -->
        <div id="qrCodeSection" style="display:none;">
            <h3>QR Code of Data:</h3>
            <canvas id="qrcodeCanvas"></canvas>
            <img id="qrcodeImage" style="display:none; margin: 20px auto; max-width: 100%; height: auto;" alt="QR Code Image"/>
            <button id="downloadQrBtn" class="center-button" style="display:none;">Download QR Code</button>
        </div>

        <!-- Decryption Link Section -->
        <div id="decryptionLinkSection" style="display:none;">
            <a id="decryptionLink" class="link" href="#" target="_blank">Decryption Link</a>
        </div>
    </div>
</div>

<!-- Trimming Overlay -->
<div id="trimmingOverlay">
    <div class="animation-container">
        <div class="spinner"></div>
        <p>Trimming data to fit...</p>
        <div id="byteInfo">
            Current Size: <span id="currentSize">0</span> bytes<br>
            Target Size: <span id="targetSize">Variable</span> bytes
        </div>
    </div>
</div>

<footer>
    &copy; 2024 Kyle B. All rights reserved.
</footer>

<!-- Include the lz-string.js library -->
<script src="https://cdn.jsdelivr.net/npm/lz-string@1.4.4/libs/lz-string.min.js"></script>
<!-- Include the QR Code generator library -->
<script src="qrcodegen.js"></script>

<script>
    // Utility function to convert Uint8Array to Base64 string
    function uint8ArrayToBase64(bytes) {
        let binary = '';
        const len = bytes.byteLength;
        for (let i = 0; i < len; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    // Function to auto-resize textarea and input boxes based on content
    function autoResizeBox(box) {
        box.style.height = 'auto';
        box.style.height = box.scrollHeight + 'px';
    }

    // Apply auto-resize to relevant textareas and inputs on content change
    function applyAutoResize(box) {
        box.addEventListener('input', () => autoResizeBox(box));
        // Initial resize to fit any pre-filled content
        autoResizeBox(box);
    }

    // Derive AES-256 key from key using PBKDF2
    async function deriveKey(key, salt) {
        const encoder = new TextEncoder();
        const keyData = encoder.encode(key);

        const keyMaterial = await crypto.subtle.importKey(
            'raw',
            keyData,
            { name: 'PBKDF2' },
            false,
            ['deriveKey']
        );

        return crypto.subtle.deriveKey(
            {
                name: 'PBKDF2',
                salt: salt,
                iterations: 100000,
                hash: 'SHA-256'
            },
            keyMaterial,
            { name: 'AES-GCM', length: 256 },
            false,
            ['encrypt']
        );
    }

    // Encrypt data using AES-256 with a random IV
    async function encryptData(key, data) {
        const iv = crypto.getRandomValues(new Uint8Array(12)); // AES-GCM standard IV length is 12 bytes
        const encryptedData = await crypto.subtle.encrypt(
            { name: 'AES-GCM', iv: iv },
            key,
            data
        );
        return { encryptedData: new Uint8Array(encryptedData), iv };
    }

    // Generate QR Code from data
    function generateQRCode(data, eccLevel = 'M', suppressUI = false) {
        const canvas = document.getElementById('qrcodeCanvas');
        let qr;
        const errorCorrectionLevels = {
            'L': qrcodegen.QrCode.Ecc.LOW,
            'M': qrcodegen.QrCode.Ecc.MEDIUM,
            'Q': qrcodegen.QrCode.Ecc.QUARTILE,
            'H': qrcodegen.QrCode.Ecc.HIGH
        };

        try {
            qr = qrcodegen.QrCode.encodeText(
                data,
                errorCorrectionLevels[eccLevel],
                qrcodegen.QrCode.MIN_VERSION,
                qrcodegen.QrCode.MAX_VERSION,
                -1,
                true
            );
        } catch (e) {
            // Data is too big for QR code
            return null;
        }

        if (!suppressUI) {
            const scale = 4;
            const border = 4;
            const size = (qr.size + border * 2) * scale;
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, size, size);

            for (let y = 0; y < qr.size; y++) {
                for (let x = 0; x < qr.size; x++) {
                    if (qr.getModule(x, y)) {
                        ctx.fillStyle = '#000000';
                        ctx.fillRect((x + border) * scale, (y + border) * scale, scale, scale);
                    }
                }
            }

            convertCanvasToImage();
        }

        return qr.version;
    }

    // Convert canvas to image and make the download link available
    function convertCanvasToImage() {
        const canvas = document.getElementById('qrcodeCanvas');
        const img = document.getElementById('qrcodeImage');
        const downloadBtn = document.getElementById('downloadQrBtn');

        // Convert canvas to a data URL (base64 encoded image)
        const dataUrl = canvas.toDataURL('image/png');

        // Set the img src to the canvas data and show the image element
        img.src = dataUrl;
        img.style.display = 'block'; // Show the image
        canvas.style.display = 'none'; // Hide the canvas

        // Show the download button and link it to the data URL
        downloadBtn.style.display = 'block';
        downloadBtn.onclick = function() {
            // Create a temporary download link
            const link = document.createElement('a');
            link.href = dataUrl;
            link.download = 'qr_matrix.png'; // Set the default filename
            link.click(); // Simulate a click to trigger download
        };
    }

    // Function to update encryption output whenever input changes
    async function updateEncryption() {
        const textToEncrypt = document.getElementById('inputTextEncrypt').value;
        const key = document.getElementById('keyEncrypt').value;
        const eccLevel = document.getElementById('errorCorrectionLevel').value;

        // Hide everything if either input or key is empty
        if (!textToEncrypt || !key) {
            document.getElementById('encryptedOutput').value = '';
            document.getElementById('encryptedOutputSection').style.display = 'none';

            document.getElementById('decryptionLink').href = '#';
            document.getElementById('decryptionLink').textContent = '';
            document.getElementById('decryptionLinkSection').style.display = 'none';

            document.getElementById('dataSize').textContent = '';
            document.getElementById('qrCapacity').textContent = '';
            document.getElementById('qrCapacityCopy').textContent = '';
            document.getElementById('dataUsed').textContent = '';
            document.getElementById('qrErrorMessage').style.display = 'none';
            document.getElementById('trimButton').style.display = 'none';

            document.getElementById('qrcodeImage').src = '';
            document.getElementById('qrCodeSection').style.display = 'none';

            // Clear the QR code canvas
            const canvas = document.getElementById('qrcodeCanvas');
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            return;
        }

        try {
            let dataToEncrypt;
            let compressionFlag;

            if (textToEncrypt.length > 20) {  // Compress only if the text is longer than 20 characters
                dataToEncrypt = LZString.compressToUint8Array(textToEncrypt);
                compressionFlag = 1; // Compression applied
            } else {
                const encoder = new TextEncoder();
                dataToEncrypt = encoder.encode(textToEncrypt);
                compressionFlag = 0; // No compression
            }

            // Generate a random salt
            const salt = crypto.getRandomValues(new Uint8Array(16));

            // Derive AES-256 Key from the provided key
            const encryptionKey = await deriveKey(key, salt);

            // Encrypt the data
            const { encryptedData, iv } = await encryptData(encryptionKey, dataToEncrypt);

            // Prepare the combined data
            // Structure: [compressionFlag][salt][iv][encryptedData]
            const combinedData = new Uint8Array(1 + salt.byteLength + iv.byteLength + encryptedData.byteLength);
            combinedData[0] = compressionFlag;
            combinedData.set(salt, 1);
            combinedData.set(iv, 17); // 1 (flag) + 16 (salt)
            combinedData.set(encryptedData, 29); // 1 (flag) + 16 (salt) + 12 (iv)

            const base64EncryptedData = uint8ArrayToBase64(combinedData);

            // Display Base64-encoded encrypted data
            document.getElementById('encryptedOutput').value = base64EncryptedData;
            document.getElementById('encryptedOutputSection').style.display = 'block';
            autoResizeBox(document.getElementById('encryptedOutput'));

            // Generate Decryption Link
            const urlEncodedData = encodeURIComponent(base64EncryptedData);
            const decryptionLink = `${window.location.origin}/decrypt?data=${urlEncodedData}`;

            // Compute data size in bytes for the decryption link
            const encoder = new TextEncoder();
            const dataBytes = encoder.encode(decryptionLink);
            const dataSize = dataBytes.length;

            // Try to generate and display the QR code with the decryption link
            const version = generateQRCode(decryptionLink, eccLevel);

            if (version === null) {
                // If QR code generation failed
                document.getElementById('qrErrorMessage').style.display = 'block';
                document.getElementById('qrCodeSection').style.display = 'none';
                document.getElementById('downloadQrBtn').style.display = 'none';
                document.getElementById('trimButton').style.display = 'block'; // Show Trim button
                document.getElementById('decryptionLinkSection').style.display = 'none';
            } else {
                document.getElementById('qrCodeSection').style.display = 'block';
                document.getElementById('qrErrorMessage').style.display = 'none';
                document.getElementById('trimButton').style.display = 'none'; // Hide Trim button if data fits

                // Update decryption link
                document.getElementById('decryptionLink').href = decryptionLink;
                document.getElementById('decryptionLink').textContent = 'Decryption Link';
                document.getElementById('decryptionLinkSection').style.display = 'block';

                // Update QR Code Capacity and Data Used
                const qrCapacity = getQrCodeCapacity(version, eccLevel);
                document.getElementById('qrCapacity').textContent = qrCapacity;
                document.getElementById('qrCapacityCopy').textContent = qrCapacity; // Update the copy
                document.getElementById('dataUsed').textContent = dataSize;
            }

        } catch (error) {
            console.error('Encryption Error:', error);
            alert('An error occurred during encryption.');
        }
    }

    // Function to get QR code capacity based on version and error correction level
    function getQrCodeCapacity(version, eccLevel) {
        // Maximum data capacity for byte mode based on error correction level
        const capacityTable = {
            'L': {
                1: 17,   2: 32,   3: 53,   4: 78,   5: 106,
                6: 134,  7: 154,  8: 192,  9: 230, 10: 271,
                11: 321, 12: 367, 13: 425, 14: 458, 15: 520,
                16: 586, 17: 644, 18: 718, 19: 792, 20: 858,
                21: 929, 22: 1003,23: 1091,24: 1171,25: 1273,
                26: 1367,27: 1465,28: 1528,29: 1628,30: 1732,
                31: 1840,32: 1952,33: 2068,34: 2188,35: 2303,
                36: 2431,37: 2563,38: 2699,39: 2809,40: 2953
            },
            'M': {
                1: 14,   2: 26,   3: 42,   4: 62,   5: 84,
                6: 106,  7: 122,  8: 152,  9: 180, 10: 213,
                11: 251, 12: 287, 13: 331, 14: 362, 15: 412,
                16: 450, 17: 504, 18: 560, 19: 624, 20: 666,
                21: 711, 22: 779, 23: 857, 24: 911, 25: 997,
                26: 1059,27: 1125,28: 1190,29: 1264,30: 1370,
                31: 1452,32: 1538,33: 1628,34: 1722,35: 1809,
                36: 1911,37: 1989,38: 2099,39: 2213,40: 2331
            },
            'Q': {
                1: 11,   2: 20,   3: 32,   4: 46,   5: 60,
                6: 74,   7: 86,   8: 108,  9: 130, 10: 151,
                11: 177, 12: 203, 13: 241, 14: 258, 15: 292,
                16: 322, 17: 364, 18: 394, 19: 442, 20: 482,
                21: 509, 22: 565, 23: 611, 24: 661, 25: 715,
                26: 751, 27: 805, 28: 868, 29: 908, 30: 982,
                31: 1030,32: 1112,33: 1168,34: 1228,35: 1283,
                36: 1351,37: 1423,38: 1499,39: 1579,40: 1663
            },
            'H': {
                1: 7,    2: 14,   3: 24,   4: 34,   5: 44,
                6: 58,   7: 64,   8: 84,   9: 98,   10: 119,
                11: 137, 12: 155, 13: 177, 14: 194, 15: 220,
                16: 250, 17: 280, 18: 310, 19: 338, 20: 382,
                21: 403, 22: 439, 23: 461, 24: 511, 25: 535,
                26: 593, 27: 625, 28: 658, 29: 698, 30: 742,
                31: 790, 32: 842, 33: 898, 34: 958, 35: 983,
                36: 1051,37: 1093,38: 1139,39: 1219,40: 1273
            }
        };
        return capacityTable[eccLevel][version] || 'Unknown';
    }

    // Function to trim data to fit into QR code
    async function trimDataToFit() {
        let originalText = document.getElementById('inputTextEncrypt').value;
        const key = document.getElementById('keyEncrypt').value;
        const eccLevel = document.getElementById('errorCorrectionLevel').value;

        if (!originalText || !key) {
            return;
        }

        // Show the trimming overlay animation
        document.getElementById('trimmingOverlay').style.display = 'flex';

        // Set target size (will be updated dynamically)
        document.getElementById('targetSize').textContent = 'Variable';
        document.getElementById('currentSize').textContent = 'Calculating...';

        // Allow UI to update before starting the trimming loop
        await new Promise(resolve => setTimeout(resolve, 50));

        // Initialize variables
        let maxLength = originalText.length;
        let minLength = 0;
        let bestFitText = '';
        let bestLength = 0;

        // Set a limit for the number of iterations to prevent infinite loops
        const maxIterations = 100;
        let iteration = 0;

        while (minLength <= maxLength && iteration < maxIterations) {
            iteration++;

            let mid = Math.floor((minLength + maxLength) / 2);
            let testText = originalText.substring(0, mid);

            // Test encryption and QR code generation without updating the UI
            let result = await testEncryption(testText, key, eccLevel);

            // Update current size in overlay
            document.getElementById('currentSize').textContent = result.dataSize;

            if (result.qrCodeSuccess) {
                // Data fits and QR code generation succeeded
                bestFitText = testText;
                bestLength = mid;
                minLength = mid + 1; // Try to include more characters
            } else {
                // Data doesn't fit or QR code generation failed
                maxLength = mid - 1; // Try with fewer characters
            }

            // Small delay to allow UI to update (optional)
            await new Promise(resolve => setTimeout(resolve, 10));
        }

        if (bestFitText !== '') {
            // Update the input field with the best fit text
            document.getElementById('inputTextEncrypt').value = bestFitText;
            autoResizeBox(document.getElementById('inputTextEncrypt'));

            // Update the encryption and UI with the best fit text
            await updateEncryption();
        } else {
            // No valid QR code could be generated even with empty input
            document.getElementById('inputTextEncrypt').value = '';
            autoResizeBox(document.getElementById('inputTextEncrypt'));

            // Update encryption with empty input
            await updateEncryption();
        }

        // Hide the trimming overlay animation
        document.getElementById('trimmingOverlay').style.display = 'none';
    }

    // Function to test encryption and QR code generation without updating the UI
    async function testEncryption(textToEncrypt, key, eccLevel) {
        try {
            let dataToEncrypt;
            let compressionFlag;

            if (textToEncrypt.length > 20) {  // Compress only if the text is longer than 20 characters
                dataToEncrypt = LZString.compressToUint8Array(textToEncrypt);
                compressionFlag = 1; // Compression applied
            } else {
                const encoder = new TextEncoder();
                dataToEncrypt = encoder.encode(textToEncrypt);
                compressionFlag = 0; // No compression
            }

            // Generate a random salt
            const salt = crypto.getRandomValues(new Uint8Array(16));

            // Derive AES-256 Key from the provided key
            const encryptionKey = await deriveKey(key, salt);

            // Encrypt the data
            const { encryptedData, iv } = await encryptData(encryptionKey, dataToEncrypt);

            // Prepare the combined data
            // Structure: [compressionFlag][salt][iv][encryptedData]
            const combinedData = new Uint8Array(1 + salt.byteLength + iv.byteLength + encryptedData.byteLength);
            combinedData[0] = compressionFlag;
            combinedData.set(salt, 1);
            combinedData.set(iv, 17); // 1 (flag) + 16 (salt)
            combinedData.set(encryptedData, 29); // 1 (flag) + 16 (salt) + 12 (iv)

            const base64EncryptedData = uint8ArrayToBase64(combinedData);

            // Generate Decryption Link
            const urlEncodedData = encodeURIComponent(base64EncryptedData);
            const decryptionLink = `${window.location.origin}/decrypt?data=${urlEncodedData}`;

            // Compute data size in bytes for the decryption link
            const encoder = new TextEncoder();
            const dataBytes = encoder.encode(decryptionLink);
            const dataSize = dataBytes.length;

            // Try to generate QR code without updating the UI
            let qrCodeSuccess = false;
            const qrVersion = generateQRCode(decryptionLink, eccLevel, true); // Suppress UI updates
            if (qrVersion !== null) {
                qrCodeSuccess = true;
            }

            return {
                dataSize: dataSize,
                qrCodeSuccess: qrCodeSuccess
            };

        } catch (error) {
            return {
                dataSize: 0,
                qrCodeSuccess: false
            };
        }
    }

    // Apply auto-resize to all textareas and input fields
    const inputFields = [
        document.getElementById('inputTextEncrypt'),
        document.getElementById('keyEncrypt'),
        document.getElementById('encryptedOutput')
    ];

    inputFields.forEach(applyAutoResize);

    // Add input event listeners to update encryption live
    document.getElementById('inputTextEncrypt').addEventListener('input', updateEncryption);
    document.getElementById('keyEncrypt').addEventListener('input', updateEncryption);
    document.getElementById('errorCorrectionLevel').addEventListener('change', updateEncryption);

    // Add click event listener to the Trim button
    document.getElementById('trimButton').addEventListener('click', trimDataToFit);
</script>

</body>
</html>
