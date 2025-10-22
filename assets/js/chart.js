<canvas id="radarChart"></canvas>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('radarChart').getContext('2d');
const radarChart = new Chart(ctx, {
    type: 'radar',
    data: {
        labels: ['Skill1','Skill2','Skill3'],
        datasets: [{
            label: 'Gemiddelde scores',
            data: [4,3,5],
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: { scales: { r: { beginAtZero: true, max:5 } } }
});
</script>
