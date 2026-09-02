<!DOCTYPE html>
<html>
<head>
    <title>PMAS Login</title>
    <style>
        body { font-family: sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 300px; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #218838; }
        .error { color: red; font-size: 14px; text-align: center; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>PMAS Login</h2>
        
        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form action="/login" method="POST">
            @csrf
            <label>Email</label>
            <input type="email" name="email" value="admin@pmas.com" required>
            
            <label>Password</label>
            <input type="password" name="password" value="password" required>
            
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>