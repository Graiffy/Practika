document.addEventListener('DOMContentLoaded', function() {
  // Открытие/закрытие модальных окон
  const modals = {
    payment: document.getElementById('payment-modal'),
    query: document.getElementById('query-modal')
  };
  
  const buttons = {
    addPayment: document.getElementById('add-payment'),
    executeQuery: document.getElementById('execute-query')
  };
  
  // Открытие модального окна добавления платежа
  if (buttons.addPayment) {
    buttons.addPayment.addEventListener('click', () => {
      modals.payment.style.display = 'flex';
    });
  }
  
  // Открытие модального окна SQL запроса
  if (buttons.executeQuery) {
    buttons.executeQuery.addEventListener('click', () => {
      modals.query.style.display = 'flex';
    });
  }
  
  // Закрытие модальных окон по клику на крестик
  document.querySelectorAll('.close').forEach(closeBtn => {
    closeBtn.addEventListener('click', function() {
      this.closest('.modal').style.display = 'none';
    });
  });
  
  // Закрытие модальных окон по клику вне области контента
  document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
      if (e.target === this) {
        this.style.display = 'none';
      }
    });
  });
  
  // Загрузка сохраненных запросов при выборе из списка
  const savedQueries = document.getElementById('saved-queries');
  const sqlQuery = document.getElementById('sql-query');
  
  if (savedQueries && sqlQuery) {
    savedQueries.addEventListener('change', function() {
      if (this.value) {
        // Здесь должна быть загрузка запроса из базы данных
        // Временная заглушка
        const queries = {
          '1': 'SELECT * FROM minbank_payments WHERE status = 1 ORDER BY createdDate DESC LIMIT 100',
          '2': 'SELECT * FROM minbank_payments WHERE status = 8 ORDER BY createdDate DESC',
          '3': 'SELECT * FROM minbank_payments WHERE status = 0 AND createdDate < NOW() - INTERVAL 1 DAY'
        };
        
        sqlQuery.value = queries[this.value] || '';
      }
    });
  }
  
  // Выход из системы
  const logoutBtn = document.querySelector('.logout-btn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function() {
      // Здесь должен быть запрос на сервер для выхода
      window.location.href = 'auth.php';
    });
  }
});