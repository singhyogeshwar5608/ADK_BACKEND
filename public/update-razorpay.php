<?php
// Simple web interface to update Razorpay keys
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keyId = $_POST['key_id'] ?? '';
    $keySecret = $_POST['key_secret'] ?? '';
    
    if ($keyId && $keySecret) {
        try {
            DB::table('mlm_settings')
                ->where('key', 'razorpay_key_id')
                ->update(['value' => json_encode(['value' => $keyId])]);
            
            DB::table('mlm_settings')
                ->where('key', 'razorpay_key_secret')
                ->update(['value' => json_encode(['value' => $keySecret])]);
            
            $message = 'Razorpay keys updated successfully!';
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please fill in both fields';
    }
}

// Get current values
try {
    $currentKeyId = DB::table('mlm_settings')->where('key', 'razorpay_key_id')->value('value');
    $currentKeySecret = DB::table('mlm_settings')->where('key', 'razorpay_key_secret')->value('value');
    
    $currentKeyId = json_decode($currentKeyId, true)['value'] ?? '';
    $currentKeySecret = json_decode($currentKeySecret, true)['value'] ?? '';
} catch (Exception $e) {
    $currentKeyId = '';
    $currentKeySecret = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Razorpay Keys</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        button:active {
            transform: translateY(0);
        }
        .message {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .current-values {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .current-values strong {
            color: #667eea;
        }
        .current-values p {
            margin: 5px 0;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 Update Razorpay Keys</h1>
        <p class="subtitle">Configure your Razorpay payment gateway credentials</p>
        
        <?php if ($message): ?>
            <div class="message success">✅ <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($currentKeyId || $currentKeySecret): ?>
            <div class="current-values">
                <strong>Current Values:</strong>
                <p>Key ID: <?= htmlspecialchars($currentKeyId) ?></p>
                <p>Key Secret: <?= str_repeat('•', strlen($currentKeySecret)) ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="key_id">Razorpay Key ID</label>
                <input 
                    type="text" 
                    id="key_id" 
                    name="key_id" 
                    placeholder="rzp_live_xxxxxxxxxxxxx"
                    value="<?= htmlspecialchars($currentKeyId) ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="key_secret">Razorpay Key Secret</label>
                <input 
                    type="password" 
                    id="key_secret" 
                    name="key_secret" 
                    placeholder="Enter your Razorpay key secret"
                    required
                >
            </div>
            
            <button type="submit">Update Keys</button>
        </form>
    </div>
</body>
</html>
