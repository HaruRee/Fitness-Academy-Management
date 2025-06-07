<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require '../config/database.php';

$userId = $_SESSION['user_id'];
$errors = [];
$success = "";

function sanitize($data)
{
    return htmlspecialchars(trim($data));
}

// Fetch current user data
try {
    $stmt = $conn->prepare("SELECT Username, Email, First_Name, Last_Name, Phone, Address, 
                           DateOfBirth, ProfileImage, RegistrationDate, membership_start_date, 
                           membership_plan, plan_id
                           FROM users WHERE UserID = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("User not found.");
    }
    
    // Get membership data
    if ($user['plan_id']) {
        $planStmt = $conn->prepare("SELECT plan_type, name, description FROM membershipplans WHERE id = ?");
        $planStmt->execute([$user['plan_id']]);
        $planData = $planStmt->fetch(PDO::FETCH_ASSOC);
        if ($planData) {
            $user['plan_type'] = $planData['plan_type'];
            $user['plan_name'] = $planData['name'];
            $user['plan_description'] = $planData['description'];
        }
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug mode for troubleshooting (remove in production)
    if (isset($_GET['debug'])) {
        echo "<pre style='background: #333; color: #fff; padding: 20px; margin: 20px;'>";
        echo "POST Data:\n";
        print_r($_POST);
        echo "\nFILES Data:\n"; 
        print_r($_FILES);
        echo "\nHeaders:\n";
        print_r(getallheaders());
        echo "</pre>";
    }

    // Sanitize inputs
    $phone = sanitize($_POST['phone'] ?? '');
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // No validation for first/last name since they are not editable

    // Handle profile image upload
    $profileImageName = $user['ProfileImage'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            if (!in_array($file['type'], $allowedTypes)) {
                $errors[] = "Only JPG, PNG and GIF images are allowed.";
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = "Profile image size should not exceed 2MB.";
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = 'profile_' . $userId . '_' . time() . '.' . $ext;
                $uploadDir = '../uploads/profile_images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $targetFile = $uploadDir . $newFileName;
                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    $profileImageName = $newFileName;
                } else {
                    $errors[] = "Failed to upload profile image.";
                }
            }
        } else {
            $errors[] = "Error uploading profile image.";
        }
    }

    // Password change check
    $passwordChange = false;
    if ($oldPassword || $newPassword || $confirmPassword) {
        $passwordChange = true;
        if (!$oldPassword) $errors[] = "Old password is required to change password.";
        if (!$newPassword) $errors[] = "New password cannot be empty.";
        if ($newPassword !== $confirmPassword) $errors[] = "New password and confirm password do not match.";
        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT PasswordHash FROM users WHERE UserID = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !password_verify($oldPassword, $row['PasswordHash'])) {
                $errors[] = "Old password is incorrect.";
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($passwordChange) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET Phone=?, ProfileImage=?, PasswordHash=? WHERE UserID=?");
                $stmt->execute([$phone, $profileImageName, $newHash, $userId]);
            } else {
                $stmt = $conn->prepare("UPDATE users SET Phone=?, ProfileImage=? WHERE UserID=?");
                $stmt->execute([$phone, $profileImageName, $userId]);
            }
            $success = "Profile updated successfully.";
            // Refresh data
            $user['Phone'] = $phone;
            $user['ProfileImage'] = $profileImageName;
        } catch (PDOException $e) {
            $errors[] = "Failed to update profile: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Update Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: #111;
            /* Match membership.php */
            color: #eee;
            font-family: Arial, sans-serif;
        }

        .page-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0;
            width: 100%;
            background: #111;
            /* Match membership.php */
        }

        main {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: transparent;
            border-radius: 0;
            padding-bottom: 32px;
        }

        .profile-summary-card,
        .profile-details-grid>div,
        .profile-image-section,
        .password-section {
            background: #222 !important;
            /* Card background */
            color: #eee;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        h1,
        h2 {
            color: #fff;
        }

        .summary-label,
        .profile-details-grid h2,
        .profile-summary-card h2 {
            color: #fff !important;
        }

        label,
        .profile-field label,
        .summary-label {
            color: #eee !important;
        }

        input[type="text"],
        input[type="date"],
        input[type="tel"],
        input[type="password"] {
            padding: 10px;
            border: 1px solid #444;
            border-radius: 5px;
            font-size: 1rem;
            background: #222;
            color: #eee;
        }

        input[disabled] {
            background: #222 !important;
            color: #bbb !important;
            border: 1px solid #333 !important;
        }        .btn {
            display: block;
            margin: 32px auto 0 auto;
            width: 320px;
            max-width: 90%;
            background: #d62328;
            color: #ffffff;
            padding: 18px 0;
            font-size: 1.2rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            letter-spacing: 1px;
            text-align: center;
            transition: background 0.2s;
        }.btn:hover {
            background: #b21e24;
        }        .btn.btn-save {
            background: #d62328 !important;
            color: #ffffff !important;
            text-transform: uppercase;
            font-weight: 600;
            border: 1px solid #d62328 !important;
            letter-spacing: 1px;
        }
        
        .btn.btn-save:hover {
            background: #b21e24 !important;
            color: #ffffff !important;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
        }

        .error {
            background: #2a1818;
            border: 1px solid #d62328;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            color: #ff5c5c;
            grid-column: span 2;
        }

        .success {
            background: #182818;
            border: 1px solid #5cff5c;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
            color: #5cff5c;
            grid-column: span 2;
        }

        .profile-summary-card {
            border: 1px solid #fff;
            border-radius: 4px;
            background: #181818;
            margin-bottom: 32px;
            padding: 24px 32px;
            max-width: 100%;
        }

        .profile-summary-row {
            display: flex;
            gap: 60px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .profile-summary-row>div {
            flex: 1 1 220px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            color: #eee;
        }

        .summary-label {
            font-weight: bold;
            margin-right: 10px;
            min-width: 110px;
            display: inline-block;
            color: #d62328;
        }

        .edit-icon {
            color: #eee;
            margin-left: 8px;
            font-size: 1.1em;
            text-decoration: none;
            transition: color 0.2s;
        }

        .edit-icon:hover {
            color: #d62328;
        }

        .profile-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 300px;
            gap: 40px 60px;
            margin-bottom: 40px;
            align-items: flex-start;
        }

        .profile-details-grid>div {
            background: #181818;
            border-radius: 8px;
            padding: 24px 20px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.04);
            border: 1px solid #222;
        }

        .profile-details-grid h2 {
            text-align: center;
            color: #d62328;
            margin-bottom: 18px;
            font-size: 1.25rem;
            border-bottom: 2px solid #d62328;
            padding-bottom: 6px;
        }

        .profile-field {
            margin-bottom: 18px;
            display: flex;
            flex-direction: column;
        }

        .profile-field label {
            font-weight: 600;
            margin-bottom: 6px;
            color: #eee;
        }

        .profile-field input[type="text"],
        .profile-field input[type="date"],
        .profile-field input[type="tel"],
        .profile-field input[type="password"] {
            padding: 10px;
            border: 1px solid #444;
            border-radius: 5px;
            font-size: 1rem;
            background: #222;
            color: #eee;
        }

        .profile-image-section {
            background: #222 !important;
            border-radius: 10px;
            padding: 24px 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-image-upload {
            background: #222;
            border: 2px dashed #888;
            border-radius: 16px;
            padding: 32px 18px 24px 18px;
            text-align: center;
            margin-bottom: 24px;
            margin-top: 10px;
            width: 100%;
            max-width: 320px;
            box-shadow: 0 4px 24px 0 rgba(44, 62, 80, 0.10);
            position: relative;
            transition: border-color 0.2s, background 0.2s;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .profile-image-upload.dragover {
            border-color: #d62328;
            background: #181818;
        }

        .profile-image-upload .upload-icon {
            font-size: 2.5rem;
            color: #d62328;
            margin-bottom: 12px;
            margin-top: 4px;
        }

        .profile-image-upload .upload-text {
            color: #eee;
            font-size: 1.08rem;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .profile-image-upload .or-divider {
            color: #fff;
            /* Make "OR" white */
            font-size: 1rem;
            margin: 16px 0 12px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 500;
            gap: 10px;
        }

        .profile-image-upload .or-divider:before,
        .profile-image-upload .or-divider:after {
            content: "";
            flex: 1;
            border-bottom: 1px solid #444;
            margin: 0 8px;
        }

        .profile-image-upload .browse-btn {
            background: #d62328;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 32px;
            font-size: 1.08rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 4px;
            margin-bottom: 4px;
            transition: background 0.2s;
            box-shadow: 0 2px 8px rgba(44, 62, 80, 0.08);
        }

        .profile-image-upload .browse-btn:hover {
            background: #b21e24;
        }

        .profile-image-upload input[type="file"] {
            display: none;
        }

        .password-section {
            background: #181818;
            border-radius: 8px;
            padding: 24px 20px;
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.04);
            max-width: none;
            margin: 0 0 40px 0 !important;
            grid-column: 1 / 2;
            border: 1px solid #222;
        }

        .password-section h2 {
            text-align: center;
            color: #fff;
            margin-bottom: 18px;
            font-size: 1.25rem;
            border-bottom: 2px solid #d62328;
            padding-bottom: 6px;
        }

        .address-paragraph {
            width: 100%;
            min-height: 48px;
            background: #222;
            color: #eee;
            border: 1px solid #444;
            border-radius: 5px;
            font-size: 1.1rem;
            letter-spacing: 1px;
            padding: 10px 12px;
            margin: 0;
            white-space: pre-line;
            word-break: break-word;
            box-sizing: border-box;
        }

        .password-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-input-wrapper input[type="password"],
        .password-input-wrapper input[type="text"] {
            width: 100%;
            padding-right: 40px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #bbb;
            cursor: pointer;
            font-size: 1.2em;
            z-index: 2;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: #d62328;
        }

        .profile-image-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
            margin-top: 12px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            display: block;
        }

        @media (max-width: 900px) {
            .profile-details-grid {
                grid-template-columns: 1fr !important;
                gap: 24px !important;
            }

            .profile-details-grid>div,
            .profile-image-section {
                width: 100% !important;
                min-width: 0 !important;
                max-width: 100% !important;
                margin: 0 !important;
                box-sizing: border-box;
            }

            form {
                grid-template-columns: 1fr !important;
                gap: 24px !important;
                padding: 18px 4px !important;
            }

            .btn {
                grid-column: span 1 !important;
                width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }

            .profile-summary-card {
                padding: 14px 4px !important;
                margin-bottom: 18px !important;
            }

            .profile-summary-row {
                flex-direction: column !important;
                gap: 10px !important;
            }

            .profile-summary-row>div {
                width: 100% !important;
                justify-content: flex-start !important;
            }

            .profile-details-grid h2 {
                text-align: left !important;
                font-size: 1.15rem !important;
                margin-bottom: 12px !important;
            }

            .profile-field label {
                font-size: 1rem !important;
                margin-bottom: 4px !important;
            }

            .profile-field input,
            .profile-field p.address-paragraph {
                font-size: 1rem !important;
                padding: 10px 8px !important;
            }

            .profile-image-preview {
                width: 90px !important;
                height: 90px !important;
                margin-top: 8px !important;
            }

            .password-section {
                padding: 18px 8px !important;
                margin: 0 0 24px 0 !important;
                max-width: 100% !important;
            }
        }

        @media (max-width: 600px) {
            .page-wrapper {
                padding: 0 2px !important;
                max-width: 100vw !important;
            }

            .profile-summary-card {
                padding: 6px 2px !important;
                margin-bottom: 10px !important;
            }

            .profile-summary-row {
                flex-direction: column !important;
                gap: 0 !important;
                margin-bottom: 0 !important;
            }

            .profile-summary-row>div {
                font-size: 1rem !important;
                gap: 2px !important;
                margin-bottom: 2px !important;
                justify-content: flex-start !important;
                padding: 4px 0 !important;
            }

            .summary-label {
                min-width: 70px !important;
                font-size: 1rem !important;
                margin-right: 4px !important;
            }

            .profile-details-grid {
                grid-template-columns: 1fr !important;
                gap: 24px !important;
            }

            .profile-details-grid>div,
            .profile-image-section {
                width: 100% !important;
                min-width: 0 !important;
                max-width: 100% !important;
                margin: 0 !important;
                box-sizing: border-box;
            }

            form {
                grid-template-columns: 1fr !important;
                gap: 24px !important;
                padding: 18px 4px !important;
            }

            .btn {
                grid-column: span 1 !important;
                width: 100% !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
            }

            .profile-summary-card {
                padding: 14px 4px !important;
                margin-bottom: 18px !important;
            }

            .profile-summary-row {
                flex-direction: column !important;
                gap: 10px !important;
            }

            .profile-summary-row>div {
                width: 100% !important;
                justify-content: flex-start !important;
            }

            .profile-details-grid h2 {
                text-align: left !important;
                font-size: 1.15rem !important;
                margin-bottom: 12px !important;
            }

            .profile-field label {
                font-size: 1rem !important;
                margin-bottom: 4px !important;
            }

            .profile-field input,
            .profile-field p.address-paragraph {
                font-size: 1rem !important;
                padding: 10px 8px !important;
            }

            .profile-image-preview {
                width: 90px !important;
                height: 90px !important;
                margin-top: 8px !important;
            }

            .password-section {
                padding: 18px 8px !important;
                margin: 0 0 24px 0 !important;
                max-width: 100% !important;
            }
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .page-wrapper {
                padding: 10px;
            }

            main {
                padding: 0 10px 32px 10px;
            }

            .profile-summary-card {
                padding: 16px;
                margin-bottom: 20px;
            }

            .profile-summary-row {
                flex-direction: column;
                gap: 8px;
                margin-bottom: 8px;
            }

            .profile-summary-row > div {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }

            .summary-label {
                min-width: auto;
                font-size: 0.9rem;
            }

            .profile-details-grid {
                grid-template-columns: 1fr !important;
                gap: 16px;
            }

            .profile-details-grid > div {
                padding: 16px;
            }

            .password-section {
                padding: 16px;
                margin: 0 0 20px 0 !important;
                grid-column: 1;
            }

            input[type="text"],
            input[type="date"],
            input[type="tel"],
            input[type="password"] {
                font-size: 16px; /* Prevents zoom on iOS */
                padding: 14px;
                -webkit-appearance: none;
                border-radius: 8px;
            }

            .btn {
                width: 100%;
                max-width: 100%;
                padding: 16px;
                font-size: 1.1rem;
                margin: 20px 0 0 0;
                -webkit-appearance: none;
                border-radius: 8px;
            }

            .profile-image-upload {
                padding: 16px;
                min-height: 120px;
            }

            .profile-image-upload .upload-text {
                font-size: 0.9rem;
                text-align: center;
            }

            .profile-image-upload .browse-btn {
                padding: 14px 24px;
                font-size: 1rem;
                width: 100%;
                -webkit-appearance: none;
                border-radius: 8px;
            }

            .profile-image-preview {
                max-width: 100px;
                max-height: 100px;
            }

            .password-input-wrapper {
                position: relative;
            }

            .password-input-wrapper input {
                padding-right: 50px;
            }

            .toggle-password {
                position: absolute;
                right: 12px;
                top: 50%;
                transform: translateY(-50%);
                padding: 8px;
                min-width: 40px;
                min-height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .page-wrapper {
                padding: 5px;
            }

            main {
                padding: 0 5px 20px 5px;
            }

            .profile-summary-card {
                padding: 12px;
            }

            .profile-details-grid > div {
                padding: 12px;
            }

            .password-section {
                padding: 12px;
            }

            input[type="text"],
            input[type="date"], 
            input[type="tel"],
            input[type="password"] {
                font-size: 16px;
                padding: 12px;
            }

            .btn {
                padding: 14px;
                font-size: 1rem;
            }

            .profile-image-upload .browse-btn {
                padding: 12px 20px;
                font-size: 0.95rem;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .profile-image-upload {
                cursor: pointer;
                -webkit-tap-highlight-color: rgba(214, 35, 40, 0.1);
            }

            .browse-btn {
                cursor: pointer;
                -webkit-tap-highlight-color: rgba(214, 35, 40, 0.1);
            }

            .toggle-password {
                cursor: pointer;
                -webkit-tap-highlight-color: rgba(214, 35, 40, 0.1);
            }

            /* Larger touch targets */
            .toggle-password {
                min-width: 44px;
                min-height: 44px;
            }
        }
    </style>
</head>

<body>
    <?php include '../assets/format/member_header.php'; ?>

    <div class="page-wrapper">
        <main>
            <h1>Update Profile</h1>

            <?php if ($errors): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>            <!-- Profile Summary Card -->
            <div class="profile-summary-card">
                <div class="profile-summary-row">
                    <div>
                        <span class="summary-label">Email</span>
                        <span><?= htmlspecialchars($user['Email']) ?></span>
                    </div>
                    <div>
                        <span class="summary-label">Password</span>
                        <span>************</span>
                        <a href="#change-password" class="edit-icon" title="Change Password"><i class="fa fa-pen-to-square"></i></a>
                    </div>
                </div>                <div class="profile-summary-row">
                    <div>
                        <span class="summary-label">Member since</span>
                        <span><?= htmlspecialchars(date('F j, Y', strtotime($user['membership_start_date'] ?? $user['RegistrationDate']))) ?></span>
                    </div>
                    <?php if (!empty($user['membership_plan'])): ?>
                    <div>
                        <span class="summary-label">Current Plan</span>
                        <span><?= htmlspecialchars($user['membership_plan']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <form action="update_profile.php" method="POST" enctype="multipart/form-data">
                <div class="profile-details-grid">
                    <div>
                        <h2>Personal information</h2>
                        <div class="profile-field">
                            <label>First Name 🔒</label>
                            <input type="text" value="<?= htmlspecialchars($user['First_Name']) ?>" disabled />
                        </div>
                        <div class="profile-field">
                            <label>Last Name 🔒</label>
                            <input type="text" value="<?= htmlspecialchars($user['Last_Name']) ?>" disabled />
                        </div>
                        <div class="profile-field">
                            <label>Phone</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($user['Phone']) ?>" />
                        </div>
                        <div class="profile-field">
                            <label>Date of Birth 🔒</label>
                            <input type="date" value="<?= htmlspecialchars($user['DateOfBirth']) ?>" disabled />
                        </div>
                    </div>
                    <div>
                        <h2>Address</h2>
                        <div class="profile-field">
                            <label>Address 🔒</label>
                            <p class="address-paragraph"><?= nl2br(htmlspecialchars($user['Address'] ?? '')) ?></p>
                        </div>
                    </div>                    <div class="profile-image-section">
                        <label>Profile Image</label>
                        <div class="profile-image-upload" id="profileImageDrop">
                            <span class="upload-icon"><i class="fa fa-camera"></i></span>
                            <span class="upload-text">Tap to select your profile image</span>
                            <div class="or-divider">OR</div>
                            <button type="button" class="browse-btn" onclick="document.getElementById('profile_image').click();">Choose Image</button>
                            <input type="file" id="profile_image" name="profile_image" accept="image/*" capture="user" />
                            <small style="color: #bbb; font-size: 0.8rem; margin-top: 8px; display: block;">
                                Supported: JPG, PNG, GIF (Max: 2MB)
                            </small>
                        </div>
                        <?php if ($user['ProfileImage']): ?>
                            <img src="../uploads/profile_images/<?= htmlspecialchars($user['ProfileImage']) ?>" alt="Profile Image" class="profile-image-preview" id="profileImagePreview" />
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['First_Name'] . ' ' . $user['Last_Name']) ?>&background=d62328&color=fff&size=120" alt="Profile Image" class="profile-image-preview" id="profileImagePreview" />
                        <?php endif; ?>
                    </div>
                </div>

                <div class="password-section">
                    <h2>Change Password</h2>
                    <div class="profile-field">
                        <label for="old_password">Old Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="old_password" name="old_password" autocomplete="off" />
                            <span class="toggle-password" data-target="old_password"><i class="fa fa-eye"></i></span>
                        </div>
                    </div>
                    <div class="profile-field">
                        <label for="new_password">New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="new_password" name="new_password" autocomplete="off" />
                            <span class="toggle-password" data-target="new_password"><i class="fa fa-eye"></i></span>
                        </div>
                    </div>
                    <div class="profile-field">
                        <label for="confirm_password">Confirm New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="off" />
                            <span class="toggle-password" data-target="confirm_password"><i class="fa fa-eye"></i></span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-save">SAVE INFORMATION</button>
            </form>
        </main>
    </div>

    <?php include '../assets/format/member_footer.php'; ?>    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const menuToggle = document.querySelector(".menu-toggle");
            const navLinks = document.querySelector("nav");

            if (menuToggle) {
                menuToggle.addEventListener("click", () => {
                    navLinks.classList.toggle("active");
                });
            }

            // Enhanced profile image handling with mobile support
            const dropArea = document.getElementById('profileImageDrop');
            const fileInput = document.getElementById('profile_image');
            const previewImg = document.getElementById('profileImagePreview');
            const browseBtn = document.querySelector('.browse-btn');

            // Mobile-friendly file selection
            function openFileDialog(e) {
                e.preventDefault();
                e.stopPropagation();
                fileInput.click();
            }

            // Improved drag & drop (works better on mobile browsers that support it)
            if (dropArea) {
                // Prevent default drag behaviors
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropArea.addEventListener(eventName, preventDefaults, false);
                    document.body.addEventListener(eventName, preventDefaults, false);
                });

                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }

                // Highlight drop area on drag (desktop)
                ['dragenter', 'dragover'].forEach(eventName => {
                    dropArea.addEventListener(eventName, () => {
                        dropArea.classList.add('dragover');
                    }, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropArea.addEventListener(eventName, () => {
                        dropArea.classList.remove('dragover');
                    }, false);
                });

                // Handle drop
                dropArea.addEventListener('drop', function(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    if (files && files[0]) {
                        handleFileSelection(files[0]);
                    }
                });

                // Handle click/tap for file selection
                dropArea.addEventListener('click', openFileDialog);
                dropArea.addEventListener('touchend', openFileDialog);
            }

            // Browse button click handler
            if (browseBtn) {
                browseBtn.addEventListener('click', openFileDialog);
                browseBtn.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    openFileDialog(e);
                });
            }

            // Handle file input change
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    if (fileInput.files && fileInput.files[0]) {
                        handleFileSelection(fileInput.files[0]);
                    }
                });
            }

            function handleFileSelection(file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file (JPG, PNG, or GIF).');
                    return;
                }

                // Validate file size (2MB limit)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Image size should not exceed 2MB.');
                    return;
                }

                // Show preview
                showPreview(file);
                
                // Create a new FileList with the selected file
                const dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
            }

            function showPreview(file) {
                if (!file.type.startsWith('image/')) return;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (previewImg) {
                        previewImg.src = e.target.result;
                    }
                };
                reader.onerror = function() {
                    alert('Error reading the selected file.');
                };
                reader.readAsDataURL(file);
            }

            // Scroll to Change Password section when clicking the edit icon
            const changePasswordBtn = document.querySelector('.edit-icon[title="Change Password"]');
            const passwordSection = document.querySelector('.password-section');
            if (changePasswordBtn && passwordSection) {
                changePasswordBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    passwordSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                });
            }

            // Enhanced password visibility toggle with better mobile support
            document.querySelectorAll('.toggle-password').forEach(function(toggle) {
                // Handle both click and touch events
                ['click', 'touchend'].forEach(eventType => {
                    toggle.addEventListener(eventType, function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const targetId = this.getAttribute('data-target');
                        const input = document.getElementById(targetId);
                        if (input) {
                            if (input.type === 'password') {
                                input.type = 'text';
                                this.innerHTML = '<i class="fa fa-eye-slash"></i>';
                            } else {
                                input.type = 'password';
                                this.innerHTML = '<i class="fa fa-eye"></i>';
                            }
                        }
                    });
                });
            });

            // Form validation with mobile-friendly alerts
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const phoneInput = document.querySelector('input[name="phone"]');
                    const profileImageInput = document.getElementById('profile_image');
                    
                    // Basic phone validation
                    if (phoneInput && phoneInput.value.trim()) {
                        const phonePattern = /^[\+]?[0-9\s\-\(\)]+$/;
                        if (!phonePattern.test(phoneInput.value.trim())) {
                            e.preventDefault();
                            alert('Please enter a valid phone number.');
                            phoneInput.focus();
                            return;
                        }
                    }

                    // Password validation
                    const oldPassword = document.getElementById('old_password');
                    const newPassword = document.getElementById('new_password');
                    const confirmPassword = document.getElementById('confirm_password');

                    if (oldPassword && (oldPassword.value || newPassword.value || confirmPassword.value)) {
                        if (!oldPassword.value) {
                            e.preventDefault();
                            alert('Old password is required to change password.');
                            oldPassword.focus();
                            return;
                        }
                        if (!newPassword.value) {
                            e.preventDefault();
                            alert('New password cannot be empty.');
                            newPassword.focus();
                            return;
                        }
                        if (newPassword.value !== confirmPassword.value) {
                            e.preventDefault();
                            alert('New password and confirm password do not match.');
                            confirmPassword.focus();
                            return;
                        }
                        if (newPassword.value.length < 6) {
                            e.preventDefault();
                            alert('New password should be at least 6 characters long.');
                            newPassword.focus();
                            return;
                        }
                    }
                });
            }

            // Prevent zoom on iOS for input focus
            if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
                document.querySelectorAll('input[type="text"], input[type="tel"], input[type="password"]').forEach(input => {
                    input.addEventListener('focus', function() {
                        this.style.fontSize = '16px';
                    });
                    input.addEventListener('blur', function() {
                        this.style.fontSize = '';
                    });
                });
            }
        });
    </script>

</body>

</html>