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

// Обработка добавления временного платежа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_temporary') {
        try {
            // Проверяем валидность JSON
            $jsonData = $_POST['json'];
            json_decode($jsonData);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Некорректный JSON формат');
            }

            $stmt = $pdo->prepare("INSERT INTO minbank_temporary_payments 
                (payment_id, json) 
                VALUES (?, ?)");
            
            $stmt->execute([
                $_POST['payment_id'],
                $jsonData
            ]);
            
            header("Location: temporary.php?success=1");
            exit();
        } catch (Exception $e) {
            die("Ошибка при добавлении временного платежа: " . $e->getMessage());
        }
    }
    
    // Обработка редактирования временного платежа
    if ($_POST['action'] === 'edit_temporary') {
        try {
            // Проверяем валидность JSON
            $jsonData = $_POST['json'];
            json_decode($jsonData);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Некорректный JSON формат');
            }

            $stmt = $pdo->prepare("UPDATE minbank_temporary_payments SET 
                payment_id = ?, 
                json = ?
                WHERE payment_id = ?");
            
            $stmt->execute([
                $_POST['payment_id'],
                $jsonData,
                $_POST['original_payment_id']
            ]);
            
            header("Location: temporary.php?success=1");
            exit();
        } catch (Exception $e) {
            die("Ошибка при обновлении временного платежа: " . $e->getMessage());
        }
    }
    
    // Обработка удаления временного платежа
    if ($_POST['action'] === 'delete_temporary') {
        try {
            $stmt = $pdo->prepare("DELETE FROM minbank_temporary_payments WHERE payment_id = ?");
            $stmt->execute([$_POST['payment_id']]);
            
            header("Location: temporary.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Ошибка при удалении временного платежа: " . $e->getMessage());
        }
    }
}

// Пагинация
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Получение списка временных платежей
try {
    $sql = "SELECT * FROM minbank_temporary_payments WHERE 1=1 $searchQuery ORDER BY payment_id LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    
    if (!empty($searchQuery)) {
        $stmt->bindValue(':search_value', $searchValue);
    }
    
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $temporaryPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получение общего количества записей с учетом поиска
    $countSql = "SELECT COUNT(*) FROM minbank_temporary_payments WHERE 1=1 $searchQuery";
    $countStmt = $pdo->prepare($countSql);
    
    if (!empty($searchQuery)) {
        $countStmt->bindValue(':search_value', $searchValue);
    }
    
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);
} catch (PDOException $e) {
    die("Ошибка при получении временных платежей: " . $e->getMessage());
}

// Получение данных для редактирования
$editTemporaryData = null;
if (isset($_GET['edit_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM minbank_temporary_payments WHERE payment_id = ?");
        $stmt->execute([$_GET['edit_id']]);
        $editTemporaryData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Ошибка при получении данных временного платежа: " . $e->getMessage());
    }
}

// Получаем список полей JSON для поиска
$searchableFields = ['payment_id'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Временные платежи - MinBank</title>
  <link rel="stylesheet" href="/css/styles.css">
  <link rel="stylesheet" href="/css/tables.css">
  <link rel="stylesheet" href="/css/modals.css">
</head>
<body>
  <header class="header">
    <div class="logo">MinBank</div>
    <div class="page-title">Временные платежи</div>
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
        <li><a href="options.php">Опции платежей</a></li>
        <li class="active"><a href="temporary.php">Временные платежи</a></li>
        <li><a href="settings.php">Системные настройки</a></li>
        <li><a href="queries.php">Сохраненные запросы</a></li>
      </ul>
    </nav>
  </aside>

  <main class="content">
    <div class="toolbar">
      <button class="btn btn-add" id="add-temporary">Добавить запись</button>
      <button class="btn btn-query" id="execute-query">Выполнить SQL запрос</button>
      
      <!-- Форма поиска -->
      <form method="get" class="search-form">
        <div class="search-box">
          <select name="search_field" class="search-field">
              <option value="">Выберите поле</option>
              <option value="payment_id" <?= $searchField === 'payment_id' ? 'selected' : '' ?>>ID платежа</option>
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
            <a href="temporary.php" class="btn-clear-search">Сбросить</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID платежа</th>
            <th>JSON данные</th>
            <th>Действия</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($temporaryPayments as $payment): ?>
          <tr>
            <td><?= htmlspecialchars($payment['payment_id']) ?></td>
            <td class="json-data"><?= htmlspecialchars($payment['json']) ?></td>
            <td class="table-actions">
              <a href="temporary.php?edit_id=<?= htmlspecialchars($payment['payment_id']) ?>" class="btn btn-edit">Ред.</a>
              <button type="button" class="btn btn-delete" data-payment-id="<?= htmlspecialchars($payment['payment_id']) ?>">Уд.</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination">
      <?php if ($currentPage > 1): ?>
        <a href="temporary.php?page=<?= $currentPage - 1 ?><?= !empty($searchField) ? "&search_field=$searchField&search_condition=$searchCondition&search_value=" . urlencode($searchValue) : '' ?>" class="btn-prev">&#9668; Назад</a>
      <?php else: ?>
        <span class="btn-prev disabled">&#9668; Назад</span>
      <?php endif; ?>
      
      <span class="page-info">Страница <?= $currentPage ?> из <?= $totalPages ?></span>
      
      <?php if ($currentPage < $totalPages): ?>
        <a href="temporary.php?page=<?= $currentPage + 1 ?><?= !empty($searchField) ? "&search_field=$searchField&search_condition=$searchCondition&search_value=" . urlencode($searchValue) : '' ?>" class="btn-next">Вперед &#9658;</a>
      <?php else: ?>
        <span class="btn-next disabled">Вперед &#9658;</span>
      <?php endif; ?>
    </div>
  </main>

  <!-- Модальное окно добавления временного платежа -->
  <div class="modal" id="temporary-modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Добавить временный платеж</h2>
      <form method="post" action="temporary.php">
        <input type="hidden" name="action" value="add_temporary">
        <div class="form-group">
          <label for="temporary-payment-id">ID платежа:</label>
          <input type="text" id="temporary-payment-id" name="payment_id" required>
        </div>
        
        <div class="form-group">
          <label for="temporary-json">JSON данные:</label>
          <textarea id="temporary-json" name="json" rows="8" required></textarea>
        </div>
        
        <div class="form-actions">
          <button type="submit" class="btn btn-save">Сохранить</button>
          <button type="button" class="btn btn-cancel">Отмена</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Модальное окно редактирования временного платежа -->
  <?php if ($editTemporaryData): ?>
  <div class="modal" id="edit-modal" style="display: flex;">
    <div class="modal-content">
      <span class="close" onclick="document.getElementById('edit-modal').style.display='none'">&times;</span>
      <h2>Редактировать временный платеж</h2>
      <form method="post" action="temporary.php">
        <input type="hidden" name="action" value="edit_temporary">
        <input type="hidden" name="original_payment_id" value="<?= htmlspecialchars($editTemporaryData['payment_id']) ?>">
        
        <div class="form-group">
          <label for="edit-temporary-payment-id">ID платежа:</label>
          <input type="text" id="edit-temporary-payment-id" name="payment_id" value="<?= htmlspecialchars($editTemporaryData['payment_id']) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="edit-temporary-json">JSON данные:</label>
          <textarea id="edit-temporary-json" name="json" rows="8" required><?= htmlspecialchars($editTemporaryData['json']) ?></textarea>
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
          <option value="1">Необработанные временные платежи</option>
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
    // Обработка модального окна добавления временного платежа
    document.getElementById('add-temporary').addEventListener('click', function() {
      document.getElementById('temporary-modal').style.display = 'flex';
    });
    
    // Обработка удаления временного платежа
    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', function() {
        const paymentId = this.getAttribute('data-payment-id');
        
        if (confirm('Вы уверены, что хотите удалить этот временный платеж?')) {
          // Создаем скрытую форму для отправки запроса
          const form = document.createElement('form');
          form.method = 'post';
          form.action = 'temporary.php';
          
          const actionInput = document.createElement('input');
          actionInput.type = 'hidden';
          actionInput.name = 'action';
          actionInput.value = 'delete_temporary';
          form.appendChild(actionInput);
          
          const paymentIdInput = document.createElement('input');
          paymentIdInput.type = 'hidden';
          paymentIdInput.name = 'payment_id';
          paymentIdInput.value = paymentId;
          form.appendChild(paymentIdInput);
          
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