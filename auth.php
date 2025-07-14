<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Авторизация - MinBank</title>
  <link rel="stylesheet" href="/css/auth.css">
</head>
<body>
  <div class="auth-container">
    <div class="auth-box">
      <h1 class="auth-title">Авторизация</h1>
      
      <form class="auth-form" id="login-form">
        <div class="form-group">
          <label for="username">Логин:</label>
          <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
          <label for="password">Пароль:</label>
          <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" class="auth-submit">Войти</button>
      </form>
      
      <div class="auth-footer">
        Система управления платежами MinBank
      </div>
    </div>
  </div>

  <script src="/js/auth.js"></script>
</body>
</html>