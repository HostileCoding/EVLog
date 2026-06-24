<?php
require_once 'config.php';

// AJAX: SALVATAGGIO NOTE, CATEGORIA E PREFERITI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $tripId = (int)$_POST['trip_id'];
    header('Content-Type: application/json');

    if ($_POST['action'] === 'save_trip_details') {
        $notes = $_POST['notes'] ?? '';
        $category = $_POST['category'] ?? 'Non Assegnato';
        $updateStmt = $pdo->prepare("UPDATE trips SET notes = ?, category = ? WHERE id = ?");
        $success = $updateStmt->execute([$notes, $category, $tripId]);
        echo json_encode(['success' => $success]);
    } 
    elseif ($_POST['action'] === 'toggle_favorite') {
        $status = (int)$_POST['status'];
        $updateStmt = $pdo->prepare("UPDATE trips SET is_favorite = ? WHERE id = ?");
        $success = $updateStmt->execute([$status, $tripId]);
        echo json_encode(['success' => $success]);
    }
    exit;
}

// Gestione Importazione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['csv_file']['tmp_name'];
        $rawContent = file_get_contents($tmpName);
        if (strpos($rawContent, "\0") !== false) $rawContent = str_replace("\0", "", $rawContent);
        $rawContent = preg_replace('/^\xEF\xBB\xBF/', '', $rawContent);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $rawContent);
        rewind($handle);
        $rowNumber = 0;
        $stmt = $pdo->prepare("INSERT IGNORE INTO trips (category, start_time, start_odometer, start_address, end_time, end_odometer, end_address, duration_minutes, distance_km, consumption_kwh, title, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        while (($row = fgetcsv($handle, 4000, ";")) !== FALSE) {
            $rowNumber++;
            if ($rowNumber === 1 || count($row) < 10) continue;
            $row = array_map('clean_csv_string', $row);
            $cat = !empty($row[0]) ? $row[0] : 'Non Assegnato';
            $startDt = DateTime::createFromFormat('d/m/y H:i', $row[1]);
            if (!$startDt) continue;
            $endDt = DateTime::createFromFormat('d/m/y H:i', $row[4]);
            $durationStr = str_ireplace(' ore', '', $row[7]);
            $parts = explode(':', $durationStr);
            $mins = (count($parts) == 2) ? ((int)$parts[0] * 60) + (int)$parts[1] : 0;
            $dist = (float)str_replace(',', '.', $row[8]);
            $cons = (float)str_replace(',', '.', $row[9]);
            $stmt->execute([$cat, $startDt->format('Y-m-d H:i:s'), (int)$row[2], $row[3], $endDt ? $endDt->format('Y-m-d H:i:s') : null, (int)$row[5], $row[6], $mins, $dist, $cons, $row[10] ?? null, $row[11] ?? null]);
        }
        fclose($handle);
        header("Location: index.php");
        exit;
    }
}

// PARAMETRI PAGINAZIONE E ORDINAMENTO
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [20, 50, 100])) $limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$sort_by = $_GET['sort_by'] ?? 'start_time';
$sort_order = $_GET['sort_order'] ?? 'DESC';

$allowed_sort = [
    'start_time' => 'start_time',
    'distance_km' => 'distance_km',
    'duration_minutes' => 'duration_minutes',
    'consumption_kwh' => 'consumption_kwh',
    'speed' => '(distance_km / (NULLIF(duration_minutes, 0)/60))'
];
$orderBySql = $allowed_sort[$sort_by] ?? 'start_time';
$orderDirSql = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

// FILTRI
$date_start = $_GET['date_start'] ?? '';
$date_end = $_GET['date_end'] ?? '';
$filter_category = $_GET['category'] ?? '';
$only_favs = isset($_GET['only_favs']) && $_GET['only_favs'] == '1';

$clauses = ["1=1"];
$params = [];
if ($date_start) { $clauses[] = "start_time >= ?"; $params[] = $date_start . " 00:00:00"; }
if ($date_end) { $clauses[] = "start_time <= ?"; $params[] = $date_end . " 23:59:59"; }
if ($filter_category) { $clauses[] = "category = ?"; $params[] = $filter_category; }
if ($only_favs) { $clauses[] = "is_favorite = 1"; }
$whereSql = "WHERE " . implode(" AND ", $clauses);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM trips $whereSql");
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$statsStmt = $pdo->prepare("SELECT SUM(distance_km) as total_km, SUM(consumption_kwh) as total_kwh, AVG(distance_km / (NULLIF(duration_minutes, 0)/60)) as avg_speed FROM trips $whereSql");
$statsStmt->execute($params);
$stats = $statsStmt->fetch();
$globalAvgEff = ($stats['total_km'] > 0) ? ($stats['total_kwh'] / $stats['total_km'] * 100) : 0;

$listStmt = $pdo->prepare("SELECT *, (consumption_kwh / NULLIF(distance_km,0) * 100) as eff, (distance_km / (NULLIF(duration_minutes, 0)/60)) as speed FROM trips $whereSql ORDER BY $orderBySql $orderDirSql LIMIT $limit OFFSET $offset");
$listStmt->execute($params);
$trips = $listStmt->fetchAll();

// Estrai categorie uniche per il filtro
$catSelectStmt = $pdo->query("SELECT DISTINCT category FROM trips WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$allCategories = $catSelectStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EV Log Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-slate-50">
    <header class="bg-[#182871] text-white p-5 sticky top-0 z-50 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold italic">EV <span class="font-light not-italic text-blue-300">LOG</span></h1>
            <nav class="flex gap-4">
                <a href="index.php" class="text-blue-200 font-bold border-b-2 border-blue-400 pb-1 text-sm uppercase tracking-tighter">Viaggi</a>
                <a href="charges.php" class="text-white/60 hover:text-white transition text-sm uppercase tracking-tighter">Ricariche</a>
                <a href="analytics.php" class="text-white/60 hover:text-white transition text-sm uppercase tracking-tighter">Analisi</a>
            </nav>
        </div>
    </header>

    <div class="bg-white border-b border-slate-200 py-3 shadow-sm">
        <div class="container mx-auto px-4 flex justify-end">
            <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="bg-blue-600 text-white px-6 py-2 rounded-xl text-xs font-black uppercase tracking-widest shadow-md hover:bg-blue-700 transition active:scale-95">
                <i class="fas fa-file-import mr-2"></i> Importa Nuovi Viaggi (CSV)
            </button>
        </div>
    </div>

    <main class="container mx-auto px-4 mt-6">
        <!-- Filtri Aggiornati -->
        <div class="bg-white p-4 rounded-2xl shadow-sm mb-6 border border-slate-100">
            <form class="flex flex-wrap gap-4 items-end">
                <div class="flex flex-col">
                    <span class="text-[9px] font-bold text-slate-400 uppercase ml-1">Data Inizio</span>
                    <input type="date" name="date_start" value="<?php echo htmlspecialchars($date_start); ?>" class="text-sm border rounded-lg px-2 py-1">
                </div>
                <div class="flex flex-col">
                    <span class="text-[9px] font-bold text-slate-400 uppercase ml-1">Data Fine</span>
                    <input type="date" name="date_end" value="<?php echo htmlspecialchars($date_end); ?>" class="text-sm border rounded-lg px-2 py-1">
                </div>
                <div class="flex flex-col min-w-[140px]">
                    <span class="text-[9px] font-bold text-slate-400 uppercase ml-1">Categoria</span>
                    <select name="category" class="text-sm border rounded-lg px-2 py-1 bg-white" onchange="this.form.submit()">
                        <option value="">Tutte le categorie</option>
                        <option value="Non Assegnato" <?php echo $filter_category == 'Non Assegnato' ? 'selected' : ''; ?>>Non Assegnato</option>
                        <option value="Lavoro" <?php echo $filter_category == 'Lavoro' ? 'selected' : ''; ?>>Lavoro</option>
                        <option value="Personale" <?php echo $filter_category == 'Personale' ? 'selected' : ''; ?>>Personale</option>
                        <option value="Altro" <?php echo $filter_category == 'Altro' ? 'selected' : ''; ?>>Altro</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm font-bold text-slate-600 mb-1 cursor-pointer">
                    <input type="checkbox" name="only_favs" value="1" <?php echo $only_favs ? 'checked' : ''; ?> onchange="this.form.submit()"> Solo Preferiti
                </label>
                <button type="submit" class="bg-[#182871] text-white px-6 py-2 rounded-xl text-sm font-bold shadow-md hover:opacity-90 transition">Filtra</button>
                <a href="index.php" class="text-slate-400 text-[10px] font-bold uppercase mb-2 hover:text-slate-600">Reset</a>
            </form>
        </div>

        <!-- Cards Statistiche -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Distanza Totale</span>
                <p class="text-2xl font-black text-[#182871]"><?php echo number_format($stats['total_km'] ?? 0, 1, ',', '.'); ?> <span class="text-xs font-normal">km</span></p>
            </div>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Efficienza Media</span>
                <p class="text-2xl font-black text-emerald-600"><?php echo number_format($globalAvgEff, 1, ',', '.'); ?> <span class="text-xs font-normal">kWh/100</span></p>
            </div>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Velocità Media</span>
                <p class="text-2xl font-black text-[#182871]"><?php echo number_format($stats['avg_speed'] ?? 0, 0, ',', '.'); ?> <span class="text-xs font-normal">km/h</span></p>
            </div>
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
                <span class="text-[10px] font-bold text-slate-400 uppercase block mb-1">Viaggi Trovati</span>
                <p class="text-2xl font-black text-slate-700"><?php echo $totalRecords; ?></p>
            </div>
        </div>

        <!-- Toolbar Lista -->
        <div class="bg-slate-200/50 p-3 rounded-2xl mb-4 flex flex-wrap gap-4 items-center justify-between">
            <div class="flex items-center gap-3">
                <label class="text-[10px] font-black text-slate-500 uppercase">Mostra</label>
                <select onchange="window.location.href=this.value" class="text-xs font-bold border-none rounded-lg bg-white px-2 py-1 shadow-sm">
                    <option value="<?php echo filterUrl(['limit' => 20, 'page' => 1]); ?>" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                    <option value="<?php echo filterUrl(['limit' => 50, 'page' => 1]); ?>" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="<?php echo filterUrl(['limit' => 100, 'page' => 1]); ?>" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-[10px] font-black text-slate-500 uppercase mr-1">Ordina per</label>
                <?php 
                $opts = ['start_time' => 'Data', 'distance_km' => 'Km', 'duration_minutes' => 'Min', 'consumption_kwh' => 'kWh', 'speed' => 'Vel'];
                foreach ($opts as $k => $l): 
                    $act = ($sort_by === $k);
                    $next = ($act && $sort_order === 'DESC') ? 'ASC' : 'DESC';
                ?>
                <a href="<?php echo filterUrl(['sort_by' => $k, 'sort_order' => $next, 'page' => 1]); ?>" 
                   class="text-[10px] font-bold px-3 py-1 rounded-lg <?php echo $act ? 'bg-[#182871] text-white shadow-md' : 'bg-white text-slate-600 hover:bg-slate-100'; ?>">
                    <?php echo $l; ?> <?php if($act) echo ($sort_order === 'DESC' ? '↓' : '↑'); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Lista Viaggi -->
        <div class="space-y-3 mb-10">
            <?php foreach ($trips as $trip): 
                $start = new DateTime($trip['start_time']);
                $displayCat = (!empty($trip['category']) && $trip['category'] !== 'null') ? $trip['category'] : 'Non Assegnato';
                $jsonTrip = htmlspecialchars(json_encode($trip), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex justify-between items-center group relative overflow-hidden cursor-pointer hover:shadow-md transition active:scale-[0.98]" 
                 onclick='openTripModal(<?php echo $jsonTrip; ?>)'>
                <?php if($trip['is_favorite']): ?>
                    <div class="absolute top-0 right-0 p-1.5 bg-yellow-400 rounded-bl-xl text-[10px] text-white shadow-sm"><i class="fas fa-star"></i></div>
                <?php endif; ?>
                <div class="flex gap-4 items-center">
                    <div class="bg-slate-50 w-12 h-12 rounded-xl flex flex-col items-center justify-center border border-slate-100">
                        <span class="text-[9px] font-bold text-slate-400 uppercase leading-none mb-1"><?php echo $start->format('M'); ?></span>
                        <span class="text-lg font-black leading-none"><?php echo $start->format('d'); ?></span>
                    </div>
                    <div>
                        <p class="font-black text-slate-800 text-base"><?php echo number_format($trip['distance_km'],1, ',', '.'); ?> km</p>
                        <p class="text-[11px] text-slate-400 font-bold uppercase tracking-tight">
                            <?php echo $displayCat; ?> • <?php echo $trip['duration_minutes']; ?> min
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-black text-[#182871] text-lg"><?php echo number_format($trip['consumption_kwh'],1, ',', '.'); ?> <span class="text-xs font-normal">kWh</span></p>
                    <p class="text-[11px] font-black <?php echo $trip['eff'] > 20 ? 'text-orange-500' : 'text-emerald-500'; ?> uppercase"><?php echo number_format($trip['eff'],1, ',', '.'); ?> / 100km</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Paginazione -->
        <?php if($totalPages > 1): ?>
        <div class="mt-8 mb-20 flex justify-center items-center gap-2">
            <?php for($i = 1; $i <= $totalPages; $i++): if($i < $page-2 || $i > $page+2) continue; ?>
                <a href="<?php echo filterUrl(['page' => $i]); ?>" class="w-10 h-10 flex items-center justify-center rounded-xl font-bold shadow-sm transition <?php echo ($i === $page) ? 'bg-[#182871] text-white' : 'bg-white text-slate-500 hover:bg-slate-100'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="bg-[#182871] text-white p-8 mt-auto shadow-inner">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="text-center md:text-left">
                <h2 class="text-lg font-black italic">EV <span class="font-light not-italic text-blue-300">LOG</span></h2>
                <p class="text-[10px] uppercase font-bold text-white/40 tracking-widest mt-1">Dashboard Analisi Viaggi Elettrici</p>
            </div>
            <a href="https://www.paypal.com/paypalme/HostileCoding" target="_blank" class="coin-btn flex items-center gap-3 px-6 py-3 rounded-full text-[#182871] font-black text-sm uppercase tracking-tighter">
                <i class="fas fa-coins text-xl"></i> Offrimi un caffè
            </a>
        </div>
    </footer>

    <!-- MODALE DETTAGLI -->
    <div id="tripModal" class="hidden fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-[200] flex items-center justify-center p-4" onclick="closeTripModal(event)">
        <div class="bg-white rounded-[2.5rem] w-full max-w-lg shadow-2xl overflow-hidden animate-in" onclick="event.stopPropagation()">
            <div id="modalHeader" class="bg-[#182871] p-6 text-white relative">
                <button onclick="closeTripModal(event)" class="absolute top-6 right-6 text-white/50 hover:text-white transition"><i class="fas fa-times text-xl"></i></button>
                <div class="flex items-center gap-4">
                    <div id="modalDateBox" class="bg-white/10 p-3 rounded-2xl text-center min-w-[60px]">
                        <p id="mDateDay" class="text-2xl font-black leading-none">00</p>
                        <p id="mDateMonth" class="text-[10px] font-bold uppercase tracking-widest opacity-70">---</p>
                    </div>
                    <div>
                        <h2 class="text-xl font-black leading-tight uppercase tracking-tight">Dettaglio Viaggio</h2>
                        <div class="mt-1">
                            <select id="mCategory" class="bg-blue-900/40 text-blue-100 border-none rounded-lg text-[10px] font-bold uppercase px-2 py-1 outline-none focus:ring-1 focus:ring-blue-400 cursor-pointer">
                                <option value="Non Assegnato">Non Assegnato</option>
                                <option value="Lavoro">Lavoro</option>
                                <option value="Personale">Personale</option>
                                <option value="Altro">Altro</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-8 space-y-6">
                <div class="space-y-4">
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Partenza</p>
                        <p id="mStartAddr" class="text-sm font-bold text-slate-700">---</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Destinazione</p>
                        <p id="mEndAddr" class="text-sm font-bold text-slate-700">---</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 border-y border-slate-50 py-6 text-center">
                    <div><span class="text-[9px] font-black text-slate-400 uppercase">Km</span><p id="mDist" class="text-lg font-black text-[#182871]">0</p></div>
                    <div><span class="text-[9px] font-black text-slate-400 uppercase">kWh</span><p id="mCons" class="text-lg font-black text-[#182871]">0</p></div>
                    <div><span class="text-[9px] font-black text-slate-400 uppercase">Eff.</span><p id="mEff" class="text-lg font-black text-emerald-600">0</p></div>
                </div>

                <div>
                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Note Viaggio</p>
                    <textarea id="mNotes" placeholder="Aggiungi dettagli sul percorso o soste..." class="w-full bg-slate-50 border border-slate-100 rounded-2xl p-4 text-sm focus:ring-2 focus:ring-blue-600 outline-none h-24 resize-none"></textarea>
                </div>

                <div class="flex gap-3">
                    <button onclick="saveAllChanges()" id="saveBtn" class="flex-grow bg-blue-600 text-white py-4 rounded-2xl font-black uppercase tracking-widest text-xs shadow-lg hover:bg-blue-700 transition active:scale-95 flex items-center justify-center gap-2">
                        <i class="fas fa-save"></i> Salva Modifiche
                    </button>
                    <button onclick="toggleFavFromModal()" id="mFavBtn" class="w-14 bg-slate-100 text-slate-400 rounded-2xl hover:text-yellow-500 transition flex items-center justify-center text-xl">
                        <i class="fas fa-star"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modale Import -->
    <div id="uploadModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-md flex items-center justify-center z-[210] p-4">
        <div class="bg-white rounded-[2rem] w-full max-sm p-8 shadow-2xl">
            <h2 class="text-xl font-black mb-6 text-center text-slate-800 uppercase tracking-tight">Importa CSV</h2>
            <form action="index.php" method="post" enctype="multipart/form-data">
                <div class="mb-6 border-2 border-dashed border-slate-200 rounded-2xl p-10 text-center bg-slate-50 relative">
                    <input type="file" name="csv_file" accept=".csv" required class="absolute inset-0 opacity-0 cursor-pointer">
                    <i class="fas fa-file-csv text-4xl text-slate-300 mb-2"></i>
                    <p class="text-xs font-bold text-slate-400 uppercase">Trascina o clicca</p>
                </div>
                <button type="submit" class="w-full bg-[#182871] text-white py-4 rounded-2xl font-black shadow-lg uppercase tracking-widest">Avvia Import</button>
            </form>
            <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="w-full mt-4 text-slate-400 font-bold text-xs uppercase tracking-widest">Annulla</button>
        </div>
    </div>

    <script>
        let currentTrip = null;

        function openTripModal(trip) {
            currentTrip = trip;
            const date = new Date(trip.start_time.replace(' ', 'T'));
            document.getElementById('mDateDay').innerText = date.getDate();
            document.getElementById('mDateMonth').innerText = date.toLocaleString('it-IT', { month: 'short' });
            
            const categorySelect = document.getElementById('mCategory');
            const currentCat = (trip.category && trip.category.trim() !== "" && trip.category !== "null") 
                               ? trip.category 
                               : "Non Assegnato";
            
            categorySelect.value = currentCat;
            if (categorySelect.selectedIndex === -1) categorySelect.value = "Non Assegnato";
            
            document.getElementById('mStartAddr').innerText = trip.start_address;
            document.getElementById('mEndAddr').innerText = trip.end_address;
            document.getElementById('mDist').innerText = parseFloat(trip.distance_km).toFixed(1) + ' km';
            document.getElementById('mCons').innerText = parseFloat(trip.consumption_kwh).toFixed(1) + ' kWh';
            document.getElementById('mEff').innerText = (trip.consumption_kwh / (trip.distance_km || 1) * 100).toFixed(1);
            document.getElementById('mNotes').value = trip.notes || '';
            
            const favBtn = document.getElementById('mFavBtn');
            if(trip.is_favorite == 1) {
                favBtn.classList.add('text-yellow-500');
                favBtn.classList.remove('text-slate-400');
            } else {
                favBtn.classList.remove('text-yellow-500');
                favBtn.classList.add('text-slate-400');
            }
            document.getElementById('tripModal').classList.remove('hidden');
        }

        function closeTripModal(e) { document.getElementById('tripModal').classList.add('hidden'); currentTrip = null; }

        async function saveAllChanges() {
            if(!currentTrip) return;
            const saveBtn = document.getElementById('saveBtn');
            const originalContent = saveBtn.innerHTML;
            saveBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Salvataggio...';
            saveBtn.disabled = true;

            const notes = document.getElementById('mNotes').value;
            const category = document.getElementById('mCategory').value;

            const formData = new FormData();
            formData.append('action', 'save_trip_details');
            formData.append('trip_id', currentTrip.id);
            formData.append('notes', notes);
            formData.append('category', category);

            try {
                const response = await fetch('index.php', { method: 'POST', body: formData });
                const result = await response.json();
                if(result.success) {
                    saveBtn.classList.replace('bg-blue-600', 'bg-emerald-500');
                    saveBtn.innerHTML = '<i class="fas fa-check"></i> Salvato!';
                    setTimeout(() => window.location.reload(), 800);
                }
            } catch (err) {
                saveBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Errore';
                saveBtn.classList.replace('bg-blue-600', 'bg-red-500');
                setTimeout(() => {
                    saveBtn.innerHTML = originalContent;
                    saveBtn.classList.replace('bg-red-500', 'bg-blue-600');
                    saveBtn.disabled = false;
                }, 2000);
            }
        }

        async function toggleFavFromModal() {
            if(!currentTrip) return;
            const newStatus = currentTrip.is_favorite == 1 ? 0 : 1;
            const formData = new FormData();
            formData.append('action', 'toggle_favorite');
            formData.append('trip_id', currentTrip.id);
            formData.append('status', newStatus);
            const response = await fetch('index.php', { method: 'POST', body: formData });
            const result = await response.json();
            if(result.success) {
                currentTrip.is_favorite = newStatus;
                const favBtn = document.getElementById('mFavBtn');
                favBtn.classList.toggle('text-yellow-500');
                favBtn.classList.toggle('text-slate-400');
            }
        }
    </script>
</body>
</html>
