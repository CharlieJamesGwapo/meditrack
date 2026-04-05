<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Force Refresh - MediTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8 text-orange-700">
            <i class="fas fa-sync mr-3"></i>Force Refresh Patients Page
        </h1>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Clear Browser Cache</h2>
            <p class="text-gray-600 mb-4">The error might be due to browser caching. Try these steps:</p>
            
            <div class="space-y-3">
                <div class="flex items-center space-x-4">
                    <button onclick="clearCache()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-trash mr-2"></i>Clear Cache
                    </button>
                    <button onclick="hardRefresh()" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700">
                        <i class="fas fa-sync mr-2"></i>Hard Refresh
                    </button>
                </div>
                
                <div class="bg-gray-100 p-4 rounded">
                    <h3 class="font-semibold mb-2">Manual Steps:</h3>
                    <ol class="list-decimal list-inside space-y-2 text-sm">
                        <li>Press <kbd>Ctrl + F5</kbd> (Windows/Linux)</li>
                        <li>Press <kbd>Cmd + Shift + R</kbd> (Mac)</li>
                        <li>Open Developer Tools (F12) → Right-click refresh button → "Empty Cache and Hard Reload"</li>
                    </ol>
                </div>
            </div>
            
            <div id="cacheResult" class="mt-4"></div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Open Patients Page</h2>
            <button onclick="openPatients()" class="bg-green-600 text-white px-6 py-3 rounded hover:bg-green-700 text-lg">
                <i class="fas fa-users mr-2"></i>Open Patients Page
            </button>
            <div id="openResult" class="mt-4"></div>
        </div>
    </div>

    <script>
        function clearCache() {
            const resultDiv = document.getElementById('cacheResult');
            resultDiv.innerHTML = '<div class="text-blue-600">Clearing cache...</div>';
            
            // Clear browser cache
            if ('caches' in window) {
                caches.keys().then(function(names) {
                    names.forEach(function(name) {
                        caches.delete(name);
                    });
                });
            }
            
            // Clear localStorage
            localStorage.clear();
            sessionStorage.clear();
            
            resultDiv.innerHTML = '<div class="text-green-600">✓ Cache cleared!</div>';
        }

        function hardRefresh() {
            const resultDiv = document.getElementById('cacheResult');
            resultDiv.innerHTML = '<div class="text-blue-600">Performing hard refresh...</div>';
            
            // Add timestamp to force refresh
            const timestamp = new Date().getTime();
            window.location.href = 'pages/patients.html?t=' + timestamp;
        }

        function openPatients() {
            const resultDiv = document.getElementById('openResult');
            resultDiv.innerHTML = '<div class="text-blue-600">Opening patients page...</div>';
            
            // Open patients page with timestamp to prevent caching
            const timestamp = new Date().getTime();
            window.open('pages/patients.html?t=' + timestamp, '_blank');
            
            setTimeout(() => {
                resultDiv.innerHTML = '<div class="text-green-600">✓ Patients page opened in new tab</div>';
            }, 1000);
        }

        // Auto-check if there's a cache parameter
        window.addEventListener('load', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('t')) {
                document.getElementById('cacheResult').innerHTML = 
                    '<div class="text-green-600">✓ Page refreshed with timestamp: ' + urlParams.get('t') + '</div>';
            }
        });
    </script>
</body>
</html>
