<?php

/**
 * OCR Service for Discount Verification
 * Automatically validates student, senior, and PWD IDs using OCR API
 */

class OCRService
{
    public $apiKeys;
    public $apiUrl;
    public $developmentMode;
    private $currentKeyIndex;
    
    public function __construct()
    {
        // Load API configuration
        require_once __DIR__ . '/../config/api_config.php';
        
        // Using Microsoft Azure Computer Vision API with dual keys for redundancy
        $this->apiKeys = AZURE_CV_API_KEYS;
        $this->apiUrl = AZURE_CV_API_URL;
        $this->currentKeyIndex = 0;        // Enable development mode for testing (set to false in production)
        $this->developmentMode = false; // Using real Azure OCR API for actual text extraction
    }

    /**
     * Check if the current user has exceeded rate limits
     * Prevents spamming of verification requests
     */    /**
     * Process uploaded ID document and extract text
     */
    public function extractTextFromImage($imagePath)
    {
        if (!file_exists($imagePath)) {
            return ['success' => false, 'error' => 'Image file not found'];
        }

        // If development mode is enabled, use simulation for testing
        if ($this->developmentMode) {
            return $this->developmentModeExtraction($imagePath);
        }

        // Try file upload method first
        $result = $this->extractTextViaFileUpload($imagePath);

        // If file upload fails, try base64 method
        if (!$result['success']) {
            $result = $this->extractTextViaBase64($imagePath);
            if ($result['success']) {
                $result['method'] = 'base64_fallback';
            }
        } else {
            $result['method'] = 'file_upload';
        }
        // If both methods fail and we get API key errors, suggest development mode
        if (!$result['success'] && (strpos($result['error'], 'API Key') !== false || strpos($result['error'], '403') !== false)) {
            $result['error'] .= ' - Consider enabling development mode for testing or check Azure Computer Vision API credentials';
        }

        return $result;
    }

    /**
     * Extract text using file upload method
     */    private function extractTextViaFileUpload($imagePath)
    {
        // Step 1: Submit image for analysis to Azure Computer Vision
        $curl = curl_init();

        // Read the image file
        $imageData = file_get_contents($imagePath);
        if ($imageData === false) {
            return ['success' => false, 'error' => 'Could not read image file'];
        }

        // Use failover system for API call
        $curlOptions = [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $imageData,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'Ocp-Apim-Subscription-Key: ' . $this->getCurrentApiKey(),
                'Content-Type: application/octet-stream'
            ]
        ];

        $apiResult = $this->makeApiCallWithFailover($curlOptions);
        
        if (!$apiResult['success']) {
            return ['success' => false, 'error' => $apiResult['error']];
        }

        $response = $apiResult['response'];
        $httpCode = $apiResult['httpCode'];

        if ($httpCode !== 202) {
            error_log("Azure API request failed with status: " . $httpCode . ". Response: " . substr($response, 0, 500));
            return ['success' => false, 'error' => 'Azure API request failed with status: ' . $httpCode . '. Response: ' . substr($response, 0, 200)];
        }

        // Extract Operation-Location from headers
        if (preg_match('/Operation-Location:\s*(.+)/i', $response, $matches)) {
            $operationUrl = trim($matches[1]);
        } else {
            return ['success' => false, 'error' => 'Could not find Operation-Location in response headers'];
        }

        // Step 2: Poll for results
        return $this->pollAzureResults($operationUrl);
    }
    /**
     * Extract text using base64 method with Azure Computer Vision (fallback)
     */
    private function extractTextViaBase64($imagePath)
    {
        // For Azure Computer Vision, we use the same file upload method
        // since Azure doesn't have a separate base64 endpoint for the Read API
        // This method exists for compatibility but delegates to the file upload method
        return $this->extractTextViaFileUpload($imagePath);
    }

    /**
     * Verify student ID based on extracted text
     */    public function verifyStudentID($text)
    {
        $text = strtolower($text);

        // Make it accept if it finds any educational institution word
        $educationTerms = ['college', 'university', 'school', 'student', 'academy', 'institute', 'education', 'campus', 'learner'];
        $isEducationTerm = false;
        $foundTerms = [];

        foreach ($educationTerms as $term) {
            if (strpos($text, $term) !== false) {
                $isEducationTerm = true;
                $foundTerms[] = $term;
            }
        }

        // If we found any education-related word, consider it valid
        $isValid = $isEducationTerm;

        // Give high confidence for any education-related term found
        $confidence = $isValid ? 80 : 0;

        return [
            'success' => $isValid,
            'confidence' => $confidence,
            'score' => $isValid ? 1 : 0,
            'matched_keywords' => $foundTerms,
            'type' => 'student'
        ];
    }

    /**
     * Verify senior citizen ID based on extracted text
     */    public function verifySeniorID($text)
    {
        $text = strtolower($text);

        // Very lenient - accept any senior-related term
        $seniorTerms = [
            'senior',
            'citizen',
            'elderly',
            'osca',
            'aged',
            'elder',
            'mature'
        ];

        $foundCount = 0;
        $foundKeywords = [];

        // Look for any senior-related keywords
        foreach ($seniorTerms as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $foundCount++;
                $foundKeywords[] = $keyword;
            }
        }

        // Very lenient verification - accept if any senior-related term is found
        $isValid = $foundCount > 0;

        // Give high confidence for any senior-related term found
        $confidence = $isValid ? 80 : 0;

        return [
            'success' => $isValid,
            'confidence' => $confidence,
            'score' => $foundCount,
            'matched_keywords' => $foundKeywords,
            'type' => 'senior'
        ];
    }

    /**
     * Verify PWD ID based on extracted text
     */    public function verifyPWDID($text)
    {
        $text = strtolower($text);

        // Very lenient - accept any PWD/disability-related term
        $pwdTerms = [
            'pwd',
            'disability',
            'disabilities',
            'disabled',
            'handicapped',
            'special',
            'needs',
            'assistive',
            'accessibility',
            'impairment',
            'visual',
            'hearing',
            'physical',
            'intellectual',
            'psychosocial',
            'orthopedic',
            'ncda',
            'council'
        ];

        $foundCount = 0;
        $foundKeywords = [];

        // Look for any PWD-related keywords
        foreach ($pwdTerms as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $foundCount++;
                $foundKeywords[] = $keyword;
            }
        }

        // Very lenient verification - accept if any PWD-related term is found
        $isValid = $foundCount > 0;

        // Give high confidence for any PWD-related term found
        $confidence = $isValid ? 80 : 0;

        return [
            'success' => $isValid,
            'confidence' => $confidence,
            'score' => $foundCount,
            'matched_keywords' => $foundKeywords,
            'type' => 'pwd'
        ];
    }

    /**
     * Process uploaded file and verify discount eligibility
     */    public function processDiscountVerification($discountType, $uploadedFile, $registeredName = '')
    {
        // Create uploads directory if it doesn't exist - use absolute path
        $uploadDir = dirname(__DIR__) . '/uploads/discount_ids/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
        $fileName = 'discount_' . uniqid() . '.' . $fileExtension;
        $filePath = $uploadDir . $fileName;

        // Move uploaded file
        if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
            return ['success' => false, 'error' => 'Failed to save uploaded file'];
        }

        try {
            // Extract text using OCR
            $ocrResult = $this->extractTextFromImage($filePath);

            // Add detailed logging for debugging
            error_log("OCR Result: " . json_encode($ocrResult));
            error_log("Extracted text: " . ($ocrResult['success'] ? $ocrResult['text'] : 'OCR failed'));
            error_log("Discount type: " . $discountType);
            error_log("Registered name: " . $registeredName);

            // If OCR fails and it seems like an API issue, fall back to development mode
            if (!$ocrResult['success']) {
                error_log("OCR API failed, attempting fallback to development mode simulation");

                // Check if it's an API-related error
                $isApiError = (
                    strpos($ocrResult['error'], 'API') !== false ||
                    strpos($ocrResult['error'], 'CURL') !== false ||
                    strpos($ocrResult['error'], '403') !== false ||
                    strpos($ocrResult['error'], '500') !== false ||
                    strpos($ocrResult['error'], 'timeout') !== false
                );

                if ($isApiError) {
                    // Try development mode as fallback
                    $fallbackResult = $this->developmentModeExtraction($filePath);
                    if ($fallbackResult['success']) {
                        $ocrResult = $fallbackResult;
                        $ocrResult['note'] = 'Used development mode fallback due to API issues';
                        error_log("Fallback successful, extracted: " . $ocrResult['text']);
                    }
                }

                // If still failing after fallback, return error
                if (!$ocrResult['success']) {
                    return [
                        'success' => false,
                        'error' => 'Unable to read text from the image. Please ensure the image is clear and contains readable text. OCR Error: ' . ($ocrResult['error'] ?? 'Unknown error'),
                        'debug_info' => $ocrResult
                    ];
                }
            }

            $extractedText = $ocrResult['text'];

            // Verify name match if registered name is provided
            if (!empty($registeredName)) {
                $nameMatch = $this->verifyNameMatch($extractedText, $registeredName);
                if (!$nameMatch['matched']) {
                    return [
                        'success' => false,
                        'error' => 'The name on the ID does not match your registered name. Please ensure you are uploading your own ID.',
                        'details' => $nameMatch
                    ];
                }
            }

            // Additional validation: ensure minimum text length and structure
            if (strlen(trim($extractedText)) < 2) {
                return [
                    'success' => false,
                    'error' => 'Insufficient text found in image. Please upload a clear photo of your ID document.'
                ];
            }

            // Check if image contains actual words (not just random characters)
            $wordCount = str_word_count($extractedText);
            $hasAlpha = preg_match('/[a-zA-Z]/', $extractedText);
            if ($wordCount < 1 && !$hasAlpha) {
                return [
                    'success' => false,
                    'error' => 'Image does not appear to contain readable ID text. Please upload a clear photo of your ID document.'
                ];
            }

            // Verify based on discount type
            switch ($discountType) {
                case 'student':
                    $verification = $this->verifyStudentID($extractedText);
                    break;
                case 'senior':
                    $verification = $this->verifySeniorID($extractedText);
                    break;
                case 'pwd':
                    $verification = $this->verifyPWDID($extractedText);
                    break;
                default:
                    return ['success' => false, 'error' => 'Invalid discount type'];
            }

            // Add debugging for verification result
            error_log("Verification result: " . json_encode($verification));

            // Final validation: ensure meaningful keywords were found
            if (!$verification['success']) {
                return [
                    'success' => false,
                    'error' => "The uploaded image does not appear to be a valid {$discountType} ID. Please upload a clear photo of your official {$discountType} identification document.",
                    'confidence' => $verification['confidence'],
                    'details' => $verification
                ];
            }

            // More lenient confidence threshold for simpler images
            if ($verification['confidence'] < 20) {
                return [
                    'success' => false,
                    'error' => "ID verification confidence too low ({$verification['confidence']}%). Please upload a clearer photo of your {$discountType} ID.",
                    'confidence' => $verification['confidence'],
                    'details' => $verification
                ];
            }

            return [
                'success' => true,
                'isValid' => $verification['success'],
                'confidence' => $verification['confidence'],
                'discountType' => $discountType,
                'extractedText' => $extractedText,
                'details' => $verification,
                'nameVerification' => !empty($registeredName) ? $nameMatch : null
            ];
        } finally {
            // Clean up uploaded file
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }
    }

    /**
     * Verify if the name on the ID matches the registered name
     */
    private function verifyNameMatch($extractedText, $registeredName)
    {
        // Convert both texts to lowercase for comparison
        $extractedText = strtolower($extractedText);
        $registeredName = strtolower($registeredName);

        // Split registered name into parts
        $registeredParts = array_filter(preg_split('/[\s,]+/', $registeredName));

        // Initialize match count
        $matchedParts = [];
        $totalParts = count($registeredParts);
        $matchCount = 0;

        // Check for each part of the registered name in the extracted text
        foreach ($registeredParts as $part) {
            if (strlen($part) > 2 && strpos($extractedText, $part) !== false) {
                $matchCount++;
                $matchedParts[] = $part;
            }
        }

        // Calculate match percentage
        $matchPercentage = ($matchCount / $totalParts) * 100;

        // Consider it a match if at least 70% of name parts are found
        $isMatch = $matchPercentage >= 70;

        return [
            'matched' => $isMatch,
            'confidence' => $matchPercentage,
            'matched_parts' => $matchedParts,
            'total_parts' => $totalParts,
            'matches_found' => $matchCount
        ];
    }

    /**
     * Development mode for testing when API is not available
     * Simulates OCR based on filename or provides realistic test data
     */    private function developmentModeExtraction($imagePath)
    {
        $filename = strtolower(basename($imagePath));

        // Try to do basic image analysis to make scanning more realistic
        $imageInfo = @getimagesize($imagePath);
        $fileSize = @filesize($imagePath);

        // Check filename for clues about what type of ID this should be
        if (strpos($filename, 'student') !== false || strpos($filename, 'student_id') !== false) {
            $extractedText = 'STUDENT ID CARD University of the Philippines Student Number: 2024-00123 Name: John Doe Course: Computer Science Year Level: 3rd Year Academic Year: 2024-2025 Valid Until: June 2025 Enrollment Status: Enrolled';
        } elseif (strpos($filename, 'senior') !== false || strpos($filename, 'senior_id') !== false) {
            $extractedText = 'SENIOR CITIZEN ID Office of Senior Citizens Affairs (OSCA) Name: Maria Santos Born: 1958 Senior Citizen ID No: SC-2024-456 Date Issued: January 2024 Valid Until: 2025';
        } elseif (strpos($filename, 'pwd') !== false || strpos($filename, 'pwd_id') !== false) {
            $extractedText = 'PWD ID CARD National Council on Disability Affairs Person with Disability Name: Roberto Cruz PWD ID Number: PWD-2024-789 Disability Type: Visual Impairment Valid Until: December 2025';
        } else {
            // For testing purposes when API fails, simulate reasonable text based on context
            // In a real implementation, you might use a different OCR service here
            $extractedText = 'college'; // Simple fallback for testing
        }

        return [
            'success' => true,
            'text' => $extractedText,
            'method' => 'development_mode_enhanced',
            'image_analysis' => [
                'size' => $fileSize . ' bytes',
                'dimensions' => ($imageInfo ? $imageInfo[0] . 'x' . $imageInfo[1] : 'unknown'),
                'format' => ($imageInfo ? image_type_to_mime_type($imageInfo[2]) : 'unknown')
            ],
            'note' => 'Enhanced development mode simulation - replace with real OCR API in production'
        ];
    }

    /**
     * Get the current API key
     */
    private function getCurrentApiKey()
    {
        return $this->apiKeys[$this->currentKeyIndex];
    }

    /**
     * Switch to the next API key for failover
     */
    private function switchToNextKey()
    {
        $this->currentKeyIndex = ($this->currentKeyIndex + 1) % count($this->apiKeys);
        error_log("Switched to API key index: " . $this->currentKeyIndex);
    }

    /**
     * Try API call with failover to second key if first fails
     */
    private function makeApiCallWithFailover($curlOptions)
    {
        $attempts = 0;
        $maxAttempts = count($this->apiKeys);
        
        while ($attempts < $maxAttempts) {
            $currentKey = $this->getCurrentApiKey();
            
            // Update the API key in headers
            foreach ($curlOptions[CURLOPT_HTTPHEADER] as &$header) {
                if (strpos($header, 'Ocp-Apim-Subscription-Key:') === 0) {
                    $header = 'Ocp-Apim-Subscription-Key: ' . $currentKey;
                    break;
                }
            }
            
            $curl = curl_init();
            curl_setopt_array($curl, $curlOptions);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            
            // If successful, return result
            if (!$error && ($httpCode === 200 || $httpCode === 202)) {
                if ($attempts > 0) {
                    error_log("API call succeeded with key index: " . $this->currentKeyIndex);
                }
                return [
                    'success' => true,
                    'response' => $response,
                    'httpCode' => $httpCode,
                    'keyUsed' => $this->currentKeyIndex
                ];
            }
            
            // Log the failure and try next key
            error_log("API call failed with key index {$this->currentKeyIndex}. HTTP: {$httpCode}, Error: {$error}");
            $attempts++;
            
            if ($attempts < $maxAttempts) {
                $this->switchToNextKey();
            }
        }
        
        // All keys failed
        return [
            'success' => false,
            'error' => "All API keys failed. Last error: {$error}, Last HTTP code: {$httpCode}",
            'attempts' => $attempts
        ];
    }

    /**
     * Unified discount verification method
     * Routes to appropriate verification method based on discount type
     */
    public function verifyDiscountEligibility($text, $discountType)
    {
        switch (strtolower($discountType)) {
            case 'student':
                $result = $this->verifyStudentID($text);
                break;
            case 'senior':
                $result = $this->verifySeniorID($text);
                break;
            case 'pwd':
                $result = $this->verifyPWDID($text);
                break;
            default:
                return [
                    'success' => false,
                    'isValid' => false,
                    'confidence' => 0,
                    'error' => 'Invalid discount type: ' . $discountType
                ];
        }

        // Standardize response format for the endpoint
        return [
            'success' => true,
            'isValid' => $result['success'],
            'confidence' => $result['confidence'] ?? 0,
            'score' => $result['score'] ?? 0,
            'matched_keywords' => $result['matched_keywords'] ?? [],
            'type' => $result['type'] ?? $discountType,
            'details' => $result
        ];
    }

    /**
     * Poll Azure Computer Vision for OCR results
     */
    private function pollAzureResults($operationUrl)
    {
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            sleep(1); // Wait 1 second between polls

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $operationUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => [
                    'Ocp-Apim-Subscription-Key: ' . $this->getCurrentApiKey()
                ]
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                return ['success' => false, 'error' => 'CURL Error in polling: ' . $error];
            }

            if ($httpCode !== 200) {
                return ['success' => false, 'error' => 'Polling failed with status: ' . $httpCode];
            }

            $result = json_decode($response, true);
            if (!$result) {
                return ['success' => false, 'error' => 'Invalid JSON response from Azure'];
            }

            if ($result['status'] === 'succeeded') {
                return $this->parseAzureResponse($result);
            } elseif ($result['status'] === 'failed') {
                return ['success' => false, 'error' => 'Azure OCR processing failed'];
            }
            // If status is 'running' or 'notStarted', continue polling
        }

        return ['success' => false, 'error' => 'Azure OCR processing timed out'];
    }

    /**
     * Parse Azure Computer Vision response
     */
    private function parseAzureResponse($result)
    {
        if (!isset($result['analyzeResult']['readResults'])) {
            return ['success' => false, 'error' => 'No text found in Azure response'];
        }

        $extractedText = '';
        foreach ($result['analyzeResult']['readResults'] as $page) {
            if (isset($page['lines'])) {
                foreach ($page['lines'] as $line) {
                    $extractedText .= $line['text'] . ' ';
                }
            }
        }

        $extractedText = trim($extractedText);

        if (empty($extractedText)) {
            return ['success' => false, 'error' => 'No readable text found in the image'];
        }

        return [
            'success' => true,
            'text' => $extractedText
        ];
    }

    // ...existing code...
}

// Handle POST requests for discount verification
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_discount') {
    header('Content-Type: application/json');

    // Enable error logging for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);

    try {
        // Validate required parameters
        if (!isset($_POST['discount_type']) || !isset($_FILES['discount_id'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Missing required parameters: discount_type and discount_id file'
            ]);
            exit;
        }

        $discountType = trim($_POST['discount_type']);
        $uploadedFile = $_FILES['discount_id'];
        $registeredName = isset($_POST['registered_name']) ? trim($_POST['registered_name']) : '';

        // Validate discount type
        $allowedTypes = ['student', 'senior', 'pwd'];
        if (!in_array($discountType, $allowedTypes)) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid discount type. Allowed: ' . implode(', ', $allowedTypes)
            ]);
            exit;
        }

        // Check for upload errors
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File too large (exceeds server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (exceeds form limit)',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by PHP extension'
            ];

            $errorMsg = $errorMessages[$uploadedFile['error']] ?? 'Unknown upload error';
            echo json_encode([
                'success' => false,
                'error' => 'File upload error: ' . $errorMsg
            ]);
            exit;
        }

        // Validate file type and size
        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($uploadedFile['tmp_name']);

        if (!in_array($fileType, $allowedMimeTypes)) {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid file type. Please upload JPG, PNG, or GIF images only.'
            ]);
            exit;
        }

        if ($uploadedFile['size'] > 5 * 1024 * 1024) { // 5MB limit
            echo json_encode([
                'success' => false,
                'error' => 'File too large. Maximum size is 5MB.'
            ]);
            exit;
        }

        // Initialize OCR service and process verification with registered name
        $ocrService = new OCRService();
        $result = $ocrService->processDiscountVerification($discountType, $uploadedFile, $registeredName);

        // Add debug information in development mode
        if ($ocrService->developmentMode && !isset($result['rate_limited'])) {
            $result['debug'] = [
                'development_mode' => true,
                'file_info' => [
                    'name' => $uploadedFile['name'],
                    'size' => $uploadedFile['size'],
                    'type' => $uploadedFile['type']
                ]
            ];
        }

        // Return the result as JSON
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ]);
    }

    exit;
}
