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
 * Initialize Tables — vanilla JS (replaces jQuery DataTables)
 */
function initDataTables() {
  document.querySelectorAll('.data-table').forEach(table => {
    if (table._vanillaDT) return
    table._vanillaDT = true
    vanillaDataTable(table)
  })
}

function vanillaDataTable(table) {
  const PAGE_LENGTH = 15
  const tbody = table.querySelector('tbody')
  const thead = table.querySelector('thead')
  if (!tbody || !thead) return

  const originalRows = Array.from(tbody.querySelectorAll('tr'))
  const allRows = [...originalRows]

  // --- state ---
  let filteredRows = [...allRows]
  let sortCol = -1
  let sortDir = 'asc'
  let currentPage = 1
  let perPage = PAGE_LENGTH

  // --- wrapper ---
  const wrapper = document.createElement('div')
  wrapper.className = 'dataTables-wrapper'
  table.parentNode.insertBefore(wrapper, table)
  wrapper.appendChild(table)

  // --- controls bar ---
  const controls = document.createElement('div')
  controls.className = 'flex flex-wrap items-center justify-between gap-3 py-3'
  wrapper.insertBefore(controls, table)

  // Length selector
  const lengthWrap = document.createElement('div')
  lengthWrap.className = 'flex items-center gap-2 text-sm'
  lengthWrap.innerHTML = '<span class="text-base-content/60">Show</span>'
  const lengthSelect = document.createElement('select')
  lengthSelect.className = 'select select-bordered select-sm'
  ;[10, 15, 25, 50].forEach(n => {
    const o = document.createElement('option')
    o.value = n
    o.textContent = n
    if (n === perPage) o.selected = true
    lengthSelect.appendChild(o)
  })
  const allOpt = document.createElement('option')
  allOpt.value = -1
  allOpt.textContent = 'All'
  lengthSelect.appendChild(allOpt)
  lengthSelect.addEventListener('change', () => {
    perPage = parseInt(lengthSelect.value)
    currentPage = 1
    render()
  })
  lengthWrap.appendChild(lengthSelect)
  lengthWrap.insertAdjacentText('beforeend', ' entries')
  controls.appendChild(lengthWrap)

  // Search
  const searchWrap = document.createElement('div')
  searchWrap.className = 'form-control'
  const searchInput = document.createElement('input')
  searchInput.type = 'text'
  searchInput.placeholder = 'Search...'
  searchInput.className = 'input input-bordered input-sm w-full max-w-xs'
  searchInput.addEventListener('input', () => {
    const q = searchInput.value.toLowerCase()
    filteredRows = allRows.filter(row => row.textContent.toLowerCase().includes(q))
    currentPage = 1
    render()
  })
  searchWrap.appendChild(searchInput)
  controls.appendChild(searchWrap)

  // --- info + pagination footer ---
  const footer = document.createElement('div')
  footer.className = 'flex flex-wrap items-center justify-between gap-3 py-3'
  wrapper.appendChild(footer)

  const infoText = document.createElement('div')
  infoText.className = 'text-sm text-base-content/60'
  footer.appendChild(infoText)

  const pagNav = document.createElement('div')
  pagNav.className = 'join'
  footer.appendChild(pagNav)

  // --- sorting ---
  const headers = thead.querySelectorAll('th')
  headers.forEach((th, idx) => {
    th.classList.add('cursor-pointer', 'select-none', 'hover:bg-base-200', 'transition-colors')
    th.addEventListener('click', () => {
      if (sortCol === idx) {
        sortDir = sortDir === 'asc' ? 'desc' : 'asc'
      } else {
        sortCol = idx
        sortDir = 'asc'
      }
      headers.forEach(h => h.classList.remove('bg-base-200'))
      th.classList.add('bg-base-200')
      render()
    })
  })

  // --- render ---
  function sortRows(rows) {
    if (sortCol < 0) return rows
    return [...rows].sort((a, b) => {
      const aVal = (a.children[sortCol]?.textContent || '').trim()
      const bVal = (b.children[sortCol]?.textContent || '').trim()
      const aNum = parseFloat(aVal.replace(/[^0-9.]/g, ''))
      const bNum = parseFloat(bVal.replace(/[^0-9.]/g, ''))
      if (!isNaN(aNum) && !isNaN(bNum)) {
        return sortDir === 'asc' ? aNum - bNum : bNum - aNum
      }
      return sortDir === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal)
    })
  }

  function render() {
    const sorted = sortRows(filteredRows)
    const total = sorted.length
    const totalPages = perPage < 0 ? 1 : Math.ceil(total / perPage)
    if (currentPage > totalPages) currentPage = totalPages
    const start = perPage < 0 ? 0 : (currentPage - 1) * perPage
    const end = perPage < 0 ? total : start + perPage
    const pageRows = sorted.slice(start, end)

    // re-render tbody
    tbody.innerHTML = ''
    if (pageRows.length === 0) {
      const tr = document.createElement('tr')
      const td = document.createElement('td')
      td.colSpan = headers.length
      td.className = 'py-8 text-center text-base-content/50'
      td.textContent = 'No data available'
      tr.appendChild(td)
      tbody.appendChild(tr)
    } else {
      pageRows.forEach(row => tbody.appendChild(row))
    }

    // info text
    if (total === 0) {
      infoText.textContent = 'No entries found'
    } else {
      infoText.textContent = `Showing ${start + 1} to ${Math.min(end, total)} of ${total} entries`
    }

    // pagination
    pagNav.innerHTML = ''
    if (totalPages > 1) {
      const addPageBtn = (label, page, disabled, active) => {
        const btn = document.createElement('button')
        btn.className = `join-item btn btn-sm ${active ? 'btn-active' : ''}`
        btn.innerHTML = label
        btn.disabled = disabled
        if (!disabled && !active)
          btn.addEventListener('click', () => {
            currentPage = page
            render()
          })
        pagNav.appendChild(btn)
      }
      addPageBtn('&laquo;', 1, currentPage === 1, false)
      addPageBtn('&lsaquo;', currentPage - 1, currentPage === 1, false)
      const maxVisible = 5
      let startP = Math.max(1, currentPage - Math.floor(maxVisible / 2))
      let endP = Math.min(totalPages, startP + maxVisible - 1)
      if (endP - startP + 1 < maxVisible) startP = Math.max(1, endP - maxVisible + 1)
      if (startP > 1) {
        addPageBtn('1', 1, false, false)
        if (startP > 2) {
          const dots = document.createElement('span')
          dots.className = 'join-item btn btn-sm btn-disabled'
          dots.textContent = '...'
          pagNav.appendChild(dots)
        }
      }
      for (let i = startP; i <= endP; i++) {
        addPageBtn(String(i), i, false, i === currentPage)
      }
      if (endP < totalPages) {
        if (endP < totalPages - 1) {
          const dots = document.createElement('span')
          dots.className = 'join-item btn btn-sm btn-disabled'
          dots.textContent = '...'
          pagNav.appendChild(dots)
        }
        addPageBtn(String(totalPages), totalPages, false, false)
      }
      addPageBtn('&rsaquo;', currentPage + 1, currentPage === totalPages, false)
      addPageBtn('&raquo;', totalPages, currentPage === totalPages, false)
    }
  }

  render()
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
