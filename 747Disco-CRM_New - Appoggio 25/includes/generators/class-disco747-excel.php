<?php
/**
 * Excel Generator Class - 747 Disco CRM
 * VERSIONE CORRETTA: Fix data evento obbligatoria
 * 
 * @package 747Disco-CRM
 * @version 11.7.0-DATE-FIXED
 * @author 747 Disco Team
 */

namespace Disco747_CRM\Generators;

defined('ABSPATH') || exit;

// Autoload PhpSpreadsheet se necessario
if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    $vendor_autoload = plugin_dir_path(dirname(dirname(__FILE__))) . 'vendor/autoload.php';
    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
    }
}

class Disco747_Excel {

    private $templates_path;
    private $output_path;
    private $debug_mode = true;
    private $autoloader_loaded = false;
    private $template_dirs = array();
    
    private $template_files = array(
        'Menu 7' => 'Menu 7.xlsx',
        'Menu 74' => 'Menu 7 - 4.xlsx',
        'Menu 7-4' => 'Menu 7 - 4.xlsx',
        'Menu 747' => 'Menu 7 - 4 - 7.xlsx',
        'Menu 7-4-7' => 'Menu 7 - 4 - 7.xlsx'
    );

    public function __construct() {
        $this->setup_paths();
        $this->log('Excel Generator v11.7.0-DATE-FIXED inizializzato');
    }

    private function setup_paths() {
        $plugin_path = plugin_dir_path(dirname(dirname(__FILE__)));
        $this->template_dirs = array(
            $plugin_path . 'templates/',
            $plugin_path . 'assets/templates/',
            $plugin_path . 'includes/templates/',
            WP_CONTENT_DIR . '/uploads/disco747/templates/'
        );
        
        $upload_dir = wp_upload_dir();
        $this->output_path = $upload_dir['basedir'] . '/preventivi/';
        
        if (!file_exists($this->output_path)) {
            wp_mkdir_p($this->output_path);
        }
    }

    public function generate_excel($data) {
        try {
            $filename = $this->generate_filename_corrected($data);
            $file_path = $this->output_path . $filename;
            
            $this->log('📄 Generando Excel: ' . $filename);
            
            $template_path = $this->find_excel_template($data['tipo_menu'] ?? 'Menu 7');
            
            $success = false;
            if ($template_path && file_exists($template_path)) {
                $this->log('📋 Usando template: ' . basename($template_path));
                $success = $this->compile_excel_from_template_corrected($template_path, $file_path, $data);
            } else {
                $this->log('⚠️ Template non trovato - uso Excel semplice');
                $success = $this->create_simple_excel($file_path, $data);
            }
            
            if ($success && file_exists($file_path) && filesize($file_path) > 0) {
                $file_size = $this->format_file_size(filesize($file_path));
                $this->log('✅ Excel generato: ' . $filename . ' (' . $file_size . ')');
                return $file_path;
            } else {
                throw new \Exception('File Excel non generato correttamente');
            }
            
        } catch (\Exception $e) {
            $this->log('❌ ERRORE generate_excel: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * CORREZIONE PRINCIPALE: Genera nome file con data evento OBBLIGATORIA
     */
    private function generate_filename_corrected($data) {
        // VERIFICA OBBLIGATORIA: la data evento DEVE essere presente
        if (empty($data['data_evento'])) {
            $this->log('⚠️ ERRORE CRITICO: data_evento mancante! Dati: ' . json_encode($data), 'ERROR');
            throw new \Exception('Data evento non presente nei dati del preventivo');
        }
        
        // Estrai giorno e mese dalla data evento
        $data_parts = explode('-', $data['data_evento']);
        
        if (count($data_parts) !== 3) {
            $this->log('⚠️ ERRORE: formato data non valido: ' . $data['data_evento'], 'ERROR');
            throw new \Exception('Formato data evento non valido: ' . $data['data_evento']);
        }
        
        $day = str_pad($data_parts[2], 2, '0', STR_PAD_LEFT);
        $month = str_pad($data_parts[1], 2, '0', STR_PAD_LEFT);
        
        $this->log('📅 Data evento estratta: ' . $day . '_' . $month . ' da ' . $data['data_evento']);
        
        $tipo_evento = $this->sanitize_filename($data['tipo_evento'] ?? 'Evento');
        $tipo_evento = substr($tipo_evento, 0, 30);
        
        $prefix = '';
        if (isset($data['stato']) && $data['stato'] === 'annullato') {
            $prefix = 'NO ';
        } elseif (isset($data['acconto']) && floatval($data['acconto']) > 0) {
            $prefix = 'CONF ';
        }
        
        $menu_type = $data['tipo_menu'] ?? 'Menu 7';
        $menu_number = str_replace('Menu ', '', $menu_type);
        
        $filename = $prefix . $day . '_' . $month . ' ' . $tipo_evento . ' (Menu ' . $menu_number . ').xlsx';
        
        $this->log('📝 Nome file Excel generato: ' . $filename);
        
        return $filename;
    }

    private function sanitize_filename($string) {
        $string = preg_replace('/[^a-zA-Z0-9\s\-àáâãäåçèéêëìíîïñòóôõöøùúûüýÿ]/u', '', $string);
        return trim($string);
    }

    private function find_excel_template($menu_type) {
        $template_file = $this->template_files[$menu_type] ?? $this->template_files['Menu 7'];
        
        foreach ($this->template_dirs as $dir) {
            $template_path = $dir . $template_file;
            if (file_exists($template_path)) {
                return $template_path;
            }
        }
        
        return false;
    }

    private function compile_excel_from_template_corrected($template_path, $output_path, $data) {
        try {
            if (!$this->check_phpspreadsheet()) {
                throw new \Exception('PhpSpreadsheet non disponibile');
            }
            
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($template_path);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $this->compile_basic_data($worksheet, $data);
            $this->compile_calculations_corrected($worksheet, $data);
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($output_path);
            
            return true;
            
        } catch (\Exception $e) {
            $this->log('❌ Errore compilazione template: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    private function compile_basic_data($worksheet, $data) {
        if (!empty($data['data_evento'])) {
            $date_obj = \DateTime::createFromFormat('Y-m-d', $data['data_evento']);
            if ($date_obj) {
                $worksheet->setCellValue('C6', $date_obj->format('d/m/Y'));
            }
        }
        
        $worksheet->setCellValue('C7', $data['tipo_evento'] ?? '');
        
        $orario_completo = ($data['orario_inizio'] ?? '19:00');
        if (!empty($data['orario_fine'])) {
            $orario_completo .= ' - ' . $data['orario_fine'];
        }
        $worksheet->setCellValue('C8', $orario_completo);
        $worksheet->setCellValue('C9', $data['numero_invitati'] ?? '');
        $worksheet->setCellValue('C11', $data['nome_referente'] ?? '');
        $worksheet->setCellValue('C12', $data['cognome_referente'] ?? '');
        $worksheet->setCellValue('C14', $data['cellulare'] ?? '');
        $worksheet->setCellValue('C15', $data['mail'] ?? '');
        $worksheet->setCellValue('C17', $data['omaggio1'] ?? '');
        $worksheet->setCellValue('C18', $data['omaggio2'] ?? '');
        $worksheet->setCellValue('C19', $data['omaggio3'] ?? '');
    }

    private function compile_calculations_corrected($worksheet, $data) {
        $importo_base = floatval($data['importo_preventivo'] ?? 0);
        $extra1_importo = floatval($data['extra1_importo'] ?? 0);
        $extra2_importo = floatval($data['extra2_importo'] ?? 0);
        $extra3_importo = floatval($data['extra3_importo'] ?? 0);
        
        $totale_extra = $extra1_importo + $extra2_importo + $extra3_importo;
        $totale_finale = $importo_base + $totale_extra;
        
        $worksheet->setCellValue('F27', $totale_finale);
        
        if (!empty($data['extra1_descrizione'])) {
            $worksheet->setCellValue('C27', $data['extra1_descrizione']);
        }
        if (!empty($data['extra2_descrizione'])) {
            $worksheet->setCellValue('C28', $data['extra2_descrizione']);
        }
        if (!empty($data['extra3_descrizione'])) {
            $worksheet->setCellValue('C29', $data['extra3_descrizione']);
        }
    }

    private function check_phpspreadsheet() {
        if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return true;
        }
        
        $autoloader_paths = array(
            __DIR__ . '/../../vendor/autoload.php',
            ABSPATH . 'vendor/autoload.php',
            ABSPATH . 'wp-content/plugins/disco747-crm/vendor/autoload.php',
            dirname(dirname(dirname(__FILE__))) . '/vendor/autoload.php'
        );
        
        foreach ($autoloader_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                $this->autoloader_loaded = true;
                return true;
            }
        }
        
        return false;
    }

    private function create_simple_excel($output_path, $data) {
        try {
            if (!$this->check_phpspreadsheet()) {
                return $this->create_simple_excel_xml($output_path, $data);
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setTitle('Preventivo');
            
            $worksheet->setCellValue('A1', '747 DISCO - PREVENTIVO');
            $worksheet->mergeCells('A1:C1');
            
            $row = 4;
            $dataFields = array(
                'Data Evento' => $this->format_italian_date($data['data_evento']),
                'Tipo Evento' => $data['tipo_evento'],
                'Referente' => $data['nome_referente'] . ' ' . ($data['cognome_referente'] ?? ''),
                'Email' => $data['mail'],
                'Cellulare' => $data['cellulare'],
                'Numero Invitati' => $data['numero_invitati'] ?? 50,
                'Tipo Menu' => $data['tipo_menu'] ?? 'Menu 7',
                'Importo' => '€' . number_format($data['importo_preventivo'], 2, ',', '.')
            );
            
            foreach ($dataFields as $label => $value) {
                $worksheet->setCellValue('A' . $row, $label);
                $worksheet->setCellValue('B' . $row, $value);
                $row++;
            }
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($output_path);
            
            return true;
            
        } catch (\Exception $e) {
            $this->log('❌ Errore Excel semplice: ' . $e->getMessage());
            return false;
        }
    }

    private function create_simple_excel_xml($output_path, $data) {
        // Implementazione XML fallback (codice esistente)
        return false;
    }

    private function format_italian_date($date_string) {
        try {
            $date = new \DateTime($date_string);
            return $date->format('d/m/Y');
        } catch (\Exception $e) {
            return $date_string;
        }
    }

    private function format_file_size($bytes) {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    private function log($message, $level = 'INFO') {
        if ($this->debug_mode) {
            error_log('[747Disco-Excel] ' . $message);
        }
    }
}