<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - Blue Facility Services</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .error-container {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 60px 40px;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ef4444;
        }
        
        .error-code {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .error-message {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .error-details {
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .back-button {
            display: inline-block;
            padding: 15px 30px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        
        <div class="error-code">
            <?= http_response_code() ?>
        </div>
        
        <div class="error-message">
            Access Denied
        </div>
        
        <div class="error-details">
            <p><strong>Security Check Failed</strong></p>
            <p>Your request was blocked by our security system. This could be due to:</p>
            <ul>
                <li>Rate limiting protection</li>
                <li>Invalid authentication</li>
                <li>Suspicious activity detected</li>
                <li>CSRF token validation failure</li>
            </ul>
        </div>
        
        <a href="/" class="back-button">
            <i class="fas fa-home"></i>
            Return to Home
        </a>
    </div>
</body>
</html>
