<?php
// --- Chart API block must be at the very top, before any HTML output ---
session_start();
if (isset($_GET['action']) && $_GET['action'] === 'progress_chart') {
    require '../config/database.php';
    $userId = $_SESSION['user_id'] ?? null;
    header('Content-Type: application/json');
    if (!$userId) {
        echo json_encode(['labels' => [], 'weights' => []]);
        exit;
    }

    $range = $_GET['range'] ?? 'all';
    $whereClause = "WHERE UserID = ?";
    $params = [$userId];
    
    if ($range !== 'all') {
        $whereClause .= " AND RecordedAt >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $range;
    }

    try {
        $stmt = $conn->prepare("SELECT Weight, RecordedAt FROM memberprogress $whereClause ORDER BY RecordedAt ASC");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $labels = [];
        $weights = [];
        foreach ($rows as $row) {
            $labels[] = date('M d, Y', strtotime($row['RecordedAt']));
            $weights[] = round($row['Weight'], 2);
        }
        echo json_encode(['labels' => $labels, 'weights' => $weights]);
    } catch (Exception $e) {
        echo json_encode(['labels' => [], 'weights' => []]);
    }    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    header('Location: login.php');
    exit;
}

require '../config/database.php';

// Function to sync member progress to coach tracking
function syncMemberProgressToCoaches($conn, $userId, $weight, $height, $bmi) {
    try {
        // Find all coaches associated with this member through class enrollments
        $stmt = $conn->prepare("
            SELECT DISTINCT c.coach_id 
            FROM classenrollments ce 
            JOIN classes c ON ce.class_id = c.class_id 
            WHERE ce.user_id = ? AND ce.status = 'confirmed'
        ");
        $stmt->execute([$userId]);
        $coaches = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($coaches)) {
            // Check if a record already exists for today
            $checkStmt = $conn->prepare("
                SELECT id FROM clientprogress 
                WHERE user_id = ? AND date = CURDATE()
            ");
            $checkStmt->execute([$userId]);
            $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
              if ($existingRecord) {
                // Update existing record
                $updateStmt = $conn->prepare("
                    UPDATE clientprogress 
                    SET weight = ?, height = ?, bmi = ?, data_source = 'member_self', updated_at = NOW()
                    WHERE user_id = ? AND date = CURDATE()
                ");
                $updateStmt->execute([$weight, $height, $bmi, $userId]);
            } else {
                // Insert new record (coaches can see data from any of their associated coaches)
                $insertStmt = $conn->prepare("
                    INSERT INTO clientprogress (user_id, date, weight, height, bmi, data_source, recorded_by, created_at, updated_at) 
                    VALUES (?, CURDATE(), ?, ?, ?, 'member_self', ?, NOW(), NOW())
                ");
                // Use the first coach as the recorded_by for tracking purposes
                $recordedBy = $coaches[0];
                $insertStmt->execute([$userId, $weight, $height, $bmi, $recordedBy]);
            }
        }
    } catch (PDOException $e) {
        // Log error but don't break the main functionality
        error_log("Error syncing to coach progress: " . $e->getMessage());
    }
}

$userId = $_SESSION['user_id'];
$bmi = null;
$progressMessage = "Please enter your data to get started.";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $weight = floatval($_POST['weight']);
    $weight_unit = $_POST['weight_unit'];
    $height = floatval($_POST['height']);
    $height_unit = $_POST['height_unit'];
    $goal = $_POST['goal'];

    if ($weight_unit === 'lbs') {
        $weight = $weight * 0.453592;
    }
    if ($height_unit === 'ft') {
        $height = $height * 0.3048;
    }
    if ($height > 0) {
        $bmi = round($weight / ($height * $height), 2);
    } else {
        $bmi = null;
    }    try {
        // Insert into member progress table
        $stmt = $conn->prepare("INSERT INTO memberprogress (UserID, Weight, Height, Goal, RecordedAt) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $weight, $height, $goal]);
        
        // Sync data to coach progress tracking (clientprogress table)
        syncMemberProgressToCoaches($conn, $userId, $weight, $height, $bmi);
        
    } catch (PDOException $e) {
        echo "<p style='color:red'>Error saving data: " . $e->getMessage() . "</p>";
    }
}

// Fetch last two progress records for message
try {
    $stmt = $conn->prepare("SELECT Weight, Height, Goal, RecordedAt FROM memberprogress WHERE UserID = ? ORDER BY RecordedAt DESC LIMIT 2");
    $stmt->execute([$userId]);
    $progressData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($progressData) >= 1) {
        $latest = $progressData[0];
        $weight = $latest['Weight'];
        $height = $latest['Height'];
        $bmi = round($weight / ($height * $height), 2);

        if (count($progressData) === 2) {
            $previous = $progressData[1];
            $weightDiff = $latest['Weight'] - $previous['Weight'];
            if ($latest['Goal'] === 'Weight Loss') {
                $progressMessage = $weightDiff < 0 ? "You're losing weight. Keep it up!" : "No weight loss detected.";
            } elseif ($latest['Goal'] === 'Muscle Gain') {
                $progressMessage = $weightDiff > 0 ? "You're gaining muscle mass!" : "No gain yet. Adjust routine.";
            }
        }
    }
} catch (PDOException $e) {
    $progressMessage = "Error retrieving progress data.";
}

include '../assets/format/member_header.php';
?>

<style>
    body {
        background: #111;
        color: #fff;
        margin: 0;
        font-family: 'Segoe UI', Arial, sans-serif;
    }

    .analytics-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        justify-content: center;
        color: white;
        padding: 20px;
        box-sizing: border-box;
    }    .analytics-card {
        background: #1e1e1e;
        padding: 20px;
        border-radius: 20px;
        color: white;
        box-shadow: 0 0 10px rgba(255, 255, 255, 0.05);
        flex: 1 1 45%;
        margin-bottom: 20px;
        min-width: 300px;
        max-width: 600px;
        box-sizing: border-box;
        transition: box-shadow 0.2s;
    }

    .analytics-card:hover {
        box-shadow: 0 0 20px rgba(255, 0, 0, 0.15);
    }

    .analytics-card h3 {
        border-bottom: 2px solid red;
        padding-bottom: 5px;
        margin-bottom: 10px;
        font-size: 1.2em;
    }

    .analytics-form {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .form-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-row input[type=number] {
        flex: 1;
        padding: 12px;
        background: #111;
        border: 1px solid #444;
        color: white;
        border-radius: 5px;
        font-size: 1em;
    }

    .form-row select {
        width: 70px;
        padding: 10px;
        background: #111;
        border: 1px solid #444;
        color: white;
        border-radius: 5px;
        font-size: 1em;
    }

    .analytics-form select,
    .analytics-form button {
        padding: 12px;
        background: #111;
        border: 1px solid #444;
        color: white;
        border-radius: 5px;
        font-size: 1em;
    }

    .analytics-form button {
        background-color: red;
        border: none;
        color: white;
        cursor: pointer;
        font-size: 1.1em;
        font-weight: bold;
        margin-top: 10px;
        transition: background 0.2s;
    }    .analytics-form button:hover {
        background-color: #c00;
    }

    .date-filter-btn {
        padding: 8px 15px;
        background: #232323;
        border: 1px solid #444;
        color: white;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.9em;
        transition: all 0.2s;
    }

    .date-filter-btn:hover {
        background: #2a2a2a;
        border-color: #666;
    }    .date-filter-btn.active {
        background: #eb3636;
        border-color: #eb3636;
    }

    @media (max-width: 900px) {
        .analytics-container {
            flex-direction: column;
            align-items: stretch;
            gap: 15px;
            padding: 10px;
        }

        .analytics-card {
            max-width: 100%;
            min-width: 0;
            margin-bottom: 10px;
        }
    }    @media (max-width: 600px) {
        .analytics-container {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
            padding: 5px;
        }

        .analytics-card {
            padding: 12px;
            border-radius: 12px;
            font-size: 0.98em;
        }

        .analytics-card h3 {
            font-size: 1em;
        }

        .form-row {
            flex-direction: column;
            align-items: center;
            gap: 14px;
            margin-bottom: 8px;
        }

        .form-row input[type=number],
        .form-row select,
        .analytics-form select,
        .analytics-form button {
            width: 90%;
            max-width: 260px;
            min-width: 120px;
            margin: 0 auto;
            box-sizing: border-box;
        }
    }
      /* AI Recommendations Styles */
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .ai-recommendation-item {
        background: #232323;
        margin-bottom: 10px;
        padding: 12px;
        border-radius: 8px;
        opacity: 0;
        animation: fadeInUp 0.5s ease forwards;
        border-left: 3px solid #eb3636;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .ai-recommendation-item:nth-child(1) { animation-delay: 0.1s; }
    .ai-recommendation-item:nth-child(2) { animation-delay: 0.2s; }
    .ai-recommendation-item:nth-child(3) { animation-delay: 0.3s; }
    .ai-recommendation-item:nth-child(4) { animation-delay: 0.4s; }
    .ai-recommendation-item:nth-child(5) { animation-delay: 0.5s; }
    .ai-recommendation-item:nth-child(6) { animation-delay: 0.6s; }
    
    .ai-badge {
        background: linear-gradient(45deg, #eb3636, #ff6b6b);
        color: white;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 0.8em;
        font-weight: bold;
        box-shadow: 0 2px 8px rgba(235, 54, 54, 0.3);
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { box-shadow: 0 2px 8px rgba(235, 54, 54, 0.3); }
        50% { box-shadow: 0 2px 15px rgba(235, 54, 54, 0.5); }
        100% { box-shadow: 0 2px 8px rgba(235, 54, 54, 0.3); }
    }
    
    .ai-recommendation-item:hover {
        background: #2a2a2a;
        transform: translateX(5px);
        transition: all 0.3s ease;
    }
</style>    <div class="analytics-container">
        <div class="analytics-card">
            <div>
                <h3>My details</h3>
                <form method="POST" class="analytics-form">
                    <label>Weight:</label>
                    <div class="form-row">
                        <input type="number" step="0.1" name="weight" required>
                        <select name="weight_unit">
                            <option value="kg">kg</option>
                            <option value="lbs">lbs</option>
                        </select>
                    </div>

                    <label>Height:</label><div class="form-row">
                <input type="number" step="0.01" name="height" required>
                <select name="height_unit">
                    <option value="ft">ft</option>
                    <option value="m">m</option>
                </select>
            </div>

            <label>Goal:</label>
            <select name="goal" required>
                <option value="" disabled selected>Select goal</option>
                <option>Weight Loss</option>
                <option>Muscle Gain</option>
                <option>Maintenance</option>
            </select>            <button type="submit">Save</button>
        </form>
            </div>            <div style="margin-top: 30px;">
                <h3>My Progress</h3>
                <div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="date-filter-btn active" data-range="7">Last 7 Days</button>
                    <button type="button" class="date-filter-btn" data-range="30">Last 30 Days</button>
                    <button type="button" class="date-filter-btn" data-range="90">Last 3 Months</button>
                    <button type="button" class="date-filter-btn" data-range="all">All Time</button>
                </div>
                <div style="height: 300px; position: relative;">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>
        </div>        <div class="analytics-card">
            <h3>AI-Powered Recommendations</h3>
            <div>
                <?php if ($bmi !== null && isset($latest['Goal'])): ?>
                    <div class='recommendations-container' style='margin-top: 15px;'>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            <p class='recommendation-intro' style='color: #fff; margin: 0; flex: 1;'><strong>Your Personalized AI Fitness Plan</strong></p>
                            <span class="ai-badge">✨ AI Generated</span>
                        </div>
                        
                        <div style='background: #282828; padding: 15px; border-radius: 10px; margin-bottom: 20px;'>
                            <p style='margin-bottom: 10px;'><strong>Current Status:</strong></p>
                            <p style='margin-left: 15px; margin-bottom: 5px;'>• BMI: <strong><?php echo $bmi; ?></strong></p>
                            <p style='margin-left: 15px; margin-bottom: 5px;'>• BMI Category: <strong><?php 
                                if ($bmi < 18.5) echo 'Underweight';
                                elseif ($bmi < 24.9) echo 'Normal';
                                elseif ($bmi < 29.9) echo 'Overweight';
                                else echo 'Obese';
                            ?></strong></p>
                            <p style='margin-left: 15px; margin-bottom: 15px;'>• Goal: <strong><?php echo $latest['Goal']; ?></strong></p>
                        </div>
                        
                        <div id="aiRecommendationsContent">
                            <div id="loadingIndicator" style="text-align: center; padding: 20px;">
                                <div style="display: inline-block; width: 20px; height: 20px; border: 3px solid #444; border-radius: 50%; border-top-color: #eb3636; animation: spin 1s ease-in-out infinite;"></div>
                                <p style="margin-top: 10px; color: #ccc;">AI is analyzing your profile...</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px; background: #232323; border-radius: 10px;">
                        <div style="font-size: 3em; margin-bottom: 15px;">📊</div>
                        <p>Enter your weight and height to get AI-powered personalized recommendations</p>
                    </div>
                <?php endif; ?>
            </div>
        </div></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    let myChart = null;
    const ctx = document.getElementById('progressChart');
    
    function loadChart(range = '7') {
        if (!ctx) {
            console.error('Could not find progressChart canvas element');
            return;
        }

        // If there's an existing chart, destroy it
        if (myChart) {
            myChart.destroy();
        }

        // Remove any existing message
        const parent = ctx.parentElement.parentElement;
        const existingMessage = parent.querySelector('p');
        if (existingMessage) {
            existingMessage.remove();
        }

        fetch(`../includes/member_analytics.php?action=progress_chart&range=${range}`)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                if (!data || !data.labels || !data.weights || data.labels.length === 0) {
                    const message = document.createElement('p');
                    message.textContent = 'No progress data available for this time period.';
                    message.style.textAlign = 'center';
                    message.style.marginTop = '20px';
                    message.style.color = '#fff';
                    parent.appendChild(message);
                    return;
                }

                myChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Weight (kg)',
                            data: data.weights,
                            borderColor: '#36a2eb',
                            backgroundColor: 'rgba(54,162,235,0.15)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 750,
                            easing: 'easeInOutQuart'
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    color: '#fff',
                                    font: {
                                        size: 12
                                    },
                                    padding: 20,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                titleFont: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 13
                                },
                                padding: 12,
                                displayColors: false
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        scales: {
                            y: {
                                beginAtZero: false,
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)',
                                    drawBorder: false
                                },
                                ticks: {
                                    color: '#fff',
                                    padding: 10,
                                    font: {
                                        size: 11
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Weight (kg)',
                                    color: '#fff',
                                    font: {
                                        size: 13,
                                        weight: 'normal'
                                    },
                                    padding: {
                                        bottom: 10
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(255, 255, 255, 0.1)',
                                    drawBorder: false,
                                    display: false
                                },
                                ticks: {
                                    color: '#fff',
                                    maxRotation: 45,
                                    minRotation: 45,
                                    padding: 10,
                                    font: {
                                        size: 11
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Error loading progress chart:', error);
                const message = document.createElement('p');
                message.textContent = 'Unable to load progress chart. Please try again later.';
                message.style.color = 'red';
                message.style.textAlign = 'center';
                message.style.marginTop = '20px';
                parent.appendChild(message);
            });
    }
    
    // Initial chart load
    loadChart('7');    // Add click handlers for filter buttons
    document.querySelectorAll('.date-filter-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            // Update active state
            document.querySelectorAll('.date-filter-btn').forEach(btn => 
                btn.classList.remove('active')
            );
            e.target.classList.add('active');
            
            // Load chart with selected range
            loadChart(e.target.dataset.range);
        });
    });
      // AI Recommendations functionality
    document.addEventListener('DOMContentLoaded', function() {
        const loadingIndicator = document.getElementById('loadingIndicator');
        const recommendationsContent = document.getElementById('aiRecommendationsContent');
        
        // Automatically load AI recommendations if user has BMI data
        <?php if ($bmi !== null && isset($latest['Goal'])): ?>
        loadAIRecommendations();
        <?php endif; ?>        async function loadAIRecommendations() {
            const startTime = Date.now();
            
            try {
                // Add cache busting parameter and detailed headers
                const cacheBuster = new Date().getTime();
                const response = await fetch(`../api/get_ai_recommendations.php?t=${cacheBuster}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Cache-Control': 'no-cache',
                        'X-Requested-With': 'XMLHttpRequest'
                    }                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                const loadTime = Date.now() - startTime;
                
                console.log('AI API Response Data:', data);
                console.log('AI API Load Time:', loadTime + 'ms');
                
                if (data.success && data.recommendations && data.recommendations.length > 0) {
                    displayAIRecommendations(data.recommendations, data.profile, data.meta);
                } else {
                    throw new Error(data.error || 'No recommendations received');
                }
                
            } catch (error) {
                const loadTime = Date.now() - startTime;
                console.error('AI Recommendations Error:', error);
                console.error('Error occurred after:', loadTime + 'ms');
                
                // Enhanced error display with debugging info
                recommendationsContent.innerHTML = `
                    <div style="text-align: center; padding: 20px; background: #2a1a1a; border-radius: 10px; border: 1px solid #d32f2f;">
                        <div style="font-size: 2em; margin-bottom: 10px;">⚠️</div>
                        <p style="color: #ff6b6b; margin-bottom: 10px; font-weight: bold;">Unable to generate AI recommendations</p>
                        <p style="font-size: 0.9em; color: #999; margin-bottom: 15px;">${error.message}</p>
                        
                        <details style="margin: 15px 0; text-align: left;">
                            <summary style="cursor: pointer; color: #eb3636; margin-bottom: 10px;">🔧 Debug Information</summary>
                            <div style="background: #1a1a1a; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 0.8em;">
                                <p><strong>Load Time:</strong> ${loadTime}ms</p>
                                <p><strong>Timestamp:</strong> ${new Date().toISOString()}</p>
                                <p><strong>User Agent:</strong> ${navigator.userAgent.substring(0, 50)}...</p>
                                <p><strong>Session Storage:</strong> ${sessionStorage.length} items</p>
                                <p><strong>Network Status:</strong> ${navigator.onLine ? 'Online' : 'Offline'}</p>
                            </div>
                        </details>
                        
                        <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                            <button onclick="location.reload()" style="background: #eb3636; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">
                                🔄 Refresh Page
                            </button>
                            <button onclick="loadAIRecommendations()" style="background: #4caf50; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">
                                � Retry API Call
                            </button>
                        </div>
                    </div>
                `;
            }
        }
        
        function displayAIRecommendations(recommendations, profile, meta) {
            console.log('Displaying recommendations:', recommendations);
            console.log('Profile data:', profile);
            console.log('Meta data:', meta);
            
            let html = `<div class="recommendation-list">`;
            
            recommendations.forEach((recommendation, index) => {
                html += `
                    <div class="ai-recommendation-item">
                        <strong style="color: #eb3636;">${index + 1}.</strong> ${recommendation}
                    </div>
                `;
            });
              html += `

            `;
            
            recommendationsContent.innerHTML = html;
        }
    });
</script>
<?php include '../assets/format/member_footer.php'; ?>