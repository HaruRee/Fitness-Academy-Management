<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo "<div class='alert alert-danger'>Unauthorized access.</div>";
    exit;
}

// Check if payment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid payment ID.</div>";
    exit;
}

$paymentId = $_GET['id'];

// Get payment details
try {
    $stmt = $conn->prepare("SELECT p.*, u.First_Name, u.Last_Name, u.Email, u.UserID, u.Username 
                          FROM payments p
                          LEFT JOIN users u ON p.user_id = u.UserID
                          WHERE p.id = :id");
    $stmt->bindParam(':id', $paymentId, PDO::PARAM_INT);
    $stmt->execute();

    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        echo "<div class='alert alert-danger'>Payment not found.</div>";
        exit;
    }

    // Format payment details
    $paymentDetails = json_decode($payment['payment_details'], true);

    // Display payment details
    echo '<div class="row mb-4">
            <div class="col-md-6">
                <h5 class="border-bottom pb-2">Payment Information</h5>
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">Payment ID:</th>
                        <td>' . $payment['id'] . '</td>
                    </tr>
                    <tr>
                        <th>Amount:</th>
                        <td>₱ ' . number_format($payment['amount'], 2) . '</td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td>' . getStatusBadge($payment['status']) . '</td>
                    </tr>
                    <tr>
                        <th>Payment Method:</th>
                        <td>' . ucfirst($payment['payment_method']) . '</td>
                    </tr>
                    <tr>
                        <th>Transaction ID:</th>
                        <td>' . $payment['transaction_id'] . '</td>
                    </tr>
                    <tr>
                        <th>Payment Date:</th>
                        <td>' . date('F d, Y h:i A', strtotime($payment['payment_date'])) . '</td>
                    </tr>
                    <tr>
                        <th>Created At:</th>
                        <td>' . date('F d, Y h:i A', strtotime($payment['created_at'])) . '</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h5 class="border-bottom pb-2">Customer Information</h5>
                <table class="table table-borderless">
                    <tr>
                        <th width="40%">User ID:</th>
                        <td>' . $payment['UserID'] . '</td>
                    </tr>
                    <tr>
                        <th>Name:</th>
                        <td>' . htmlspecialchars($payment['First_Name'] . ' ' . $payment['Last_Name']) . '</td>
                    </tr>
                    <tr>
                        <th>Username:</th>
                        <td>' . htmlspecialchars($payment['Username']) . '</td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td>' . htmlspecialchars($payment['Email']) . '</td>
                    </tr>
                </table>
                <div class="text-end mt-3">
                    <a href="manage_users.php?action=view&id=' . $payment['UserID'] . '" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-user"></i> View User Profile
                    </a>
                </div>
            </div>
        </div>';    // Display payment details if available
    if ($paymentDetails) {
        echo '<div class="row">
                <div class="col-12">
                    <h5 class="border-bottom pb-2">Payment Details</h5>
                    <div class="bg-light p-3 rounded">';
        
        // Format specific payment details based on type
        if (isset($paymentDetails['type'])) {
            echo '<table class="table table-borderless">';
            
            // Common fields first
            foreach ($paymentDetails as $key => $value) {
                if ($key != 'items' && !is_array($value)) {
                    $formattedKey = str_replace('_', ' ', ucfirst($key));
                    echo '<tr>
                            <th width="30%">' . $formattedKey . ':</th>
                            <td>' . htmlspecialchars($value) . '</td>
                          </tr>';
                }
            }
            
            // If there's a subscription type
            if ($paymentDetails['type'] == 'subscription') {
                if (isset($paymentDetails['coach_id'])) {
                    echo '<tr class="table-info">
                            <th colspan="2">Coach Information</th>
                          </tr>
                          <tr>
                            <th>Coach ID:</th>
                            <td>' . htmlspecialchars($paymentDetails['coach_id']) . '</td>
                          </tr>
                          <tr>
                            <th>Coach Name:</th>
                            <td>' . htmlspecialchars($paymentDetails['coach_name']) . '</td>
                          </tr>';
                }
            }
            
            // If there are line items
            if (isset($paymentDetails['items']) && is_array($paymentDetails['items'])) {
                echo '<tr class="table-info">
                        <th colspan="2">Items</th>
                      </tr>';
                
                foreach ($paymentDetails['items'] as $item) {
                    echo '<tr>
                            <td colspan="2">
                                <div class="d-flex justify-content-between">
                                    <span>' . htmlspecialchars($item['name']) . '</span>
                                    <span>₱ ' . number_format($item['price'], 2) . '</span>
                                </div>';
                    
                    if (isset($item['description'])) {
                        echo '<small class="text-muted">' . htmlspecialchars($item['description']) . '</small>';
                    }
                    
                    echo '</td>
                          </tr>';
                }
            }
            
            echo '</table>';
        } else {
            // Fallback if the structure doesn't match expected format
            echo '<pre class="mb-0" style="white-space: pre-wrap;">' . json_encode($paymentDetails, JSON_PRETTY_PRINT) . '</pre>';
        }
          echo '    </div>
                </div>
            </div>';
    }

    // Display admin actions
    echo '<div class="row mt-4">
            <div class="col-12">
                <h5 class="border-bottom pb-2">Admin Actions</h5>
                <div class="btn-group" role="group">
                    <a href="print_receipt.php?id=' . $payment['id'] . '" class="btn btn-outline-secondary" target="_blank">
                        <i class="fas fa-print"></i> Print Receipt
                    </a>
                </div>
            </div>
        </div>';
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error retrieving payment details: " . $e->getMessage() . "</div>";
}

// Helper function to display status badge
function getStatusBadge($status)
{
    switch ($status) {
        case 'completed':
            return '<span class="badge bg-success">Completed</span>';
        case 'pending':
            return '<span class="badge bg-warning">Pending</span>';
        case 'failed':
            return '<span class="badge bg-danger">Failed</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
