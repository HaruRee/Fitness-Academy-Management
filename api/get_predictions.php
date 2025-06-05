<?php
// Prevent PHP errors from breaking JSON output
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set timezone and request tracking
date_default_timezone_set('Asia/Manila');

// Set proper JSON headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Register error handler to convert PHP errors to JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error: [$errno] $errstr in $errfile on line $errline");
    if (headers_sent()) return false;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => "PHP Error: $errstr",
        'details' => "$errfile line $errline"
    ]);
    exit(1);
});

// Include required files
require_once '../config/database.php';
require_once '../config/api_config.php';
$requestId = uniqid('predict_', true);

// Business Intelligence & AI Analytics Service
class BusinessIntelligenceService {
    private $apiKey;
    private $apiUrl;
    private $model;
    private $conn;
    private $requestId;      public function __construct($conn, $requestId) {
        $this->apiKey = OPENROUTER_API_KEY;
        $this->apiUrl = OPENROUTER_API_URL;
        $this->model = AI_MODEL; // Use only the configured model
        $this->conn = $conn;
        $this->requestId = $requestId;
    }
    
    public function generatePredictions() {
        try {
            $startTime = microtime(true);
            error_log("[{$this->requestId}] Starting business intelligence predictions generation");
            
            // Get data for analysis from database
            $membershipData = $this->getMembershipTrends();
            $revenueData = $this->getRevenueTrends();
            $attendanceData = $this->getAttendancePatterns();
              // Generate insights using AI
            $membershipPredictions = $this->generateAIInsights('membership', $membershipData, $revenueData);
            $revenuePredictions = $this->generateAIInsights('revenue', $revenueData, $membershipData);
            
            // Format and return predictions
            $predictions = [
                'membership' => $membershipPredictions,
                'revenue' => $revenuePredictions
            ];
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            error_log("[{$this->requestId}] Predictions generated in {$duration}ms");
            
            return [
                'success' => true,
                'predictions' => $predictions,
                'generated_at' => date('Y-m-d H:i:s'),
                'processing_time_ms' => $duration
            ];
            
        } catch (Exception $e) {
            error_log("[{$this->requestId}] Error generating predictions: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'request_id' => $this->requestId
            ];
        }
    }
    

private function getMembershipTrends() {
        $dailySignups = [];
        $memberStatus = ['total_members' => 0, 'active_members' => 0];
        $planDistribution = [];
        
        try {
            // Get new member signups over time
            $stmt = $this->conn->prepare("
                SELECT 
                    DATE(RegistrationDate) as reg_date,
                    COUNT(*) as new_members
                FROM users
                WHERE RegistrationDate >= DATE_SUB(CURRENT_DATE(), INTERVAL 90 DAY)
                AND Role = 'Member'
                GROUP BY DATE(RegistrationDate)
                ORDER BY reg_date ASC
            ");
            $stmt->execute();
            $dailySignups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get active vs inactive members
            $stmt = $this->conn->prepare("
                SELECT 
                    COUNT(*) as total_members,
                    SUM(CASE WHEN IsActive = 1 THEN 1 ELSE 0 END) as active_members
                FROM users
                WHERE Role = 'Member'
            ");
            $stmt->execute();
            $memberStatus = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Handle null values for memberStatus
            if (!$memberStatus) {
                $memberStatus = ['total_members' => 0, 'active_members' => 0];
            }
            
            // Get membership plan distribution
            $stmt = $this->conn->prepare("
                SELECT 
                    IFNULL(mp.name, 'None') as plan_name,
                    COUNT(*) as member_count
                FROM users u
                LEFT JOIN membershipplans mp ON u.plan_id = mp.id
                WHERE u.Role = 'Member'
                GROUP BY mp.name
                ORDER BY member_count DESC
            ");
            $stmt->execute();
            $planDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            // Log error but continue with default empty values
            error_log("[{$this->requestId}] Database error in getMembershipTrends: " . $e->getMessage());
        }
        
        return [
            'daily_signups' => $dailySignups,
            'member_status' => $memberStatus,
            'plan_distribution' => $planDistribution,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getRevenueTrends() {
        $dailyRevenue = [];
        $revenueByMethod = [];
        $transactionStats = ['avg_transaction' => 0, 'max_transaction' => 0, 'min_transaction' => 0];
        
        try {
            // Get daily revenue
            $stmt = $this->conn->prepare("
                SELECT 
                    DATE(payment_date) as payment_date,
                    SUM(amount) as daily_revenue,
                    COUNT(*) as transaction_count
                FROM payments
                WHERE payment_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 90 DAY)
                AND status = 'completed'
                GROUP BY DATE(payment_date)
                ORDER BY payment_date ASC
            ");
            $stmt->execute();
            $dailyRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get revenue by payment method
            $stmt = $this->conn->prepare("
                SELECT 
                    payment_method,
                    SUM(amount) as total_amount,
                    COUNT(*) as transaction_count
                FROM payments
                WHERE payment_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                AND status = 'completed'
                GROUP BY payment_method
                ORDER BY total_amount DESC
            ");
            $stmt->execute();
            $revenueByMethod = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get average transaction value
            $stmt = $this->conn->prepare("
                SELECT 
                    IFNULL(AVG(amount), 0) as avg_transaction,
                    IFNULL(MAX(amount), 0) as max_transaction,
                    IFNULL(MIN(amount), 0) as min_transaction
                FROM payments
                WHERE payment_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                AND status = 'completed'
            ");
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Handle null values for stats
            if ($stats) {
                $transactionStats = $stats;
            }
            
        } catch (PDOException $e) {
            // Log error but continue with default empty values
            error_log("[{$this->requestId}] Database error in getRevenueTrends: " . $e->getMessage());
        }
        
        return [
            'daily_revenue' => $dailyRevenue,
            'revenue_by_method' => $revenueByMethod,
            'transaction_stats' => $transactionStats,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getAttendancePatterns() {
        $dailyAttendance = [];
        
        try {            // Get daily check-ins
            $stmt = $this->conn->prepare("
                SELECT 
                    DATE(check_in_time) as check_in_date,
                    COUNT(*) as total_checkins
                FROM attendance_records
                WHERE check_in_time >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
                GROUP BY DATE(check_in_time)
                ORDER BY check_in_date ASC
            ");
            $stmt->execute();
            $dailyAttendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            // Log error but continue with default empty values
            error_log("[{$this->requestId}] Database error in getAttendancePatterns: " . $e->getMessage());
        }
        
        return [
            'daily_attendance' => $dailyAttendance,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function generateAIInsights($type, $primaryData, $secondaryData) {
        error_log("[{$this->requestId}] Generating AI insights for {$type}");
        
        // Format data for the AI prompt
        $dataContext = $this->formatDataContext($type, $primaryData, $secondaryData);
        
        // Build the prompt based on the type of prediction
        $systemPrompt = $this->buildSystemPrompt($type);
        $userPrompt = $this->buildUserPrompt($type, $dataContext);
        
        // Call AI API to generate insights
        $aiResponse = $this->callOpenRouterAPI($systemPrompt, $userPrompt, $type);
        
        // Parse the insights
        return $this->parseInsights($type, $aiResponse, $primaryData);
    }
    
    private function formatDataContext($type, $primaryData, $secondaryData) {
        // Convert raw data to a format suitable for the AI prompt
        $context = [];
        
        if ($type === 'membership') {
            // Calculate metrics from raw data
            $totalSignups = 0;
            $recentSignups = 0;
            
            if (!empty($primaryData['daily_signups'])) {
                foreach ($primaryData['daily_signups'] as $signup) {
                    $totalSignups += $signup['new_members'];
                    
                    // Consider the last 7 days as recent
                    $signupDate = new DateTime($signup['reg_date']);
                    $today = new DateTime();
                    $dayDiff = $today->diff($signupDate)->days;
                    
                    if ($dayDiff <= 7) {
                        $recentSignups += $signup['new_members'];
                    }
                }
            }
            
            // Status metrics
            $activeRate = 0;
            if (isset($primaryData['member_status'])) {
                $activeRate = ($primaryData['member_status']['total_members'] > 0) 
                    ? ($primaryData['member_status']['active_members'] / $primaryData['member_status']['total_members'] * 100) 
                    : 0;
            }
            
            // Structure data for AI
            $context = [
                'total_signups' => $totalSignups,
                'recent_signups' => $recentSignups,
                'active_rate' => round($activeRate, 2),
                'total_members' => $primaryData['member_status']['total_members'] ?? 0,
                'active_members' => $primaryData['member_status']['active_members'] ?? 0,
                'plan_distribution' => $primaryData['plan_distribution'] ?? [],
                'recent_revenue' => isset($secondaryData['daily_revenue']) 
                    ? array_slice($secondaryData['daily_revenue'], -7) 
                    : []
            ];
        }
        elseif ($type === 'revenue') {
            // Calculate revenue metrics
            $totalRevenue = 0;
            $recentRevenue = 0;
            
            if (!empty($primaryData['daily_revenue'])) {
                foreach ($primaryData['daily_revenue'] as $revenue) {
                    $totalRevenue += $revenue['daily_revenue'];
                    
                    // Consider the last 7 days as recent
                    $revenueDate = new DateTime($revenue['payment_date']);
                    $today = new DateTime();
                    $dayDiff = $today->diff($revenueDate)->days;
                    
                    if ($dayDiff <= 7) {
                        $recentRevenue += $revenue['daily_revenue'];
                    }
                }
            }
            
            // Structure data for AI
            $context = [
                'total_revenue' => $totalRevenue,
                'recent_revenue' => $recentRevenue,
                'avg_transaction' => $primaryData['transaction_stats']['avg_transaction'] ?? 0,
                'max_transaction' => $primaryData['transaction_stats']['max_transaction'] ?? 0,
                'payment_methods' => $primaryData['revenue_by_method'] ?? [],
                'recent_signups' => isset($secondaryData['daily_signups']) 
                    ? array_slice($secondaryData['daily_signups'], -7) 
                    : []
            ];
        }
        
        return $context;
    }
    
    private function buildSystemPrompt($type) {
        // Create system prompts based on prediction type
        if ($type === 'membership') {
            return 'You are a data-driven membership growth analyst for a fitness gym. Analyze the data and provide exactly 3 concise, actionable insights about membership trends and retention. Each insight must be no more than 1-2 sentences. Be specific, unique, and insightful - focus on data patterns that are not immediately obvious.';
        } 
        elseif ($type === 'revenue') {
            return 'You are a revenue optimization specialist for a fitness gym. Analyze the data and provide exactly 3 concise, actionable insights about revenue patterns and opportunities. Each insight must be no more than 1-2 sentences. Be specific, unique, and insightful - focus on data patterns that are not immediately obvious.';
        }
    }
    
    private function buildUserPrompt($type, $dataContext) {
        // Format data as stringified JSON for the AI
        $dataJson = json_encode($dataContext, JSON_PRETTY_PRINT);
        
        if ($type === 'membership') {
            return "Based on this membership data from our gym, provide 3 specific insights about membership trends and retention. Be concise and actionable.\n\nDATA: {$dataJson}\n\nAlso make a specific prediction for new members next week based on these patterns. Be creative but data-driven.";
        }        elseif ($type === 'revenue') {
            return "Based on this revenue data from our gym, provide 3 specific insights about revenue patterns and opportunities. Be concise and actionable.\n\nDATA: {$dataJson}\n\nAlso make a specific prediction for next week's revenue based on these patterns. Include an exact number. Be creative but data-driven.";
        }
    }
    
    private function callOpenRouterAPI($systemPrompt, $userPrompt, $type = null) {
        try {
            // Check API key
            if (empty($this->apiKey) || strlen($this->apiKey) < 20) {
                error_log("[{$this->requestId}] Invalid API key: length " . strlen($this->apiKey));
                throw new Exception('Invalid or missing OpenRouter API key');
            }
            
            // Log key length (not the actual key) for debugging
            error_log("[{$this->requestId}] Calling OpenRouter API with key length: " . strlen($this->apiKey));
            error_log("[{$this->requestId}] Using API URL: " . $this->apiUrl);
            error_log("[{$this->requestId}] Using model: " . $this->model);
  
            
            $data = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt
                    ]
                ],
                'max_tokens' => 300,
                'temperature' => 0.7
            ];
            
            // Check if curl is available
            if (!function_exists('curl_init')) {
                error_log("[{$this->requestId}] cURL is not available on this server");
                throw new Exception('cURL is not available on this server');
            }
            
            $ch = curl_init();
            if ($ch === false) {
                error_log("[{$this->requestId}] Failed to initialize cURL");
                throw new Exception('Failed to initialize cURL');
            }
            
            $postFields = json_encode($data);
            
            // If json_encode fails, log and throw
            if ($postFields === false) {
                error_log("[{$this->requestId}] Failed to encode request data: " . json_last_error_msg());
                throw new Exception('Failed to encode API request data: ' . json_last_error_msg());
            }
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'HTTP-Referer: ' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_VERBOSE => false,  // Set to false for production
                CURLOPT_FAILONERROR => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            
            curl_close($ch);
            
            if ($response === false || $curlError) {
                error_log("[{$this->requestId}] cURL Error ({$curlErrno}): {$curlError}");
                throw new Exception("API call failed: ({$curlErrno}) {$curlError}");
            }
            
            // Log response for debugging when not 200
            if ($httpCode !== 200) {
                error_log("[{$this->requestId}] API Error - HTTP Code {$httpCode}: " . substr($response, 0, 500));
                
                // Try to parse error response as JSON
                $errorData = json_decode($response, true);
                $errorMessage = isset($errorData['error']) ? 
                    (is_string($errorData['error']) ? $errorData['error'] : json_encode($errorData['error'])) : 
                    "Unknown API error";
                
                throw new Exception("API returned error code {$httpCode}: {$errorMessage}");
            }
            
            // Debug: Log raw response (truncated)
            error_log("[{$this->requestId}] Raw API response (first 100 chars): " . substr($response, 0, 100));
            
            $jsonResponse = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("[{$this->requestId}] JSON decode error: " . json_last_error_msg());
                error_log("[{$this->requestId}] Raw response (first 200 chars): " . substr($response, 0, 200));
                throw new Exception('Invalid JSON response from API: ' . json_last_error_msg());
            }
            
            // Log structure of response
            error_log("[{$this->requestId}] Response structure: " . json_encode(array_keys($jsonResponse)));
            
            return $jsonResponse;
            
        } catch (Exception $e) {
            error_log("[{$this->requestId}] API call exception: " . $e->getMessage());
            throw $e; // Re-throw to be handled by caller
        }
    }
  
      private function parseInsights($type, $aiResponse, $rawData) {
        error_log("[{$this->requestId}] Parsing AI insights for {$type}");
        
        // Check for OpenRouter API response format (should have choices array)
        if (isset($aiResponse['choices'][0]['message']['content'])) {
            $content = $aiResponse['choices'][0]['message']['content'];
        } 
        // Alternative format check - some models might return directly in a different structure
        else if (isset($aiResponse['message']['content'])) {
            $content = $aiResponse['message']['content'];
        }
        // Final fallback
        else if (isset($aiResponse['content']) || isset($aiResponse['text'])) {
            $content = isset($aiResponse['content']) ? $aiResponse['content'] : $aiResponse['text'];
        } 
        else {
            error_log("[{$this->requestId}] Unable to parse AI response: " . json_encode($aiResponse));
            throw new Exception('Invalid API response format - unable to locate content');
        }
          // Extract insights
        preg_match_all('/\d+\.?\s+(.+?)(?=\d+\.|\n\n|$)/s', $content, $insightsMatches);
        
        $insights = [];
        if (!empty($insightsMatches[1])) {
            foreach ($insightsMatches[1] as $insight) {                $insight = trim($insight);
                // Clean up any badly formatted asterisks first
                $insight = preg_replace('/\*+\s*\*+/', '**', $insight);
                
                // Ensure proper formatting for emphasis (bold text)
                // First standardize all formatting to ** for bold
                $insight = preg_replace('/\*([^*]+)\*(?!\*)/', '**$1**', $insight);
                
                // Make sure there are no spaces between asterisks and text
                $insight = preg_replace('/\*\*\s+/', '**', $insight);
                $insight = preg_replace('/\s+\*\*/', '**', $insight);
                
                // Add emphasis to key numbers and metrics if not already emphasized
                $insight = preg_replace_callback('/(\d+%|\d+\.\d+%|\$\d+|\₱\d+(?:,\d+)*(?:\.\d+)?)/m', 
                    function($matches) {
                        // Don't add asterisks if it's already inside asterisks
                        if (strpos($matches[0], '**') !== false) {
                            return $matches[0];
                        }
                        // Check if we're already inside a bold section
                        $pos = strpos($matches[0], 0);
                        $leftContext = substr($matches[0], 0, $pos);
                        $rightContext = substr($matches[0], $pos + strlen($matches[0]));
                        $countLeftAsterisks = substr_count($leftContext, '**') % 2;
                        $countRightAsterisks = substr_count($rightContext, '**') % 2;
                        
                        // Only add ** if we're not already in a bold section
                        if ($countLeftAsterisks == $countRightAsterisks) {
                            return '**' . $matches[0] . '**';
                        } else {
                            return $matches[0];
                        }
                    }, $insight);
                $insights[] = $insight;
            }
        } else {
            // Fallback - try to split by newlines if numbered format not found
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line) && strlen($line) > 10) {
                    // Add emphasis to key numbers and metrics
                    $line = preg_replace('/(\d+%|\d+\.\d+%|\$\d+|\₱\d+(?:,\d+)*(?:\.\d+)?)/m', '**$1**', $line);
                    $insights[] = $line;
                }
            }
            $insights = array_slice($insights, 0, 3);
        }
          // Extract prediction using regex
        if ($type === 'membership') {
            preg_match('/predict(?:ion|ed|ing)?(?:\s+for)?(?:\s+next\s+week)?\s*(?:is|:)?\s*(\d+)(?:\s+new)?\s+members/i', $content, $matches);
            
            // If prediction not found in content, look more broadly
            if (empty($matches[1])) {
                preg_match('/(\d+)(?:\s+new)?\s+members/i', $content, $matches);
            }
              $prediction = !empty($matches[1]) ? intval($matches[1]) : 0;
            
            return [
                'trend' => $this->detectTrend($rawData, $type),
                'prediction' => "Predicted {$prediction} new members next week",
                'insights' => array_slice($insights, 0, 3)
            ];
        }        elseif ($type === 'revenue') {
            // Try to find revenue prediction patterns with currency - more comprehensive approach
            preg_match('/(?:projected|predict|forecast|expect|next\s+week).*?(?:revenue|sales|income|earnings).*?(?:₱|P|\$|PHP)\s*(\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)/is', $content, $matches);
            
            // If not found, try broader patterns with currency
            if (empty($matches[1])) {
                preg_match('/(?:₱|P|\$|PHP)\s*(\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)/i', $content, $matches);
            }
            
            // If still not found, try patterns with numbers followed by currency
            if (empty($matches[1])) {
                preg_match('/(\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)\s*(?:peso|PHP|₱|\$)/i', $content, $matches);
            }
            
            // Try to find large numbers that might be revenue
            if (empty($matches[1])) {
                preg_match('/(?:approximately|around|about|estimated|estimate|projection|projecting|roughly).*?(\d{4,}(?:,\d{3})*(?:\.\d{1,2})?)/is', $content, $matches);
            }
            
            // Last resort - look for any larger number (likely to be revenue)
            if (empty($matches[1])) {
                preg_match('/\b(\d{4,}(?:,\d{3})*(?:\.\d{1,2})?)\b/i', $content, $matches); // Match any 4+ digit number
            }            // Get the prediction amount directly from the match without fallback calculation
            $amount = !empty($matches[1]) ? str_replace(',', '', $matches[1]) : 0;
            
            return [
                'trend' => $this->detectTrend($rawData, $type),
                'prediction' => "Projected revenue: ₱" . number_format(floatval($amount), 2) . " next week",
                'insights' => array_slice($insights, 0, 3)
            ];
        }
    }
  
    
    private function detectTrend($data, $type) {
        if ($type === 'membership' && !empty($data['daily_signups'])) {
            $signups = $data['daily_signups'];
            $count = count($signups);
            
            if ($count >= 4) {
                $firstHalf = array_slice($signups, 0, floor($count/2));
                $secondHalf = array_slice($signups, floor($count/2));
                
                $firstSum = 0;
                foreach ($firstHalf as $day) {
                    $firstSum += $day['new_members'];
                }
                
                $secondSum = 0;
                foreach ($secondHalf as $day) {
                    $secondSum += $day['new_members'];
                }
                
                return ($secondSum > $firstSum) ? 'increasing' : 'decreasing';
            }
        }
        elseif ($type === 'revenue' && !empty($data['daily_revenue'])) {
            $revenues = $data['daily_revenue'];
            $count = count($revenues);
            
            if ($count >= 4) {
                $firstHalf = array_slice($revenues, 0, floor($count/2));
                $secondHalf = array_slice($revenues, floor($count/2));
                
                $firstSum = 0;
                foreach ($firstHalf as $day) {
                    $firstSum += $day['daily_revenue'];
                }
                
                $secondSum = 0;
                foreach ($secondHalf as $day) {
                    $secondSum += $day['daily_revenue'];
                }
                
                return ($secondSum > $firstSum) ? 'increasing' : 'decreasing';
            }
        }
        
        return 'steady';
    }
  
}

// Initialize and execute the service



// Initialize and execute the service
try {
    error_log("[{$requestId}] Starting prediction service - API URL: " . OPENROUTER_API_URL);
    
    // Verify database connection
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    $biService = new BusinessIntelligenceService($conn, $requestId);
    $result = $biService->generatePredictions();
    
    // Set HTTP status code based on success flag
    if (!$result['success']) {
        http_response_code(500);
        error_log("[{$requestId}] Prediction service returned failure: " . 
            (isset($result['error']) ? $result['error'] : 'Unknown error'));
    } else {
        error_log("[{$requestId}] Prediction service completed successfully");
    }
    
    // Return JSON response
    echo json_encode($result);
    
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    $trace = $e->getTraceAsString();
    error_log("[{$requestId}] Critical error in get_predictions.php: {$errorMessage}");
    error_log("[{$requestId}] Stack trace: {$trace}");
      // Return error to frontend without fallback data
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate AI predictions: ' . $errorMessage,
        'request_id' => $requestId
    ]);
}
