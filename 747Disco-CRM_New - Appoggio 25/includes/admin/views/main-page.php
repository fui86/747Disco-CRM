<?php
/**
 * Template dashboard principale 747 Disco CRM - VERSIONE CORRETTA
 * Rimuove tutte le chiamate $this-> e usa solo variabili passate dal controller
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @since      11.6.1-FIXED
 * @version    11.6.1-FIXED
 */

// Sicurezza: impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// CORRETTO: usa solo variabili passate dal controller, NO $this->
// Le variabili $stats, $system_status, $recent_preventivi sono passate dal metodo render_main_dashboard_page()

// Valori di default sicuri se le variabili non sono definite
if (!isset($stats) || !is_array($stats)) {
    $stats = array(
        'total' => 0,
        'active' => 0,
        'confirmed' => 0,
        'this_month' => 0,
        'total_preventivi' => 0,
        'preventivi_attivi' => 0,
        'preventivi_confermati' => 0,
        'valore_totale' => 0
    );
}

if (!isset($system_status) || !is_array($system_status)) {
    $system_status = array(
        'plugin_version' => '11.6.1',
        'last_sync' => 'Mai'
    );
}

if (!isset($recent_preventivi)) {
    $recent_preventivi = array();
}

// Status sistema sicuro
$storage_type = get_option('disco747_storage_type', 'googledrive');
$storage_connected = false; // Da implementare check reale
$database_status = 'ok'; // Assumiamo OK se arriviamo qui
$version = defined('DISCO747_CRM_VERSION') ? DISCO747_CRM_VERSION : '11.6.1';

// User info sicuro
$current_user = wp_get_current_user();
$user_name = $current_user && $current_user->display_name ? $current_user->display_name : 'Utente';
?>

<div class="wrap disco747-crm-dashboard" style="margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    
    <!-- Header Principale -->
    <div style="background: linear-gradient(135deg, #2b1e1a 0%, #c28a4d 50%, #2b1e1a 100%); color: white; padding: 40px 30px; border-radius: 15px; margin-bottom: 30px; position: relative; overflow: hidden; box-shadow: 0 8px 30px rgba(43, 30, 26, 0.3);">
        <!-- Elementi decorativi -->
        <div style="position: absolute; top: -50px; right: -50px; width: 200px; height: 200px; background: rgba(255,215,0,0.1); border-radius: 50%;"></div>
        <div style="position: absolute; bottom: -30px; left: -30px; width: 150px; height: 150px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
        
        <div style="position: relative; z-index: 2;">
            <h1 style="margin: 0 0 15px 0; font-size: 2.8rem; font-weight: 700; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">
                🎉 747 Disco CRM
            </h1>
            <p style="margin: 0; font-size: 1.2rem; opacity: 0.9; font-weight: 300;">
                Dashboard di gestione preventivi e eventi - Benvenuto, <?php echo esc_html($user_name); ?>!
            </p>
        </div>
    </div>

    <!-- Statistiche in Evidenza -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px;">
        
        <!-- Totale Preventivi -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(194, 138, 77, 0.3);">
            <div style="font-size: 3rem; font-weight: bold; margin: 15px 0;">
                <?php echo number_format($stats['total_preventivi'] ?? $stats['total'] ?? 0); ?>
            </div>
            <h3 style="margin: 0; font-size: 1.1rem; opacity: 0.9; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">📋 Totale Preventivi</h3>
            <p style="margin: 10px 0 0 0; font-size: 0.85rem; opacity: 0.7;">Tutti i preventivi</p>
        </div>
        
        <!-- Preventivi Attivi -->
        <div style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);">
            <div style="font-size: 3rem; font-weight: bold; margin: 15px 0;">
                <?php echo number_format($stats['preventivi_attivi'] ?? $stats['active'] ?? 0); ?>
            </div>
            <h3 style="margin: 0; font-size: 1.1rem; opacity: 0.9; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">⏳ Attivi</h3>
            <p style="margin: 10px 0 0 0; font-size: 0.85rem; opacity: 0.7;">In attesa di conferma</p>
        </div>
        
        <!-- Preventivi Confermati -->
        <div style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(40, 167, 69, 0.3);">
            <div style="font-size: 3rem; font-weight: bold; margin: 15px 0;">
                <?php echo number_format($stats['preventivi_confermati'] ?? $stats['confirmed'] ?? 0); ?>
            </div>
            <h3 style="margin: 0; font-size: 1.1rem; opacity: 0.9; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">🎉 Confermati</h3>
            <p style="margin: 10px 0 0 0; font-size: 0.85rem; opacity: 0.7;">Eventi sicuri</p>
        </div>
        
        <!-- Questo Mese -->
        <div style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); color: white; padding: 25px; border-radius: 15px; text-align: center; box-shadow: 0 6px 20px rgba(255, 193, 7, 0.3);">
            <div style="font-size: 3rem; font-weight: bold; margin: 15px 0;">
                <?php echo number_format($stats['this_month'] ?? 0); ?>
            </div>
            <h3 style="margin: 0; font-size: 1.1rem; opacity: 0.9; text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">📅 Questo Mese</h3>
            <p style="margin: 10px 0 0 0; font-size: 0.85rem; opacity: 0.7;"><?php echo date('F Y'); ?></p>
        </div>
    </div>

    <!-- NUOVO: Pulsanti di Azione Principali -->
    <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); border-top: 5px solid #dc3545; margin-bottom: 30px;">
        <h2 style="color: #2b1e1a; border-bottom: 3px solid #dc3545; padding-bottom: 15px; margin-bottom: 25px;">
            🚀 Azioni Rapide
        </h2>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=new_preventivo')); ?>" 
               style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px; border-radius: 15px; text-decoration: none; text-align: center; font-weight: 600; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); transition: all 0.3s ease;">
                📝 Crea Nuovo Preventivo
                <div style="font-size: 0.9rem; margin-top: 8px; opacity: 0.9;">Aggiungi un nuovo preventivo evento</div>
            </a>
            
            <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=dashboard_preventivi')); ?>" 
               style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 20px; border-radius: 15px; text-decoration: none; text-align: center; font-weight: 600; font-size: 1.1rem; box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3); transition: all 0.3s ease;">
                📊 Dashboard Preventivi
                <div style="font-size: 0.9rem; margin-top: 8px; opacity: 0.9;">Gestisci e filtra tutti i preventivi</div>
            </a>
        </div>
    </div>

    <!-- ============================================================================ -->
    <!-- SEZIONI PRINCIPALI -->
    <!-- ============================================================================ -->
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        
        <!-- Sistema e Configurazione -->
        <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); border-top: 5px solid #c28a4d;">
            <h2 style="color: #2b1e1a; border-bottom: 3px solid #c28a4d; padding-bottom: 15px; margin-bottom: 25px; font-size: 1.4rem;">
                ⚙️ Sistema e Configurazione
            </h2>
            
            <!-- Status Database -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid <?php echo $database_status === 'ok' ? '#28a745' : '#dc3545'; ?>;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: #2b1e1a;">🗄️ Database</strong>
                        <div style="font-size: 13px; color: #666; margin-top: 5px;">
                            Sistema di archiviazione preventivi
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="background: <?php echo $database_status === 'ok' ? '#28a745' : '#dc3545'; ?>; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;">
                            <?php echo $database_status === 'ok' ? '✅ OK' : '❌ Errore'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Status Storage -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid <?php echo $storage_connected ? '#28a745' : '#ffc107'; ?>;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: #2b1e1a;">
                            <?php echo $storage_type === 'googledrive' ? '📁 Google Drive' : '📦 Dropbox'; ?>
                        </strong>
                        <div style="font-size: 13px; color: #666; margin-top: 5px;">
                            Storage cloud per PDF e Excel
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="background: <?php echo $storage_connected ? '#28a745' : '#ffc107'; ?>; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;">
                            <?php echo $storage_connected ? '✅ CONNESSO' : '⚠️ VERIFICA'; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Versione Plugin -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 5px solid #17a2b8;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: #2b1e1a;">🔧 Versione Plugin</strong>
                        <div style="font-size: 13px; color: #666; margin-top: 5px;">
                            747 Disco CRM Enhanced
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <span style="background: #17a2b8; color: white; padding: 5px 10px; border-radius: 15px; font-size: 12px; font-weight: 600;">
                            v<?php echo esc_html($version); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Azioni e Collegamenti -->
        <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); border-top: 5px solid #17a2b8;">
            <h2 style="color: #2b1e1a; border-bottom: 3px solid #17a2b8; padding-bottom: 15px; margin-bottom: 25px;">
                🔗 Azioni e Collegamenti
            </h2>
            
            <!-- Sincronizzazione -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                <h4 style="color: #17a2b8; margin: 0 0 15px 0;">☁️ Sincronizzazione Storage</h4>
                <p style="color: #666; margin: 0 0 15px 0; font-size: 14px;">
                    Sincronizza preventivi con <?php echo $storage_type === 'googledrive' ? 'Google Drive' : 'Dropbox'; ?>
                </p>
                <form method="post" style="display: inline-block;">
                    <?php wp_nonce_field('disco747_sync_storage'); ?>
                    <input type="hidden" name="disco747_action" value="sync_storage">
                    <button type="submit" class="button button-primary" 
                            style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); border: none; color: white; padding: 10px 20px; border-radius: 20px; font-weight: 600;">
                        🔄 Sincronizza Ora
                    </button>
                </form>
            </div>
            
            <!-- Links Rapidi -->
            <div style="display: grid; gap: 10px;">
                <a href="<?php echo admin_url('admin.php?page=disco747-settings'); ?>" 
                   class="button button-secondary" 
                   style="background: rgba(194, 138, 77, 0.1); border: 2px solid #c28a4d; color: #c28a4d; padding: 12px; text-align: center; text-decoration: none; border-radius: 8px; transition: all 0.3s ease;">
                    ⚙️ Impostazioni
                </a>
                <a href="<?php echo admin_url('admin.php?page=disco747-messages'); ?>" 
                   class="button button-secondary" 
                   style="background: rgba(194, 138, 77, 0.1); border: 2px solid #c28a4d; color: #c28a4d; padding: 12px; text-align: center; text-decoration: none; border-radius: 8px; transition: all 0.3s ease;">
                    📧 Messaggi Automatici
                </a>
                <a href="<?php echo esc_url(home_url('/disco747-dashboard')); ?>" 
                   class="button button-primary" 
                   target="_blank" 
                   style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border: none; color: white; padding: 12px; text-align: center; text-decoration: none; border-radius: 8px; font-weight: 600;">
                    🌐 Dashboard Frontend
                </a>
            </div>
        </div>
    </div>

    <!-- Preventivi Recenti (se disponibili) -->
    <?php if (!empty($recent_preventivi) && is_array($recent_preventivi)): ?>
    <div style="background: white; padding: 30px; border-radius: 15px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); border-top: 5px solid #6f42c1;">
        <h2 style="color: #2b1e1a; border-bottom: 3px solid #6f42c1; padding-bottom: 15px; margin-bottom: 25px;">
            📋 Preventivi Recenti
        </h2>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <thead style="background: #f8f9fa;">
                    <tr>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #495057;">Cliente</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #495057;">Evento</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #495057;">Data</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; color: #495057;">Stato</th>
                        <th style="padding: 12px; text-align: center; border-bottom: 2px solid #dee2e6; color: #495057;">Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recent_preventivi, 0, 5) as $preventivo): ?>
                    <?php 
                    // Gestione compatibilità array/object
                    $prev_data = is_object($preventivo) ? (array)$preventivo : $preventivo;
                    ?>
                    <tr style="border-bottom: 1px solid #dee2e6;">
                        <td style="padding: 10px; color: #495057;">
                            <strong><?php echo esc_html($prev_data['nome_referente'] ?? 'N/D'); ?></strong>
                            <br><small style="color: #6c757d;"><?php echo esc_html($prev_data['mail'] ?? ''); ?></small>
                        </td>
                        <td style="padding: 10px; color: #495057;">
                            <?php echo esc_html($prev_data['tipo_evento'] ?? 'N/D'); ?>
                            <br><small style="color: #6c757d;"><?php echo esc_html($prev_data['tipo_menu'] ?? ''); ?></small>
                        </td>
                        <td style="padding: 10px; color: #495057;">
                            <?php 
                            $data_evento = $prev_data['data_evento'] ?? '';
                            echo $data_evento ? date('d/m/Y', strtotime($data_evento)) : 'N/D';
                            ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php 
                            $stato = $prev_data['stato'] ?? 'attivo';
                            $stato_color = $stato === 'confermato' ? '#28a745' : ($stato === 'annullato' ? '#dc3545' : '#ffc107');
                            ?>
                            <span style="background: <?php echo $stato_color; ?>; color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                <?php echo esc_html(ucfirst($stato)); ?>
                            </span>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <div style="display: flex; gap: 5px; justify-content: center;">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=edit_preventivo&id=' . ($prev_data['id'] ?? 0))); ?>" 
                                   style="background: #007bff; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 11px;">
                                    ✏️ Modifica
                                </a>
                                <?php if (!empty($prev_data['googledrive_url'])): ?>
                                <a href="<?php echo esc_url($prev_data['googledrive_url']); ?>" target="_blank"
                                   style="background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 11px;">
                                    📄 PDF
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="<?php echo esc_url(admin_url('admin.php?page=disco747-crm&action=dashboard_preventivi')); ?>" 
               style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 12px 25px; border-radius: 25px; text-decoration: none; font-weight: 600;">
                📊 Visualizza Tutti i Preventivi
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>

<style>
/* Stili aggiuntivi per la dashboard */
.disco747-crm-dashboard h1 {
    color: #2b1e1a;
}

.disco747-crm-dashboard h2 {
    font-size: 1.4rem;
}

.disco747-crm-dashboard .button:hover,
.disco747-crm-dashboard a[style*="linear-gradient"]:hover {
    transform: translateY(-2px);
    transition: all 0.3s ease;
    filter: brightness(110%);
}

.disco747-crm-dashboard table tr:hover {
    background: #f1f3f4 !important;
    transition: background-color 0.2s ease;
}

@media (max-width: 768px) {
    .disco747-crm-dashboard [style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
    }
    
    .disco747-crm-dashboard [style*="display: flex"] {
        flex-direction: column !important;
        gap: 10px !important;
    }
}

/* Animazioni hover per le card */
.disco747-crm-dashboard [style*="border-left: 5px"]:hover {
    transform: translateY(-3px);
    transition: transform 0.3s ease;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
</style>