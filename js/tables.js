document.addEventListener('DOMContentLoaded', function() {
  // Пагинация
  const prevBtn = document.querySelector('.btn-prev');
  const nextBtn = document.querySelector('.btn-next');
  const pageInfo = document.querySelector('.page-info');
  
  let currentPage = 1;
  const totalPages = 5; // Это значение должно приходить с сервера
  
  // Обновление информации о странице
  function updatePageInfo() {
    if (pageInfo) {
      pageInfo.textContent = `Страница ${currentPage} из ${totalPages}`;
    }
  }
  
  // Кнопка "Назад"
  if (prevBtn) {
    prevBtn.addEventListener('click', function() {
      if (currentPage > 1) {
        currentPage--;
        updatePageInfo();
        // Здесь должен быть запрос на сервер для загрузки предыдущей страницы
      }
    });
  }
  
  // Кнопка "Вперед"
  if (nextBtn) {
    nextBtn.addEventListener('click', function() {
      if (currentPage < totalPages) {
        currentPage++;
        updatePageInfo();
        // Здесь должен быть запрос на сервер для загрузки следующей страницы
      }
    });
  }
  
  // Поиск
  const searchInput = document.getElementById('search-input');
  const searchBtn = document.querySelector('.btn-search');
  
  if (searchBtn && searchInput) {
    searchBtn.addEventListener('click', function() {
      const searchTerm = searchInput.value.trim();
      if (searchTerm) {
        // Здесь должен быть запрос на сервер для поиска
        console.log(`Поиск: ${searchTerm}`);
      }
    });
    
    // Поиск при нажатии Enter
    searchInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        searchBtn.click();
      }
    });
  }
  
  // Подтверждение удаления
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', function() {
      if (confirm('Вы уверены, что хотите удалить эту запись?')) {
        const paymentId = this.getAttribute('data-id') || '';
        // Здесь должен быть запрос на сервер для удаления
        console.log(`Удаление платежа с ID: ${paymentId}`);
      }
    });
  });
  
  // Инициализация виртуализации таблицы
  if (document.querySelector('.virtual-table')) {
    initVirtualTable();
  }
  
  function initVirtualTable() {
    // Реализация виртуализации для больших таблиц
    // Это упрощенный пример, в реальном проекте нужно использовать специализированные библиотеки
    console.log('Инициализация виртуализации таблицы');
  }
  
  // Первоначальное обновление информации о странице
  updatePageInfo();
});