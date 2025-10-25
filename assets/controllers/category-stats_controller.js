// assets/controllers/category-stats_controller.js
import { Controller } from '@hotwired/stimulus'
import Chart from 'chart.js/auto'

export default class extends Controller {
  static values = {
    directQuestions: Number,
    totalQuestions: Number,
    childrenCount: Number,
    subCategories: Array,
  }

  connect() {
    this.initQuestionsChart()
    this.initSubCategoryChart()
  }

  initQuestionsChart() {
    if (this.childrenCountValue === 0) return

    const ctx = this.element.querySelector('#questionsChart')
    if (!ctx) return

    new Chart(ctx.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: ['Questions directes', 'Questions dans sous-catégories'],
        datasets: [
          {
            data: [
              this.directQuestionsValue,
              this.totalQuestionsValue - this.directQuestionsValue,
            ],
            backgroundColor: ['#0d6efd', '#6c757d'],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    })
  }

  initSubCategoryChart() {
    if (this.subCategoriesValue.length === 0) return

    const ctx = this.element.querySelector('#subCategoryChart')
    if (!ctx) return

    const labels = this.subCategoriesValue.map((cat) => cat.name)
    const data = this.subCategoriesValue.map((cat) => cat.questionsCount)

    new Chart(ctx.getContext('2d'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Nombre de questions',
            data: data,
            backgroundColor: [
              'rgba(255, 99, 132, 0.2)',
              'rgba(54, 162, 235, 0.2)',
              'rgba(255, 206, 86, 0.2)',
              'rgba(75, 192, 192, 0.2)',
              'rgba(153, 102, 255, 0.2)',
              'rgba(255, 159, 64, 0.2)',
            ],
            borderColor: [
              'rgba(255, 99, 132, 1)',
              'rgba(54, 162, 235, 1)',
              'rgba(255, 206, 86, 1)',
              'rgba(75, 192, 192, 1)',
              'rgba(153, 102, 255, 1)',
              'rgba(255, 159, 64, 1)',
            ],
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1,
            },
          },
        },
      },
    })
  }
}
