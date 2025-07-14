<?php
// Подключение к базе данных
$host = 'localhost';
$dbname = 'minbank';
$username = 'root';
$password = 'Prac12345';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Обработка поиска
$searchField = $_GET['search_field'] ?? '';
$searchCondition = $_GET['search_condition'] ?? '';
$searchValue = $_GET['search_value'] ?? '';
$searchQuery = '';

if (!empty($searchField) && !empty($searchCondition) && !empty($searchValue)) {
    switch ($searchCondition) {
        case 'equals':
            $searchQuery = " AND $searchField = :search_value";
            break;
        case 'greater':
            $searchQuery = " AND $searchField > :search_value";
            break;
        case 'less':
            $searchQuery = " AND $searchField < :search_value";
            break;
        case 'like':
            $searchQuery = " AND $searchField LIKE :search_value";
            $searchValue = "%$searchValue%";
            break;
    }
}

// Обработка добавления новой опции
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_option') {
        try {
            $stmt = $pdo->prepare("INSERT INTO minbank_payments_options 
                (payment_id, `key`, `value`) 
                VALUES (?, ?, ?)");
            
            $stmt->execute([
                $_POST['payment_id'],
                $_POST['key'],
                $_POST['value']
            ]);
            
            header("Location: options.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Ошибка при добавлении опции: " . $e->getMessage());
        }
    }
    
    // Обработка редактирования опции
    if ($_POST['action'] === 'edit_option') {
        try {
            $originalValues = explode('|', $_POST['original_values']);
            
            $stmt = $pdo->prepare("UPDATE minbank_payments_options SET 
                payment_id = ?, 
                `key` = ?, 
                `value` = ?
                WHERE payment_id = ? AND `key` = ?");
            
            $stmt->execute([
                $_POST['payment_id'],
                $_POST['key'],
                $_POST['value'],
                $originalValues[0],
                $originalValues[1]
            ]);
            
            header("Location: options.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Ошибка при обновлении опции: " . $e->getMessage());
        }
    }
    
    // Обработка удаления опции
    if ($_POST['action'] === 'delete_option') {
        try {
            $stmt = $pdo->prepare("DELETE FROM minbank_payments_options WHERE payment_id = ? AND `key` = ?");
            $stmt->execute([
                $_POST['payment_id'],
                $_POST['key']
            ]);
            
            header("Location: options.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Ошибка при удалении опции: " . $e->getMessage());
        }
    }
}

// Пагинация
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Получение списка опций
try {
    $sql = "SELECT * FROM minbank_payments_options WHERE 1=1 $searchQuery ORDER BY payment_id, `key` LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    
    if (!empty($searchQuery)) {
        $stmt->bindValue(':search_value', $searchValue);
    }
    
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получение общего количества записей с учетом поиска
    $countSql = "SELECT COUNT(*) FROM minbank_payments_options WHERE 1=1 $searchQuery";
    $countStmt = $pdo->prepare($countSql);
    
    if (!empty($searchQuery)) {
        $countStmt->bindValue(':search_value', $searchValue);
    }
    
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);
} catch (PDOException $e) {
    die("Ошибка при получении опций: " . $e->getMessage());
}

// Получение данных для редактирования
$editOptionData = null;
if (isset($_GET['edit_id'])) {
    $editParams = explode('|', $_GET['edit_id']);
    if (count($editParams) === 2) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM minbank_payments_options WHERE payment_id = ? AND `key` = ?");
            $stmt->execute([$editParams[0], $editParams[1]]);
            $editOptionData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Ошибка при получении данных опции: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Опции платежей - MinBank</title>
  <link rel="stylesheet" href="/css/styles.css">
  <link rel="stylesheet" href="/css/tables.css">
  <link rel="stylesheet" href="/css/modals.css">
</head>
<body>
  <header class="header">
    <div class="logo">MinBank</div>
    <div class="page-title">Опции платежей</div>
    <div class="user-info">
      <span class="user-role">Администратор</span>
      <button class="logout-btn">Выйти</button>
    </div>
  </header>

  <aside class="sidebar">
    <nav>
      <ul>
        <li><a href="payments.php">Платежи</a></li>
        <li><a href="logs.php">Логи платежей</a></li>
        <li class="active"><a href="options.php">Опции платежей</a></li>
        <li><a href="temporary.php">Временные платежи</a></li>
        <li><a href="settings.php">Системные настройки</a></li>
        <li><a href="queries.php">Сохраненные запросы</a></li>
      </ul>
    </nav>
  </aside>

  <main class="content">
    <div class="toolbar">
      <button class="btn btn-add" id="add-option">Добавить опцию</button>
      <button class="btn btn-query" id="execute-query">Выполнить SQL запрос</button>
      
      <!-- Форма поиска -->
      <form method="get" class="search-form">
        <div class="search-box">
          <select name="search_field" class="search-field">
            <option value="">Выберите поле</option>
            <option value="payment_id" <?= $searchField === 'payment_id' ? 'selected' : '' ?>>ID платежа</option>
            <option value="key" <?= $searchField === 'key' ? 'selected' : '' ?>>Ключ</option>
            <option value="value" <?= $searchField === 'value' ? 'selected' : '' ?>>Значение</option>
          </select>
          
          <select name="search_condition" class="search-condition">
            <option value="">Условие</option>
            <option value="equals" <?= $searchCondition === 'equals' ? 'selected' : '' ?>>Равенство</option>
            <option value="greater" <?= $searchCondition === 'greater' ? 'selected' : '' ?>>Больше</option>
            <option value="less" <?= $searchCondition === 'less' ? 'selected' : '' ?>>Меньше</option>
            <option value="like" <?= $searchCondition === 'like' ? 'selected' : '' ?>>LIKE</option>
          </select>
          
          <input type="text" name="search_value" placeholder="Значение" value="<?= htmlspecialchars($searchValue) ?>">
          <button type="submit" class="btn-search">Найти</button>
          <?php if (!empty($searchField) || !empty($searchCondition) || !empty($searchValue)): ?>
            <a href="options.php" class="btn-clear-search">Сбросить</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID платежа</th>
            <th>Ключ</th>
            <th>Значение</th>
            <th>Действия</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($options as $option): ?>
          <tr>
            <td><?= htmlspecialchars($option['payment_id']) ?></td>
            <td><?= htmlspecialchars($option['key']) ?></td>
            <td><?= htmlspecialchars($option['value']) ?></td>
            <td class="table-actions">
              <a href="options.php?edit_id=<?= htmlspecialchars($option['payment_id']) ?>|<?= htmlspecialchars($option['key']) ?>" class="btn btn-edit">Ред.</a>
              <button type="button" class="btn btn-delete" data-payment-id="<?= htmlspecialchars($option['payment_id']) ?>" data-key="<?= htmlspecialchars($option['key']) ?>">Уд.</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination">
      <?php if ($currentPage > 1): ?>
        <a href="options.php?page=<?= $currentPage - 1 ?><?= !empty($searchField) ? "&search_field=$searchField&search_condition=$searchCondition&search_value=" . urlencode($searchValue) : '' ?>" class="btn-prev">&#9668; Назад</a>
      <?php else: ?>
        <span class="btn-prev disabled">&#9668; Назад</span>
      <?php endif; ?>
      
      <span class="page-info">Страница <?= $currentPage ?> из <?= $totalPages ?></span>
      
      <?php if ($currentPage < $totalPages): ?>
        <a href="options.php?page=<?= $currentPage + 1 ?><?= !empty($searchField) ? "&search_field=$searchField&search_condition=$searchCondition&search_value=" . urlencode($searchValue) : '' ?>" class="btn-next">Вперед &#9658;</a>
      <?php else: ?>
        <span class="btn-next disabled">Вперед &#9658;</span>
      <?php endif; ?>
    </div>
  </main>

  <!-- Модальное окно добавления опции -->
  <div class="modal" id="option-modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Добавить опцию платежа</h2>
      <form method="post" action="options.php">
        <input type="hidden" name="action" value="add_option">
        <div class="form-group">
          <label for="option-payment-id">ID платежа:</label>
          <input type="text" id="option-payment-id" name="payment_id" required>
        </div>
        
        <div class="form-group">
          <label for="option-key">Ключ:</label>
          <input type="text" id="option-key" name="key" required maxlength="128">
        </div>
        
        <div class="form-group">
          <label for="option-value">Значение:</label>
          <textarea id="option-value" name="value" rows="4"></textarea>
        </div>
        
        <div class="form-actions">
          <button type="submit" class="btn btn-save">Сохранить</button>
          <button type="button" class="btn btn-cancel">Отмена</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Модальное окно редактирования опции -->
  <?php if ($editOptionData): ?>
  <div class="modal" id="edit-modal" style="display: flex;">
    <div class="modal-content">
      <span class="close" onclick="document.getElementById('edit-modal').style.display='none'">&times;</span>
      <h2>Редактировать опцию платежа</h2>
      <form method="post" action="options.php">
        <input type="hidden" name="action" value="edit_option">
        <input type="hidden" name="original_values" value="<?= htmlspecialchars($editOptionData['payment_id']) ?>|<?= htmlspecialchars($editOptionData['key']) ?>">
        
        <div class="form-group">
          <label for="edit-option-payment-id">ID платежа:</label>
          <input type="text" id="edit-option-payment-id" name="payment_id" value="<?= htmlspecialchars($editOptionData['payment_id']) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="edit-option-key">Ключ:</label>
          <input type="text" id="edit-option-key" name="key" value="<?= htmlspecialchars($editOptionData['key']) ?>" required maxlength="128">
        </div>
        
        <div class="form-group">
          <label for="edit-option-value">Значение:</label>
          <textarea id="edit-option-value" name="value" rows="4"><?= htmlspecialchars($editOptionData['value']) ?></textarea>
        </div>
        
        <div class="form-actions">
          <button type="submit" class="btn btn-save">Сохранить</button>
          <button type="button" class="btn btn-cancel" onclick="document.getElementById('edit-modal').style.display='none'">Отмена</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Модальное окно SQL запроса -->
  <div class="modal" id="query-modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Выполнить SQL запрос</h2>
      
      <div class="form-group">
        <label for="saved-queries">Сохраненные запросы:</label>
        <select id="saved-queries">
          <option value="">-- Выберите запрос --</option>
          <option value="1">Часто используемые опции</option>
        </select>
      </div>
      
      <div class="form-group">
        <label for="sql-query">SQL запрос:</label>
        <textarea id="sql-query" rows="8"></textarea>
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn btn-save" id="execute-sql">Выполнить</button>
        <button type="button" class="btn btn-cancel">Отмена</button>
      </div>
    </div>
  </div>

  <script src="/js/main.js"></script>
  <script src="/js/tables.js"></script>
  <script>
    // Обработка модального окна добавления опции
    document.getElementById('add-option').addEventListener('click', function() {
      document.getElementById('option-modal').style.display = 'flex';
    });
    
    // Обработка удаления опции
    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', function() {
        const paymentId = this.getAttribute('data-payment-id');
        const key = this.getAttribute('data-key');
        
        if (confirm('Вы уверены, что хотите удалить эту опцию?')) {
          // Создаем скрытую форму для отправки запроса
          const form = document.createElement('form');
          form.method = 'post';
          form.action = 'options.php';
          
          const actionInput = document.createElement('input');
          actionInput.type = 'hidden';
          actionInput.name = 'action';
          actionInput.value = 'delete_option';
          form.appendChild(actionInput);
          
          const paymentIdInput = document.createElement('input');
          paymentIdInput.type = 'hidden';
          paymentIdInput.name = 'payment_id';
          paymentIdInput.value = paymentId;
          form.appendChild(paymentIdInput);
          
          const keyInput = document.createElement('input');
          keyInput.type = 'hidden';
          keyInput.name = 'key';
          keyInput.value = key;
          form.appendChild(keyInput);
          
          document.body.appendChild(form);
          form.submit();
        }
      });
    });
    
    // Закрытие модальных окон
    document.querySelectorAll('.close').forEach(closeBtn => {
      closeBtn.addEventListener('click', function() {
        this.closest('.modal').style.display = 'none';
      });
    });
    
    // Закрытие по клику вне модального окна
    document.querySelectorAll('.modal').forEach(modal => {
      modal.addEventListener('click', function(e) {
        if (e.target === this) {
          this.style.display = 'none';
        }
      });
    });
  </script>
  
</body>
</html>