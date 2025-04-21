<?php
session_start();

if ($_SESSION['role'] == 'guest' || !isset($_SESSION['role'])) {
    header('Location: unauthorized.php');
    exit();
}

include __DIR__ . '/../include/db.php';
include __DIR__ . '/../include/pagination.php';

$feedback = '';
$reportData = [];
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'revenue';
$timePeriod = isset($_GET['time_period']) ? $_GET['time_period'] : 'monthly';
$currentYear = date('Y');
$currentMonth = date('m');

// Tax rates (hardcoded)
$VAT_RATE = 0.20;
$INCOME_TAX_RATE = 0.19;
$PROPERTY_TAX_RATE = 0.015;

function generateRevenueReport($conn, $timePeriod, $currentYear, $currentMonth, $VAT_RATE, $INCOME_TAX_RATE)
{
    $reportData = [];

    if ($timePeriod == 'monthly') {
        $query = "SELECT 
                    DATE_FORMAT(payment_date, '%Y-%m') as period,
                    payment_type,
                    SUM(amount) as total_amount
                  FROM payments
                  WHERE DATE_FORMAT(payment_date, '%Y-%m') = :yearMonth
                  GROUP BY period, payment_type
                  ORDER BY period DESc";

        $yearMonth = $currentYear . '-' . str_pad($currentMonth, 2, '0', STR_PAD_LEFT);
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':yearMonth', $yearMonth, PDO::PARAM_STR);
    } else {
        $query = "SELECT 
                    DATE_FORMAT(payment_date, '%Y') as period,
                    payment_type,
                    SUM(amount) as total_amount
                  FROM payments
                  WHERe DATE_FORMAT(payment_date, '%Y') = :year
                  GROUP BY period, payment_type
                  ORDER BY period DESC";

        $stmt = $conn->prepare($query);
        $stmt->bindParam(':year', $currentYear, PDO::PARAM_STR);
    }

    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRevenue = 0;
    $revenueByType = [
        'rent' => 0,
        'meal_plan' => 0,
        'laundry' => 0,
        'deposit' => 0,
        'deposit_refund' => 0
    ];

    foreach ($results as $row) {
        if (isset($row['payment_type']) && array_key_exists($row['payment_type'], $revenueByType)) {
            $revenueByType[$row['payment_type']] += $row['total_amount'];
        }
        $totalRevenue += $row['total_amount'];
    }

    $vatAmount = $totalRevenue * $VAT_RATE;
    $incomeTaxAmount = $totalRevenue * $INCOME_TAX_RATE;
    $netRevenue = $totalRevenue - $vatAmount - $incomeTaxAmount;

    $reportData = [
        'period' => $timePeriod == 'monthly' ? date('F Y', strtotime($currentYear . '-' . $currentMonth . '-01')) : $currentYear,
        'total_revenue' => $totalRevenue,
        'revenue_by_type' => $revenueByType,
        'vat_amount' => $vatAmount,
        'income_tax_amount' => $incomeTaxAmount,
        'net_revenue' => $netRevenue
    ];

    return $reportData;
}

function generateExpenseReport($conn, $timePeriod, $currentYear, $currentMonth, $PROPERTY_TAX_RATE)
{
    $totalRooms = $conn->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    $averageRoomRate = $conn->query("SELECT AVG(rate_monthly) FROM room_types")->fetchColumn();

    // simulatinon of monthly expenses
    $utilities = $totalRooms * 50;
    $maintenance = $totalRooms * 30;
    $staff = 2000 + ($totalRooms * 20);
    $propertyTax = $totalRooms * $averageRoomRate * $PROPERTY_TAX_RATE;

    if ($timePeriod == 'yearly') {
        $utilities *= 12;
        $maintenance *= 12;
        $staff *= 12;
        $propertyTax *= 12;
    }

    $totalExpenses = $utilities + $maintenance + $staff + $propertyTax;

    $reportData = [
        'period' => $timePeriod == 'monthly' ? date('F Y', strtotime($currentYear . '-' . $currentMonth . '-01')) : $currentYear,
        'utilities' => $utilities,
        'maintenance' => $maintenance,
        'staff' => $staff,
        'property_tax' => $propertyTax,
        'total_expenses' => $totalExpenses
    ];

    return $reportData;
}

if ($reportType == 'revenue') {
    $reportData = generateRevenueReport($conn, $timePeriod, $currentYear, $currentMonth, $VAT_RATE, $INCOME_TAX_RATE);
} else {
    $reportData = generateExpenseReport($conn, $timePeriod, $currentYear, $currentMonth, $PROPERTY_TAX_RATE);
}

$profitLossData = [];
if (!empty($reportData)) {
    if ($reportType == 'revenue') {
        $expenseData = generateExpenseReport($conn, $timePeriod, $currentYear, $currentMonth, $PROPERTY_TAX_RATE);
        $profitLossData = [
            'total_revenue' => $reportData['total_revenue'],
            'total_expenses' => $expenseData['total_expenses'],
            'gross_profit' => $reportData['total_revenue'] - $expenseData['total_expenses'],
            'taxes' => $reportData['vat_amount'] + $reportData['income_tax_amount'] + $expenseData['property_tax'],
            'net_profit' => $reportData['net_revenue'] - $expenseData['total_expenses'] + $expenseData['property_tax']
        ];
    } else {
        $revenueData = generateRevenueReport($conn, $timePeriod, $currentYear, $currentMonth, $VAT_RATE, $INCOME_TAX_RATE);
        $profitLossData = [
            'total_revenue' => $revenueData['total_revenue'],
            'total_expenses' => $reportData['total_expenses'],
            'gross_profit' => $revenueData['total_revenue'] - $reportData['total_expenses'],
            'taxes' => $revenueData['vat_amount'] + $revenueData['income_tax_amount'] + $reportData['property_tax'],
            'net_profit' => $revenueData['net_revenue'] - $reportData['total_expenses'] + $reportData['property_tax']
        ];
    }
}

$conn = null;
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&display=swap" />
    <link rel="stylesheet" href="../assets/styles.css">
    <script src="../assets/scripts.js"></script>
    <title>Financial Reports</title>
</head>

<body>
    <?php include "../include/admin_navbar.php"; ?>
    <div class="blur-layer-3"></div>
    <div class="manage-default">
        <h1><a class="title" href="../admin/dashboard.php">LuckyNest</a></h1>

        <div class="content-container">
            <h1>Financial Reports</h1>

            <div class="report-filters">
                <form method="GET" action="">
                    <select name="time_period" onchange="this.form.submit()">
                        <option value="monthly" <?php echo $timePeriod == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="yearly" <?php echo $timePeriod == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </form>
            </div>

            <h2><?php echo $reportData['period']; ?> Financial Summary</h2>

            <div class="financial-summary">
                <?php if (!empty($profitLossData)): ?>
                    <!-- Profit & Loss Card -->
                    <div class="financial-card">
                        <div class="financial-card-header"
                            onclick="window.LuckyNest.toggleFinancialCard('profit-loss-details')">
                            <span>Net Profit/Loss</span>
                            <span
                                class="financial-card-value <?php echo $profitLossData['net_profit'] >= 0 ? 'positive' : 'negative'; ?>">
                                £<?php echo number_format($profitLossData['net_profit'], 2); ?>
                            </span>
                        </div>
                        <div id="profit-loss-details" class="financial-card-body">
                            <table border="0" width="100%">
                                <tr>
                                    <td>Total Revenue</td>
                                    <td class="financial-card-value" align="right">
                                        £<?php echo number_format($profitLossData['total_revenue'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>Total Expenses</td>
                                    <td class="financial-card-value negative" align="right">
                                        £<?php echo number_format($profitLossData['total_expenses'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Gross Profit</strong></td>
                                    <td class="financial-card-value <?php echo $profitLossData['gross_profit'] >= 0 ? 'positive' : 'negative'; ?>"
                                        align="right">
                                        <strong>£<?php echo number_format($profitLossData['gross_profit'], 2); ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Total Taxes</td>
                                    <td class="financial-card-value negative" align="right">
                                        £<?php echo number_format($profitLossData['taxes'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Net Profit/Loss</strong></td>
                                    <td class="financial-card-value <?php echo $profitLossData['net_profit'] >= 0 ? 'positive' : 'negative'; ?>"
                                        align="right">
                                        <strong>£<?php echo number_format($profitLossData['net_profit'], 2); ?></strong>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Revenue Card -->
                <div class="financial-card">
                    <div class="financial-card-header"
                        onclick="window.LuckyNest.toggleFinancialCard('revenue-details')">
                        <span>Total Revenue</span>
                        <span class="financial-card-value">
                            £<?php echo number_format($reportData['total_revenue'] ?? $revenueData['total_revenue'], 2); ?>
                        </span>
                    </div>
                    <div id="revenue-details" class="financial-card-body">
                        <table border="0" width="100%">
                            <tr>
                                <td>Rent Revenue</td>
                                <td class="financial-card-value" align="right">
                                    £<?php echo number_format(($reportData['revenue_by_type']['rent'] ?? $revenueData['revenue_by_type']['rent']), 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Meal Plan Revenue</td>
                                <td class="financial-card-value" align="right">
                                    £<?php echo number_format(($reportData['revenue_by_type']['meal_plan'] ?? $revenueData['revenue_by_type']['meal_plan']), 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Laundry Revenue</td>
                                <td class="financial-card-value" align="right">
                                    £<?php echo number_format(($reportData['revenue_by_type']['laundry'] ?? $revenueData['revenue_by_type']['laundry']), 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Total Gross Revenue</strong></td>
                                <td class="financial-card-value" align="right">
                                    <strong>£<?php echo number_format(($reportData['total_revenue'] ?? $revenueData['total_revenue']), 2); ?></strong>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Taxes Card -->
                <div class="financial-card">
                    <div class="financial-card-header" onclick="window.LuckyNest.toggleFinancialCard('tax-details')">
                        <span>Total Taxes</span>
                        <span class="financial-card-value negative">
                            £<?php
                            $totalTax = ($reportData['vat_amount'] ?? $revenueData['vat_amount']) +
                                ($reportData['income_tax_amount'] ?? $revenueData['income_tax_amount']) +
                                ($expenseData['property_tax'] ?? $reportData['property_tax']);
                            echo number_format($totalTax, 2);
                            ?>
                        </span>
                    </div>
                    <div id="tax-details" class="financial-card-body">
                        <table border="0" width="100%">
                            <tr>
                                <td>VAT (20%)</td>
                                <td class="financial-card-value negative" align="right">
                                    £<?php echo number_format(($reportData['vat_amount'] ?? $revenueData['vat_amount']), 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Income Tax (19%)</td>
                                <td class="financial-card-value negative" align="right">
                                    £<?php echo number_format(($reportData['income_tax_amount'] ?? $revenueData['income_tax_amount']), 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Property Tax (1.5%)</td>
                                <td class="financial-card-value negative" align="right">
                                    £<?php echo number_format(($expenseData['property_tax'] ?? $reportData['property_tax']), 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Total Taxes</strong></td>
                                <td class="financial-card-value negative" align="right">
                                    <strong>£<?php echo number_format($totalTax, 2); ?></strong>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Expenses Card -->
                <div class="financial-card">
                    <div class="financial-card-header"
                        onclick="window.LuckyNest.toggleFinancialCard('expense-details')">
                        <span>Total Expenses</span>
                        <span class="financial-card-value negative">
                            £<?php echo number_format(($expenseData['total_expenses'] ?? $reportData['total_expenses']), 2); ?>
                        </span>
                    </div>
                    <div id="expense-details" class="financial-card-body">
                        <table border="0" width="100%">
                            <tr>
                                <td>Utilities</td>
                                <td class="financial-card-value" align="right">
                                    £<?php echo number_format(($expenseData['utilities'] ?? $reportData['utilities']), 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Maintenance</td>
                                <td class="financial-card-value" align="right">
                                    £<?php echo number_format(($expenseData['maintenance'] ?? $reportData['maintenance']), 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Staff Salaries</td>
                                <td class="financial-card-value" align="right">
                                    £<?php echo number_format(($expenseData['staff'] ?? $reportData['staff']), 2); ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Total Expenses</strong></td>
                                <td class="financial-card-value negative" align="right">
                                    <strong>£<?php echo number_format(($expenseData['total_expenses'] ?? $reportData['total_expenses']), 2); ?></strong>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            window.LuckyNest.initFinancialCards();
        });
    </script>
</body>

</html>