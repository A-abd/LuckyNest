<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
/*TODO MOVE THIS CSS INTO styles.css*/

        * {
            margin: 0;
            padding: 0;
        }

        .navbar {
            background-color: teal;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            padding: 0 20px;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
        }

        .logo-container {
            display: flex;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            font-weight: bold;
        }

        .sidebar {
            position: fixed;
            height: calc(100vh - 70px);
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            top: 70px;
            left: 0;
            width: 250px;
            background-color: white;
            transition: transform 0.3s ease;
            z-index: 999;
        }

        .sidebar-hidden {
            transform: translateX(-100%);
        }

        .menu-category {
            color: #888;
            font-size: 12px;
            text-transform: uppercase;
            padding: 15px 20px 5px;
            letter-spacing: 1px;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            text-decoration: none;
            cursor: pointer;
        }

        .menu-item:hover {
            background-color: rgba(98, 0, 234, 0.1);
        }

        .menu-item.active {
            background-color: rgba(98, 0, 234, 0.15);
            border-left: 4px solid #6200EA;
        }

        .menu-item a {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .submenu {
            list-style: none;
            padding-left: 30px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .submenu.active {
            max-height: 200px;
        }

        .submenu-item {
            padding: 10px 20px;
            text-decoration: none;
            display: block;
            font-size: 14px;
            cursor: pointer;
            color: inherit;
        }

        .submenu a {
            text-decoration: none;
            color: inherit;
        }

        .submenu-item:hover {
            color: #6200EA;
        }

        .badge {
            background-color: #FF3E1D;
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 11px;
            margin-left: 10px;
        }

        .quick-actions {
            display: flex;
            gap: 15px;
            margin-right: 20px;
        }

        .quick-action-btn {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            position: relative;
        }

        .toggle-sidebar {
            cursor: pointer;
            font-size: 24px;
            margin-right: 15px;
            color: white;
        }

        .main-content {
            margin-top: 70px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .overlay {
            position: fixed;
            top: 70px;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 998;
            display: none;
        }

        .overlay.active {
            display: block;
        }

        .menu-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .has-dropdown i.fa-chevron-down {
            margin-left: auto;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="logo-container">
            <div class="toggle-sidebar" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </div>
            <div class="logo">
                <h1>LuckyNest</h1>
            </div>
        </div>
    </nav>

    <aside class="sidebar sidebar-hidden" id="sidebar">
        <ul class="sidebar-menu">
            <li class="menu-item"><a href="../guest/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>

            <li class="menu-category">Payments</li>
            <li class="menu-item"><a href="../guest/payments_page.php"><i class="fas fa-money-bill-wave"></i> Payments
                    Page</a></li>
            <li class="menu-item"><a href="../guest/deposit.php"><i class="fas fa-piggy-bank"></i> Security Deposit</a>
            </li>

            <li class="menu-category">Services</li>
            <li class="menu-item has-dropdown" onclick="window.LuckyNest.toggleSubmenu(this)">
                <span><i class="fas fa-chart-bar"></i> Food Services</span>
                <i class="fas fa-chevron-down"></i>
            </li>
            <ul class="submenu">
                <a href="../guest/meals.php">
                    <li class="submenu-item"><i class="fas fa-utensils"></i> Meals</li>
                </a>
                <a href="../guest/meal_custom.php">
                    <li class="submenu-item"><i class="fas fa-concierge-bell"></i> Custom Meal Orders</li>
                </a>
            </ul>
            <li class="menu-item">
                <a href="../guest/laundry.php"><i class="fas fa-tshirt"></i> Laundry</a>
            </li>
            <li class="menu-item">
                <a href="../guest/maintenance.php"><i class="fas fa-wrench"></i> Make Maintenance Request</a>
            </li>

            <li class="menu-category">Reports</li>
            <li class="menu-item has-dropdown" onclick="window.LuckyNest.toggleSubmenu(this)">
                <span><i class="fas fa-chart-bar"></i> Reports & Analytics</span>
                <i class="fas fa-chevron-down"></i>
            </li>
            <ul class="submenu">
                <a href="../guest/invoices.php">
                    <li class="submenu-item"><i class="fas fa-chart-bar"></i> Payment Statistics</li>
                </a>
                <a href="../guest/stats_food.php">
                    <li class="submenu-item"><i class="fas fa-hamburger"></i> Food Consumption Statistics</li>
                </a>
            </ul>


            <li class="menu-category">Settings</li>
            <li class="menu-item">
                <a href="../guest/profile.php"><i class="fas fa-user"></i> Your Profile</a>
            </li>
            <li class="menu-item">
                <a href="../guest/settings.php"><i class="fas fa-cog"></i> Settings</a>
            </li>
            <li class="menu-item">
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </aside>

    <div class="overlay" id="overlay"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.LuckyNest.initSidebar();
        });
    </script>
</body>

</html>