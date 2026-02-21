// Busca e filtro por categoria
(function(){
  const search = document.getElementById('searchInput');
  const catChips = document.getElementById('categoryChips');
  const subChips = document.getElementById('subcategoryChips');
  const cards = Array.from(document.querySelectorAll('#productGrid .card'));
  
  let activeCategory = 'all';
  let activeSubcategory = '';
  var REQUIRE_SUB = '__require__';

  function matches(card, term) {
    if (!term) return true;
    const name = card.getAttribute('data-name') || '';
    const cat = (card.getAttribute('data-category') || '').toLowerCase();
    const sub = (card.getAttribute('data-subcategory') || '').toLowerCase();
    term = term.toLowerCase();
    return name.includes(term) || cat.includes(term) || sub.includes(term);
  }

  function matchesTaxonomy(card) {
    const cat = (card.getAttribute('data-category') || '').toLowerCase();
    const sub = (card.getAttribute('data-subcategory') || '').toLowerCase();
    if (activeSubcategory === REQUIRE_SUB) return false;
    if (activeCategory !== 'all' && cat !== activeCategory.toLowerCase()) return false;
    if (activeSubcategory && sub !== activeSubcategory.toLowerCase()) return false;
    return true;
  }

  function applyFilters() {
    const term = (search && search.value) ? search.value.trim() : '';
    cards.forEach(card => {
      const ok = matches(card, term) && matchesTaxonomy(card);
      card.style.display = ok ? '' : 'none';
    });
  }

  function renderSubcategories() {
    if (!subChips) return;
    const map = window.categorySubMap || {};
    const subs = map[activeCategory] || [];
    if (!subs.length || activeCategory === 'all') {
      subChips.style.display = 'none';
      subChips.innerHTML = '';
      activeSubcategory = '';
      var notice = document.getElementById('subNotice');
      if (notice) notice.style.display = 'none';
      return;
    }
    subChips.innerHTML = ['<button class="chip" data-sub="">Todas</button>'].concat(subs.map(function(s){ return '<button class="chip" data-sub="'+s.replace(/"/g,'&quot;')+'">'+s.replace(/</g,'&lt;')+'</button>'; })).join('');
    subChips.style.display = '';
    activeSubcategory = REQUIRE_SUB;
    var notice = document.getElementById('subNotice');
    if (notice) notice.style.display = '';
  }

  if (search) { search.addEventListener('input', applyFilters); }
  if (catChips) {
    catChips.addEventListener('click', function(e){
      const btn = e.target.closest('.chip');
      if (!btn) return;
      catChips.querySelectorAll('.chip').forEach(function(c){ c.classList.remove('active'); });
      btn.classList.add('active');
      activeCategory = btn.getAttribute('data-category') || 'all';
      renderSubcategories();
      applyFilters();
    });
  }
  if (subChips) {
    subChips.addEventListener('click', function(e){
      const btn = e.target.closest('.chip');
      if (!btn) return;
      subChips.querySelectorAll('.chip').forEach(function(c){ c.classList.remove('active'); });
      btn.classList.add('active');
      activeSubcategory = btn.getAttribute('data-sub') || '';
      var notice = document.getElementById('subNotice');
      if (notice) notice.style.display = 'none';
      applyFilters();
    });
  }

  // Click tracking básico em links de afiliado
  document.addEventListener('click', (e) => {
    const link = e.target.closest('a');
    if (!link) return;
    if (link.classList.contains('primary') && link.textContent.toLowerCase().includes('compr')) {
      console.log('[Affiliate] Click:', link.href);
    }
  });
})();