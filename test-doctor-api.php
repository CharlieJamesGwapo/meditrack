<!DOCTYPE html>
<html>
<head>
    <title>Test Doctor API</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        button { background: #10b981; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #059669; }
        pre { background: #f3f4f6; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Test Doctor API</h1>
    <button onclick="testAPI()">Test Create Doctor</button>
    <div id="result"></div>

    <script>
        async function testAPI() {
            const formData = new FormData();
            formData.append('first_name', 'Test');
            formData.append('middle_name', 'API');
            formData.append('last_name', 'Doctor');
            formData.append('username', 'test.api' + Date.now());
            formData.append('email', 'test@example.com');
            formData.append('password', 'test123456');
            formData.append('role', 'doctor');
            formData.append('status', 'active');
            formData.append('department_id', '1');
            formData.append('specialization', 'Test Specialist');
            formData.append('qualification', 'MBBS');
            formData.append('license_number', 'TEST' + Date.now());
            formData.append('consultation_fee', '1000');
            formData.append('experience_years', '5');
            formData.append('phone', '+639123456789');
            formData.append('bio', 'Test doctor');

            try {
                Swal.fire({
                    title: 'Testing API...',
                    html: 'Please wait',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                const response = await fetch('api/doctors/create.php', {
                    method: 'POST',
                    body: formData
                });

                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                const text = await response.text();
                console.log('Raw response:', text);

                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text);
                }

                document.getElementById('result').innerHTML = '<h2>Response:</h2><pre>' + JSON.stringify(result, null, 2) + '</pre>';

                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'API Test Successful!',
                        html: `<pre>${JSON.stringify(result, null, 2)}</pre>`,
                        confirmButtonColor: '#10b981'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'API Test Failed',
                        text: result.message,
                        confirmButtonColor: '#ef4444'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Test Failed',
                    text: error.message,
                    confirmButtonColor: '#ef4444'
                });
            }
        }
    </script>
</body>
</html>
