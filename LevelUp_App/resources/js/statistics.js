import Chart from 'chart.js/auto';

// State variables
let currentHealthCycle = healthCycle;
let allTimeStats = {
    sitting_minutes: totalSitting,
    standing_minutes: totalStanding
};
let barChart;
let pieChart;
let pieInited = false;

// Initialize bar chart
function initBarChart() {
    const day = currentHealthCycle ? new Date(currentHealthCycle.completed_at).toLocaleDateString() : 'Today';
    const sittingTime = currentHealthCycle ? currentHealthCycle.sitting_minutes : 0;
    const standingTime = currentHealthCycle ? currentHealthCycle.standing_minutes : 0;

    const barCtx = document.getElementById('barChart').getContext('2d');
    barChart = new Chart(barCtx, {
        type: 'bar',
        data: {
            labels: [day],
            datasets: [
                {
                    label: 'Sitting Time',
                    data: [sittingTime],
                    backgroundColor: '#B9E0FF'
                },
                {
                    label: 'Standing Time',
                    data: [standingTime],
                    backgroundColor: '#8D9EFF'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Daily Activity Time (in minutes)'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Minutes'
                    }
                }
            }
        }
    });
}

// Update bar chart with new data
function updateBarChart() {
    if (!barChart || !currentHealthCycle) return;
    
    const sittingTime = currentHealthCycle.sitting_minutes || 0;
    const standingTime = currentHealthCycle.standing_minutes || 0;

    barChart.data.datasets[0].data = [sittingTime];
    barChart.data.datasets[1].data = [standingTime];
    barChart.update();
}

// Update pie chart with new data
function updatePieChart() {
    if (!pieChart || !pieInited) return;
    
    const data = [allTimeStats.sitting_minutes, allTimeStats.standing_minutes];
    pieChart.data.datasets[0].data = data;
    pieChart.update();
}

// Fetch latest health cycle data
async function fetchLatestHealthCycle() {
    try {
        // Get user's current date in their LOCAL timezone (not UTC)
        const now = new Date();
        const userDate = now.getFullYear() + '-' + 
                       String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                       String(now.getDate()).padStart(2, '0');
        
        const [todayResponse, allTimeResponse] = await Promise.all([
            fetch(`/api/statistics/today-stats?user_date=${userDate}`),
            fetch('/api/statistics/all-time-stats')
        ]);

        if (todayResponse.ok) {
            const data = await todayResponse.json();
            currentHealthCycle = {
                sitting_minutes: data.sitting_minutes,
                standing_minutes: data.standing_minutes,
                completed_at: new Date().toISOString()
            };
            updateBarChart();
        }

        if (allTimeResponse.ok) {
            const data = await allTimeResponse.json();
            allTimeStats = {
                sitting_minutes: data.sitting_minutes,
                standing_minutes: data.standing_minutes
            };
            updatePieChart();
        }
    } catch (error) {
        console.error('Error fetching health cycle data:', error);
    }
}

// Initialize bar chart on page load
initBarChart();

// Button For Toggling Pie Chart
const togglePiebtn = document.getElementById("togglePieChart");
const allTime = document.getElementById("allTimeChart");

togglePiebtn.addEventListener("click", () => {
    const expanded = allTime.classList.toggle("expanded");
    togglePiebtn.setAttribute("aria-expanded", expanded);
    togglePiebtn.textContent = expanded
    ? "Hide All-Time Statistics"
    : "Show All-Time Statistics";

    if(expanded && !pieInited) {
        initPieChart();
        pieInited = true;
    }
});

function initPieChart() {
    const data = [totalSitting, totalStanding];
    const sum = data.reduce((s, v) => s + v, 0) || 1;

    const pieCtx = document.getElementById('pieChart').getContext('2d');
    pieChart = new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: ['Sitting', 'Standing'],
            datasets: [{
                data: data,
                backgroundColor: [
                    '#B9E0FF',
                    '#8D9EFF'
                ],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'All-Time Sitting vs Standing'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = Number(context.raw || 0);
                            const percentage = ((value / sum) * 100).toFixed(1);
                            return `${context.label}: ${percentage}% (${value} minutes)`;
                        }
                    }
                }
            }
        }
    });
}

setInterval(fetchLatestHealthCycle, 1000);
