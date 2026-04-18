<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的个人主页 | PHP 开发工程师</title>
    <meta name="description" content="沈超 - PHP 后端开发工程师个人主页。展示专业技能与精选项目经验，欢迎联系合作与工作机会。">
    <meta name="theme-color" content="#3498db">
    <meta name="color-scheme" content="light">
    
    <!-- 引入 CSS -->
    <link rel="stylesheet" type="text/css" href="{$hostUrl}/static/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="{$hostUrl}/static/css/font-awesome.css">
    <link rel="stylesheet" type="text/css" href="{$hostUrl}/static/css/home.css">
    
    <!-- 引入 Tailwind CSS (用于 AI 组件) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- 引入 JS -->
    <script src="{$hostUrl}/static/js/jquery.js"></script>
    <script src="{$hostUrl}/static/js/bootstrap.js"></script>

    <style>
        /* ==================== 全局样式 ==================== */
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --text-color: #333;
            --bg-light: #f4f7f6;
            --surface: rgba(255, 255, 255, 0.78);
            --surface-strong: rgba(255, 255, 255, 0.92);
            --border: rgba(15, 23, 42, 0.10);
            --shadow-sm: 0 10px 24px rgba(15, 23, 42, 0.10);
            --shadow-md: 0 18px 42px rgba(15, 23, 42, 0.14);
            --radius: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "PingFang SC", "Microsoft YaHei", sans-serif;
            line-height: 1.65;
            color: var(--text-color);
            background:
                radial-gradient(1200px 800px at 15% -10%, rgba(52, 152, 219, 0.16), transparent 60%),
                radial-gradient(900px 600px at 90% 0%, rgba(44, 62, 80, 0.12), transparent 55%),
                linear-gradient(180deg, #fbfdff 0%, #ffffff 55%, #f7fafc 100%);
        }
        
        html { scroll-behavior: smooth; }
        #chatMessages {  text-align: left;}
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            * { 
                transition-duration: 0.01ms !important; 
                animation-duration: 0.01ms !important; 
                animation-iteration-count: 1 !important; 
                scroll-behavior: auto !important; 
            }
        }
        
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        :where(section[id]) { scroll-margin-top: 80px; }

        /* ==================== 导航栏 ==================== */
        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        header.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 6px 30px rgba(15, 23, 42, 0.12);
        }
        
        .nav-container {
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }
        
        .nav-logo {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--primary-color);
            text-decoration: none;
            letter-spacing: 0.5px;
            transition: transform 0.3s ease;
        }
        
        .nav-logo:hover {
            transform: scale(1.05);
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
            gap: 8px;
            margin: 0;
            padding: 0;
        }
        
        .nav-item {
            margin: 0;
        }
        
        .nav-link {
            color: rgba(15, 23, 42, 0.75);
            font-weight: 600;
            font-size: 0.95rem;
            padding: 10px 18px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: block;
        }
        
        .nav-link:hover {
            color: var(--primary-color);
            background: rgba(52, 152, 219, 0.08);
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            color: #fff;
            background: linear-gradient(135deg, var(--primary-color), #2980b9);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .nav-toggle {
            display: none;
            flex-direction: column;
            gap: 5px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        
        .nav-toggle:hover {
            background: rgba(52, 152, 219, 0.1);
        }
        
        .nav-toggle span {
            display: block;
            width: 25px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px;
            transition: all 0.3s ease;
        }
        
        .nav-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(5px, 5px);
        }
        
        .nav-toggle.active span:nth-child(2) {
            opacity: 0;
        }
        
        .nav-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -6px);
        }
        
        @media (max-width: 767px) {
            .nav-toggle {
                display: flex;
            }
            
            .nav-menu {
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(14px);
                flex-direction: column;
                padding: 20px;
                gap: 10px;
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.1);
                transform: translateY(-100%);
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }
            
            .nav-menu.active {
                transform: translateY(0);
                opacity: 1;
                visibility: visible;
            }
            
            .nav-link {
                text-align: center;
                padding: 14px 20px;
                font-size: 1rem;
            }
        }

        /* ==================== Hero 区域 ==================== */
        .hero {
            min-height: 100vh;
            background:
                radial-gradient(900px 420px at 50% 20%, rgba(52, 152, 219, 0.32), transparent 60%),
                linear-gradient(rgba(15, 23, 42, 0.78), rgba(15, 23, 42, 0.78)),
                url('https://images.unsplash.com/photo-1555066931-4365d14bab8c?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #fff;
            padding: 100px 20px 70px;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, transparent 50%, rgba(44, 62, 80, 0.1) 100%);
            pointer-events: none;
        }
        
        .hero .container {
            position: relative;
            z-index: 1;
            animation: fadeInUp 1s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .hero h1 { 
            font-size: 3rem; 
            margin-bottom: 15px;
            font-weight: 900;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .hero p { 
            font-size: 1.5rem; 
            margin-bottom: 15px; 
            opacity: 0.95;
            font-weight: 500;
        }
        
        .hero p:first-child { 
            margin-bottom: 5px; 
        }
        
        .hero p > small { 
            font-size: 1rem;
            opacity: 0.85;
            display: block;
            margin-top: 10px;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #3aa0ff, #2f80ed);
            color: #fff;
            padding: 14px 32px;
            border-radius: 999px;
            font-weight: 800;
            letter-spacing: 0.5px;
            box-shadow: 0 12px 28px rgba(47, 128, 237, 0.35);
            transition: all 0.3s ease;
            margin-top: 20px;
            text-transform: uppercase;
            font-size: 0.95rem;
        }
        
        .btn:hover { 
            transform: translateY(-3px); 
            filter: brightness(1.1); 
            box-shadow: 0 16px 40px rgba(47, 128, 237, 0.5);
        }

        /* ==================== 通用 Section ==================== */
        section { 
            padding: 100px 0;
            position: relative;
        }
        
        .section-title { 
            text-align: center; 
            margin-bottom: 60px;
            position: relative;
        }
        
        .section-title h2 { 
            font-size: 2.8rem; 
            color: var(--secondary-color); 
            margin-bottom: 15px;
            font-weight: 900;
            position: relative;
            display: inline-block;
        }
        
        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), #2980b9);
            border-radius: 2px;
        }
        
        .section-title span { 
            display: none;
        }
        
        section#about, section#contact { 
            background: transparent; 
        }

        /* ==================== 关于我 & 技能 ==================== */
        .text-muted { 
            --bs-text-opacity: 1; 
            color: #6c757d !important; 
        }
        
        /* 自定义5列布局 */
        @media (min-width: 992px) {
            .col-lg-2-4 {
                flex: 0 0 20%;
                max-width: 20%;
            }
        }
        
        .about-content { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 50px; 
            align-items: center;
            margin-top: 40px;
        }
        
        .about-text { 
            flex: 1; 
            min-width: 300px; 
        }
        
        .about-text h3 {
            font-size: 1.8rem;
            color: var(--secondary-color);
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .about-text p {
            font-size: 1.05rem;
            line-height: 1.8;
            color: #555;
        }
        
        .skills { 
            flex: 1; 
            min-width: 300px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }

        .skill-tag {
            display: inline-block;
            background: linear-gradient(135deg, #e1ecf4, #d4e6f1);
            color: #39739d;
            padding: 10px 18px;
            border-radius: 25px;
            font-weight: 600;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: default;
            font-size: 0.9rem;
        }
        
        .skill-tag:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.2);
        }
        
        .skill-tag.highlight { 
            background: linear-gradient(135deg, var(--primary-color), #2980b9);
            color: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .skill-tag.highlight:hover {
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .skill-card { 
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            background: var(--surface-strong);
            height: 100%;
            min-height: 280px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .skill-card:hover { 
            transform: translateY(-8px);
            box-shadow: var(--shadow-md);
        }
        
        .skill-card i {
            transition: transform 0.3s ease;
        }
        
        .skill-card:hover i {
            transform: scale(1.1);
        }
        
        .skill-card h5 {
            font-weight: 700;
            color: var(--secondary-color);
            margin: 15px 0 10px;
            min-height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .skill-card p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 60px;
        }

        /* ==================== 项目轮播 ==================== */
        :root {
            --project-card-h: 440px;
            --project-img-h: 180px;
        }
        
        .project-card-link {
            display: block;
            text-decoration: none;
            color: inherit;
            outline: none;
        }
        
        .project-card-link:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 4px;
            border-radius: var(--radius);
        }
        
        #projects {
            background:
                radial-gradient(1000px 520px at 10% 0%, rgba(52, 152, 219, 0.12), transparent 55%),
                radial-gradient(900px 520px at 90% 10%, rgba(44, 62, 80, 0.10), transparent 58%),
                var(--bg-light);
            padding-bottom: 30px;
        }
        #projects .section-title{ margin-bottom: 20px;}
        
        .carousel-wrapper {
            position: relative;
            overflow: hidden;
            padding: 20px 0;
        }
        
        .carousel-track-container {
            overflow: hidden;
            width: 100%;
            min-height: var(--project-card-h);
        }
        
        .carousel-track {
            display: flex;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            padding: 0;
            will-change: transform;
        }
        
        .carousel-card {
            flex: 0 0 100%;
            padding: 30px 15px;
            box-sizing: border-box;
        }

        .project-card {
            background: var(--surface-strong);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            height: var(--project-card-h);
            display: flex;
            flex-direction: column;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .project-card:hover { 
            transform: translateY(-8px);
            box-shadow: var(--shadow-md);
        }
        
        .card-img {
            height: var(--project-img-h);
            background:
                radial-gradient(420px 240px at 30% 20%, rgba(58, 160, 255, 0.26), transparent 60%),
                linear-gradient(135deg, rgba(44, 62, 80, 0.10), rgba(44, 62, 80, 0.02));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 3.5rem;
            transition: all 0.3s ease;
        }
        
        .project-card:hover .card-img {
            background:
                radial-gradient(420px 240px at 30% 20%, rgba(58, 160, 255, 0.35), transparent 60%),
                linear-gradient(135deg, rgba(52, 152, 219, 0.15), rgba(52, 152, 219, 0.05));
        }
        
        .card-body {
            padding: 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 0;
        }
        
        .card-body h3 { 
            margin-bottom: 8px; 
            color: var(--secondary-color);
            font-size: 1.3rem;
            font-weight: 700;
        }
        
        .tech-stack { 
            font-size: 0.9rem; 
            color: #666;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .tech-stack i { 
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .project-card .card-body > p:last-child {
            margin-bottom: 0;
            overflow: hidden;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 4;
            line-clamp: 4;
            color: #555;
            line-height: 1.7;
            font-size: 0.95rem;
        }

        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, var(--primary-color), #2980b9);
            color: #fff;
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10;
            font-size: 1.3rem;
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .carousel-btn:hover {
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 12px 30px rgba(52, 152, 219, 0.4);
        }
        
        .prev-btn { left: 15px; }
        .next-btn { right: 15px; }

        /* ==================== 页脚 ==================== */
        footer { 
            background: linear-gradient(135deg, var(--secondary-color), #1a252f);
            color: #fff; 
            text-align: center; 
            padding: 30px 20px;
            position: relative;
        }
        
        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), #2980b9, var(--primary-color));
        }
        
        footer p {
            margin: 0;
            font-size: 0.95rem;
            opacity: 0.9;
        }

        /* ==================== AI 对话组件动画 ==================== */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .animate-pulse-custom {
            animation: pulse 1.5s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        .typing-indicator span {
            animation: typing 1.4s infinite ease-in-out;
        }
        .typing-indicator span:nth-child(1) { animation-delay: 0s; }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }
        
        .message-enter {
            animation: slideIn 0.3s ease-out forwards;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ==================== 媒体查询 ==================== */
        @media (min-width: 768px) {
            .carousel-card { flex-basis: 33.333%; }
            .hero h1 { font-size: 4rem; }
            .hero p { font-size: 1.8rem; }
            .section-title h2 { font-size: 3rem; }
        }
        
        @media (max-width: 767px) {
            .hero {
                padding: 90px 15px 60px;
            }
            .hero h1 { font-size: 2.2rem; }
            .hero p { font-size: 1.2rem; }
            :root { --project-card-h: 460px; }
            section { padding: 70px 0; }
            .section-title h2 { font-size: 2.2rem; }
            .about-content { gap: 30px; }
            .carousel-btn {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
            }
            .prev-btn { left: 10px; }
            .next-btn { right: 10px; }
        }

    </style>
</head>
<body>
<a href="#home" class="visually-hidden-focusable">跳到主要内容</a>

<!-- ==================== 导航栏 ==================== -->
<header id="mainHeader">
    <div class="nav-container">
        <a href="#home" class="nav-logo">正在找工作</a>
        
        <button class="nav-toggle" id="navToggle" aria-label="切换导航菜单">
            <span></span>
            <span></span>
            <span></span>
        </button>
        
        <ul class="nav-menu" id="navMenu">
            <li class="nav-item">
                <a href="#home" class="nav-link active">首页</a>
            </li>
            <li class="nav-item">
                <a href="#about" class="nav-link">专业技能</a>
            </li>
            <li class="nav-item">
                <a href="#projects" class="nav-link">精选项目</a>
            </li>
            <li class="nav-item">
                <a href="#contact" class="nav-link">联系我</a>
            </li>
            <li class="nav-item">
                <a href="http://www.shenchao.me/swoper" class="nav-link">日常管理系统</a>
            </li>
            <li class="nav-item">
                <a href="http://www.shenchao.me/swoper/site/longData" class="nav-link">数据展示</a>
            </li>
        </ul>
    </div>
</header>

<!-- ==================== Hero 区域 ==================== -->
<section id="home" class="hero">
    <div class="container">
        <h1>你好，我是沈超</h1>
        <p>PHP 后端开发工程师</p>
        </p>
        <a href="#projects" class="btn">查看我的作品</a>
    </div>
</section>

<!-- ==================== 关于我 ==================== -->
<section id="about">
    <div class="container">
        <div class="section-title">
            <h2>专业技能</h2>
            <span></span>
        </div>

        <!-- 技能卡片 -->
        <div class="row text-center">
            <div class="col-lg-2-4 col-md-3 col-sm-6 mb-4">
                <div class="p-4 bg-white shadow rounded skill-card">
                    <i class="fab fa-php fa-3x text-primary mb-3"></i>
                    <h5>PHP开发</h5>
                    <p>熟练使用Yii、ThinkPHP框架进行高效开发</p>
                </div>
            </div>
            <div class="col-lg-2-4 col-md-3 col-sm-6 mb-4">
                <div class="p-4 bg-white shadow rounded skill-card">
                    <i class="fas fa-database fa-3x text-success mb-3"></i>
                    <h5>MySQL</h5>
                    <p>数据库设计、优化与维护<br>&nbsp;</p>
                </div>
            </div>
            <div class="col-lg-2-4 col-md-3 col-sm-6 mb-4">
                <div class="p-4 bg-white shadow rounded skill-card">
                    <i class="fab fa-html5 fa-3x text-danger mb-3"></i>
                    <h5>前端技术</h5>
                    <p>HTML5+CSS3+jQuery开发响应式网站</p>
                </div>
            </div>
            <div class="col-lg-2-4 col-md-3 col-sm-6 mb-4">
                <div class="p-4 bg-white shadow rounded skill-card">
                    <i class="fas fa-laptop-code fa-3x text-info mb-3"></i>
                    <h5>独立开发</h5>
                    <p>从需求到上线全流程掌控<br>&nbsp;</p>
                </div>
            </div>
            <div class="col-lg-2-4 col-md-3 col-sm-6 mb-4">
                <div class="p-4 bg-white shadow rounded skill-card">
                    <i class="fas fa-robot fa-3x text-warning mb-3"></i>
                    <h5>AI 工具</h5>
                    <p>熟练使用通义灵码等AI编程助手提升开发效率</p>
                </div>
            </div>
        </div>

        <!-- 详细介绍 -->
        <div class="about-content">
            <div class="about-text">
                <h3>专注于高性能后端开发</h3>
                <p style="margin-top: 15px;">
                    拥有多年 PHP 开发经验，擅长构建高并发、可扩展的 Web 应用。
                    深入理解面向对象编程（OOP），熟悉设计模式。
                    能够独立负责从数据库设计到接口开发的全流程。
                </p>
            </div>
            <div class="skills">
                <span class="skill-tag highlight">PHP 7/8</span>
                <span class="skill-tag highlight">Yii Framework</span>
                <span class="skill-tag highlight">ThinkPHP</span>
                <span class="skill-tag highlight">MySQL</span>
                <span class="skill-tag">jQuery</span>
                <span class="skill-tag">HTML5/CSS3</span>
                <span class="skill-tag">Git</span>
            </div>
        </div>
    </div>
</section>

<!-- ==================== 项目轮播 ==================== -->
<section id="projects">
    <div class="container">
        <div class="section-title">
            <h2>精选项目</h2>
            <span></span>
        </div>

        <div class="carousel-wrapper">
            <button class="carousel-btn prev-btn" type="button" aria-label="上一页项目">
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
            </button>

            <div class="carousel-track-container">
                <ul class="carousel-track">
                    <!-- 项目 1 -->
                    <li class="carousel-card">
                        <a href="http://www.shenchao.me/swoper" target="_blank" class="project-card-link">
                            <div class="project-card">
                                <div class="card-img"><i class="fas fa-file-invoice-dollar"></i></div>
                                <div class="card-body">
                                    <h3>企业管理系统</h3>
                                    <p class="tech-stack">
                                        <i class="fab fa-php"></i> Yii1 + 
                                        <i class="fas fa-database"></i> MySQL
                                    </p>
                                    <p>基于 Yii1 框架开发的企业管理系统，实现了模块化开发，提升了代码复用率，优化了数据库查询性能。</p>
                                </div>
                            </div>
                        </a>
                    </li>

                    <!-- 项目 2 -->
                    <li class="carousel-card">
                        <a href="javascript:void(0);" class="project-card-link">
                            <div class="project-card">
                                <div class="card-img"><i class="fab fa-weixin"></i></div>
                                <div class="card-body">
                                    <h3>企业微信开发</h3>
                                    <p class="tech-stack">
                                        <i class="fab fa-php"></i> Yii1 + 
                                        <i class="fab fa-js"></i> jQuery
                                    </p>
                                    <p>集成企业微信API的企业内部应用</p>
                                </div>
                            </div>
                        </a>
                    </li>

                    <!-- 项目 3 -->
                    <li class="carousel-card">
                        <a href="http://www.shenchao.me/swoper/site/longData" target="_blank" class="project-card-link">
                            <div class="project-card">
                                <div class="card-img"><i class="fas fa-chart-line"></i></div>
                                <div class="card-body">
                                    <h3>数据可视化大屏后台</h3>
                                    <p class="tech-stack">
                                        <i class="fab fa-php"></i> Yii1 + 
                                        <i class="fas fa-chart-bar"></i> ECharts
                                    </p>
                                    <p>原生 PHP 结合 jQuery 开发的数据报表系统，支持动态生成图表，每日处理百万级数据记录。</p>
                                </div>
                            </div>
                        </a>
                    </li>

                    <!-- 项目 4 -->
                    <li class="carousel-card">
                        <a href="javascript:void(0);" class="project-card-link">
                            <div class="project-card">
                                <div class="card-img"><i class="fas fa-mobile-alt"></i></div>
                                <div class="card-body">
                                    <h3>API 接口服务平台</h3>
                                    <p class="tech-stack">
                                        <i class="fab fa-php"></i> Yii1 + 
                                        <i class="fas fa-server"></i> Redis
                                    </p>
                                    <p>基于 Swoole 扩展的高性能 API 网关，为移动端提供毫秒级响应服务。</p>
                                </div>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>

            <button class="carousel-btn next-btn" type="button" aria-label="下一页项目">
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</section>

<!-- ==================== AI 智能助手 ==================== -->
<section id="contact" style="background: #fff;">
    <div class="container" style="text-align: center;">
        <div class="section-title">
            <h2>联系我</h2>
            <span></span>
        </div>
        <div class="row">
            <div class="col-lg-12">
                <?php if(session('?userList')): ?>
                    <!-- AI 对话组件 -->
                    <input type="hidden" id="currentUserId" value="{$token}">
                    
                    <div id="chatContainer" class="bg-white rounded-2xl shadow-xl overflow-hidden mb-6" style="max-width: 800px; margin: 0 auto;">
                        <!-- 聊天头部 -->
                        <div id="chatHeader" class="bg-gradient-to-r from-primary to-secondary p-4 text-white">
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-semibold">实时对话</h2>
                                <div class="flex items-center space-x-2">
                                    <span class="flex w-3 h-3">
                                        <span class="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-green-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                    </span>
                                    <span class="text-sm">在线</span>
                                </div>
                            </div>
                        </div>

                        <!-- 消息区域 -->
                        <div id="chatMessages" class="h-96 overflow-y-auto p-4 space-y-4 bg-gray-50">
                            <div class="flex justify-start message-enter">
                                <div class="max-w-xs lg:max-w-md bg-white rounded-2xl rounded-tl-none p-4 shadow-sm border">
                                    <div class="flex items-center mb-2">
                                        <i class="fas fa-robot text-primary mr-2"></i>
                                        <span class="font-medium text-gray-700">AI助手</span>
                                    </div>
                                    <p class="text-gray-600">您好！我是沈超的应聘助手，随时为您解答招聘问题。请问您有什么想问沈超的吗？</p>
                                </div>
                            </div>
                        </div>

                        <!-- 输入区域 -->
                        <div class="bg-white rounded-2xl shadow-xl p-4">
                            <div class="flex items-end space-x-3">
                                <div class="flex-1">
                                    <label for="messageInput" class="block text-sm font-medium text-gray-700 mb-1">
                                        输入您的问题
                                    </label>
                                    <div class="relative">
                                        <textarea
                                            id="messageInput"
                                            rows="3"
                                            placeholder="请输入您的问题... (支持Enter发送)"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent resize-none transition-all"
                                        ></textarea>
                                        <div class="absolute bottom-2 right-2 flex items-center space-x-2">
                                            <span id="charCount" class="text-xs text-gray-500">0/500</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col space-y-2 pb-1">
                                    <button
                                        id="sendButton"
                                        class="bg-gradient-to-r from-primary to-secondary hover:from-primary hover:to-primary text-white p-3 rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                        disabled
                                    >
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                    <button
                                        id="clearButton"
                                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 p-3 rounded-xl transition-colors"
                                        title="清空对话"
                                    >
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- 加载状态 -->
                            <div id="loadingIndicator" class="hidden mt-4 flex items-center justify-center p-3 bg-blue-50 rounded-xl">
                                <div class="typing-indicator flex space-x-1 text-primary">
                                    <span class="w-2 h-2 bg-current rounded-full"></span>
                                    <span class="w-2 h-2 bg-current rounded-full"></span>
                                    <span class="w-2 h-2 bg-current rounded-full"></span>
                                </div>
                                <span class="ml-3 text-primary font-medium">AI正在思考中...</span>
                                <button id="cancelRequest" class="ml-4 text-red-500 hover:text-red-700">
                                    <i class="fas fa-times-circle"></i> 取消
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tailwind 配置 -->
                    <script>
                        tailwind.config = {
                            theme: {
                                extend: {
                                    colors: {
                                        primary: '#6366f1',
                                        secondary: '#8b5cf6',
                                        accent: '#ec4899',
                                        dark: '#1e293b',
                                        light: '#f8fafc'
                                    }
                                }
                            }
                        }
                    </script>

                    <!-- AI 对话脚本 -->
                    <script src="{$hostUrl}/static/js/aiScript.js"></script>
                <?php else: ?>
                    <p><a href="{:url('index/ai')}" class="btn btn-primary">登录以使用 AI 助手</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ==================== 页脚 ==================== -->
<footer>
    <p>&copy; 2023 [沈超]. All Rights Reserved.</p>
</footer>

<!-- ==================== JavaScript ==================== -->
<script>
    $(document).ready(function() {
        // ==================== 移动端菜单切换 ====================
        $('#navToggle').on('click', function() {
            $(this).toggleClass('active');
            $('#navMenu').toggleClass('active');
        });
        
        // 点击导航链接后关闭移动端菜单
        $('.nav-link').on('click', function() {
            if ($(window).width() < 768) {
                $('#navToggle').removeClass('active');
                $('#navMenu').removeClass('active');
            }
            
            // 更新激活状态
            $('.nav-link').removeClass('active');
            $(this).addClass('active');
        });
        
        // 点击页面其他地方关闭菜单
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#mainHeader').length && $(window).width() < 768) {
                $('#navToggle').removeClass('active');
                $('#navMenu').removeClass('active');
            }
        });
        
        // ==================== 滚动时高亮导航 ====================
        $(window).on('scroll', function() {
            const scrollPos = $(window).scrollTop() + 100;
            
            // Header 滚动效果
            if ($(window).scrollTop() > 50) {
                $('#mainHeader').addClass('scrolled');
            } else {
                $('#mainHeader').removeClass('scrolled');
            }
            
            // 根据滚动位置高亮对应导航
            $('section[id]').each(function() {
                const sectionTop = $(this).offset().top;
                const sectionHeight = $(this).outerHeight();
                const sectionId = $(this).attr('id');
                
                if (scrollPos >= sectionTop && scrollPos < sectionTop + sectionHeight) {
                    $('.nav-link').removeClass('active');
                    $(`.nav-link[href="#${sectionId}"]`).addClass('active');
                }
            });
        });
        
        // ==================== 技能卡片悬停效果 ====================
        $('.skill-card').hover(
            function() {
                $(this).addClass('shadow-lg');
            },
            function() {
                $(this).removeClass('shadow-lg');
            }
        );
        
        // ==================== 轮播图逻辑 ====================
        const track = $('.carousel-track');
        const cards = $('.carousel-card');
        const nextBtn = $('.next-btn');
        const prevBtn = $('.prev-btn');
        let currentIndex = 0;

        function getVisibleCards() {
            return $(window).width() >= 768 ? 3 : 1;
        }

        function updateCarousel() {
            const visibleCards = getVisibleCards();
            const totalCards = cards.length;
            const maxIndex = totalCards - visibleCards;

            if (currentIndex < 0) currentIndex = 0;
            if (currentIndex > maxIndex) currentIndex = maxIndex;

            const movePercent = $(window).width() >= 768 ? 33.333 : 100;
            track.css('transform', 'translateX(-' + (currentIndex * movePercent) + '%)');
        }

        nextBtn.click(function() {
            const visibleCards = getVisibleCards();
            if (currentIndex < cards.length - visibleCards) {
                currentIndex++;
            } else {
                currentIndex = 0;
            }
            updateCarousel();
        });

        prevBtn.click(function() {
            if (currentIndex > 0) {
                currentIndex--;
                updateCarousel();
            }
        });

        $(window).resize(function() {
            currentIndex = 0;
            updateCarousel();
        });

        updateCarousel();

        // ==================== 平滑滚动 ====================
        $('a[href^="#"]').on('click', function(event) {
            const target = $(this.getAttribute('href'));
            if (target.length) {
                event.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 70
                }, 200, 'swing');
            }
        });

        // 触发一次滚动事件以设置初始状态
        $(window).trigger('scroll');
    });
</script>
</body>
</html>
