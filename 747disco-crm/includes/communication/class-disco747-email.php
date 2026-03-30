<?php
/**
 * Email Handler Class - 747 Disco CRM
 * VERSIONE CORRETTA v11.9.0 - FIX CALCOLI TOTALI PDF ALLEGATO
 * 
 * MODIFICHE v11.9.0:
 * ✅ Allineamento calcoli totali al PDF Generator
 * ✅ Aggiunti placeholder sconto_allinclusive_formatted
 * ✅ Aggiunti placeholder totale_parziale (barrato)
 * ✅ Corretti calcoli acconto e saldo
 * 
 * MODIFICHE v11.8.0:
 * ✅ Aggiunti placeholder omaggio1, omaggio2, omaggio3
 * ✅ Aggiunti placeholder extra1, extra2, extra3
 * ✅ Aggiunti importi extra1_importo, extra2_importo, extra3_importo formattati
 * ✅ Aggiunto totale_con_extra calcolato
 * 
 * @package    Disco747_CRM
 * @subpackage Communication
 * @since      11.9.0
 * @author     747 Disco Team
 */

namespace Disco747_CRM\Communication;

use Disco747_CRM\Core\Disco747_Config;
use Disco747_CRM\Generators\Disco747_Templates;
use Exception;

defined('ABSPATH') || exit;

class Disco747_Email {

    private $config;
    private $templates;
    private $smtp_config;
    private $debug_mode;
    private $delivery_log = array();

    public function __construct() {
        $this->config = Disco747_Config::get_instance();
        $this->debug_mode = $this->config->get('debug_mode', false);
        
        // Carica configurazione SMTP
        $this->smtp_config = array(
            'enabled' => $this->config->get('smtp_enabled', false),
            'host' => $this->config->get('smtp_host', ''),
            'port' => $this->config->get('smtp_port', 587),
            'username' => $this->config->get('smtp_username', ''),
            'password' => $this->config->get('smtp_password', ''),
            'encryption' => $this->config->get('smtp_encryption', 'tls'),
            'from_name' => $this->config->get('email_from_name', '747 Disco'),
            'from_email' => $this->config->get('email_from_address', 'info@gestionale.747disco.it')
        );
        
        $this->setup_hooks();
        $this->log('Email Handler v11.9.0 inizializzato - Calcoli allineati a PDF Generator');
    }

    /**
     * Setup hooks WordPress
     */
    private function setup_hooks() {
        if ($this->smtp_config['enabled']) {
            add_action('phpmailer_init', array($this, 'configure_smtp'));
        }
        add_action('wp_mail_failed', array($this, 'on_mail_failure'));
    }

    /**
     * ✅ METODO PRINCIPALE: Invia email preventivo con PDF allegato
     * 
     * @param array $preventivo_data Dati del preventivo
     * @param string $pdf_path Path del PDF da allegare (opzionale)
     * @param array $options Opzioni aggiuntive
     * @return bool Success
     */
    public function send_preventivo_email($preventivo_data, $pdf_path = null, $options = array()) {
        $this->log('Invio email preventivo per: ' . ($preventivo_data['nome_referente'] ?? 'N/A'));

        try {
            // Ottieni template_id dalle options (default 1)
            $template_id = isset($options['template_id']) ? intval($options['template_id']) : 1;
            $this->log('Template ID richiesto: ' . $template_id);
            
            // Prepara dati email
            $email_data = $this->prepare_email_data($preventivo_data, $options, $template_id);
            
            // Genera contenuto HTML dell'email usando il template selezionato
            $email_content = $this->generate_email_content($email_data, $template_id);
            
            // Prepara allegati
            $attachments = array();
            if ($pdf_path && file_exists($pdf_path)) {
                $attachments[] = $pdf_path;
                $this->log('PDF allegato: ' . basename($pdf_path));
                $this->log('PDF path completo: ' . $pdf_path);
                $this->log('PDF dimensione: ' . filesize($pdf_path) . ' bytes');
            } else {
                if ($pdf_path) {
                    $this->log('ATTENZIONE: PDF path fornito ma file non esiste: ' . $pdf_path, 'WARNING');
                } else {
                    $this->log('Nessun PDF da allegare');
                }
            }
            
            // Invia email tramite wp_mail
            $this->log('Invio wp_mail con ' . count($attachments) . ' allegati');
            $sent = wp_mail(
                $email_data['recipient_email'],
                $email_data['subject'],
                $email_content,
                $this->get_email_headers(),
                $attachments
            );
            
            // Log risultato
            $this->log_delivery($email_data, $sent, $attachments);
            
            if ($sent) {
                $this->log('✅ Email inviata con successo a: ' . $email_data['recipient_email']);
            } else {
                throw new Exception('Errore invio email tramite wp_mail');
            }
            
            return $sent;
            
        } catch (Exception $e) {
            $this->log('❌ Errore invio email: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * ✅ VERSIONE CORRETTA v11.9.0: Prepara i dati per l'email con calcoli allineati al PDF Generator
     * 
     * @param array $preventivo_data Dati preventivo
     * @param array $options Opzioni
     * @param int $template_id Template ID
     * @return array Dati email preparati
     */
    private function prepare_email_data($preventivo_data, $options = array(), $template_id = 1) {
        // Email destinatario
        $recipient_email = $preventivo_data['mail'] ?? '';
        if (!is_email($recipient_email)) {
            throw new Exception('Email destinatario non valida: ' . $recipient_email);
        }
        
        // Calcola importi extra
        $extra1_importo = floatval($preventivo_data['extra1_importo'] ?? 0);
        $extra2_importo = floatval($preventivo_data['extra2_importo'] ?? 0);
        $extra3_importo = floatval($preventivo_data['extra3_importo'] ?? 0);

        // Calcola extra totale (solo se hanno importo > 0)
        $extra_totale = 0;
        if (!empty($preventivo_data['extra1']) && $extra1_importo > 0) {
            $extra_totale += $extra1_importo;
        }
        if (!empty($preventivo_data['extra2']) && $extra2_importo > 0) {
            $extra_totale += $extra2_importo;
        }
        if (!empty($preventivo_data['extra3']) && $extra3_importo > 0) {
            $extra_totale += $extra3_importo;
        }

        // ✅ CALCOLO COME PDF GENERATOR - ALLINEAMENTO COMPLETO
        $importo_preventivo = floatval($preventivo_data['importo_preventivo'] ?? $preventivo_data['importo_totale'] ?? 0);
        $acconto = floatval($preventivo_data['acconto'] ?? 0);

        // SCONTO MENU in base al tipo di menu (come in class-disco747-pdf.php linea 121-129)
        $sconti_menu = array(
            'Menu 7' => 400,
            'Menu 7-4' => 500,
            'Menu 74' => 500,
            'Menu 7-4-7' => 600,
            'Menu 747' => 600
        );
        $tipo_menu = $preventivo_data['tipo_menu'] ?? 'Menu 7';
        $sconto_menu = $sconti_menu[$tipo_menu] ?? 400;

        // CALCOLI FINALI (formula IDENTICA al PDF generator)
        $totale_parziale = $importo_preventivo + $sconto_menu + $extra_totale; // Totale teorico con sconto
        $totale = $importo_preventivo + $extra_totale; // Totale effettivo da pagare
        $saldo = $totale - $acconto;

        // Log per debug
        $this->log('========== CALCOLI EMAIL (allineati a PDF) ==========');
        $this->log('  - Importo Base: ' . $importo_preventivo);
        $this->log('  - Sconto Menu (' . $tipo_menu . '): ' . $sconto_menu);
        $this->log('  - Extra Totale: ' . $extra_totale);
        $this->log('  - Totale Parziale (barrato): ' . $totale_parziale);
        $this->log('  - Totale (finale): ' . $totale);
        $this->log('  - Acconto: ' . $acconto);
        $this->log('  - Saldo: ' . $saldo);
        $this->log('=====================================================');
        
        // ✅ LISTA UFFICIALE: Prepara ESATTAMENTE i placeholder richiesti
        $template_vars = array(
            // === CLIENTE ===
            'nome' => $preventivo_data['nome_referente'] ?? '',
            'cognome' => $preventivo_data['cognome_referente'] ?? '',
            'nome_completo' => trim(($preventivo_data['nome_referente'] ?? '') . ' ' . ($preventivo_data['cognome_referente'] ?? '')),
            'email' => $preventivo_data['mail'] ?? $preventivo_data['email'] ?? '',
            'telefono' => $preventivo_data['telefono'] ?? $preventivo_data['cellulare'] ?? '',
            
            // === EVENTO ===
            'data_evento' => $this->format_date($preventivo_data['data_evento'] ?? ''),
            'tipo_evento' => $preventivo_data['tipo_evento'] ?? '',
            'numero_invitati' => $preventivo_data['numero_invitati'] ?? 0,
            'orario_inizio' => $this->format_time($preventivo_data['orario_inizio'] ?? '20:30'),
            'orario_fine' => $this->format_time($preventivo_data['orario_fine'] ?? '01:30'),
            
            // === MENU ===
            'menu' => $preventivo_data['tipo_menu'] ?? '',
            'tipo_menu' => $preventivo_data['tipo_menu'] ?? '',
            
            // === IMPORTI - ALLINEATI AL PDF ===
            'importo_totale' => $this->format_currency($importo_preventivo),
            'importo_preventivo' => $this->format_currency($importo_preventivo),

            // ✅ AGGIUNTI: Placeholder per sconto menu (usati nei template PDF)
            'sconto_menu' => $sconto_menu,
            'sconto_allinclusive' => $this->format_currency($sconto_menu),
            'sconto_allinclusive_formatted' => $this->format_currency($sconto_menu),

            // ✅ AGGIUNTI: Totale parziale barrato (importo base + sconto menu + extra)
            'totale_parziale' => $this->format_currency($totale_parziale),
            'totale_parziale_raw' => $totale_parziale,

            // Totale Effettivo (importo base + extra) - CORRETTO
            'totale' => $this->format_currency($totale),
            'totale_raw' => $totale,
            'totale_lordo' => $this->format_currency($totale),
            'totale_finale' => $this->format_currency($totale),

            // Acconto e Saldo - CORRETTI (calcolati sul totale finale)
            'acconto' => $this->format_currency($acconto),
            'acconto_raw' => $acconto,
            'acconto_formatted' => $this->format_currency($acconto),

            'saldo' => $this->format_currency($saldo),
            'saldo_raw' => $saldo,
            
            // === EXTRA ===
            'extra1' => $preventivo_data['extra1'] ?? '',
            'extra2' => $preventivo_data['extra2'] ?? '',
            'extra3' => $preventivo_data['extra3'] ?? '',
            
            // === OMAGGI ===
            'omaggio1' => $preventivo_data['omaggio1'] ?? '',
            'omaggio2' => $preventivo_data['omaggio2'] ?? '',
            'omaggio3' => $preventivo_data['omaggio3'] ?? '',
            
            // === ALTRO ===
            'preventivo_id' => $preventivo_data['preventivo_id'] ?? '',
            'stato' => $preventivo_data['stato'] ?? 'attivo',
            
            // === PLACEHOLDER AGGIUNTIVI PER CALCOLI INTERNI (non documentati ma utili) ===
            'extra1_importo' => $extra1_importo,
            'extra1_importo_formatted' => $this->format_currency($extra1_importo),
            'extra2_importo' => $extra2_importo,
            'extra2_importo_formatted' => $this->format_currency($extra2_importo),
            'extra3_importo' => $extra3_importo,
            'extra3_importo_formatted' => $this->format_currency($extra3_importo),
            'extra_totale' => $this->format_currency($extra_totale),
            'prezzo_extra_persona' => $this->get_prezzo_extra_persona($preventivo_data['tipo_menu'] ?? 'Menu 74')
        );
        
        // Log debug variabili
        $this->log('========== PLACEHOLDER EMAIL PREPARATI ==========');
        $this->log('Omaggi: ' . ($template_vars['omaggio1'] ? '✓' : '✗') . ' ' . 
                                ($template_vars['omaggio2'] ? '✓' : '✗') . ' ' .
                                ($template_vars['omaggio3'] ? '✓' : '✗'));
        $this->log('Extra: ' . ($template_vars['extra1'] ? '✓' : '✗') . ' ' .
                              ($template_vars['extra2'] ? '✓' : '✗') . ' ' .
                              ($template_vars['extra3'] ? '✓' : '✗'));
        $this->log('Extra totale: ' . $template_vars['extra_totale']);
        $this->log('Totale parziale: ' . $template_vars['totale_parziale']);
        $this->log('Sconto menu: ' . $template_vars['sconto_allinclusive_formatted']);
        $this->log('==============================================');
        
        // Oggetto email dal template salvato
        $subject = get_option('disco747_email_subject_' . $template_id, 'Il tuo preventivo 747 Disco è pronto! 🎉');
        
        return array(
            'recipient_email' => $recipient_email,
            'subject' => $subject,
            'template_vars' => $template_vars,
            'template_id' => $template_id,
            'options' => $options
        );
    }

    /**
     * ✅ NUOVO METODO: Calcola prezzo extra persona in base al menu
     * 
     * @param string $tipo_menu
     * @return string Prezzo formattato (es: "20,00")
     */
    private function get_prezzo_extra_persona($tipo_menu) {
        $prezzi = array(
            'Menu 7' => '20,00',
            'Menu 74' => '25,00',
            'Menu 7-4' => '25,00',  // Alias
            'Menu 747' => '30,00',
            'Menu 7-4-7' => '30,00'  // Alias
        );
        
        return $prezzi[$tipo_menu] ?? '25,00'; // Default 25,00
    }

    /**
     * ✅ Genera contenuto HTML dell'email con il TEMPLATE BELLISSIMO
     * 
     * @param array $email_data Dati email preparati
     * @param int $template_id Template ID
     * @return string HTML content
     */
    private function generate_email_content($email_data, $template_id = 1) {
        // Recupera template HTML personalizzato dal database
        $custom_template = get_option('disco747_email_template_' . $template_id, '');
        
        if (!empty($custom_template)) {
            // Sostituisci placeholder nel template personalizzato
            $html = $this->replace_placeholders($custom_template, $email_data['template_vars']);
            $this->log('✅ Template personalizzato compilato con successo');
            return $html;
        }
        
        // Fallback: template hardcoded se nessun template salvato
        $this->log('⚠️ Nessun template personalizzato, uso template di default');
        return $this->get_default_template($email_data['template_vars']);
    }

    /**
     * ✅ Sostituisce i placeholder nel template con i valori
     * 
     * @param string $template Template HTML
     * @param array $vars Variabili da sostituire
     * @return string Template compilato
     */
    private function replace_placeholders($template, $vars) {
        foreach ($vars as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $template = str_replace($placeholder, esc_html($value), $template);
        }
        
        return $template;
    }

    /**
     * ✅ Template email di default (bellissimo!)
     * 
     * @param array $vars Variabili template
     * @return string HTML template
     */
    private function get_default_template($vars) {
        // Estrai variabili
        $nome = esc_html($vars['nome'] ?? '');
        $cognome = esc_html($vars['cognome'] ?? '');
        $data_evento = esc_html($vars['data_evento']);
        $tipo_evento = esc_html($vars['tipo_evento']);
        $numero_invitati = esc_html($vars['numero_invitati']);
        $tipo_menu = esc_html($vars['tipo_menu']);
        $orario_inizio = esc_html($vars['orario_inizio']);
        $orario_fine = esc_html($vars['orario_fine']);
        $importo = esc_html($vars['totale']); // ✅ Usa totale finale
        $prezzo_extra = esc_html($vars['prezzo_extra_persona']);
        
        // Costruisci lista omaggi
        $omaggi_html = '';
        if (!empty($vars['omaggio1'])) {
            $omaggi_html .= '<li style="padding: 8px 0; color: #333; font-size: 14px;">🎁 ' . esc_html($vars['omaggio1']) . '</li>';
        }
        if (!empty($vars['omaggio2'])) {
            $omaggi_html .= '<li style="padding: 8px 0; color: #333; font-size: 14px;">🎁 ' . esc_html($vars['omaggio2']) . '</li>';
        }
        if (!empty($vars['omaggio3'])) {
            $omaggi_html .= '<li style="padding: 8px 0; color: #333; font-size: 14px;">🎁 ' . esc_html($vars['omaggio3']) . '</li>';
        }
        
        // Costruisci lista extra a pagamento (solo se presenti)
        $extra_html = '';
        if (!empty($vars['extra1']) && $vars['extra1_importo'] > 0) {
            $extra_html .= '<li style="padding: 8px 0; color: #333; font-size: 14px;">💰 ' . esc_html($vars['extra1']) . ' - ' . esc_html($vars['extra1_importo_formatted']) . '</li>';
        }
        if (!empty($vars['extra2']) && $vars['extra2_importo'] > 0) {
            $extra_html .= '<li style="padding: 8px 0; color: #333; font-size: 14px;">💰 ' . esc_html($vars['extra2']) . ' - ' . esc_html($vars['extra2_importo_formatted']) . '</li>';
        }
        if (!empty($vars['extra3']) && $vars['extra3_importo'] > 0) {
            $extra_html .= '<li style="padding: 8px 0; color: #333; font-size: 14px;">💰 ' . esc_html($vars['extra3']) . ' - ' . esc_html($vars['extra3_importo_formatted']) . '</li>';
        }
        
        $html = '<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Il tuo preventivo 747 Disco</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #0a0a0a; color: #ffffff;">
    
    <div style="max-width: 600px; margin: 0 auto; background-color: #1a1a1a;">
        
        <!-- HEADER CON LOGO -->
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #f4d03f 100%); padding: 40px 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 36px; font-weight: 700; color: #000000; text-transform: uppercase; letter-spacing: 3px;">
                747 DISCO
            </h1>
            <p style="margin: 10px 0 0; font-size: 14px; color: rgba(0,0,0,0.7); text-transform: uppercase; letter-spacing: 2px;">
                La tua festa, la nostra passione
            </p>
        </div>
        
        <!-- CONTENUTO PRINCIPALE -->
        <div style="padding: 30px 25px; background-color: #ffffff; color: #333333;">
            
            <!-- Saluto personalizzato -->
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin: 0 0 20px;">
                Gentile <strong>' . $nome . ' ' . $cognome . '</strong>,
            </p>
            
            <p style="color: #333; font-size: 16px; line-height: 1.6; margin-bottom: 25px;">
                Siamo lieti di inviarle il preventivo per il suo <strong>' . $tipo_evento . '</strong> del <strong>' . $data_evento . '</strong>. 
                Il nostro team è pronto a rendere la sua festa un\'esperienza indimenticabile!
            </p>
            
            <!-- Box dettagli evento -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 12px; margin: 25px 0; border-left: 4px solid #c28a4d;">
                <h2 style="margin: 0 0 15px; color: #c28a4d; font-size: 20px; font-weight: 600;">
                    📋 Dettagli Evento
                </h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-size: 14px; width: 40%;">🗓️ Data</td>
                        <td style="padding: 8px 0; color: #333; font-weight: 600; font-size: 14px;">' . $data_evento . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-size: 14px;">⏰ Orario</td>
                        <td style="padding: 8px 0; color: #333; font-weight: 600; font-size: 14px;">' . $orario_inizio . ' - ' . $orario_fine . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-size: 14px;">👥 Invitati</td>
                        <td style="padding: 8px 0; color: #333; font-weight: 600; font-size: 14px;">' . $numero_invitati . ' persone</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-size: 14px;">🍽️ Menu</td>
                        <td style="padding: 8px 0; color: #333; font-weight: 600; font-size: 14px;">' . $tipo_menu . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666; font-size: 14px;">➕ Extra persona</td>
                        <td style="padding: 8px 0; color: #333; font-weight: 600; font-size: 14px;">€' . $prezzo_extra . '</td>
                    </tr>
                </table>
            </div>';
        
        // Aggiungi sezione omaggi se presenti
        if (!empty($omaggi_html)) {
            $html .= '
            <!-- Box omaggi inclusi -->
            <div style="background: #e8f5e9; padding: 20px; border-radius: 12px; margin: 25px 0; border-left: 4px solid #4caf50;">
                <h3 style="margin: 0 0 10px; color: #2e7d32; font-size: 18px; font-weight: 600;">
                    🎁 Omaggi Inclusi nel Pacchetto
                </h3>
                <p style="color: #666; margin: 10px 0; font-size: 14px;">Ecco cosa è incluso gratuitamente:</p>
                <ul style="list-style: none; padding: 0; margin: 15px 0;">
                    ' . $omaggi_html . '
                </ul>
            </div>';
        }
        
        // Aggiungi sezione extra se presenti
        if (!empty($extra_html)) {
            $html .= '
            <!-- Box extra a pagamento -->
            <div style="background: #fff3e0; padding: 20px; border-radius: 12px; margin: 25px 0; border-left: 4px solid #ff9800;">
                <h3 style="margin: 0 0 10px; color: #e65100; font-size: 18px; font-weight: 600;">
                    💰 Extra a Pagamento Selezionati
                </h3>
                <ul style="list-style: none; padding: 0; margin: 15px 0;">
                    ' . $extra_html . '
                </ul>
            </div>';
        }
        
        $html .= '
            <!-- Box extra disponibili -->
            <div style="background: #e3f2fd; padding: 20px; border-radius: 12px; margin: 25px 0; border-left: 4px solid #2196f3;">
                <h3 style="margin: 0 0 10px; color: #1565c0; font-size: 18px; font-weight: 600;">
                    ✨ Extra Disponibili
                </h3>
                <p style="color: #666; margin: 10px 0; font-size: 14px;">Aggiunga uno di questi extra esclusivi:</p>
                <ul style="list-style: none; padding: 0; margin: 15px 0;">
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🥂 <strong>Aperol Spritz in aperitivo</strong> - €80,00</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍺 <strong>Birra per tutta la cena</strong> - €3,00/persona</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍷 <strong>Vino per tutta la cena</strong> - €2,50/persona</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍤 <strong>Frittini misti</strong> - €4,50/persona</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍉 <strong>Tagliata di frutta</strong> - €120,00</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍫 <strong>Fontana di cioccolato</strong> - €260,00</li>
                    <li style="padding: 8px 0; color: #333; font-size: 14px;">🍾 <strong>Consumazioni alcoliche</strong> - €4,00 cad.</li>
                </ul>
            </div>
            
            <!-- Box investimento -->
            <div style="background: linear-gradient(135deg, #2b1e1a 0%, #3c3c3c 100%); padding: 25px; border-radius: 12px; margin: 25px 0; text-align: center;">
                <p style="margin: 0; color: rgba(255,255,255,0.8); font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Investimento Totale</p>
                <p style="margin: 10px 0; color: #c28a4d; font-size: 42px; font-weight: bold;">' . $importo . '</p>
                <p style="margin: 5px 0 0; color: rgba(255,255,255,0.7); font-size: 13px;">
                    <em>Include tutto quanto descritto + omaggi del valore di €450</em>
                </p>
            </div>
            
            <!-- CTA PRINCIPALE -->
            <div style="background: #f0f9ff; padding: 25px; border-radius: 12px; margin: 25px 0; text-align: center; border: 2px solid #3b82f6;">
                <p style="color: #1e40af; font-size: 18px; font-weight: bold; margin: 0 0 15px;">
                    ⏰ Confermi entro 7 giorni e si assicuri:<br>
                    <span style="font-size: 24px; color: #c28a4d;">€450 di omaggi GRATIS!</span>
                </p>
                <a href="https://wa.me/393331234567?text=Salve%2C%20vorrei%20confermare%20il%20preventivo%20per%20' . urlencode($tipo_evento) . '" 
                   style="display: inline-block; background: #25d366; color: white; padding: 18px 45px; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 17px; margin: 10px 0; box-shadow: 0 4px 12px rgba(37,211,102,0.3);">
                    💬 Conferma Subito su WhatsApp
                </a>
                <p style="margin: 15px 0 0; color: #666; font-size: 13px;">
                    Oppure risponda a questa email o ci chiami al numero in calce
                </p>
            </div>
            
            <p style="color: #333; line-height: 1.6; font-size: 15px; margin-top: 25px;">
                Il preventivo completo è allegato in PDF. Siamo a disposizione per qualsiasi chiarimento o personalizzazione.
            </p>
            
            <p style="color: #333; line-height: 1.6; font-size: 15px; margin-top: 20px;">
                <strong>Non vediamo l\'ora di rendere il suo ' . $tipo_evento . ' indimenticabile!</strong>
            </p>
        </div>
        
        <!-- Footer -->
        <div style="text-align: center; color: #666; font-size: 12px; margin-top: 30px; padding: 20px;">
            <p style="margin: 5px 0;">
                <strong style="color: #c28a4d; font-size: 16px;">747 DISCO</strong><br>
                <em style="color: #999;">La tua festa, la nostra passione</em>
            </p>
            <p style="margin: 15px 0 5px;">
                📧 info@gestionale.747disco.it | 📞 +39 333 123 4567<br>
                🌐 <a href="https://www.gestionale.747disco.it" style="color: #c28a4d; text-decoration: none;">www.gestionale.747disco.it</a>
            </p>
            <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <p style="margin: 0; font-size: 11px; color: #999; line-height: 1.4;">
                    Hai ricevuto questa email perché hai richiesto un preventivo a 747 Disco.<br>
                    Se non sei interessato, puoi ignorare questa comunicazione.
                </p>
            </div>
        </div>
        
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Invia email con allegato (wrapper pubblico)
     */
    public function send_email_with_attachment($preventivo_data, $pdf_path, $is_update = false) {
        $options = array(
            'is_update' => $is_update,
            'template_type' => $is_update ? 'preventivo_aggiornato' : 'preventivo_nuovo'
        );
        
        $success = $this->send_preventivo_email($preventivo_data, $pdf_path, $options);
        
        return array(
            'success' => $success,
            'message' => $success ? 'Email inviata con successo' : 'Errore invio email'
        );
    }

    /**
     * ✅ Headers email con BCC automatico all'utente corrente
     */
    private function get_email_headers() {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->smtp_config['from_name'] . ' <' . $this->smtp_config['from_email'] . '>'
        );
        
        // ✅ NUOVO: Aggiungi BCC all'utente WordPress corrente che gestisce il preventivo
        $current_user = wp_get_current_user();
        if ($current_user && $current_user->user_email) {
            $headers[] = 'Bcc: ' . $current_user->user_email;
            $this->log('✅ BCC aggiunto automaticamente a: ' . $current_user->user_email);
        }
        
        return $headers;
    }

    /**
     * Configura SMTP per PHPMailer
     */
    public function configure_smtp($phpmailer) {
        if (!$this->smtp_config['enabled']) {
            return;
        }
        
        $phpmailer->isSMTP();
        $phpmailer->Host = $this->smtp_config['host'];
        $phpmailer->Port = $this->smtp_config['port'];
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $this->smtp_config['username'];
        $phpmailer->Password = $this->smtp_config['password'];
        $phpmailer->SMTPSecure = $this->smtp_config['encryption'];
        
        if ($this->debug_mode) {
            $phpmailer->SMTPDebug = 2;
        }
    }

    /**
     * Callback errore invio
     */
    public function on_mail_failure($wp_error) {
        $this->log("Errore invio email: " . $wp_error->get_error_message(), 'ERROR');
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
     * Formatta orario (rimuove secondi)
     */
    private function format_time($time_string) {
        if (empty($time_string)) return '';
        
        // Rimuove secondi se presenti (20:30:00 -> 20:30)
        return substr($time_string, 0, 5);
    }

    /**
     * Formatta valuta
     */
    private function format_currency($amount) {
        return '€' . number_format(floatval($amount), 2, ',', '.');
    }

    /**
     * Log delivery
     */
    private function log_delivery($email_data, $success, $attachments = array()) {
        $delivery_record = array(
            'timestamp' => current_time('mysql'),
            'recipient' => $email_data['recipient_email'],
            'subject' => $email_data['subject'],
            'success' => $success,
            'attachments_count' => count($attachments),
            'method' => $this->smtp_config['enabled'] ? 'SMTP' : 'PHP_MAIL'
        );
        
        $this->delivery_log[] = $delivery_record;
    }

    /**
     * Log centralizzato
     */
    private function log($message, $level = 'INFO') {
        if ($this->debug_mode || $level === 'ERROR') {
            error_log("[747Disco-Email] [{$level}] {$message}");
        }
    }
}