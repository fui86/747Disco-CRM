<?php
/**
 * Plugin Name: 747 Disco CRM - PreventiviParty Enhanced
 * Plugin URI: https://747disco.it
 * Description: Sistema CRM completo per la gestione dei preventivi della location 747 Disco. Replica del vecchio PreventiviParty con funzionalità avanzate.
 * Version: 11.6.1-COMPLETE-FIXED
 * Author: 747 Disco Team
 * Author URI: https://747disco.it
 * Text Domain: disco747
 * Domain Path: /languages/
 * Requires at least: 5.8
 * Tested up to: 6.4.2
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package    Disco747_CRM
 * @version    11.6.1-COMPLETE-FIXED
 * @author     747 Disco Team
 */

// Sicurezza: impedisce l'accesso diretto al file
if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// ========================================================================
// COSTANTI DEL PLUGIN - CONFIGURATION
// ========================================================================

// Versione plugin
define('DISCO747_CRM_VERSION', '11.6.1-COMPLETE-FIXED');

// Percorsi del plugin
define('DISCO747_CRM_PLUGIN_FILE', __FILE__);
define('DISCO747_CRM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DISCO747_CRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DISCO747_CRM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Prefissi database
define('DISCO747_CRM_DB_PREFIX', 'disco747_');

// Debug mode (può essere disabilitato in produzione)
define('DISCO747_CRM_DEBUG', true);

// ========================================================================
// CLASSE PRINCIPALE DEL PLUGIN - VERSIONE COMPLETA E CORRETTA
// ========================================================================

/**
 * Classe principale del plugin 747 Disco CRM
 * VERSIONE CORRETTA: Risolve tutti i problemi di inizializzazione e logging
 *
 * @since 11.6.1-COMPLETE-FIXED
 */
final class Disco747_CRM_Plugin {
    
    /**
     * Istanza singleton
     */
    private static $instance = null;
    
    /**
     * Componenti del plugin
     */
    private $config = null;
    private $database = null;
    private $auth = null;
    private $admin = null;
    private $frontend = null;
    private $storage_manager = null;
    private $email_manager = null;
    private $pdf_generator = null;
    private $excel_generator = null;
    private $gdrive_sync = null;
    private $forms_handler = null;
    
    /**
     * Stato inizializzazione
     */
    private $initialized = false;
    private $debug_mode = DISCO747_CRM_DEBUG;
    
    /**
     * Costruttore privato per singleton
     */
    private function __construct() {
        // Registra hook di attivazione/disattivazione
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));
        
        // Inizializzazione principale
        add_action('plugins_loaded', array($this, 'init_plugin'), 10);
        
        // Hook per cleanup
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_files'));
    }

    /**
     * Ottieni istanza singleton
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inizializzazione plugin - SAFE VERSION
     */
    public function init_plugin() {
        try {
            // Log inizializzazione
            $this->public_log('🚀 Inizializzazione 747 Disco CRM v' . DISCO747_CRM_VERSION);
            
            // Carica autoloader
            $this->load_autoloader();
            
            // Inizializza componenti core
            $this->init_core_components();
            
            // Inizializza componenti aggiuntivi
            $this->init_additional_components();
            
            // Registra hook finali
            $this->register_final_hooks();
            
            $this->initialized = true;
            $this->public_log('✅ Plugin inizializzato correttamente');
            
        } catch (Exception $e) {
            $this->public_log('❌ Errore inizializzazione: ' . $e->getMessage(), 'ERROR');
            add_action('admin_notices', array($this, 'show_init_error_notice'));
        }
    }

    /**
     * Carica autoloader SAFE - FIXED con storage dependencies
     */
    private function load_autoloader() {
        // Carica le classi principali manualmente per sicurezza
        $core_files = array(
            'includes/core/class-disco747-config.php',
            'includes/core/class-disco747-database.php',
            'includes/core/class-disco747-auth.php',
            'includes/admin/class-disco747-admin.php',
            // AGGIUNTO: File storage necessari PRIMA del Storage Manager
            'includes/storage/class-disco747-googledrive.php',
            'includes/storage/class-disco747-dropbox.php',
            'includes/storage/class-disco747-storage-manager.php'
        );
        
        $loaded_files = 0;
        $missing_files = array();
        $optional_missing = array();
        
        foreach ($core_files as $file) {
            $file_path = DISCO747_CRM_PLUGIN_DIR . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
                $loaded_files++;
                $this->public_log("✅ Core caricato: {$file}");
            } else {
                // Alcuni file storage potrebbero non esistere ancora
                if (strpos($file, 'storage/') !== false && strpos($file, 'storage-manager') === false) {
                    $optional_missing[] = $file;
                    $this->public_log("⚠️ File storage opzionale mancante: {$file}", 'WARNING');
                } else {
                    $missing_files[] = $file;
                    $this->public_log("❌ File core critico mancante: {$file}", 'ERROR');
                }
            }
        }
        
        // Solo i file critici sono obbligatori
        if (count($missing_files) > 0) {
            throw new Exception("File core critici mancanti: " . implode(', ', $missing_files));
        }
        
        $message = "✅ Autoloader caricato ({$loaded_files} file core";
        if (count($optional_missing) > 0) {
            $message .= ", " . count($optional_missing) . " file storage opzionali mancanti";
        }
        $message .= ")";
        
        $this->public_log($message);
    }

    /**
     * Inizializza componenti core - FIXED: Verifica presenza get_instance()
     */
    private function init_core_components() {
        // Config Manager (Singleton confermato)
        if (class_exists('Disco747_CRM\\Core\\Disco747_Config')) {
            $this->config = Disco747_CRM\Core\Disco747_Config::get_instance();
        }
        
        // Database Manager (NON singleton - usa costruttore normale)
        if (class_exists('Disco747_CRM\\Core\\Disco747_Database')) {
            if (method_exists('Disco747_CRM\\Core\\Disco747_Database', 'get_instance')) {
                $this->database = Disco747_CRM\Core\Disco747_Database::get_instance();
            } else {
                $this->database = new Disco747_CRM\Core\Disco747_Database();
            }
        }
        
        // Auth Manager (verifica se è singleton)
        if (class_exists('Disco747_CRM\\Core\\Disco747_Auth')) {
            if (method_exists('Disco747_CRM\\Core\\Disco747_Auth', 'get_instance')) {
                $this->auth = Disco747_CRM\Core\Disco747_Auth::get_instance();
            } else {
                $this->auth = new Disco747_CRM\Core\Disco747_Auth();
            }
        }
        
        // Storage Manager (verifica se è singleton)
        if (class_exists('Disco747_CRM\\Storage\\Disco747_Storage_Manager')) {
            if (method_exists('Disco747_CRM\\Storage\\Disco747_Storage_Manager', 'get_instance')) {
                $this->storage_manager = Disco747_CRM\Storage\Disco747_Storage_Manager::get_instance();
            } else {
                $this->storage_manager = new Disco747_CRM\Storage\Disco747_Storage_Manager();
            }
        }
        
        $this->public_log('✅ Componenti core inizializzati');
    }

    /**
     * Inizializza componenti aggiuntivi SAFE - FIXED con gestione errori robusta
     */
    private function init_additional_components() {
        try {
            // Admin Manager (solo se in area admin)
            if (is_admin() && class_exists('Disco747_CRM\\Admin\\Disco747_Admin')) {
                try {
                    // Le classi Admin di solito non sono singleton
                    $this->admin = new Disco747_CRM\Admin\Disco747_Admin();
                    $this->public_log('✅ Admin Manager caricato');
                } catch (Exception $e) {
                    $this->public_log('⚠️ Errore caricamento Admin Manager: ' . $e->getMessage(), 'WARNING');
                }
            }

            // Carica PDF Generator
            $this->load_component('includes/generators/class-disco747-pdf.php', function() {
                if (class_exists('Disco747_CRM\\Generators\\Disco747_PDF')) {
                    $this->pdf_generator = new Disco747_CRM\Generators\Disco747_PDF();
                    $this->public_log('✅ PDF Generator inizializzato');
                }
            });

            // Carica Excel Generator
            $this->load_component('includes/generators/class-disco747-excel.php', function() {
                if (class_exists('Disco747_CRM\\Generators\\Disco747_Excel')) {
                    $this->excel_generator = new Disco747_CRM\Generators\Disco747_Excel();
                    $this->public_log('✅ Excel Generator inizializzato');
                }
            });

            // Carica Email Manager
            $this->load_component('includes/communication/class-disco747-email.php', function() {
                if (class_exists('Disco747_CRM\\Communication\\Disco747_Email')) {
                    $this->email_manager = new Disco747_CRM\Communication\Disco747_Email();
                }
            });

            // Carica Messaging Manager
            $this->load_component('includes/communication/class-disco747-messaging.php', function() {
                if (class_exists('Disco747_CRM\\Communication\\Disco747_Messaging')) {
                    // Inizializza se necessario
                }
            });

            // Carica Google Drive Sync
            $this->load_component('includes/storage/class-disco747-googledrive-sync.php', function() {
                if (class_exists('Disco747_CRM\\Storage\\Disco747_GoogleDrive_Sync')) {
                    $this->gdrive_sync = new Disco747_CRM\Storage\Disco747_GoogleDrive_Sync();
                    $this->public_log('✅ Google Drive Sync inizializzato');
                }
            });

        } catch (Exception $e) {
            $this->public_log('⚠️ Errore durante caricamento componenti aggiuntivi: ' . $e->getMessage(), 'WARNING');
        }
    }

    /**
     * Carica componente con gestione errori sicura
     */
    private function load_component($file_path, $callback) {
        try {
            $full_path = DISCO747_CRM_PLUGIN_DIR . $file_path;
            if (file_exists($full_path)) {
                require_once $full_path;
                $this->public_log("✅ Caricato: {$file_path}");
                if (is_callable($callback)) {
                    $callback();
                }
            } else {
                $this->public_log("⚠️ File non trovato: {$file_path}", 'WARNING');
            }
        } catch (Exception $e) {
            $this->public_log("❌ Errore caricamento {$file_path}: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Registra hook finali
     */
    private function register_final_hooks() {
        // Hook WordPress finali
        add_action('init', array($this, 'wp_init_complete'), 999);
        
        // NUOVO: Carica Forms Handler dopo che tutto è inizializzato
        add_action('init', array($this, 'init_forms_handler'), 1000);
        
        $this->public_log('✅ Hook registrati, inizializzazione in corso...');
    }

    /**
     * NUOVO: Inizializza Forms Handler dopo tutti gli altri componenti
     */
    public function init_forms_handler() {
        try {
            $this->load_component('includes/handlers/class-disco747-forms.php', function() {
                if (class_exists('Disco747_CRM\\Handlers\\Disco747_Forms')) {
                    $this->forms_handler = new Disco747_CRM\Handlers\Disco747_Forms();
                    $this->public_log('✅ Forms Handler caricato');
                }
            });
        } catch (Exception $e) {
            $this->public_log('❌ Errore caricamento Forms Handler: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Callback WordPress init completato
     */
    public function wp_init_complete() {
        $this->public_log('✅ Init WordPress completato');
        
        // Auto-migrazione dal vecchio plugin se presente
        $this->auto_migrate_if_needed();
    }

    /**
     * Auto-migrazione dal vecchio plugin se necessario
     */
    private function auto_migrate_if_needed() {
        // Controlla se esiste già la tabella del vecchio plugin
        global $wpdb;
        $old_table = $wpdb->prefix . 'preventivi_party';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '{$old_table}'") && $this->database) {
            $this->public_log('🔄 Tabella vecchio plugin rilevata: ' . $old_table);
            
            try {
                // Esegui migrazione automatica se il database manager lo supporta
                if (method_exists($this->database, 'migrate_from_old_plugin')) {
                    $this->database->migrate_from_old_plugin();
                    $this->public_log('✅ Dati migrati automaticamente dal vecchio plugin PreventiviParty!');
                }
            } catch (Exception $e) {
                $this->public_log('❌ Errore migrazione: ' . $e->getMessage(), 'ERROR');
            }
        }
    }

    // ============================================================================
    // METODI GETTER PER COMPONENTI
    // ============================================================================

    /**
     * Verifica se il plugin è inizializzato
     */
    public function is_initialized() {
        return $this->initialized;
    }

    /**
     * Ottieni componente Config
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Ottieni componente Database
     */
    public function get_database() {
        return $this->database;
    }

    /**
     * Ottieni componente Auth
     */
    public function get_auth() {
        return $this->auth;
    }

    /**
     * Ottieni componente Admin
     */
    public function get_admin() {
        return $this->admin;
    }

    /**
     * Ottieni componente Storage Manager
     */
    public function get_storage_manager() {
        return $this->storage_manager;
    }

    /**
     * Ottieni componente Email Manager
     */
    public function get_email() {
        return $this->email_manager;
    }

    /**
     * Ottieni componente PDF Generator
     */
    public function get_pdf() {
        return $this->pdf_generator;
    }

    /**
     * Ottieni componente Excel Generator
     */
    public function get_excel() {
        return $this->excel_generator;
    }

    /**
     * Ottieni componente Google Drive Sync
     */
    public function get_gdrive_sync() {
        return $this->gdrive_sync;
    }

    /**
     * Ottieni componente Forms Handler
     */
    public function get_forms_handler() {
        return $this->forms_handler;
    }

    // ============================================================================
    // HOOK ATTIVAZIONE/DISATTIVAZIONE
    // ============================================================================

    /**
     * Hook attivazione plugin
     */
    public function activate_plugin() {
        try {
            $this->public_log('🔄 Attivazione plugin 747 Disco CRM v' . DISCO747_CRM_VERSION);

            // Crea tabelle database se necessario
            if ($this->database && method_exists($this->database, 'create_tables')) {
                $this->database->create_tables();
            }

            // Flush rewrite rules
            flush_rewrite_rules();

            $this->public_log('✅ Plugin attivato con successo');

        } catch (Exception $e) {
            $this->public_log('❌ Errore attivazione: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Hook disattivazione plugin
     */
    public function deactivate_plugin() {
        try {
            $this->public_log('🔄 Disattivazione plugin 747 Disco CRM');

            // Flush rewrite rules
            flush_rewrite_rules();

            $this->public_log('✅ Plugin disattivato');

        } catch (Exception $e) {
            $this->public_log('❌ Errore disattivazione: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Cleanup file vecchi
     */
    public function cleanup_old_files() {
        try {
            // Implementa logica cleanup se necessario
            $upload_dir = wp_upload_dir();
            $preventivi_dir = $upload_dir['basedir'] . '/preventivi/';
            
            if (is_dir($preventivi_dir)) {
                // Cleanup file più vecchi di 30 giorni
                $files = glob($preventivi_dir . '*');
                $now = time();
                
                foreach ($files as $file) {
                    if (is_file($file) && $now - filemtime($file) >= 30 * 24 * 60 * 60) {
                        unlink($file);
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->public_log('❌ Errore cleanup: ' . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * Mostra notice errore inizializzazione
     */
    public function show_init_error_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>747 Disco CRM:</strong> Errore durante l\'inizializzazione. Controlla i log per maggiori dettagli.';
        echo '</p></div>';
    }

    // ============================================================================
    // SISTEMA LOGGING PUBBLICO - CORRECTED
    // ============================================================================

    /**
     * Sistema di logging pubblico - CORRECTED per evitare errori
     */
    public function public_log($message, $level = 'INFO') {
        try {
            if (!$this->debug_mode) {
                return;
            }

            $timestamp = date('Y-m-d H:i:s');
            $log_entry = "[{$timestamp}] [747Disco-CRM] [{$level}] {$message}";
            
            error_log($log_entry);
            
        } catch (Exception $e) {
            // Fallback logging silenzioso
            error_log('[747Disco-CRM] Errore logging: ' . $e->getMessage());
        }
    }
}

// ========================================================================
// FUNZIONI GLOBALI DI ACCESSO
// ========================================================================

/**
 * Ottieni istanza principale del plugin
 * 
 * @return Disco747_CRM_Plugin|null
 */
function disco747_crm() {
    return Disco747_CRM_Plugin::get_instance();
}

/**
 * Verifica se il plugin è inizializzato
 * 
 * @return bool
 */
function disco747_is_ready() {
    $plugin = disco747_crm();
    return $plugin && $plugin->is_initialized();
}

/**
 * Ottieni componente specifico del plugin
 * 
 * @param string $component Nome del componente
 * @return mixed|null
 */
function disco747_get_component($component) {
    $plugin = disco747_crm();
    if (!$plugin || !$plugin->is_initialized()) {
        return null;
    }

    $method = "get_{$component}";
    if (method_exists($plugin, $method)) {
        return $plugin->$method();
    }

    return null;
}

/**
 * Ottieni statistiche preventivi
 */
function disco747_get_stats() {
    $plugin = disco747_crm();
    if (!$plugin || !$plugin->is_initialized()) {
        return array();
    }
    
    $database = $plugin->get_database();
    if (!$database) {
        return array();
    }
    
    return method_exists($database, 'get_statistics') ? $database->get_statistics() : array();
}

/**
 * Verifica permessi utente per 747 Disco CRM
 */
function disco747_user_can_access($capability = 'manage_options') {
    $plugin = disco747_crm();
    if (!$plugin || !$plugin->is_initialized()) {
        return false;
    }
    
    $auth = $plugin->get_auth();
    if (!$auth) {
        return current_user_can($capability);
    }
    
    return method_exists($auth, 'current_user_can') ? $auth->current_user_can($capability) : current_user_can($capability);
}

// ========================================================================
// INIZIALIZZAZIONE PLUGIN
// ========================================================================

/**
 * Inizializza istanza plugin quando WordPress è caricato
 * SAFE INITIALIZATION
 */
function disco747_crm_init() {
    // Verifica compatibilità WordPress
    if (version_compare(get_bloginfo('version'), '5.8', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>747 Disco CRM:</strong> Richiede WordPress 5.8 o superiore.';
            echo '</p></div>';
        });
        return;
    }
    
    // Verifica compatibilità PHP
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>747 Disco CRM:</strong> Richiede PHP 7.4 o superiore.';
            echo '</p></div>';
        });
        return;
    }
    
    // Inizializza plugin
    Disco747_CRM_Plugin::get_instance();
}

// Aggancia l'inizializzazione
add_action('plugins_loaded', 'disco747_crm_init', 5);

// Log finale per confermare il caricamento del file
if (defined('DISCO747_CRM_DEBUG') && DISCO747_CRM_DEBUG) {
    error_log('[747Disco-CRM] File principale caricato - v' . DISCO747_CRM_VERSION);
}