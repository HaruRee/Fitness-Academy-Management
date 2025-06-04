# New Attendance System Documentation

## Overview
The gym management system now uses a modernized QR-code based attendance system with the following improvements:

### Key Features
1. **Static QR Codes**: Each user has one permanent QR code that works for both check-in and check-out
2. **Separate Scanner Pages**: Dedicated check-in and check-out scanner pages
3. **Improved UI/UX**: Clear visual feedback and better user experience
4. **Real-time Updates**: Live display of recent attendance activity

## File Structure

### Core Files
- `/attendance/checkin.php` - Check-in scanner page
- `/attendance/checkout.php` - Check-out scanner page
- `/attendance/generate_static_qr.php` - Static QR code generation API
- `/attendance/process_checkin.php` - Check-in processing API
- `/attendance/process_checkout.php` - Check-out processing API
- `/attendance/get_recent_checkins.php` - Recent check-ins API
- `/attendance/get_recent_checkouts.php` - Recent check-outs API
- `/attendance/test_attendance.php` - Test page for system verification

### Removed Files (Obsolete)
- `includes/qr_scanner.php` - Replaced by dedicated check-in/check-out pages
- `includes/qr_image_api.php` - No longer needed with static QR system
- `includes/qr_attendance_manager.php` - Functionality moved to new system
- `includes/generate_checkin_qr.php` - Replaced by static QR generation

### Remaining Files
- `includes/generate_staff_qr.php` - Still used by staff attendance system

### Updated Files
- `includes/member_dashboard.php` - Updated with static QR display only
- `includes/coach_dashboard.php` - Updated with static QR display only
- `includes/qr_scanner.php` - Now redirects to new check-in page
- All admin/staff navigation pages - Updated to point to new attendance system

## How It Works

### Static QR Code System
1. Each user gets one permanent QR code containing their User ID
2. The same QR code is used for both check-in and check-out
3. Physical scanners at gym entrances/exits determine the action
4. QR codes are generated on-demand and can be saved to user's device

### Check-in Process
1. User scans their QR code using the check-in scanner
2. System validates the user and checks for active membership
3. Creates a new attendance record with check-in time
4. Updates recent check-ins display

### Check-out Process
1. User scans their QR code using the check-out scanner
2. System finds the most recent check-in without a check-out
3. Updates the attendance record with check-out time
4. Updates recent check-outs display

## Database Structure

### Tables Used
- `users` - User information and membership status
- `attendance_records` - Check-in/check-out records
- `memberships` - Membership status and expiration

### Key Fields
- `attendance_records.UserID` - Links to user
- `attendance_records.CheckInTime` - Check-in timestamp
- `attendance_records.CheckOutTime` - Check-out timestamp (nullable)
- `attendance_records.SessionID` - Unique session identifier

## Navigation Updates

All admin and staff pages now link to the new attendance system:
- QR Scanner menu items point to `/attendance/checkin.php`
- Old `qr_scanner.php` automatically redirects to new system
- Member and coach dashboards show static QR codes and scanner links

## Testing

Use `/attendance/test_attendance.php` to verify:
1. QR code generation
2. Check-in functionality
3. Check-out functionality
4. Recent attendance APIs
5. Navigation links

## Migration Notes

### What Changed
- QR codes are now static (one per user) instead of dynamic (per action)
- Scanner is split into dedicated check-in and check-out pages
- Improved error handling and user feedback
- Better mobile responsiveness

### Backward Compatibility
- Old QR scanner URLs automatically redirect to new system
- Database structure remains compatible
- Existing attendance records are preserved

## Maintenance

### Regular Tasks
1. Monitor attendance records for orphaned check-ins (no check-out)
2. Verify QR code generation is working properly
3. Check scanner camera permissions on mobile devices

### Troubleshooting
- If QR codes don't generate, check internet connection (uses qrserver.com API)
- If scanning fails, verify camera permissions in browser
- For attendance mismatches, check database for incomplete sessions

## Security Considerations

- QR codes contain only User ID (minimal data exposure)
- All API endpoints require active user session
- User status and membership validation on every scan
- Attendance records include timestamp and session tracking
