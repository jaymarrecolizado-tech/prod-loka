/**
 * LOKA Dashboard Charts Module
 * Extracted from dashboard/index.php inline script.
 * Reads data from window.dashboardAnalytics (set by PHP).
 */
/* global Chart */
;(function () {
  'use strict'

  const analyticsData = window.dashboardAnalytics
  if (!analyticsData) return

  // Dynamic mobile detection with resize listener
  function getDeviceProfile() {
    const w = window.innerWidth
    return {
      isSmallMobile: w < 576,
      isMobile: w < 768,
      fontScale: w < 576 ? 0.85 : w < 768 ? 0.92 : 1,
    }
  }

  function buildCommonOptions() {
    const { isMobile, isSmallMobile } = getDeviceProfile()
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            font: {
              size: isSmallMobile ? 10 : isMobile ? 11 : 12,
              family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
            },
            boxWidth: isSmallMobile ? 12 : isMobile ? 14 : 16,
            padding: isSmallMobile ? 8 : 12,
          },
        },
        tooltip: {
          titleFont: { size: isSmallMobile ? 11 : 12 },
          bodyFont: { size: isSmallMobile ? 10 : 11 },
          padding: isSmallMobile ? 6 : 8,
        },
      },
      scales: {
        x: {
          ticks: {
            font: { size: isSmallMobile ? 9 : isMobile ? 10 : 11 },
            maxRotation: isMobile ? 45 : 0,
            minRotation: isMobile ? 45 : 0,
          },
          grid: { display: !isSmallMobile },
        },
        y: {
          ticks: {
            font: { size: isSmallMobile ? 9 : isMobile ? 10 : 11 },
            stepSize: 1,
          },
          grid: { display: !isSmallMobile },
        },
      },
    }
  }

  const charts = {}

  function initCharts() {
    // Destroy existing charts
    Object.values(charts).forEach(function (c) {
      if (c && typeof c.destroy === 'function') c.destroy()
    })

    var commonOptions = buildCommonOptions()

    // Daily Trips
    var dailyTripsCtx = document.getElementById('dailyTripsChart')
    if (dailyTripsCtx) {
      charts.dailyTrips = new Chart(dailyTripsCtx, {
        type: 'line',
        data: {
          labels: analyticsData.dailyTrips.map(function (d) {
            return d.date
          }),
          datasets: [
            {
              label: 'Number of Trips',
              data: analyticsData.dailyTrips.map(function (d) {
                return d.count
              }),
              borderColor: '#0d6efd',
              backgroundColor: 'rgba(13, 110, 253, 0.1)',
              fill: true,
              tension: 0.4,
            },
          ],
        },
        options: Object.assign({}, commonOptions, {
          scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
          },
        }),
      })
    }

    // Status Distribution
    var statusCtx = document.getElementById('statusChart')
    if (statusCtx) {
      var statusLabels = {
        approved: 'Approved',
        pending: 'Pending',
        pending_motorpool: 'Motorpool',
        completed: 'Completed',
        cancelled: 'Cancelled',
        rejected: 'Rejected',
        revision: 'Revision',
      }
      var statusColors = {
        approved: '#198754',
        pending: '#ffc107',
        pending_motorpool: '#0dcaf0',
        completed: '#20c997',
        cancelled: '#6c757d',
        rejected: '#dc3545',
        revision: '#fd7e14',
      }

      charts.status = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
          labels: analyticsData.statusDistribution.map(function (s) {
            return statusLabels[s.status] || s.status
          }),
          datasets: [
            {
              data: analyticsData.statusDistribution.map(function (s) {
                return s.count
              }),
              backgroundColor: analyticsData.statusDistribution.map(function (s) {
                return statusColors[s.status] || '#6c757d'
              }),
            },
          ],
        },
        options: commonOptions,
      })
    }

    // Department Trips
    var deptCtx = document.getElementById('departmentChart')
    if (deptCtx) {
      charts.department = new Chart(deptCtx, {
        type: 'bar',
        data: {
          labels: analyticsData.departmentStats.map(function (d) {
            return d.department
          }),
          datasets: [
            {
              label: 'Trips',
              data: analyticsData.departmentStats.map(function (d) {
                return d.count
              }),
              backgroundColor: [
                '#0d6efd',
                '#6610f2',
                '#d63384',
                '#dc3545',
                '#fd7e14',
                '#ffc107',
                '#198754',
                '#20c997',
              ],
            },
          ],
        },
        options: Object.assign({}, commonOptions, {
          indexAxis: 'y',
          scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1 } },
          },
        }),
      })
    }

    // Peak Hours
    var peakCtx = document.getElementById('peakHoursChart')
    if (peakCtx) {
      var hourLabels = Array.from({ length: 24 }, function (_, i) {
        return i + ':00'
      })
      charts.peakHours = new Chart(peakCtx, {
        type: 'bar',
        data: {
          labels: hourLabels,
          datasets: [
            {
              label: 'Trips',
              data: analyticsData.peakHours,
              backgroundColor: 'rgba(13, 110, 253, 0.7)',
              borderColor: '#0d6efd',
              borderWidth: 1,
            },
          ],
        },
        options: Object.assign({}, commonOptions, {
          scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
          },
          plugins: { legend: { display: false } },
        }),
      })
    }
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCharts)
  } else {
    initCharts()
  }

  // Rebuild charts on resize (debounced)
  var resizeTimer
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer)
    resizeTimer = setTimeout(initCharts, 250)
  })
})()
