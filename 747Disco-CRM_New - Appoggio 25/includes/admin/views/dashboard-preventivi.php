<?php
/**
 * Template Dashboard Preventivi - 747 Disco CRM - VERSIONE CORRETTA
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      11.7.0-FIXED
 */

if (!defined('ABSPATH')) {
    exit;
}

// CSS Stili dashboard
echo '<style>
.disco747-dashboard-preventivi .notice,
.disco747-dashboard-preventivi .updated,
.disco747-dashboard-preventivi .error {
    background: #fff3cd !important;
    border-left: 4px solid #c28a4d !important;
    color: #2b1e1a !important;
    padding: 12px 15px !important;
    margin: 15px 0 !important;
    border-radius: 6px !important;
    box-shadow: 0 2px 5px rgba(43, 30, 26, 0.1) !important;
    font-size: 14px !important;
}

.disco747-dashboard-preventivi .notice-error {
    background: #f8d7da !important;
    border-left-color: #dc3545 !important;
    color: #721c24 !important;
}

.disco747-dashboard-preventivi .notice-success,
.disco747-dashboard-preventivi .updated {
    background: #d4edda !important;
    border-left-color: #28a745 !important;
    color: #155724 !important;
}

@media (max-width: 768px) {
    .disco747-filtri-mobile {
        grid-template-columns: 1fr !important;
    }
}
</style>';

// Wrapper principale
echo '<div class="disco747-dashboard-preventivi">';

// Inizializza plugin e componenti
$disco747_crm = disco747_crm();
$gdrive_sync = $disco747_crm ? $disco747_crm->get_gdrive_sync() : null;
$database = $disco747_crm ? $disco747_crm->get_database() : null;

// Variabili per dati
$preventivi_raw = [];
$statistics = ['total' => 0, 'confermati' => 0, 'annullati' => 0, 'attivi' => 0];
$data_source = 'none';

// STRATEGIA: Prova Google Drive prima, poi fallback a database
if ($gdrive_sync && $gdrive_sync->is_available()) {
    try {
        $preventivi_raw = $gdrive_sync->get_all_preventivi(true);
        if (!empty($preventivi_raw)) {
            // CORRETTO: Calcola statistiche direttamente invece di chiamare metodo inesistente
            $statistics = [
                'total' => count($preventivi_raw),
                'confermati' => count(array_filter($preventivi_raw, function($p) { 
                    return isset($p['confermato']) && $p['confermato'] == 1; 
                })),
                'attivi' => count(array_filter($preventivi_raw, function($p) { 
                    return isset($p['stato']) && $p['stato'] === 'attivo'; 
                })),
                'annullati' => count(array_filter($preventivi_raw, function($p) { 
                    return isset($p['stato']) && $p['stato'] === 'annullato'; 
                }))
            ];
            $data_source = 'google_drive';
        }
    } catch (Exception $e) {
        error_log('[747Disco-Dashboard] Errore Google Drive: ' . $e->getMessage());
    }
}

// Fallback al database se Google Drive fallisce
if (empty($preventivi_raw) && $database) {
    try {
        global $wpdb;
        $table_name = $wpdb->prefix . 'disco747_preventivi';
        $preventivi_db = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC", ARRAY_A);
        
        if (!empty($preventivi_db)) {
            $preventivi_raw = array_map(function($prev) {
                return [
                    'id_visuale' => 'DB-' . $prev['id'],
                    'preventivo_id' => $prev['preventivo_id'] ?? '',
                    'tipo_evento' => $prev['tipo_evento'] ?? 'Evento',
                    'data_evento' => $prev['data_evento'] ?? '',
                    'tipo_menu' => $prev['tipo_menu'] ?? 'Menu 7',
                    'stato' => $prev['stato'] ?? 'attivo',
                    'confermato' => $prev['confermato'] ?? 0,
                    'importo_preventivo' => $prev['importo_preventivo'] ?? 0,
                    'nome_referente' => $prev['nome_referente'] ?? 'Cliente',
                    'file_name' => ($prev['nome_referente'] ?? 'Cliente') . ' - ' . ($prev['tipo_evento'] ?? 'Evento'),
                    'source' => 'database'
                ];
            }, $preventivi_db);
            
            $statistics = [
                'total' => count($preventivi_raw),
                'confermati' => count(array_filter($preventivi_raw, function($p) { return $p['confermato'] == 1; })),
                'attivi' => count(array_filter($preventivi_raw, function($p) { return $p['stato'] === 'attivo'; })),
                'annullati' => count(array_filter($preventivi_raw, function($p) { return $p['stato'] === 'annullato'; }))
            ];
            
            $data_source = 'database';
        }
    } catch (Exception $e) {
        error_log('[747Disco-Dashboard] Errore database: ' . $e->getMessage());
    }
}

?>

<div class="wrap">
    <!-- Header Dashboard -->
    <div style="background: linear-gradient(135deg, #2b1e1a 0%, #1a1a1a 100%); color: white; padding: 30px; border-radius: 15px; margin: 20px 0; position: relative; overflow: hidden;">
        <div style="position: absolute; top: 0; right: 0; width: 200px; height: 200px; background: radial-gradient(circle, rgba(194, 138, 77, 0.3) 0%, transparent 70%); transform: translate(50px, -50px);"></div>
        <div style="position: relative; z-index: 2;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 20px;">
                <div>
                    <h1 style="margin: 0; color: white; font-size: 2.2rem; font-weight: 700;">
                        📊 Dashboard Preventivi
                    </h1>
                    <p style="margin: 10px 0 0 0; opacity: 0.9;">
                        <?php 
                        if ($data_source === 'google_drive') {
                            echo 'Sincronizzato con Google Drive • ' . count($preventivi_raw) . ' preventivi trovati';
                        } elseif ($data_source === 'database') {
                            echo 'Caricato dal Database • ' . count($preventivi_raw) . ' preventivi';
                        } else {
                            echo 'Nessun preventivo disponibile';
                        }
                        ?>
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="<?php echo admin_url('admin.php?page=disco747-crm&action=new_preventivo'); ?>" 
                       style="background: #c28a4d; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">
                        ➕ Nuovo Preventivo
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=disco747-crm'); ?>" 
                       style="background: rgba(255,255,255,0.2); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                        ← Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistiche -->
    <?php if (!empty($preventivi_raw)): ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #a67c52 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
            <h3 style="margin: 0; color: white; font-size: 2rem;"><?php echo $statistics['total']; ?></h3>
            <p style="margin: 5px 0 0 0; font-weight: 600;">📊 Totale</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
            <h3 style="margin: 0; color: white; font-size: 2rem;"><?php echo $statistics['attivi']; ?></h3>
            <p style="margin: 5px 0 0 0; font-weight: 600;">✅ Attivi</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
            <h3 style="margin: 0; color: white; font-size: 2rem;"><?php echo $statistics['confermati']; ?></h3>
            <p style="margin: 5px 0 0 0; font-weight: 600;">🎉 Confermati</p>
        </div>
        
        <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 20px; border-radius: 10px; text-align: center;">
            <h3 style="margin: 0; color: white; font-size: 2rem;"><?php echo $statistics['annullati']; ?></h3>
            <p style="margin: 5px 0 0 0; font-weight: 600;">❌ Annullati</p>
        </div>
    </div>

    <!-- Tabella Preventivi -->
    <div style="background: white; border-radius: 15px; overflow: hidden; margin: 20px 0; box-shadow: 0 8px 25px rgba(43, 30, 26, 0.15);">
        <div style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white; padding: 20px;">
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; font-size: 1.3rem;">
                📋 Elenco Preventivi (<?php echo count($preventivi_raw); ?> totali)
            </h2>
        </div>
        
        <div style="overflow-x: auto; padding: 20px;">
            <table class="wp-list-table widefat fixed striped" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">ID</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Cliente</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Evento</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Data</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Menu</th>
                        <th style="padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;">Stato</th>
                        <th style="padding: 12px; text-align: right; border-bottom: 2px solid #dee2e6;">Importo</th>
                        <th style="padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preventivi_raw as $preventivo): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                            <strong><?php echo esc_html($preventivo['preventivo_id'] ?? $preventivo['id_visuale'] ?? 'N/A'); ?></strong>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                            <?php echo esc_html($preventivo['nome_referente'] ?? 'N/A'); ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                            <?php echo esc_html($preventivo['tipo_evento'] ?? 'N/A'); ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                            <?php 
                            $data = $preventivo['data_evento'] ?? '';
                            echo $data ? date('d/m/Y', strtotime($data)) : 'N/A';
                            ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                            <?php echo esc_html($preventivo['tipo_menu'] ?? 'N/A'); ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #dee2e6; text-align: center;">
                            <?php 
                            $stato = $preventivo['stato'] ?? 'attivo';
                            $confermato = $preventivo['confermato'] ?? 0;
                            
                            if ($confermato == 1) {
                                echo '<span style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">✅ CONFERMATO</span>';
                            } elseif ($stato === 'annullato') {
                                echo '<span style="background: #dc3545; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">❌ ANNULLATO</span>';
                            } else {
                                echo '<span style="background: #ffc107; color: #000; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">⏳ ATTIVO</span>';
                            }
                            ?>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #dee2e6; text-align: right;">
                            <strong>€<?php echo number_format($preventivo['importo_preventivo'] ?? 0, 2, ',', '.'); ?></strong>
                        </td>
                        <td style="padding: 12px; border-bottom: 1px solid #dee2e6; text-align: center;">
                            <a href="<?php echo admin_url('admin.php?page=disco747-crm&action=edit_preventivo&id=' . ($preventivo['id'] ?? '')); ?>" 
                               style="color: #007bff; text-decoration: none; margin-right: 10px;">
                                ✏️ Modifica
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <p style="margin: 0;">
            <strong>ℹ️ Nessun preventivo disponibile</strong><br>
            <?php if ($data_source === 'none'): ?>
            Impossibile caricare i preventivi. Verifica la connessione a Google Drive o il database.
            <?php else: ?>
            Non ci sono ancora preventivi nel sistema. <a href="<?php echo admin_url('admin.php?page=disco747-crm&action=new_preventivo'); ?>">Crea il primo preventivo</a>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>
    
</div>

<?php
echo '</div>'; // chiudi disco747-dashboard-preventivi
?>