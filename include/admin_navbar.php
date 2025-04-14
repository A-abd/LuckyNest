<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

    <div class="overlay" id="overlay"></div>

    <aside class="sidebar sidebar-hidden" id="sidebar">
        <ul class="sidebar-menu">
            <li class="menu-category">Main</li>
            <li class="menu-item active">
                <a href="../admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>

            <li class="menu-category">User Management</li>

            <li class="menu-item">
                <a href="../admin/users.php"><i class="fas fa-users"></i> Guests</a>
            </li>

            <li class="menu-item">
                <a href="../admin/admins.php"><i class="fas fa-user-shield"></i> Admin Users</a>
            </li>
                        
            <li class="menu-item">
                <a href="../admin/create_users.php"><i class="fas fa-user-plus"></i> Create An Account</a>
            </li>

            <li class="menu-category">Property Management</li>
            <li class="menu-item">
                <a href="../admin/rooms.php"><i class="fas fa-door-open"></i> All Rooms</a>
            </li>
            <li class="menu-item">
                <a href="../admin/room_types.php"><i class="fas fa-bed"></i> Room Types</a>
            </li>
            <li class="menu-category">Operations</li>
            <li class="menu-item">
                <a href="../admin/bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a>
            </li>

            <li class="menu-item has-dropdown" onclick="window.LuckyNest.toggleSubmenu(this)">
                <span><i class="fas fa-money-bill-wave"></i> Payments</span>
                <i class="fas fa-chevron-down"></i>
            </li>
            <ul class="submenu">
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-file-invoice"></i> Invoices</li>
                </a>
                <a href="../admin/deposits.php">
                    <li class="submenu-item"><i class="fas fa-piggy-bank"></i> Security Deposits</li>
                </a>
            </ul>

            <li class="menu-item has-dropdown" onclick="window.LuckyNest.toggleSubmenu(this)">
                <span><i class="fas fa-utensils"></i> Food Services</span>
                <i class="fas fa-chevron-down"></i>
            </li>
            <ul class="submenu">
                <a href="../admin/meals.php">
                    <li class="submenu-item"><i class="fas fa-clipboard-list"></i> Food Menu</li>
                </a>
                <a href="../admin/meal_plans.php">
                    <li class="submenu-item"><i class="fas fa-carrot"></i> Meal Plans</li>
                </a>
                <a href="../admin/meal_assignment.php">
                    <li class="submenu-item"><i class="fas fa-clipboard"></i> Assign Meals to Meal Plan</li>
                </a>
                <a href="../admin/meal_custom_view.php">
                    <li class="submenu-item"><i class="fas fa-concierge-bell"></i> View Special Requests</li>
                </a>
            </ul>

            <li class="menu-item has-dropdown" onclick="window.LuckyNest.toggleSubmenu(this)">
                <span><i class="fas fa-cog"></i> Other Services</span>
                <i class="fas fa-chevron-down"></i>
            </li>
            <ul class="submenu">
                <a href="../admin/laundry.php">
                    <li class="submenu-item"><i class="fas fa-tshirt"></i> Laundry</li>
                </a>
                <a href="../admin/view_maintenance.php">
                    <li class="submenu-item"><i class="fas fa-wrench"></i> View Maintenance Requests</li>
                </a>
            </ul>

            <li class="menu-item">
                <a href="../admin/visitors.php"><i class="fas fa-clipboard-user"></i> Log Visitors</a>
            </li>

            <li class="menu-category">Communication</li>
            <li class="menu-item">
                <a href="TODO"><i class="fas fa-bell"></i> Notifications</a>
            </li>
            <li class="menu-item">
                <a href="TODO"><i class="fas fa-bullhorn"></i> Announcements</a>
            </li>

            <li class="menu-category">Reports</li>
            <li class="menu-item has-dropdown" onclick="window.LuckyNest.toggleSubmenu(this)">
                <span><i class="fas fa-chart-bar"></i> Reports & Analytics</span>
                <i class="fas fa-chevron-down"></i>
            </li>
            <ul class="submenu">
                <a href="../admin/financials.php">
                    <li class="submenu-item"><i class="fas fa-dollar-sign"></i> Revenue & Expense Reports</li>
                </a>
                <a href="../admin/report_occupancy.php">
                    <li class="submenu-item"><i class="fas fa-percentage"></i> Room Occupancy Reports</li>
                </a>
                <a href="../admin/report_pg.php">
                    <li class="submenu-item"><i class="fas fa-percentage"></i> PG Occupany Reports</li>
                </a>
                <a href="../admin/report_food.php">
                    <li class="submenu-item"><i class="fas fa-hamburger"></i> Food Consumption</li>
                </a>
            </ul>

            <li class="menu-category">Settings</li>
            <li class="menu-item">
                <a href="TODO"><i class="fas fa-sliders-h"></i> System Settings</a>
            </li>
            <li class="menu-item">
                <a href="TODO"><i class="fas fa-user-cog"></i> Account Settings</a>
            </li>
            <li class="menu-item">
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </aside>

    <div id="mainContent" class="main-content expanded">
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.LuckyNest.initSidebar();
        });
    </script>
</body>

</html>
