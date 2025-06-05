<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

session_start();

// Generate unique request ID for tracking
$requestId = uniqid('api_req_', true);
error_log("[$requestId] AI Recommendations API call started");

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Member') {
    error_log("[$requestId] Unauthorized access attempt - User ID: " . ($_SESSION['user_id'] ?? 'none') . ", Role: " . ($_SESSION['role'] ?? 'none'));
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'request_id' => $requestId]);
    exit;
}

require '../config/database.php';
require '../config/api_config.php';

// AI Service class
class AIRecommendationService {
    private $apiKey;
    private $apiUrl;
    private $model;
    
    public function __construct() {
        $this->apiKey = OPENROUTER_API_KEY;
        $this->apiUrl = OPENROUTER_API_URL;
        $this->model = AI_MODEL;
    }

    public function getPersonalizedRecommendations($userProfile) {
        $startTime = microtime(true);
        $requestId = uniqid('ai_req_', true);
        
        try {
            // Validate profile data
            if (!$this->validateUserProfile($userProfile)) {
                throw new Exception('Invalid user profile data provided');
            }
            
            $prompt = $this->buildPrompt($userProfile);
            
            $data = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a certified fitness expert. Provide exactly 6 short, actionable fitness recommendations. Each recommendation must be only 1-2 sentences maximum. Keep them concise and direct. Format as: "1. [brief recommendation]", "2. [brief recommendation]", etc.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 400,  // Reduced for shorter responses
                'temperature' => 0.9  // Higher temperature for more varied responses
            ];
            
            // Enhanced logging with request tracking
            error_log("[$requestId] AI API Call START - User BMI: {$userProfile['bmi']}, Goal: {$userProfile['goal']}");
            error_log("[$requestId] AI API Call - Profile: " . json_encode([
                'bmi' => $userProfile['bmi'],
                'goal' => $userProfile['goal'],
                'age' => $userProfile['age'] ?? 'unknown',
                'progress_trend' => $userProfile['progress_trend'] ?? 'none'
            ]));
            
            $response = $this->makeAPICall($data, $requestId);
            $recommendations = $this->parseRecommendations($response, $requestId);
            
            // Calculate timing and log success
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            error_log("[$requestId] AI API Call SUCCESS - Generated " . count($recommendations) . " recommendations in {$duration}ms");
            
            return $recommendations;
            
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            // Comprehensive error logging with context
            error_log("[$requestId] AI API ERROR after {$duration}ms: " . $e->getMessage());
            error_log("[$requestId] AI API ERROR - Full trace: " . $e->getTraceAsString());
            error_log("[$requestId] AI API ERROR - User Profile: " . json_encode($userProfile));
            error_log("[$requestId] AI API ERROR - Model: " . $this->model);
            error_log("[$requestId] AI API ERROR - API URL: " . $this->apiUrl);
            
            // Check API key validity
            if (empty($this->apiKey) || strlen($this->apiKey) < 20) {
                error_log("[$requestId] AI API ERROR - Invalid API key detected (length: " . strlen($this->apiKey) . ")");
            }
            
            // Return empty array on error
            error_log("[$requestId] AI API ERROR - No fallback recommendations available");
            
            return [];
        }
    }

    private function buildPrompt($profile) {
        $bmiCategory = $this->getBMICategory($profile['bmi']);
        $timestamp = time();
        $uniqueSeed = substr(md5($timestamp . $profile['bmi'] . $profile['goal']), 0, 8);
        
        $prompt = "Create 6 unique, personalized fitness recommendations for this user (Session ID: {$uniqueSeed}):\n\n";
        $prompt .= "User Profile:\n";
        $prompt .= "- Age: " . ($profile['age'] ?? 'Not specified') . "\n";
        $prompt .= "- BMI: " . $profile['bmi'] . " (" . $bmiCategory . ")\n";
        $prompt .= "- Goal: " . $profile['goal'] . "\n";
        $prompt .= "- Weight: " . round($profile['weight'], 1) . " kg\n";
        $prompt .= "- Height: " . round($profile['height'], 2) . " m\n";
        
        if (isset($profile['progress_trend'])) {
            $prompt .= "- Recent Progress: " . $profile['progress_trend'] . "\n";
        }
        
        // Add variety prompts to ensure unique responses
        $varietyPrompts = [
            "Focus on innovative and creative approaches to fitness and nutrition.",
            "Include some lesser-known but effective strategies for achieving goals.",
            "Emphasize practical, real-world applications that fit into daily life.",
            "Consider both mental and physical aspects of fitness transformation.",
            "Incorporate modern fitness trends and evidence-based practices.",
            "Think outside the box while maintaining scientific accuracy."
        ];
        
        $selectedVariety = $varietyPrompts[($timestamp % count($varietyPrompts))];
        
        $prompt .= "\nGuidelines: {$selectedVariety}\n\n";
        $prompt .= "Provide 6 brief, actionable recommendations (1-2 sentences each). Include:\n";
        $prompt .= "- Nutrition strategies\n";
        $prompt .= "- Exercise recommendations\n";
        $prompt .= "- Lifestyle tips\n";
        $prompt .= "- Progress tracking\n";
        $prompt .= "- Motivation advice\n";
        $prompt .= "- Implementation tips\n\n";
        $prompt .= "Keep each recommendation SHORT and direct. Format as: '1. [brief recommendation]'";
        return $prompt;
    }

    private function makeAPICall($data, $requestId = null) {
        $requestId = $requestId ?? uniqid('api_', true);
        
        // Validate API key
        if (empty($this->apiKey) || $this->apiKey === 'sk-or-v1-d46...ce4') {
            throw new Exception('Invalid or incomplete API key configured');
        }
        
        if (strlen($this->apiKey) < 20) {
            throw new Exception('API key appears to be incomplete (too short)');
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'),
                'X-Title: Gym Management System'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_VERBOSE => false
        ]);
        
        error_log("[$requestId] Making cURL request to: " . $this->apiUrl);
        error_log("[$requestId] Request payload size: " . strlen(json_encode($data)) . " bytes");
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $curlInfo = curl_getinfo($ch);
        
        // Enhanced curl error handling with detailed info
        if ($curlErrno) {
            curl_close($ch);
            $errorMsg = "Curl Error (Code: {$curlErrno}): {$curlError}";
            error_log("[$requestId] Curl Error Details: " . $errorMsg);
            error_log("[$requestId] Curl Info: " . json_encode($curlInfo));
            throw new Exception($errorMsg);
        }
        
        curl_close($ch);
        
        // Log comprehensive response details
        error_log("[$requestId] HTTP Response Code: " . $httpCode);
        error_log("[$requestId] Response Size: " . strlen($response) . " bytes");
        error_log("[$requestId] Content Type: " . ($curlInfo['content_type'] ?? 'unknown'));
        error_log("[$requestId] Total Time: " . round(($curlInfo['total_time'] ?? 0) * 1000, 2) . "ms");
        
        // Log first 500 chars of response for debugging
        if (strlen($response) > 0) {
            error_log("[$requestId] Response Preview: " . substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''));
        } else {
            error_log("[$requestId] Response is empty!");
        }
        
        // Enhanced HTTP error handling
        if ($httpCode !== 200) {
            $errorMsg = "API returned HTTP {$httpCode}";
            
            // Try to parse error response for more details
            $errorData = json_decode($response, true);
            if ($errorData) {
                if (isset($errorData['error'])) {
                    $apiError = is_array($errorData['error']) ? 
                        ($errorData['error']['message'] ?? json_encode($errorData['error'])) : 
                        $errorData['error'];
                    $errorMsg .= ": " . $apiError;
                    
                    // Log specific error types
                    if (strpos($apiError, 'rate limit') !== false) {
                        error_log("[$requestId] RATE LIMIT ERROR detected");
                    } elseif (strpos($apiError, 'quota') !== false) {
                        error_log("[$requestId] QUOTA EXCEEDED ERROR detected");
                    } elseif (strpos($apiError, 'auth') !== false) {
                        error_log("[$requestId] AUTHENTICATION ERROR detected");
                    }
                } else {
                    $errorMsg .= ": " . substr($response, 0, 200);
                }
            } else {
                $errorMsg .= ": " . substr($response, 0, 200);
            }
            
            error_log("[$requestId] HTTP Error: " . $errorMsg);
            throw new Exception($errorMsg);
        }
        
        // Validate response is not empty
        if (empty($response)) {
            error_log("[$requestId] Empty response received from API");
            throw new Exception('Empty response from API');
        }
        
        $decoded = json_decode($response, true);
        
        // Enhanced JSON validation
        if (json_last_error() !== JSON_ERROR_NONE) {
            $jsonError = json_last_error_msg();
            error_log("[$requestId] JSON Parse Error: " . $jsonError);
            error_log("[$requestId] Raw response (first 1000 chars): " . substr($response, 0, 1000));
            throw new Exception("Failed to parse JSON response: " . $jsonError);
        }
        
        if (!$decoded) {
            error_log("[$requestId] Decoded response is null/false");
            throw new Exception('Invalid JSON response from API');
        }
        
        // Validate response structure with detailed logging
        if (!isset($decoded['choices'])) {
            error_log("[$requestId] Response Structure Error - Missing 'choices' field");
            error_log("[$requestId] Available fields: " . implode(', ', array_keys($decoded)));
            error_log("[$requestId] Full response: " . json_encode($decoded));
            throw new Exception('Invalid API response: missing choices array');
        }
        
        if (empty($decoded['choices'])) {
            error_log("[$requestId] Empty choices array in response");
            throw new Exception('No choices returned from API');
        }
        
        if (!isset($decoded['choices'][0]['message']['content'])) {
            error_log("[$requestId] Response Structure Error - Missing message content");
            error_log("[$requestId] First choice structure: " . json_encode($decoded['choices'][0] ?? 'No first choice'));
            throw new Exception('Invalid API response: missing message content');
        }
        
        $content = $decoded['choices'][0]['message']['content'];
        error_log("[$requestId] Successfully extracted content - length: " . strlen($content) . " characters");
        
        return $content;
    }

    private function parseRecommendations($text, $requestId = null) {
        $requestId = $requestId ?? uniqid('parse_', true);
        
        if (empty($text)) {
            error_log("[$requestId] Parse Error: Empty response text");
            throw new Exception('Empty response from AI');
        }
        
        // Log parsing attempt
        error_log("[$requestId] Parsing AI response - length: " . strlen($text) . " chars");
        error_log("[$requestId] Response preview: " . substr($text, 0, 300) . "...");
        
        // Clean the text - remove extra whitespace and normalize line endings
        $text = trim($text);
        $text = preg_replace('/\r\n|\r/', "\n", $text); // Normalize line endings
        $text = preg_replace('/\n\s*\n/', "\n", $text); // Remove empty lines
        
        $lines = explode("\n", $text);
        $recommendations = [];
        $processedLines = 0;
        
        foreach ($lines as $lineNum => $line) {
            $line = trim($line);
            $processedLines++;
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // Look for numbered recommendations with more flexible patterns
            if (preg_match('/^(\d+)[\.\)\:]?\s*(.+)/', $line, $matches)) {
                $recommendation = trim($matches[2]);
                if (!empty($recommendation)) {
                    $recommendations[] = $recommendation;
                    error_log("[$requestId] Found recommendation " . count($recommendations) . ": " . substr($recommendation, 0, 80) . "...");
                }
            } else {
                // Log lines that don't match the pattern for debugging
                if (strlen($line) > 10) { // Only log substantial lines
                    error_log("[$requestId] Unmatched line " . ($lineNum + 1) . ": " . substr($line, 0, 50) . "...");
                }
            }
        }
        
        error_log("[$requestId] Processed " . $processedLines . " lines, found " . count($recommendations) . " recommendations");
        
        // If insufficient recommendations, throw error instead of using fallbacks
        if (count($recommendations) < 6) {
            error_log("[$requestId] Insufficient recommendations found (" . count($recommendations) . "/6)");
            
            // Try alternative parsing methods
            $altRecommendations = $this->alternativeParsingMethods($text, $requestId);
            $recommendations = array_merge($recommendations, $altRecommendations);
            
            error_log("[$requestId] After alternative parsing: " . count($recommendations) . " recommendations");
            
            // If still insufficient, throw error
            if (count($recommendations) < 6) {
                throw new Exception("AI generated insufficient recommendations (" . count($recommendations) . "/6)");
            }
        }
        
        // Ensure we return exactly 6 recommendations
        $finalRecommendations = array_slice($recommendations, 0, 6);
        
        // Validate each recommendation and convert markdown formatting
        $validRecommendations = [];
        foreach ($finalRecommendations as $index => $rec) {
            if (is_string($rec) && strlen(trim($rec)) > 10) {
                // Convert markdown bold (**text**) to HTML bold (<b>text</b>)
                $rec = trim($rec);
                $rec = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $rec);
                
                $validRecommendations[] = $rec;
            } else {
                error_log("[$requestId] Invalid recommendation at index $index: " . var_export($rec, true));
            }
        }
        
        error_log("[$requestId] Final valid recommendations: " . count($validRecommendations));
        
        return $validRecommendations;
    }

    private function getBMICategory($bmi) {
        if ($bmi < 18.5) return 'underweight';
        if ($bmi < 24.9) return 'normal weight';
        if ($bmi < 29.9) return 'overweight';
        return 'obese';
    }
    
    private function validateUserProfile($profile) {
        $required = ['bmi', 'goal', 'weight', 'height'];
        
        foreach ($required as $field) {
            if (!isset($profile[$field]) || empty($profile[$field])) {
                error_log("Profile validation failed: missing required field '$field'");
                return false;
            }
        }
        
        // Validate BMI range
        if (!is_numeric($profile['bmi']) || $profile['bmi'] < 10 || $profile['bmi'] > 50) {
            error_log("Profile validation failed: invalid BMI value " . $profile['bmi']);
            return false;
        }
        
        // Validate goal
        $validGoals = ['Weight Loss', 'Muscle Gain', 'Maintenance'];
        if (!in_array($profile['goal'], $validGoals)) {
            error_log("Profile validation failed: invalid goal '" . $profile['goal'] . "'");
            return false;
        }
        
        return true;
    }
    
    private function alternativeParsingMethods($text, $requestId) {
        error_log("[$requestId] Attempting alternative parsing methods");
        $recommendations = [];
        
        // Method 1: Look for bullet points or dashes
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^[\-\*•]\s*(.+)/', $line, $matches)) {
                $rec = trim($matches[1]);
                if (strlen($rec) > 10) {
                    $recommendations[] = $rec;
                    error_log("[$requestId] Alt method 1 found: " . substr($rec, 0, 50) . "...");
                }
            }
        }
        
        // Method 2: Split by periods and look for substantial content
        if (count($recommendations) < 3) {
            $sentences = preg_split('/\.(?!\d)/', $text);
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (strlen($sentence) > 20 && !preg_match('/^(here|this|these|the|a|an)\s/i', $sentence)) {
                    $recommendations[] = $sentence;
                    error_log("[$requestId] Alt method 2 found: " . substr($sentence, 0, 50) . "...");
                    if (count($recommendations) >= 6) break;
                }
            }
        }
        
        error_log("[$requestId] Alternative parsing found " . count($recommendations) . " additional recommendations");
        return array_slice($recommendations, 0, 6);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $startTime = microtime(true);
        $userId = $_SESSION['user_id'];
        
        error_log("[$requestId] Processing request for User ID: $userId");
        
        // Get user's latest progress data
        $stmt = $conn->prepare("
            SELECT Weight, Height, Goal, RecordedAt 
            FROM memberprogress 
            WHERE UserID = ? 
            ORDER BY RecordedAt DESC 
            LIMIT 2
        ");
        $stmt->execute([$userId]);
        $progressData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($progressData)) {
            error_log("[$requestId] No progress data found for user $userId");
            echo json_encode([
                'error' => 'No progress data found. Please enter your weight and height first.',
                'recommendations' => [],
                'request_id' => $requestId
            ]);
            exit;
        }
        
        $latest = $progressData[0];
        $weight = $latest['Weight'];
        $height = $latest['Height'];
        $goal = $latest['Goal'];
        $bmi = round($weight / ($height * $height), 2);
        
        error_log("[$requestId] User profile - BMI: $bmi, Goal: $goal, Weight: {$weight}kg, Height: {$height}m");
        
        // Calculate progress trend if we have multiple records
        $progressTrend = null;
        if (count($progressData) >= 2) {
            $previous = $progressData[1];
            $weightDiff = $latest['Weight'] - $previous['Weight'];
            
            if ($goal === 'Weight Loss') {
                $progressTrend = $weightDiff < 0 ? "losing weight successfully" : "weight stable or increasing";
            } elseif ($goal === 'Muscle Gain') {
                $progressTrend = $weightDiff > 0 ? "gaining weight/muscle" : "weight stable or decreasing";
            } else {
                $progressTrend = "maintaining current weight";
            }
            
            error_log("[$requestId] Progress trend calculated: $progressTrend (weight change: " . round($weightDiff, 2) . "kg)");
        }
        
        // Get user's age from users table (if available)
        $ageStmt = $conn->prepare("SELECT YEAR(CURDATE()) - YEAR(DateOfBirth) as age FROM users WHERE UserID = ?");
        $ageStmt->execute([$userId]);
        $ageResult = $ageStmt->fetch(PDO::FETCH_ASSOC);
        $age = $ageResult['age'] ?? null;
        
        // Prepare user profile for AI with enhanced tracking
        $userProfile = [
            'weight' => $weight,
            'height' => $height,
            'bmi' => $bmi,
            'goal' => $goal,
            'age' => $age,
            'progress_trend' => $progressTrend,
            'session_id' => session_id(),
            'timestamp' => time(),
            'request_id' => $requestId
        ];
        
        error_log("[$requestId] Calling AI service with complete profile");
        
        // Get AI recommendations
        $aiService = new AIRecommendationService();
        $recommendations = $aiService->getPersonalizedRecommendations($userProfile);
        
        $processingTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // Validate recommendations
        if (!is_array($recommendations) || empty($recommendations)) {
            error_log("[$requestId] AI service returned invalid recommendations: " . var_export($recommendations, true));
            throw new Exception('AI service returned invalid recommendations');
        }
        
        $response = [
            'success' => true,
            'recommendations' => $recommendations,
            'profile' => [
                'bmi' => $bmi,
                'bmi_category' => $bmi < 18.5 ? 'underweight' : ($bmi < 24.9 ? 'normal' : ($bmi < 29.9 ? 'overweight' : 'obese')),
                'goal' => $goal,
                'progress_trend' => $progressTrend
            ],
            'meta' => [
                'request_id' => $requestId,
                'processing_time_ms' => $processingTime,
                'recommendations_count' => count($recommendations),
                'timestamp' => time()
            ]
        ];
        
        error_log("[$requestId] Successfully generated " . count($recommendations) . " recommendations in {$processingTime}ms");
        echo json_encode($response);
        
    } catch (PDOException $e) {
        $processingTime = round((microtime(true) - $startTime) * 1000, 2);
        error_log("[$requestId] Database error after {$processingTime}ms: " . $e->getMessage());
        echo json_encode([
            'error' => 'Database error occurred. Please try again later.',
            'recommendations' => [],
            'request_id' => $requestId
        ]);
    } catch (Exception $e) {
        $processingTime = round((microtime(true) - $startTime) * 1000, 2);
        error_log("[$requestId] General error after {$processingTime}ms: " . $e->getMessage());
        error_log("[$requestId] Error trace: " . $e->getTraceAsString());
        
        echo json_encode([
            'error' => 'Unable to generate recommendations. Please try again later.',
            'recommendations' => [],
            'debug' => [
                'error_type' => get_class($e),
                'processing_time_ms' => $processingTime,
                'request_id' => $requestId
            ]
        ]);
    }
} else {
    error_log("[$requestId] Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode([
        'error' => 'Method not allowed',
        'request_id' => $requestId
    ]);
}
?>
