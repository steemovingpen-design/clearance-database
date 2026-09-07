<?php
// index.php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ElevateClear | Digital Biometric Clearance System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0a1e2c 0%, #0f2b3d 60%, #173b52 100%);
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            padding: 24px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.4rem;
            font-weight: 700;
            color: #ffffff;
            text-decoration: none;
        }
        .brand i {
            color: #48c9b0;
            font-size: 1.6rem;
        }
        .nav-links {
            display: flex;
            gap: 16px;
        }
        .nav-btn {
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .nav-btn-light {
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .nav-btn-light:hover {
            background: rgba(255,255,255,0.2);
        }

        .hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 60px 20px 40px;
            max-width: 1100px;
            margin: 0 auto;
        }
        .hero-badge {
            background: rgba(72, 201, 176, 0.15);
            color: #48c9b0;
            border: 1px solid rgba(72, 201, 176, 0.3);
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .hero h1 {
            font-size: 2.8rem;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }
        .hero h1 span {
            background: linear-gradient(135deg, #48c9b0, #2ecc71);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .hero p {
            font-size: 1.1rem;
            color: #94a3b8;
            max-width: 680px;
            margin-bottom: 48px;
            line-height: 1.6;
        }

        .portals-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            width: 100%;
            margin-bottom: 40px;
        }
        @media (max-width: 860px) {
            .portals-grid { grid-template-columns: 1fr; }
            .hero h1 { font-size: 2.1rem; }
        }

        .portal-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 32px 26px;
            text-align: left;
            transition: 0.25s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .portal-card:hover {
            transform: translateY(-6px);
            background: rgba(255, 255, 255, 0.08);
            border-color: #48c9b0;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 20px;
        }
        .icon-student { background: rgba(44, 125, 160, 0.25); color: #48c9b0; }
        .icon-officer { background: rgba(46, 204, 113, 0.25); color: #2ecc71; }
        .icon-admin { background: rgba(243, 156, 18, 0.25); color: #f39c12; }

        .portal-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #ffffff;
        }
        .portal-card p {
            font-size: 0.88rem;
            color: #94a3b8;
            line-height: 1.5;
            margin-bottom: 24px;
            min-height: 42px;
        }
        .btn-card {
            width: 100%;
            padding: 12px 18px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.88rem;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-student { background: #2c7da0; color: white; }
        .btn-student:hover { background: #1f5e7e; }
        .btn-officer { background: #27ae60; color: white; }
        .btn-officer:hover { background: #1e8449; }
        .btn-admin { background: #d35400; color: white; }
        .btn-admin:hover { background: #ba4a00; }

        .footer {
            text-align: center;
            padding: 24px 20px;
            font-size: 0.8rem;
            color: #64748b;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="index.php" class="brand">
        <i class="fas fa-shield-alt"></i>
        <span>ElevateClear</span>
    </a>
    <div class="nav-links">
        <a href="students/register.php" class="nav-btn nav-btn-light"><i class="fas fa-user-plus"></i> Student Register</a>
        <a href="students/login.php" class="nav-btn nav-btn-light"><i class="fas fa-sign-in-alt"></i> Student Login</a>
        <a href="officers/login.php" class="nav-btn nav-btn-light"><i class="fas fa-user-tie"></i> Officer Desk</a>
    </div>
</nav>

<div class="hero">
    <div class="hero-badge"><i class="fas fa-fingerprint"></i> Next-Gen Biometric Authentication</div>
    <h1>Institutional Electronic <span>Clearance Suite</span></h1>
    <p>Seamlessly process academic clearance with AI face verification, live document endorsement, and cryptographically verified digital clearance certificates.</p>

    <div class="portals-grid">
        <div class="portal-card">
            <div>
                <div class="card-icon icon-student"><i class="fas fa-user-graduate"></i></div>
                <h3>Student Portal</h3>
                <p>Register your face identity, submit fee & departmental receipts, and track approval velocity.</p>
            </div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <a href="students/login.php" class="btn-card btn-student"><i class="fas fa-camera"></i> Student Face Login</a>
                <a href="students/register.php" style="text-align:center; font-size:0.8rem; color:#48c9b0; text-decoration:none;">New Student? Register here &rarr;</a>
            </div>
        </div>

        <div class="portal-card">
            <div>
                <div class="card-icon icon-officer"><i class="fas fa-id-card-alt"></i></div>
                <h3>Clearance Officers</h3>
                <p>Bursary, Library, Dean, Medical, SUG, and Departmental officers approve or reject student files in real-time.</p>
            </div>
            <a href="officers/login.php" class="btn-card btn-officer"><i class="fas fa-lock"></i> Officer Desk Sign In</a>
        </div>

        <div class="portal-card">
            <div>
                <div class="card-icon icon-admin"><i class="fas fa-cogs"></i></div>
                <h3>Administrator Console</h3>
                <p>Provision and manage clearance desk officers, upload bulk CSV rosters, and monitor system metrics.</p>
            </div>
            <a href="admin/manage_officers.php" class="btn-card btn-admin"><i class="fas fa-users-cog"></i> Manage Officers</a>
        </div>
    </div>
</div>

<footer class="footer">
    ElevateClear &copy; <?php echo date('Y'); ?> &bull; Automated Biometric Student Clearance & Verification System
</footer>

</body>
</html>
