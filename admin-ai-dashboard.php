<?php
/**
 * Dashboard de Consumo AI
 * Acceso restringido a administradores
 */
require_once('wp-load.php');

// Verificar permisos de admin
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado');
}

require_once('openai-controller.php');
$controller = new OpenAIController();

$monthlyStats = $controller->getMonthlyStats();
$userStats = $controller->getUserStats();

// Preparar datos para Chart.js
$fechas = [];
$costos = [];
$tokens = [];

foreach ($monthlyStats as $dia) {
    $fechas[] = $dia['fecha'];
    $costos[] = $dia['costo'];
    $tokens[] = $dia['tokens'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control AI - AutomatizaTech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background-color: #fff; border-bottom: 1px solid #eee; font-weight: bold; }
        .stat-card { border-left: 4px solid #0d6efd; }
        .stat-card.cost { border-left-color: #198754; }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>🤖 Monitor de Consumo OpenAI</h1>
            <div>
                <span class="badge bg-primary">API V2</span>
                <span class="badge bg-success">Active</span>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card stat-card cost p-3">
                    <h5 class="text-muted">Gasto últimos 30 días</h5>
                    <h3>$<?php echo number_format(array_sum($costos), 4); ?> USD</h3>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card stat-card p-3">
                    <h5 class="text-muted">Tokens Procesados</h5>
                    <h3><?php echo number_format(array_sum($tokens)); ?></h3>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Gráfico Principal -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Evolución de Costos Diarios</div>
                    <div class="card-body">
                        <canvas id="usageChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Usuarios -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Top Usuarios / Servicios</div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Reqs</th>
                                    <th>Costo (USD)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userStats as $stat): ?>
                                <tr>
                                    <td>#<?php echo $stat['user_id']; ?></td>
                                    <td><?php echo $stat['requests']; ?></td>
                                    <td>$<?php echo number_format($stat['total_cost'], 4); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    const ctx = document.getElementById('usageChart').getContext('2d');
    const usageChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($fechas); ?>,
            datasets: [{
                label: 'Costo Diario (USD)',
                data: <?php echo json_encode($costos); ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                yAxisID: 'y',
                fill: true
            }, {
                label: 'Tokens Totales',
                data: <?php echo json_encode($tokens); ?>,
                borderColor: '#0d6efd',
                borderDash: [5, 5],
                yAxisID: 'y1',
                fill: false
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: { display: true, text: 'Costo USD' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Tokens' }
                }
            }
        }
    });
    </script>
</body>
</html>