<?php
// Direct settings API bypass to avoid routing issues
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load Laravel
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

// Get authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

// Simple token validation - you can enhance this
if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$token = substr($authHeader, 7);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all settings and extract values
        $rawSettings = DB::table('mlm_settings')->get()->keyBy('key');
        
        $settings = [];
        foreach ($rawSettings as $key => $setting) {
            $value = $setting->value;
            
            // Parse JSON if it's a JSON string
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $value = isset($decoded['value']) ? $decoded['value'] : $decoded;
                }
            }
            
            $settings[$key] = [
                'key' => $setting->key,
                'value' => $value,
                'updated_at' => $setting->updated_at
            ];
        }
        
        echo json_encode(['settings' => $settings]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // Update settings
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['settings']) || !is_array($input['settings'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request format']);
            exit;
        }

        foreach ($input['settings'] as $setting) {
            if (isset($setting['key']) && isset($setting['value'])) {
                // Store value in the same format as expected
                $value = $setting['value'];
                
                // For complex values, store as JSON
                if (is_array($value) || is_object($value)) {
                    $value = json_encode(['value' => $value]);
                } elseif (is_string($value) && !is_numeric($value)) {
                    // For simple strings, store as JSON value format
                    $value = json_encode(['value' => $value]);
                } else {
                    // For numbers, store as JSON value format
                    $value = json_encode(['value' => $value]);
                }
                
                DB::table('mlm_settings')
                    ->where('key', $setting['key'])
                    ->update(['value' => $value]);
                    
                // Clear cache if it's a Razorpay setting
                if ($setting['key'] === 'razorpay_key_id' || $setting['key'] === 'razorpay_key_secret') {
                    if (function_exists('cache')) {
                        cache()->forget('razorpay_config');
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
