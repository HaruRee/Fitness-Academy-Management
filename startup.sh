#!/bin/bash

# Startup script for Azure App Service
# This script configures nginx and PHP for large file uploads

echo "Starting Azure App Service configuration..."

# Create nginx configuration for large uploads
echo "Configuring nginx for large file uploads..."

# Create nginx configuration directory if it doesn't exist
mkdir -p /home/site/nginx

# Create custom nginx configuration
cat > /home/site/nginx/default.conf << 'EOF'
server {
    listen 8080;
    listen [::]:8080;
    root /home/site/wwwroot;
    index index.php index.html index.htm;
    
    # Increase client max body size for file uploads (500MB)
    client_max_body_size 500M;
    
    # Increase buffer sizes
    client_body_buffer_size 128k;
    client_header_buffer_size 1k;
    large_client_header_buffers 4 4k;
    
    # Increase timeouts for large uploads
    client_body_timeout 120s;
    client_header_timeout 120s;
    keepalive_timeout 65s;
    send_timeout 120s;
    
    # Proxy settings for large uploads
    proxy_connect_timeout 300s;
    proxy_send_timeout 300s;
    proxy_read_timeout 300s;
    proxy_buffering off;
    proxy_request_buffering off;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        
        # Increase FastCGI timeouts for large uploads
        fastcgi_connect_timeout 300s;
        fastcgi_send_timeout 300s;
        fastcgi_read_timeout 300s;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }
    
    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    
    # Handle video files
    location ~* \.(mp4|avi|mov|wmv|flv|webm)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ /(vendor|config|\.git) {
        deny all;
    }
}
EOF

# Create PHP configuration for large uploads
echo "Configuring PHP for large file uploads..."

# Create PHP configuration directory if it doesn't exist
mkdir -p /home/site/ini

# Create custom PHP configuration
cat > /home/site/ini/uploads.ini << 'EOF'
; PHP configuration for large file uploads
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M
max_file_uploads = 20

; Error reporting (adjust for production)
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Session settings
session.gc_maxlifetime = 1440
session.cookie_httponly = On
session.cookie_secure = On
session.use_strict_mode = On

; Security settings
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
EOF

# Set proper permissions
echo "Setting permissions..."
chmod -R 755 /home/site/wwwroot
chmod -R 777 /home/site/wwwroot/uploads

# Create uploads directory structure if it doesn't exist
mkdir -p /home/site/wwwroot/uploads/coach_videos
mkdir -p /home/site/wwwroot/uploads/video_thumbnails
mkdir -p /home/site/wwwroot/uploads/coach_resumes
chmod -R 777 /home/site/wwwroot/uploads

# Log configuration completion
echo "Azure App Service configuration completed successfully!"
echo "Nginx configuration: /home/site/nginx/default.conf"
echo "PHP configuration: /home/site/ini/uploads.ini"
echo "Max upload size: 500MB"
echo "Max execution time: 300 seconds"

# Display current PHP limits for verification
echo "Current PHP upload settings:"
php -i | grep -E "(upload_max_filesize|post_max_size|max_execution_time|memory_limit)"

echo "Startup script completed. Application should now support large file uploads."
