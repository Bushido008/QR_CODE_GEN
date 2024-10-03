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
    <!-- Include your existing head content -->
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
        textarea, input[type="text"] {
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
        textarea:focus, input[type="text"]:focus {
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
    </style>
</head>
<body>

<div class="container">

    <div class="section">
        <h1>QR & Data Encryption</h1>
        <textarea id="inputTextEncrypt" placeholder="Enter Data to Encrypt (Text, Emojis, Symbols, etc.)"></textarea>
        <input type="text" id="keyEncrypt" placeholder="Enter Key"/>

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
            <button id="trimButton" class="center-button" style="display:none;">Trim Data to Fit</button>
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
    function generateQRCode(data) {
        const canvas = document.getElementById('qrcodeCanvas');
        let qr;
        try {
            qr = qrcodegen.QrCode.encodeText(data, qrcodegen.QrCode.Ecc.MEDIUM, qrcodegen.QrCode.MIN_VERSION, qrcodegen.QrCode.MAX_VERSION, -1, true);
        } catch (e) {
            // Data is too big for QR code
            return null;
        }

        const scale = 4; // Adjust the scale (size of each module)
        const border = 4; // Adjust the border size

        // Calculate the size of the QR code
        const size = (qr.size + border * 2) * scale;
        canvas.width = size;
        canvas.height = size;

        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, size, size);

        // Draw the QR code modules
        for (let y = 0; y < qr.size; y++) {
            for (let x = 0; x < qr.size; x++) {
                if (qr.getModule(x, y)) {
                    ctx.fillStyle = '#000000';
                    ctx.fillRect((x + border) * scale, (y + border) * scale, scale, scale);
                }
            }
        }

        // After drawing, hide the canvas and show the image
        convertCanvasToImage();

        return qr.version; // Return QR code version
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

            // QR Code capacity in bytes for version 40, error correction level M
            const qrCapacity = 2331; // Maximum capacity in bytes for byte mode

            // Update the data size and capacity display
            document.getElementById('dataSize').textContent = dataSize;
            document.getElementById('qrCapacity').textContent = qrCapacity;
            document.getElementById('qrCapacityCopy').textContent = qrCapacity; // Update the copy
            document.getElementById('dataUsed').textContent = dataSize;

            if (dataSize > qrCapacity) {
                // Data is too big for QR code
                document.getElementById('qrErrorMessage').style.display = 'block';
                document.getElementById('decryptionLinkSection').style.display = 'none';
                document.getElementById('qrCodeSection').style.display = 'none';
                document.getElementById('downloadQrBtn').style.display = 'none';
                document.getElementById('trimButton').style.display = 'block'; // Show Trim button
                return;
            } else {
                document.getElementById('qrErrorMessage').style.display = 'none';
                document.getElementById('trimButton').style.display = 'none'; // Hide Trim button if data fits
            }

            // Update decryption link
            document.getElementById('decryptionLink').href = decryptionLink;
            document.getElementById('decryptionLink').textContent = 'Decryption Link';
            document.getElementById('decryptionLinkSection').style.display = 'block';

            // Generate and display the QR code with the decryption link
            const version = generateQRCode(decryptionLink);

            if (version === null) {
                // If QR code generation failed
                document.getElementById('qrErrorMessage').style.display = 'block';
                document.getElementById('qrCodeSection').style.display = 'none';
                document.getElementById('downloadQrBtn').style.display = 'none';
                document.getElementById('trimButton').style.display = 'block'; // Show Trim button
            } else {
                document.getElementById('qrCodeSection').style.display = 'block';
                document.getElementById('qrErrorMessage').style.display = 'none';
                document.getElementById('trimButton').style.display = 'none'; // Hide Trim button if data fits
            }

        } catch (error) {
            console.error('Encryption Error:', error);
            alert('An error occurred during encryption.');
        }
    }

    // Function to trim data to fit into QR code
    async function trimDataToFit() {
        let textToEncrypt = document.getElementById('inputTextEncrypt').value;
        const key = document.getElementById('keyEncrypt').value;

        if (!textToEncrypt || !key) {
            return;
        }

        // Keep trimming one character at a time from the end
        while (textToEncrypt.length > 0) {
            // Remove last character
            textToEncrypt = textToEncrypt.slice(0, -1);

            // Update the input field
            document.getElementById('inputTextEncrypt').value = textToEncrypt;
            autoResizeBox(document.getElementById('inputTextEncrypt'));

            // Recalculate encryption and check if data fits
            await updateEncryption();

            // Check if data now fits into QR code
            const dataSize = parseInt(document.getElementById('dataSize').textContent);
            const qrCapacity = parseInt(document.getElementById('qrCapacity').textContent);

            if (dataSize <= qrCapacity) {
                // Data now fits
                break;
            }
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

    // Add click event listener to the Trim button
    document.getElementById('trimButton').addEventListener('click', trimDataToFit);

</script>

</body>
</html>
