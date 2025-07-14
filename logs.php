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
// Обработка удаления записи лога
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_log') {
    try {
        $stmt = $pdo->prepare("DELETE FROM minbank_payments_logs WHERE payment_id = ? AND date = ? AND location = ?");
        $stmt->execute([
            $_POST['payment_id'],
            $_POST['date'],
            $_POST['location']
        ]);
        
        header("Location: logs.php?success=1");
        exit();
    } catch (PDOException $e) {
        die("Ошибка при удалении записи лога: " . $e->getMessage());
    }
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
            if ($searchField === 'date') {
                $searchQuery = " AND $searchField > :search_value";
            } else {
                $searchQuery = " AND $searchField > :search_value";
            }
            break;
        case 'less':
            if ($searchField === 'date') {
                $searchQuery = " AND $searchField < :search_value";
            } else {
                $searchQuery = " AND $searchField < :search_value";
            }
            break;
        case 'like':
            $searchQuery = " AND $searchField LIKE :search_value";
            $searchValue = "%$searchValue%";
            break;
    }
}

// Обработка добавления новой записи лога
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_log') {
        try {
            $stmt = $pdo->prepare("INSERT INTO minbank_payments_logs 
                (payment_id, date, location, text, ip, browser) 
                VALUES (?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $_POST['payment_id'],
                $_POST['date'],
                $_POST['location'],
                $_POST['text'],
                $_POST['ip'],
                $_POST['browser']
            ]);
            
            header("Location: logs.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Ошибка при добавлении записи лога: " . $e->getMessage());
        }
    }
    
    // Обработка редактирования записи лога
    if ($_POST['action'] === 'edit_log') {
        try {
            // Для логов нет уникального ID, поэтому будем использовать комбинацию payment_id + date + location
            $originalValues = explode('|', $_POST['original_values']);
            
            $stmt = $pdo->prepare("UPDATE minbank_payments_logs SET 
                payment_id = ?, 
                date = ?, 
                location = ?,
                text = ?, 
                ip = ?, 
                browser = ?
                WHERE payment_id = ? AND date = ? AND location = ?");
            
            $stmt->execute([
                $_POST['payment_id'],
                $_POST['date'],
                $_POST['location'],
                $_POST['text'],
                $_POST['ip'],
                $_POST['browser'],
                $originalValues[0],
                $originalValues[1],
                $originalValues[2]
            ]);
            
            header("Location: logs.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Ошибка при обновлении записи лога: " . $e->getMessage());
        }
    }
}

// Пагинация
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Получение списка логов
try {
    $sql = "SELECT * FROM minbank_payments_logs WHERE 1=1 $searchQuery ORDER BY date DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    
    if (!empty($searchQuery)) {
        $stmt->bindValue(':search_value', $searchValue);
    }
    
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получение общего количества записей с учетом поиска
    $countSql = "SELECT COUNT(*) FROM minbank_payments_logs WHERE 1=1 $searchQuery";
    $countStmt = $pdo->prepare($countSql);
    
    if (!empty($searchQuery)) {
        $countStmt->bindValue(':search_value', $searchValue);
    }
    
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);
} catch (PDOException $e) {
    die("Ошибка при получении логов: " . $e->getMessage());
}

// Получение данных для редактирования
$editLogData = null;
if (isset($_GET['edit_id'])) {
    $editParams = explode('|', $_GET['edit_id']);
    if (count($editParams) === 3) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM minbank_payments_logs WHERE payment_id = ? AND date = ? AND location = ?");
            $stmt->execute([$editParams[0], $editParams[1], $editParams[2]]);
            $editLogData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Ошибка при получении данных лога: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Логи платежей - MinBank</title>
  <link rel="stylesheet" href="/css/styles.css">
  <link rel="stylesheet" href="/css/tables.css">
  <link rel="stylesheet" href="/css/modals.css">
</head>
<body>
  <header class="header">
    <div class="logo">MinBank</div>
    <div class="page-title">Логи платежей</div>
    <div class="user-info">
      <span class="user-role">Администратор</span>
      <button class="logout-btn">Выйти</button>
    </div>
  </header>

  <aside class="sidebar">
    <nav>
      <ul>
        <li><a href="payments.php">Платежи</a></li>
        <li class="active"><a href="logs.php">Логи платежей</a></li>
        <li><a href="options.php">Опции платежей</a></li>
        <li><a href="temporary.php">Временные платежи</a></li>
        <li><a href="settings.php">Системные настройки</a></li>
        <li><a href="queries.php">Сохраненные запросы</a></li>
      </ul>
    </nav>
  </aside>

  <main class="content">
    <div class="toolbar">
      <button class="btn btn-add" id="add-log">Добавить запись</button>
      <button class="btn btn-query" id="execute-query">Выполнить SQL запрос</button>
      
      <!-- Форма поиска -->
      <form method="get" class="search-form">
        <div class="search-box">
          <select name="search_field" class="search-field">
            <option value="">Выберите поле</option>
            <option value="payment_id" <?= $searchField === 'payment_id' ? 'selected' : '' ?>>ID платежа</option>
            <option value="date" <?= $searchField === 'date' ? 'selected' : '' ?>>Дата</option>
            <option value="location" <?= $searchField === 'location' ? 'selected' : '' ?>>Локация</option>
            <option value="text" <?= $searchField === 'text' ? 'selected' : '' ?>>Текст</option>
            <option value="ip" <?= $searchField === 'ip' ? 'selected' : '' ?>>IP-адрес</option>
            <option value="browser" <?= $searchField === 'browser' ? 'selected' : '' ?>>Браузер</option>
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
            <a href="logs.php" class="btn-clear-search">Сбросить</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID платежа</th>
            <th>Дата</th>
            <th>Локация</th>
            <th>Текст</th>
            <th>IP-адрес</th>
            <th>Браузер</th>
            <th>Действия</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td><?= htmlspecialchars($log['payment_id']) ?></td>
            <td><?= htmlspecialchars($log['date']) ?></td>
            <td><?= htmlspecialchars($log['location']) ?></td>
            <td><?= htmlspecialchars($log['text']) ?></td>
            <td><?= htmlspecialchars($log['ip']) ?></td>
            <td><?= htmlspecialchars($log['browser']) ?></td>
            <td class="table-actions">
              <a href="logs.php?edit_id=<?= htmlspecialchars($log['payment_id']) ?>|<?= htmlspecialchars($log['date']) ?>|<?= htmlspecialchars($log['location']) ?>" class="btn btn-edit">Ред.</a>
              <button type="button" class="btn btn-delete">Уд.</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination">
      <?php if ($currentPage > 1): ?>
        <a href="logs.php?page=<?= $currentPage - 1 ?><?= !empty($searchField) ? "&search_field=$searchField&search_condition=$searchCondition&search_value=" . urlencode($searchValue) : '' ?>" class="btn-prev">&#9668; Назад</a>
      <?php else: ?>
        <span class="btn-prev disabled">&#9668; Назад</span>
      <?php endif; ?>
      
      <span class="page-info">Страница <?= $currentPage ?> из <?= $totalPages ?></span>
      
      <?php if ($currentPage < $totalPages): ?>
        <a href="logs.php?page=<?= $currentPage + 1 ?><?= !empty($searchField) ? "&search_field=$searchField&search_condition=$searchCondition&search_value=" . urlencode($searchValue) : '' ?>" class="btn-next">Вперед &#9658;</a>
      <?php else: ?>
        <span class="btn-next disabled">Вперед &#9658;</span>
      <?php endif; ?>
    </div>
  </main>

  <!-- Модальное окно добавления лога -->
  <div class="modal" id="log-modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Добавить запись в лог</h2>
      <form method="post" action="logs.php">
        <input type="hidden" name="action" value="add_log">
        <div class="form-group">
          <label for="log-payment-id">ID платежа:</label>
          <input type="text" id="log-payment-id" name="payment_id" required>
        </div>
        
        <div class="form-group">
          <label for="log-date">Дата и время:</label>
          <input type="datetime-local" id="log-date" name="date" required>
        </div>
        
        <div class="form-group">
          <label for="log-location">Локация:</label>
          <input type="text" id="log-location" name="location" required>
        </div>
        
        <div class="form-group">
          <label for="log-text">Текст:</label>
          <textarea id="log-text" name="text" rows="4" required></textarea>
        </div>
        
        <div class="form-group">
          <label for="log-ip">IP-адрес:</label>
          <input type="text" id="log-ip" name="ip">
        </div>
        
        <div class="form-group">
          <label for="log-browser">Браузер:</label>
          <input type="text" id="log-browser" name="browser">
        </div>
        
        <div class="form-actions">
          <button type="submit" class="btn btn-save">Сохранить</button>
          <button type="button" class="btn btn-cancel">Отмена</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Модальное окно редактирования лога -->
  <?php if ($editLogData): ?>
  <div class="modal" id="edit-modal" style="display: flex;">
    <div class="modal-content">
      <span class="close" onclick="document.getElementById('edit-modal').style.display='none'">&times;</span>
      <h2>Редактировать запись лога</h2>
      <form method="post" action="logs.php">
        <input type="hidden" name="action" value="edit_log">
        <input type="hidden" name="original_values" value="<?= htmlspecialchars($editLogData['payment_id']) ?>|<?= htmlspecialchars($editLogData['date']) ?>|<?= htmlspecialchars($editLogData['location']) ?>">
        
        <div class="form-group">
          <label for="edit-log-payment-id">ID платежа:</label>
          <input type="text" id="edit-log-payment-id" name="payment_id" value="<?= htmlspecialchars($editLogData['payment_id']) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="edit-log-date">Дата и время:</label>
          <input type="datetime-local" id="edit-log-date" name="date" value="<?= str_replace(' ', 'T', htmlspecialchars($editLogData['date'])) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="edit-log-location">Локация:</label>
          <input type="text" id="edit-log-location" name="location" value="<?= htmlspecialchars($editLogData['location']) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="edit-log-text">Текст:</label>
          <textarea id="edit-log-text" name="text" rows="4" required><?= htmlspecialchars($editLogData['text']) ?></textarea>
        </div>
        
        <div class="form-group">
          <label for="edit-log-ip">IP-адрес:</label>
          <input type="text" id="edit-log-ip" name="ip" value="<?= htmlspecialchars($editLogData['ip'] ?? '') ?>">
        </div>
        
        <div class="form-group">
          <label for="edit-log-browser">Браузер:</label>
          <input type="text" id="edit-log-browser" name="browser" value="<?= htmlspecialchars($editLogData['browser'] ?? '') ?>">
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
          <option value="1">Логи за последний день</option>
          <option value="2">Ошибки в логах</option>
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
    // Обработка модального окна добавления лога
    document.getElementById('add-log').addEventListener('click', function() {
      document.getElementById('log-modal').style.display = 'flex';
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
    // Обработка удаления записи лога
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const paymentId = row.cells[0].textContent;
            const date = row.cells[1].textContent;
            const location = row.cells[2].textContent;
            
            if (confirm('Вы уверены, что хотите удалить эту запись лога?')) {
                // Создаем скрытую форму для отправки запроса
                const form = document.createElement('form');
                form.method = 'post';
                form.action = 'logs.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_log';
                form.appendChild(actionInput);
                
                const paymentIdInput = document.createElement('input');
                paymentIdInput.type = 'hidden';
                paymentIdInput.name = 'payment_id';
                paymentIdInput.value = paymentId;
                form.appendChild(paymentIdInput);
                
                const dateInput = document.createElement('input');
                dateInput.type = 'hidden';
                dateInput.name = 'date';
                dateInput.value = date;
                form.appendChild(dateInput);
                
                const locationInput = document.createElement('input');
                locationInput.type = 'hidden';
                locationInput.name = 'location';
                locationInput.value = location;
                form.appendChild(locationInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
  </script>
  
</body>
</html>