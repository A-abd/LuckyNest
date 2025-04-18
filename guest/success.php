<?php
require __DIR__ . "/../include/db.php";
require __DIR__ . "/../vendor/autoload.php";
require __DIR__ . "/../include/mail_config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_GET['session_id']) || !isset($_GET['payment_type']) || !isset($_GET['reference_id'])) {
    die("Invalid request. Session ID, payment type, or reference ID is missing.");
}

$payment_type = $_GET['payment_type'];
$session_id = $_GET['session_id'];
$reference_id = $_GET['reference_id'];
$invoice_number = date('Ymd') . "_" . $reference_id . "_" . $payment_type;
$pdf_generated = false;
$user_id = null;
$amount = 0;
$description = '';
$item_details = [];

$vat_rate = 0.20;

try {
    // Different queries based on payment type
    if ($payment_type == 'rent') {
        $stmt = $conn->prepare("SELECT b.*, u.forename, u.surname, u.email, u.address, u.phone, r.room_number, 
                               rt.rate_monthly, rt.room_type_name 
                               FROM bookings b 
                               JOIN users u ON b.guest_id = u.user_id 
                               JOIN rooms r ON b.room_id = r.room_id 
                               JOIN room_types rt ON r.room_type_id = rt.room_type_id 
                               WHERE b.booking_id = :reference_id");
        $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            die("Booking not found.");
        }

        $user_id = $data['guest_id'];
        $check_in_date = $data['check_in_date'];
        $check_out_date = $data['check_out_date'];
        $guest_name = $data['forename'] . ' ' . $data['surname'];
        $guest_email = $data['email'];
        $guest_address = $data['address'];
        $guest_phone = $data['phone'];
        $room_number = $data['room_number'];
        $room_type = $data['room_type_name'];
        $amount = floatval($data['rate_monthly']);

        $date1 = new DateTime($check_in_date);
        $date2 = new DateTime($check_out_date);
        $interval = $date1->diff($date2);
        $days = $interval->days;

        $description = "Accommodation: {$room_type} - Room {$room_number} - {$days} days stay";
        $item_details = [
            'item' => $room_type . ' - ' . $days . ' days stay',
            'room_number' => $room_number,
            'start_date' => $check_in_date,
            'end_date' => $check_out_date
        ];

    } elseif ($payment_type == 'meal_plan') {
        $stmt = $conn->prepare("SELECT mpl.*, mp.name AS meal_plan_name, mp.price, 
                               u.forename, u.surname, u.email, u.address, u.phone 
                               FROM meal_plan_user_link mpl 
                               JOIN meal_plans mp ON mpl.meal_plan_id = mp.meal_plan_id 
                               JOIN users u ON mpl.user_id = u.user_id 
                               WHERE mpl.meal_plan_user_link = :reference_id");
        $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            die("Meal plan not found.");
        }

        $user_id = $data['user_id'];
        $guest_name = $data['forename'] . ' ' . $data['surname'];
        $guest_email = $data['email'];
        $guest_address = $data['address'];
        $guest_phone = $data['phone'];
        $meal_plan_name = $data['meal_plan_name'];
        $amount = floatval($data['price']);

        $description = "Meal Plan: {$meal_plan_name}";
        $item_details = [
            'item' => $meal_plan_name,
            'room_number' => 'N/A',
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+30 days'))
        ];

    } elseif ($payment_type == 'laundry') {
        $stmt = $conn->prepare("SELECT lsl.*, ls.date, ls.start_time, ls.price, 
                               u.forename, u.surname, u.email, u.address, u.phone 
                               FROM laundry_slot_user_link lsl 
                               JOIN laundry_slots ls ON lsl.laundry_slot_id = ls.laundry_slot_id 
                               JOIN users u ON lsl.user_id = u.user_id 
                               WHERE lsl.laundry_slot_user_link_id = :reference_id");
        $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            die("Laundry slot not found.");
        }

        $user_id = $data['user_id'];
        $guest_name = $data['forename'] . ' ' . $data['surname'];
        $guest_email = $data['email'];
        $guest_address = $data['address'];
        $guest_phone = $data['phone'];
        $laundry_date = $data['date'];
        $laundry_time = $data['start_time'];
        $amount = floatval($data['price']);

        $description = "Laundry Service: {$laundry_date} at {$laundry_time}";
        $item_details = [
            'item' => 'Laundry Slot',
            'room_number' => 'N/A',
            'start_date' => $laundry_date,
            'end_date' => $laundry_date
        ];
    } elseif ($payment_type == 'deposit') {
        $stmt = $conn->prepare("SELECT b.*, u.forename, u.surname, u.email, u.address, u.phone, r.room_number, 
                               rt.room_type_name, rt.deposit_amount 
                               FROM bookings b 
                               JOIN users u ON b.guest_id = u.user_id 
                               JOIN rooms r ON b.room_id = r.room_id 
                               JOIN room_types rt ON r.room_type_id = rt.room_type_id 
                               WHERE b.booking_id = :reference_id");
        $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            die("Booking not found for deposit.");
        }

        $user_id = $data['guest_id'];
        $check_in_date = $data['check_in_date'];
        $check_out_date = $data['check_out_date'];
        $guest_name = $data['forename'] . ' ' . $data['surname'];
        $guest_email = $data['email'];
        $guest_address = $data['address'];
        $guest_phone = $data['phone'];
        $room_number = $data['room_number'];
        $room_type = $data['room_type_name'];
        $amount = floatval($data['deposit_amount']);

        $description = "Security Deposit: {$room_type} - Room {$room_number}";
        $item_details = [
            'item' => 'Security Deposit - ' . $room_type,
            'room_number' => $room_number,
            'start_date' => $check_in_date,
            'end_date' => $check_out_date
        ];
    } else {
        die("Invalid payment type.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

try {
    // Update the relevant table based on payment type
    if ($payment_type == 'rent') {
        $stmt = $conn->prepare("SELECT booking_is_paid FROM bookings WHERE booking_id = :reference_id");
        $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
        $stmt->execute();
        $isPaid = $stmt->fetchColumn();

        if (!$isPaid) {
            $stmt = $conn->prepare("UPDATE bookings SET booking_is_paid = 1 WHERE booking_id = :reference_id");
            $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    } elseif ($payment_type == 'meal_plan') {
        $stmt = $conn->prepare("SELECT is_paid FROM meal_plan_user_link WHERE meal_plan_user_link = :reference_id");
        $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
        $stmt->execute();
        $isPaid = $stmt->fetchColumn();

        if (!$isPaid) {
            $stmt = $conn->prepare("UPDATE meal_plan_user_link SET is_paid = 1 WHERE meal_plan_user_link = :reference_id");
            $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    } elseif ($payment_type == 'laundry') {
        $stmt = $conn->prepare("SELECT is_paid FROM laundry_slot_user_link WHERE laundry_slot_user_link_id = :reference_id");
        $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
        $stmt->execute();
        $isPaid = $stmt->fetchColumn();

        if (!$isPaid) {
            $stmt = $conn->prepare("UPDATE laundry_slot_user_link SET is_paid = 1 WHERE laundry_slot_user_link_id = :reference_id");
            $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
            $stmt->execute();
        }
    } elseif ($payment_type == 'deposit') {
        $stmt = $conn->prepare("SELECT deposit_id FROM deposits WHERE booking_id = :booking_id");
        $stmt->bindValue(':booking_id', $reference_id, PDO::PARAM_INT);
        $stmt->execute();
        $depositExists = $stmt->fetchColumn();

        if (!$depositExists) {
            $stmt = $conn->prepare("INSERT INTO deposits (booking_id, amount, status, date_paid) 
                                 VALUES (:booking_id, :amount, 'paid', NOW())");
            $stmt->bindValue(':booking_id', $reference_id, PDO::PARAM_INT);
            $stmt->bindValue(':amount', $amount, PDO::PARAM_STR);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("UPDATE deposits SET status = 'paid', date_paid = NOW() WHERE booking_id = :booking_id");
            $stmt->bindValue(':booking_id', $reference_id, PDO::PARAM_INT);
            $stmt->execute();   
        }
    }

    // Check if payment already exists
    $stmt = $conn->prepare("SELECT payment_id FROM payments WHERE stripe_payment_id = :stripe_payment_id");
    $stmt->bindValue(':stripe_payment_id', $session_id, PDO::PARAM_STR);
    $stmt->execute();
    $paymentExists = $stmt->fetchColumn();

    if (!$paymentExists) {
        // Insert into payments table
        $stmt = $conn->prepare("INSERT INTO payments (user_id, reference_id, payment_type, amount, payment_date, stripe_payment_id) 
                               VALUES (:user_id, :reference_id, :payment_type, :amount, :payment_date, :stripe_payment_id)");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':reference_id', $reference_id, PDO::PARAM_INT);
        $stmt->bindValue(':payment_type', $payment_type, PDO::PARAM_STR);
        $stmt->bindValue(':amount', $amount, PDO::PARAM_STR);
        $stmt->bindValue(':payment_date', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':stripe_payment_id', $session_id, PDO::PARAM_STR);
        $stmt->execute();
        $payment_id = $conn->lastInsertId();

        // Insert notification
        $message = "Your payment for " . ucfirst($payment_type) . " has been successfully processed.";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':message', $message, PDO::PARAM_STR);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("SELECT payment_id FROM payments WHERE stripe_payment_id = :stripe_payment_id");
        $stmt->bindValue(':stripe_payment_id', $session_id, PDO::PARAM_STR);
        $stmt->execute();
        $payment_id = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$pdf_filename = 'invoice_' . $invoice_number . '.pdf';
$pdf_path = __DIR__ . '/../invoices/' . $pdf_filename;

// Calculate the inclusvie tax
$net_amount = $amount / (1 + $vat_rate);
$vat_amount = $amount - $net_amount;

if (!file_exists($pdf_path)) {
    if (!file_exists(__DIR__ . '/../invoices/')) {
        mkdir(__DIR__ . '/../invoices/', 0755, true);
    }

    class PDF extends FPDF
    {
        function Header()
        {
            $this->SetFont('Arial', 'B', 18);
            $this->Cell(0, 10, 'LuckyNest Invoice', 0, 1, 'C');
            $this->SetFont('Arial', '', 12);
            $this->Cell(0, 6, 'LuckyNest', 0, 1, 'C');
            $this->Cell(0, 6, '123 Main Street, City, Country', 0, 1, 'C');
            $this->Cell(0, 6, 'Phone: +44 1234 567890  |  Email: info@example.com', 0, 1, 'C');
            $this->Cell(0, 6, 'VAT Registration Number: GB123456789', 0, 1, 'C');
            $this->Ln(10);
        }

        function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(95, 8, 'Invoice To:', 0, 0);
    $pdf->Cell(95, 8, 'Invoice Details:', 0, 1);

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(95, 6, $guest_name, 0, 0);
    $pdf->Cell(35, 6, 'Invoice Number:', 0, 0);
    $pdf->Cell(60, 6, $invoice_number, 0, 1);

    $pdf->Cell(95, 6, $guest_address, 0, 0);
    $pdf->Cell(35, 6, 'Payment Date:', 0, 0);
    $pdf->Cell(60, 6, date('d/m/Y'), 0, 1);

    $pdf->Cell(95, 6, 'Phone: ' . $guest_phone, 0, 0);
    $pdf->Cell(35, 6, 'Reference ID:', 0, 0);
    $pdf->Cell(60, 6, $reference_id, 0, 1);

    $pdf->Cell(95, 6, 'Email: ' . $guest_email, 0, 0);
    $pdf->Cell(35, 6, 'Payment ID:', 0, 0);
    $pdf->Cell(60, 6, $payment_id, 0, 1);

    $pdf->Ln(10);

    $pdf->SetFillColor(235, 235, 235);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(70, 10, 'Description', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Details', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'From', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'To', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Amount', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(70, 10, $description, 1, 0, 'L');
    $pdf->Cell(30, 10, $item_details['room_number'], 1, 0, 'C');
    $pdf->Cell(30, 10, date('d/m/Y', strtotime($item_details['start_date'])), 1, 0, 'C');
    $pdf->Cell(30, 10, date('d/m/Y', strtotime($item_details['end_date'])), 1, 0, 'C');
    $pdf->Cell(30, 10, chr(163) . number_format($net_amount, 2), 1, 1, 'R');

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(160, 10, 'VAT (20%)', 1, 0, 'R');
    $pdf->Cell(30, 10, chr(163) . number_format($vat_amount, 2), 1, 1, 'R');

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(160, 10, 'Total (Inc. VAT)', 1, 0, 'R', true);
    $pdf->Cell(30, 10, chr(163) . number_format($amount, 2), 1, 1, 'R', true);

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 6, 'Prices are inclusive of VAT at 20%', 0, 1);

    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Payment Information', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 6, 'Payment Status: Paid', 0, 1);
    $pdf->Cell(0, 6, 'Payment Method: Credit Card (via Stripe)', 0, 1);
    $pdf->Cell(0, 6, 'Payment Date: ' . date('d/m/Y'), 0, 1);

    $pdf->Ln(10);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Terms and Conditions', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 5, 'Thank you for your business. This invoice serves as confirmation that your payment has been processed successfully. If you have any questions regarding your purchase or this invoice, please contact our customer service team.');

    $pdf->Output('F', $pdf_path);
    $pdf_generated = true;

    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM invoices WHERE payment_id = :payment_id AND user_id = :user_id");
        $stmt->bindValue(':payment_id', $payment_id, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $invoiceExists = $stmt->fetchColumn();

        if (!$invoiceExists) {
            $stmt = $conn->prepare("INSERT INTO invoices (user_id, payment_id, invoice_number, amount, filename, created_at) 
            VALUES (:user_id, :payment_id, :invoice_number, :amount, :filename, :created_at)");
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':payment_id', $payment_id, PDO::PARAM_INT);
            $stmt->bindValue(':invoice_number', $invoice_number, PDO::PARAM_STR);
            $stmt->bindValue(':amount', $amount, PDO::PARAM_STR);
            $stmt->bindValue(':filename', $pdf_filename, PDO::PARAM_STR);
            $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
            $stmt->execute();
        }
    } catch (PDOException $e) {
    }
}

try {
    $mail = getConfiguredMailer();

    $mail->addAddress($guest_email, $guest_name);

    $mail->Subject = 'Your LuckyNest Invoice #' . $invoice_number;

    // Calculate zaa tax values for zaa email
    $net_amount_email = number_format($net_amount, 2);
    $vat_amount_email = number_format($vat_amount, 2);
    $total_amount_email = number_format($amount, 2);

    $email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .header { color: #2c3e50; }
                .details { background-color: #f9f9f9; padding: 15px; border-radius: 5px; }
                .footer { margin-top: 20px; font-size: 0.9em; color: #7f8c8d; }
                .tax-info { margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }
            </style>
        </head>
        <body>
            <h1 class='header'>Payment Confirmation</h1>
            <p>Dear {$guest_name},</p>
            <p>Thank you for your payment. Your transaction has been completed successfully.</p>
            
            <div class='details'>
                <h3>Payment Details</h3>
                <p><strong>Invoice Number:</strong> {$invoice_number}</p>
                <p><strong>Payment Type:</strong> " . ucfirst($payment_type) . "</p>
                
                <div class='tax-info'>
                    <p><strong>Net Amount:</strong> £{$net_amount_email}</p>
                    <p><strong>VAT (20%):</strong> £{$vat_amount_email}</p>
                    <p><strong>Total Amount (Inc. VAT):</strong> £{$total_amount_email}</p>
                </div>
                
                <p><strong>Payment Date:</strong> " . date('d/m/Y H:i:s') . "</p>
            </div>
            
            <p>Please find your invoice attached to this email for your records.</p>
            
            <div class='footer'>
                <p>If you have any questions about this invoice, please contact our support team.</p>
                <p>Thank you for choosing LuckyNest!</p>
            </div>
        </body>
        </html>
    ";

    $mail->Body = $email_body;

    $mail->addAttachment($pdf_path, 'Invoice_' . $invoice_number . '.pdf');

    $mail->send();
} catch (Exception $e) {
    error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
}
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
    <title>Payment Successful</title>
    <script src="../assets/scripts.js"></script>
</head>

<body>
    <?php include "../include/guest_navbar.php"; ?>

    <div class="blur-layer-2"></div>
    <div class="manage-default">
        <h1>Payment Successful!</h1>
        <div class="content-container">

            <p>Your payment has been processed successfully.</p>

            <div class="details-box">
                <p>Reference ID: <?php echo htmlspecialchars($reference_id); ?></p>
                <p>Payment Type: <?php echo htmlspecialchars(ucfirst($payment_type)); ?></p>
                <?php if ($payment_type == 'rent'): ?>
                    <p>Room Type: <?php echo htmlspecialchars($room_type); ?></p>
                    <p>Room Number: <?php echo htmlspecialchars($room_number); ?></p>
                    <p>Check-in Date: <?php echo date('d/m/Y', strtotime($check_in_date)); ?></p>
                    <p>Check-out Date: <?php echo date('d/m/Y', strtotime($check_out_date)); ?></p>
                <?php elseif ($payment_type == 'meal_plan'): ?>
                    <p>Meal Plan: <?php echo htmlspecialchars($meal_plan_name); ?></p>
                <?php elseif ($payment_type == 'laundry'): ?>
                    <p>Laundry Date: <?php echo date('d/m/Y', strtotime($laundry_date)); ?></p>
                    <p>Laundry Time: <?php echo htmlspecialchars($laundry_time); ?></p>
                <?php elseif ($payment_type == 'deposit'): ?>
                    <p>Room Type: <?php echo htmlspecialchars($room_type); ?></p>
                    <p>Room Number: <?php echo htmlspecialchars($room_number); ?></p>
                    <p>Security Deposit for Booking #<?php echo htmlspecialchars($reference_id); ?></p>
                <?php endif; ?>
                <p>Amount Paid (Inc. VAT): <?php echo '£' . number_format($amount, 2); ?></p>
                <p>VAT (20%): <?php echo '£' . number_format($vat_amount, 2); ?></p>
            </div>

            <p>A confirmation email with your invoice has been sent to
                <?php echo htmlspecialchars($guest_email); ?>.
            </p>

            <div>
                <a href="../invoices/<?php echo $pdf_filename; ?>" class="btn" target="_blank">Download Invoice</a>
            </div>

            <p>If you have any questions about your purchase, please contact our support team.</p>
        </div>
    </div>
</body>

</html>