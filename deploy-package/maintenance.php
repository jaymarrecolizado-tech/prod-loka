<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance - LOKA Fleet Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* Background Animation */
        .bg-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: float 20s infinite ease-in-out;
        }

        .shape-1 {
            width: 400px;
            height: 400px;
            background: #dc3545;
            top: -100px;
            left: -100px;
        }

        .shape-2 {
            width: 300px;
            height: 300px;
            background: #ffc107;
            bottom: -50px;
            right: -50px;
            animation-delay: -5s;
        }

        .shape-3 {
            width: 250px;
            height: 250px;
            background: #0d6efd;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(50px, -50px) scale(1.1); }
            50% { transform: translate(0, 50px) scale(1); }
            75% { transform: translate(-50px, -25px) scale(0.9); }
        }

        /* Road Lines */
        .road-line {
            position: fixed;
            left: 0;
            width: 100%;
            height: 3px;
            background: repeating-linear-gradient(90deg, transparent, transparent 50px, rgba(255,193,7,0.5) 50px, rgba(255,193,7,0.5) 100px);
            animation: moveLine 2s linear infinite;
            z-index: 1;
        }

        .road-top { top: 100px; }
        .road-bottom { bottom: 100px; animation-direction: reverse; }

        @keyframes moveLine {
            0% { background-position: 0 0; }
            100% { background-position: 100px 0; }
        }

        /* Animated Cars */
        .car-container {
            position: fixed;
            z-index: 2;
            animation: drive 10s linear infinite;
        }

        .car-top {
            top: 35px;
            animation-duration: 12s;
        }

        .car-bottom {
            bottom: 35px;
            animation-direction: reverse;
            animation-duration: 15s;
            animation-delay: -3s;
        }

        @keyframes drive {
            0% { left: -250px; }
            100% { left: calc(100% + 250px); }
        }

        .car {
            width: 200px;
            height: 100px;
            position: relative;
        }

        .car-body {
            position: absolute;
            bottom: 20px;
            width: 180px;
            height: 50px;
            background: linear-gradient(180deg, #dc3545 0%, #a71d2a 100%);
            border-radius: 15px 50px 10px 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4), inset 0 -5px 10px rgba(0,0,0,0.2);
        }

        .car-body::before {
            content: '';
            position: absolute;
            top: -30px;
            left: 30px;
            width: 90px;
            height: 35px;
            background: linear-gradient(180deg, #dc3545 0%, #a71d2a 100%);
            border-radius: 20px 20px 0 0;
        }

        .car-window {
            position: absolute;
            top: -22px;
            left: 45px;
            width: 60px;
            height: 25px;
            background: linear-gradient(180deg, #87CEEB 0%, #4682B4 100%);
            border-radius: 10px 10px 0 0;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.2);
        }

        .car-window::after {
            content: '';
            position: absolute;
            top: 0;
            right: -20px;
            width: 30px;
            height: 25px;
            background: linear-gradient(180deg, #87CEEB 0%, #4682B4 100%);
            border-radius: 0 10px 0 0;
        }

        .wheel {
            position: absolute;
            bottom: 0;
            width: 40px;
            height: 40px;
            background: linear-gradient(145deg, #333 0%, #111 100%);
            border-radius: 50%;
            border: 5px solid #555;
            animation: spin 0.3s linear infinite;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }

        .wheel::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 15px;
            height: 15px;
            background: #777;
            border-radius: 50%;
            border: 3px solid #999;
        }

        .wheel.front { right: 25px; }
        .wheel.back { left: 25px; }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .headlight {
            position: absolute;
            bottom: 28px;
            right: -3px;
            width: 12px;
            height: 18px;
            background: #ffc107;
            border-radius: 0 6px 6px 0;
            box-shadow: 0 0 20px #ffc107, 0 0 40px #ffc107, 50px 0 80px rgba(255,193,7,0.3);
            animation: flicker 3s ease-in-out infinite;
        }

        .taillight {
            position: absolute;
            bottom: 28px;
            left: -3px;
            width: 12px;
            height: 18px;
            background: #ff0000;
            border-radius: 6px 0 0 6px;
            box-shadow: 0 0 15px #ff0000;
        }

        @keyframes flicker {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        /* Main Container */
        .main-wrapper {
            position: relative;
            z-index: 10;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 140px 20px;
        }

        .glass-card {
            width: 100%;
            max-width: 700px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            box-shadow: 
                0 25px 80px rgba(0,0,0,0.4),
                0 0 0 1px rgba(255,255,255,0.1),
                inset 0 0 80px rgba(255,255,255,0.1);
            overflow: hidden;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #dc3545 0%, #b02a37 50%, #7b1c25 100%);
            padding: 50px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .icon-wrapper {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 3px solid rgba(255,255,255,0.3);
            position: relative;
            animation: iconPulse 2s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(255,255,255,0.4); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 20px rgba(255,255,255,0); }
        }

        .icon-wrapper svg {
            width: 50px;
            height: 50px;
            stroke: white;
            stroke-width: 2;
        }

        .header h1 {
            color: white;
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 8px;
            position: relative;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 15px;
            position: relative;
        }

        /* Content */
        .content {
            padding: 30px;
        }

        /* Schedule Card */
        .schedule-card {
            background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%);
            border: 2px solid #ffd54f;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }

        .schedule-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #ffc107, #ff8f00, #ffc107);
        }

        .schedule-title {
            font-size: 14px;
            font-weight: 700;
            color: #856404;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .schedule-title i {
            font-size: 20px;
            color: #ffc107;
        }

        .schedule-dates {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .date-box {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }

        .date-box:hover {
            transform: translateY(-5px);
        }

        .date-box .label {
            font-size: 11px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .date-box .date {
            font-size: 18px;
            font-weight: 700;
            color: #222;
            margin-bottom: 4px;
        }

        .date-box .time {
            font-size: 14px;
            font-weight: 600;
            color: #666;
        }

        .date-box.start .date { color: #dc3545; }
        .date-box.end .date { color: #28a745; }

        /* Countdown */
        .countdown-section {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .countdown-title {
            color: rgba(255,255,255,0.7);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 20px;
            text-align: center;
        }

        .countdown {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .countdown-item {
            text-align: center;
            padding: 20px 10px;
            background: rgba(255,255,255,0.08);
            border-radius: 15px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: transform 0.3s ease;
        }

        .countdown-item:hover {
            transform: scale(1.05);
            background: rgba(255,255,255,0.12);
        }

        .countdown-item .number {
            font-size: 32px;
            font-weight: 800;
            color: #ffc107;
            line-height: 1;
            text-shadow: 0 0 20px rgba(255,193,7,0.5);
        }

        .countdown-item .label {
            font-size: 11px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            margin-top: 8px;
            letter-spacing: 1px;
        }

        /* Info Section */
        .info-section {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .info-title {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-title i {
            color: #0d6efd;
        }

        .info-text {
            color: #555;
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .features-list {
            list-style: none;
            padding: 0;
        }

        .features-list li {
            padding: 12px 0;
            font-size: 14px;
            color: #555;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .features-list li:last-child { border-bottom: none; }
        .features-list li i { font-size: 16px; }
        .features-list li i.warning { color: #dc3545; }
        .features-list li i.success { color: #28a745; }

        /* Contact Card */
        .contact-card {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .contact-header {
            color: white;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .contact-subtitle {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            margin-bottom: 20px;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .contact-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .contact-btn:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .contact-btn i {
            color: #ffc107;
            font-size: 18px;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .footer-text {
            color: #888;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .footer-brand {
            color: #0d6efd;
            font-weight: 700;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .glass-card { border-radius: 20px; }
            .header { padding: 40px 20px; }
            .header h1 { font-size: 24px; }
            .icon-wrapper { width: 80px; height: 80px; }
            .icon-wrapper svg { width: 40px; height: 40px; }
            .content { padding: 20px; }
            .schedule-dates { grid-template-columns: 1fr; }
            .countdown { gap: 10px; }
            .countdown-item { padding: 15px 8px; }
            .countdown-item .number { font-size: 24px; }
            .contact-grid { grid-template-columns: 1fr; }
            .car { width: 150px; height: 75px; }
            .car-body { width: 130px; height: 40px; bottom: 15px; }
            .car-body::before { top: -20px; left: 20px; width: 60px; height: 25px; }
            .car-window { top: -15px; left: 30px; width: 40px; height: 18px; }
            .car-window::after { width: 20px; height: 18px; right: -12px; }
            .wheel { width: 30px; height: 30px; border-width: 4px; }
            .wheel.front { right: 18px; }
            .wheel.back { left: 18px; }
            .headlight, .taillight { width: 8px; height: 12px; bottom: 22px; }
        }
    </style>
</head>
<body>
    <!-- Background Shapes -->
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>

    <!-- Road Lines -->
    <div class="road-line road-top"></div>
    <div class="road-line road-bottom"></div>

    <!-- Animated Cars -->
    <div class="car-container car-top">
        <div class="car">
            <div class="car-body">
                <div class="car-window"></div>
                <div class="headlight"></div>
                <div class="taillight"></div>
            </div>
            <div class="wheel back"></div>
            <div class="wheel front"></div>
        </div>
    </div>

    <div class="car-container car-bottom">
        <div class="car">
            <div class="car-body">
                <div class="car-window"></div>
                <div class="headlight"></div>
                <div class="taillight"></div>
            </div>
            <div class="wheel back"></div>
            <div class="wheel front"></div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-wrapper">
        <div class="glass-card">
            <!-- Header -->
            <div class="header">
                <div class="icon-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                    </svg>
                </div>
                <h1>System Under Maintenance</h1>
                <p>We're performing scheduled upgrades to serve you better</p>
            </div>

            <!-- Content -->
            <div class="content">
                <!-- Schedule -->
                <div class="schedule-card">
                    <div class="schedule-title">
                        <i class="fas fa-calendar-alt"></i> Maintenance Schedule
                    </div>
                    <div class="schedule-dates">
                        <div class="date-box start">
                            <div class="label">Start</div>
                            <div class="date">February 27, 2026</div>
                            <div class="time">9:00 AM</div>
                        </div>
                        <div class="date-box end">
                            <div class="label">End</div>
                            <div class="date">March 1, 2026</div>
                            <div class="time">11:59 PM</div>
                        </div>
                    </div>
                </div>

                <!-- Countdown -->
                <div class="countdown-section">
                    <div class="countdown-title">Time Remaining</div>
                    <div class="countdown">
                        <div class="countdown-item">
                            <div class="number" id="days">0</div>
                            <div class="label">Days</div>
                        </div>
                        <div class="countdown-item">
                            <div class="number" id="hours">0</div>
                            <div class="label">Hours</div>
                        </div>
                        <div class="countdown-item">
                            <div class="number" id="minutes">0</div>
                            <div class="label">Minutes</div>
                        </div>
                        <div class="countdown-item">
                            <div class="number" id="seconds">0</div>
                            <div class="label">Seconds</div>
                        </div>
                    </div>
                </div>

                <!-- Info Section -->
                <div class="info-section">
                    <div class="info-title">
                        <i class="fas fa-info-circle"></i> Important Notice
                    </div>
                    <p class="info-text">
                        We are currently performing scheduled maintenance to improve the performance and reliability of the LOKA Fleet Management System. During this time, the system will be temporarily unavailable.
                    </p>
                    <ul class="features-list">
                        <li><i class="fas fa-times-circle warning"></i> System will be temporarily unavailable</li>
                        <li><i class="fas fa-times-circle warning"></i> No vehicle reservations can be processed</li>
                        <li><i class="fas fa-times-circle warning"></i> Login and authentication services are paused</li>
                        <li><i class="fas fa-check-circle success"></i> All existing data will be preserved safely</li>
                        <li><i class="fas fa-check-circle success"></i> System will be fully restored after maintenance</li>
                    </ul>
                </div>

                <!-- Contact -->
                <div class="contact-card">
                    <div class="contact-header">
                        <i class="fas fa-user-shield"></i> Contact JE LITE
                    </div>
                    <div class="contact-subtitle">Have questions? Feel free to reach out for details.</div>
                    <div class="contact-grid">
                        <a href="mailto:jelite.demo@gmail.com" class="contact-btn">
                            <i class="fas fa-envelope"></i>
                            <span>jelite.demo@gmail.com</span>
                        </a>
                        <a href="tel:+639926316210" class="contact-btn">
                            <i class="fas fa-phone"></i>
                            <span>+63 992 631 6210</span>
                        </a>
                    </div>
                </div>

                <!-- Footer -->
                <div class="footer">
                    <p class="footer-text">Thank you for your patience and understanding.</p>
                    <p class="footer-brand">LOKA Fleet Management &bull; Department of Information and Communications Technology</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const endDate = new Date('March 1, 2026 23:59:59').getTime();

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = endDate - now;

            if (distance < 0) {
                document.getElementById('days').parentElement.innerHTML = '<span style="color:#28a745;font-weight:700;">Done!</span>';
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById('days').textContent = days;
            document.getElementById('hours').textContent = hours;
            document.getElementById('minutes').textContent = minutes;
            document.getElementById('seconds').textContent = seconds;
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    </script>
</body>
</html>
