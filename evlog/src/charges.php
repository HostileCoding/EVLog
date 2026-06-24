<?php
require_once 'config.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_charge') {
    $charge_date = $_POST['charge_date'] ?? '';
    $kwh_amount = $_POST['kwh_amount'] ?? '';
    $cost = $_POST['cost'] ?? '';
    $notes = $_POST['notes'] ?? '';

    // Validazione
    if (empty($charge_date)) {
        $error_message = "La data della ricarica è obbligatoria.";
    } elseif (empty($kwh_amount)) {
        $error_message = "La quantità di kWh è obbligatoria.";
    } else {
        $kwh_val = (float)str_replace(',', '.', $kwh_amount);
        $cost_val = ($cost === '') ? 0.00 : (float)str_replace(',', '.', $cost);

        if ($kwh_val <= 0) {
            $error_message = "La quantità di kWh deve essere maggiore di 0.";
        } elseif ($cost_val < 0) {
            $error_message = "Il costo non può essere negativo.";
        } else {
            try {
                $date_obj = new DateTime($charge_date);
                $formatted_date = $date_obj->format('Y-m-d H:i:s');

                $stmt = $pdo->prepare("INSERT INTO charges (charge_date, kwh_amount, cost, notes) VALUES (?, ?, ?, ?)");
                $stmt->execute([$formatted_date, $kwh_val, $cost_val, empty($notes) ? null : $notes]);
                $success_message = "Ricarica inserita con successo!";
            } catch (Exception $e) {
                $error_message = "Errore durante il salvataggio: " . $e->getMessage();
            }
        }
    }
}

// Estrazione ricariche per lo storico
$charges = [];
try {
    $stmt = $pdo->query("SELECT * FROM charges ORDER BY charge_date DESC");
    $charges = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = "Errore nel caricamento dello storico: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EV Log - Gestione Ricariche</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-slate-50">
    <header class="bg-[#182871] text-white p-5 sticky top-0 z-50 shadow-lg">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold italic">EV <span class="font-light not-italic text-blue-300">LOG</span></h1>
            <nav class="flex gap-4">
                <a href="index.php" class="text-white/60 hover:text-white transition text-sm uppercase tracking-tighter">Viaggi</a>
                <a href="charges.php" class="text-blue-200 font-bold border-b-2 border-blue-400 pb-1 text-sm uppercase tracking-tighter">Ricariche</a>
                <a href="analytics.php" class="text-white/60 hover:text-white transition text-sm uppercase tracking-tighter">Analisi</a>
            </nav>
        </div>
    </header>

    <main class="container mx-auto px-4 mt-8 pb-20">
        <!-- Banner Sezione -->
        <div class="bg-gradient-to-r from-emerald-600 to-teal-500 rounded-[2rem] p-6 text-white shadow-xl flex justify-between items-center mb-8">
            <div>
                <p class="text-[10px] font-bold opacity-75 uppercase tracking-widest">Dashboard Ricariche</p>
                <h3 class="text-xl font-black uppercase">Gestione sessioni di ricarica</h3>
            </div>
            <div class="text-right">
                <p class="text-[10px] font-bold opacity-75 uppercase">Totale Ricariche</p>
                <p class="text-2xl font-black"><?php echo count($charges); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Form Aggiungi Ricarica -->
            <div class="lg:col-span-1">
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-slate-100">
                    <h2 class="text-lg font-black text-slate-800 mb-6 uppercase tracking-tight flex items-center gap-2">
                        <i class="fas fa-plus-circle text-emerald-600"></i> Aggiungi Ricarica
                    </h2>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="bg-red-50 text-red-700 p-4 rounded-xl text-xs font-bold mb-4 border border-red-100">
                            <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                        <div class="bg-emerald-50 text-emerald-700 p-4 rounded-xl text-xs font-bold mb-4 border border-emerald-100">
                            <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <form action="charges.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="add_charge">
                        
                        <div>
                            <label for="charge_date" class="text-[10px] font-black text-slate-400 uppercase ml-1 block mb-1">Data e Ora *</label>
                            <input type="datetime-local" id="charge_date" name="charge_date" required
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>

                        <div>
                            <label for="kwh_amount" class="text-[10px] font-black text-slate-400 uppercase ml-1 block mb-1">Quantità kWh caricati *</label>
                            <input type="number" id="kwh_amount" name="kwh_amount" step="0.01" min="0.01" required placeholder="es. 45.5"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>

                        <div>
                            <label for="cost" class="text-[10px] font-black text-slate-400 uppercase ml-1 block mb-1">Costo Totale (€) <span class="text-slate-300 font-normal">(opzionale)</span></label>
                            <input type="number" id="cost" name="cost" step="0.01" min="0" placeholder="es. 12.30"
                                   class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>

                        <div>
                            <label for="notes" class="text-[10px] font-black text-slate-400 uppercase ml-1 block mb-1">Note <span class="text-slate-300 font-normal">(opzionale)</span></label>
                            <textarea id="notes" name="notes" placeholder="Aggiungi dettagli (es. Posizione, Operatore...)" rows="3"
                                      class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-emerald-500 outline-none resize-none"></textarea>
                        </div>

                        <button type="submit" 
                                class="w-full bg-emerald-600 text-white py-4 rounded-xl font-black uppercase tracking-widest text-xs shadow-lg hover:bg-emerald-700 transition active:scale-95 flex items-center justify-center gap-2 mt-6">
                            <i class="fas fa-bolt"></i> Aggiungi Ricarica
                        </button>
                    </form>
                </div>
            </div>

            <!-- Storico Ricariche -->
            <div class="lg:col-span-2">
                <div class="bg-white p-8 rounded-[2rem] shadow-xl border border-slate-100 min-h-[500px]">
                    <h2 class="text-lg font-black text-slate-800 mb-6 uppercase tracking-tight flex items-center gap-2">
                        <i class="fas fa-history text-emerald-600"></i> Storico Sessioni
                    </h2>

                    <?php if (empty($charges)): ?>
                        <div class="flex flex-col items-center justify-center py-20 text-slate-400">
                            <i class="fas fa-charging-station text-5xl mb-4 text-slate-200"></i>
                            <p class="font-bold text-sm uppercase tracking-wider">Nessuna ricarica registrata</p>
                            <p class="text-xs text-slate-400 mt-1">Inserisci la tua prima ricarica usando il modulo a sinistra.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 max-h-[600px] overflow-y-auto pr-2">
                            <?php foreach ($charges as $charge): 
                                $dt = new DateTime($charge['charge_date']);
                                $cost_display = ($charge['cost'] > 0) ? '€ ' . number_format($charge['cost'], 2, ',', '.') : 'Gratis / N.S.';
                            ?>
                                <div class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100 flex justify-between items-center hover:shadow-sm transition">
                                    <div class="flex gap-4 items-center">
                                        <div class="bg-emerald-50 w-12 h-12 rounded-xl flex items-center justify-center border border-emerald-100 text-emerald-600 text-lg">
                                            <i class="fas fa-plug"></i>
                                        </div>
                                        <div>
                                            <p class="font-black text-slate-800 text-sm">
                                                <?php echo $dt->format('d/m/Y'); ?> <span class="text-slate-400 font-normal">alle <?php echo $dt->format('H:i'); ?></span>
                                            </p>
                                            <p class="text-[11px] text-slate-400 font-bold uppercase tracking-tight mt-0.5">
                                                <?php echo !empty($charge['notes']) ? htmlspecialchars($charge['notes']) : 'Nessuna nota aggiuntiva'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-black text-emerald-600 text-base"><?php echo number_format($charge['kwh_amount'], 2, ',', '.'); ?> <span class="text-xs font-normal">kWh</span></p>
                                        <p class="text-[11px] font-black text-slate-500 uppercase mt-0.5"><?php echo $cost_display; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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

    <script>
        // Imposta la data ed ora locale corrente come default dell'input datetime-local se vuoto
        document.addEventListener("DOMContentLoaded", function() {
            const dateInput = document.getElementById("charge_date");
            if (dateInput && !dateInput.value) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                dateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
            }
        });
    </script>
</body>
</html>
