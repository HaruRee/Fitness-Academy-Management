<?php
session_start();
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
    }

    try {
        $stmt = $conn->prepare("INSERT INTO memberprogress (UserID, Weight, Height, Goal, RecordedAt) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $weight, $height, $goal]);
    } catch (PDOException $e) {
        $progressMessage = "Error saving data: " . $e->getMessage();
    }
}

// For AJAX: return chart data as JSON
if (isset($_GET['action']) && $_GET['action'] === 'progress_chart') {
    header('Content-Type: application/json');
    try {
        $stmt = $conn->prepare("SELECT Weight, RecordedAt FROM memberprogress WHERE UserID = ? ORDER BY RecordedAt ASC");
        $stmt->execute([$userId]);
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
    }
    exit;
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
    }

    .analytics-card {
        background: #1e1e1e;
        padding: 20px;
        border-radius: 20px;
        color: white;
        box-shadow: 0 0 10px rgba(255, 255, 255, 0.05);
        flex: 1 1 320px;
        margin-bottom: 20px;
        min-width: 300px;
        max-width: 400px;
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
    }

    .analytics-form button:hover {
        background-color: #c00;
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
</style>

<div class="analytics-container">
    <div class="analytics-card">
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

            <label>Height:</label>
            <div class="form-row">
                <input type="number" step="0.01" name="height" required>
                <select name="height_unit">
                    <option value="m">m</option>
                    <option value="ft">ft</option>
                </select>
            </div>

            <label>Goal:</label>
            <select name="goal">
                <option>Weight Loss</option>
                <option>Muscle Gain</option>
                <option>Maintenance</option>
            </select>

            <button type="submit">Save</button>
        </form>

        <div style="margin-top: 15px;">
            <h3 style="margin-top: 20px;">BMI</h3>
            <p><strong><?php echo $bmi ?? 'N/A'; ?></strong></p>
            <p>
                <?php
                if ($bmi !== null) {
                    if ($bmi < 18.5) echo "Underweight";
                    elseif ($bmi < 24.9) echo "Normal";
                    elseif ($bmi < 29.9) echo "Overweight";
                    else echo "Obese";
                } else echo "No data yet.";
                ?>
            </p>
        </div>
    </div>

    <div class="analytics-card">
        <h3>Recommendations</h3>
        <div>
            <?php
            // Define recommendations for each BMI category
            $recommendations = [
                'underweight' => [
                    "Focus on resistance training to build muscle mass, and incorporate calorie-dense, nutritious foods into your meals.",
                    "Consider consulting a nutritionist for a personalized meal plan and avoid excessive cardio to prevent further weight loss.",
                    "Consume protein-rich foods like eggs, chicken, and legumes to support muscle growth and recovery.",
                    "Add healthy fats like avocados, nuts, and olive oil to your diet to increase calorie intake.",
                    "Engage in strength training exercises like weightlifting to promote muscle gain.",
                    "Eat small, frequent meals throughout the day to maintain a steady calorie intake.",
                    "Drink smoothies or shakes with fruits, nuts, and protein powder to boost calorie intake.",
                    "Include whole-grain bread, pasta, and cereals in your diet for sustained energy.",
                    "Snack on nuts, seeds, and dried fruits between meals to increase calorie consumption.",
                    "Avoid skipping meals and aim to eat every 3-4 hours to maintain a steady calorie intake.",
                    "Incorporate dairy products like milk, cheese, and yogurt for additional protein and calories.",
                    "Stay consistent with your workout routine to ensure gradual and healthy weight gain.",
                    // Three additional recommendations for underweight
                    "Increase your intake of nutrient-dense snacks throughout the day for additional energy.",
                    "Incorporate smoothies with peanut butter or almonds to further boost your calorie intake.",
                    "Consider small protein shakes as supplemental snacks between meals."
                ],
                'normal' => [
                    "Maintain your healthy lifestyle with a balanced mix of strength training, cardio, and flexibility exercises.",
                    "Continue monitoring your progress and adjust your routine as needed to stay fit and healthy.",
                    "Incorporate activities like yoga or pilates to improve flexibility and mental well-being.",
                    "Focus on maintaining a balanced diet with a mix of protein, carbs, and healthy fats.",
                    "Stay consistent with your workout routine to maintain your current fitness level.",
                    "Include active recovery days, such as light walking or stretching, to prevent burnout.",
                    "Drink plenty of water throughout the day to stay hydrated and support overall health.",
                    "Experiment with new workout routines or sports to keep your fitness journey exciting.",
                    "Track your food intake to ensure you're meeting your nutritional needs.",
                    "Get at least 7-8 hours of sleep per night to support recovery and overall well-being.",
                    "Incorporate mindfulness practices like meditation to reduce stress and improve focus.",
                    "Challenge yourself with progressive overload in strength training to build muscle.",
                    // Three additional recommendations for normal
                    "Explore new recipes that incorporate lean proteins and whole grains.",
                    "Balance indulgent meals by planning lighter, nutrient-rich options.",
                    "Monitor your hydration levels and adjust your fluid intake based on your activity."
                ],
                'overweight' => [
                    "Prioritize regular cardio workouts, such as brisk walking, cycling, or swimming, and combine them with strength training.",
                    "Focus on portion control and a balanced diet to help manage your weight.",
                    "Set realistic goals and track your progress to boost motivation.",
                    "Incorporate high-intensity interval training (HIIT) to burn calories efficiently.",
                    "Reduce sugar and processed food intake to support weight loss.",
                    "Stay hydrated and ensure you're getting enough sleep to aid recovery and weight management.",
                    "Plan your meals ahead of time to avoid unhealthy food choices.",
                    "Include more vegetables and fruits in your meals to increase fiber intake.",
                    "Limit your intake of sugary beverages and replace them with water or herbal teas.",
                    "Find a workout buddy to stay motivated and accountable.",
                    "Practice mindful eating by savoring each bite and avoiding distractions during meals.",
                    "Reward yourself for reaching milestones with non-food-related treats, like a new book or workout gear.",
                    // Three additional recommendations for overweight
                    "Consider intermittent fasting to help manage your calorie intake if appropriate.",
                    "Replace high-calorie snacks with fiber-rich alternatives like fruits and vegetables.",
                    "Seek advice on portion size control from a nutrition expert to fine-tune your diet."
                ],
                'obese' => [
                    "Consult with a healthcare professional or coach for a tailored weight-loss program.",
                    "Start with low-impact cardio exercises and gradually increase intensity.",
                    "Adopt a healthy, calorie-controlled diet and seek support from fitness professionals.",
                    "Focus on small, achievable goals to build momentum and stay motivated.",
                    "Incorporate strength training to preserve muscle mass while losing fat.",
                    "Join a fitness community or group to stay accountable and motivated.",
                    "Limit your intake of high-calorie, low-nutrient foods like fast food and desserts.",
                    "Track your daily steps and aim to gradually increase your activity level.",
                    "Replace sedentary activities with light physical activities, like gardening or cleaning.",
                    "Practice portion control by using smaller plates and measuring your food.",
                    "Incorporate healthy snacks like raw veggies, nuts, and yogurt into your diet.",
                    "Celebrate small victories to maintain a positive mindset and stay on track.",
                    // Three additional recommendations for obese
                    "Incorporate daily walking routines to gradually build endurance.",
                    "Focus on reducing stress through practices like meditation or therapy, which can help with better eating habits.",
                    "Regularly review your progress and consult with professionals to adjust your diet plan as needed."
                ]
            ];

            // Generate random recommendations based on BMI
            $randomRecommendations = [];
            if ($bmi !== null) {
                if ($bmi < 18.5) {
                    $randomRecommendations = array_rand(array_flip($recommendations['underweight']), 6);
                } elseif ($bmi < 24.9) {
                    $randomRecommendations = array_rand(array_flip($recommendations['normal']), 6);
                } elseif ($bmi < 29.9) {
                    $randomRecommendations = array_rand(array_flip($recommendations['overweight']), 6);
                } else {
                    $randomRecommendations = array_rand(array_flip($recommendations['obese']), 6);
                }
            }

            if ($bmi !== null) {
                foreach ($randomRecommendations as $recommendation) {
                    echo "<p>" . htmlspecialchars($recommendation) . "</p>";
                }
            } else {
                echo "<p>Enter your weight and height to get personalized advice based on your BMI.</p>";
            }
            ?>
        </div>
    </div>

    <div class="analytics-card">
        <h3>My Progress</h3>
        <canvas id="progressChart" style="max-width: 100%;"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    fetch('../includes/member_analytics.php?action=progress_chart')
        .then(res => res.json())
        .then(data => {
            if (!data || !data.labels || data.labels.length === 0) {
                document.getElementById('progressChart').style.display = 'none';
                return;
            }
            new Chart(document.getElementById('progressChart'), {
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
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: true
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: 'Weight (kg)'
                            }
                        }
                    }
                }
            });
        });
</script>

<?php include '../assets/format/member_footer.php'; ?>