<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found | Painter Near Me</title>
    <link rel="stylesheet" href="/serve-asset.php?file=css/style.css">
    <style>
        .error-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f8f9fa;
            text-align: center;
            padding: 2rem;
        }
        .error-content {
            max-width: 500px;
            background: white;
            padding: 3rem 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .error-title {
            color: #333;
            margin-bottom: 1rem;
            font-size: 2rem;
        }
        .error-text {
            color: #666;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .error-btn {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: #00b050;
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        .error-btn:hover {
            background: #009140;
        }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-content">
            <div class="error-icon">🔍</div>
            <h1 class="error-title">Page Not Found</h1>
            <p class="error-text">
                Sorry, the page you're looking for doesn't exist. 
                It might have been moved, deleted, or you entered the wrong URL.
            </p>
            <a href="/" class="error-btn">Go Home</a>
        </div>
    </div>
</body>
</html> 