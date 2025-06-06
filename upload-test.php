<?php
echo "<h2>Server Information</h2>";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Operating System: " . php_uname() . "<br>";

echo "<h2>PHP Upload Settings</h2>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
echo "memory_limit: " . ini_get('memory_limit') . "<br>";

echo "<h2>Request Information</h2>";
echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "<br>";
echo "Content Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'Not set') . "<br>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Upload Attempt</h2>";
    if (isset($_FILES['test_file'])) {
        echo "File name: " . $_FILES['test_file']['name'] . "<br>";
        echo "File size: " . $_FILES['test_file']['size'] . " bytes<br>";
        echo "File error: " . $_FILES['test_file']['error'] . "<br>";
        echo "Temp name: " . $_FILES['test_file']['tmp_name'] . "<br>";
        
        // Error code meanings
        $errors = [
            0 => 'UPLOAD_ERR_OK - No error',
            1 => 'UPLOAD_ERR_INI_SIZE - File exceeds upload_max_filesize',
            2 => 'UPLOAD_ERR_FORM_SIZE - File exceeds MAX_FILE_SIZE in form',
            3 => 'UPLOAD_ERR_PARTIAL - File was only partially uploaded',
            4 => 'UPLOAD_ERR_NO_FILE - No file was uploaded',
            6 => 'UPLOAD_ERR_NO_TMP_DIR - Missing temporary folder',
            7 => 'UPLOAD_ERR_CANT_WRITE - Failed to write file to disk',
            8 => 'UPLOAD_ERR_EXTENSION - File upload stopped by extension'
        ];
        
        echo "Error meaning: " . ($errors[$_FILES['test_file']['error']] ?? 'Unknown error') . "<br>";
        
    } else {
        echo "No file data received<br>";
    }
    echo "POST data size: " . strlen(serialize($_POST)) . " bytes<br>";
} else {
    echo '<h2>Upload Test</h2>';
    echo '<form method="post" enctype="multipart/form-data">';
    echo '<input type="file" name="test_file" accept="video/*">';
    echo '<input type="submit" value="Test Upload">';
    echo '</form>';
}
?>
