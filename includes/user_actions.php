<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Initialize variables
$error = '';
$success = '';
$redirect = 'manage_users.php';

// Handle add user action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    // Get form data
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $phone = $_POST['phone'] ?? null;
    $address = $_POST['address'] ?? null;
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    $isApproved = isset($_POST['isApproved']) ? 1 : 0;

    try {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE Username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Username already exists. Please choose a different username.";
            header('Location: ' . $redirect);
            exit;
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE Email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Email already exists. Please use a different email address.";
            header('Location: ' . $redirect);
            exit;
        }

        // Hash the password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user into database
        $stmt = $conn->prepare("INSERT INTO users (Username, PasswordHash, Email, Role, First_Name, Last_Name, Phone, Address, DateOfBirth, IsActive, is_approved, email_confirmed) VALUES (:username, :password, :email, :role, :firstName, :lastName, :phone, :address, :dob, :isActive, :isApproved, 1)");

        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $passwordHash);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':firstName', $firstName);
        $stmt->bindParam(':lastName', $lastName);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':dob', $dob);
        $stmt->bindParam(':isActive', $isActive, PDO::PARAM_INT);
        $stmt->bindParam(':isApproved', $isApproved, PDO::PARAM_INT);

        $stmt->execute();

        // Log the action in audit trail
        $audit_stmt = $conn->prepare("INSERT INTO audit_trail (username, action, timestamp) VALUES (:username, :action, NOW())");
        $audit_stmt->bindParam(':username', $_SESSION['username']);
        $action = "added new user: " . $username;
        $audit_stmt->bindParam(':action', $action);
        $audit_stmt->execute();

        $_SESSION['success'] = "User added successfully.";
        header('Location: ' . $redirect);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error adding user: " . $e->getMessage();
        header('Location: ' . $redirect);
        exit;
    }
}

// Handle edit user action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    // Get form data
    $userId = $_POST['userId'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $phone = $_POST['phone'] ?? null;
    $address = $_POST['address'] ?? null;
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $isActive = isset($_POST['isActive']) ? 1 : 0;
    $isApproved = isset($_POST['isApproved']) ? 1 : 0;
    $emailConfirmed = isset($_POST['emailConfirmed']) ? 1 : 0;

    try {
        // Check if username already exists (except for current user)
        $stmt = $conn->prepare("SELECT * FROM users WHERE Username = :username AND UserID != :userId");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Username already exists. Please choose a different username.";
            header('Location: ' . $redirect);
            exit;
        }

        // Check if email already exists (except for current user)
        $stmt = $conn->prepare("SELECT * FROM users WHERE Email = :email AND UserID != :userId");
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Email already exists. Please use a different email address.";
            header('Location: ' . $redirect);
            exit;
        }

        // If password is provided, update it along with other fields
        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users SET Username = :username, PasswordHash = :password, Email = :email, Role = :role, First_Name = :firstName, Last_Name = :lastName, Phone = :phone, Address = :address, DateOfBirth = :dob, IsActive = :isActive, is_approved = :isApproved, email_confirmed = :emailConfirmed WHERE UserID = :userId");

            $stmt->bindParam(':password', $passwordHash);
        } else {
            // If no new password, update other fields only
            $stmt = $conn->prepare("UPDATE users SET Username = :username, Email = :email, Role = :role, First_Name = :firstName, Last_Name = :lastName, Phone = :phone, Address = :address, DateOfBirth = :dob, IsActive = :isActive, is_approved = :isApproved, email_confirmed = :emailConfirmed WHERE UserID = :userId");
        }

        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':firstName', $firstName);
        $stmt->bindParam(':lastName', $lastName);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':dob', $dob);
        $stmt->bindParam(':isActive', $isActive, PDO::PARAM_INT);
        $stmt->bindParam(':isApproved', $isApproved, PDO::PARAM_INT);
        $stmt->bindParam(':emailConfirmed', $emailConfirmed, PDO::PARAM_INT);
        $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);

        $stmt->execute();

        // Log the action in audit trail
        $audit_stmt = $conn->prepare("INSERT INTO audit_trail (username, action, timestamp) VALUES (:username, :action, NOW())");
        $audit_stmt->bindParam(':username', $_SESSION['username']);
        $action = "updated user ID: " . $userId;
        $audit_stmt->bindParam(':action', $action);
        $audit_stmt->execute();

        $_SESSION['success'] = "User updated successfully.";
        header('Location: ' . $redirect);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating user: " . $e->getMessage();
        header('Location: ' . $redirect);
        exit;
    }
}

// Handle user status change actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'change_status':
            handleStatusChange($conn);
            break;
        case 'reactivate':
            handleReactivation($conn);
            break;
        case 'bulk_action':
            handleBulkAction($conn);
            break;
    }
}

// Handle GET requests for quick actions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    switch ($_GET['action']) {
        case 'deactivate':
            quickStatusChange($conn, $_GET['id'], 'inactive', 'Manual deactivation by admin');
            break;
        case 'activate':
            quickStatusChange($conn, $_GET['id'], 'active', 'Manual activation by admin');
            break;
        case 'archive':
            quickStatusChange($conn, $_GET['id'], 'archived', 'Manual archiving by admin');
            break;
        case 'blacklist':
            quickStatusChange($conn, $_GET['id'], 'blacklisted', 'Manual blacklisting by admin');
            break;
    }
}

function handleStatusChange($conn) {
    if (!isset($_POST['user_id']) || !isset($_POST['new_status'])) {
        $_SESSION['error'] = "Missing required parameters.";
        header('Location: manage_users.php');
        exit;
    }

    $userId = $_POST['user_id'];
    $newStatus = $_POST['new_status'];
    $reason = $_POST['reason'] ?? 'Status changed by admin';
    $adminNotes = $_POST['admin_notes'] ?? '';

    try {
        $conn->beginTransaction();

        // Get current user data
        $stmt = $conn->prepare("SELECT account_status, Username, First_Name, Last_Name FROM users WHERE UserID = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentUser) {
            throw new Exception("User not found.");
        }

        $previousStatus = $currentUser['account_status'];
        
        // Update user status
        $updateFields = [
            'account_status = :new_status',
            'IsActive = :is_active'
        ];

        $isActive = ($newStatus === 'active') ? 1 : 0;

        if ($newStatus === 'inactive') {
            $updateFields[] = 'deactivation_date = NOW()';
            $updateFields[] = 'reactivation_date = NULL';
        } elseif ($newStatus === 'active' && $previousStatus !== 'active') {
            $updateFields[] = 'reactivation_date = NOW()';
            $updateFields[] = 'auto_deactivated = FALSE';
        }

        if (!empty($adminNotes)) {            $updateFields[] = 'admin_notes = CONCAT(COALESCE(admin_notes, ""), :admin_notes)';
        }

        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE UserID = :user_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':new_status', $newStatus);
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        
        if (!empty($adminNotes)) {
            $notesWithTimestamp = "[" . date('Y-m-d H:i:s') . "] " . $adminNotes . " ";
            $stmt->bindParam(':admin_notes', $notesWithTimestamp);
        }

        $stmt->execute();

        // Log status change in history
        $historyStmt = $conn->prepare("
            INSERT INTO user_status_history (user_id, previous_status, new_status, change_reason, changed_by)
            VALUES (:user_id, :previous_status, :new_status, :reason, :changed_by)
        ");
        $historyStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $historyStmt->bindParam(':previous_status', $previousStatus);
        $historyStmt->bindParam(':new_status', $newStatus);
        $historyStmt->bindParam(':reason', $reason);
        $historyStmt->bindParam(':changed_by', $_SESSION['user_id'], PDO::PARAM_INT);
        $historyStmt->execute();

        // Log in audit trail
        $auditStmt = $conn->prepare("INSERT INTO audit_trail (username, action, timestamp) VALUES (:username, :action, NOW())");
        $auditStmt->bindParam(':username', $_SESSION['username']);
        $action = "changed user status: {$currentUser['Username']} from '$previousStatus' to '$newStatus' - Reason: $reason";
        $auditStmt->bindParam(':action', $action);
        $auditStmt->execute();

        $conn->commit();
        $_SESSION['success'] = "User status changed successfully from '$previousStatus' to '$newStatus'.";

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error changing user status: " . $e->getMessage();
    }

    header('Location: manage_users.php');
    exit;
}

function handleReactivation($conn) {
    if (!isset($_POST['user_id'])) {
        $_SESSION['error'] = "Missing user ID.";
        header('Location: manage_users.php');
        exit;
    }

    $userId = $_POST['user_id'];
    $reason = $_POST['reason'] ?? 'Account reactivated by admin';

    try {
        $conn->beginTransaction();

        // Get current user data
        $stmt = $conn->prepare("SELECT account_status, Username, auto_deactivated FROM users WHERE UserID = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentUser) {
            throw new Exception("User not found.");
        }

        if ($currentUser['account_status'] === 'active') {
            throw new Exception("User is already active.");
        }        // Reactivate user
        $stmt = $conn->prepare("
            UPDATE users 
            SET account_status = 'active',
                IsActive = 1, 
                reactivation_date = NOW(), 
                auto_deactivated = FALSE,
                last_activity_date = NOW(),
                admin_notes = CONCAT(COALESCE(admin_notes, ''), '[', NOW(), '] Reactivated by admin: ', :reason, ' ')
            WHERE UserID = :user_id
        ");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':reason', $reason);
        $stmt->execute();

        // Log status change in history
        $historyStmt = $conn->prepare("
            INSERT INTO user_status_history (user_id, previous_status, new_status, change_reason, changed_by)
            VALUES (:user_id, :previous_status, 'active', :reason, :changed_by)
        ");
        $historyStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $historyStmt->bindParam(':previous_status', $currentUser['account_status']);
        $historyStmt->bindParam(':reason', $reason);
        $historyStmt->bindParam(':changed_by', $_SESSION['user_id'], PDO::PARAM_INT);
        $historyStmt->execute();

        // Log in audit trail
        $auditStmt = $conn->prepare("INSERT INTO audit_trail (username, action, timestamp) VALUES (:username, :action, NOW())");
        $auditStmt->bindParam(':username', $_SESSION['username']);
        $action = "reactivated user: {$currentUser['Username']} - Reason: $reason";
        $auditStmt->bindParam(':action', $action);
        $auditStmt->execute();

        $conn->commit();
        $_SESSION['success'] = "User account reactivated successfully.";

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error reactivating user: " . $e->getMessage();
    }

    header('Location: manage_users.php');
    exit;
}

function handleBulkAction($conn) {
    if (!isset($_POST['selected_users']) || !isset($_POST['bulk_action_type'])) {
        $_SESSION['error'] = "Missing required parameters for bulk action.";
        header('Location: manage_users.php');
        exit;
    }

    $selectedUsers = $_POST['selected_users'];
    $actionType = $_POST['bulk_action_type'];
    $reason = $_POST['bulk_reason'] ?? "Bulk action: $actionType";

    if (empty($selectedUsers)) {
        $_SESSION['error'] = "No users selected.";
        header('Location: manage_users.php');
        exit;
    }

    try {
        $conn->beginTransaction();
        $successCount = 0;
        $errorCount = 0;

        foreach ($selectedUsers as $userId) {
            try {
                quickStatusChange($conn, $userId, $actionType, $reason, false);
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                error_log("Bulk action error for user $userId: " . $e->getMessage());
            }
        }

        $conn->commit();
        
        if ($errorCount === 0) {
            $_SESSION['success'] = "Bulk action completed successfully. $successCount users processed.";
        } else {
            $_SESSION['error'] = "Bulk action completed with errors. $successCount successful, $errorCount failed.";
        }

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error in bulk action: " . $e->getMessage();
    }

    header('Location: manage_users.php');
    exit;
}

function quickStatusChange($conn, $userId, $newStatus, $reason, $redirect = true) {
    try {
        if (!$redirect) {
            // When called from bulk action, transaction is already started
            $needsTransaction = false;
        } else {
            $conn->beginTransaction();
            $needsTransaction = true;
        }

        // Get current user data
        $stmt = $conn->prepare("SELECT account_status, Username FROM users WHERE UserID = :id");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentUser) {
            throw new Exception("User not found.");
        }

        $previousStatus = $currentUser['account_status'];
        $isActive = ($newStatus === 'active') ? 1 : 0;

        // Update user status
        $updateFields = ['account_status = :new_status', 'IsActive = :is_active'];
        
        if ($newStatus === 'inactive') {
            $updateFields[] = 'deactivation_date = NOW()';
        } elseif ($newStatus === 'active' && $previousStatus !== 'active') {
            $updateFields[] = 'reactivation_date = NOW()';            $updateFields[] = 'auto_deactivated = FALSE';
            $updateFields[] = 'last_activity_date = NOW()';
        }

        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE UserID = :user_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':new_status', $newStatus);
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Log status change in history
        $historyStmt = $conn->prepare("
            INSERT INTO user_status_history (user_id, previous_status, new_status, change_reason, changed_by)
            VALUES (:user_id, :previous_status, :new_status, :reason, :changed_by)
        ");
        $historyStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $historyStmt->bindParam(':previous_status', $previousStatus);
        $historyStmt->bindParam(':new_status', $newStatus);
        $historyStmt->bindParam(':reason', $reason);
        $historyStmt->bindParam(':changed_by', $_SESSION['user_id'], PDO::PARAM_INT);
        $historyStmt->execute();

        // Log in audit trail
        $auditStmt = $conn->prepare("INSERT INTO audit_trail (username, action, timestamp) VALUES (:username, :action, NOW())");
        $auditStmt->bindParam(':username', $_SESSION['username']);
        $action = "quick status change: {$currentUser['Username']} to '$newStatus' - $reason";
        $auditStmt->bindParam(':action', $action);
        $auditStmt->execute();

        if ($needsTransaction) {
            $conn->commit();
            $_SESSION['success'] = "User status changed to '$newStatus' successfully.";
        }

    } catch (Exception $e) {
        if ($needsTransaction) {
            $conn->rollBack();
            $_SESSION['error'] = "Error changing user status: " . $e->getMessage();
        } else {
            throw $e; // Re-throw for bulk action handling
        }
    }

    if ($redirect) {
        header('Location: manage_users.php');
        exit;
    }
}

// If we get here, it means an invalid action was requested
$_SESSION['error'] = "Invalid action requested.";
header('Location: ' . $redirect);
exit;
