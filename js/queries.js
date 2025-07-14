document.addEventListener('DOMContentLoaded', function() {
  // Открытие модального окна добавления запроса
  const addQueryBtn = document.getElementById('add-query');
  const queryModal = document.getElementById('query-edit-modal');
  
  if (addQueryBtn && queryModal) {
    addQueryBtn.addEventListener('click', () => {
      // Сброс формы перед открытием
      const form = document.getElementById('query-form');
      if (form) form.reset();
      
      // Установка заголовка
      const title = queryModal.querySelector('h2');
      if (title) title.textContent = 'Добавить новый запрос';
      
      queryModal.style.display = 'flex';
    });
  }
  
  // Обработка редактирования запроса
  document.querySelectorAll('.btn-edit').forEach(btn => {
    btn.addEventListener('click', function() {
      // Здесь должна быть загрузка данных запроса для редактирования
      // Временная заглушка
      const row = this.closest('tr');
      const name = row.querySelector('td:first-child').textContent;
      const sql = row.querySelector('td:nth-child(2)').textContent;
      
      document.getElementById('query-name').value = name;
      document.getElementById('query-sql').value = sql;
      
      // Установка заголовка
      const title = queryModal.querySelector('h2');
      if (title) title.textContent = 'Редактировать запрос';
      
      queryModal.style.display = 'flex';
    });
  });
  
  // Выполнение сохраненного запроса
  document.querySelectorAll('.btn-query').forEach(btn => {
    btn.addEventListener('click', function() {
      const queryName = this.closest('tr').querySelector('td:first-child').textContent;
      if (confirm(`Выполнить запрос "${queryName}"?`)) {
        // Здесь должен быть запрос на сервер для выполнения
        console.log(`Выполнение запроса: ${queryName}`);
      }
    });
  });
  
  // Отправка формы запроса
  const queryForm = document.getElementById('query-form');
  if (queryForm) {
    queryForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const name = document.getElementById('query-name').value.trim();
      const sql = document.getElementById('query-sql').value.trim();
      
      if (name && sql) {
        // Здесь должен быть запрос на сервер для сохранения
        console.log(`Сохранение запроса: ${name}`);
        
        // Закрытие модального окна после сохранения
        document.getElementById('query-edit-modal').style.display = 'none';
        
        // Обновление таблицы (в реальном проекте - AJAX запрос)
        alert('Запрос успешно сохранен');
      } else {
        alert('Заполните все обязательные поля');
      }
    });
  }
  
  // Поиск по сохраненным запросам
  const searchInput = document.getElementById('search-query');
  const searchBtn = document.querySelector('.btn-search');
  
  if (searchBtn && searchInput) {
    searchBtn.addEventListener('click', function() {
      const searchTerm = searchInput.value.trim().toLowerCase();
      if (searchTerm) {
        // Фильтрация таблицы на клиенте (в реальном проекте - AJAX запрос)
        document.querySelectorAll('.data-table tbody tr').forEach(row => {
          const name = row.querySelector('td:first-child').textContent.toLowerCase();
          const sql = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
          
          if (name.includes(searchTerm) || sql.includes(searchTerm)) {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        });
      } else {
        // Показать все строки, если поисковой запрос пуст
        document.querySelectorAll('.data-table tbody tr').forEach(row => {
          row.style.display = '';
        });
      }
    });
    
    // Поиск при нажатии Enter
    searchInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        searchBtn.click();
      }
    });
  }
});