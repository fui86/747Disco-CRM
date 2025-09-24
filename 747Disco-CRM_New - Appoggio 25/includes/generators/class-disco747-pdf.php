<?php
/**
 * PDF Generator Class - 747 Disco CRM
 * VERSIONE CORRETTA: Fix cellulare, totale parziale e acconto
 * 
 * @package 747Disco-CRM
 * @version 11.7.2-BUGFIX
 * @author 747 Disco Team
 */

namespace Disco747_CRM\Generators;

defined('ABSPATH') || exit;

if (!defined('PDF_PAGE_ORIENTATION')) {
    define('PDF_PAGE_ORIENTATION', 'P');
    define('PDF_UNIT', 'mm');
    define('PDF_PAGE_FORMAT', 'A4');
}

if (!class_exists('\Dompdf\Dompdf')) {
    $vendor_autoload = plugin_dir_path(dirname(dirname(__FILE__))) . 'vendor/autoload.php';
    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
    }
}

class Disco747_PDF {

    private $templates_path;
    private $output_path;
    private $debug_mode = true;

    public function __construct() {
        $this->templates_path = plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/';
        $this->output_path = wp_upload_dir()['basedir'] . '/preventivi/';
        
        if (!file_exists($this->output_path)) {
            wp_mkdir_p($this->output_path);
        }
        
        $this->log('PDF Generator v11.7.2-BUGFIX inizializzato');
    }

    public function generate_pdf($data) {
        try {
            $this->log('🚀 Avvio generazione PDF per ' . ($data['tipo_evento'] ?? 'evento'));
            
            $pdf_filename = $this->generate_filename($data);
            $pdf_path = $this->output_path . $pdf_filename;
            
            $template_file = $this->get_template_file($data['tipo_menu'] ?? 'Menu 7');
            $template_path = $this->templates_path . $template_file;
            
            if (!file_exists($template_path)) {
                $this->log('⚠️ Template HTML non trovato, uso generazione semplice');
                return $this->generate_simple_pdf($pdf_path, $data);
            }
            
            // ✅ CORREZIONE: Prepara tutti i dati per il template
            $prepared_data = $this->prepare_pdf_data($data);
            
            $html_content = $this->compile_template($template_path, $prepared_data);
            $result = $this->create_pdf_with_dompdf($html_content, $pdf_path);
            
            if ($result && file_exists($pdf_path)) {
                $this->log('✅ PDF generato: ' . $pdf_filename);
                return $pdf_path;
            }
            
            return false;
            
        } catch (\Exception $e) {
            $this->log('❌ Errore PDF: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * ✅ METODO CORRETTO: Prepara tutti i dati per il template PDF
     * FIX: 1) cellulare, 2) totale parziale con +30%, 3) acconto formattato
     */
    private function prepare_pdf_data($data) {
        // Mappa e calcola tutti i campi necessari
        $importo_preventivo = floatval($data['importo_preventivo'] ?? 0);
        $acconto = floatval($data['acconto'] ?? 0);
        
        // ✅ FIX #2: Calcola totale parziale = importo + 30% (che è lo sconto)
        $totale_con_sconto = $importo_preventivo * 1.30;
        
        // Calcola extra se presenti
        $extra_totale = 0;
        if (!empty($data['extra1']) && !empty($data['extra1_importo'])) {
            $extra_totale += floatval($data['extra1_importo']);
        }
        if (!empty($data['extra2']) && !empty($data['extra2_importo'])) {
            $extra_totale += floatval($data['extra2_importo']);
        }
        if (!empty($data['extra3']) && !empty($data['extra3_importo'])) {
            $extra_totale += floatval($data['extra3_importo']);
        }
        
        $totale_finale = $importo_preventivo + $extra_totale;
        $saldo = $totale_finale - $acconto;
        
        // Prepara display omaggi
        $omaggi = array();
        if (!empty($data['omaggio1'])) $omaggi[] = $data['omaggio1'];
        if (!empty($data['omaggio2'])) $omaggi[] = $data['omaggio2'];
        if (!empty($data['omaggio3'])) $omaggi[] = $data['omaggio3'];
        $omaggi_display = !empty($omaggi) ? implode(', ', $omaggi) : 'Nessuno';
        
        // Prepara display extra
        $extra = array();
        if (!empty($data['extra1'])) {
            $extra[] = $data['extra1'] . ' - €' . number_format(floatval($data['extra1_importo'] ?? 0), 2, ',', '.');
        }
        if (!empty($data['extra2'])) {
            $extra[] = $data['extra2'] . ' - €' . number_format(floatval($data['extra2_importo'] ?? 0), 2, ',', '.');
        }
        if (!empty($data['extra3'])) {
            $extra[] = $data['extra3'] . ' - €' . number_format(floatval($data['extra3_importo'] ?? 0), 2, ',', '.');
        }
        $extra_display = !empty($extra) ? implode(', ', $extra) : 'Nessuno';
        
        // Prepara array completo per template
        $prepared = array(
            // Dati originali
            'nome_referente' => $data['nome_referente'] ?? '',
            'cognome_referente' => $data['cognome_referente'] ?? '',
            'telefono' => $data['cellulare'] ?? '',
            'data_evento' => $this->format_date($data['data_evento'] ?? ''),
            'tipo_evento' => $data['tipo_evento'] ?? '',
            'orario' => $this->format_orario($data),
            'numero_invitati' => $data['numero_invitati'] ?? '',
            'tipo_menu' => $data['tipo_menu'] ?? 'Menu 7',
            
            // ✅ FIX #1: Aggiungi anche 'cellulare' come campo separato
            'cellulare' => $data['cellulare'] ?? '',
            
            // ✅ MAPPATURE CORRETTE per i campi mancanti
            'email' => $data['mail'] ?? '',  // Mappa mail → email
            
            // ✅ FIX #2: Totale parziale ora include il 30% di sconto
            'totale_parziale' => '€' . number_format($totale_con_sconto, 2, ',', '.'),
            
            'sconto_allinclusive_formatted' => '€' . number_format($totale_con_sconto - $importo_preventivo, 2, ',', '.'),
            'totale' => '€' . number_format($totale_finale, 2, ',', '.'),
            'saldo' => '€' . number_format($saldo, 2, ',', '.'),
            'omaggi_display' => $omaggi_display,
            'extra_display' => $extra_display,
            
            // Campi numerici
            'importo_preventivo' => $importo_preventivo,
            
            // ✅ FIX #3: Usa sempre la versione formattata per l'acconto
            'acconto' => '€' . number_format($acconto, 2, ',', '.'),
            'acconto_formatted' => '€' . number_format($acconto, 2, ',', '.'),
            
            // Omaggi separati
            'omaggio1' => $data['omaggio1'] ?? '',
            'omaggio2' => $data['omaggio2'] ?? '',
            'omaggio3' => $data['omaggio3'] ?? '',
            
            // Extra separati
            'extra1' => $data['extra1'] ?? '',
            'extra2' => $data['extra2'] ?? '',
            'extra3' => $data['extra3'] ?? '',
        );
        
        $this->log('✅ Dati preparati per template PDF (cellulare: ' . $prepared['cellulare'] . ')');
        $this->log('✅ Totale parziale con sconto 30%: ' . $prepared['totale_parziale']);
        $this->log('✅ Acconto formattato: ' . $prepared['acconto']);
        
        return $prepared;
    }

    /**
     * Formatta data in italiano
     */
    private function format_date($date_string) {
        if (empty($date_string)) return '';
        
        $timestamp = strtotime($date_string);
        if (!$timestamp) return $date_string;
        
        $mesi = array(
            1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
            5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
            9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
        );
        
        $giorno = date('d', $timestamp);
        $mese = $mesi[intval(date('m', $timestamp))];
        $anno = date('Y', $timestamp);
        
        return "$giorno $mese $anno";
    }

    /**
     * Formatta orario (rimuove secondi se presenti)
     */
    private function format_orario($data) {
        $orario_inizio = $data['orario_inizio'] ?? '';
        $orario_fine = $data['orario_fine'] ?? '';
        
        // ✅ FIX: Rimuovi secondi se presenti (20:30:00 -> 20:30)
        if (!empty($orario_inizio)) {
            $orario_inizio = substr($orario_inizio, 0, 5);
        }
        if (!empty($orario_fine)) {
            $orario_fine = substr($orario_fine, 0, 5);
        }
        
        if (empty($orario_inizio)) return '';
        if (empty($orario_fine)) return $orario_inizio;
        
        return "$orario_inizio - $orario_fine";
    }

    /**
     * Genera nome file con data evento OBBLIGATORIA
     */
    private function generate_filename($data) {
        if (empty($data['data_evento'])) {
            $this->log('⚠️ ERRORE CRITICO: data_evento mancante per PDF!', 'ERROR');
            throw new \Exception('Data evento non presente nei dati del preventivo');
        }
        
        $data_parts = explode('-', $data['data_evento']);
        
        if (count($data_parts) !== 3) {
            $this->log('⚠️ ERRORE: formato data non valido: ' . $data['data_evento'], 'ERROR');
            throw new \Exception('Formato data evento non valido: ' . $data['data_evento']);
        }
        
        $day = str_pad($data_parts[2], 2, '0', STR_PAD_LEFT);
        $month = str_pad($data_parts[1], 2, '0', STR_PAD_LEFT);
        
        $this->log('📅 PDF - Data evento estratta: ' . $day . '_' . $month . ' da ' . $data['data_evento']);
        
        $tipo_evento = $this->sanitize_filename($data['tipo_evento'] ?? 'Evento');
        $tipo_evento = substr($tipo_evento, 0, 50);
        
        $prefix = '';
        if (isset($data['stato']) && $data['stato'] === 'annullato') {
            $prefix = 'NO ';
        } elseif (isset($data['acconto']) && floatval($data['acconto']) > 0) {
            $prefix = 'CONF ';
        }
        
        $menu_type = $data['tipo_menu'] ?? 'Menu 7';
        $menu_number = str_replace('Menu ', '', $menu_type);
        
        $filename = $prefix . $day . '_' . $month . ' ' . $tipo_evento . ' (Menu ' . $menu_number . ').pdf';
        
        $this->log('📝 Nome file PDF generato: ' . $filename);
        
        return $filename;
    }

    private function sanitize_filename($string) {
        $string = preg_replace('/[^a-zA-Z0-9\s\-àáâãäåçèéêëìíîïñòóôõöøùúûüýÿ]/u', '', $string);
        return trim($string);
    }

    private function get_template_file($menu_type) {
        $mapping = array(
            'Menu 7' => 'menu-7-template.html',
            'Menu 74' => 'menu-7-4-template.html',
            'Menu 7-4' => 'menu-7-4-template.html',
            'Menu 747' => 'menu-7-4-7-template.html',
            'Menu 7-4-7' => 'menu-7-4-7-template.html'
        );
        
        return $mapping[$menu_type] ?? 'menu-7-template.html';
    }

    /**
     * Compila template sostituendo i placeholder
     */
    private function compile_template($template_path, $data) {
        $html = file_get_contents($template_path);
        
        // Sostituisci tutti i placeholder
        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $html = str_replace($placeholder, $value, $html);
        }
        
        return $html;
    }

    private function create_pdf_with_dompdf($html_content, $output_path) {
        try {
            if (!class_exists('\Dompdf\Dompdf')) {
                throw new \Exception('Dompdf non disponibile');
            }
            
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html_content);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            file_put_contents($output_path, $dompdf->output());
            
            return true;
            
        } catch (\Exception $e) {
            $this->log('❌ Errore Dompdf: ' . $e->getMessage());
            return false;
        }
    }

    private function generate_simple_pdf($output_path, $data) {
        // Implementazione semplice fallback
        return false;
    }

    private function log($message, $level = 'INFO') {
        if ($this->debug_mode) {
            error_log('[747 PDF] ' . $level . ': ' . $message);
        }
    }
}