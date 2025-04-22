<?php
session_start();

include __DIR__ . "/../vendor/autoload.php";
include __DIR__ . "/../include/db.php";
include __DIR__ . "/../include/mail_config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

function getActiveStripeKey($conn)
{
    try {
        $stmt = $conn->prepare("SELECT key_reference, valid_until FROM stripe_keys WHERE is_active = 1 ORDER BY valid_until DESC LIMIT 1");
        $stmt->execute();
        $key = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$key || strtotime($key["valid_until"]) < time()) {
            $stmt = $conn->prepare("SELECT key_id, key_reference FROM stripe_keys WHERE valid_until > NOW() ORDER BY valid_until ASC LIMIT 1");
            $stmt->execute();
            $newKey = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($newKey) {
                $stmt = $conn->prepare("UPDATE stripe_keys SET is_active = 0 WHERE is_active = 1");
                $stmt->execute();

                $stmt = $conn->prepare("UPDATE stripe_keys SET is_active = 1 WHERE key_id = :key_id");
                $stmt->bindValue(':key_id', $newKey['key_id'], PDO::PARAM_INT);
                $stmt->execute();

                return $_ENV[$newKey['key_reference']];
            } else {
                return $_ENV["stripe_key_fallback"];
            }
        }

        return $_ENV[$key['key_reference']];
    } catch (PDOException $e) {
        error_log("Error fetching Stripe key: " . $e->getMessage());
        return $_ENV["stripe_key_fallback"];
    }
}

$feedback = '';
$refund_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit_id'])) {
    $deposit_id = $_POST['deposit_id'];
    $refund_amount = $_POST['refund_amount'];
    $withholding_reason = $_POST['withholding_reason'] ?? '';
    $status = $_POST['status'];

    try {
        $stmt = $conn->prepare("
            SELECT d.*, b.booking_id, p.stripe_payment_id, u.email, u.forename, u.surname, u.user_id, 
                   r.room_number, b.check_in_date, b.check_out_date
            FROM deposits d
            JOIN bookings b ON d.booking_id = b.booking_id
            JOIN users u ON b.guest_id = u.user_id
            JOIN rooms r ON b.room_id = r.room_id
            LEFT JOIN payments p ON p.reference_id = d.booking_id AND p.payment_type = 'deposit'
            WHERE d.deposit_id = :deposit_id
        ");
        $stmt->bindParam(':deposit_id', $deposit_id, PDO::PARAM_INT);
        $stmt->execute();
        $deposit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$deposit) {
            $feedback = 'Deposit not found.';
            $refund_status = 'error';
        } else if ($deposit['status'] == 'pending' || $deposit['status'] == 'withheld') {
            $feedback = 'Only paid deposits can be refunded.';
            $refund_status = 'error';
        } else if ($refund_amount <= 0 || $refund_amount > $deposit['amount']) {
            $feedback = 'Invalid refund amount.';
            $refund_status = 'error';
        } else if (!$deposit['stripe_payment_id']) {
            $feedback = 'No payment record found for this deposit.';
            $refund_status = 'error';
        } else {
            $stripe_key = getActiveStripeKey($conn);
            \Stripe\Stripe::setApiKey($stripe_key);

            try {
                $session = \Stripe\Checkout\Session::retrieve($deposit['stripe_payment_id']);
                $payment_intent_id = $session->payment_intent;

                $refund = \Stripe\Refund::create([
                    'payment_intent' => $payment_intent_id,
                    'amount' => $refund_amount * 100,
                    'reason' => 'requested_by_customer'
                ]);

                $newStatus = ($refund_amount < $deposit['amount']) ? 'partially_refunded' : 'fully_refunded';

                $stmt = $conn->prepare("
                    UPDATE deposits 
                    SET status = :status, 
                        refunded_amount = :refunded_amount, 
                        withholding_reason = :withholding_reason, 
                        date_refunded = NOW() 
                    WHERE deposit_id = :deposit_id
                ");
                $stmt->bindParam(':deposit_id', $deposit_id, PDO::PARAM_INT);
                $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
                $stmt->bindParam(':refunded_amount', $refund_amount, PDO::PARAM_STR);
                $stmt->bindParam(':withholding_reason', $withholding_reason, PDO::PARAM_STR);
                $stmt->execute();

                $stmt = $conn->prepare("
                    INSERT INTO payments (user_id, reference_id, payment_type, amount, payment_date, stripe_payment_id) 
                    VALUES (:user_id, :reference_id, 'deposit', :amount, NOW(), :stripe_payment_id)
                ");
                $stmt->bindParam(':user_id', $deposit['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':reference_id', $deposit['booking_id'], PDO::PARAM_INT);
                $stmt->bindParam(':amount', $refund_amount, PDO::PARAM_STR);
                $stmt->bindParam(':stripe_payment_id', $refund->id, PDO::PARAM_STR);
                $stmt->execute();
                $payment_id = $conn->lastInsertId();

                $message = "Your security deposit for room {$deposit['room_number']} has been " .
                    ($newStatus == 'partially_refunded' ? 'partially' : 'fully') .
                    " refunded. Amount: £" . number_format($refund_amount, 2);

                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
                $stmt->bindParam(':user_id', $deposit['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':message', $message, PDO::PARAM_STR);
                $stmt->execute();

                $invoice_number = date('Ymd') . "_" . $deposit['booking_id'] . "_deposit_refund";
                $pdf_filename = 'refund_invoice_' . $invoice_number . '.pdf';
                $pdf_path = __DIR__ . '/../invoices/' . $pdf_filename;

                if (!file_exists(__DIR__ . '/../invoices/')) {
                    mkdir(__DIR__ . '/../invoices/', 0755, true);
                }

                $vat_rate = 0.20;
                $net_amount = $refund_amount / (1 + $vat_rate);
                $vat_amount = $refund_amount - $net_amount;


                class PDF extends \FPDF
                {
                    function Header()
                    {
                        $this->SetFont('Arial', 'B', 18);
                        $this->Cell(0, 10, 'LuckyNest Refund Invoice', 0, 1, 'C');
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
                $pdf->Cell(95, 8, 'Refund To:', 0, 0);
                $pdf->Cell(95, 8, 'Refund Details:', 0, 1);

                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(95, 6, $deposit['forename'] . ' ' . $deposit['surname'], 0, 0);
                $pdf->Cell(35, 6, 'Invoice Number:', 0, 0);
                $pdf->Cell(60, 6, $invoice_number, 0, 1);

                $pdf->Cell(95, 6, 'Email: ' . $deposit['email'], 0, 0);
                $pdf->Cell(35, 6, 'Refund Date:', 0, 0);
                $pdf->Cell(60, 6, date('d/m/Y'), 0, 1);

                $pdf->Cell(95, 6, '', 0, 0);
                $pdf->Cell(35, 6, 'Reference ID:', 0, 0);
                $pdf->Cell(60, 6, $deposit['booking_id'], 0, 1);

                $pdf->Ln(10);

                $pdf->SetFillColor(235, 235, 235);
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(70, 10, 'Description', 1, 0, 'C', true);
                $pdf->Cell(30, 10, 'Room No.', 1, 0, 'C', true);
                $pdf->Cell(30, 10, 'From', 1, 0, 'C', true);
                $pdf->Cell(30, 10, 'To', 1, 0, 'C', true);
                $pdf->Cell(30, 10, 'Amount', 1, 1, 'C', true);

                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(70, 10, 'Security Deposit Refund', 1, 0, 'L');
                $pdf->Cell(30, 10, $deposit['room_number'], 1, 0, 'C');
                $pdf->Cell(30, 10, date('d/m/Y', strtotime($deposit['check_in_date'])), 1, 0, 'C');
                $pdf->Cell(30, 10, date('d/m/Y', strtotime($deposit['check_out_date'])), 1, 0, 'C');
                $pdf->Cell(30, 10, chr(163) . number_format($net_amount, 2), 1, 1, 'R');

                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(160, 10, 'VAT (20%)', 1, 0, 'R');
                $pdf->Cell(30, 10, chr(163) . number_format($vat_amount, 2), 1, 1, 'R');

                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(160, 10, 'Total Refund (Inc. VAT)', 1, 0, 'R', true);
                $pdf->Cell(30, 10, chr(163) . number_format($refund_amount, 2), 1, 1, 'R', true);

                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'I', 10);
                $pdf->Cell(0, 6, 'Refund amounts are inclusive of VAT at 20%', 0, 1);

                if ($newStatus == 'partially_refunded' && !empty($withholding_reason)) {
                    $pdf->Ln(5);
                    $pdf->SetFont('Arial', 'B', 12);
                    $pdf->Cell(0, 8, 'Partial Refund Explanation', 0, 1);
                    $pdf->SetFont('Arial', '', 10);
                    $pdf->MultiCell(0, 5, 'Amount Withheld: £' . number_format($deposit['amount'] - $refund_amount, 2) . "\nReason: " . $withholding_reason);
                }

                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(0, 8, 'Refund Information', 0, 1);
                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(0, 6, 'Refund Status: Completed', 0, 1);
                $pdf->Cell(0, 6, 'Refund Method: Credit Card (via Stripe)', 0, 1);
                $pdf->Cell(0, 6, 'Refund Date: ' . date('d/m/Y'), 0, 1);
                $pdf->Cell(0, 6, 'Original Deposit Amount: £' . number_format($deposit['amount'], 2), 0, 1);

                $pdf->Ln(10);
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(0, 8, 'Terms and Conditions', 0, 1);
                $pdf->SetFont('Arial', '', 10);
                $pdf->MultiCell(0, 5, 'This document confirms that a refund has been processed for your security deposit. The refund has been issued back to the original payment method. If you have any questions regarding this refund, please contact our customer service team.');

                $pdf->Output('F', $pdf_path);

                $stmt = $conn->prepare("
                    INSERT INTO invoices (user_id, payment_id, invoice_number, amount, filename, created_at) 
                    VALUES (:user_id, :payment_id, :invoice_number, :amount, :filename, NOW())
                ");
                $stmt->bindParam(':user_id', $deposit['user_id'], PDO::PARAM_INT);
                $stmt->bindParam(':payment_id', $payment_id, PDO::PARAM_INT);
                $stmt->bindParam(':invoice_number', $invoice_number, PDO::PARAM_STR);
                $stmt->bindParam(':amount', $refund_amount, PDO::PARAM_STR);
                $stmt->bindParam(':filename', $pdf_filename, PDO::PARAM_STR);
                $stmt->execute();

                $mail = getConfiguredMailer();
                $mail->addAddress($deposit['email'], $deposit['forename'] . ' ' . $deposit['surname']);
                $mail->Subject = 'Your LuckyNest Deposit Refund - Invoice #' . $invoice_number;

                $net_amount_formatted = number_format($net_amount, 2);
                $vat_amount_formatted = number_format($vat_amount, 2);
                $refund_amount_formatted = number_format($refund_amount, 2);

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
                        <h1 class='header'>Deposit Refund Confirmation</h1>
                        <p>Dear {$deposit['forename']} {$deposit['surname']},</p>
                        <p>We are pleased to confirm that your security deposit has been " .
                    ($newStatus == 'partially_refunded' ? 'partially' : 'fully') .
                    " refunded.</p>
                        
                        <div class='details'>
                            <h3>Refund Details</h3>
                            <p><strong>Invoice Number:</strong> {$invoice_number}</p>
                            <p><strong>Room Number:</strong> {$deposit['room_number']}</p>
                            <p><strong>Booking Dates:</strong> " . date('d/m/Y', strtotime($deposit['check_in_date'])) .
                    " to " . date('d/m/Y', strtotime($deposit['check_out_date'])) . "</p>
                            
                            <div class='tax-info'>
                                <p><strong>Net Amount:</strong> £{$net_amount_formatted}</p>
                                <p><strong>VAT (20%):</strong> £{$vat_amount_formatted}</p>
                                <p><strong>Total Refund Amount (Inc. VAT):</strong> £{$refund_amount_formatted}</p>
                            </div>";

                if ($newStatus == 'partially_refunded' && !empty($withholding_reason)) {
                    $withheld_amount = number_format($deposit['amount'] - $refund_amount, 2);
                    $email_body .= "
                            <div class='withholding-info'>
                                <p><strong>Amount Withheld:</strong> £{$withheld_amount}</p>
                                <p><strong>Reason for Withholding:</strong> {$withholding_reason}</p>
                            </div>";
                }

                $email_body .= "                            
                            <p><strong>Refund Date:</strong> " . date('d/m/Y') . "</p>
                        </div>
                        
                        <p>Please find your refund invoice attached to this email for your records.</p>
                        
                        <div class='footer'>
                            <p>If you have any questions about this refund, please contact our support team.</p>
                            <p>Thank you for choosing LuckyNest!</p>
                        </div>
                    </body>
                    </html>
                ";

                $mail->Body = $email_body;
                $mail->addAttachment($pdf_path, 'Refund_Invoice_' . $invoice_number . '.pdf');
                $mail->send();

                $feedback = 'Deposit refund processed successfully! A confirmation email has been sent to the guest.';
                $refund_status = 'success';

            } catch (\Stripe\Exception\ApiErrorException $e) {
                $feedback = 'Stripe Error: ' . $e->getMessage();
                $refund_status = 'error';
            }
        }
    } catch (PDOException $e) {
        $feedback = 'Database Error: ' . $e->getMessage();
        $refund_status = 'error';
    } catch (Exception $e) {
        $feedback = 'Error sending email: ' . $e->getMessage();
        $refund_status = 'error';
    }
}

header('Location: ../admin/deposits.php?feedback=' . urlencode($feedback) . '&status=' . $refund_status);
exit();
?>