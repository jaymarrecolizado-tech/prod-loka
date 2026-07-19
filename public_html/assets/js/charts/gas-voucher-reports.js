/* global Chart, window */
;(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') fn()
    else document.addEventListener('DOMContentLoaded', fn)
  }

  function palette(n) {
    var base = ['#00d4ff', '#3b82f6', '#14e0b0', '#f5c518', '#f43f5e', '#a78bfa', '#fb7185', '#38bdf8']
    var out = []
    for (var i = 0; i < n; i++) out.push(base[i % base.length])
    return out
  }

  function emptyMessage(canvas, msg) {
    if (!canvas || !canvas.parentNode) return
    var p = document.createElement('p')
    p.className = 'text-sm text-base-content/50 text-center py-8 mb-0'
    p.textContent = msg || 'No data for these filters.'
    canvas.replaceWith(p)
  }

  ready(function () {
    var data = window.gasVoucherReportAnalytics
    if (!data || typeof Chart === 'undefined') return

    var common = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
    }

    var statusCtx = document.getElementById('gvStatusChart')
    if (statusCtx) {
      if (!data.status || !data.status.length) emptyMessage(statusCtx)
      else {
        new Chart(statusCtx, {
          type: 'doughnut',
          data: {
            labels: data.status.map(function (r) {
              return String(r.label || '').replace(/_/g, ' ')
            }),
            datasets: [
              {
                data: data.status.map(function (r) {
                  return Number(r.count) || 0
                }),
                backgroundColor: palette(data.status.length),
              },
            ],
          },
          options: common,
        })
      }
    }

    var fundCtx = document.getElementById('gvFundChart')
    if (fundCtx) {
      if (!data.byFund || !data.byFund.length) emptyMessage(fundCtx)
      else {
        new Chart(fundCtx, {
          type: 'bar',
          data: {
            labels: data.byFund.map(function (r) {
              return r.label
            }),
            datasets: [
              {
                label: 'Vouchers',
                data: data.byFund.map(function (r) {
                  return Number(r.count) || 0
                }),
                backgroundColor: 'rgba(0, 212, 255, 0.7)',
                borderColor: '#00d4ff',
                borderWidth: 1,
              },
            ],
          },
          options: Object.assign({}, common, {
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
          }),
        })
      }
    }

    var payCtx = document.getElementById('gvPaymentChart')
    if (payCtx) {
      if (!data.payment || !data.payment.length) emptyMessage(payCtx, 'No approved vouchers in range.')
      else {
        new Chart(payCtx, {
          type: 'pie',
          data: {
            labels: data.payment.map(function (r) {
              return String(r.label || '').replace(/_/g, ' ')
            }),
            datasets: [
              {
                data: data.payment.map(function (r) {
                  return Number(r.count) || 0
                }),
                backgroundColor: palette(data.payment.length),
              },
            ],
          },
          options: common,
        })
      }
    }

    var stationCtx = document.getElementById('gvStationChart')
    if (stationCtx) {
      if (!data.byStation || !data.byStation.length) emptyMessage(stationCtx)
      else {
        new Chart(stationCtx, {
          type: 'bar',
          data: {
            labels: data.byStation.map(function (r) {
              return r.label
            }),
            datasets: [
              {
                label: 'Vouchers',
                data: data.byStation.map(function (r) {
                  return Number(r.count) || 0
                }),
                backgroundColor: 'rgba(59, 130, 246, 0.7)',
                borderColor: '#3b82f6',
                borderWidth: 1,
              },
            ],
          },
          options: Object.assign({}, common, {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
          }),
        })
      }
    }

    var trendCtx = document.getElementById('gvTrendChart')
    if (trendCtx) {
      if (!data.daily || !data.daily.length) emptyMessage(trendCtx)
      else {
        new Chart(trendCtx, {
          type: 'line',
          data: {
            labels: data.daily.map(function (r) {
              return r.day
            }),
            datasets: [
              {
                label: 'Vouchers / day',
                data: data.daily.map(function (r) {
                  return Number(r.count) || 0
                }),
                borderColor: '#14e0b0',
                backgroundColor: 'rgba(20, 224, 176, 0.15)',
                fill: true,
                tension: 0.25,
              },
            ],
          },
          options: Object.assign({}, common, {
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
          }),
        })
      }
    }
  })
})()
