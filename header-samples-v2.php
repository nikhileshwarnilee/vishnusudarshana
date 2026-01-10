<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Header Design Samples</title>
    <link rel="icon" type="image/png" href="assets/images/logo/logo-icon.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }

        .samples-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-title {
            text-align: center;
            margin: 30px 0 40px 0;
            font-size: 28px;
            color: #2c3e50;
            font-weight: bold;
        }

        .sample-title {
            font-size: 18px;
            color: #333;
            margin: 40px 0 10px 0;
            padding-bottom: 5px;
            border-left: 4px solid #d4af37;
            padding-left: 10px;
        }

        .header-sample {
            background: white;
            margin-bottom: 40px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .sample-label {
            background: linear-gradient(135deg, #d4af37 0%, #b8960f 100%);
            color: white;
            padding: 12px 20px;
            font-weight: bold;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .design-number {
            background: rgba(255, 255, 255, 0.3);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
        }

        .logo-img {
            height: 40px;
            max-width: 180px;
            object-fit: contain;
        }

        /* ============== DESIGN 1: Classic Centered ============== */
        .design-1 {
            background: linear-gradient(180deg, #ffffff 0%, #f9f9f9 100%);
            border-bottom: 3px solid #d4af37;
            padding: 20px 30px;
        }

        .design-1-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .design-1 .nav-menu {
            display: flex;
            gap: 35px;
            list-style: none;
            justify-content: center;
        }

        .design-1 .nav-menu a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            position: relative;
            transition: color 0.3s;
        }

        .design-1 .nav-menu a::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 0;
            height: 3px;
            background: #d4af37;
            transition: width 0.3s;
        }

        .design-1 .nav-menu a:hover {
            color: #d4af37;
        }

        .design-1 .nav-menu a:hover::after {
            width: 100%;
        }

        .design-1 .lang-btn {
            background: #d4af37;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .design-1 .lang-btn:hover {
            background: #b8960f;
        }

        /* ============== DESIGN 2: Left-Right Split ============== */
        .design-2 {
            background: linear-gradient(90deg, #4a235a 0%, #6a3a7a 100%);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .design-2 .nav-menu {
            display: flex;
            gap: 25px;
            list-style: none;
            margin: 0 auto;
        }

        .design-2 .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            padding: 8px 0;
        }

        .design-2 .nav-menu a:hover {
            color: #d4af37;
            border-bottom: 3px solid #d4af37;
        }

        .design-2 .lang-btn {
            background: #d4af37;
            border: none;
            color: #4a235a;
            padding: 10px 18px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
        }

        /* ============== DESIGN 3: Horizontal Stacked ============== */
        .design-3 {
            background: linear-gradient(135deg, #1a472a 0%, #2d6e44 100%);
            padding: 0;
        }

        .design-3-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 30px;
            border-bottom: 2px solid #d4af37;
        }

        .design-3-bottom {
            display: flex;
            gap: 30px;
            padding: 12px 30px;
            background: rgba(0, 0, 0, 0.2);
        }

        .design-3 .nav-menu {
            display: flex;
            gap: 30px;
            list-style: none;
        }

        .design-3 .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: color 0.3s;
        }

        .design-3 .nav-menu a:hover {
            color: #d4af37;
        }

        .design-3 .lang-btn {
            background: #d4af37;
            border: none;
            color: #1a472a;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }

        /* ============== DESIGN 4: Sticky Top Bar + Main Header ============== */
        .design-4 {
            background: white;
        }

        .design-4-topbar {
            background: #2c3e50;
            padding: 8px 30px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .design-4-topbar a, .design-4-topbar button {
            color: white;
            text-decoration: none;
            font-size: 12px;
            cursor: pointer;
            border: none;
            background: none;
            transition: color 0.3s;
        }

        .design-4-topbar a:hover, .design-4-topbar button:hover {
            color: #d4af37;
        }

        .design-4-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            border-bottom: 3px solid #d4af37;
        }

        .design-4 .nav-menu {
            display: flex;
            gap: 28px;
            list-style: none;
            flex: 1;
            justify-content: center;
        }

        .design-4 .nav-menu a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: color 0.3s;
        }

        .design-4 .nav-menu a:hover {
            color: #d4af37;
        }

        .design-4 .lang-btn {
            background: #d4af37;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
        }

        /* ============== DESIGN 5: Mega Banner ============== */
        .design-5 {
            background: linear-gradient(135deg, #8b3a3a 0%, #a84c4c 100%);
            padding: 25px 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .design-5-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 40px;
        }

        .design-5-left {
            flex: 1;
        }

        .design-5-center {
            flex: 1;
        }

        .design-5-right {
            flex: 1;
            text-align: right;
        }

        .design-5 .nav-menu {
            display: flex;
            gap: 25px;
            list-style: none;
            justify-content: center;
        }

        .design-5 .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            padding: 8px 12px;
        }

        .design-5 .nav-menu a:hover {
            background: rgba(212, 175, 55, 0.2);
            border-radius: 5px;
            color: #d4af37;
        }

        .design-5 .lang-btn {
            background: white;
            border: 2px solid #d4af37;
            color: #8b3a3a;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }

        /* ============== DESIGN 6: Modern Cards ============== */
        .design-6 {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            padding: 25px 30px;
        }

        .design-6-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 30px;
        }

        .design-6 .nav-menu {
            display: flex;
            gap: 15px;
            list-style: none;
            flex: 1;
            justify-content: center;
        }

        .design-6 .nav-menu li {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .design-6 .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: block;
            padding: 12px 20px;
            transition: all 0.3s;
        }

        .design-6 .nav-menu a:hover {
            background: #d4af37;
            color: #1e3c72;
        }

        .design-6 .lang-btn {
            background: #d4af37;
            border: none;
            color: #1e3c72;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s;
        }

        .design-6 .lang-btn:hover {
            transform: translateY(-2px);
        }

        /* ============== DESIGN 7: Minimalist Flat ============== */
        .design-7 {
            background: #ffffff;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e0e0e0;
        }

        .design-7-logo-nav {
            display: flex;
            gap: 40px;
            align-items: center;
            flex: 1;
        }

        .design-7 .nav-menu {
            display: flex;
            gap: 30px;
            list-style: none;
        }

        .design-7 .nav-menu a {
            color: #666;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: color 0.3s;
        }

        .design-7 .nav-menu a:hover {
            color: #d4af37;
        }

        .design-7 .lang-btn {
            background: transparent;
            border: 2px solid #d4af37;
            color: #d4af37;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .design-7 .lang-btn:hover {
            background: #d4af37;
            color: white;
        }

        /* ============== DESIGN 8: Vertical Navigation ============== */
        .design-8 {
            background: linear-gradient(180deg, #3d2817 0%, #5a3d2a 100%);
            padding: 0;
            display: flex;
        }

        .design-8-sidebar {
            background: rgba(0, 0, 0, 0.2);
            padding: 20px 25px;
            border-right: 3px solid #d4af37;
            min-width: 180px;
        }

        .design-8-main {
            flex: 1;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .design-8 .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 15px;
            list-style: none;
        }

        .design-8 .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 15px;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }

        .design-8 .nav-menu a:hover {
            border-left-color: #d4af37;
            color: #d4af37;
        }

        .design-8 .lang-btn {
            background: #d4af37;
            border: none;
            color: #3d2817;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }

        /* ============== DESIGN 9: Top Navigation with Icons ============== */
        .design-9 {
            background: linear-gradient(90deg, #2d5a7b 0%, #4a7ba7 100%);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .design-9 .nav-menu {
            display: flex;
            gap: 20px;
            list-style: none;
            flex: 1;
            justify-content: center;
        }

        .design-9 .nav-menu li {
            text-align: center;
        }

        .design-9 .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            padding: 8px 15px;
        }

        .design-9 .nav-menu a:hover {
            color: #d4af37;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 8px;
        }

        .design-9 .lang-btn {
            background: #d4af37;
            border: none;
            color: #2d5a7b;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }

        /* ============== DESIGN 10: Luxury Centered ============== */
        .design-10 {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            padding: 30px 30px;
            text-align: center;
        }

        .design-10 .logo-wrapper {
            margin-bottom: 20px;
        }

        .design-10 .nav-menu {
            display: flex;
            gap: 40px;
            list-style: none;
            justify-content: center;
            margin-bottom: 20px;
            border-top: 2px solid #d4af37;
            border-bottom: 2px solid #d4af37;
            padding: 20px 0;
        }

        .design-10 .nav-menu a {
            color: #e8e8e8;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: 1px;
            transition: color 0.3s;
            text-transform: uppercase;
            font-size: 13px;
        }

        .design-10 .nav-menu a:hover {
            color: #d4af37;
        }

        .design-10 .lang-btn {
            background: #d4af37;
            border: none;
            color: #1a1a1a;
            padding: 12px 28px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }

        /* ============== DESIGN 11: Tab Style Navigation ============== */
        .design-11 {
            background: linear-gradient(90deg, #5d4e37 0%, #7a6652 100%);
            padding: 0;
        }

        .design-11-top {
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #d4af37;
        }

        .design-11-tabs {
            display: flex;
            gap: 0;
            list-style: none;
            padding: 0 30px;
        }

        .design-11-tabs li {
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .design-11-tabs li.active {
            border-bottom-color: #d4af37;
        }

        .design-11-tabs a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            display: block;
            padding: 15px 20px;
            transition: color 0.3s;
        }

        .design-11-tabs a:hover, .design-11-tabs li.active a {
            color: #d4af37;
        }

        .design-11 .lang-btn {
            background: #d4af37;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }

        /* ============== DESIGN 12: Side-by-Side Logo & Nav ============== */
        .design-12 {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .design-12-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 50px;
        }

        .design-12-logo {
            flex-shrink: 0;
            border-right: 3px solid #d4af37;
            padding-right: 30px;
        }

        .design-12 .nav-menu {
            display: flex;
            gap: 35px;
            list-style: none;
            flex: 1;
        }

        .design-12 .nav-menu a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            position: relative;
            transition: all 0.3s;
        }

        .design-12 .nav-menu a::before {
            content: '';
            position: absolute;
            top: -5px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #d4af37;
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s;
        }

        .design-12 .nav-menu a:hover {
            color: #d4af37;
        }

        .design-12 .nav-menu a:hover::before {
            transform: scaleX(1);
            transform-origin: left;
        }

        .design-12 .lang-btn {
            background: #d4af37;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .design-12 .lang-btn:hover {
            background: #b8960f;
        }

        @media (max-width: 1024px) {
            .design-2, .design-5-wrapper, .design-6-content, .design-7, .design-12-content {
                flex-direction: column;
                gap: 15px;
            }

            .design-2 .nav-menu, .design-5 .nav-menu, .design-6 .nav-menu, 
            .design-7 .nav-menu, .design-12 .nav-menu {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .design-1 .nav-menu, .design-3 .nav-menu, .design-4 .nav-menu,
            .design-5 .nav-menu, .design-6 .nav-menu, .design-7 .nav-menu,
            .design-9 .nav-menu, .design-10 .nav-menu, .design-12 .nav-menu {
                flex-wrap: wrap;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="samples-container">
        <h1 class="page-title">Advanced Header Designs - 12 Unique Layouts</h1>

        <!-- DESIGN 1: Classic Centered -->
        <h3 class="sample-title">Design #1 - Classic Centered</h3>
        <div class="header-sample">
            <div class="sample-label">Centered Logo, Navigation Below <span class="design-number">Design 1</span></div>
            <header class="design-1">
                <div class="design-1-content">
                    <div style="display: flex; justify-content: center;">
                        <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img">
                    </div>
                    <ul class="nav-menu">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#reels">Reels</a></li>
                        <li><a href="#track">Track</a></li>
                    </ul>
                    <button class="lang-btn">🌐 Select Language</button>
                </div>
            </header>
        </div>

        <!-- DESIGN 2: Left-Right Split -->
        <h3 class="sample-title">Design #2 - Left-Right Split</h3>
        <div class="header-sample">
            <div class="sample-label">Logo Left, Nav Center, Button Right <span class="design-number">Design 2</span></div>
            <header class="design-2">
                <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img">
                <ul class="nav-menu">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#reels">Reels</a></li>
                    <li><a href="#track">Track</a></li>
                </ul>
                <button class="lang-btn">🌐 Language</button>
            </header>
        </div>

        <!-- DESIGN 3: Horizontal Stacked -->
        <h3 class="sample-title">Design #3 - Horizontal Stacked</h3>
        <div class="header-sample">
            <div class="sample-label">Two-Row Layout with Accent Bar <span class="design-number">Design 3</span></div>
            <header class="design-3">
                <div class="design-3-top">
                    <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img">
                    <button class="lang-btn">🌐 Language</button>
                </div>
                <div class="design-3-bottom">
                    <ul class="nav-menu">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#reels">Reels</a></li>
                        <li><a href="#track">Track</a></li>
                    </ul>
                </div>
            </header>
        </div>

        <!-- DESIGN 4: Sticky Top Bar + Main Header -->
        <h3 class="sample-title">Design #4 - Sticky Top Bar + Main</h3>
        <div class="header-sample">
            <div class="sample-label">Info Bar + Main Navigation <span class="design-number">Design 4</span></div>
            <header class="design-4">
                <div style="width: 100%;">
                    <div class="design-4-topbar">
                        <span>📞 Support</span>
                        <span>📧 Contact</span>
                        <button>🔐 Login</button>
                    </div>
                    <div class="design-4-main">
                        <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img" style="max-width: 120px;">
                        <ul class="nav-menu">
                            <li><a href="#home">Home</a></li>
                            <li><a href="#services">Services</a></li>
                            <li><a href="#reels">Reels</a></li>
                            <li><a href="#track">Track</a></li>
                        </ul>
                        <button class="lang-btn">🌐 Language</button>
                    </div>
                </div>
            </header>
        </div>

        <!-- DESIGN 5: Mega Banner -->
        <h3 class="sample-title">Design #5 - Mega Banner</h3>
        <div class="header-sample">
            <div class="sample-label">Three-Column Banner Layout <span class="design-number">Design 5</span></div>
            <header class="design-5">
                <div class="design-5-wrapper">
                    <div class="design-5-left">
                        <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img">
                    </div>
                    <div class="design-5-center">
                        <ul class="nav-menu">
                            <li><a href="#home">Home</a></li>
                            <li><a href="#services">Services</a></li>
                            <li><a href="#reels">Reels</a></li>
                            <li><a href="#track">Track</a></li>
                        </ul>
                    </div>
                    <div class="design-5-right">
                        <button class="lang-btn">🌐 Language</button>
                    </div>
                </div>
            </header>
        </div>

        <!-- DESIGN 6: Modern Cards -->
        <h3 class="sample-title">Design #6 - Modern Cards</h3>
        <div class="header-sample">
            <div class="sample-label">Card-Based Navigation Items <span class="design-number">Design 6</span></div>
            <header class="design-6">
                <div class="design-6-content">
                    <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img">
                    <ul class="nav-menu">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#reels">Reels</a></li>
                        <li><a href="#track">Track</a></li>
                    </ul>
                    <button class="lang-btn">🌐 Language</button>
                </div>
            </header>
        </div>

        <!-- DESIGN 7: Minimalist Flat -->
        <h3 class="sample-title">Design #7 - Minimalist Flat</h3>
        <div class="header-sample">
            <div class="sample-label">Clean & Minimal Single Row <span class="design-number">Design 7</span></div>
            <header class="design-7">
                <div class="design-7-logo-nav">
                    <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img" style="max-width: 140px;">
                    <ul class="nav-menu">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#reels">Reels</a></li>
                        <li><a href="#track">Track</a></li>
                    </ul>
                </div>
                <button class="lang-btn">🌐 Language</button>
            </header>
        </div>

        <!-- DESIGN 8: Vertical Navigation -->
        <h3 class="sample-title">Design #8 - Vertical Navigation</h3>
        <div class="header-sample">
            <div class="sample-label">Sidebar with Vertical Menu <span class="design-number">Design 8</span></div>
            <header class="design-8">
                <div class="design-8-sidebar">
                    <ul class="nav-menu">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#reels">Reels</a></li>
                        <li><a href="#track">Track</a></li>
                    </ul>
                </div>
                <div class="design-8-main">
                    <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img" style="max-width: 140px;">
                    <button class="lang-btn">🌐 Language</button>
                </div>
            </header>
        </div>

        <!-- DESIGN 9: Navigation with Icons -->
        <h3 class="sample-title">Design #9 - Icon-Style Navigation</h3>
        <div class="header-sample">
            <div class="sample-label">Centered with Icon Indicators <span class="design-number">Design 9</span></div>
            <header class="design-9">
                <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img" style="max-width: 140px;">
                <ul class="nav-menu">
                    <li><a href="#home">🏠 Home</a></li>
                    <li><a href="#services">⭐ Services</a></li>
                    <li><a href="#reels">🎬 Reels</a></li>
                    <li><a href="#track">📍 Track</a></li>
                </ul>
                <button class="lang-btn">🌐 Language</button>
            </header>
        </div>

        <!-- DESIGN 10: Luxury Centered -->
        <h3 class="sample-title">Design #10 - Luxury Centered</h3>
        <div class="header-sample">
            <div class="sample-label">Premium Stacked Layout <span class="design-number">Design 10</span></div>
            <header class="design-10">
                <div class="design-10">
                    <div class="design-10 logo-wrapper">
                        <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img">
                    </div>
                    <ul class="nav-menu">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#reels">Reels</a></li>
                        <li><a href="#track">Track</a></li>
                    </ul>
                    <div style="text-align: center;">
                        <button class="lang-btn">🌐 Select Language</button>
                    </div>
                </div>
            </header>
        </div>

        <!-- DESIGN 11: Tab Style Navigation -->
        <h3 class="sample-title">Design #11 - Tab Style Navigation</h3>
        <div class="header-sample">
            <div class="sample-label">Tab-Styled Menu Items <span class="design-number">Design 11</span></div>
            <header class="design-11">
                <div class="design-11-top">
                    <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img" style="max-width: 120px;">
                    <button class="lang-btn">🌐 Language</button>
                </div>
                <ul class="design-11-tabs">
                    <li class="active"><a href="#home">Home</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#reels">Reels</a></li>
                    <li><a href="#track">Track</a></li>
                </ul>
            </header>
        </div>

        <!-- DESIGN 12: Side-by-Side Logo & Nav -->
        <h3 class="sample-title">Design #12 - Side-by-Side with Divider</h3>
        <div class="header-sample">
            <div class="sample-label">Logo Section | Navigation Section <span class="design-number">Design 12</span></div>
            <header class="design-12">
                <div class="design-12-content">
                    <div class="design-12-logo">
                        <img src="assets/images/logo/logomain.png" alt="Logo" class="logo-img" style="max-width: 140px;">
                    </div>
                    <ul class="nav-menu">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#reels">Reels</a></li>
                        <li><a href="#track">Track</a></li>
                    </ul>
                    <button class="lang-btn">🌐 Language</button>
                </div>
            </header>
        </div>

        <div style="text-align: center; margin-top: 60px; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
            <h3 style="color: #2c3e50; margin-bottom: 10px;">Which design do you prefer?</h3>
            <p style="color: #666;">Tell me the design number (1-12) and I'll integrate it into your original header.php with the Google Translate functionality and language popup.</p>
        </div>
    </div>
</body>
</html>
