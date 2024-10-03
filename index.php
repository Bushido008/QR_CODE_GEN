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
        /* Include your existing styles */
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
        button {
            width: 100%;
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
        button:hover {
            background-color: #2980b9;
        }
        button:active {
            background-color: #1f6391;
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
        footer {
            text-align: center;
            padding: 20px;
            margin-top: 40px;
            color: #95a5a6;
        }
    </style>
</head>
<body>

<div class="container">

    <div class="section">
        <h1>QR & Data Encryption</h1>
        <textarea id="inputTextEncrypt" placeholder="Enter Data to Encrypt"></textarea>
        <input type="text" id="keyEncrypt" placeholder="Enter Key"/>
        <button id="compressEncryptBtn">Encrypt</button>

        <!-- Encrypted Output Section -->
        <div id="encryptedOutputSection" style="display:none;">
            <h3>Base64 (Raw Data):</h3>
            <textarea id="encryptedOutput" readonly onclick="this.select()"></textarea>
        </div>

        <!-- QR Code Display Section -->
        <div id="qrCodeSection" style="display:none;">
            <h3>QR Code of Data:</h3>
            <canvas id="qrcodeCanvas"></canvas>
            <img id="qrcodeImage" style="display:none; margin: 20px auto; max-width: 100%; height: auto;" alt="QR Code Image"/>
            <button id="downloadQrBtn" class="button" style="display:none;">Download QR Code</button>
        </div>

        <!-- Link to Decryption Page -->
        <div id="decryptionLinkSection" style="display:none;">
            <h3>Decryption Link:</h3>
            <a id="decryptionLink" href="#" target="_blank"></a>
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
        const qr = qrcodegen.QrCode.encodeText(data, qrcodegen.QrCode.Ecc.MEDIUM);
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

        // Show the QR code section
        document.getElementById('qrCodeSection').style.display = 'block';

        // After drawing, hide the canvas and show the image
        convertCanvasToImage();
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
        downloadBtn.addEventListener('click', function() {
            // Create a temporary download link
            const link = document.createElement('a');
            link.href = dataUrl;
            link.download = 'qr_matrix.png'; // Set the default filename
            link.click(); // Simulate a click to trigger download
        });
    }

    document.getElementById('compressEncryptBtn').addEventListener('click', async () => {
        const textToEncrypt = document.getElementById('inputTextEncrypt').value;
        const key = document.getElementById('keyEncrypt').value;

        if (!textToEncrypt && !key) {
            // Both fields are empty, clear all outputs and hide sections
            document.getElementById('encryptedOutput').value = '';
            document.getElementById('encryptedOutputSection').style.display = 'none';

            document.getElementById('decryptionLink').href = '#';
            document.getElementById('decryptionLink').textContent = '';
            document.getElementById('decryptionLinkSection').style.display = 'none';

            document.getElementById('qrcodeImage').src = '';
            document.getElementById('qrCodeSection').style.display = 'none';

            // Also clear the QR code canvas
            const canvas = document.getElementById('qrcodeCanvas');
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            return;
        }

        if (!textToEncrypt || !key) {
            alert('Please enter both text and key.');
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
            document.getElementById('decryptionLink').href = decryptionLink;
            document.getElementById('decryptionLink').textContent = decryptionLink;
            document.getElementById('decryptionLinkSection').style.display = 'block';

            // Generate and display the QR code with the decryption link
            generateQRCode(decryptionLink);

        } catch (error) {
            console.error('Encryption Error:', error);
            alert('An error occurred during encryption.');
        }
    });

    // Apply auto-resize to all textareas and input fields
    const inputFields = [
        document.getElementById('inputTextEncrypt'),
        document.getElementById('keyEncrypt'),
        document.getElementById('encryptedOutput')
    ];

    inputFields.forEach(applyAutoResize);
</script>

</body>
</html>
