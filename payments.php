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
// Обработка удаления платежа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_payment') {
    try {
        $pdo->beginTransaction();
        
        // Удаляем связанные записи
        $stmt = $pdo->prepare("DELETE FROM minbank_payments_logs WHERE payment_id = ?");
        $stmt->execute([$_POST['payment_id']]);
        
        $stmt = $pdo->prepare("DELETE FROM minbank_payments_options WHERE payment_id = ?");
        $stmt->execute([$_POST['payment_id']]);
        
        $stmt = $pdo->prepare("DELETE FROM minbank_temporary_payments WHERE payment_id = ?");
        $stmt->execute([$_POST['payment_id']]);
        
        // Удаляем сам платеж
        $stmt = $pdo->prepare("DELETE FROM minbank_payments WHERE payment_id = ?");
        $stmt->execute([$_POST['payment_id']]);
        
        $pdo->commit();
        header("Location: payments.php?success=1");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Ошибка при удалении платежа: " . $e->getMessage());
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

// Обработка добавления нового платежа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_payment') {
        try {
            $stmt = $pdo->prepare("INSERT INTO minbank_payments 
                (payment_id, createdDate, paidDate, exportedDate, status, 
                startAmount, paidAmount, refundAmount, order_id, session_id, 
                ip, checkedDate, sended, sbp_flag) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $_POST['payment_id'],
                $_POST['created_date'],
                $_POST['paid_date'] ?: null,
                $_POST['exported_date'] ?: null,
                $_POST['status'],
                $_POST['start_amount'] * 100, // Конвертируем в копейки
                $_POST['paid_amount'] * 100,
                $_POST['refund_amount'] * 100,
                $_POST['order_id'],
                $_POST['session_id'],
                $_POST['ip'],
                $_POST['checked_date'] ?: null,
                isset($_POST['sended']) ? 1 : 0,
                isset($_POST['sbp_flag']) ? 1 : 0
            ]);
            
            header("Location: payments.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Ошибка при добавлении платежа: " . $e->getMessage());
        }
    }
    
    // Обработка редактирования платежа
    if ($_POST['action'] === 'edit_payment') {
        try {
            $stmt = $pdo->prepare("UPDATE minbank_payments SET 
                createdDate = ?, 
                paidDate = ?, 
                exportedDate = ?,
                status = ?, 
                startAmount = ?, 
                paidAmount = ?, 
                refundAmount = ?,
                order_id = ?, 
                session_id = ?, 
                ip = ?,
                checkedDate = ?,
                sended = ?,
                sbp_flag = ?
                WHERE payment_id = ?");
            
            $stmt->execute([
                $_POST['created_date'],
                $_POST['paid_date'] ?: null,
                $_POST['exported_date'] ?: null,
                $_POST['status'],
                $_POST['start_amount'] * 100, // Конвертируем в копейки
                $_POST['paid_amount'] * 100,
                $_POST['refund_amount'] * 100,
                $_POST['order_id'],
                $_POST['session_id'],
                $_POST['ip'],
                $_POST['checked_date'] ?: null,
                isset($_POST['sended']) ? 1 : 0,
                isset($_POST['sbp_flag']) ? 1 : 0,
                $_POST['payment_id']
            ]);
            
            header("Location: payments.php?success=1");
            exit();
        } catch (PDOException $e) {
            die("Ошибка при обновлении платежа: " . $e->getMessage());
        }
    }
}

// Вспомогательные функции
function getStatusText($status) {
    switch ($status) {
        case 0: return 'Ожидает оплаты';
        case 1: return 'Успех';
        case 8: return 'Возврат';
        default: return 'Неизвестно';
    }
}

function getStatusClass($status) {
    switch ($status) {
        case 0: return 'status-pending';
        case 1: return 'status-success';
        case 8: return 'status-refund';
        default: return '';
    }
}

function formatAmount($amount) {
    return number_format($amount / 100, 2, '.', ' ') . ' руб.';
}

function formatBoolean($value) {
    return $value == 1 ? 'Да' : 'Нет';
}

function formatDate($date) {
    return $date ? date('d.m.Y H:i', strtotime($date)) : '-';
}

// Пагинация
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Получение списка платежей
try {
    $sql = "SELECT * FROM minbank_payments WHERE 1=1 $searchQuery ORDER BY createdDate DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    
    if (!empty($searchQuery)) {
        $stmt->bindValue(':search_value', $searchValue);
    }
    
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получение общего количества записей с учетом поиска
    $countSql = "SELECT COUNT(*) FROM minbank_payments WHERE 1=1 $searchQuery";
    $countStmt = $pdo->prepare($countSql);
    
    if (!empty($searchQuery)) {
        $countStmt->bindValue(':search_value', $searchValue);
    }
    
    $countStmt->execute();
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);
} catch (PDOException $e) {
    die("Ошибка при получении платежей: " . $e->getMessage());
}

// Получение ID выбранного платежа через чекбокс
$selectedPaymentId = null;
if (isset($_POST['selected_payment'])) {
    $selectedPaymentId = $_POST['selected_payment'];
}

// Получение связанных данных для выбранного платежа
$logs = [];
$options = [];
$temporary = [];

if ($selectedPaymentId) {
    try {
        // Логи платежа
        $stmt = $pdo->prepare("SELECT * FROM minbank_payments_logs WHERE payment_id = ? ORDER BY date DESC");
        $stmt->execute([$selectedPaymentId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Опции платежа
        $stmt = $pdo->prepare("SELECT * FROM minbank_payments_options WHERE payment_id = ?");
        $stmt->execute([$selectedPaymentId]);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Временные платежи
        $stmt = $pdo->prepare("SELECT * FROM minbank_temporary_payments WHERE payment_id = ?");
        $stmt->execute([$selectedPaymentId]);
        $temporary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Ошибка при получении связанных данных: " . $e->getMessage());
    }
}

// Получение данных для редактирования
$editPaymentData = null;
if (isset($_GET['edit_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM minbank_payments WHERE payment_id = ?");
        $stmt->execute([$_GET['edit_id']]);
        $editPaymentData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Ошибка при получении данных платежа: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Платежи - MinBank</title>
  <link rel="stylesheet" href="/css/styles.css">
  <link rel="stylesheet" href="/css/tables.css">
  <link rel="stylesheet" href="/css/modals.css">
</head>
<body>
  <header class="header">
    <div class="logo">MinBank</div>
    <div class="page-title">Платежи</div>
    <div class="user-info">
      <span class="user-role">Администратор</span>
      <button class="logout-btn">Выйти</button>
    </div>
  </header>

  <aside class="sidebar">
    <nav>
      <ul>
        <li class="active"><a href="payments.php">Платежи</a></li>
        <li><a href="logs.php">Логи платежей</a></li>
        <li><a href="options.php">Опции платежей</a></li>
        <li><a href="temporary.php">Временные платежи</a></li>
        <li><a href="settings.php">Системные настройки</a></li>
        <li><a href="queries.php">Сохраненные запросы</a></li>
      </ul>
    </nav>
  </aside>

  <main class="content">
    <div class="toolbar">
      <button class="btn btn-add" id="add-payment">Добавить платеж</button>
      <button class="btn btn-query" id="execute-query">Выполнить SQL запрос</button>
      
      <!-- Форма поиска -->
      <form method="get" class="search-form">
        <div class="search-box">
          <select name="search_field" class="search-field">
            <option value="">Выберите поле</option>
            <option value="payment_id" <?= $searchField === 'payment_id' ? 'selected' : '' ?>>ID платежа</option>
            <option value="createdDate" <?= $searchField === 'createdDate' ? 'selected' : '' ?>>Дата создания</option>
            <option value="paidDate" <?= $searchField === 'paidDate' ? 'selected' : '' ?>>Дата оплаты</option>
            <option value="exportedDate" <?= $searchField === 'exportedDate' ? 'selected' : '' ?>>Дата экспорта</option>
            <option value="status" <?= $searchField === 'status' ? 'selected' : '' ?>>Статус</option>
            <option value="startAmount" <?= $searchField === 'startAmount' ? 'selected' : '' ?>>Начальная сумма</option>
            <option value="paidAmount" <?= $searchField === 'paidAmount' ? 'selected' : '' ?>>Оплаченная сумма</option>
            <option value="refundAmount" <?= $searchField === 'refundAmount' ? 'selected' : '' ?>>Сумма возврата</option>
            <option value="order_id" <?= $searchField === 'order_id' ? 'selected' : '' ?>>ID заказа</option>
            <option value="session_id" <?= $searchField === 'session_id' ? 'selected' : '' ?>>ID сессии</option>
            <option value="ip" <?= $searchField === 'ip' ? 'selected' : '' ?>>IP-адрес</option>
            <option value="checkedDate" <?= $searchField === 'checkedDate' ? 'selected' : '' ?>>Дата проверки</option>
            <option value="sended" <?= $searchField === 'sended' ? 'selected' : '' ?>>Отправлено</option>
            <option value="sbp_flag" <?= $searchField === 'sbp_flag' ? 'selected' : '' ?>>SBP</option>
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
            <a href="payments.php" class="btn-clear-search">Сбросить</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <form method="post" id="payment-form">
      <div class="main-content-wrapper">
        <div class="payments-table">
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Выбрать</th>
                  <th>ID платежа</th>
                  <th>Дата создания</th>
                  <th>Дата оплаты</th>
                  <th>Дата экспорта</th>
                  <th>Статус</th>
                  <th>Начальная сумма</th>
                  <th>Оплаченная сумма</th>
                  <th>Сумма возврата</th>
                  <th>ID заказа</th>
                  <th>ID сессии</th>
                  <th>IP-адрес</th>
                  <th>Дата проверки</th>
                  <th>Отправлено</th>
                  <th>SBP</th>
                  <th>Действия</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $payment): ?>
                <tr class="<?= $selectedPaymentId === $payment['payment_id'] ? 'selected' : '' ?>">
                  <td>
                    <input type="radio" name="selected_payment" value="<?= htmlspecialchars($payment['payment_id']) ?>" 
                           <?= $selectedPaymentId === $payment['payment_id'] ? 'checked' : '' ?> 
                           onchange="this.form.submit()">
                  </td>
                  <td><?= htmlspecialchars($payment['payment_id']) ?></td>
                  <td><?= formatDate($payment['createdDate']) ?></td>
                  <td><?= formatDate($payment['paidDate']) ?></td>
                  <td><?= formatDate($payment['exportedDate']) ?></td>
                  <td class="<?= getStatusClass($payment['status']) ?>">
                    <?= getStatusText($payment['status']) ?>
                  </td>
                  <td><?= formatAmount($payment['startAmount']) ?></td>
                  <td><?= formatAmount($payment['paidAmount']) ?></td>
                  <td><?= formatAmount($payment['refundAmount']) ?></td>
                  <td><?= htmlspecialchars($payment['order_id'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($payment['session_id'] ?? '-') ?></td>
                  <td><?= htmlspecialchars($payment['ip'] ?? '-') ?></td>
                  <td><?= formatDate($payment['checkedDate']) ?></td>
                  <td><?= formatBoolean($payment['sended']) ?></td>
                  <td><?= formatBoolean($payment['sbp_flag']) ?></td>
                  <td class="table-actions">
                    <a href="payments.php?edit_id=<?= htmlspecialchars($payment['payment_id']) ?>" class="btn btn-edit">Ред.</a>
                    <button type="button" class="btn btn-delete">Уд.</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="pagination">
            <?php if ($currentPage > 1): ?>
              <a href="payments.php?page=<?= $currentPage - 1 ?><?= !empty($searchField) ? "&search_field=$searchField&search_condition=$searchCondition&search_value=" . urlencode($searchValue) : '' ?>" class="btn-prev">&#9668; Назад</a>
            <?php else: ?>
              <span class="btn-prev disabled">&#9668; Назад</span>
            <?php endif; ?>
            
            <span class="page-info">Страница <?= $currentPage ?> из <?= $totalPages ?></span>
            
            <?php if ($currentPage < $totalPages): ?>
              <a href="payments.php?page=<?= $currentPage + 1 ?><?= !empty($searchField) ? "&search_field=$searchField&search_condition=$searchCondition&search_value=" . urlencode($searchValue) : '' ?>" class="btn-next">Вперед &#9658;</a>
            <?php else: ?>
              <span class="btn-next disabled">Вперед &#9658;</span>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($selectedPaymentId): ?>
        <div class="related-tables">
          <!-- Логи платежа -->
          <div class="related-table">
            <h3>Логи платежа</h3>
            <div class="table-container">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Дата</th>
                    <th>Локация</th>
                    <th>Текст</th>
                    <th>IP-адрес</th>
                    <th>Браузер</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($logs as $log): ?>
                  <tr>
                    <td><?= htmlspecialchars($log['date']) ?></td>
                    <td><?= htmlspecialchars($log['location']) ?></td>
                    <td><?= htmlspecialchars($log['text']) ?></td>
                    <td><?= htmlspecialchars($log['ip']) ?></td>
                    <td><?= htmlspecialchars($log['browser']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($logs)): ?>
                  <tr>
                    <td colspan="5" class="no-data">Нет данных</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Опции платежа -->
          <div class="related-table">
            <h3>Опции платежа</h3>
            <div class="table-container">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Ключ</th>
                    <th>Значение</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($options as $option): ?>
                  <tr>
                    <td><?= htmlspecialchars($option['key']) ?></td>
                    <td><?= htmlspecialchars($option['value']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($options)): ?>
                  <tr>
                    <td colspan="2" class="no-data">Нет данных</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Временные платежи -->
          <div class="related-table">
            <h3>Временные платежи</h3>
            <div class="table-container">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>JSON данные</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($temporary as $temp): ?>
                  <tr>
                    <td class="json-data"><?= htmlspecialchars($temp['json']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($temporary)): ?>
                  <tr>
                    <td class="no-data">Нет данных</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </form>
  </main>

  <!-- Модальное окно добавления платежа -->
  <div class="modal" id="payment-modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Добавить новый платеж</h2>
      <form method="post" action="payments.php">
        <input type="hidden" name="action" value="add_payment">
        <div class="form-group">
          <label for="payment-id">ID платежа:</label>
          <input type="text" id="payment-id" name="payment_id" required>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="created-date">Дата создания:</label>
            <input type="datetime-local" id="created-date" name="created_date" required>
          </div>
          
          <div class="form-group">
            <label for="paid-date">Дата оплаты:</label>
            <input type="datetime-local" id="paid-date" name="paid_date">
          </div>
          
          <div class="form-group">
            <label for="exported-date">Дата экспорта:</label>
            <input type="datetime-local" id="exported-date" name="exported_date">
          </div>
        </div>
        
        <div class="form-group">
          <label for="status">Статус:</label>
          <select id="status" name="status" required>
            <option value="0">Ожидает оплаты</option>
            <option value="1">Успех</option>
            <option value="8">Возврат</option>
          </select>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="start-amount">Начальная сумма (руб):</label>
            <input type="number" step="0.01" id="start-amount" name="start_amount" required>
          </div>
          
          <div class="form-group">
            <label for="paid-amount">Оплаченная сумма (руб):</label>
            <input type="number" step="0.01" id="paid-amount" name="paid_amount" required>
          </div>
          
          <div class="form-group">
            <label for="refund-amount">Сумма возврата (руб):</label>
            <input type="number" step="0.01" id="refund-amount" name="refund_amount" required>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="order-id">ID заказа:</label>
            <input type="text" id="order-id" name="order_id">
          </div>
          
          <div class="form-group">
            <label for="session-id">ID сессии:</label>
            <input type="text" id="session-id" name="session_id">
          </div>
          
          <div class="form-group">
            <label for="ip">IP-адрес:</label>
            <input type="text" id="ip" name="ip">
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="checked-date">Дата проверки:</label>
            <input type="datetime-local" id="checked-date" name="checked_date">
          </div>
          
          <div class="form-group">
            <label>
              <input type="checkbox" name="sended" value="1"> Отправлено
            </label>
          </div>
          
          <div class="form-group">
            <label>
              <input type="checkbox" name="sbp_flag" value="1"> СБП
            </label>
          </div>
        </div>
        
        <div class="form-actions">
          <button type="submit" class="btn btn-save">Сохранить</button>
          <button type="button" class="btn btn-cancel">Отмена</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Модальное окно редактирования платежа -->
  <?php if ($editPaymentData): ?>
  <div class="modal" id="edit-modal" style="display: flex;">
    <div class="modal-content">
      <span class="close" onclick="document.getElementById('edit-modal').style.display='none'">&times;</span>
      <h2>Редактировать платеж</h2>
      <form method="post" action="payments.php">
        <input type="hidden" name="action" value="edit_payment">
        <input type="hidden" name="payment_id" value="<?= htmlspecialchars($editPaymentData['payment_id']) ?>">
        
        <div class="form-group">
          <label>ID платежа:</label>
          <div class="form-control-static"><?= htmlspecialchars($editPaymentData['payment_id']) ?></div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="edit-created-date">Дата создания:</label>
            <input type="datetime-local" id="edit-created-date" name="created_date" 
                   value="<?= str_replace(' ', 'T', htmlspecialchars($editPaymentData['createdDate'])) ?>" required>
          </div>
          
          <div class="form-group">
            <label for="edit-paid-date">Дата оплаты:</label>
            <input type="datetime-local" id="edit-paid-date" name="paid_date" 
                   value="<?= $editPaymentData['paidDate'] ? str_replace(' ', 'T', htmlspecialchars($editPaymentData['paidDate'])) : '' ?>">
          </div>
          
          <div class="form-group">
            <label for="edit-exported-date">Дата экспорта:</label>
            <input type="datetime-local" id="edit-exported-date" name="exported_date" 
                   value="<?= $editPaymentData['exportedDate'] ? str_replace(' ', 'T', htmlspecialchars($editPaymentData['exportedDate'])) : '' ?>">
          </div>
        </div>
        
        <div class="form-group">
          <label for="edit-status">Статус:</label>
          <select id="edit-status" name="status" required>
            <option value="0" <?= $editPaymentData['status'] == 0 ? 'selected' : '' ?>>Ожидает оплаты</option>
            <option value="1" <?= $editPaymentData['status'] == 1 ? 'selected' : '' ?>>Успех</option>
            <option value="8" <?= $editPaymentData['status'] == 8 ? 'selected' : '' ?>>Возврат</option>
          </select>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="edit-start-amount">Начальная сумма (руб):</label>
            <input type="number" step="0.01" id="edit-start-amount" name="start_amount" 
                   value="<?= $editPaymentData['startAmount'] / 100 ?>" required>
          </div>
          
          <div class="form-group">
            <label for="edit-paid-amount">Оплаченная сумма (руб):</label>
            <input type="number" step="0.01" id="edit-paid-amount" name="paid_amount" 
                   value="<?= $editPaymentData['paidAmount'] / 100 ?>" required>
          </div>
          
          <div class="form-group">
            <label for="edit-refund-amount">Сумма возврата (руб):</label>
            <input type="number" step="0.01" id="edit-refund-amount" name="refund_amount" 
                   value="<?= $editPaymentData['refundAmount'] / 100 ?>" required>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="edit-order-id">ID заказа:</label>
            <input type="text" id="edit-order-id" name="order_id" 
                   value="<?= htmlspecialchars($editPaymentData['order_id'] ?? '') ?>">
          </div>
          
          <div class="form-group">
            <label for="edit-session-id">ID сессии:</label>
            <input type="text" id="edit-session-id" name="session_id" 
                   value="<?= htmlspecialchars($editPaymentData['session_id'] ?? '') ?>">
          </div>
          
          <div class="form-group">
            <label for="edit-ip">IP-адрес:</label>
            <input type="text" id="edit-ip" name="ip" 
                   value="<?= htmlspecialchars($editPaymentData['ip'] ?? '') ?>">
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label for="edit-checked-date">Дата проверки:</label>
            <input type="datetime-local" id="edit-checked-date" name="checked_date" 
                   value="<?= $editPaymentData['checkedDate'] ? str_replace(' ', 'T', htmlspecialchars($editPaymentData['checkedDate'])) : '' ?>">
          </div>
          
          <div class="form-group">
            <label>
              <input type="checkbox" name="sended" value="1" <?= $editPaymentData['sended'] == 1 ? 'checked' : '' ?>> 
              Отправлено
            </label>
          </div>
          
          <div class="form-group">
            <label>
              <input type="checkbox" name="sbp_flag" value="1" <?= $editPaymentData['sbp_flag'] == 1 ? 'checked' : '' ?>> 
              СБП
            </label>
          </div>
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
          <option value="1">Последние успешные платежи</option>
          <option value="2">Платежи на возврат</option>
          <option value="3">Неоплаченные платежи</option>
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
    // Обработка модального окна добавления платежа
    document.getElementById('add-payment').addEventListener('click', function() {
      document.getElementById('payment-modal').style.display = 'flex';
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
    // Обработка удаления платежа
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const paymentId = row.querySelector('input[name="selected_payment"]').value;
            
            if (confirm('Вы уверены, что хотите удалить этот платеж и все связанные данные?')) {
                // Создаем скрытую форму для отправки запроса
                const form = document.createElement('form');
                form.method = 'post';
                form.action = 'payments.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_payment';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'payment_id';
                idInput.value = paymentId;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
  </script>
  
</body>
</html>