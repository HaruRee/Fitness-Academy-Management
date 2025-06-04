<?php
session_start();
require_once '../config/database.php';

// Helper function to properly handle thumbnail paths
function fixThumbnailPath($path) {
    if (empty($path)) return false;
    
    // If the path already starts with '../' remove it to avoid double path issues
    if (strpos($path, '../') === 0) {
        return substr($path, 3);
    }
    return $path;
}

// Get all approved free videos with coach info
$stmt = $conn->prepare("
    SELECT cv.*, u.First_Name, u.Last_Name
    FROM coach_videos cv
    JOIN users u ON cv.coach_id = u.UserID
    WHERE cv.status = 'approved' AND cv.access_type = 'free'
    ORDER BY cv.created_at DESC
");
$stmt->execute();
$free_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Redirect logged-in users to their respective pages 
if (isset($_SESSION['user_id'])) {
  switch ($_SESSION['role']) {
    case 'Admin':
      header('Location: admin_dashboard.php');
      exit;
    case 'Coach':
      header('Location: coach_dashboard.php');
      exit;
    case 'Staff':
      header('Location: staff_dashboard.php');
      exit;
    case 'Member':
      header('Location: member_dashboard.php');
      exit;
    case 'Guest':
      // Guests can view the index page even if logged in
      break;
    default:
      // Default to member dashboard for any other role
      header('Location: member_dashboard.php');
      exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">  <title>Fitness Academy</title>
  <link rel="icon" type="image/png" href="../assets/images/fa_logo.png">
  <link href="https://fonts.googleapis.com/css?family=Montserrat:400,500,600,700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
     integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
     crossorigin=""/>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    /* Basic Reset */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Montserrat', 'Inter', sans-serif;
      background: #ffffff;
      color: #333;
      min-height: 100vh;
    }

    /* Font Variations - Using related font family */
    h1, h2, h3, .logo-text {
      font-family: 'Montserrat', sans-serif;
      font-weight: 700;
    }

    p, a, li, .tagline {
      font-family: 'Inter', 'Montserrat', sans-serif;
    }

    .feature-title, .section-title, .cta-banner-title {
      font-family: 'Montserrat', sans-serif;
      font-weight: 600;
    }

    .auth-btn, .cta, .feature-link {
      font-family: 'Inter', 'Montserrat', sans-serif;
      font-weight: 500;
    }

    /* Gallery Style Layout */
    .gallery-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      grid-gap: 30px;
      margin: 40px auto;
      max-width: 1200px;
    }

    .gallery-item {
      position: relative;
      overflow: hidden;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      height: 350px;
    }

    .gallery-item:hover {
      transform: translateY(-10px);
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    }

    .gallery-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }

    .gallery-item:hover .gallery-image {
      transform: scale(1.05);
    }

    .gallery-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0, 0, 0, 0.7);
      color: #fff;
      padding: 20px;
      transform: translateY(100%);
      transition: transform 0.3s ease;
    }

    .gallery-item:hover .gallery-overlay {
      transform: translateY(0);
    }

    .gallery-title {
      font-size: 1.5rem;
      margin-bottom: 10px;
    }

    .gallery-description {
      font-size: 0.9rem;
      line-height: 1.4;
    }

    /* Additional responsive styles for gallery */
    @media (max-width: 768px) {
      .gallery-container {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        grid-gap: 15px;
      }
      
      .gallery-item {
        height: 280px;
      }
      
      .gallery-overlay {
        padding: 15px;
        background: rgba(0, 0, 0, 0.75); /* Slightly darker for better readability on mobile */
      }
      
      .gallery-title {
        font-size: 1.3rem;
      }
      
      .gallery-description {
        font-size: 0.85rem;
      }
      
      /* Make overlay always visible on mobile for better UX */
      .gallery-item .gallery-overlay {
        transform: translateY(0);
        height: auto;
        max-height: 50%;
        overflow-y: auto;
      }
    }
    
    /* Enhanced Social Media Links */
    .social-links {
      display: flex;
      gap: 15px;
      margin-top: 15px;
    }

    .social-links a {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      background: #e41e26;
      color: white;
      border-radius: 50%;
      text-decoration: none;
      transition: all 0.3s ease;
    }

    .social-links a:hover {
      transform: translateY(-5px);
      background: #c81a21;
    }

    /* Modern Navigation Bar */
    .navbar {
      position: fixed;
      top: 0;
      width: 100%;
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #fff;
      z-index: 10;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      transition: transform 0.4s ease;
      /* Add smooth transition */
    }

    /* New class for hiding navbar */
    .navbar-hidden {
      transform: translateY(-100%);
    }

    .navbar .logo {
      display: flex;
      align-items: center;
    }

    .navbar .logo img {
      max-height: 40px;
      width: auto;
      margin-right: 10px;
    }

    .navbar .logo-text {
      font-weight: 700;
      font-size: 1.4rem;
      color: #e41e26;
      text-decoration: none;
    }

    .navbar .nav-links {
      display: flex;
      align-items: center;
    }

    .main-menu {
      display: flex;
      list-style: none;
      margin-right: 20px;
    }

    .main-menu li {
      margin: 0 15px;
    }

    .main-menu li a {
      color: #333;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.95rem;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      padding: 8px 0;
      position: relative;
      transition: all 0.3s ease;
    }

    .main-menu li a::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 0;
      height: 2px;
      background-color: #e41e26;
      transition: width 0.3s ease;
    }

    .main-menu li a:hover {
      color: #e41e26;
    }

    .main-menu li a:hover::after {
      width: 100%;
    }

    .auth-buttons {
      display: flex;
    }

    .auth-btn {
      padding: 10px 20px;
      margin-left: 10px;
      border-radius: 4px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      font-size: 0.9rem;
      text-transform: uppercase;
    }

    .login-btn {
      background: transparent;
      color: #333;
      border: 1px solid #e41e26;
    }

    .login-btn:hover {
      background: rgba(228, 30, 38, 0.1);
      color: #e41e26;
    }

    .signup-btn {
      background: #e41e26;
      color: #fff;
      border: 1px solid #e41e26;
    }

    .signup-btn:hover {
      background: #c81a21;
      border-color: #c81a21;
    }

    .mobile-toggle {
      display: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: #333;
    }

    /* Hero Section - Enhanced to match reference */
    .hero {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      margin-top: 0;
      /* Removed margin-top to make it flush with navbar */
      padding: 0;
      background: url('../assets/images/landing.jpeg') no-repeat center center;
      background-size: cover;
      position: relative;
      min-height: 100vh;
      /* Full viewport height */
      width: 100%;
    }    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4); /* Reduced opacity by 10% for a wash out effect */
      z-index: 1;
    }

    .hero-content {
      position: relative;
      z-index: 2;
      color: #fff;
      max-width: 1200px;
      width: 100%;
      padding: 120px 20px 60px;
      /* Added top padding to account for navbar */
    }

    .hero h1 {
      font-size: 4rem;
      /* Larger heading */
      font-weight: 700;
      margin-bottom: 1.5rem;
      text-shadow: 2px 2px 5px rgba(0, 0, 0, 0.7);
      text-transform: uppercase;
      /* Uppercase like in reference */
      letter-spacing: 1px;
    }

    .hero .tagline {
      font-size: 1.8rem;
      /* Larger tagline */
      margin-bottom: 2.5rem;
      line-height: 1.4;
      max-width: 800px;
      margin-left: auto;
      margin-right: auto;
      color: #f0f0f0;
    }

    .cta-buttons {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      margin-top: 3rem;
      /* More space above buttons */
      gap: 20px;
      /* Increased gap between buttons */
    }

    .cta {
      padding: 15px 40px;
      /* Wider buttons */
      background: #e41e26;
      color: #fff;
      text-decoration: none;
      border-radius: 5px;
      font-size: 1.2rem;
      /* Slightly larger text */
      font-weight: 600;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .cta:hover {
      background: #c81a21;
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    .cta.secondary {
      background: transparent;
      border: 2px solid #fff;
    }

    .cta.secondary:hover {
      background: rgba(255, 255, 255, 0.1);
      transform: translateY(-3px);
    }

    /* Modern Card-based Features Section */
    .features {
      padding: 80px 20px;
      background: #fff;
    }

    .features-container {
      display: flex;
      flex-wrap: wrap;
      gap: 30px;
      justify-content: center;
      max-width: 1200px;
      margin: 0 auto;
    }

    .feature-card {
      flex: 1;
      min-width: 300px;
      max-width: 380px;
      background: #fff;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }

    .feature-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    }

    .feature-image {
      width: 100%;
      height: 220px;
      object-fit: cover;
    }

    .feature-content {
      padding: 25px 20px;
    }

    .feature-title {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 15px;
      color: #333;
    }

    .feature-text {
      font-size: 1rem;
      line-height: 1.6;
      color: #666;
      margin-bottom: 20px;
    }

    .feature-link {
      display: inline-block;
      font-size: 0.9rem;
      font-weight: 600;
      color: #e41e26;
      text-transform: uppercase;
      text-decoration: none;
      letter-spacing: 1px;
      transition: all 0.3s ease;
    }

    .feature-link:hover {
      color: #c81a21;
      padding-left: 5px;
    }

    /* Section styles */
    .section {
      padding: 80px 20px;
      text-align: center;
      background: #f8f8f8;
    }

    .section:nth-child(even) {
      background: #fff;
    }

    .section-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .section-header {
      margin-bottom: 50px;
    }

    .section-title {
      font-size: 2.5rem;
      font-weight: 700;
      color: #333;
      margin-bottom: 15px;
      position: relative;
      display: inline-block;
    }

    .section-title::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 60px;
      height: 3px;
      background: #e41e26;
    }

    .section-subtitle {
      font-size: 1.2rem;
      color: #666;
      max-width: 700px;
      margin: 0 auto;
    }

    .section-content {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 30px;
    }

    .info-card {
      background: #fff;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      max-width: 500px;
      text-align: left;
    }

    .section-image {
      max-width: 100%;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    /* CTA Banner */
    .cta-banner {
      background: #333;
      color: #fff;
      padding: 60px 20px;
      text-align: center;
    }

    .cta-banner-content {
      max-width: 800px;
      margin: 0 auto;
    }

    .cta-banner-title {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 20px;
    }

    .cta-banner-text {
      font-size: 1.2rem;
      margin-bottom: 30px;
      line-height: 1.6;
    }

    /* Footer */
    .footer {
      background: #222;
      color: #fff;
      padding: 60px 20px 30px;
    }
    
    .footer-content {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .footer a {
      color: #fff;
      text-decoration: none;
      transition: color 0.3s ease;
    }
    
    .footer a:hover {
      color: #e41e26;
    }
    
    /* Text center class */
    .text-center {
      text-align: center;
    }
    
    .mt-5 {
      margin-top: 3rem;
    }
    
    /* Better spacing between sections */
    .section {
      padding: 80px 20px;
      margin-bottom: 0;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
      .navbar {
        padding: 0.8rem 1rem;
      }

      .hero h1 {
        font-size: 2.8rem;
      }

      .hero .tagline {
        font-size: 1.3rem;
      }

      .section-title {
        font-size: 2.2rem;
      }

      .feature-card {
        min-width: 280px;
      }
    }

    @media (max-width: 768px) {
      .mobile-toggle {
        display: block;
      }

      .main-menu {
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        flex-direction: column;
        background: #fff;
        text-align: center;
        transform: translateY(-10px);
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        padding-bottom: 15px;
        /* Add padding to accommodate auth buttons */
      }

      .main-menu.active {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
      }

      .main-menu li {
        margin: 15px 0;
      }

      .hero h1 {
        font-size: 2.2rem;
      }

      .hero .tagline {
        font-size: 1.1rem;
      }

      .section-title {
        font-size: 1.8rem;
      }

      .feature-card {
        min-width: 100%;
      }

      .auth-buttons {
        flex-direction: column;
      }

      .auth-btn {
        margin: 5px 0;
      }

      .footer-section {
        flex: 100%;
        text-align: center;
      }

      .footer-section h3:after {
        left: 50%;
        transform: translateX(-50%);
      }

      .social-links {
        justify-content: center;
      }

      /* Increase touch target sizes */
      .auth-btn,
      .cta,
      .feature-link,
      .main-menu li a {
        padding: 12px 20px;
        min-height: 44px;
        /* Minimum Apple recommended touch target size */
      }

      /* Better spacing for mobile */
      .section {
        padding: 60px 15px;
      }

      .features {
        padding: 60px 15px;
      }

      /* Fix overflow issues */
      .feature-card {
        margin-bottom: 20px;
      }

      /* Improve form elements for touch */
      input,
      select,
      textarea,
      button {
        font-size: 16px;
        /* Prevents iOS zoom on focus */
        min-height: 44px;
      }

      /* Mobile menu overlay */
      body.menu-open::after {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9;
      }

      /* Ensure mobile menu is above overlay */
      .main-menu.active {
        z-index: 10;
      }

      /* Better hero section on mobile */
      .hero-content {
        padding: 100px 15px 40px;
      }
    }

    /* Animation Classes */
    .fade-in-up {
      opacity: 0;
      transform: translateY(40px);
      transition: opacity 0.6s ease-out, transform 0.6s ease-out;
    }

    .fade-in-left {
      opacity: 0;
      transform: translateX(-40px);
      transition: opacity 0.6s ease-out, transform 0.6s ease-out;
    }

    .fade-in-right {
      opacity: 0;
      transform: translateX(40px);
      transition: opacity 0.6s ease-out, transform 0.6s ease-out;
    }

    .fade-in {
      opacity: 0;
      transition: opacity 0.6s ease-out;
    }

    /* Classes applied when element is visible */
    .visible {
      opacity: 1;
      transform: translate(0, 0);
    }

    /* Add a small delay between elements */
    .delay-100 {
      transition-delay: 0.1s;
    }

    .delay-200 {
      transition-delay: 0.2s;
    }

    .delay-300 {
      transition-delay: 0.3s;
    }

    /* Get Free Trial Button */
    .get-trial-btn {
      position: fixed;
      right: 0;
      top: 50%;
      transform: translateY(-50%) rotate(-90deg);
      transform-origin: right center;
      background: #e41e26;
      color: #fff;
      padding: 10px 20px;
      border-radius: 5px 5px 0 0;
      font-weight: 600;
      text-transform: uppercase;
      text-decoration: none;
      letter-spacing: 1px;
      box-shadow: -2px -2px 5px rgba(0, 0, 0, 0.1);
      z-index: 99;
      font-size: 0.9rem;
      transition: all 0.3s ease;
    }

    .get-trial-btn:hover {
      background: #c81a21;
      padding-right: 30px;
    }

    /* Mobile button improvements */
    @media (max-width: 768px) {

      /* Fix auth buttons in navbar */
      .auth-buttons {
        flex-direction: column;
        align-items: stretch;
        width: 100%;
        margin-top: 10px;
      }

      .auth-btn {
        margin: 5px 0;
        text-align: center;
        font-size: 0.85rem;
        /* Slightly smaller font */
        padding: 12px 10px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }

      /* Adjust signup button text for mobile */
      .auth-btn.signup-btn span.mobile-hidden {
        display: none;
      }

      /* Fix CTA buttons */
      .cta-buttons {
        flex-direction: column;
        width: 100%;
        max-width: 280px;
        margin-left: auto;
        margin-right: auto;
      }

      .cta {
        width: 100%;
        margin: 5px 0;
        font-size: 1rem;
        padding: 12px 15px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-align: center;
      }

      /* Fix CTA banner button */
      .cta-banner .cta {
        max-width: 200px;
        margin-left: auto;
        margin-right: auto;
        font-size: 0.9rem;
      }

      /* Add smaller text variants for mobile */
      .mobile-short-text {
        display: inline;
      }

      .desktop-text {
        display: none;
      }
    }

    /* Desktop text display */
    @media (min-width: 769px) {
      .mobile-short-text {
        display: none;
      }

      .desktop-text {
        display: inline;
      }
    }

    /* Mobile menu auth buttons */
    .mobile-menu-auth {
      display: none;
    }

    /* Desktop only auth buttons */
    .desktop-only-auth {
      display: flex;
    }

    @media (max-width: 768px) {
      .mobile-menu-auth {
        display: block;
        margin: 10px 0;
      }

      .mobile-menu-auth .auth-btn {
        display: inline-block;
        width: 80%;
        margin: 5px auto;
        text-align: center;
      }

      .desktop-only-auth {
        display: none;
      }

      /* Style adjustments for auth buttons in mobile menu */
      .main-menu .auth-btn {
        padding: 10px 20px;
        margin: 8px auto;
        border-radius: 4px;
        font-size: 0.9rem;
        text-transform: uppercase;
      }

      /* Other mobile styles... */
      .mobile-toggle {
        display: block;
      }

      .main-menu {
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        flex-direction: column;
        background: #fff;
        text-align: center;
        transform: translateY(-10px);
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        padding-bottom: 15px;
        /* Add padding to accommodate auth buttons */
      }

      /* Rest of your mobile styles... */
    }

    /* Membership Plans Section - Gallery Style */
    #membership .gallery-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      grid-gap: 30px;
      margin: 40px auto;
      max-width: 1200px;
    }

    #membership .gallery-item {
      position: relative;
      overflow: hidden;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      height: 350px;
    }

    #membership .gallery-item:hover {
      transform: translateY(-10px);
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    }

    #membership .gallery-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }

    #membership .gallery-item:hover .gallery-image {
      transform: scale(1.05);
    }

    #membership .gallery-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0, 0, 0, 0.7);
      color: #fff;
      padding: 20px;
      transform: translateY(100%);
      transition: transform 0.3s ease;
    }

    #membership .gallery-item:hover .gallery-overlay {
      transform: translateY(0);
    }

    #membership .gallery-title {
      font-size: 1.5rem;
      margin-bottom: 10px;
    }

    #membership .gallery-description {
      font-size: 0.9rem;
      line-height: 1.4;
    }

    /* Add margin to the Join Now button in Membership Plans section */
    #membership .text-center {
      margin-top: 30px;
    }

    /* Adjust button styles in Membership Plans section */
    #membership .cta {
      padding: 12px 30px;
      font-size: 1rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      transition: all 0.3s ease;
      display: inline-block;
      margin: 0 auto;
    }

    #membership .cta:hover {
      background: #c81a21;
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    /* Ensure consistent padding in section */
    #membership .section-container {
      padding: 0 20px;
    }

    /* Responsive adjustments for Membership Plans section */
    @media (max-width: 768px) {
      #membership .gallery-item {
        height: 250px;
      }      #membership .cta {
        padding: 10px 20px;
        font-size: 0.9rem;
      }
    }

    /* Location Section with Map Styles */
    .location-content {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      align-items: center;
      margin-top: 40px;
    }

    .location-info {
      padding: 20px;
    }

    .location-info h3 {
      color: #e41e26;
      font-size: 1.8rem;
      margin-bottom: 20px;
      font-family: 'Montserrat', sans-serif;
    }

    .location-details {
      margin-bottom: 30px;
    }

    .location-item {
      display: flex;
      align-items: center;
      margin-bottom: 15px;
      padding: 10px;
      background: #f8f9fa;
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    .location-item:hover {
      background: #e8f4fd;
      transform: translateX(5px);
    }

    .location-item i {
      color: #e41e26;
      font-size: 1.2rem;
      margin-right: 15px;
      width: 20px;
      text-align: center;
    }

    .location-item span {
      font-weight: 500;
      color: #333;
    }

    .map-container {
      position: relative;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      height: 400px;
    }

    #map {
      height: 100%;
      width: 100%;
    }

    .directions-btn {
      display: inline-flex;
      align-items: center;
      background: #e41e26;
      color: white;
      padding: 12px 25px;
      text-decoration: none;
      border-radius: 25px;
      font-weight: 600;
      transition: all 0.3s ease;
      margin-top: 20px;
    }

    .directions-btn:hover {
      background: #c81a21;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(228, 30, 38, 0.3);
    }

    .directions-btn i {
      margin-right: 8px;
    }

    /* Responsive design for location section */
    @media (max-width: 768px) {
      .location-content {
        grid-template-columns: 1fr;
        gap: 30px;
      }

      .map-container {
        height: 300px;
      }      .location-info h3 {
        font-size: 1.5rem;
      }
    }    /* Compact About Us Section Styles */
    .about-content-compact {
      max-width: 1200px;
      margin: 0 auto;
    }

    .about-main-compact {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 40px;
      align-items: center;
      margin-bottom: 40px;
    }

    .about-text-compact p {
      font-size: 1.1rem;
      line-height: 1.6;
      color: #555;
      margin-bottom: 20px;
      text-align: justify;
    }

    .main-about-image-compact {
      width: 100%;
      height: 300px;
      object-fit: cover;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .about-features-compact {
      margin-top: 30px;
    }

    .feature-grid-compact {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 20px;
    }

    .feature-item-compact {
      text-align: center;
      padding: 20px 15px;
      background: #f8f9fa;
      border-radius: 10px;
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .feature-item-compact:hover {
      transform: translateY(-3px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      background: #fff;
    }

    .feature-item-compact i {
      color: #e41e26;
      font-size: 1.5rem;
      margin-bottom: 10px;
    }

    .feature-item-compact span {
      color: #333;
      font-weight: 600;
      font-size: 0.9rem;
    }

    /* Responsive design for compact about section */
    @media (max-width: 768px) {
      .about-main-compact {
        grid-template-columns: 1fr;
        gap: 25px;
      }

      .about-text-compact p {
        font-size: 1rem;
      }

      .main-about-image-compact {
        height: 250px;
      }

      .feature-grid-compact {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
      }

      .feature-item-compact {
        padding: 15px 10px;
      }
    }

    /* Free Courses Section */
    .video-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }

    .video-card {
      background: #fff;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
    }

    .video-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
    }

    .video-thumbnail {
      position: relative;
      padding-top: 56.25%; /* 16:9 Aspect Ratio */
      overflow: hidden;
    }

    .video-thumbnail img {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.3s ease;
    }

    .video-card:hover .video-thumbnail img {
      transform: scale(1.1);
    }

    .video-info {
      padding: 15px;
    }

    .video-title {
      font-size: 1.2rem;
      font-weight: 600;
      margin-bottom: 10px;
      color: #333;
    }

    .coach-name {
      font-size: 0.9rem;
      color: #666;
      margin-bottom: 10px;
      display: flex;
      align-items: center;
    }

    .coach-name i {
      margin-right: 5px;
      color: #e41e26;
    }

    .video-description {
      font-size: 0.9rem;
      color: #555;
      margin-bottom: 10px;
      height: 40px; /* Fixed height for uniformity */
      overflow: hidden;
      display: -webkit-box;
      -webkit-box-orient: vertical;
      -webkit-line-clamp: 2; /* Limit to 2 lines */
    }

    .access-badge {
      display: inline-block;
      font-size: 0.8rem;
      font-weight: 500;
      padding: 5px 10px;
      border-radius: 15px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .access-free {
      background: #e1f5fe;
      color: #01579b;
    }

    /* Empty state for no videos */
    .empty-state {
      text-align: center;
      padding: 50px 20px;
      background: #f9f9f9;
      border-radius: 10px;
      margin-top: 20px;
    }

    .empty-state i {
      font-size: 3rem;
      color: #ccc;
      margin-bottom: 15px;
    }

    .empty-state h3 {
      font-size: 1.5rem;
      color: #333;
      margin-bottom: 10px;
    }

    .empty-state p {
      font-size: 1rem;
      color: #666;
    }

    /* Responsive adjustments for video section */
    @media (max-width: 768px) {
      .video-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      }

      .video-title {
        font-size: 1rem;
      }

      .coach-name {
        font-size: 0.8rem;
      }

      .video-description {
        font-size: 0.8rem;
      }

      .access-badge {
        font-size: 0.7rem;
        padding: 4px 8px;
      }
    }

    /* Video Modal Styles */
.video-modal {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
}

.video-modal-content {
  background: #fff;
  border-radius: 12px;
  padding: 0;
  max-width: 90vw;
  width: 420px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18);
  position: relative;
  overflow: hidden;
}

.video-modal-close {
  position: absolute;
  top: 8px;
  right: 15px;
  font-size: 2rem;
  color: #222;
  cursor: pointer;
  z-index: 10;
}

#video-modal-body {
  padding: 28px 25px 25px 25px;
}

@media (max-width: 500px) {
  .video-modal-content {
    width: 98vw;
    max-width: 98vw;
    padding: 0;
  }

  #video-modal-body {
    padding: 12px 2vw;
  }
}

.cta.download-btn {
  background: #e41e26;
  color: #fff;
  margin-top: 8px;
  display: inline-block;
  border: none;
  transition: all 0.3s ease;
}
.cta.download-btn:hover {
  background:rgb(169, 25, 30);
  color: #fff;
  transform: translateY(-3px);
  box-shadow: 0 10px 20px rgba(1, 87, 155, 0.15);
}

html {
  scroll-behavior: smooth;
}
  </style>
</head>

<body>
  <!-- Modern Navigation Bar -->
  <nav class="navbar">
    <div class="logo">
      <a href="index.php"><img src="../assets/images/fa_logo.png" alt="Fitness Academy"></a>
      <a href="index.php" class="logo-text">Fitness Academy</a>
    </div>

    <div class="mobile-toggle" id="mobile-toggle">
      <i class="fas fa-bars"></i>
    </div>

    <div class="nav-links">      <ul class="main-menu" id="main-menu">
        <li><a href="#about">About</a></li>        
        <li><a href="#free-courses">Online Courses</a></li>
        <li><a href="#location">Locations</a></li>
        <li><a href="register_coach.php">Become a Coach</a></li>
        <!-- Auth buttons for mobile menu -->
        <li class="mobile-menu-auth">
          <a href="login.php" class="auth-btn login-btn">Login</a>
        </li>
        <li class="mobile-menu-auth">
          <a href="register.php" class="auth-btn signup-btn">
            <span class="mobile-short-text">Join Now</span>
            <span class="desktop-text">Register</span>
          </a>
        </li>
      </ul>      <div class="auth-buttons desktop-only-auth">
        <a href="login.php" class="auth-btn login-btn">Login</a>
        <a href="register.php" class="auth-btn signup-btn">
          <span class="mobile-short-text">Join Now</span>
          <span class="desktop-text">Register</span>
        </a>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-content">
      <h1>Welcome ka rito Ka-TroFA</h1>
      <p class="tagline">Start your fitness journey with us and transform your life today</p>

      <div class="cta-buttons">
        <a href="register.php" class="cta">Join Now</a>
        <a href="register_coach.php" class="cta secondary">Become a Coach</a>
      </div>
    </div>
  </section>

  <!-- Modern Card-based Features Section -->
  <section class="features">
    <div class="features-container">
      <div class="feature-card">
        <img src="../assets/images/coaches.jpeg" alt="Personal Training" class="feature-image">
        <div class="feature-content">
          <h3 class="feature-title">PERSONAL TRAINING</h3>
          <p class="feature-text">Achieve more with a Personal Trainer - Personalized, Motivating, Effective. Our expert trainers will help you reach your goals faster.</p>
          <a href="#" class="feature-link">Learn More <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>

      <div class="feature-card">
        <img src="../assets/images/landing.jpeg" alt="Classes" class="feature-image">
        <div class="feature-content">
          <h3 class="feature-title">CHOOSE YOUR CLASSES</h3>
          <p class="feature-text">Gym newbie or getting back in the game, we've got a wide variety of classes to choose from. Find what works for you.</p>
          <a href="#" class="feature-link">Learn More <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>

      <div class="feature-card">
        <img src="../assets/images/location.jpg" alt="Membership" class="feature-image">
        <div class="feature-content">
          <h3 class="feature-title">MEMBERSHIP OPTIONS</h3>
          <p class="feature-text">No long-term contracts, no monthly dues, just pay as you go with up to 14 months validity. Choose what's best for you.</p>
          <a href="#" class="feature-link">Join Now <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
    </div>
  </section>  <!-- About Us Section - Compact -->
  <section id="about" class="section">
    <div class="section-container">
      <div class="section-header">
        <h2 class="section-title">About Fitness Academy</h2>
        <p class="section-subtitle">Your premier fitness destination in Caloocan City</p>
      </div>
      
      <div class="about-content-compact">
        <div class="about-main-compact">
          <div class="about-text-compact">
            <p>
              Fitness Academy is Caloocan City's premier fitness destination, dedicated to empowering individuals 
              on their journey to better health and wellness. Located in Poblacion, we provide a comprehensive 
              fitness experience with modern equipment, expert guidance, and a supportive community.
            </p>
            
            <p>
              Whether you're a beginner or experienced athlete, we provide the tools, support, and motivation 
              you need to achieve your fitness goals in a welcoming environment.
            </p>
          </div>
            <div class="about-image-compact">
            <img src="../assets/images/fa.jpg" alt="Fitness Academy Interior" class="main-about-image-compact">
          </div>
        </div>
      </div>
    </div>
  </section></section>

  <!-- Location Section with Interactive Map -->
  <section id="location" class="section">
    <div class="section-container">
      <div class="section-header">
        <h2 class="section-title">Our Location</h2>
        <p class="section-subtitle">Find us at the heart of Caloocan City</p>
      </div>

      <div class="location-content">
        <div class="location-info">
          <h3>Fitness Academy</h3>
          
          <div class="location-details">            <div class="location-item">
              <i class="fas fa-map-marker-alt"></i>
              <span>349 A. Mabini St, Poblacion, Caloocan, 1400 Metro Manila</span>
            </div>
            
            <div class="location-item">
              <i class="fas fa-phone"></i>
              <span>0917 700 4373</span>
            </div>
            
            <div class="location-item">
              <i class="fas fa-envelope"></i>
              <span>fitnessacademycaloocan@gmail.com</span>
            </div>
            
            <div class="location-item">
              <i class="fas fa-clock"></i>
              <span>Mon-Sun: 5:00 AM - 10:00 PM</span>
            </div>
            
            <div class="location-item">
              <i class="fas fa-parking"></i>
              <span>Ample parking available</span>
            </div>
          </div>
            <a href="https://www.google.com/maps/dir//Fitness+Academy+-+Caloocan,+349+A.+Mabini+St,+Poblacion,+Caloocan,+1400+Metro+Manila/@14.6496838,120.9728041,17z" 
             target="_blank" class="directions-btn">
            <i class="fas fa-directions"></i>
            Get Directions
          </a>
        </div>

        <div class="map-container">
          <div id="map"></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Free Courses Section -->
  <section id="free-courses" class="section">
    <div class="section-container">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-gift"></i> Free Courses
        </h2>
        <p class="section-subtitle">Explore our range of free courses available online</p>
      </div>

      <?php if (count($free_videos) > 0): ?>
          <div class="video-grid">
              <?php foreach ($free_videos as $video): ?>
<div class="video-card" style="cursor:pointer;text-decoration:none;color:inherit;" 
     data-video-id="<?= $video['id'] ?>">                      <div class="video-thumbnail">                          <?php if (isset($video['thumbnail_path']) && $video['thumbnail_path']): ?>
                              <img src="../<?= htmlspecialchars(fixThumbnailPath($video['thumbnail_path'])) ?>" alt="Thumbnail">
                          <?php else: ?>
                              <i class="fas fa-play-circle"></i>
                          <?php endif; ?>
                      </div>
                      <div class="video-info">
                          <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
                          <div class="coach-name">
                              <i class="fas fa-user"></i>
                              <?= htmlspecialchars($video['First_Name'] . ' ' . $video['Last_Name']) ?>
                          </div>
                          <div class="video-description"><?= htmlspecialchars($video['description']) ?></div>
                          <span class="access-badge access-free">Free</span>
                      </div>
                  </a>
              <?php endforeach; ?>
          </div>
      <?php else: ?>
          <div class="empty-state">
              <i class="fas fa-video"></i>
              <h3>No Free Courses Available</h3>
              <p>Check back later for new free content from our trainers</p>
          </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Video Modal -->
<div id="video-modal" class="video-modal" style="display:none;">
  <div class="video-modal-content">
    <span class="video-modal-close" id="video-modal-close">&times;</span>
    <div id="video-modal-body"></div>
  </div>
</div>

  <!-- Enhanced Footer with Active Social Media Links -->
  <footer class="footer">
    <div class="footer-content">
      <div class="footer-section">
        <h3>Fitness Academy</h3>
        <p>Your premier fitness destination in Caloocan City. We're dedicated to helping you achieve your fitness goals in a supportive and motivating environment.</p>
      </div>

      <div class="footer-section">
        <h3>Quick Links</h3>
        <p><a href="login.php">Login</a></p>
        <p><a href="register.php">Welcome ka rito Ka-TroFA</a></p>
        <p><a href="register_coach.php">Be a Coach</a></p>
        <p><a href="#membership">Membership Plans</a></p>
      </div>

      <div class="footer-section">
        <h3>Contact Info</h3>
        <p><i class="fas fa-map-marker-alt"></i> 3F Newland Lumber Building</p>
        <p>A. Mabini Street, Caloocan City</p>
        <p><i class="fas fa-phone"></i> 0917 700 4373</p>
        <p><i class="fas fa-envelope"></i> fitnessacademy28@gmail.com</p>
      </div>

      <div class="footer-section">
        <h3>Follow Us</h3>
        <div class="social-links">
          <a href="https://www.facebook.com/fitnessacademy" title="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="https://www.instagram.com/fitnessacademy" title="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="https://www.tiktok.com/@fitnessacademy" title="TikTok"><i class="fab fa-tiktok"></i></a>
          <a href="mailto:fitnessacademy28@gmail.com" title="Email us"><i class="fas fa-envelope"></i></a>
        </div>
      </div>
    </div>

    <div class="copyright">
      <p>&copy; <?php echo date("Y"); ?> Fitness Academy. All rights reserved.</p>
    </div>  </footer>

  <!-- Leaflet JavaScript -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
     integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
     crossorigin=""></script>

  <script>    // Initialize Leaflet Map
    document.addEventListener('DOMContentLoaded', function() {
      // Coordinates for Fitness Academy - Caloocan (349 A. Mabini St, Poblacion, Caloocan)
      var gymLat = 14.6496838;
      var gymLng = 120.9728041;
      
      // Initialize the map
      var map = L.map('map').setView([gymLat, gymLng], 16);

      // Add OpenStreetMap tile layer
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
      }).addTo(map);

      // Create custom gym icon
      var gymIcon = L.divIcon({
        className: 'custom-gym-marker',
        html: '<i class="fas fa-dumbbell" style="color: #e41e26; font-size: 24px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);"></i>',
        iconSize: [30, 30],
        iconAnchor: [15, 15]
      });

      // Add marker for the gym
      var gymMarker = L.marker([gymLat, gymLng], {icon: gymIcon}).addTo(map);
        // Add popup to the marker
      gymMarker.bindPopup(`
        <div style="text-align: center; font-family: 'Montserrat', sans-serif;">
          <h3 style="color: #e41e26; margin-bottom: 10px;">🏋️ Fitness Academy</h3>
          <p style="margin-bottom: 8px;"><strong>📍 Address:</strong><br>349 A. Mabini St, Poblacion<br>Caloocan, 1400 Metro Manila</p>
          <p style="margin-bottom: 8px;"><strong>📞 Phone:</strong><br>0917 700 4373</p>
          <p style="margin-bottom: 15px;"><strong>🕐 Hours:</strong><br>Mon-Sun: 5:00 AM - 10:00 PM</p>
          <a href="https://www.google.com/maps/dir//Fitness+Academy+-+Caloocan,+349+A.+Mabini+St,+Poblacion,+Caloocan,+1400+Metro+Manila/@14.6496838,120.9728041,17z" 
             target="_blank" 
             style="background: #e41e26; color: white; padding: 8px 16px; text-decoration: none; border-radius: 20px; font-weight: 600;">
            🧭 Get Directions
          </a>
        </div>
      `).openPopup();

      // Add circle to show approximate coverage area
      L.circle([gymLat, gymLng], {
        color: '#e41e26',
        fillColor: '#e41e26',
        fillOpacity: 0.1,
        radius: 500
      }).addTo(map);
    });
  </script>

  <script>
    // Enhanced mobile menu toggle functionality
    document.getElementById('mobile-toggle').addEventListener('click', function(e) {
      e.preventDefault();
      const mainMenu = document.getElementById('main-menu');
      mainMenu.classList.toggle('active');

      // Toggle aria attributes for accessibility
      const expanded = mainMenu.classList.contains('active');
      this.setAttribute('aria-expanded', expanded);

      // Add overlay when menu is open
      document.body.classList.toggle('menu-open', expanded);
    });

    // Close mobile menu when clicking menu items
    document.querySelectorAll('.main-menu a').forEach(item => {
      item.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
          document.getElementById('main-menu').classList.remove('active');
          document.body.classList.remove('menu-open');
          document.getElementById('mobile-toggle').setAttribute('aria-expanded', 'false');
        }
      });
    });

    // Close mobile menu when clicking menu items including auth buttons
    document.querySelectorAll('.main-menu a').forEach(item => {
      item.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
          document.getElementById('main-menu').classList.remove('active');
          document.body.classList.remove('menu-open');
          document.getElementById('mobile-toggle').setAttribute('aria-expanded', 'false');
        }
      });
    });

    // Auto-hide navbar on scroll
    (() => {
      const navbar = document.querySelector('.navbar');
      let lastScrollY = window.scrollY;
      let ticking = false;

      // Threshold in pixels - how far user needs to scroll before navbar state changes
      const scrollThreshold = 5;

      const updateNavbar = () => {
        const scrollY = window.scrollY;

        // Only change navbar state if we've scrolled more than threshold amount
        if (Math.abs(scrollY - lastScrollY) < scrollThreshold) {
          ticking = false;
          return;
        }

        // Add hidden class when scrolling down, remove when scrolling up
        if (scrollY > lastScrollY) {
          navbar.classList.add('navbar-hidden');
        } else {
          navbar.classList.remove('navbar-hidden');
        }

        lastScrollY = scrollY;
        ticking = false;
      };

      // Throttled scroll handler using requestAnimationFrame for better performance
      window.addEventListener('scroll', () => {
        if (!ticking) {
          window.requestAnimationFrame(updateNavbar);
          ticking = true;
        }
      }, {
        passive: true
      });
    })();

    // Scroll animations using Intersection Observer
    (() => {
      // Get all elements that should animate on scroll
      const featureCards = document.querySelectorAll('.feature-card');
      const sectionHeaders = document.querySelectorAll('.section-header');
      const infoCards = document.querySelectorAll('.info-card');
      const sectionImages = document.querySelectorAll('.section-image');
      const ctaBanner = document.querySelector('.cta-banner-content');
      const footerSections = document.querySelectorAll('.footer-section');

      // Add initial animation classes
      featureCards.forEach((card, index) => {
        card.classList.add('fade-in-up', `delay-${index * 100}`);
      });

      sectionHeaders.forEach(header => {
        header.classList.add('fade-in-up');
      });

      infoCards.forEach(card => {
        card.classList.add('fade-in-left');
      });

      sectionImages.forEach(img => {
        img.classList.add('fade-in-right');
      });

      if (ctaBanner) ctaBanner.classList.add('fade-in');

      footerSections.forEach((section, index) => {
        section.classList.add('fade-in-up', `delay-${index * 100}`);
      });

      // Create the intersection observer
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          // If element is in viewport
          if (entry.isIntersecting) {
            // Add class to make element visible
            entry.target.classList.add('visible');
            // Stop observing after animation
            observer.unobserve(entry.target);
          }
        });
      }, {
        root: null, // viewport
        threshold: 0.15, // 15% of element must be visible
        rootMargin: '-50px' // trigger slightly before element comes into view
      });

      // Start observing all animated elements
      document.querySelectorAll('.fade-in-up, .fade-in-left, .fade-in-right, .fade-in').forEach(el => {
        observer.observe(el);
      });
    })();

    // Performance optimization for mobile devices
    (() => {
      const isMobile = window.innerWidth <= 768;

      // Optimize scroll animations for mobile
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            // Add class to make element visible
            entry.target.classList.add('visible');
            // Stop observing after animation
            observer.unobserve(entry.target);
          }
        });
      }, {
        root: null,
        threshold: isMobile ? 0.1 : 0.15, // Lower threshold for mobile for earlier triggering
        rootMargin: isMobile ? '-30px' : '-50px' // Less strict margin on mobile
      });

      // Start observing all animated elements
      document.querySelectorAll('.fade-in-up, .fade-in-left, .fade-in-right, .fade-in').forEach(el => {
        observer.observe(el);
      });

      // Smoother navbar behavior on mobile
      if (isMobile) {
        const navbar = document.querySelector('.navbar');
        let touchStartY = 0;
        let touchEndY = 0;

        document.addEventListener('touchstart', e => {
          touchStartY = e.touches[0].clientY;
        }, {
          passive: true
        });

        document.addEventListener('touchend', e => {
          touchEndY = e.changedTouches[0].clientY;
          const diff = touchStartY - touchEndY;

          // Only trigger if significant vertical swipe
          if (Math.abs(diff) > 30) {
            if (diff > 0) {
              // Swiping up, hide navbar
              navbar.classList.add('navbar-hidden');
            } else {
              // Swiping down, show navbar
              navbar.classList.remove('navbar-hidden');
            }
          }
        }, {
          passive: true
        });
      }
    })();

    // Lazy load images for better mobile performance
    if ('loading' in HTMLImageElement.prototype) {
      // Browser supports native lazy loading
      const images = document.querySelectorAll('img');
      images.forEach(img => {
        img.loading = 'lazy';
      });
    }

  // Store your videos in JS for client-side lookup
  const freeVideos = <?= json_encode($free_videos) ?>;

  // When a video card is clicked
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.video-card').forEach(card => {
      card.addEventListener('click', function() {
        const id = this.getAttribute('data-video-id');
        const video = freeVideos.find(v => v.id == id);

        if (video) {
          let html = '';
          if (video.video_path) {
            // Fix the thumbnail path in JavaScript
            const thumbnailPath = video.thumbnail_path && video.thumbnail_path.startsWith('../') ? 
                video.thumbnail_path.substring(3) : video.thumbnail_path;
            
            html += `<video src="../${video.video_path}" controls autoplay style="width:100%;border-radius:8px;" poster="${thumbnailPath ? '../'+thumbnailPath : ''}"></video>`;
            html += `<div style="margin-top:14px;">
  <a href="../${video.video_path}" download class="cta download-btn">
    <i class="fas fa-download"></i> Download Video
  </a>
</div>`;
          } else {
            html += `<div style="text-align:center;color:#e41e26;">No video available</div>`;
          }
          html += `<h3 style="margin-top:16px;">${video.title}</h3>`;
          html += `<div class="coach-name"><i class="fas fa-user"></i> ${video.First_Name} ${video.Last_Name}</div>`;
          html += `<div style="margin-top:10px;color:#555;">${video.description ?? ''}</div>`;
          document.getElementById('video-modal-body').innerHTML = html;
          document.getElementById('video-modal').style.display = 'flex';
        }
      });
    });

    document.getElementById('video-modal-close').onclick = function() {
      document.getElementById('video-modal').style.display = 'none';
      document.getElementById('video-modal-body').innerHTML = '';
    };
    // Optional: close modal on outside click
    document.getElementById('video-modal').addEventListener('click', function(e) {
      if (e.target === this) {
        this.style.display = 'none';
        document.getElementById('video-modal-body').innerHTML = '';
      }
    });
  });
  </script>
</body>

</html>