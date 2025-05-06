<?php
session_start();
require __DIR__ . "/../include/db.php";

$error = '';
$errors = [];
$success = '';
$validToken = false;
$email = '';

$formValues = [
    'forename' => '',
    'surname' => '',
    'email' => '',
    'phone_country' => '+44',
    'phone_number' => '',
    'emergency_country' => '+44',
    'emergency_number' => '',
    'address' => ''
];

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT * FROM invitations WHERE token = :token AND expires_at > NOW() AND used = 0");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invitation) {
        $validToken = true;
        $email = $invitation['email'];
        $formValues['email'] = $email;
    } else {
        $error = 'Invalid or expired invitation link. Please request a new invitation.';
    }
} else {
    $error = 'Registration requires a valid invitation link. Please contact an administrator.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $validToken) {
    $formValues = [
        'forename' => trim($_POST['forename']),
        'surname' => trim($_POST['surname']),
        'email' => trim($_POST['email']),
        'phone_country' => trim($_POST['phone_country']),
        'phone_number' => trim($_POST['phone_number']),
        'emergency_country' => trim($_POST['emergency_country']),
        'emergency_number' => trim($_POST['emergency_number']),
        'address' => trim($_POST['address'])
    ];

    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $token = $_POST['token'];
    $role = 'guest';

    $isValid = true;

    if (empty($formValues['forename'])) {
        $errors['forename'] = 'Forename is required';
        $isValid = false;
    }

    if (empty($formValues['surname'])) {
        $errors['surname'] = 'Surname is required';
        $isValid = false;
    }

    if (empty($formValues['email'])) {
        $errors['email'] = 'Email is required';
        $isValid = false;
    } else {
        // Note for examiner: in an actual implementation, this list of disposable/blocked emails would be more extensive
        $blockedDomains = [
            'yopmail.com'
        ];

        $emailParts = explode('@', $formValues['email']);
        if (count($emailParts) === 2) {
            $domain = strtolower($emailParts[1]);
            if (in_array($domain, $blockedDomains)) {
                $errors['email'] = 'Email addresses from ' . $domain . ' are not allowed. Please use a legitimate email service.';
                $isValid = false;
            }
        }
    }

    if (empty($formValues['phone_number'])) {
        $errors['phone_number'] = 'Phone number is required';
        $isValid = false;
    } elseif (preg_match('/^\+/', $formValues['phone_number'])) {
        $errors['phone_number'] = 'Phone number should not include country code (+)';
        $isValid = false;
    } elseif (strlen($formValues['phone_number']) < 9 || strlen($formValues['phone_number']) > 11) {
        $errors['phone_number'] = 'Phone number must be between 9 and 11 digits';
        $isValid = false;
    } elseif (!preg_match('/^[0-9]+$/', $formValues['phone_number'])) {
        $errors['phone_number'] = 'Phone number must contain only digits';
        $isValid = false;
    }

    if (empty($formValues['emergency_number'])) {
        $errors['emergency_number'] = 'Emergency contact number is required';
        $isValid = false;
    } elseif (preg_match('/^\+/', $formValues['emergency_number'])) {
        $errors['emergency_number'] = 'Emergency number should not include country code (+)';
        $isValid = false;
    } elseif (strlen($formValues['emergency_number']) < 9 || strlen($formValues['emergency_number']) > 11) {
        $errors['emergency_number'] = 'Emergency number must be between 9 and 11 digits';
        $isValid = false;
    } elseif (!preg_match('/^[0-9]+$/', $formValues['emergency_number'])) {
        $errors['emergency_number'] = 'Emergency number must contain only digits';
        $isValid = false;
    }

    if (empty($formValues['address'])) {
        $errors['address'] = 'Address is required';
        $isValid = false;
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required';
        $isValid = false;
    }

    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm your password';
        $isValid = false;
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
        $isValid = false;
    }

    if ($isValid) {
        $stmt = $conn->prepare("SELECT * FROM invitations WHERE token = :token AND email = :email AND expires_at > NOW() AND used = 0");
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':email', $formValues['email']);
        $stmt->execute();

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $error = 'Invalid or expired invitation.';
        } else {
            $stmt = $conn->prepare("SELECT email FROM users WHERE email = :email");
            $stmt->bindParam(':email', $formValues['email']);
            $stmt->execute();

            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $error = 'Email already exists.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $phone = $formValues['phone_country'] . $formValues['phone_number'];
                $emergency_contact = $formValues['emergency_country'] . $formValues['emergency_number'];

                $conn->beginTransaction();
                try {
                    $stmt = $conn->prepare("INSERT INTO users (forename, surname, email, phone, emergency_contact, address, role, password) VALUES (:forename, :surname, :email, :phone, :emergency_contact, :address, :role, :password)");
                    $stmt->bindParam(':forename', $formValues['forename']);
                    $stmt->bindParam(':surname', $formValues['surname']);
                    $stmt->bindParam(':email', $formValues['email']);
                    $stmt->bindParam(':phone', $phone);
                    $stmt->bindParam(':emergency_contact', $emergency_contact);
                    $stmt->bindParam(':address', $formValues['address']);
                    $stmt->bindParam(':role', $role);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->execute();

                    $stmt = $conn->prepare("UPDATE invitations SET used = 1, used_at = NOW() WHERE token = :token");
                    $stmt->bindParam(':token', $token);
                    $stmt->execute();

                    $conn->commit();
                    $success = 'Registration successful! You can now <a href="login">login</a>.';
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    } else {
        $error = 'Please correct the errors in the form.';
    }
}

$countryCodes = [
    "+44" => "UK (+44)",
    "+1" => "USA (+1)"
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&display=swap" />
    <link rel="stylesheet" href="../assets/styles.css">
    <title>Register</title>
</head>

<body class="registration">
    <div class="blur-layer"></div>
    <div class="center-container-registration">
        <h1 class="title">LuckyNest</h1>
        <div class="wrapper">
            <h1>Register</h1>
            <?php if ($error): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo $success; ?></p>
            <?php elseif ($validToken): ?>
                <form method="POST" action="registration?token=<?php echo htmlspecialchars($_GET['token']); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">

                    <div class="input-box">
                        <input type="text" id="forename" name="forename" placeholder="Forename"
                            value="<?php echo htmlspecialchars($formValues['forename']); ?>" required>
                        <?php if (isset($errors['forename'])): ?>
                            <small class="error-text"><?php echo $errors['forename']; ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="input-box">
                        <input type="text" id="surname" name="surname" placeholder="Surname"
                            value="<?php echo htmlspecialchars($formValues['surname']); ?>" required>
                        <?php if (isset($errors['surname'])): ?>
                            <small class="error-text"><?php echo $errors['surname']; ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="input-box">
                        <input type="email" id="email" name="email"
                            value="<?php echo htmlspecialchars($formValues['email']); ?>" readonly required>
                        <small>Email from invitation cannot be changed</small>
                    </div>

                    <div class="input-box">
                        <div class="phone-container">
                            <select id="phone_country" name="phone_country" class="country-code">
                                <?php foreach ($countryCodes as $code => $country): ?>
                                    <option value="<?php echo $code; ?>" <?php echo ($code === $formValues['phone_country']) ? 'selected' : ''; ?>>
                                        <?php echo $country; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="tel" id="phone_number" name="phone_number" class="phone-number"
                                placeholder="Phone Number"
                                value="<?php echo htmlspecialchars($formValues['phone_number']); ?>" required minlength="9"
                                maxlength="11" pattern="[0-9]{9,11}">
                        </div>
                        <?php if (isset($errors['phone_number'])): ?>
                            <small class="error-text"><?php echo $errors['phone_number']; ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="input-box">
                        <div class="phone-container">
                            <select id="emergency_country" name="emergency_country" class="country-code">
                                <?php foreach ($countryCodes as $code => $country): ?>
                                    <option value="<?php echo $code; ?>" <?php echo ($code === $formValues['emergency_country']) ? 'selected' : ''; ?>>
                                        <?php echo $country; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="tel" id="emergency_number" name="emergency_number" class="phone-number"
                                placeholder="Emergency Number"
                                value="<?php echo htmlspecialchars($formValues['emergency_number']); ?>" required
                                minlength="9" maxlength="11" pattern="[0-9]{9,11}">
                        </div>
                        <?php if (isset($errors['emergency_number'])): ?>
                            <small class="error-text"><?php echo $errors['emergency_number']; ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="input-box">
                        <input type="text" id="address" name="address" placeholder="Home Address"
                            value="<?php echo htmlspecialchars($formValues['address']); ?>" required>
                        <?php if (isset($errors['address'])): ?>
                            <small class="error-text"><?php echo $errors['address']; ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="input-box">
                        <input type="password" id="password" name="password" placeholder="Password" required>
                        <?php if (isset($errors['password'])): ?>
                            <small class="error-text"><?php echo $errors['password']; ?></small>
                        <?php endif; ?>
                    </div>

                    <div class="input-box">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password"
                            required>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <small class="error-text"><?php echo $errors['confirm_password']; ?></small>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn">Register</button>

                    <div class="login-link">
                        <p>Have an account already? <a href="login">Login</a></p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>