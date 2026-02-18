<?php
/**
 * LOKA - Login Page
 * DICT-Inspired Design with Antigravity Aesthetics
 */

if (isLoggedIn()) {
    redirect('/?page=dashboard');
}

$errors = [];
$isLocked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $email = Security::getInstance()->sanitizeEmail(post('email'));
    $password = post('password');
    $remember = post('remember') === '1';

    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (empty($password)) {
        $errors[] = 'Password is required';
    }

    if (empty($errors)) {
        $auth = new Auth();
        $result = $auth->attempt($email, $password, $remember);

        if ($result['success']) {
            $userName = $_SESSION['user_name'] ?? 'User';
            redirectWith('/?page=dashboard', 'success', 'Welcome back, ' . e($userName) . '!');
        } else {
            $errors[] = $result['error'];
            $isLocked = $result['locked'] ?? false;
        }
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= ASSETS_PATH ?>/css/style.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #030407;
            --bg-card: rgba(11, 11, 18, 0.85);
            --primary: #6366f1;
            --primary-light: #818cf8;
            --accent: #c084fc;
            --text: #ffffff;
            --text-secondary: #a1a1aa;
            --border: rgba(255, 255, 255, 0.1);
            --success: #10b981;
            --error: #f43f5e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-dark);
            min-height: 100vh;
            overflow-x: hidden;
            color: var(--text);
        }

        .login-wrapper {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #030407;
            padding: 20px;
            overflow: hidden;
        }

        /* Animated gradient orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            -webkit-filter: blur(80px);
            animation: orbFloat 20s ease-in-out infinite;
            will-change: transform, opacity;
        }

        .orb-1 {
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.6) 0%, rgba(99, 102, 241, 0.3) 40%, transparent 70%);
            top: -250px;
            left: -250px;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(192, 132, 252, 0.5) 0%, rgba(192, 132, 252, 0.25) 40%, transparent 70%);
            bottom: -200px;
            right: -200px;
            animation-delay: -7s;
        }

        .orb-3 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.45) 0%, rgba(99, 102, 241, 0.2) 40%, transparent 70%);
            top: 35%;
            right: 5%;
            animation-delay: -14s;
        }

        .orb-4 {
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(192, 132, 252, 0.4) 0%, transparent 70%);
            top: 55%;
            left: 8%;
            animation-delay: -10s;
        }

        @keyframes orbFloat {
            0% { transform: translate(0, 0) scale(1) rotate(0deg); opacity: 0.7; }
            25% { transform: translate(80px, -50px) scale(1.2) rotate(8deg); opacity: 1; }
            50% { transform: translate(-50px, 70px) scale(0.85) rotate(-8deg); opacity: 0.6; }
            75% { transform: translate(-70px, -40px) scale(1.15) rotate(5deg); opacity: 0.9; }
            100% { transform: translate(0, 0) scale(1) rotate(0deg); opacity: 0.7; }
        }

        /* Grid pattern */
        .grid-bg {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                linear-gradient(rgba(99, 102, 241, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(99, 102, 241, 0.08) 1px, transparent 1px);
            background-size: 60px 60px;
            animation: gridMove 8s linear infinite;
            z-index: 0;
        }

        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(60px, 60px); }
        }

        /* Main card */
        .login-card {
            background: var(--bg-card);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid var(--border);
            border-radius: 28px;
            box-shadow: 
                0 0 0 1px rgba(255, 255, 255, 0.03) inset,
                0 25px 50px -12px rgba(0, 0, 0, 0.6),
                0 0 80px -20px rgba(99, 102, 241, 0.25),
                0 0 2px rgba(99, 102, 241, 0.3);
            position: relative;
            z-index: 10;
            animation: cardEntrance 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.96);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .card-inner {
            padding: 3rem 2.5rem;
        }

        /* Logo */
        .logo-wrapper {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.25rem;
            color: white;
            box-shadow: 
                0 15px 50px -10px rgba(99, 102, 241, 0.5),
                0 0 0 1px rgba(255, 255, 255, 0.1) inset,
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            animation: logoFloat 6s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-8px) rotate(3deg); }
        }

        .app-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0.35rem;
            letter-spacing: -0.5px;
        }

        .login-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 400;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.75rem;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--text) !important;
            background: rgba(255, 255, 255, 0.04) !important;
            border: 1px solid var(--border);
            border-radius: 14px;
            transition: all 0.25s ease;
            outline: none;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.3) !important;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.06) !important;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15), 0 0 20px -5px rgba(99, 102, 241, 0.3);
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.1rem;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .form-control:focus ~ .input-icon {
            color: var(--primary-light);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 0.5rem;
            transition: color 0.2s ease;
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--primary-light);
        }

        /* Remember & Forgot */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-check-input {
            width: 17px;
            height: 17px;
            margin: 0;
            cursor: pointer;
            accent-color: var(--primary);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 4px;
        }

        .form-check-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .forgot-link {
            font-size: 0.875rem;
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .forgot-link:hover {
            color: var(--accent);
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            font-family: inherit;
            color: white;
            background: linear-gradient(135deg, var(--primary) 0%, #4f46e5 100%);
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 24px -3px rgba(99, 102, 241, 0.45);
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
            transition: left 0.5s ease;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 35px -4px rgba(99, 102, 241, 0.55);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Loading */
        .spinner-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(3, 4, 7, 0.92);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 20;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            border-radius: 28px;
        }

        .spinner-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 42px;
            height: 42px;
            border: 3px solid var(--border);
            border-top-color: var(--primary-light);
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Alerts */
        .alert-dict {
            background: rgba(244, 63, 94, 0.1);
            border: 1px solid rgba(244, 63, 94, 0.25);
            border-radius: 14px;
            color: #fda4af;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            animation: shake 0.45s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
        }

        .alert-dict strong {
            display: block;
            margin-bottom: 0.4rem;
            color: var(--error);
        }

        .alert-dict ul {
            margin: 0;
            padding-left: 1.25rem;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .card-inner {
                padding: 2.25rem 1.75rem;
            }

            .app-title {
                font-size: 1.65rem;
            }

            .form-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .orb-1 { width: 400px; height: 400px; }
            .orb-2 { width: 350px; height: 350px; }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-card,
            .logo-icon,
            .orb {
                animation: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Background elements -->
        <div class="grid-bg"></div>
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
        <div class="orb orb-4"></div>

        <!-- Login Card -->
        <div class="login-card">
            <div class="spinner-overlay" id="loadingOverlay">
                <div class="loading-spinner"></div>
            </div>

            <div class="card-inner">
                <div class="logo-wrapper">
                    <div class="logo-icon">
                        <i class="bi bi-car-front-fill"></i>
                    </div>
                    <h1 class="app-title">LOKA</h1>
                    <p class="login-subtitle">Fleet Management System</p>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert-dict">
                    <strong><i class="bi bi-exclamation-triangle me-2"></i>Unable to sign in</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" id="loginForm">
                    <?= csrfField() ?>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-wrapper">
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email"
                                   value="<?= e(post('email', '')) ?>"
                                   placeholder="name@dict.gov.ph"
                                   required 
                                   autofocus>
                            <i class="bi bi-envelope input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password"
                                   placeholder="Enter your password" 
                                   required>
                            <i class="bi bi-lock input-icon"></i>
                            <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <a href="<?= APP_URL ?>/?page=forgot-password" class="forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="bi bi-arrow-right-circle me-2"></i>Sign In
                    </button>
                </form>

                <div class="login-footer">
                    <i class="bi bi-shield-lock me-1"></i>
                    Secured by DICT Authentication
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const passwordIcon = togglePassword.querySelector('i');

            togglePassword.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    passwordIcon.classList.replace('bi-eye', 'bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    passwordIcon.classList.replace('bi-eye-slash', 'bi-eye');
                }
            });

            const loginForm = document.getElementById('loginForm');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const submitBtn = document.getElementById('submitBtn');

            loginForm.addEventListener('submit', function(e) {
                loadingOverlay.classList.add('active');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-arrow-repeat me-2"></i>Authenticating...';
            });

            window.addEventListener('load', function() {
                if (document.querySelector('.alert-dict')) {
                    loadingOverlay.classList.remove('active');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-arrow-right-circle me-2"></i>Sign In';
                }
            });
        })();
    </script>
</body>
</html>
