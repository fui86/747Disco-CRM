<?php
/**
 * Funnel Database Manager - 747 Disco CRM
 * Gestisce le tabelle per il sistema di automazione funnel
 * 
 * @package    Disco747_CRM
 * @subpackage Funnel
 * @version    1.0.0
 */

namespace Disco747_CRM\Funnel;

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

class Disco747_Funnel_Database {
    
    private $sequences_table;
    private $tracking_table;
    private $charset_collate;
    
    public function __construct() {
        global $wpdb;
        
        $this->sequences_table = $wpdb->prefix . 'disco747_funnel_sequences';
        $this->tracking_table = $wpdb->prefix . 'disco747_funnel_tracking';
        $this->charset_collate = $wpdb->get_charset_collate();
    }
    
    /**
     * Crea le tabelle necessarie per il funnel
     */
    public function create_tables() {
        global $wpdb;
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Tabella Sequenze (Configurazione)
        $sql_sequences = "CREATE TABLE IF NOT EXISTS {$this->sequences_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            funnel_type varchar(50) NOT NULL DEFAULT 'pre_conferma',
            step_number int(11) NOT NULL DEFAULT 1,
            step_name varchar(100) DEFAULT '',
            days_offset int(11) NOT NULL DEFAULT 0,
            send_time time DEFAULT '09:00:00',
            email_enabled tinyint(1) NOT NULL DEFAULT 1,
            email_subject text DEFAULT NULL,
            email_body longtext DEFAULT NULL,
            whatsapp_enabled tinyint(1) NOT NULL DEFAULT 0,
            whatsapp_text text DEFAULT NULL,
            active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY funnel_type (funnel_type),
            KEY active (active)
        ) {$this->charset_collate}";
        
        dbDelta($sql_sequences);
        
        // Verifica e aggiungi colonna send_time se manca
        $this->maybe_add_send_time_column();
        
        // Tabella Tracking (Stato invii)
        $sql_tracking = "CREATE TABLE IF NOT EXISTS {$this->tracking_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            preventivo_id bigint(20) UNSIGNED NOT NULL,
            funnel_type varchar(50) NOT NULL DEFAULT 'pre_conferma',
            current_step int(11) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            started_at datetime NOT NULL,
            last_sent_at datetime DEFAULT NULL,
            next_send_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            emails_log longtext DEFAULT NULL,
            whatsapp_log longtext DEFAULT NULL,
            notes text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY preventivo_id (preventivo_id),
            KEY funnel_type (funnel_type),
            KEY status (status),
            KEY next_send_at (next_send_at),
            UNIQUE KEY unique_preventivo_funnel (preventivo_id, funnel_type)
        ) {$this->charset_collate};";
        
        dbDelta($sql_tracking);
        
        error_log('[747Disco-Funnel] Tabelle create/verificate con successo');
        
        // Inserisci sequenze di default se la tabella è vuota
        $this->insert_default_sequences();
    }
    
    /**
     * Aggiunge colonna send_time se non esiste
     */
    private function maybe_add_send_time_column() {
        global $wpdb;
        
        // Controlla se la colonna esiste
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM {$this->sequences_table} LIKE %s",
            'send_time'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$this->sequences_table} ADD COLUMN send_time time DEFAULT '09:00:00' AFTER days_offset");
            error_log('[747Disco-Funnel] ✅ Colonna send_time aggiunta');
        }
    }
    
    /**
     * Inserisce sequenze di default per iniziare
     */
    private function insert_default_sequences() {
        global $wpdb;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->sequences_table}");
        
        if ($count > 0) {
            return; // Già esistono sequenze
        }
        
        // Sequenze PRE-CONFERMA di default
        $default_pre_conferma = array(
            array(
                'funnel_type' => 'pre_conferma',
                'step_number' => 1,
                'step_name' => 'Invio Preventivo',
                'days_offset' => 0,
                'send_time' => '09:00:00',
                'email_enabled' => 1,
                'email_subject' => 'Il tuo preventivo per {{tipo_evento}} è pronto! 🎉',
                'email_body' => 'Ciao {{nome_referente}},

Grazie per averci scelto per il tuo {{tipo_evento}}!

Il tuo preventivo è pronto e ti aspetta. Abbiamo riservato per te la data del {{data_evento}}.

DETTAGLI EVENTO:
📅 Data: {{data_evento}}
🎊 Tipo: {{tipo_evento}}
👥 Invitati: {{numero_invitati}}
💰 Importo: €{{importo_totale}}

Per confermare l\'evento serve un acconto di €{{acconto}}.

Restiamo a disposizione per qualsiasi domanda!

A presto,
Team 747 Disco
📞 {{telefono_sede}}
📧 info@gestionale.747disco.it',
                'whatsapp_enabled' => 1,
                'whatsapp_text' => 'Ciao {{nome_referente}}! 👋

Il tuo preventivo per {{tipo_evento}} del {{data_evento}} è pronto!

Importo: €{{importo_totale}}
Acconto per conferma: €{{acconto}}

Hai domande? Scrivici pure! 😊',
                'active' => 1
            ),
            array(
                'funnel_type' => 'pre_conferma',
                'step_number' => 2,
                'step_name' => 'Follow-up',
                'days_offset' => 2,
                'send_time' => '14:00:00',
                'email_enabled' => 1,
                'email_subject' => 'Hai visto il preventivo? 🤔',
                'email_body' => 'Ciao {{nome_referente}},

Ti abbiamo inviato il preventivo qualche giorno fa per il tuo {{tipo_evento}}.

Hai avuto modo di visionarlo? Hai domande o dubbi?

Siamo qui per aiutarti a organizzare un evento perfetto! 🎉

La data {{data_evento}} è ancora disponibile, ma non possiamo garantirla a lungo senza conferma.

Scrivici o chiamaci!

Team 747 Disco
📞 {{telefono_sede}}',
                'whatsapp_enabled' => 0,
                'whatsapp_text' => 'Ciao {{nome_referente}}! 

Hai visto il preventivo per il {{data_evento}}? 

Se hai domande siamo qui! 😊',
                'active' => 1
            ),
            array(
                'funnel_type' => 'pre_conferma',
                'step_number' => 3,
                'step_name' => 'Urgenza',
                'days_offset' => 4,
                'send_time' => '10:00:00',
                'email_enabled' => 1,
                'email_subject' => '⏰ Ultima possibilità per {{data_evento}}!',
                'email_body' => 'Ciao {{nome_referente}},

Questa è l\'ultima chiamata per il tuo evento! ⏰

La data {{data_evento}} che avevi richiesto è ancora libera, ma abbiamo altre richieste in arrivo.

Non vogliamo che tu perda questa opportunità! 

Per BLOCCARE DEFINITIVAMENTE la data, serve solo l\'acconto di €{{acconto}}.

⚠️ Dopo oggi potremmo non riuscire a garantirti questa data.

Cosa ne dici? Confermiamo? 🎉

Team 747 Disco
📞 {{telefono_sede}}
📧 info@gestionale.747disco.it',
                'whatsapp_enabled' => 1,
                'whatsapp_text' => '⏰ {{nome_referente}}, ULTIMA CHIAMATA!

La data {{data_evento}} sta per essere presa da altri.

Confermi con l\'acconto di €{{acconto}}? 

Rispondimi subito! 🚨',
                'active' => 1
            )
        );
        
        foreach ($default_pre_conferma as $sequence) {
            $wpdb->insert($this->sequences_table, $sequence);
        }
        
        // Sequenze PRE-EVENTO di default (vuote, da configurare)
        $default_pre_evento = array(
            array(
                'funnel_type' => 'pre_evento',
                'step_number' => 1,
                'step_name' => 'Upselling -10 giorni',
                'days_offset' => -10,
                'send_time' => '09:00:00',
                'email_enabled' => 1,
                'email_subject' => '🎁 Mancano 10 giorni! Pacchetti extra in sconto',
                'email_body' => '[Da configurare]',
                'whatsapp_enabled' => 0,
                'whatsapp_text' => '[Da configurare]',
                'active' => 0
            ),
            array(
                'funnel_type' => 'pre_evento',
                'step_number' => 2,
                'step_name' => 'Reminder -7 giorni',
                'days_offset' => -7,
                'send_time' => '09:00:00',
                'email_enabled' => 1,
                'email_subject' => '🎉 Una settimana al tuo evento!',
                'email_body' => '[Da configurare]',
                'whatsapp_enabled' => 0,
                'whatsapp_text' => '[Da configurare]',
                'active' => 0
            )
        );
        
        foreach ($default_pre_evento as $sequence) {
            $wpdb->insert($this->sequences_table, $sequence);
        }
        
        error_log('[747Disco-Funnel] Sequenze di default inserite');
    }
    
    /**
     * Verifica salute delle tabelle
     */
    public function check_health() {
        global $wpdb;
        
        $health = array(
            'sequences_table_exists' => false,
            'tracking_table_exists' => false,
            'sequences_count' => 0,
            'tracking_count' => 0,
            'status' => 'unknown'
        );
        
        try {
            $sequences_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->sequences_table}'");
            $tracking_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->tracking_table}'");
            
            $health['sequences_table_exists'] = ($sequences_exists === $this->sequences_table);
            $health['tracking_table_exists'] = ($tracking_exists === $this->tracking_table);
            
            if ($health['sequences_table_exists']) {
                $health['sequences_count'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->sequences_table}"));
            }
            
            if ($health['tracking_table_exists']) {
                $health['tracking_count'] = intval($wpdb->get_var("SELECT COUNT(*) FROM {$this->tracking_table}"));
            }
            
            $health['status'] = ($health['sequences_table_exists'] && $health['tracking_table_exists']) ? 'ok' : 'error';
            
        } catch (\Exception $e) {
            $health['status'] = 'error';
            $health['error'] = $e->getMessage();
        }
        
        return $health;
    }
    
    /**
     * Reset completo (solo per sviluppo/test)
     */
    public function reset_tables($confirm = false) {
        if (!$confirm) {
            return false;
        }
        
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$this->sequences_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$this->tracking_table}");
        
        $this->create_tables();
        
        error_log('[747Disco-Funnel] Tabelle resettate');
        
        return true;
    }
}
