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
        case 'like':
            $searchQuery = " AND $searchField LIKE :search_value";
            $searchValue = "%$searchValue%";
            break;
    }
}

// Обработка добавления нового запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_query') {
        try {
            $stmt = $pdo->prepare("INSERT INTO saved_queries (name, sql_query) VALUES (?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['sql_query']
            ]);
            
            header("Location: queries.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Ошибка при добавлении запроса: " . $e->getMessage());
        }
    }
    
    // Обработка редактирования запроса
    if ($_POST['action'] === 'edit_query') {
        try {
            $stmt = $pdo->prepare("UPDATE saved_queries SET name = ?, sql_query = ? WHERE id = ?");
            $stmt->execute([
                $_POST['name'],
                $_POST['sql_query'],
                $_POST['id']
            ]);
            
            header("Location: queries.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Ошибка при обновлении запроса: " . $e->getMessage());
        }
    }
    
    // Обработка удаления запроса
    if ($_POST['action'] === 'delete_query') {
        try {
            $stmt = $pdo->prepare("DELETE FROM saved_queries WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            header("Location: queries.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Ошибка при удалении запроса: " . $e->getMessage());
        }
    }
    
    // Обработка выполнения запроса
    if ($_POST['action'] === 'execute_query') {
        try {
            $stmt = $pdo->prepare("SELECT sql_query FROM saved_queries WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $query = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($query) {
                $result = $pdo->query($query['sql_query']);
                $queryResult = $result->fetchAll(PDO::FETCH_ASSOC);
                
                // Сохраняем результат в сессии для отображения
                session_start();
                $_SESSION['query_result'] = $queryResult;
                $_SESSION['query_sql'] = $query['sql_query'];
                
                header("Location: queries.php?executed=1");
                exit();
            }
        } catch (PDOException $e) {
            die("Ошибка при выполнении запроса: " . $e->getMessage());
        }
    }
}

// Пагинация
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Получение списка запросов
try {
    $sql = "SELECT * FROM saved_queries WHERE 1=1 $searchQuery ORDER BY name LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    
    if (!empty($searchQuery)) {
        $stmt->bindValue(':search_value', $searchValue);
    }
    
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $queries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получение общего количества записей
    $countSql = "SELECT COUNT(*) FROM saved_queries WHERE 1=1 $searchQuery";
    $countStmt = $pdo->prepare($countSql);
    
    if (!empty($searchQuery)) {
        $countStmt->bindValue(':search_value', $searchValue);
    }
    
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);
} catch (PDOException $e) {
    die("Ошибка при получении запросов: " . $e->getMessage());
}

// Получение данных для редактирования
$editQueryData = null;
if (isset($_GET['edit_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM saved_queries WHERE id = ?");
        $stmt->execute([$_GET['edit_id']]);
        $editQueryData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Ошибка при получении данных запроса: " . $e->getMessage());
    }
}

// Проверяем, есть ли результат выполнения запроса
$queryResult = [];
$querySql = '';
if (isset($_GET['executed']) && session_status() === PHP_SESSION_ACTIVE) {
    session_start();
    if (isset($_SESSION['query_result'])) {
        $queryResult = $_SESSION['query_result'];
        $querySql = $_SESSION['query_sql'];
        unset($_SESSION['query_result']);
        unset($_SESSION['query_sql']);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Сохраненные запросы - MinBank</title>
  <link rel="stylesheet" href="/css/styles.css">
  <link rel="stylesheet" href="/css/tables.css">
  <link rel="stylesheet" href="/css/modals.css">
</head>
<body>
  <header class="header">
    <div class="logo">MinBank</div>
    <div class="page-title">Сохраненные запросы</div>
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
        <li><a href="temporary.php">Временные платежи</a></li>
        <li><a href="settings.php">Системные настройки</a></li>
        <li class="active"><a href="queries.php">Сохраненные запросы</a></li>
      </ul>
    </nav>
  </aside>

  <main class="content">
    <div class="toolbar">
      <button class="btn btn-add" id="add-query">Добавить запрос</button>
      <button class="btn btn-query" id="execute-query">Выполнить SQL запрос</button>
      
      <!-- Форма поиска -->
      <form method="get" class="search-form">
        <div class="search-box">
          <select name="search_field" class="search-field">
            <option value="">Выберите поле</option>
            <option value="name" <?= $searchField === 'name' ? 'selected' : '' ?>>Название</option>
            <option value="sql_query" <?= $searchField === 'sql_query' ? 'selected' : '' ?>>SQL запрос</option>
          </select>
          
          <select name="search_condition" class="search-condition">
            <option value="">Условие</option>
            <option value="equals" <?= $searchCondition === 'equals' ? 'selected' : '' ?>>Равенство</option>
            <option value="like" <?= $searchCondition === 'like' ? 'selected' : '' ?>>LIKE</option>
          </select>
          
          <input type="text" name="search_value" placeholder="Значение" value="<?= htmlspecialchars($searchValue) ?>">
          <button type="submit" class="btn-search">Найти</button>
          <?php if (!empty($searchField) || !empty($searchCondition) || !empty($searchValue)): ?>
            <a href="queries.php" class="btn-clear-search">Сбросить</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Название</th>
            <th>SQL запрос</th>
            <th>Действия</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($queries as $query): ?>
          <tr>
            <td><?= htmlspecialchars($query['id']) ?></td>
            <td><?= htmlspecialchars($query['name']) ?></td>
            <td class="sql-query"><?= htmlspecialchars($query['sql_query']) ?></td>
            <td class="table-actions">
              <a href="queries.php?edit_id=<?= htmlspecialchars($query['id']) ?>" class="btn btn-edit">Ред.</a>
              <button type="button" class="btn btn-delete" data-id="<?= htmlspecialchars($query['id']) ?>">Уд.</button>
              <form method="post" action="queries.php" style="display: inline;">
                <input type="hidden" name="action" value="execute_query">
                <input type="hidden" name="id" value="<?= htmlspecialchars($query['id']) ?>">
                <button type="submit" class="btn btn-query">Выполнить</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination">
      <?php if ($currentPage > 1): ?>
        <a href="queries.php?page=<?= $currentPage - 1 ?><?= !empty($searchField) ? "&search_field=$searchField&search_condition=$searchCondition&search_value=" . urlencode($searchValue) : '' ?>" class="btn-prev">&#9668; Назад</a>
      <?php else: ?>
        <span class="btn-prev disabled">&#9668; Назад</span>
      <?php endif; ?>
      
      <span class="page-info">Страница <?= $currentPage ?> из <?= $totalPages ?></span>
      
      <?php if ($currentPage < $totalPages): ?>
        <a href="queries.php?page=<?= $currentPage + 1 ?><?= !empty($searchField) ? "&search_field=$searchField&search_condition=$searchCondition&search_value=" . urlencode($searchValue) : '' ?>" class="btn-next">Вперед &#9658;</a>
      <?php else: ?>
        <span class="btn-next disabled">Вперед &#9658;</span>
      <?php endif; ?>
    </div>

    <?php if (!empty($queryResult)): ?>
    <div class="query-result-container">
      <h3>Результат выполнения запроса</h3>
      <div class="executed-query">
        <strong>SQL запрос:</strong>
        <pre><?= htmlspecialchars($querySql) ?></pre>
      </div>
      
      <?php if (!empty($queryResult)): ?>
      <div class="table-container">
        <table class="data-table">
          <thead>
            <tr>
              <?php foreach (array_keys($queryResult[0]) as $column): ?>
                <th><?= htmlspecialchars($column) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($queryResult as $row): ?>
            <tr>
              <?php foreach ($row as $value): ?>
                <td><?= htmlspecialchars($value) ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p>Запрос не вернул результатов</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </main>

  <!-- Модальное окно добавления запроса -->
  <div class="modal" id="query-modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Добавить новый запрос</h2>
      <form method="post" action="queries.php">
        <input type="hidden" name="action" value="add_query">
        <div class="form-group">
          <label for="query-name">Название запроса:</label>
          <input type="text" id="query-name" name="name" required maxlength="255">
        </div>
        
        <div class="form-group">
          <label for="query-sql">SQL запрос:</label>
          <textarea id="query-sql" name="sql_query" rows="10" required></textarea>
        </div>
        
        <div class="form-actions">
          <button type="submit" class="btn btn-save">Сохранить</button>
          <button type="button" class="btn btn-cancel">Отмена</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Модальное окно редактирования запроса -->
  <?php if ($editQueryData): ?>
  <div class="modal" id="edit-modal" style="display: flex;">
    <div class="modal-content">
      <span class="close" onclick="document.getElementById('edit-modal').style.display='none'">&times;</span>
      <h2>Редактировать запрос</h2>
      <form method="post" action="queries.php">
        <input type="hidden" name="action" value="edit_query">
        <input type="hidden" name="id" value="<?= htmlspecialchars($editQueryData['id']) ?>">
        
        <div class="form-group">
          <label for="edit-query-name">Название запроса:</label>
          <input type="text" id="edit-query-name" name="name" value="<?= htmlspecialchars($editQueryData['name']) ?>" required maxlength="255">
        </div>
        
        <div class="form-group">
          <label for="edit-query-sql">SQL запрос:</label>
          <textarea id="edit-query-sql" name="sql_query" rows="10" required><?= htmlspecialchars($editQueryData['sql_query']) ?></textarea>
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
  <div class="modal" id="sql-query-modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Выполнить SQL запрос</h2>
      
      <div class="form-group">
        <label for="sql-query-text">SQL запрос:</label>
        <textarea id="sql-query-text" rows="8"></textarea>
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
    // Обработка модального окна добавления запроса
    document.getElementById('add-query').addEventListener('click', function() {
      document.getElementById('query-modal').style.display = 'flex';
    });
    
    // Обработка удаления запроса
    document.querySelectorAll('.btn-delete').forEach(btn => {
      btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        
        if (confirm('Вы уверены, что хотите удалить этот запрос?')) {
          // Создаем скрытую форму для отправки запроса
          const form = document.createElement('form');
          form.method = 'post';
          form.action = 'queries.php';
          
          const actionInput = document.createElement('input');
          actionInput.type = 'hidden';
          actionInput.name = 'action';
          actionInput.value = 'delete_query';
          form.appendChild(actionInput);
          
          const idInput = document.createElement('input');
          idInput.type = 'hidden';
          idInput.name = 'id';
          idInput.value = id;
          form.appendChild(idInput);
          
          document.body.appendChild(form);
          form.submit();
        }
      });
    });
    
    // Обработка модального окна SQL запроса
    document.getElementById('execute-query').addEventListener('click', function() {
      document.getElementById('sql-query-modal').style.display = 'flex';
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