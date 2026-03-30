<?php
/**
 * Funnel Scheduler - 747 Disco CRM
 * Gestisce il WP Cron per invii automatici del funnel
 * 
 * @package    Disco747_CRM
 * @subpackage Funnel
 * @version    1.0.0
 */

namespace Disco747_CRM\Funnel;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Funnel_Scheduler {
    
    private $funnel_manager;
    
    public function __construct() {
        $this->funnel_manager = new Disco747_Funnel_Manager();
        
        // Registra hook cron
        add_action('disco747_funnel_check_sends', array($this, 'process_pending_sends'));
        add_action('disco747_funnel_check_pre_evento', array($this, 'check_pre_evento_funnel'));
        
        // Registra hook dopo salvataggio preventivo
        add_action('disco747_preventivo_created', array($this, 'handle_new_preventivo'), 10, 1);
        add_action('disco747_preventivo_confirmed', array($this, 'handle_preventivo_confirmed'), 10, 1);
        add_action('disco747_preventivo_cancelled', array($this, 'handle_preventivo_cancelled'), 10, 1);
        add_action('disco747_preventivo_reactivated', array($this, 'handle_preventivo_reactivated'), 10, 1);
    }
    
    /**
     * Attiva gli scheduled events
     */
    public function activate() {
        // Check invii ogni ora
        if (!wp_next_scheduled('disco747_funnel_check_sends')) {
            wp_schedule_event(time(), 'hourly', 'disco747_funnel_check_sends');
            error_log('[747Disco-Funnel-Scheduler] Ã¢Å“â€¦ Cron orario attivato');
        }
        
        // Check pre-evento giornaliero (alle 09:00)
        if (!wp_next_scheduled('disco747_funnel_check_pre_evento')) {
            $tomorrow_9am = strtotime('tomorrow 09:00:00');
            wp_schedule_event($tomorrow_9am, 'daily', 'disco747_funnel_check_pre_evento');
            error_log('[747Disco-Funnel-Scheduler] Ã¢Å“â€¦ Cron giornaliero pre-evento attivato');
        }
    }
    
    /**
     * Disattiva gli scheduled events
     */
    public function deactivate() {
        $timestamp = wp_next_scheduled('disco747_funnel_check_sends');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'disco747_funnel_check_sends');
        }
        
        $timestamp_pre = wp_next_scheduled('disco747_funnel_check_pre_evento');
        if ($timestamp_pre) {
            wp_unschedule_event($timestamp_pre, 'disco747_funnel_check_pre_evento');
        }
        
        error_log('[747Disco-Funnel-Scheduler] Ã¢ÂÂ¹Ã¯Â¸Â Cron disattivati');
    }
    
    /**
     * Processa invii in sospeso (CRON ORARIO)
     */
    public function process_pending_sends() {
        error_log('[747Disco-Funnel-Scheduler] Ã°Å¸â€â€ž Check invii in sospeso...');
        
        $pending = $this->funnel_manager->get_pending_sends();
        
        if (empty($pending)) {
            error_log('[747Disco-Funnel-Scheduler] Ã¢â€žÂ¹Ã¯Â¸Â Nessun invio in sospeso');
            return;
        }
        
        $count = count($pending);
        error_log("[747Disco-Funnel-Scheduler] Ã°Å¸â€œÂ¬ Trovati {$count} invii da processare");
        
        foreach ($pending as $tracking) {
            try {
                $this->funnel_manager->send_next_step($tracking->id);
                error_log("[747Disco-Funnel-Scheduler] Ã¢Å“â€¦ Inviato step per tracking #{$tracking->id}");
            } catch (\Exception $e) {
                error_log("[747Disco-Funnel-Scheduler] Ã¢ÂÅ’ Errore tracking #{$tracking->id}: " . $e->getMessage());
            }
        }
        
        error_log("[747Disco-Funnel-Scheduler] Ã¢Å“â€¦ Processamento completato");
    }
    
    /**
     * Check funnel pre-evento (CRON GIORNALIERO)
     * Avvia funnel per eventi confermati con data tra X giorni
     */
    public function check_pre_evento_funnel() {
        global $wpdb;
        
        error_log('[747Disco-Funnel-Scheduler] Ã°Å¸â€â€ž Check funnel pre-evento...');
        
        $preventivi_table = $wpdb->prefix . 'disco747_preventivi';
        $tracking_table = $wpdb->prefix . 'disco747_funnel_tracking';
        
        // Trova eventi confermati con data tra 7 e 14 giorni
        // (range ampio per non perdere eventi)
        $date_start = date('Y-m-d', strtotime('+7 days'));
        $date_end = date('Y-m-d', strtotime('+14 days'));
        
        $preventivi = $wpdb->get_results($wpdb->prepare("
            SELECT p.* 
            FROM {$preventivi_table} p
            LEFT JOIN {$tracking_table} t ON p.id = t.preventivo_id AND t.funnel_type = 'pre_evento'
            WHERE p.data_evento BETWEEN %s AND %s
              AND p.stato = 'confermato'
              AND p.acconto > 0
              AND (t.id IS NULL OR t.status != 'active')
        ", $date_start, $date_end));
        
        if (empty($preventivi)) {
            error_log('[747Disco-Funnel-Scheduler] Ã¢â€žÂ¹Ã¯Â¸Â Nessun evento da avviare nel funnel pre-evento');
            return;
        }
        
        $count = count($preventivi);
        error_log("[747Disco-Funnel-Scheduler] Ã°Å¸â€œâ€¦ Trovati {$count} eventi per funnel pre-evento");
        
        foreach ($preventivi as $preventivo) {
            try {
                $this->funnel_manager->start_funnel($preventivo->id, 'pre_evento');
                error_log("[747Disco-Funnel-Scheduler] Ã¢Å“â€¦ Funnel pre-evento avviato per #{$preventivo->id}");
            } catch (\Exception $e) {
                error_log("[747Disco-Funnel-Scheduler] Ã¢ÂÅ’ Errore preventivo #{$preventivo->id}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Handle nuovo preventivo creato
     * Avvia automaticamente il funnel pre-conferma
     * 
     * âœ… FIX: Usa STATO invece di acconto per determinare se avviare il funnel
     * - stato = 'attivo' â†’ AVVIA funnel pre-conferma
     * - stato = 'confermato' o 'annullato' â†’ NON avvia funnel
     */
    public function handle_new_preventivo($preventivo_id) {
        global $wpdb;
        
        $preventivi_table = $wpdb->prefix . 'disco747_preventivi';
        $preventivo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$preventivi_table} WHERE id = %d",
            $preventivo_id
        ));
        
        if (!$preventivo) {
            error_log("[747Disco-Funnel-Scheduler] âš ï¸ Preventivo #{$preventivo_id} non trovato");
            return;
        }
        
        // âœ… LOGICA CORRETTA: Avvia funnel SOLO se stato = 'attivo'
        $stato = $preventivo->stato ?? 'attivo';
        
        error_log("[747Disco-Funnel-Scheduler] ðŸ“Š Preventivo #{$preventivo_id} - Stato: '{$stato}', Acconto: â‚¬" . ($preventivo->acconto ?? 0));
        
        if ($stato === 'attivo') {
            error_log("[747Disco-Funnel-Scheduler] ðŸš€ Nuovo preventivo #{$preventivo_id} (stato: {$stato}) - Avvio funnel pre-conferma");
            $this->funnel_manager->start_funnel($preventivo_id, 'pre_conferma');
        } else {
            error_log("[747Disco-Funnel-Scheduler] â„¹ï¸ Preventivo #{$preventivo_id} con stato '{$stato}' - Skip funnel pre-conferma (il funnel parte solo per preventivi ATTIVI)");
        }
    }
    
    /**
     * Handle preventivo confermato
     * Stoppa il funnel pre-conferma se attivo
     */
    public function handle_preventivo_confirmed($preventivo_id) {
        error_log("[747Disco-Funnel-Scheduler] Ã¢Å“â€¦ Preventivo #{$preventivo_id} confermato - Stop funnel pre-conferma");
        
        // Stoppa il funnel pre-conferma
        $result = $this->funnel_manager->stop_funnel($preventivo_id, 'pre_conferma');
        
        if ($result) {
            error_log("[747Disco-Funnel-Scheduler] ✅ Funnel pre-conferma stoppato con successo per preventivo #{$preventivo_id}");
        } else {
            error_log("[747Disco-Funnel-Scheduler] ⚠️ Nessun funnel attivo trovato per preventivo #{$preventivo_id}");
        }
    }
    
    /**
     * Test manuale dello scheduler
     */
    public function test_run() {
        error_log('[747Disco-Funnel-Scheduler] Ã°Å¸Â§Âª TEST RUN MANUALE');
        
        echo "<h2>Test Funnel Scheduler</h2>";
        
        
        echo "<h3>0. Debug Report Tracking</h3>";
        $this->funnel_manager->debug_tracking_report();
        echo "<p>✅ Report generato nei log</p>";
        echo "<h3>1. Check Invii Pending</h3>";
        $this->process_pending_sends();
        
        echo "<h3>2. Check Pre-Evento</h3>";
        $this->check_pre_evento_funnel();
        
        echo "<p>Ã¢Å“â€¦ Test completato. Controlla i log per dettagli.</p>";
    }
    
    /**
     * Info sullo stato del cron
     */
    public function get_cron_status() {
        $next_sends_check = wp_next_scheduled('disco747_funnel_check_sends');
        $next_pre_evento_check = wp_next_scheduled('disco747_funnel_check_pre_evento');
        
        return array(
            'sends_check' => array(
                'active' => $next_sends_check !== false,
                'next_run' => $next_sends_check ? date('d/m/Y H:i:s', $next_sends_check) : 'Non schedulato',
                'next_run_relative' => $next_sends_check ? human_time_diff($next_sends_check) : 'N/A'
            ),
            'pre_evento_check' => array(
                'active' => $next_pre_evento_check !== false,
                'next_run' => $next_pre_evento_check ? date('d/m/Y H:i:s', $next_pre_evento_check) : 'Non schedulato',
                'next_run_relative' => $next_pre_evento_check ? human_time_diff($next_pre_evento_check) : 'N/A'
            )
        );
    }
    
    /**
     * Handle preventivo annullato - AGGIUNTO v12.0.5
     * Stoppa il funnel pre-conferma se attivo
     */
    public function handle_preventivo_cancelled($preventivo_id) {
        error_log("[747Disco-Funnel-Scheduler] ❌ Preventivo #{$preventivo_id} annullato - Stop funnel pre-conferma");
        
        // Stoppa il funnel pre-conferma
        $result = $this->funnel_manager->stop_funnel($preventivo_id, 'pre_conferma');
        
        if ($result) {
            error_log("[747Disco-Funnel-Scheduler] ✅ Funnel pre-conferma stoppato con successo per preventivo #{$preventivo_id}");
        } else {
            error_log("[747Disco-Funnel-Scheduler] ⚠️ Nessun funnel attivo trovato per preventivo #{$preventivo_id}");
        }
    }
    
    /**
     * Handle preventivo riattivato - AGGIUNTO v12.0.5
     * Riavvia il funnel pre-conferma se era stato fermato
     */
    public function handle_preventivo_reactivated($preventivo_id) {
        error_log("[747Disco-Funnel-Scheduler] REACTIVATE Preventivo #{$preventivo_id} riattivato - Riavvio funnel pre-conferma");
        
        // Riavvia il funnel pre-conferma
        $this->funnel_manager->start_funnel($preventivo_id, 'pre_conferma');
    }
}