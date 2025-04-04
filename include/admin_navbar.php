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
                <a href="admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>

            <li class="menu-category">User Management</li>
            <li class="menu-item has-dropdown" onclick="toggleSubmenu(this)">
                <span><i class="fas fa-users"></i> PG Guests</span>
                <i class="fas fa-chevron-down"></i>
            </li>
            <ul class="submenu">
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-list"></i> All Guests</li>
                </a>
                <a href="../admin/users.php">
                    <li class="submenu-item"><i class="fas fa-id-card"></i> Guest Profiles</li>
                </a>
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-phone"></i> Emergency Contacts</li>
                </a>
            </ul>

            <li class="menu-item">
                <a href="TODO"><i class="fas fa-user-shield"></i> Admin Users</a>
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

            <li class="menu-item has-dropdown" onclick="toggleSubmenu(this)">
                <span><i class="fas fa-money-bill-wave"></i> Payments</span>
                <i class="fas fa-chevron-down"></i>
            </li>
            <ul class="submenu">
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-file-invoice"></i> Invoices</li>
                </a>
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-piggy-bank"></i> Security Deposits</li>
                </a>
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-history"></i> Payment History</li>
                </a>
            </ul>

            <li class="menu-item has-dropdown" onclick="toggleSubmenu(this)">
                <span><i class="fas fa-utensils"></i> Food Services</span>
                <i class="fas fa-chevron-down"></i>
            </li>
            <ul class="submenu">
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-clipboard-list"></i> Food Menu</li>
                </a>
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-carrot"></i> Meal Plans</li>
                </a>
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-concierge-bell"></i> Special Requests</li>
                </a>
            </ul>

            <li class="menu-item has-dropdown" onclick="toggleSubmenu(this)">
                <span><i class="fas fa-cog"></i> Other Services</span>
                <i class="fas fa-chevron-down"></i>
            </li>
            <ul class="submenu">
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-tshirt"></i> Laundry</li>
                </a>
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-broom"></i> Housekeeping</li>
                </a>
            </ul>

            <li class="menu-item">
                <a href="TODO"><i class="fas fa-clipboard-user"></i> Log Visitors</a>
            </li>

            <li class="menu-category">Communication</li>
            <li class="menu-item">
                <a href="TODO"><i class="fas fa-bell"></i> Notifications</a>
            </li>
            <li class="menu-item">
                <a href="TODO"><i class="fas fa-bullhorn"></i> Announcements</a>
            </li>

            <li class="menu-category">Reports</li>
            <li class="menu-item has-dropdown" onclick="toggleSubmenu(this)">
                <span><i class="fas fa-chart-bar"></i> Reports & Analytics</span>
                <i class="fas fa-chevron-down"></i>
            </li>
            <ul class="submenu">
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-dollar-sign"></i> Revenue & Expense Reports</li>
                </a>
                <a href="TODO">
                    <li class="submenu-item"><i class="fas fa-percentage"></i> Occupancy Reports</li>
                </a>
                <a href="TODO">
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
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </li>
        </ul>
    </aside>

    <div id="mainContent" class="main-content expanded">
        <!-- Your main content here -->
    </div>

    <script>
        const toggleSidebar = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const overlay = document.getElementById('overlay');

        function toggleNav() {
            sidebar.classList.toggle('sidebar-hidden');
            mainContent.classList.toggle('expanded');
            overlay.classList.toggle('active');
            
            if (!sidebar.classList.contains('sidebar-hidden')) {
                disableMainContentInteraction();
            } else {
                enableMainContentInteraction();
            }
        }

        function disableMainContentInteraction() {
            const links = mainContent.querySelectorAll('a, button, input, select, textarea');
            links.forEach(link => {
                link.setAttribute('tabindex', '-1');
                link.setAttribute('aria-hidden', 'true');
            });
        }

        function enableMainContentInteraction() {
            const links = mainContent.querySelectorAll('a, button, input, select, textarea');
            links.forEach(link => {
                link.removeAttribute('tabindex');
                link.removeAttribute('aria-hidden');
            });
        }

        toggleSidebar.addEventListener('click', toggleNav);
        overlay.addEventListener('click', toggleNav);

        function toggleSubmenu(element) {
            const submenu = element.nextElementSibling;
            element.classList.toggle('open');
            submenu.classList.toggle('active');

            const allSubmenus = document.querySelectorAll('.submenu.active');
            const allDropdowns = document.querySelectorAll('.has-dropdown.open');

            allSubmenus.forEach(menu => {
                if (menu !== submenu) {
                    menu.classList.remove('active');
                }
            });

            allDropdowns.forEach(dropdown => {
                if (dropdown !== element) {
                    dropdown.classList.remove('open');
                }
            });
        }

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.has-dropdown') && !event.target.closest('.submenu')) {
                const allSubmenus = document.querySelectorAll('.submenu.active');
                const allDropdowns = document.querySelectorAll('.has-dropdown.open');

                allSubmenus.forEach(menu => {
                    menu.classList.remove('active');
                });

                allDropdowns.forEach(dropdown => {
                    dropdown.classList.remove('open');
                });
            }
        });

        const adminProfile = document.querySelector('.admin-profile');
        if (adminProfile) {
            adminProfile.addEventListener('click', function () {
                alert('TODO maybe add something here');
            });
        }

        const notificationBtn = document.querySelector('.quick-action-btn:nth-child(1)');
        if (notificationBtn) {
            notificationBtn.addEventListener('click', function () {
                alert('Notifications would appear here');
            });
        }

        const messageBtn = document.querySelector('.quick-action-btn:nth-child(2)');
        if (messageBtn) {
            messageBtn.addEventListener('click', function () {
                alert('Messages would appear here');
            });
        }

        const settingsBtn = document.querySelector('.quick-action-btn:nth-child(3)');
        if (settingsBtn) {
            settingsBtn.addEventListener('click', function () {
                alert('Quick settings would appear here');
            });
        }

        sidebar.classList.add('sidebar-hidden');
    </script>
</body>

</html>
