// assets/controllers/table-sortable_controller.js
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static targets = ['table', 'search', 'pagination', 'info']
  static values = {
    perPage: { type: Number, default: 25 },
  }

  connect() {
    this.currentPage = 1
    this.sortColumn = 2 // Score par défaut
    this.sortDirection = 'asc'
    this.searchTerm = ''

    this.originalRows = Array.from(
      this.tableTarget.querySelectorAll('tbody tr'),
    )
    this.filteredRows = [...this.originalRows]

    this.initTable()
  }

  initTable() {
    this.addSortListeners()
    this.render()
  }

  addSortListeners() {
    const headers = this.tableTarget.querySelectorAll('thead th')
    headers.forEach((header, index) => {
      if (index < headers.length - 1) {
        // Pas sur la colonne Actions
        header.style.cursor = 'pointer'
        header.addEventListener('click', () => this.sort(index))
        header.innerHTML += ' <span class="sort-icon">↕️</span>'
      }
    })
  }

  sort(columnIndex) {
    if (this.sortColumn === columnIndex) {
      this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc'
    } else {
      this.sortColumn = columnIndex
      this.sortDirection = 'asc'
    }

    this.filteredRows.sort((a, b) => {
      const aValue = this.getCellValue(a, columnIndex)
      const bValue = this.getCellValue(b, columnIndex)

      let comparison = 0
      if (!isNaN(aValue) && !isNaN(bValue)) {
        comparison = parseFloat(aValue) - parseFloat(bValue)
      } else {
        comparison = aValue.localeCompare(bValue, 'fr')
      }

      return this.sortDirection === 'asc' ? comparison : -comparison
    })

    this.updateSortIcons()
    this.render()
  }

  getCellValue(row, columnIndex) {
    const cell = row.children[columnIndex]
    return cell?.textContent.trim() || ''
  }

  updateSortIcons() {
    const headers = this.tableTarget.querySelectorAll('thead th')
    headers.forEach((header, index) => {
      const icon = header.querySelector('.sort-icon')
      if (icon) {
        if (index === this.sortColumn) {
          icon.textContent = this.sortDirection === 'asc' ? '↑' : '↓'
        } else {
          icon.textContent = '↕️'
        }
      }
    })
  }

  search(event) {
    this.searchTerm = event.target.value.toLowerCase()
    this.currentPage = 1

    if (this.searchTerm === '') {
      this.filteredRows = [...this.originalRows]
    } else {
      this.filteredRows = this.originalRows.filter((row) => {
        return Array.from(row.children).some((cell) =>
          cell.textContent.toLowerCase().includes(this.searchTerm),
        )
      })
    }

    this.render()
  }

  changePage(event) {
    const page = parseInt(event.params.page)
    if (page >= 1 && page <= this.totalPages) {
      this.currentPage = page
      this.render()
    }
  }

  get totalPages() {
    return Math.ceil(this.filteredRows.length / this.perPageValue)
  }

  render() {
    const tbody = this.tableTarget.querySelector('tbody')
    tbody.innerHTML = ''

    const start = (this.currentPage - 1) * this.perPageValue
    const end = start + this.perPageValue
    const pageRows = this.filteredRows.slice(start, end)

    pageRows.forEach((row) => tbody.appendChild(row.cloneNode(true)))

    this.updateInfo()
    this.updatePagination()
  }

  updateInfo() {
    if (!this.hasInfoTarget) return

    const start = (this.currentPage - 1) * this.perPageValue + 1
    const end = Math.min(start + this.perPageValue - 1, this.filteredRows.length)
    const total = this.filteredRows.length

    this.infoTarget.textContent =
      total === 0
        ? 'Aucune entrée à afficher'
        : `Affichage de ${start} à ${end} sur ${total} entrées`
  }

  updatePagination() {
    if (!this.hasPaginationTarget) return

    const totalPages = this.totalPages
    let html = ''

    if (totalPages <= 1) {
      this.paginationTarget.innerHTML = ''
      return
    }

    // Bouton Précédent
    html += `<li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
      <button class="page-link" data-action="click->table-sortable#changePage" data-table-sortable-page-param="${this.currentPage - 1}">
        Précédent
      </button>
    </li>`

    // Pages
    const maxVisible = 5
    let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2))
    let endPage = Math.min(totalPages, startPage + maxVisible - 1)

    if (endPage - startPage < maxVisible - 1) {
      startPage = Math.max(1, endPage - maxVisible + 1)
    }

    if (startPage > 1) {
      html += `<li class="page-item"><button class="page-link" data-action="click->table-sortable#changePage" data-table-sortable-page-param="1">1</button></li>`
      if (startPage > 2) {
        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`
      }
    }

    for (let i = startPage; i <= endPage; i++) {
      html += `<li class="page-item ${i === this.currentPage ? 'active' : ''}">
        <button class="page-link" data-action="click->table-sortable#changePage" data-table-sortable-page-param="${i}">
          ${i}
        </button>
      </li>`
    }

    if (endPage < totalPages) {
      if (endPage < totalPages - 1) {
        html += `<li class="page-item disabled"><span class="page-link">...</span></li>`
      }
      html += `<li class="page-item"><button class="page-link" data-action="click->table-sortable#changePage" data-table-sortable-page-param="${totalPages}">${totalPages}</button></li>`
    }

    // Bouton Suivant
    html += `<li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
      <button class="page-link" data-action="click->table-sortable#changePage" data-table-sortable-page-param="${this.currentPage + 1}">
        Suivant
      </button>
    </li>`

    this.paginationTarget.innerHTML = html
  }

  exportCSV() {
    const headers = Array.from(
      this.tableTarget.querySelectorAll('thead th'),
    ).map((th) => th.textContent.trim().replace(/[↕️↑↓]/g, '').trim())

    const rows = this.filteredRows.map((row) =>
      Array.from(row.children).map((cell) => {
        const text = cell.textContent.trim()
        return `"${text.replace(/"/g, '""')}"`
      }),
    )

    const csvContent = [
      headers.join(';'),
      ...rows.map((row) => row.join(';')),
    ].join('\n')

    this.downloadFile(
      csvContent,
      `export_${new Date().toISOString().split('T')[0]}.csv`,
      'text/csv;charset=utf-8;',
    )

    this.showNotification('Export CSV réussi !', 'success')
  }

  downloadFile(content, fileName, mimeType) {
    const blob = new Blob([content], { type: mimeType })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = fileName
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(link.href)
  }

  showNotification(message, type = 'info') {
    const alertClass =
      {
        success: 'alert-success',
        error: 'alert-danger',
        info: 'alert-info',
        warning: 'alert-warning',
      }[type] || 'alert-info'

    const alert = document.createElement('div')
    alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed top-0 end-0 m-3`
    alert.style.zIndex = '9999'
    alert.innerHTML = `
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `

    document.body.appendChild(alert)

    setTimeout(() => {
      alert.classList.remove('show')
      setTimeout(() => alert.remove(), 150)
    }, 3000)
  }

  runDiagnostic() {
    const diagnostic = {
      timestamp: new Date().toISOString(),
      totalRows: this.originalRows.length,
      filteredRows: this.filteredRows.length,
      currentPage: this.currentPage,
      totalPages: this.totalPages,
      perPage: this.perPageValue,
      sortColumn: this.sortColumn,
      sortDirection: this.sortDirection,
      searchTerm: this.searchTerm || 'aucun',
    }

    const report = Object.entries(diagnostic)
      .map(([key, value]) => `${key}: ${value}`)
      .join('\n')

    this.downloadFile(
      report,
      `diagnostic_${new Date().toISOString().split('T')[0]}.txt`,
      'text/plain;charset=utf-8;',
    )

    this.showNotification('Diagnostic téléchargé !', 'success')
  }
}

