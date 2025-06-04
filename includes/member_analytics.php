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
        $stmt = $conn->prepare("INSERT INTO memberprogress (UserID, Weight, Height, Goal, RecordedAt) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $weight, $height, $goal]);
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
    }

    @media (max-width: 600px) {
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
        </div>

        <div class="analytics-card">
            <h3>Recommendations</h3>
        <div>
            <?php            // Define recommendations based on BMI category and goal
            $recommendations = [
                'underweight' => [
                    'Weight Loss' => [
                        "Focus on body recomposition rather than weight loss - build muscle while maintaining current weight",
                        "Prioritize resistance training with proper form over cardio exercises",
                        "Consume adequate protein (1.6-2.2g per kg of body weight) to preserve muscle mass",
                        "Eat nutrient-dense foods instead of reducing portions",
                        "Consider consulting a healthcare professional as weight loss may not be advisable",
                        "Focus on strength training 3-4 times per week with proper rest periods"
                    ],
                    'Muscle Gain' => [
                        "Implement a progressive overload strength training program",
                        "Eat in a caloric surplus of 300-500 calories above maintenance",
                        "Consume 1.6-2.2g of protein per kg of body weight",
                        "Include complex carbohydrates to fuel workouts and support muscle growth",
                        "Add healthy fats like nuts, avocados, and olive oil for extra calories",
                        "Drink calorie-dense smoothies between meals"
                    ],
                    'Maintenance' => [
                        "Focus on reaching a healthy BMI through muscle gain",
                        "Maintain a balanced diet with adequate protein and calories",
                        "Incorporate both strength training and light cardio",
                        "Track your food intake to ensure you're eating enough",
                        "Get adequate sleep to support recovery and muscle growth",
                        "Consider working with a nutritionist for a personalized plan"
                    ]
                ],
                'normal' => [
                    'Weight Loss' => [
                        "Create a moderate caloric deficit of 300-500 calories",
                        "Maintain high protein intake to preserve muscle mass",
                        "Include both strength training and cardio in your routine",
                        "Focus on nutrient-dense, whole foods",
                        "Practice portion control without extreme restrictions",
                        "Track progress through measurements and photos, not just weight"
                    ],
                    'Muscle Gain' => [
                        "Eat in a slight caloric surplus (200-300 calories)",
                        "Focus on progressive overload in strength training",
                        "Prioritize compound exercises for maximum muscle growth",
                        "Ensure adequate protein intake (1.6-2.2g per kg)",
                        "Get 7-9 hours of quality sleep for recovery",
                        "Plan your meals around your training schedule"
                    ],
                    'Maintenance' => [
                        "Balance your calorie intake with activity level",
                        "Maintain a consistent exercise routine",
                        "Mix up workouts to prevent plateaus",
                        "Focus on whole, nutrient-dense foods",
                        "Regular monitoring of weight and measurements",
                        "Adjust intake based on activity levels"
                    ]
                ],
                'overweight' => [
                    'Weight Loss' => [
                        "Create a sustainable caloric deficit of 500-750 calories",
                        "Combine strength training with regular cardio",
                        "Focus on high-protein, low-calorie foods",
                        "Implement portion control strategies",
                        "Track food intake and exercise consistently",
                        "Set realistic weekly weight loss goals (0.5-1kg)"
                    ],
                    'Muscle Gain' => [
                        "Focus on body recomposition through strength training",
                        "Maintain current calorie intake while increasing protein",
                        "Prioritize compound exercises with progressive overload",
                        "Include moderate cardio for heart health",
                        "Track measurements and progress photos",
                        "Get adequate rest between training sessions"
                    ],
                    'Maintenance' => [
                        "Focus on gradual weight loss while maintaining muscle",
                        "Balance strength training and cardio activities",
                        "Monitor portion sizes and food quality",
                        "Maintain consistent meal timing",
                        "Track progress through multiple metrics",
                        "Adjust routine based on progress"
                    ]
                ],
                'obese' => [
                    'Weight Loss' => [
                        "Begin with low-impact activities like walking or swimming",
                        "Create a moderate caloric deficit with professional guidance",
                        "Focus on whole, unprocessed foods",
                        "Start strength training with bodyweight exercises",
                        "Track progress with multiple metrics including how clothes fit",
                        "Set small, achievable weekly goals"
                    ],
                    'Muscle Gain' => [
                        "Focus on fat loss while building strength",
                        "Start with bodyweight and machine exercises",
                        "Maintain high protein intake while in a deficit",
                        "Include regular low-impact cardio",
                        "Work with a trainer for proper form",
                        "Progress gradually to prevent injury"
                    ],
                    'Maintenance' => [
                        "Focus on establishing healthy, sustainable habits",
                        "Combine strength training with regular cardio",
                        "Work with healthcare providers on a safe plan",
                        "Monitor progress through various metrics",
                        "Build a support system for accountability",
                        "Make gradual, sustainable changes"
                    ]
                ]
            ];
            
            // Get recommendations based on BMI and goal
            if ($bmi !== null && isset($latest['Goal'])) {
                $bmiCategory = '';
                if ($bmi < 18.5) {
                    $bmiCategory = 'underweight';
                } elseif ($bmi < 24.9) {
                    $bmiCategory = 'normal';
                } elseif ($bmi < 29.9) {
                    $bmiCategory = 'overweight';
                } else {
                    $bmiCategory = 'obese';
                }

                $goal = $latest['Goal'];
                
                if (isset($recommendations[$bmiCategory][$goal])) {
                    echo "<div class='recommendations-container' style='margin-top: 15px;'>";
                    echo "<p class='recommendation-intro' style='color: #fff; margin-bottom: 20px;'><strong>Your Personalized Fitness Plan</strong></p>";                    echo "<div style='background: #282828; padding: 15px; border-radius: 10px; margin-bottom: 20px;'>";
                    echo "<p style='margin-bottom: 10px;'><strong>Current Status:</strong></p>";
                    echo "<p style='margin-left: 15px; margin-bottom: 5px;'>• BMI: <strong>" . $bmi . "</strong></p>";
                    echo "<p style='margin-left: 15px; margin-bottom: 5px;'>• BMI Category: <strong>" . ucfirst($bmiCategory) . "</strong></p>";
                    echo "<p style='margin-left: 15px; margin-bottom: 15px;'>• Goal: <strong>" . $goal . "</strong></p>";
                    echo "</div>";
                    
                    echo "<div class='recommendation-list'>";
                    foreach ($recommendations[$bmiCategory][$goal] as $index => $recommendation) {
                        $number = $index + 1;
                        echo "<div style='background: #232323; margin-bottom: 10px; padding: 12px; border-radius: 8px;'>";
                        echo "<strong style='color: #eb3636;'>" . $number . ".</strong> " . htmlspecialchars($recommendation);
                        echo "</div>";
                    }
                    echo "</div></div>";
                } else {
                    echo "<p>Enter your weight and height to get personalized advice based on your BMI and goals.</p>";
                }
            } else {
                echo "<p>Enter your weight and height to get personalized advice based on your BMI and goals.</p>";
            }
            ?>
        </div>
    </div>    </div>

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
    loadChart('7');

    // Add click handlers for filter buttons
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
</script>
<?php include '../assets/format/member_footer.php'; ?>