/**
 * LOKA - Application JavaScript
 */

// Import Tailwind / DaisyUI styles so Vite bundles them for production
import '../css/app.css'

// Simple debounce utility
function debounce(fn, ms) {
  let timer
  return function (...args) {
    clearTimeout(timer)
    timer = setTimeout(() => fn.apply(this, args), ms)
  }
}

function initApp() {
  initSidebar()
  initDataTables()
  initDatePickers()
  initToasts()
  initConfirmDialogs()
  initFormValidation()
  initDropdowns()
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp)
} else {
  initApp()
}

/**
 * Sidebar Toggle
 */
function initSidebar() {
  const sidebar = document.getElementById('sidebar')
  const mainContent = document.getElementById('main-content')
  const toggleBtn = document.getElementById('sidebarToggle')

  // Create overlay for mobile
  const overlay = document.createElement('div')
  overlay.className = 'loka-sidebar-overlay'
  overlay.id = 'sidebarOverlay'
  document.body.appendChild(overlay)

  // Helper function to check if mobile
  const isMobileView = () => window.innerWidth < 992

  if (toggleBtn && sidebar && mainContent) {
    const toggleSidebar = () => {
      if (isMobileView()) {
        // Mobile: use show/hide classes with overlay
        const isOpen = sidebar.classList.contains('show')
        if (isOpen) {
          sidebar.classList.remove('show')
          overlay.classList.remove('show')
          document.body.classList.remove('sidebar-open')
        } else {
          // Remove collapsed class so it doesn't override mobile positioning
          sidebar.classList.remove('collapsed')
          mainContent.classList.remove('expanded')
          sidebar.classList.add('show')
          overlay.classList.add('show')
          document.body.classList.add('sidebar-open')
        }
      } else {
        // Desktop: use collapsed/expanded classes
        sidebar.classList.toggle('collapsed')
        mainContent.classList.toggle('expanded')
        // Save state for desktop only
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'))
      }
    }

    toggleBtn.addEventListener('click', toggleSidebar)

    // Close sidebar on overlay click (mobile)
    overlay.addEventListener('click', () => {
      if (sidebar.classList.contains('show')) {
        sidebar.classList.remove('show')
        overlay.classList.remove('show')
        document.body.classList.remove('sidebar-open')
      }
    })

    // Close sidebar on escape key
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && sidebar.classList.contains('show')) {
        sidebar.classList.remove('show')
        overlay.classList.remove('show')
        document.body.classList.remove('sidebar-open')
      }
    })

    // Restore desktop state only
    if (!isMobileView() && localStorage.getItem('sidebarCollapsed') === 'true') {
      sidebar.classList.add('collapsed')
      mainContent.classList.add('expanded')
    }

    // Mobile: close sidebar on link click
    sidebar.querySelectorAll('.loka-nav-link').forEach(link => {
      link.addEventListener('click', () => {
        if (isMobileView()) {
          sidebar.classList.remove('show')
          overlay.classList.remove('show')
          document.body.classList.remove('sidebar-open')
        }
      })
    })
  }

  // Handle window resize (debounced to prevent layout thrashing)
  window.addEventListener(
    'resize',
    debounce(() => {
      const nowMobile = window.innerWidth < 992
      const sidebarOverlay = document.getElementById('sidebarOverlay')

      // Clean up mobile styles when switching to desktop
      if (!nowMobile && sidebarOverlay) {
        sidebar.classList.remove('show')
        sidebarOverlay.classList.remove('show')
        document.body.classList.remove('sidebar-open')
      }
    }, 150)
  )
}

/**
 * Initialize DataTables
 */
function initDataTables() {
  const tables = document.querySelectorAll('.data-table')
  tables.forEach(table => {
    if (!$.fn.DataTable.isDataTable(table)) {
      $(table).DataTable({
        pageLength: 15,
        lengthMenu: [
          [10, 15, 25, 50, -1],
          [10, 15, 25, 50, 'All'],
        ],
        language: {
          search: '_INPUT_',
          searchPlaceholder: 'Search...',
          lengthMenu: 'Show _MENU_ entries',
          info: 'Showing _START_ to _END_ of _TOTAL_ entries',
          infoEmpty: 'No entries found',
          emptyTable: 'No data available',
          paginate: {
            first: '<i class="bi bi-chevron-double-left"></i>',
            previous: '<i class="bi bi-chevron-left"></i>',
            next: '<i class="bi bi-chevron-right"></i>',
            last: '<i class="bi bi-chevron-double-right"></i>',
          },
        },
        dom:
          '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
          '<"row"<"col-sm-12"tr>>' +
          '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        order: [[0, 'desc']],
        responsive: {
          details: {
            type: 'column',
            renderer: function (api, rowIdx, columns) {
              var data = columns
                .map(function (col) {
                  return col.hidden
                    ? '<li data-dt-row="' +
                        col.rowIndex +
                        '" data-dt-column="' +
                        col.columnIndex +
                        '">' +
                        '<span class="dt-bold">' +
                        col.title +
                        ':</span> ' +
                        col.data +
                        '</li>'
                    : ''
                })
                .join('')

              return data ? $('<ul data-dt-row="' + rowIdx + '"/>').append(data) : false
            },
          },
        },
      })
    }
  })
}

/**
 * Initialize Date Pickers
 */
function initDatePickers() {
  if (typeof flatpickr === 'undefined') {
    console.warn('Flatpickr not loaded')
    return
  }

  // Date only
  document.querySelectorAll('.datepicker').forEach(el => {
    // Skip if already initialized
    if (el._flatpickr) return
    flatpickr(el, {
      dateFormat: 'Y-m-d',
      allowInput: true,
    })
  })

  // DateTime
  document.querySelectorAll('.datetimepicker').forEach(el => {
    // Skip if already initialized
    if (el._flatpickr) return
    flatpickr(el, {
      enableTime: true,
      dateFormat: 'Y-m-d H:i',
      altInput: true,
      altFormat: 'Y-m-d h:i K',
      time_24hr: false,
      allowInput: true,
      minDate: 'today',
      minuteIncrement: 15,
    })
  })

  // Date range
  document.querySelectorAll('.daterange').forEach(el => {
    // Skip if already initialized
    if (el._flatpickr) return
    flatpickr(el, {
      mode: 'range',
      dateFormat: 'Y-m-d',
      allowInput: true,
    })
  })
}

/**
 * Initialize Toast Notifications
 */
function initToasts() {
  // Auto-dismiss alerts after 5 seconds
  document.querySelectorAll('.alert-dismissible').forEach(alert => {
    setTimeout(() => {
      const closeBtn = alert.querySelector('.btn-close')
      if (closeBtn) {
        closeBtn.click()
      }
    }, 5000)
  })
}

/**
 * Show toast notification (DaisyUI alert, no Bootstrap JS)
 */
function showToast(message, type = 'info') {
  const toastContainer = document.getElementById('toast-container') || createToastContainer()

  const toast = document.createElement('div')
  toast.className = `alert alert-${type} shadow-lg max-w-sm animate-[fade-in_0.3s_ease-out]`
  toast.setAttribute('role', 'alert')
  toast.innerHTML = `
        <span class="flex-1">${message}</span>
        <button type="button" class="btn btn-sm btn-ghost" onclick="this.closest('.alert').remove()">✕</button>
    `

  toastContainer.appendChild(toast)

  setTimeout(() => {
    toast.style.transition = 'opacity 0.3s'
    toast.style.opacity = '0'
    setTimeout(() => toast.remove(), 300)
  }, 3000)
}

function createToastContainer() {
  const container = document.createElement('div')
  container.id = 'toast-container'
  container.className = 'fixed top-0 right-0 z-[1100] flex flex-col gap-2 p-3'
  document.body.appendChild(container)
  return container
}

/**
 * Initialize Confirm Dialogs
 */
function initConfirmDialogs() {
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
      const message = this.getAttribute('data-confirm') || 'Are you sure?'
      if (!confirm(message)) {
        e.preventDefault()
        return false
      }
    })
  })
}

/**
 * Initialize Form Validation
 */
function initFormValidation() {
  document.querySelectorAll('.needs-validation').forEach(form => {
    form.addEventListener('submit', function (e) {
      if (!form.checkValidity()) {
        e.preventDefault()
        e.stopPropagation()
      }
      form.classList.add('was-validated')
    })
  })
}

/**
 * Initialize Dropdowns (DaisyUI)
 */
function initDropdowns() {
  // Prevent dropdown link navigation (DaisyUI uses tabindex + CSS)
  document.querySelectorAll('.dropdown > [tabindex]').forEach(trigger => {
    trigger.addEventListener('click', function (e) {
      e.preventDefault()
    })
  })
}
