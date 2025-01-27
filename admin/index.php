<?php
//Only display the page if the user is an admin
if ( !current_user_can( 'administrator' ) ) {
    return;
}
?>

<div class='wrap'>
    <h1>
    <?php echo esc_html( "Dashboard", 'referral_manager'); ?>
    </h1>

    <div>
        <canvas id="myChart"></canvas>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('myChart');

new Chart(ctx, {
    type: 'bar',
    data: {
    labels: ['Red', 'Blue', 'Yellow', 'Green', 'Purple', 'Orange'],
    datasets: [{
        label: '# of Votes',
        data: [12, 19, 3, 5, 2, 3],
        borderWidth: 1
    }]
    },
    options: {
    scales: {
        y: {
        beginAtZero: true
        }
    }
    }
});
</script>