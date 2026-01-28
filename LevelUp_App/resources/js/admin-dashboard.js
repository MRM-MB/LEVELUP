//Admin Dashboard Controller

class AdminDashboard {
    constructor() {
        this.init();
    }

    init() {
        // Initialize archived rewards toggle
        this.initializeArchivedToggle();
    }

    initializeArchivedToggle() {
        const toggleArchivedBtn = document.getElementById('toggleArchivedBtn');
        if (toggleArchivedBtn) {
            toggleArchivedBtn.addEventListener('click', function() {
                const section = document.getElementById('archivedRewardsSection');
                if (section.style.display === 'none' || section.style.display === '') {
                    section.style.display = 'block';
                    this.innerHTML = '<i class="fas fa-eye-slash"></i> Hide Archived';
                } else {
                    section.style.display = 'none';
                    this.innerHTML = '<i class="fas fa-archive"></i> Show Archived';
                }
            });
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new AdminDashboard();
});

document.addEventListener('DOMContentLoaded', function () {
              const selectAllCheckbox = document.getElementById('selectAllDesksCheckbox');
              const checkboxes = document.querySelectorAll('.desk-checkbox');

              if (selectAllCheckbox && checkboxes.length > 0) {
                selectAllCheckbox.addEventListener('change', function () {
                  const checked = selectAllCheckbox.checked;
                  checkboxes.forEach(cb => cb.checked = checked);
                });
              }
            });
// Average Statistics Page Script
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('averageStatsChart')) {
        const avgSitting = window.avgSitting || 0;
        const avgStanding = window.avgStanding || 0;
        const sittingPurple = '#6C4AB6'; // Homepage hero purple
        const standingGreen = '#10B981'; // Homepage focus clock green

        const ctx = document.getElementById('averageStatsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Average Sitting', 'Average Standing'],
                datasets: [
                    {
                        label: 'Minutes',
                        data: [avgSitting, avgStanding],
                        backgroundColor: [sittingPurple, standingGreen],
                        borderColor: [sittingPurple, standingGreen],
                        borderWidth: 2,
                        borderRadius: 14,
                        borderSkipped: false,
                        barPercentage: 0.55,
                        categoryPercentage: 0.5,
                        hoverBackgroundColor: ['#8D72E1', '#34D399']
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 1200,
                    easing: 'easeOutQuart'
                },
                layout: {
                    padding: {
                        top: 10,
                        right: 16,
                        left: 16,
                        bottom: 0
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Average Sitting vs Standing (in minutes)',
                        color: '#2b2f43',
                        font: {
                            size: 16,
                            weight: '600'
                        }
                    },
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1f1f2b',
                        padding: 12,
                        titleFont: { weight: '600' },
                        bodyFont: { weight: '500' },
                        callbacks: {
                            label: (context) => `${context.formattedValue} minutes`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            color: '#6b6f85',
                            font: { weight: '600' }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(107, 111, 133, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6b6f85'
                        },
                        title: {
                            display: true,
                            text: 'Minutes',
                            color: '#6b6f85'
                        }
                    }
                }
            }
        });
    }
});