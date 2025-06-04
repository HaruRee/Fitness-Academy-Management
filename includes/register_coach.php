<?php
session_start();
require_once '../config/database.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $birthdate = trim($_POST['birthdate']);
    $license_number = trim($_POST['license_number']);
    $experience = trim($_POST['experience']);
    $specialization = trim($_POST['specialization']);
    $why_coach = trim($_POST['why_coach']);
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($birthdate)) {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';
    } else {
        // Validate age (must be 18+)
        $birth_date = new DateTime($birthdate);
        $today = new DateTime();
        $age = $today->diff($birth_date)->y;
        
        if ($age < 18) {
            $message = 'You must be at least 18 years old to apply as a coach.';
            $messageType = 'error';
        } else {
        // Handle file upload
        $resume_path = '';
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
            $upload_dir = '../uploads/coach_resumes/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $file_name = 'resume_' . time() . '_' . uniqid() . '.' . $file_extension;
                $target_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_path)) {
                    $resume_path = $file_name;
                } else {
                    $message = 'Failed to upload resume. Please try again.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Invalid file format. Please upload PDF, DOC, DOCX, JPG, JPEG, or PNG files only.';
                $messageType = 'error';
            }
        }
        
        if (empty($message)) {
            try {                // Insert coach application into database
                $stmt = $conn->prepare("
                    INSERT INTO coach_applications (name, email, phone, address, birthdate, license_number, experience, specialization, why_coach, resume_path, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                $stmt->execute([$name, $email, $phone, $address, $birthdate, $license_number, $experience, $specialization, $why_coach, $resume_path]);
                
                $message = 'Your coach application has been submitted successfully! We will review your application and get back to you soon.';
                $messageType = 'success';
                
                // Clear form data
                $_POST = array();
                
            } catch (PDOException $e) {
                $message = 'An error occurred while submitting your application. Please try again.';
                $messageType = 'error';
                error_log("Coach registration error: " . $e->getMessage());            }
        }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become a Coach - Fitness Academy</title>
    <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 1000;
        }

        .navbar .logo {
            display: flex;
            align-items: center;
        }

        .navbar .logo img {
            height: 40px;
            margin-right: 10px;
        }

        .navbar .logo-text {
            font-weight: 700;
            font-size: 1.4rem;
            color: #e41e26;
            text-decoration: none;
        }

        .nav-links a {
            margin-left: 20px;
            color: #333;
            text-decoration: none;
            font-weight: 600;
        }

        .nav-links a:hover {
            color: #e41e26;
        }        .main-content {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 73px);
            padding: 15px;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 700px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header {
            background: linear-gradient(135deg, #e41e26, #c81a21);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .header p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .form-container {
            padding: 2rem;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }

        .form-group label i {
            margin-right: 0.5rem;
            color: #e41e26;
            width: 16px;
        }

        .required {
            color: #e41e26;
            margin-left: 3px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #e41e26;
            box-shadow: 0 0 0 3px rgba(228, 30, 38, 0.1);
        }        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-help {
            display: block;
            margin-top: 5px;
            font-size: 0.85rem;
            color: #6c757d;
            font-style: italic;
        }

        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0.8rem;
            border: 2px dashed rgba(228, 30, 38, 0.3);
            border-radius: 8px;
            background: rgba(228, 30, 38, 0.03);
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .file-upload-label:hover {
            border-color: #e41e26;
            background: #fff5f5;
        }        .file-upload-label i {
            font-size: 1.5rem;
            color: #e41e26;
            margin-bottom: 8px;
        }

        .file-info {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #666;        }        .submit-btn {
            background: linear-gradient(135deg, #e41e26, #c81a21);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(228, 30, 38, 0.4);
            background: linear-gradient(135deg, #f03a3f, #e41e26);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: #e41e26;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: color 0.3s ease;
        }

        .back-link:hover {
            color: #c81a21;
        }

        .back-link i {
            margin-right: 8px;
        }        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .header {
                padding: 1.5rem 1rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .form-container {
                padding: 1.5rem 1rem;
            }

            .main-content {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="logo">
            <a href="index.php"><img src="../assets/images/fa_logo.png" alt="Fitness Academy"></a>
            <a href="index.php" class="logo-text">Fitness Academy</a>
        </div>
        <div class="nav-links">
            <a href="login.php">Sign In</a>
            <a href="register.php">Join now</a>
        </div>
    </nav>

    <div class="main-content">
        <div class="container">
        <div class="header">
            <h1><i class="fas fa-dumbbell"></i> Become a Coach</h1>
            <p>Join our team of fitness professionals at Fitness Academy</p>
        </div>

        <div class="form-container">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Home
            </a>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">
                            <i class="fas fa-user"></i>
                            Full Name <span class="required">*</span>
                        </label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            Email Address <span class="required">*</span>
                        </label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone">
                            <i class="fas fa-phone"></i>
                            Phone Number <span class="required">*</span>
                        </label>
                        <input type="tel" id="phone" name="phone" required 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="experience">
                            <i class="fas fa-clock"></i>
                            Years of Experience
                        </label>
                        <select id="experience" name="experience">
                            <option value="">Select experience level</option>
                            <option value="0-1" <?php echo (isset($_POST['experience']) && $_POST['experience'] == '0-1') ? 'selected' : ''; ?>>0-1 years</option>
                            <option value="1-3" <?php echo (isset($_POST['experience']) && $_POST['experience'] == '1-3') ? 'selected' : ''; ?>>1-3 years</option>
                            <option value="3-5" <?php echo (isset($_POST['experience']) && $_POST['experience'] == '3-5') ? 'selected' : ''; ?>>3-5 years</option>
                            <option value="5-10" <?php echo (isset($_POST['experience']) && $_POST['experience'] == '5-10') ? 'selected' : ''; ?>>5-10 years</option>
                            <option value="10+" <?php echo (isset($_POST['experience']) && $_POST['experience'] == '10+') ? 'selected' : ''; ?>>10+ years</option>
                        </select>
                    </div>                    <div class="form-group full-width">
                        <label for="address">
                            <i class="fas fa-map-marker-alt"></i>
                            Address <span class="required">*</span>
                        </label>
                        <input type="text" id="address" name="address" required 
                               value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="birthdate">
                            <i class="fas fa-calendar-alt"></i>
                            Date of Birth <span class="required">*</span>
                        </label>
                        <input type="date" id="birthdate" name="birthdate" required 
                               max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
                               value="<?php echo isset($_POST['birthdate']) ? htmlspecialchars($_POST['birthdate']) : ''; ?>">
                        <small class="form-help">You must be at least 18 years old to apply</small>
                    </div>

                    <div class="form-group">
                        <label for="license_number">
                            <i class="fas fa-certificate"></i>
                            Coach License Number
                        </label>
                        <input type="text" id="license_number" name="license_number" 
                               placeholder="Optional - Enter your coaching license number"
                               value="<?php echo isset($_POST['license_number']) ? htmlspecialchars($_POST['license_number']) : ''; ?>">
                        <small class="form-help">If you have a professional coaching license or certification</small>
                    </div>

                    <div class="form-group full-width">
                        <label for="specialization">
                            <i class="fas fa-star"></i>
                            Specialization/Expertise
                        </label>
                        <input type="text" id="specialization" name="specialization" 
                               placeholder="e.g., Weight Training, Cardio, Yoga, CrossFit, etc."
                               value="<?php echo isset($_POST['specialization']) ? htmlspecialchars($_POST['specialization']) : ''; ?>">
                    </div>

                    <div class="form-group full-width">
                        <label for="why_coach">
                            <i class="fas fa-heart"></i>
                            Why do you want to be a coach at Fitness Academy?
                        </label>
                        <textarea id="why_coach" name="why_coach" 
                                  placeholder="Tell us about your passion for fitness and why you'd like to join our team..."><?php echo isset($_POST['why_coach']) ? htmlspecialchars($_POST['why_coach']) : ''; ?></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="resume">
                            <i class="fas fa-file-upload"></i>
                            Upload Resume/CV
                        </label>
                        <div class="file-upload">
                            <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <div class="file-upload-label">
                                <div>
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Click to upload</strong> or drag and drop</p>
                                    <p class="file-info">PDF, DOC, DOCX, JPG, JPEG, PNG (Max 5MB)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                    Submit Application
                </button>            </form>
        </div>
    </div>
    </div>

    <script>
        // File upload preview
        document.getElementById('resume').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const label = document.querySelector('.file-upload-label');
            
            if (file) {
                label.innerHTML = `
                    <div>
                        <i class="fas fa-file-check" style="color: #28a745;"></i>
                        <p><strong>File selected:</strong> ${file.name}</p>
                        <p class="file-info">Size: ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                    </div>
                `;
            }
        });        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = ['name', 'email', 'phone', 'address', 'birthdate'];
            let isValid = true;

            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#e41e26';
                } else {
                    input.style.borderColor = '#e1e5e9';
                }
            });

            // Validate age (18+)
            const birthdateInput = document.getElementById('birthdate');
            if (birthdateInput.value) {
                const birthDate = new Date(birthdateInput.value);
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                if (age < 18) {
                    isValid = false;
                    birthdateInput.style.borderColor = '#e41e26';
                    alert('You must be at least 18 years old to apply as a coach.');
                    e.preventDefault();
                    return;
                }
            }

            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });

        // Real-time age validation
        document.getElementById('birthdate').addEventListener('change', function() {
            const birthDate = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            if (age < 18) {
                this.style.borderColor = '#e41e26';
                alert('You must be at least 18 years old to apply as a coach.');
                this.value = '';
            } else {
                this.style.borderColor = '#28a745';
            }
        });
    </script>
</body>
</html>
