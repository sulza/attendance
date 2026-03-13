// VLA System - Main JS

// Sidebar toggle
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');

if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => {
    if (window.innerWidth <= 768) {
      sidebar.classList.toggle('open');
    } else {
      document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem('sc', document.body.classList.contains('sidebar-collapsed') ? '1' : '0');
    }
  });
}

if (localStorage.getItem('sc') === '1' && window.innerWidth > 768) {
  document.body.classList.add('sidebar-collapsed');
}

// Notification dropdown
const notifBtn = document.getElementById('notifBtn');
const notifDropdown = document.getElementById('notifDropdown');
if (notifBtn && notifDropdown) {
  notifBtn.addEventListener('click', e => { e.stopPropagation(); notifDropdown.classList.toggle('open'); profileDropdown?.classList.remove('open'); });
}

// Profile dropdown
const profileBtn = document.getElementById('profileBtn');
const profileDropdown = document.getElementById('profileDropdown');
if (profileBtn && profileDropdown) {
  profileBtn.addEventListener('click', e => { e.stopPropagation(); profileDropdown.classList.toggle('open'); notifDropdown?.classList.remove('open'); });
}

document.addEventListener('click', () => {
  notifDropdown?.classList.remove('open');
  profileDropdown?.classList.remove('open');
});

// Duration auto-calc
const startT = document.getElementById('start_time');
const endT = document.getElementById('end_time');
const durField = document.getElementById('duration_hours');
function calcDur() {
  if (!startT?.value || !endT?.value) return;
  const [sh,sm] = startT.value.split(':').map(Number);
  const [eh,em] = endT.value.split(':').map(Number);
  const diff = (eh*60+em) - (sh*60+sm);
  if (diff > 0 && durField) durField.value = (diff/60).toFixed(2);
}
startT?.addEventListener('change', calcDur);
endT?.addEventListener('change', calcDur);

// Confirm delete
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => { if(!confirm(el.dataset.confirm || 'Are you sure?')) e.preventDefault(); });
});

// Table search
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
  searchInput.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('table.data-table tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// Auto-dismiss alerts
document.querySelectorAll('.alert:not(.alert-permanent)').forEach(el => {
  setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .4s'; setTimeout(()=>el.remove(),400); }, 5000);
});

// Sidebar collapse effect for desktop
document.head.insertAdjacentHTML('beforeend', `<style>
.sidebar-collapsed .sidebar { width: 64px; }
.sidebar-collapsed .sidebar .brand-name,
.sidebar-collapsed .sidebar .brand-sub,
.sidebar-collapsed .sidebar .nav-divider,
.sidebar-collapsed .sidebar .nav-item span,
.sidebar-collapsed .sidebar .nav-badge,
.sidebar-collapsed .sidebar .user-info,
.sidebar-collapsed .sidebar .logout-btn { display: none; }
.sidebar-collapsed .sidebar .nav-item { justify-content: center; }
.sidebar-collapsed .sidebar .user-card { justify-content: center; }
.sidebar-collapsed .main-wrapper { margin-left: 64px; }
</style>`);
