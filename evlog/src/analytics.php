<?php
require_once 'config.php';

// Filtri Base (RIPRISTINATI)
$date_start      = $_GET['date_start'] ?? '';
$date_end        = $_GET['date_end'] ?? '';
$filter_category = $_GET['category'] ?? '';
$only_favs       = isset($_GET['only_favs']) && $_GET['only_favs'] == '1';
$source_table    = $_GET['source'] ?? 'trips'; // trips o charges

// BI Configuration (RIPRISTINATO)
$groupBy    = $_GET['group_by'] ?? 'date'; 
$metric     = $_GET['metric'] ?? ($source_table === 'trips' ? 'distance_km' : 'cost');    
$aggregate  = $_GET['aggregate'] ?? 'SUM';
$chartType  = $_GET['chart_type'] ?? 'line'; 

// Costruzione query SQL
$clauses = ["1=1"];
$params = [];
$dateField = ($source_table === 'trips') ? 'start_time' : 'charge_date';

if ($date_start) { $clauses[] = "$dateField >= ?"; $params[] = $date_start . " 00:00:00"; }
if ($date_end)   { $clauses[] = "$dateField <= ?"; $params[] = $date_end . " 23:59:59"; }

if ($source_table === 'trips') {
    if ($filter_category) { $clauses[] = "category = ?"; $params[] = $filter_category; }
    if ($only_favs) { $clauses[] = "is_favorite = 1"; }
}
$whereSql = "WHERE " . implode(" AND ", $clauses);

$groupSql = [
    'date'     => "DATE($dateField)",
    'month'    => "DATE_FORMAT($dateField, '%Y-%m')",
    'week'     => "YEARWEEK($dateField)",
    'category' => "category",
    'hour'     => "HOUR($dateField)"
];

// Metriche speciali
if ($metric === 'efficiency_calc') {
    $selectValue = "SUM(consumption_kwh) / NULLIF(SUM(distance_km), 0) * 100";
} elseif ($metric === 'cost_per_kwh') {
    $selectValue = "SUM(cost) / NULLIF(SUM(kwh_amount), 0)";
} else {
    $cleanMetric = preg_replace('/[^a-zA-Z0-9_]/', '', $metric);
    $selectValue = "$aggregate($cleanMetric)";
}

$sql = "SELECT " . ($groupSql[$groupBy] ?? "DATE($dateField)") . " as label, $selectValue as value FROM $source_table $whereSql GROUP BY label ORDER BY label ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

$labels = array_column($data, 'label');
$values = array_column($data, 'value');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EV Log - Analisi Avanzata</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50">
    <header class="bg-[#182871] text-white p-5 sticky top-0 z-50 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold italic">EV <span class="font-light not-italic text-blue-300">LOG</span></h1>
            <nav class="flex gap-4">
                <a href="index.php" class="text-white/60 hover:text-white transition text-sm uppercase tracking-tighter">Viaggi</a>
                <a href="analytics.php" class="text-blue-200 font-bold border-b-2 border-blue-400 pb-1 text-sm uppercase tracking-tighter">Analisi</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-4 mt-8 pb-20">
        <!-- Sorgente Dati (NUOVO) -->
        <div class="flex gap-2 mb-6">
            <a href="?source=trips" class="px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition <?php echo $source_table === 'trips' ? 'bg-[#182871] text-white' : 'bg-white text-slate-400 shadow-sm'; ?>">Viaggi</a>
            <a href="?source=charges" class="px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition <?php echo $source_table === 'charges' ? 'bg-emerald-600 text-white' : 'bg-white text-slate-400 shadow-sm'; ?>">Ricariche</a>
        </div>

        <!-- Filtri (RIPRISTINATI) -->
        <div class="bg-white p-4 rounded-2xl shadow-sm mb-6 border border-slate-100 flex flex-wrap gap-4 items-end">
            <form id="topFilters" method="GET" class="flex flex-wrap gap-4 items-end">
                <input type="hidden" name="source" value="<?php echo $source_table; ?>">
                <div class="flex flex-col">
                    <span class="text-[9px] font-bold text-slate-400 uppercase ml-1">Inizio</span>
                    <input type="date" name="date_start" value="<?php echo $date_start; ?>" class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 outline-none">
                </div>
                <div class="flex flex-col">
                    <span class="text-[9px] font-bold text-slate-400 uppercase ml-1">Fine</span>
                    <input type="date" name="date_end" value="<?php echo $date_end; ?>" class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 outline-none">
                </div>
                <?php if($source_table === 'trips'): ?>
                <select name="category" onchange="this.form.submit()" class="text-sm border border-slate-200 rounded-lg px-3 py-1.5 bg-white outline-none">
                    <option value="">Tutte le categorie</option>
                    <option value="Lavoro" <?php echo $filter_category == 'Lavoro' ? 'selected' : ''; ?>>Lavoro</option>
                    <option value="Personale" <?php echo $filter_category == 'Personale' ? 'selected' : ''; ?>>Personale</option>
                </select>
                <label class="flex items-center gap-2 text-sm font-bold text-slate-600 mb-1 cursor-pointer">
                    <input type="checkbox" name="only_favs" value="1" <?php echo $only_favs ? 'checked' : ''; ?> onchange="this.form.submit()" class="w-4 h-4 rounded text-blue-600"> Preferiti
                </label>
                <?php endif; ?>
                <button type="submit" class="bg-[#182871] text-white px-6 py-1.5 rounded-xl text-xs font-black uppercase shadow-md transition active:scale-95">Applica</button>
            </form>
        </div>

        <div class="flex flex-col xl:flex-row gap-8">
            <!-- Sidebar Parametri BI (RIPRISTINATO) -->
            <div class="w-full xl:w-80 shrink-0">
                <div class="bg-white p-6 rounded-[2rem] shadow-xl border border-slate-100 sticky top-28">
                    <h2 class="text-lg font-black text-[#182871] mb-6 uppercase tracking-tight">Parametri Grafico</h2>
                    <form method="GET" class="space-y-4">
                        <input type="hidden" name="source" value="<?php echo $source_table; ?>">
                        <input type="hidden" name="date_start" value="<?php echo $date_start; ?>">
                        <input type="hidden" name="date_end" value="<?php echo $date_end; ?>">
                        <input type="hidden" name="category" value="<?php echo $filter_category; ?>">
                        <input type="hidden" name="only_favs" value="<?php echo $only_favs ? '1' : '0'; ?>">

                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase ml-1">Raggruppa Per</label>
                            <select name="group_by" onchange="this.form.submit()" class="w-full mt-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold">
                                <option value="date" <?php echo $groupBy == 'date' ? 'selected' : ''; ?>>Giorno</option>
                                <option value="week" <?php echo $groupBy == 'week' ? 'selected' : ''; ?>>Settimana</option>
                                <option value="month" <?php echo $groupBy == 'month' ? 'selected' : ''; ?>>Mese</option>
                                <?php if($source_table === 'trips'): ?><option value="category" <?php echo $groupBy == 'category' ? 'selected' : ''; ?>>Categoria</option><?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase ml-1">Metrica</label>
                            <select name="metric" onchange="this.form.submit()" class="w-full mt-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold">
                                <?php if($source_table === 'trips'): ?>
                                    <option value="distance_km" <?php echo $metric == 'distance_km' ? 'selected' : ''; ?>>Distanza (km)</option>
                                    <option value="consumption_kwh" <?php echo $metric == 'consumption_kwh' ? 'selected' : ''; ?>>Consumo (kWh)</option>
                                    <option value="efficiency_calc" <?php echo $metric == 'efficiency_calc' ? 'selected' : ''; ?>>Efficienza</option>
                                <?php else: ?>
                                    <option value="cost" <?php echo $metric == 'cost' ? 'selected' : ''; ?>>Costo Totale (€)</option>
                                    <option value="kwh_amount" <?php echo $metric == 'kwh_amount' ? 'selected' : ''; ?>>Energia Caricata (kWh)</option>
                                    <option value="cost_per_kwh" <?php echo $metric == 'cost_per_kwh' ? 'selected' : ''; ?>>€/kWh Medio</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase ml-1">Tipo Grafico</label>
                            <select name="chart_type" onchange="this.form.submit()" class="w-full mt-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold">
                                <option value="line" <?php echo $chartType == 'line' ? 'selected' : ''; ?>>Linee</option>
                                <option value="bar" <?php echo $chartType == 'bar' ? 'selected' : ''; ?>>Barre</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Area Grafico (RIPRISTINATO) -->
            <div class="flex-grow space-y-6">
                <div class="<?php echo $source_table === 'trips' ? 'bg-[#182871]' : 'bg-emerald-600'; ?> rounded-[2rem] p-6 text-white shadow-xl flex justify-between items-center transition-colors">
                    <div>
                        <p class="text-[10px] font-bold opacity-60 uppercase tracking-widest">Dashboard Analisi</p>
                        <h3 class="text-xl font-black uppercase"><?php echo str_replace(['_', 'calc', 'per'], [' ', '', '/'], $metric); ?></h3>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold opacity-60 uppercase">Campioni</p>
                        <p class="text-2xl font-black"><?php echo count($data); ?></p>
                    </div>
                </div>
                <div class="bg-white p-8 rounded-[2.5rem] shadow-xl border border-slate-100 min-h-[500px]">
                    <canvas id="biChart"></canvas>
                </div>
            </div>
        </div>
    </main>

    <script>
        const ctx = document.getElementById('biChart');
        if (ctx) {
            new Chart(ctx, {
                type: '<?php echo $chartType; ?>',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: 'Valore',
                        data: <?php echo json_encode($values); ?>,
                        borderColor: '<?php echo $source_table === "trips" ? "#182871" : "#10b981"; ?>',
                        backgroundColor: '<?php echo $source_table === "trips" ? "rgba(24, 40, 113, 0.2)" : "rgba(16, 185, 129, 0.2)"; ?>',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } }
                }
            });
        }
    </script>
</body>
</html>