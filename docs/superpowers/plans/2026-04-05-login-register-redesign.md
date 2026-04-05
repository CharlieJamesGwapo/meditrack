# Login & Register Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign MediTrack's login and register pages with a professional split-screen layout and convert the register form into a 4-step wizard.

**Architecture:** Each auth page gets a split-screen layout: dark green branding panel on left, white form panel on right. On mobile (<768px), the left panel collapses to a compact header. The register page adds a 4-step wizard with JavaScript state management. All existing JavaScript functionality (validation, API calls, camera capture, Philippine location database) is preserved.

**Tech Stack:** HTML5, Tailwind CSS 2.2.19 (CDN), Font Awesome 6.0.0 (CDN), SweetAlert2 11 (CDN), vanilla JavaScript, Google Fonts (Inter, Poppins)

---

### Task 1: Redesign Login Page

**Files:**
- Modify: `pages/login.html` (full rewrite of HTML and CSS, preserve all JS logic)

- [ ] **Step 1: Replace the entire login.html with the split-screen layout**

Replace the full contents of `pages/login.html` with the new split-screen design. The new file preserves ALL existing JavaScript functionality (clock, password toggle, password strength, rate limiting, sanitization, login API call, role-based redirect, security measures) but wraps it in the new split-screen HTML/CSS structure.

Key structural changes:
- Body uses a full-viewport flex container instead of centered card
- Left panel (`.left-panel`): dark green gradient with branding, features, decorative circles
- Right panel (`.right-panel`): white background with form
- Mobile (<768px): left panel becomes compact header, form stacks below
- Input fields use new styling: 44px height, `#f9fafb` background, `1.5px` border, `10px` border-radius
- Remove the old centered card layout, security badges at top, and the "New to MediTrack?" divider text
- Keep: real-time clock (moved into right panel), password strength bar, remember me, forgot password link, all form validation, all security JS

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <meta name="referrer" content="no-referrer">
    <title>Secure Login - MediTrack</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/logo.jpg">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/logo.jpg">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/logo.jpg">
    <link rel="manifest" href="../assets/images/site.webmanifest">
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
        }
        
        h1, h2, h3 {
            font-family: 'Poppins', 'Inter', sans-serif;
        }
        
        .split-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Left Panel - Branding */
        .left-panel {
            flex: 0 0 45%;
            background: linear-gradient(135deg, #065f46 0%, #047857 50%, #059669 100%);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .left-panel::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        
        .left-panel::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -40px;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
        }
        
        .logo-box {
            width: 56px;
            height: 56px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            overflow: hidden;
        }
        
        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 14px;
        }
        
        .brand-title {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            line-height: 1.2;
        }
        
        .brand-subtitle {
            font-size: 14px;
            color: rgba(255,255,255,0.8);
            line-height: 1.6;
            margin-bottom: 32px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        
        .feature-icon {
            width: 32px;
            height: 32px;
            background: rgba(52,211,153,0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .feature-icon i {
            color: #34d399;
            font-size: 13px;
        }
        
        .feature-text {
            font-size: 13px;
            color: rgba(255,255,255,0.85);
        }
        
        /* Right Panel - Form */
        .right-panel {
            flex: 1;
            background: #ffffff;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
        }
        
        .form-container {
            width: 100%;
            max-width: 400px;
        }
        
        .form-title {
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }
        
        .form-subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 28px;
        }
        
        .field-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }
        
        .input-field {
            width: 100%;
            height: 44px;
            background: #f9fafb;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            padding: 0 14px 0 42px;
            font-size: 14px;
            color: #1f2937;
            transition: all 0.2s ease;
            outline: none;
        }
        
        .input-field::placeholder {
            color: #9ca3af;
        }
        
        .input-field:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
            background: #ffffff;
        }
        
        .input-wrapper {
            position: relative;
            margin-bottom: 18px;
        }
        
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 14px;
            pointer-events: none;
        }
        
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            background: none;
            border: none;
            padding: 4px;
            font-size: 14px;
            transition: color 0.2s;
        }
        
        .toggle-password:hover {
            color: #10b981;
        }
        
        .strength-bar-container {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }
        
        .password-strength {
            height: 100%;
            border-radius: 2px;
            transition: all 0.3s ease;
            width: 0;
        }
        
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
        
        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .remember-me input {
            width: 16px;
            height: 16px;
            accent-color: #10b981;
            cursor: pointer;
        }
        
        .remember-me label {
            font-size: 13px;
            color: #6b7280;
            cursor: pointer;
        }
        
        .forgot-link {
            font-size: 13px;
            color: #059669;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .forgot-link:hover {
            color: #047857;
        }
        
        .btn-primary {
            width: 100%;
            height: 44px;
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            border: none;
            border-radius: 10px;
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.35);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .divider {
            display: flex;
            align-items: center;
            gap: 14px;
            margin: 22px 0;
        }
        
        .divider-line {
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }
        
        .divider-text {
            font-size: 12px;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-secondary {
            width: 100%;
            height: 44px;
            background: #ffffff;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            color: #374151;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            text-decoration: none;
        }
        
        .btn-secondary:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .btn-secondary span {
            color: #059669;
            font-weight: 600;
        }
        
        .clock-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0fdf4;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid #bbf7d0;
            font-size: 12px;
            font-weight: 600;
            color: #047857;
            margin-bottom: 24px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            display: none;
            animation: slideDown 0.3s ease-out;
        }
        
        .alert.show {
            display: block;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Security notice */
        .security-notice {
            margin-top: 20px;
            padding: 14px;
            background: #f0fdf4;
            border-radius: 10px;
            border: 1px solid #bbf7d0;
        }
        
        .security-notice p {
            font-size: 12px;
            color: #065f46;
        }
        
        .security-notice .notice-title {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        /* iOS fixes */
        @supports (-webkit-touch-callout: none) {
            input, select, textarea {
                font-size: 16px !important;
            }
            .input-field {
                -webkit-appearance: none;
                appearance: none;
            }
        }
        
        /* Mobile: stack vertically */
        @media (max-width: 767px) {
            .split-container {
                flex-direction: column;
            }
            
            .left-panel {
                flex: none;
                padding: 28px 24px;
                text-align: center;
                align-items: center;
            }
            
            .brand-title {
                font-size: 22px;
            }
            
            .brand-subtitle {
                font-size: 13px;
                margin-bottom: 0;
            }
            
            .features-list {
                display: none;
            }
            
            .right-panel {
                padding: 28px 20px;
            }
            
            .form-container {
                max-width: 100%;
            }
            
            .form-title {
                font-size: 20px;
            }
        }
        
        /* Touch device improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn-primary,
            .btn-secondary,
            button,
            a {
                min-height: 44px;
            }
            
            .input-field {
                min-height: 44px;
            }
        }
        
        /* Landscape mode */
        @media (max-height: 700px) and (orientation: landscape) {
            .left-panel {
                padding: 20px 32px;
            }
            
            .right-panel {
                padding: 20px 32px;
            }
            
            .logo-box {
                width: 40px;
                height: 40px;
                margin-bottom: 12px;
            }
            
            .brand-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="split-container">
        <!-- Left Panel - Branding -->
        <div class="left-panel">
            <a href="../index.html" style="text-decoration:none;">
                <div class="logo-box">
                    <img src="../assets/images/logo.jpg" alt="MediTrack Logo">
                </div>
            </a>
            <div class="brand-title">MediTrack</div>
            <div class="brand-subtitle">Your trusted healthcare management system for the Philippines.</div>
            
            <div class="features-list">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-calendar-check"></i></div>
                    <span class="feature-text">Easy appointment booking</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <span class="feature-text">Secure medical records</span>
                </div>
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-qrcode"></i></div>
                    <span class="feature-text">QR code check-in</span>
                </div>
            </div>
        </div>

        <!-- Right Panel - Login Form -->
        <div class="right-panel">
            <div class="form-container">
                <!-- Clock -->
                <div class="clock-badge">
                    <i class="fas fa-clock"></i>
                    <span id="real-time-clock"></span>
                </div>
                
                <div class="form-title">Welcome back</div>
                <div class="form-subtitle">Sign in to your account</div>
                
                <div id="alertBox" class="alert"></div>
                
                <form id="loginForm" autocomplete="off">
                    <!-- Username -->
                    <label class="field-label" for="username">Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input id="username" name="username" type="text" required 
                               class="input-field" placeholder="Enter your username"
                               autocomplete="off" maxlength="50">
                    </div>
                    
                    <!-- Password -->
                    <label class="field-label" for="password">Password</label>
                    <div class="input-wrapper" style="margin-bottom: 8px;">
                        <i class="fas fa-lock input-icon"></i>
                        <input id="password" name="password" type="password" required 
                               class="input-field" style="padding-right: 42px;"
                               placeholder="Enter your password"
                               autocomplete="new-password" minlength="6" maxlength="100">
                        <button type="button" id="togglePassword" class="toggle-password">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    <div class="strength-bar-container">
                        <div id="passwordStrength" class="password-strength"></div>
                    </div>
                    
                    <div style="height: 18px;"></div>
                    
                    <!-- Remember me & Forgot -->
                    <div class="options-row">
                        <div class="remember-me">
                            <input id="remember-me" name="remember-me" type="checkbox">
                            <label for="remember-me">Remember me</label>
                        </div>
                        <a href="forgot-password.html" class="forgot-link">Forgot password?</a>
                    </div>
                    
                    <!-- Submit -->
                    <button type="submit" id="loginButton" class="btn-primary">
                        <span id="loginText">
                            <i class="fas fa-sign-in-alt"></i> Sign In
                        </span>
                        <span id="loginSpinner" class="hidden">
                            <i class="fas fa-spinner fa-spin"></i> Authenticating...
                        </span>
                    </button>
                </form>
                
                <!-- Divider -->
                <div class="divider">
                    <div class="divider-line"></div>
                    <span class="divider-text">or</span>
                    <div class="divider-line"></div>
                </div>
                
                <!-- Register link -->
                <a href="register.html" class="btn-secondary">
                    Don't have an account? <span>Create one</span>
                </a>
                
                <!-- Security Notice -->
                <div class="security-notice">
                    <div style="display:flex;align-items:start;gap:10px;">
                        <i class="fas fa-info-circle" style="color:#059669;margin-top:2px;"></i>
                        <div>
                            <p class="notice-title">Security Notice:</p>
                            <p>Your connection is encrypted and secure. Never share your login credentials with anyone.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Disable right-click and keyboard shortcuts for security
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if (e.ctrlKey && (e.key === 'u' || e.key === 's' || e.key === 'c')) {
                e.preventDefault();
            }
        });

        // Real-Time Clock
        function updateRealTimeClock() {
            const now = new Date();
            const options = { 
                month: 'short', 
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const dateTimeString = now.toLocaleString('en-US', options);
            const clockElement = document.getElementById('real-time-clock');
            if (clockElement) {
                clockElement.textContent = dateTimeString;
            }
        }
        updateRealTimeClock();
        setInterval(updateRealTimeClock, 1000);

        // Password Toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        });

        // Password Strength Indicator
        const passwordStrength = document.getElementById('passwordStrength');
        passwordInput.addEventListener('input', () => {
            const password = passwordInput.value;
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            passwordStrength.className = 'password-strength';
            if (strength <= 2) {
                passwordStrength.classList.add('strength-weak');
            } else if (strength <= 4) {
                passwordStrength.classList.add('strength-medium');
            } else {
                passwordStrength.classList.add('strength-strong');
            }
        });

        // Form Elements
        const loginForm = document.getElementById('loginForm');
        const alertBox = document.getElementById('alertBox');
        const loginText = document.getElementById('loginText');
        const loginSpinner = document.getElementById('loginSpinner');
        const loginButton = document.getElementById('loginButton');
        const usernameInput = document.getElementById('username');

        // Alert Function
        function showAlert(message, type = 'error') {
            alertBox.className = `alert show ${type === 'error' ? 'alert-error' : 'alert-success'}`;
            alertBox.innerHTML = `
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                    <span style="font-weight:600;font-size:13px;">${message}</span>
                </div>
            `;
            setTimeout(() => {
                alertBox.className = 'alert';
            }, 5000);
        }

        // Input Sanitization
        function sanitizeInput(input) {
            return input.replace(/[<>"'&]/g, '');
        }

        // Rate Limiting
        let loginAttempts = 0;
        let lastAttemptTime = 0;
        const MAX_ATTEMPTS = 5;
        const LOCKOUT_TIME = 300000; // 5 minutes

        // Form Submission
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const now = Date.now();
            if (loginAttempts >= MAX_ATTEMPTS) {
                const timeLeft = Math.ceil((LOCKOUT_TIME - (now - lastAttemptTime)) / 1000);
                if (timeLeft > 0) {
                    showAlert(`Too many login attempts. Please try again in ${timeLeft} seconds.`, 'error');
                    return;
                } else {
                    loginAttempts = 0;
                }
            }
            
            const username = sanitizeInput(usernameInput.value.trim());
            const password = passwordInput.value;
            const recaptchaResponse = 'disabled';
            
            if (!username || !password) {
                showAlert('Please enter both username and password.', 'error');
                return;
            }
            
            if (username.length < 3) {
                showAlert('Username must be at least 3 characters.', 'error');
                return;
            }
            
            if (password.length < 6) {
                showAlert('Password must be at least 6 characters.', 'error');
                return;
            }

            loginButton.disabled = true;
            loginText.classList.add('hidden');
            loginSpinner.classList.remove('hidden');

            try {
                const timestamp = Date.now();
                const sessionToken = btoa(`${username}:${timestamp}`);
                
                const response = await fetch('../api/auth/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Session-Token': sessionToken
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ 
                        username, 
                        password,
                        timestamp,
                        recaptcha: recaptchaResponse
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('Login successful! Redirecting...', 'success');
                    passwordInput.value = '';
                    
                    setTimeout(() => {
                        switch(data.user?.role) {
                            case 'patient':
                                window.location.replace('patient-dashboard.html');
                                break;
                            case 'doctor':
                                window.location.replace('doctor-dashboard.html');
                                break;
                            case 'reception':
                                window.location.replace('reception-dashboard.html');
                                break;
                            case 'admin':
                                window.location.replace('admin-dashboard.html');
                                break;
                            default:
                                window.location.replace('patient-dashboard.html');
                        }
                    }, 1500);
                } else {
                    loginAttempts++;
                    lastAttemptTime = Date.now();
                    showAlert(data.message || 'Invalid username or password.', 'error');
                    passwordInput.value = '';
                }
            } catch (error) {
                console.error('Login error:', error);
                showAlert('Connection error. Please check your internet and try again.', 'error');
            } finally {
                loginButton.disabled = false;
                loginText.classList.remove('hidden');
                loginSpinner.classList.add('hidden');
            }
        });

        // Clear cache on load
        window.addEventListener('load', () => {
            if (window.performance && window.performance.navigation.type === 2) {
                window.location.reload(true);
            }
        });

        // Prevent back button after logout
        window.history.pushState(null, '', window.location.href);
        window.addEventListener('popstate', () => {
            window.history.pushState(null, '', window.location.href);
        });
    </script>
</body>
</html>
```

- [ ] **Step 2: Test the login page in browser**

Open `http://localhost/meditrack/pages/login.html` and verify:
- Desktop: split-screen layout renders (green left panel, white right panel)
- Mobile (use browser dev tools, toggle to 375px width): stacks vertically, features hidden
- Password toggle works
- Password strength bar updates
- Form submits to API correctly
- Clock updates every second
- "Create one" link goes to register.html
- "Forgot password?" link goes to forgot-password.html

- [ ] **Step 3: Commit login page**

```bash
git add pages/login.html
git commit -m "redesign: login page with split-screen layout

Replace centered card with professional split-screen design.
Left panel: dark green gradient with branding and features.
Right panel: clean form with improved input styling.
Mobile: stacks vertically with compact header.
All existing JS functionality preserved."
```

---

### Task 2: Redesign Register Page with 4-Step Wizard

**Files:**
- Modify: `pages/register.html` (full rewrite with wizard, preserve all JS logic)

- [ ] **Step 1: Replace register.html with the split-screen wizard layout**

This is the largest change. The new register.html:
- Uses the same split-screen layout as login
- Left panel shows a vertical step progress indicator (4 steps) instead of features
- Right panel shows only the current step's form fields
- JavaScript manages wizard state: `currentStep`, `formData` object, step navigation
- ALL existing JavaScript is preserved: Philippine location database, cascading dropdowns, camera capture, photo processing, phone formatting, name validation, DOB validation, form submission
- The form submission on step 4 gathers all data from the `formData` object and submits to the same API

The complete file content for register.html should be written using the Write tool. It will be approximately 1800 lines. Key sections:

**CSS (~200 lines):** Same split-screen base as login plus wizard-specific styles (step indicators, step transitions, progress states)

**HTML structure:**
```
<div class="split-container">
  <div class="left-panel">
    <!-- Logo, title, step progress indicator, "Already have account?" link -->
  </div>
  <div class="right-panel">
    <div class="form-container">
      <!-- Step 1: Account & Profile (profile pic, username, email, passwords) -->
      <!-- Step 2: Personal Info (name, DOB, gender, contact, blood group) -->
      <!-- Step 3: Address (street, region, province, city, barangay, ZIP) -->
      <!-- Step 4: Emergency & Medical (emergency contact, allergies) -->
      <!-- Navigation: Back/Next buttons -->
    </div>
  </div>
</div>
```

**JavaScript (~1200 lines):**
- Wizard state management (currentStep, formData, goToStep, validateStep)
- All existing Philippine location data (copy exactly from current file)
- All existing photo upload/camera/resize logic (copy exactly)
- All existing validation (name, phone, DOB, email, password)
- Modified form submission: collects from formData object instead of reading form elements
- Step indicator updates (completed checkmarks, active highlighting)

**Mobile behavior (<768px):**
- Left panel becomes compact header with logo, "Step X/4" text, and horizontal progress bar
- Step progress sidebar hidden
- Form fields stack single-column
- Back/Next buttons full width

- [ ] **Step 2: Test the register wizard in browser**

Open `http://localhost/meditrack/pages/register.html` and verify:
- Step 1 shows: profile picture upload area, username, email, password, confirm password
- Step 2 shows: full name, DOB, gender, contact number, blood group
- Step 3 shows: street address, region/province/city cascading dropdowns, barangay, ZIP
- Step 4 shows: emergency contact name/number, allergies, "Create Account" button
- Next button validates current step before advancing
- Back button returns to previous step without losing data
- Camera capture works
- Photo upload and resize works
- Philippine location dropdowns cascade correctly
- Phone number auto-formats with +63
- Name fields reject non-letter characters
- DOB validates age range
- Final submission sends all data to API
- Left panel updates: completed steps show checkmarks
- Mobile: horizontal progress bar, single column fields

- [ ] **Step 3: Commit register page**

```bash
git add pages/register.html
git commit -m "redesign: register page as 4-step wizard with split-screen

Convert single-page form to 4-step wizard:
1. Account & Profile (photo, credentials)
2. Personal Info (name, DOB, gender, contact, blood)
3. Address (Philippine location cascading dropdowns)
4. Emergency & Medical (contact, allergies)

Split-screen layout with step progress on left panel.
Mobile: compact header with horizontal progress bar.
All existing validation and API integration preserved."
```

---

### Task 3: Redesign Forgot Password Page

**Files:**
- Modify: `pages/forgot-password.html` (apply split-screen layout, preserve all JS)

- [ ] **Step 1: Replace forgot-password.html with split-screen layout**

Apply the same split-screen structure as login:
- Left panel: MediTrack logo + tagline only (no features list)
- Right panel: "Reset Password" heading, email field, "Send OTP" button, info box, "Back to Login" link
- Mobile: stacked layout
- Preserve all existing JavaScript: database setup call, form submission to `request-otp.php`, email validation, SweetAlert2 modals, sessionStorage, redirect to verify-otp.html

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MediTrack</title>
    
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/logo.jpg">
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }
        h1, h2, h3 { font-family: 'Poppins', 'Inter', sans-serif; }
        
        .split-container { display: flex; min-height: 100vh; }
        
        .left-panel {
            flex: 0 0 45%;
            background: linear-gradient(135deg, #065f46 0%, #047857 50%, #059669 100%);
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .left-panel::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        .left-panel::after {
            content: '';
            position: absolute;
            bottom: -80px; left: -40px;
            width: 260px; height: 260px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
        }
        
        .logo-box {
            width: 56px; height: 56px;
            background: rgba(255,255,255,0.15);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 24px;
            border: 1px solid rgba(255,255,255,0.2);
            overflow: hidden;
        }
        .logo-box img { width: 100%; height: 100%; object-fit: cover; border-radius: 14px; }
        
        .brand-title { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 8px; }
        .brand-subtitle { font-size: 14px; color: rgba(255,255,255,0.8); line-height: 1.6; }
        
        .right-panel {
            flex: 1;
            background: #ffffff;
            padding: 48px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
        }
        .form-container { width: 100%; max-width: 400px; }
        .form-title { font-size: 24px; font-weight: 700; color: #1f2937; margin-bottom: 4px; }
        .form-subtitle { font-size: 14px; color: #6b7280; margin-bottom: 28px; }
        
        .icon-circle {
            width: 64px; height: 64px;
            background: #f0fdf4;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px;
        }
        .icon-circle i { color: #059669; font-size: 24px; }
        
        .field-label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .input-field {
            width: 100%; height: 44px;
            background: #f9fafb;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            padding: 0 14px 0 42px;
            font-size: 14px; color: #1f2937;
            transition: all 0.2s ease;
            outline: none;
        }
        .input-field::placeholder { color: #9ca3af; }
        .input-field:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.1); background: #fff; }
        .input-wrapper { position: relative; margin-bottom: 20px; }
        .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 14px; pointer-events: none; }
        
        .btn-primary {
            width: 100%; height: 44px;
            background: linear-gradient(135deg, #059669, #10b981);
            border: none; border-radius: 10px;
            color: #fff; font-size: 15px; font-weight: 600;
            cursor: pointer; transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(5,150,105,0.3);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(5,150,105,0.35); }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        
        .back-link {
            display: inline-flex; align-items: center; gap: 6px;
            color: #059669; font-size: 13px; font-weight: 500;
            text-decoration: none; margin-top: 20px;
            transition: color 0.2s;
        }
        .back-link:hover { color: #047857; }
        
        .info-box {
            margin-top: 24px;
            padding: 14px;
            background: #eff6ff;
            border-radius: 10px;
            border: 1px solid #bfdbfe;
        }
        .info-box .info-title { font-size: 12px; font-weight: 600; color: #1e40af; margin-bottom: 6px; }
        .info-box ul { list-style: disc; padding-left: 16px; }
        .info-box li { font-size: 11px; color: #1e40af; margin-bottom: 4px; }
        
        @supports (-webkit-touch-callout: none) {
            input { font-size: 16px !important; -webkit-appearance: none; }
        }
        
        @media (max-width: 767px) {
            .split-container { flex-direction: column; }
            .left-panel { flex: none; padding: 28px 24px; text-align: center; align-items: center; }
            .brand-title { font-size: 22px; }
            .brand-subtitle { font-size: 13px; }
            .right-panel { padding: 28px 20px; }
            .form-container { max-width: 100%; }
            .form-title { font-size: 20px; }
            button { min-height: 44px; }
        }
    </style>
</head>
<body>
    <div class="split-container">
        <div class="left-panel">
            <a href="login.html" style="text-decoration:none;">
                <div class="logo-box">
                    <img src="../assets/images/logo.jpg" alt="MediTrack Logo">
                </div>
            </a>
            <div class="brand-title">MediTrack</div>
            <div class="brand-subtitle">Your trusted healthcare management system for the Philippines.</div>
        </div>

        <div class="right-panel">
            <div class="form-container">
                <div class="icon-circle">
                    <i class="fas fa-key"></i>
                </div>
                
                <div class="form-title">Reset Password</div>
                <div class="form-subtitle">We'll send a 6-digit OTP to your registered email address</div>
                
                <form id="forgotPasswordForm">
                    <label class="field-label" for="email">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input id="email" name="email" type="email" required 
                               class="input-field" placeholder="Enter your registered email">
                    </div>

                    <button type="submit" id="submitBtn" class="btn-primary">
                        <span id="submitText">
                            <i class="fas fa-paper-plane"></i> Send OTP
                        </span>
                        <span id="submitSpinner" class="hidden">
                            <i class="fas fa-spinner fa-spin"></i> Sending...
                        </span>
                    </button>
                </form>

                <div style="text-align:center;">
                    <a href="login.html" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>

                <div class="info-box">
                    <div style="display:flex;align-items:start;gap:10px;">
                        <i class="fas fa-info-circle" style="color:#2563eb;margin-top:2px;"></i>
                        <div>
                            <div class="info-title">Important:</div>
                            <ul>
                                <li>OTP will be valid for 10 minutes</li>
                                <li>Check your spam folder if you don't see the email</li>
                                <li>You can request a new OTP after expiration</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('forgotPasswordForm');
        const emailInput = document.getElementById('email');
        const submitBtn = document.getElementById('submitBtn');
        const submitText = document.getElementById('submitText');
        const submitSpinner = document.getElementById('submitSpinner');

        async function setupDatabase() {
            try {
                const response = await fetch('../setup-password-reset-auto.php');
                const data = await response.json();
                console.log('Database setup:', data);
            } catch (error) {
                console.error('Setup check failed:', error);
            }
        }
        setupDatabase();

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = emailInput.value.trim();

            if (!email) {
                Swal.fire({ icon: 'error', title: 'Email Required', text: 'Please enter your email address', confirmButtonColor: '#10b981' });
                return;
            }

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                Swal.fire({ icon: 'error', title: 'Invalid Email', text: 'Please enter a valid email address', confirmButtonColor: '#10b981' });
                return;
            }

            Swal.fire({
                title: 'Sending OTP...',
                html: 'Please wait while we send the verification code to your email',
                allowOutsideClick: false, allowEscapeKey: false,
                didOpen: () => { Swal.showLoading(); }
            });

            submitBtn.disabled = true;
            submitText.classList.add('hidden');
            submitSpinner.classList.remove('hidden');

            try {
                const response = await fetch('../api/auth/request-otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success', title: 'OTP Sent Successfully!',
                        html: `<p>A 6-digit verification code has been sent to:</p><p style="color:#059669;font-weight:700;font-size:18px;margin:8px 0;">${email}</p><p style="font-size:13px;color:#6b7280;">Check your inbox or spam folder. Code expires in 10 minutes.</p>`,
                        confirmButtonText: 'Continue to Verification',
                        confirmButtonColor: '#10b981', allowOutsideClick: false
                    }).then(() => {
                        sessionStorage.setItem('reset_email', email);
                        window.location.href = 'verify-otp.html';
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Failed to Send OTP', text: data.message || 'Unable to send OTP. Please try again.', confirmButtonColor: '#10b981' });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Unable to connect to the server. Please try again.', confirmButtonColor: '#10b981' });
            } finally {
                submitBtn.disabled = false;
                submitText.classList.remove('hidden');
                submitSpinner.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
```

- [ ] **Step 2: Test forgot password page**

Open `http://localhost/meditrack/pages/forgot-password.html` and verify:
- Split-screen layout matches login page style
- Email field with icon renders correctly
- Form submission works (sends to request-otp.php)
- SweetAlert2 modals show correctly
- "Back to Login" link works
- Mobile layout stacks correctly

- [ ] **Step 3: Commit forgot password page**

```bash
git add pages/forgot-password.html
git commit -m "redesign: forgot-password page with split-screen layout"
```

---

### Task 4: Redesign Verify OTP Page

**Files:**
- Modify: `pages/verify-otp.html` (apply split-screen layout, preserve all JS)

- [ ] **Step 1: Replace verify-otp.html with split-screen layout**

Same split-screen structure. Right panel contains: shield icon, "Enter Verification Code" heading, email display, timer countdown, 6 OTP input fields, verify button, resend OTP button, "Use different email" link, security tips box.

Preserve all existing JavaScript: email check from sessionStorage, OTP input auto-advance, backspace handling, paste handling, 10-minute countdown timer, form submission to `verify-otp.php`, resend OTP, redirect to reset-password.html.

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - MediTrack</title>
    
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/logo.jpg">
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }
        h1, h2, h3 { font-family: 'Poppins', 'Inter', sans-serif; }
        
        .split-container { display: flex; min-height: 100vh; }
        
        .left-panel {
            flex: 0 0 45%;
            background: linear-gradient(135deg, #065f46 0%, #047857 50%, #059669 100%);
            padding: 48px 40px;
            display: flex; flex-direction: column; justify-content: center;
            position: relative; overflow: hidden;
        }
        .left-panel::before { content: ''; position: absolute; top: -60px; right: -60px; width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,0.05); }
        .left-panel::after { content: ''; position: absolute; bottom: -80px; left: -40px; width: 260px; height: 260px; border-radius: 50%; background: rgba(255,255,255,0.04); }
        
        .logo-box { width: 56px; height: 56px; background: rgba(255,255,255,0.15); border-radius: 14px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; border: 1px solid rgba(255,255,255,0.2); overflow: hidden; }
        .logo-box img { width: 100%; height: 100%; object-fit: cover; border-radius: 14px; }
        .brand-title { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 8px; }
        .brand-subtitle { font-size: 14px; color: rgba(255,255,255,0.8); line-height: 1.6; }
        
        .right-panel { flex: 1; background: #ffffff; padding: 48px 40px; display: flex; flex-direction: column; justify-content: center; align-items: center; overflow-y: auto; }
        .form-container { width: 100%; max-width: 400px; }
        .form-title { font-size: 24px; font-weight: 700; color: #1f2937; margin-bottom: 4px; }
        .form-subtitle { font-size: 14px; color: #6b7280; margin-bottom: 8px; }
        
        .icon-circle { width: 64px; height: 64px; background: #f0fdf4; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .icon-circle i { color: #059669; font-size: 24px; }
        
        .otp-container { display: flex; justify-content: center; gap: 8px; margin: 24px 0; }
        .otp-input {
            width: 50px; height: 60px;
            font-size: 24px; font-weight: 700; text-align: center;
            border: 1.5px solid #e5e7eb; border-radius: 10px;
            background: #f9fafb; color: #1f2937;
            transition: all 0.2s ease; outline: none;
        }
        .otp-input:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.1); background: #fff; }
        
        .timer { font-size: 16px; font-weight: 700; color: #10b981; }
        .timer.expired { color: #ef4444; }
        
        .btn-primary {
            width: 100%; height: 44px;
            background: linear-gradient(135deg, #059669, #10b981);
            border: none; border-radius: 10px;
            color: #fff; font-size: 15px; font-weight: 600;
            cursor: pointer; transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(5,150,105,0.3);
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(5,150,105,0.35); }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        
        .text-link { font-size: 13px; font-weight: 500; cursor: pointer; transition: color 0.2s; border: none; background: none; }
        .text-link.green { color: #059669; }
        .text-link.green:hover { color: #047857; }
        .text-link.gray { color: #6b7280; text-decoration: none; }
        .text-link.gray:hover { color: #374151; }
        .text-link:disabled { color: #9ca3af; cursor: not-allowed; }
        
        .info-box {
            margin-top: 24px; padding: 14px;
            background: #fefce8; border-radius: 10px; border: 1px solid #fde68a;
        }
        .info-box .info-title { font-size: 12px; font-weight: 600; color: #92400e; margin-bottom: 6px; }
        .info-box li { font-size: 11px; color: #92400e; margin-bottom: 4px; }
        
        @supports (-webkit-touch-callout: none) { input { font-size: 16px !important; -webkit-appearance: none; } }
        
        @media (max-width: 767px) {
            .split-container { flex-direction: column; }
            .left-panel { flex: none; padding: 28px 24px; text-align: center; align-items: center; }
            .brand-title { font-size: 22px; }
            .brand-subtitle { font-size: 13px; }
            .right-panel { padding: 28px 20px; }
            .form-container { max-width: 100%; }
            .form-title { font-size: 20px; }
            .otp-input { width: 44px; height: 52px; font-size: 20px; }
            .otp-container { gap: 6px; }
            button { min-height: 44px; }
        }
        
        @media (max-width: 380px) {
            .otp-input { width: 40px; height: 48px; font-size: 18px; }
            .otp-container { gap: 4px; }
        }
    </style>
</head>
<body>
    <div class="split-container">
        <div class="left-panel">
            <a href="login.html" style="text-decoration:none;">
                <div class="logo-box">
                    <img src="../assets/images/logo.jpg" alt="MediTrack Logo">
                </div>
            </a>
            <div class="brand-title">MediTrack</div>
            <div class="brand-subtitle">Your trusted healthcare management system for the Philippines.</div>
        </div>

        <div class="right-panel">
            <div class="form-container">
                <div class="icon-circle">
                    <i class="fas fa-shield-alt"></i>
                </div>
                
                <div class="form-title">Enter Verification Code</div>
                <div class="form-subtitle">Code sent to: <span id="emailDisplay" style="color:#059669;font-weight:600;"></span></div>
                
                <div style="text-align:center;margin-top:12px;">
                    <span style="font-size:13px;color:#6b7280;">Time remaining: </span>
                    <span id="timer" class="timer">10:00</span>
                </div>
                
                <form id="verifyOtpForm">
                    <div class="otp-container">
                        <input type="text" maxlength="1" class="otp-input" id="otp1" pattern="[0-9]" inputmode="numeric">
                        <input type="text" maxlength="1" class="otp-input" id="otp2" pattern="[0-9]" inputmode="numeric">
                        <input type="text" maxlength="1" class="otp-input" id="otp3" pattern="[0-9]" inputmode="numeric">
                        <input type="text" maxlength="1" class="otp-input" id="otp4" pattern="[0-9]" inputmode="numeric">
                        <input type="text" maxlength="1" class="otp-input" id="otp5" pattern="[0-9]" inputmode="numeric">
                        <input type="text" maxlength="1" class="otp-input" id="otp6" pattern="[0-9]" inputmode="numeric">
                    </div>

                    <button type="submit" id="verifyBtn" class="btn-primary">
                        <span id="verifyText"><i class="fas fa-check-circle"></i> Verify OTP</span>
                        <span id="verifySpinner" class="hidden"><i class="fas fa-spinner fa-spin"></i> Verifying...</span>
                    </button>
                </form>

                <div style="text-align:center;margin-top:20px;display:flex;flex-direction:column;gap:10px;align-items:center;">
                    <button id="resendBtn" class="text-link green" disabled>
                        <i class="fas fa-redo"></i> Resend OTP
                    </button>
                    <a href="forgot-password.html" class="text-link gray">
                        <i class="fas fa-arrow-left"></i> Use different email
                    </a>
                </div>

                <div class="info-box">
                    <div style="display:flex;align-items:start;gap:10px;">
                        <i class="fas fa-exclamation-triangle" style="color:#d97706;margin-top:2px;"></i>
                        <div>
                            <div class="info-title">Security Tips:</div>
                            <ul style="list-style:disc;padding-left:16px;">
                                <li>Never share your OTP with anyone</li>
                                <li>OTP expires after 10 minutes</li>
                                <li>Request a new OTP if expired</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const email = sessionStorage.getItem('reset_email');
        if (!email) { window.location.href = 'forgot-password.html'; }
        document.getElementById('emailDisplay').textContent = email;

        const otpInputs = document.querySelectorAll('.otp-input');
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                const value = e.target.value;
                if (!/^\d*$/.test(value)) { e.target.value = ''; return; }
                if (value && index < otpInputs.length - 1) { otpInputs[index + 1].focus(); }
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) { otpInputs[index - 1].focus(); }
            });
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').slice(0, 6);
                if (!/^\d+$/.test(pastedData)) return;
                pastedData.split('').forEach((char, i) => { if (otpInputs[i]) otpInputs[i].value = char; });
                otpInputs[Math.min(pastedData.length, 5)].focus();
            });
        });

        let timeLeft = 600;
        const timerElement = document.getElementById('timer');
        const resendBtn = document.getElementById('resendBtn');
        const countdown = setInterval(() => {
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            if (timeLeft <= 0) {
                clearInterval(countdown);
                timerElement.classList.add('expired');
                timerElement.textContent = 'Expired';
                resendBtn.disabled = false;
                Swal.fire({ icon: 'warning', title: 'OTP Expired', text: 'Your OTP has expired. Please request a new one.', confirmButtonText: 'OK' });
            }
        }, 1000);

        const form = document.getElementById('verifyOtpForm');
        const verifyBtn = document.getElementById('verifyBtn');
        const verifyText = document.getElementById('verifyText');
        const verifySpinner = document.getElementById('verifySpinner');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const otp = Array.from(otpInputs).map(input => input.value).join('');
            if (otp.length !== 6) { Swal.fire('Error', 'Please enter the complete 6-digit OTP', 'error'); return; }

            verifyBtn.disabled = true;
            verifyText.classList.add('hidden');
            verifySpinner.classList.remove('hidden');

            try {
                const response = await fetch('../api/auth/verify-otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, otp })
                });
                const data = await response.json();
                if (data.success) {
                    clearInterval(countdown);
                    Swal.fire({ icon: 'success', title: 'OTP Verified!', text: 'You can now reset your password', confirmButtonText: 'Continue' }).then(() => {
                        sessionStorage.setItem('reset_token', data.reset_token);
                        window.location.href = 'reset-password.html';
                    });
                } else {
                    Swal.fire('Error', data.message || 'Invalid OTP', 'error');
                    otpInputs.forEach(input => input.value = '');
                    otpInputs[0].focus();
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            } finally {
                verifyBtn.disabled = false;
                verifyText.classList.remove('hidden');
                verifySpinner.classList.add('hidden');
            }
        });

        resendBtn.addEventListener('click', async () => {
            resendBtn.disabled = true;
            try {
                const response = await fetch('../api/auth/request-otp.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email })
                });
                const data = await response.json();
                if (data.success) {
                    Swal.fire('Success', 'New OTP sent to your email', 'success');
                    timeLeft = 600;
                    timerElement.classList.remove('expired');
                    otpInputs.forEach(input => input.value = '');
                    otpInputs[0].focus();
                } else {
                    Swal.fire('Error', data.message || 'Failed to resend OTP', 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Network error. Please try again.', 'error');
            } finally {
                resendBtn.disabled = false;
            }
        });

        otpInputs[0].focus();
    </script>
</body>
</html>
```

- [ ] **Step 2: Test verify OTP page**

Navigate through forgot-password flow to reach verify-otp.html. Verify:
- Split-screen layout renders
- OTP inputs auto-advance on digit entry
- Backspace moves to previous field
- Paste fills all 6 fields
- Timer counts down from 10:00
- Resend button enables when timer expires
- Mobile layout stacks correctly

- [ ] **Step 3: Commit verify OTP page**

```bash
git add pages/verify-otp.html
git commit -m "redesign: verify-otp page with split-screen layout"
```

---

### Task 5: Redesign Reset Password Page

**Files:**
- Modify: `pages/reset-password.html` (apply split-screen layout, preserve all JS)

- [ ] **Step 1: Replace reset-password.html with split-screen layout**

Same split-screen structure. Right panel contains: lock icon, "Create New Password" heading, new password field with toggle and strength bar, confirm password field with toggle and match indicator, password requirements checklist, reset button.

Preserve all existing JavaScript: reset token check, password toggle, strength indicator with 5 requirements, real-time match checking, form submission to `reset-password.php`, sessionStorage cleanup.

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MediTrack</title>
    
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/logo.jpg">
    
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; -webkit-font-smoothing: antialiased; min-height: 100vh; }
        h1, h2, h3 { font-family: 'Poppins', 'Inter', sans-serif; }
        
        .split-container { display: flex; min-height: 100vh; }
        .left-panel { flex: 0 0 45%; background: linear-gradient(135deg, #065f46 0%, #047857 50%, #059669 100%); padding: 48px 40px; display: flex; flex-direction: column; justify-content: center; position: relative; overflow: hidden; }
        .left-panel::before { content: ''; position: absolute; top: -60px; right: -60px; width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,0.05); }
        .left-panel::after { content: ''; position: absolute; bottom: -80px; left: -40px; width: 260px; height: 260px; border-radius: 50%; background: rgba(255,255,255,0.04); }
        
        .logo-box { width: 56px; height: 56px; background: rgba(255,255,255,0.15); border-radius: 14px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; border: 1px solid rgba(255,255,255,0.2); overflow: hidden; }
        .logo-box img { width: 100%; height: 100%; object-fit: cover; border-radius: 14px; }
        .brand-title { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 8px; }
        .brand-subtitle { font-size: 14px; color: rgba(255,255,255,0.8); line-height: 1.6; }
        
        .right-panel { flex: 1; background: #ffffff; padding: 48px 40px; display: flex; flex-direction: column; justify-content: center; align-items: center; overflow-y: auto; }
        .form-container { width: 100%; max-width: 400px; }
        .form-title { font-size: 24px; font-weight: 700; color: #1f2937; margin-bottom: 4px; }
        .form-subtitle { font-size: 14px; color: #6b7280; margin-bottom: 28px; }
        
        .icon-circle { width: 64px; height: 64px; background: #f0fdf4; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .icon-circle i { color: #059669; font-size: 24px; }
        
        .field-label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        .input-field { width: 100%; height: 44px; background: #f9fafb; border: 1.5px solid #e5e7eb; border-radius: 10px; padding: 0 42px 0 42px; font-size: 14px; color: #1f2937; transition: all 0.2s ease; outline: none; }
        .input-field::placeholder { color: #9ca3af; }
        .input-field:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.1); background: #fff; }
        .input-field.input-error { border-color: #ef4444 !important; }
        .input-field.input-success { border-color: #10b981 !important; }
        .input-wrapper { position: relative; margin-bottom: 8px; }
        .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 14px; pointer-events: none; }
        .toggle-password { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); color: #9ca3af; cursor: pointer; background: none; border: none; padding: 4px; font-size: 14px; }
        .toggle-password:hover { color: #10b981; }
        
        .strength-bar-container { height: 4px; background: #e5e7eb; border-radius: 2px; overflow: hidden; margin-bottom: 4px; }
        .password-strength { height: 100%; border-radius: 2px; transition: all 0.3s; width: 0; }
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
        
        .strength-text { font-size: 12px; color: #6b7280; margin-bottom: 18px; }
        .match-indicator { font-size: 12px; font-weight: 600; margin-top: 4px; margin-bottom: 18px; }
        .match-success { color: #10b981; }
        .match-error { color: #ef4444; }
        
        .btn-primary { width: 100%; height: 44px; background: linear-gradient(135deg, #059669, #10b981); border: none; border-radius: 10px; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 14px rgba(5,150,105,0.3); display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(5,150,105,0.35); }
        .btn-primary:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        
        .requirements-box { margin-top: 20px; padding: 14px; background: #f0fdf4; border-radius: 10px; border: 1px solid #bbf7d0; }
        .requirements-box .req-title { font-size: 12px; font-weight: 600; color: #065f46; margin-bottom: 8px; }
        .req-item { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; font-size: 12px; color: #065f46; }
        .req-item i { font-size: 10px; }
        .req-item i.fa-circle { color: #9ca3af; }
        .req-item i.fa-check-circle { color: #10b981; }
        
        @supports (-webkit-touch-callout: none) { input { font-size: 16px !important; -webkit-appearance: none; } }
        @media (max-width: 767px) {
            .split-container { flex-direction: column; }
            .left-panel { flex: none; padding: 28px 24px; text-align: center; align-items: center; }
            .brand-title { font-size: 22px; }
            .brand-subtitle { font-size: 13px; }
            .right-panel { padding: 28px 20px; }
            .form-container { max-width: 100%; }
            .form-title { font-size: 20px; }
            button { min-height: 44px; }
        }
    </style>
</head>
<body>
    <div class="split-container">
        <div class="left-panel">
            <a href="login.html" style="text-decoration:none;">
                <div class="logo-box"><img src="../assets/images/logo.jpg" alt="MediTrack Logo"></div>
            </a>
            <div class="brand-title">MediTrack</div>
            <div class="brand-subtitle">Your trusted healthcare management system for the Philippines.</div>
        </div>

        <div class="right-panel">
            <div class="form-container">
                <div class="icon-circle"><i class="fas fa-lock"></i></div>
                <div class="form-title">Create New Password</div>
                <div class="form-subtitle">Your new password must be different from previous passwords</div>
                
                <form id="resetPasswordForm">
                    <label class="field-label" for="newPassword">New Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input id="newPassword" name="newPassword" type="password" required class="input-field" placeholder="Enter new password" minlength="8">
                        <button type="button" id="toggleNewPassword" class="toggle-password"><i class="fas fa-eye" id="eyeIconNew"></i></button>
                    </div>
                    <div class="strength-bar-container"><div id="passwordStrength" class="password-strength"></div></div>
                    <p id="strengthText" class="strength-text">Minimum 8 characters required</p>

                    <label class="field-label" for="confirmPassword">Confirm Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input id="confirmPassword" name="confirmPassword" type="password" required class="input-field" placeholder="Confirm new password" minlength="8">
                        <button type="button" id="toggleConfirmPassword" class="toggle-password"><i class="fas fa-eye" id="eyeIconConfirm"></i></button>
                    </div>
                    <div id="matchIndicator" class="match-indicator" style="display:none;"></div>

                    <button type="submit" id="resetBtn" class="btn-primary">
                        <span id="resetText"><i class="fas fa-check-circle"></i> Reset Password</span>
                        <span id="resetSpinner" class="hidden"><i class="fas fa-spinner fa-spin"></i> Resetting...</span>
                    </button>
                </form>

                <div class="requirements-box">
                    <div class="req-title">Password Requirements:</div>
                    <div id="req-length" class="req-item"><i class="fas fa-circle"></i><span>At least 8 characters</span></div>
                    <div id="req-uppercase" class="req-item"><i class="fas fa-circle"></i><span>One uppercase letter</span></div>
                    <div id="req-lowercase" class="req-item"><i class="fas fa-circle"></i><span>One lowercase letter</span></div>
                    <div id="req-number" class="req-item"><i class="fas fa-circle"></i><span>One number</span></div>
                    <div id="req-special" class="req-item"><i class="fas fa-circle"></i><span>One special character</span></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const resetToken = sessionStorage.getItem('reset_token');
        const email = sessionStorage.getItem('reset_email');
        if (!resetToken || !email) {
            Swal.fire({ icon: 'error', title: 'Invalid Access', text: 'Please start the password reset process from the beginning', confirmButtonText: 'OK' }).then(() => { window.location.href = 'forgot-password.html'; });
        }

        const newPasswordInput = document.getElementById('newPassword');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const toggleNewPassword = document.getElementById('toggleNewPassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const eyeIconNew = document.getElementById('eyeIconNew');
        const eyeIconConfirm = document.getElementById('eyeIconConfirm');

        toggleNewPassword.addEventListener('click', () => {
            const type = newPasswordInput.type === 'password' ? 'text' : 'password';
            newPasswordInput.type = type;
            eyeIconNew.classList.toggle('fa-eye');
            eyeIconNew.classList.toggle('fa-eye-slash');
        });
        toggleConfirmPassword.addEventListener('click', () => {
            const type = confirmPasswordInput.type === 'password' ? 'text' : 'password';
            confirmPasswordInput.type = type;
            eyeIconConfirm.classList.toggle('fa-eye');
            eyeIconConfirm.classList.toggle('fa-eye-slash');
        });

        const passwordStrength = document.getElementById('passwordStrength');
        const requirements = {
            length: document.getElementById('req-length'),
            uppercase: document.getElementById('req-uppercase'),
            lowercase: document.getElementById('req-lowercase'),
            number: document.getElementById('req-number'),
            special: document.getElementById('req-special')
        };

        function updateRequirement(element, met) {
            const icon = element.querySelector('i');
            icon.className = met ? 'fas fa-check-circle' : 'fas fa-circle';
        }

        const strengthText = document.getElementById('strengthText');
        const matchIndicator = document.getElementById('matchIndicator');

        newPasswordInput.addEventListener('input', () => {
            const password = newPasswordInput.value;
            let strength = 0;
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^a-zA-Z0-9]/.test(password);

            updateRequirement(requirements.length, hasLength);
            updateRequirement(requirements.uppercase, hasUppercase);
            updateRequirement(requirements.lowercase, hasLowercase);
            updateRequirement(requirements.number, hasNumber);
            updateRequirement(requirements.special, hasSpecial);

            if (hasLength) strength++;
            if (hasUppercase) strength++;
            if (hasLowercase) strength++;
            if (hasNumber) strength++;
            if (hasSpecial) strength++;

            passwordStrength.className = 'password-strength';
            if (password.length === 0) {
                strengthText.textContent = 'Minimum 8 characters required';
                strengthText.style.color = '#6b7280';
            } else if (strength <= 2) {
                passwordStrength.classList.add('strength-weak');
                strengthText.textContent = 'Weak password - Add more variety';
                strengthText.style.color = '#ef4444';
                newPasswordInput.classList.add('input-error');
                newPasswordInput.classList.remove('input-success');
            } else if (strength <= 4) {
                passwordStrength.classList.add('strength-medium');
                strengthText.textContent = 'Medium strength - Almost there!';
                strengthText.style.color = '#d97706';
                newPasswordInput.classList.remove('input-error', 'input-success');
            } else {
                passwordStrength.classList.add('strength-strong');
                strengthText.textContent = 'Strong password!';
                strengthText.style.color = '#10b981';
                newPasswordInput.classList.add('input-success');
                newPasswordInput.classList.remove('input-error');
            }
            checkPasswordMatch();
        });

        confirmPasswordInput.addEventListener('input', checkPasswordMatch);

        function checkPasswordMatch() {
            const password = newPasswordInput.value;
            const confirm = confirmPasswordInput.value;
            if (confirm.length === 0) { matchIndicator.style.display = 'none'; confirmPasswordInput.classList.remove('input-error', 'input-success'); return; }
            matchIndicator.style.display = 'block';
            if (password === confirm && confirm.length >= 8) {
                matchIndicator.textContent = 'Passwords match';
                matchIndicator.className = 'match-indicator match-success';
                confirmPasswordInput.classList.add('input-success');
                confirmPasswordInput.classList.remove('input-error');
            } else {
                matchIndicator.textContent = 'Passwords do not match';
                matchIndicator.className = 'match-indicator match-error';
                confirmPasswordInput.classList.add('input-error');
                confirmPasswordInput.classList.remove('input-success');
            }
        }

        const form = document.getElementById('resetPasswordForm');
        const resetBtn = document.getElementById('resetBtn');
        const resetText = document.getElementById('resetText');
        const resetSpinner = document.getElementById('resetSpinner');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (newPassword.length < 8) { Swal.fire({ icon: 'error', title: 'Password Too Short', text: 'Password must be at least 8 characters long', confirmButtonColor: '#10b981' }); newPasswordInput.focus(); return; }
            if (!/[A-Z]/.test(newPassword)) { Swal.fire({ icon: 'error', title: 'Missing Uppercase', text: 'Password must contain at least one uppercase letter', confirmButtonColor: '#10b981' }); newPasswordInput.focus(); return; }
            if (!/[a-z]/.test(newPassword)) { Swal.fire({ icon: 'error', title: 'Missing Lowercase', text: 'Password must contain at least one lowercase letter', confirmButtonColor: '#10b981' }); newPasswordInput.focus(); return; }
            if (!/[0-9]/.test(newPassword)) { Swal.fire({ icon: 'error', title: 'Missing Number', text: 'Password must contain at least one number', confirmButtonColor: '#10b981' }); newPasswordInput.focus(); return; }
            if (!/[^a-zA-Z0-9]/.test(newPassword)) { Swal.fire({ icon: 'error', title: 'Missing Special Character', text: 'Password must contain at least one special character', confirmButtonColor: '#10b981' }); newPasswordInput.focus(); return; }
            if (newPassword !== confirmPassword) { Swal.fire({ icon: 'error', title: 'Passwords Don\'t Match', text: 'Please make sure both passwords are identical', confirmButtonColor: '#10b981' }); confirmPasswordInput.focus(); return; }

            Swal.fire({ title: 'Resetting Password...', html: 'Please wait', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => { Swal.showLoading(); } });
            resetBtn.disabled = true;
            resetText.classList.add('hidden');
            resetSpinner.classList.remove('hidden');

            try {
                const response = await fetch('../api/auth/reset-password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, reset_token: resetToken, new_password: newPassword })
                });
                const data = await response.json();
                if (data.success) {
                    sessionStorage.removeItem('reset_email');
                    sessionStorage.removeItem('reset_token');
                    Swal.fire({ icon: 'success', title: 'Password Reset Successful!', html: '<p>Your password has been updated. You can now login with your new password.</p>', confirmButtonText: 'Go to Login', confirmButtonColor: '#10b981', allowOutsideClick: false }).then(() => { window.location.href = 'login.html'; });
                } else {
                    Swal.fire({ icon: 'error', title: 'Reset Failed', text: data.message || 'Failed to reset password. Please try again.', confirmButtonColor: '#10b981' });
                }
            } catch (error) {
                console.error('Error:', error);
                Swal.fire({ icon: 'error', title: 'Network Error', text: 'Unable to connect to the server. Please try again.', confirmButtonColor: '#10b981' });
            } finally {
                resetBtn.disabled = false;
                resetText.classList.remove('hidden');
                resetSpinner.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
```

- [ ] **Step 2: Test reset password page**

Verify:
- Split-screen layout renders
- Password toggle works on both fields
- Password strength bar and requirements checklist update in real-time
- Password match indicator shows/hides correctly
- Form submission works
- Mobile layout stacks correctly

- [ ] **Step 3: Commit reset password page**

```bash
git add pages/reset-password.html
git commit -m "redesign: reset-password page with split-screen layout"
```

---

### Task 6: Final Cross-Page Testing

- [ ] **Step 1: Test full auth flow end-to-end**

Walk through the entire flow in browser:
1. Login page loads with split-screen → click "Create one"
2. Register wizard loads → fill Step 1, click Next
3. Fill Steps 2-4, submit → redirected to login
4. Back on login → click "Forgot password?"
5. Forgot password page → enter email, submit
6. Verify OTP page → enter code
7. Reset password page → set new password

Verify all pages share consistent visual language (same green gradient, same input styling, same button styling, same mobile behavior).

- [ ] **Step 2: Test mobile responsiveness on all pages**

Using browser dev tools, test at:
- 375px (iPhone SE)
- 390px (iPhone 14)
- 768px (iPad)
- 1024px (desktop)

Verify on each:
- No horizontal scrolling
- Touch targets are at least 44px
- Forms are usable
- Text is readable (no tiny fonts)

- [ ] **Step 3: Commit any fixes**

If any cross-page issues were found and fixed:
```bash
git add pages/
git commit -m "fix: cross-page consistency and mobile responsiveness fixes"
```
