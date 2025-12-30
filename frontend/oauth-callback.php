<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Google OAuth Callback</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .container {
            text-align: center;
        }
        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 3px solid white;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .message {
            font-size: 18px;
            margin-bottom: 10px;
        }
        .error {
            color: #ff6b6b;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="spinner"></div>
        <div class="message">מעבד חיבור...</div>
        <div class="submessage">אנא המתן, החלון ייסגר אוטומטית</div>
    </div>

    <script>
        (function() {
            'use strict';

            const params = new URLSearchParams(window.location.search);
            const code = params.get('code');
            const error = params.get('error');
            const state = params.get('state');

            console.log('[OAuth Callback] Received params:', { code: !!code, error, state });

            if (error) {
                document.querySelector('.message').textContent = 'שגיאה בחיבור';
                document.querySelector('.submessage').textContent = error;
                document.querySelector('.message').classList.add('error');
                document.querySelector('.spinner').style.display = 'none';
                
                // שליחת שגיאה ל-parent
                if (window.opener) {
                    window.opener.postMessage({
                        type: 'GOOGLE_AUTH_ERROR',
                        error: error
                    }, window.location.origin);
                }
                
                setTimeout(() => {
                    window.close();
                }, 3000);
                
                return;
            }

            if (code) {
                console.log('[OAuth Callback] Sending code to parent window');
                
                document.querySelector('.message').textContent = 'חיבור מוצלח!';
                document.querySelector('.submessage').textContent = 'סוגר חלון...';
                
                // שליחת הקוד ל-parent window
                if (window.opener) {
                    window.opener.postMessage({
                        type: 'GOOGLE_AUTH_CODE',
                        code: code,
                        state: state
                    }, window.location.origin);
                    
                    console.log('[OAuth Callback] Code sent to parent');
                    
                    // סגירת החלון אחרי שנייה
                    setTimeout(() => {
                        window.close();
                    }, 1000);
                } else {
                    document.querySelector('.message').textContent = 'שגיאה: חלון אב לא נמצא';
                    document.querySelector('.submessage').textContent = 'אנא סגור חלון זה ונסה שוב';
                    document.querySelector('.spinner').style.display = 'none';
                }
            } else {
                document.querySelector('.message').textContent = 'לא התקבל קוד אימות';
                document.querySelector('.submessage').textContent = 'אנא נסה שוב';
                document.querySelector('.message').classList.add('error');
                document.querySelector('.spinner').style.display = 'none';
                
                setTimeout(() => {
                    window.close();
                }, 3000);
            }
        })();
    </script>
</body>
</html>

