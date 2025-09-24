<?php
/**
 * Form Preventivo - 747 Disco CRM
 * VERSIONE 11.9.0 - Grafica pulsanti moderna e responsive
 * 
 * @package Disco747_CRM
 * @version 11.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Valori di default
$default_values = isset($preventivo) ? (array)$preventivo : array(
    'nome_referente' => '',
    'cognome_referente' => '',
    'cellulare' => '',
    'mail' => '',
    'codice_fiscale' => '',
    'data_evento' => '',
    'tipo_evento' => '',
    'tipo_menu' => 'Menu 7',
    'numero_invitati' => '50',
    'orario_inizio' => '20:30',
    'orario_fine' => '01:30',
    'importo_preventivo' => '1990',
    'acconto' => '',
    'omaggio1' => 'Crepes alla Nutella',
    'omaggio2' => 'Servizio Fotografico',
    'omaggio3' => '',
    'extra1' => '',
    'extra1_importo' => '',
    'extra2' => '',
    'extra2_importo' => '',
    'extra3' => '',
    'extra3_importo' => '',
    'note_interne' => '',
    'stato' => 'attivo'
);

$is_edit_mode = isset($preventivo);
$page_title = $is_edit_mode ? 'Modifica Preventivo' : 'Nuovo Preventivo';
$submit_text = $is_edit_mode ? 'Aggiorna Preventivo' : 'Crea Preventivo';
?>

<style>
/* Base Styles */
.wrap.disco747-wrap {
    max-width: 1400px;
    margin: 20px auto;
    padding: 0 20px;
}

.disco747-page-title {
    background: linear-gradient(135deg, #1e1e1e 0%, #2b1e1a 100%);
    color: #fff;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 28px;
    font-weight: 700;
}

.disco747-icon {
    font-size: 40px;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
}

/* ============================================
   NUOVA SEZIONE AZIONI - DESIGN MODERNO
   ============================================ */

#preventivo-actions-section {
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    border-radius: 20px;
    padding: 0;
    margin-bottom: 40px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    border: 1px solid rgba(194, 138, 77, 0.2);
    animation: slideDown 0.5s ease-out;
    overflow: visible;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#preventivo-actions-section .disco747-card-header {
    background: linear-gradient(135deg, #c28a4d 0%, #a67c44 100%);
    padding: 25px 35px;
    border-bottom: none;
    border-radius: 20px 20px 0 0;
    position: relative;
    overflow: hidden;
}

#preventivo-actions-section .disco747-card-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.1) 50%, transparent 70%);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

#preventivo-actions-section .disco747-card-header h3 {
    font-size: 26px;
    font-weight: 700;
    color: white;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    position: relative;
    z-index: 1;
}

#preventivo-actions-section .disco747-card-header p {
    margin: 12px 0 0 0;
    font-size: 18px;
    color: rgba(255,255,255,0.95);
    position: relative;
    z-index: 1;
}

#preventivo-actions-section .disco747-card-header strong {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: 1px;
}

#preventivo-actions-section .disco747-card-content {
    padding: 40px 35px;
}

.disco747-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
}

/* Action Card */
.disco747-action-card {
    background: linear-gradient(135deg, #2a2a2a 0%, #1e1e1e 100%);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid rgba(194, 138, 77, 0.2);
    position: relative;
}

.disco747-action-card:hover {
    transform: translateY(-8px);
    border-color: rgba(194, 138, 77, 0.5);
    box-shadow: 0 15px 40px rgba(194, 138, 77, 0.3);
}

.disco747-action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #c28a4d, #a67c44);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.disco747-action-card:hover::before {
    transform: scaleX(1);
}

/* Action Button */
.disco747-action-btn {
    width: 100%;
    padding: 25px 20px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    color: white;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.disco747-action-btn:hover {
    background: rgba(194, 138, 77, 0.1);
}

.disco747-action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.disco747-action-btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background: rgba(194, 138, 77, 0.2);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.disco747-action-btn:hover::before {
    width: 300px;
    height: 300px;
}

.disco747-action-icon {
    font-size: 42px;
    display: block;
    margin-bottom: 12px;
    filter: drop-shadow(0 3px 6px rgba(0,0,0,0.3));
    position: relative;
    z-index: 1;
}

.disco747-action-label {
    font-size: 16px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    position: relative;
    z-index: 1;
}

/* PDF Button - Rosso */
.disco747-action-card.pdf-card {
    border-color: rgba(220, 53, 69, 0.3);
}

.disco747-action-card.pdf-card:hover {
    border-color: rgba(220, 53, 69, 0.6);
    box-shadow: 0 15px 40px rgba(220, 53, 69, 0.3);
}

.disco747-action-card.pdf-card::before {
    background: linear-gradient(90deg, #dc3545, #c82333);
}

/* Email Button - Blu */
.disco747-action-card.email-card {
    border-color: rgba(0, 123, 255, 0.3);
}

.disco747-action-card.email-card:hover {
    border-color: rgba(0, 123, 255, 0.6);
    box-shadow: 0 15px 40px rgba(0, 123, 255, 0.3);
}

.disco747-action-card.email-card::before {
    background: linear-gradient(90deg, #007bff, #0056b3);
}

/* WhatsApp Button - Verde */
.disco747-action-card.whatsapp-card {
    border-color: rgba(37, 211, 102, 0.3);
}

.disco747-action-card.whatsapp-card:hover {
    border-color: rgba(37, 211, 102, 0.6);
    box-shadow: 0 15px 40px rgba(37, 211, 102, 0.3);
}

.disco747-action-card.whatsapp-card::before {
    background: linear-gradient(90deg, #25d366, #128c7e);
}

/* Template Select */
.disco747-template-select {
    margin: 15px 20px 0 20px;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.05);
    border: 2px solid rgba(194, 138, 77, 0.3);
    border-radius: 10px;
    color: white;
    font-size: 14px;
    font-weight: 600;
    width: calc(100% - 40px);
    cursor: pointer;
    transition: all 0.3s ease;
}

.disco747-template-select:hover,
.disco747-template-select:focus {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(194, 138, 77, 0.6);
    outline: none;
}

.disco747-template-select option {
    background: #2a2a2a;
    color: white;
    padding: 10px;
}

/* Checkbox Allegato */
.disco747-pdf-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 15px 20px 20px 20px;
    padding: 12px 16px;
    background: rgba(194, 138, 77, 0.1);
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    user-select: none;
}

.disco747-pdf-checkbox:hover {
    background: rgba(194, 138, 77, 0.2);
}

.disco747-pdf-checkbox input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    margin: 0;
    accent-color: #c28a4d;
}

.disco747-pdf-checkbox label {
    margin: 0 !important;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
    text-transform: none;
    letter-spacing: 0.3px;
}

/* Cards del form */
.disco747-card {
    background: #fff;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.disco747-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.disco747-card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 3px solid #c28a4d;
    padding: 20px 25px;
}

.disco747-card-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #2b1e1a;
    display: flex;
    align-items: center;
    gap: 10px;
}

.disco747-card-content {
    padding: 30px 25px;
}

.disco747-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.disco747-form-group {
    margin-bottom: 0;
}

.disco747-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2b1e1a;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.disco747-form-group input,
.disco747-form-group select,
.disco747-form-group textarea {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 15px;
    transition: all 0.3s ease;
    background: #fff;
}

.disco747-form-group input:focus,
.disco747-form-group select:focus,
.disco747-form-group textarea:focus {
    outline: none;
    border-color: #c28a4d;
    box-shadow: 0 0 0 3px rgba(194, 138, 77, 0.1);
    transform: translateY(-1px);
}

.disco747-form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.disco747-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 30px;
    padding-top: 30px;
    border-top: 2px solid #e9ecef;
}

.disco747-button {
    padding: 14px 32px;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.disco747-button-primary {
    background: linear-gradient(135deg, #c28a4d 0%, #a67c44 100%);
    color: #fff;
    box-shadow: 0 4px 15px rgba(194, 138, 77, 0.3);
}

.disco747-button-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(194, 138, 77, 0.4);
}

.disco747-button-secondary {
    background: #6c757d;
    color: #fff;
}

.disco747-button-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.disco747-extra-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

/* Responsive */
@media (max-width: 1024px) {
    .disco747-actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
}

@media (max-width: 768px) {
    .disco747-page-title {
        font-size: 22px;
        padding: 20px;
    }
    
    .disco747-icon {
        font-size: 30px;
    }
    
    .disco747-actions-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    #preventivo-actions-section .disco747-card-content {
        padding: 25px 20px;
    }
    
    .disco747-form-grid {
        grid-template-columns: 1fr;
    }
    
    .disco747-extra-grid {
        grid-template-columns: 1fr;
    }
    
    .disco747-form-actions {
        flex-direction: column-reverse;
    }
    
    .disco747-button {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .wrap.disco747-wrap {
        padding: 0 10px;
    }
    
    .disco747-card-content {
        padding: 20px 15px;
    }
    
    #preventivo-actions-section .disco747-card-header {
        padding: 20px;
    }
    
    #preventivo-actions-section .disco747-card-header h3 {
        font-size: 20px;
    }
    
    .disco747-action-icon {
        font-size: 36px;
    }
    
    .disco747-action-label {
        font-size: 14px;
    }
}

/* Loading States */
.disco747-button:disabled,
.disco747-action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.disco747-button:disabled,
.disco747-action-btn:disabled {
    animation: pulse 1.5s infinite;
}

/* Notifications */
.disco747-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 999999;
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

*:focus-visible {
    outline: 2px solid #c28a4d;
    outline-offset: 2px;
}

@media print {
    #preventivo-actions-section,
    .disco747-form-actions {
        display: none;
    }
}
</style>

<div class="wrap disco747-wrap">
    <h1 class="disco747-page-title">
        <span class="disco747-icon">📝</span>
        <?php echo esc_html($page_title); ?>
    </h1>

    <!-- SEZIONE AZIONI POST-CREAZIONE - DESIGN MODERNO -->
    <div id="preventivo-actions-section" style="display: none;">
        <div class="disco747-card-header">
            <h3>✅ Preventivo Creato con Successo!</h3>
            <p>ID: <strong><span id="created-preventivo-id"></span></strong></p>
        </div>
        <div class="disco747-card-content">
            <div class="disco747-actions-grid">
                
                <!-- PDF Card -->
                <div class="disco747-action-card pdf-card">
                    <button type="button" id="btn-generate-pdf" class="disco747-action-btn">
                        <span class="disco747-action-icon">📄</span>
                        <span class="disco747-action-label">Genera PDF</span>
                    </button>
                </div>
                
                <!-- Email Card -->
                <div class="disco747-action-card email-card">
                    <button type="button" id="btn-send-email" class="disco747-action-btn">
                        <span class="disco747-action-icon">📧</span>
                        <span class="disco747-action-label">Invia Email</span>
                    </button>
                    <select id="email-template-select" class="disco747-template-select">
                        <option value="1">📝 Template Email 1</option>
                        <option value="2">📝 Template Email 2</option>
                        <option value="3">📝 Template Email 3</option>
                    </select>
                    <div class="disco747-pdf-checkbox">
                        <input type="checkbox" id="email-attach-pdf" checked>
                        <label for="email-attach-pdf">📎 Allega PDF preventivo</label>
                    </div>
                </div>
                
                <!-- WhatsApp Card -->
                <div class="disco747-action-card whatsapp-card">
                    <button type="button" id="btn-send-whatsapp" class="disco747-action-btn">
                        <span class="disco747-action-icon">💬</span>
                        <span class="disco747-action-label">Invia WhatsApp</span>
                    </button>
                    <select id="whatsapp-template-select" class="disco747-template-select">
                        <option value="1">💬 Template WhatsApp 1</option>
                        <option value="2">💬 Template WhatsApp 2</option>
                        <option value="3">💬 Template WhatsApp 3</option>
                    </select>
                    <div class="disco747-pdf-checkbox">
                        <input type="checkbox" id="whatsapp-attach-pdf">
                        <label for="whatsapp-attach-pdf">🔗 Includi link PDF</label>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <form id="disco747-preventivo-form" method="post" class="disco747-form">
        <?php wp_nonce_field('disco747_form_nonce', 'disco747_nonce'); ?>
        
        <?php if ($is_edit_mode): ?>
            <input type="hidden" name="preventivo_id" value="<?php echo esc_attr($preventivo->id); ?>">
        <?php endif; ?>

        <!-- Dati Cliente -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                <h3>👤 Dati Cliente</h3>
            </div>
            <div class="disco747-card-content">
                <div class="disco747-form-grid">
                    <div class="disco747-form-group">
                        <label for="nome_referente">Nome *</label>
                        <input type="text" id="nome_referente" name="nome_referente" value="<?php echo esc_attr($default_values['nome_referente']); ?>" required>
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="cognome_referente">Cognome</label>
                        <input type="text" id="cognome_referente" name="cognome_referente" value="<?php echo esc_attr($default_values['cognome_referente']); ?>">
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="cellulare">Telefono</label>
                        <input type="tel" id="cellulare" name="cellulare" value="<?php echo esc_attr($default_values['cellulare']); ?>">
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="mail">Email</label>
                        <input type="email" id="mail" name="mail" value="<?php echo esc_attr($default_values['mail']); ?>">
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="codice_fiscale">Codice Fiscale</label>
                        <input type="text" id="codice_fiscale" name="codice_fiscale" value="<?php echo esc_attr($default_values['codice_fiscale']); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Dettagli Evento -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                <h3>🎉 Dettagli Evento</h3>
            </div>
            <div class="disco747-card-content">
                <div class="disco747-form-grid">
                    <div class="disco747-form-group">
                        <label for="data_evento">Data Evento *</label>
                        <input type="date" id="data_evento" name="data_evento" value="<?php echo esc_attr($default_values['data_evento']); ?>" required>
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="tipo_evento">Tipo di Evento</label>
                        <input type="text" id="tipo_evento" name="tipo_evento" value="<?php echo esc_attr($default_values['tipo_evento']); ?>" placeholder="Es: Compleanno, Laurea...">
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="tipo_menu">Tipo Menu</label>
                        <select id="tipo_menu" name="tipo_menu">
                            <option value="Menu 7" <?php selected($default_values['tipo_menu'], 'Menu 7'); ?>>Menu 7</option>
                            <option value="Menu 74" <?php selected($default_values['tipo_menu'], 'Menu 74'); ?>>Menu 74</option>
                            <option value="Menu 747" <?php selected($default_values['tipo_menu'], 'Menu 747'); ?>>Menu 747</option>
                        </select>
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="numero_invitati">Numero Invitati</label>
                        <input type="number" id="numero_invitati" name="numero_invitati" value="<?php echo esc_attr($default_values['numero_invitati']); ?>" min="0">
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="orario_inizio">Orario Inizio</label>
                        <input type="time" id="orario_inizio" name="orario_inizio" value="<?php echo esc_attr($default_values['orario_inizio']); ?>">
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="orario_fine">Orario Fine</label>
                        <input type="time" id="orario_fine" name="orario_fine" value="<?php echo esc_attr($default_values['orario_fine']); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Dati Economici -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                <h3>💰 Dati Economici</h3>
            </div>
            <div class="disco747-card-content">
                <div class="disco747-form-grid">
                    <div class="disco747-form-group">
                        <label for="importo_preventivo">Importo Totale (€)</label>
                        <input type="number" id="importo_preventivo" name="importo_preventivo" value="<?php echo esc_attr($default_values['importo_preventivo']); ?>" step="0.01" min="0">
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="acconto">Acconto Versato (€)</label>
                        <input type="number" id="acconto" name="acconto" value="<?php echo esc_attr($default_values['acconto']); ?>" step="0.01" min="0">
                    </div>
                </div>
            </div>
        </div>

        <!-- Omaggi -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                <h3>🎁 Omaggi</h3>
            </div>
            <div class="disco747-card-content">
                <div class="disco747-form-grid">
                    <div class="disco747-form-group">
                        <label for="omaggio1">Omaggio 1</label>
                        <input type="text" id="omaggio1" name="omaggio1" value="<?php echo esc_attr($default_values['omaggio1']); ?>">
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="omaggio2">Omaggio 2</label>
                        <input type="text" id="omaggio2" name="omaggio2" value="<?php echo esc_attr($default_values['omaggio2']); ?>">
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="omaggio3">Omaggio 3</label>
                        <input type="text" id="omaggio3" name="omaggio3" value="<?php echo esc_attr($default_values['omaggio3']); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Extra a Pagamento -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                <h3>➕ Extra a Pagamento</h3>
            </div>
            <div class="disco747-card-content">
                <div class="disco747-extra-grid">
                    <div class="disco747-form-group">
                        <label for="extra1">Extra 1 - Descrizione</label>
                        <input type="text" id="extra1" name="extra1" value="<?php echo esc_attr($default_values['extra1']); ?>" placeholder="Es: Servizio fotografico">
                    </div>
                    <div class="disco747-form-group">
                        <label for="extra1_importo">Prezzo (€)</label>
                        <input type="number" id="extra1_importo" name="extra1_importo" value="<?php echo esc_attr($default_values['extra1_importo']); ?>" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                
                <div class="disco747-extra-grid">
                    <div class="disco747-form-group">
                        <label for="extra2">Extra 2 - Descrizione</label>
                        <input type="text" id="extra2" name="extra2" value="<?php echo esc_attr($default_values['extra2']); ?>" placeholder="Es: Decorazioni aggiuntive">
                    </div>
                    <div class="disco747-form-group">
                        <label for="extra2_importo">Prezzo (€)</label>
                        <input type="number" id="extra2_importo" name="extra2_importo" value="<?php echo esc_attr($default_values['extra2_importo']); ?>" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                
                <div class="disco747-extra-grid">
                    <div class="disco747-form-group">
                        <label for="extra3">Extra 3 - Descrizione</label>
                        <input type="text" id="extra3" name="extra3" value="<?php echo esc_attr($default_values['extra3']); ?>" placeholder="Es: Torta personalizzata">
                    </div>
                    <div class="disco747-form-group">
                        <label for="extra3_importo">Prezzo (€)</label>
                        <input type="number" id="extra3_importo" name="extra3_importo" value="<?php echo esc_attr($default_values['extra3_importo']); ?>" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
            </div>
        </div>

        <!-- Note -->
        <div class="disco747-card">
            <div class="disco747-card-header">
                <h3>📝 Note Interne</h3>
            </div>
            <div class="disco747-card-content">
                <div class="disco747-form-group">
                    <label for="note_interne">Note</label>
                    <textarea id="note_interne" name="note_interne" rows="4" placeholder="Note visibili solo internamente..."><?php echo esc_textarea($default_values['note_interne']); ?></textarea>
                </div>
                
                <div class="disco747-form-group" style="margin-top: 20px;">
                    <label for="stato">Stato</label>
                    <select id="stato" name="stato">
                        <option value="attivo" <?php selected($default_values['stato'], 'attivo'); ?>>Attivo</option>
                        <option value="confermato" <?php selected($default_values['stato'], 'confermato'); ?>>Confermato</option>
                        <option value="annullato" <?php selected($default_values['stato'], 'annullato'); ?>>Annullato</option>
                        <option value="bozza" <?php selected($default_values['stato'], 'bozza'); ?>>Bozza</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="disco747-form-actions">
            <a href="<?php echo admin_url('admin.php?page=disco747-crm'); ?>" class="disco747-button disco747-button-secondary">
                ← Annulla
            </a>
            
            <button type="submit" name="submit_preventivo" class="disco747-button disco747-button-primary">
                <?php echo esc_html($submit_text); ?>
            </button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    var createdPreventivoId = null;
    var generatedPdfPath = null;
    
    // SUBMIT FORM
    $('#disco747-preventivo-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $submitBtn = $form.find('button[type="submit"]');
        var formData = new FormData(this);
        
        formData.append('action', 'disco747_save_preventivo');
        
        $submitBtn.prop('disabled', true).text('Salvataggio in corso...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    createdPreventivoId = response.data.preventivo_id;
                    $('#created-preventivo-id').text(createdPreventivoId);
                    $('#preventivo-actions-section').slideDown();
                    
                    $('html, body').animate({
                        scrollTop: $('#preventivo-actions-section').offset().top - 20
                    }, 500);
                    
                    showNotification('success', response.data.message || 'Preventivo salvato con successo!');
                } else {
                    showNotification('error', response.data || 'Errore durante il salvataggio');
                }
                
                $submitBtn.prop('disabled', false).text('<?php echo esc_js($submit_text); ?>');
            },
            error: function() {
                showNotification('error', 'Errore di comunicazione con il server');
                $submitBtn.prop('disabled', false).text('<?php echo esc_js($submit_text); ?>');
            }
        });
    });
    
    // GENERA PDF
    $('#btn-generate-pdf').on('click', function() {
        if (!createdPreventivoId) {
            showNotification('error', 'Crea prima il preventivo');
            return;
        }
        
        var $btn = $(this);
        $btn.prop('disabled', true).html('<span class="disco747-action-icon">⏳</span><span class="disco747-action-label">Generazione...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_generate_pdf',
                disco747_nonce: $('[name="disco747_nonce"]').val(),
                preventivo_id: createdPreventivoId
            },
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                    generatedPdfPath = response.data.pdf_path;
                    
                    var downloadUrl = ajaxurl + '?action=disco747_download_pdf&token=' + response.data.download_token;
                    var iframe = document.createElement('iframe');
                    iframe.style.display = 'none';
                    iframe.src = downloadUrl;
                    document.body.appendChild(iframe);
                    
                    setTimeout(function() {
                        document.body.removeChild(iframe);
                    }, 5000);
                } else {
                    showNotification('error', response.data || 'Errore generazione PDF');
                }
                
                $btn.prop('disabled', false).html('<span class="disco747-action-icon">📄</span><span class="disco747-action-label">Genera PDF</span>');
            },
            error: function() {
                showNotification('error', 'Errore di comunicazione');
                $btn.prop('disabled', false).html('<span class="disco747-action-icon">📄</span><span class="disco747-action-label">Genera PDF</span>');
            }
        });
    });
    
    // INVIA EMAIL
    $('#btn-send-email').on('click', function() {
        if (!createdPreventivoId) {
            showNotification('error', 'Crea prima il preventivo');
            return;
        }
        
        var $btn = $(this);
        var templateNumber = $('#email-template-select').val();
        var attachPdf = $('#email-attach-pdf').is(':checked') ? 1 : 0;
        
        $btn.prop('disabled', true).html('<span class="disco747-action-icon">⏳</span><span class="disco747-action-label">Invio...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_send_email_template',
                disco747_nonce: $('[name="disco747_nonce"]').val(),
                preventivo_id: createdPreventivoId,
                template_number: templateNumber,
                attach_pdf: attachPdf
            },
            success: function(response) {
                if (response.success) {
                    var msg = response.data.message;
                    if (response.data.pdf_attached) {
                        msg += ' (con PDF allegato)';
                    }
                    showNotification('success', msg);
                } else {
                    showNotification('error', response.data || 'Errore invio email');
                }
                
                $btn.prop('disabled', false).html('<span class="disco747-action-icon">📧</span><span class="disco747-action-label">Invia Email</span>');
            },
            error: function() {
                showNotification('error', 'Errore di comunicazione');
                $btn.prop('disabled', false).html('<span class="disco747-action-icon">📧</span><span class="disco747-action-label">Invia Email</span>');
            }
        });
    });
    
    // INVIA WHATSAPP
    $('#btn-send-whatsapp').on('click', function() {
        if (!createdPreventivoId) {
            showNotification('error', 'Crea prima il preventivo');
            return;
        }
        
        var $btn = $(this);
        var templateNumber = $('#whatsapp-template-select').val();
        var includePdfLink = $('#whatsapp-attach-pdf').is(':checked') ? 1 : 0;
        
        $btn.prop('disabled', true).html('<span class="disco747-action-icon">⏳</span><span class="disco747-action-label">Apertura...</span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'disco747_send_whatsapp_template',
                disco747_nonce: $('[name="disco747_nonce"]').val(),
                preventivo_id: createdPreventivoId,
                template_number: templateNumber,
                include_pdf_link: includePdfLink
            },
            success: function(response) {
                if (response.success && response.data.whatsapp_url) {
                    showNotification('success', response.data.message);
                    window.open(response.data.whatsapp_url, '_blank');
                } else {
                    showNotification('error', response.data || 'Errore apertura WhatsApp');
                }
                
                $btn.prop('disabled', false).html('<span class="disco747-action-icon">💬</span><span class="disco747-action-label">Invia WhatsApp</span>');
            },
            error: function() {
                showNotification('error', 'Errore di comunicazione');
                $btn.prop('disabled', false).html('<span class="disco747-action-icon">💬</span><span class="disco747-action-label">Invia WhatsApp</span>');
            }
        });
    });
    
    function showNotification(type, message) {
        var bgColor = type === 'success' ? 'linear-gradient(135deg, #28a745 0%, #20c997 100%)' : 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
        var icon = type === 'success' ? '✅' : '❌';
        
        var $notification = $('<div class="disco747-notification">')
            .css({
                background: bgColor,
                color: 'white',
                padding: '16px 24px',
                borderRadius: '12px',
                boxShadow: '0 8px 30px rgba(0,0,0,0.3)',
                fontWeight: '600',
                fontSize: '15px',
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                minWidth: '300px',
                maxWidth: '500px'
            })
            .html('<span style="font-size: 20px;">' + icon + '</span><span>' + message + '</span>')
            .appendTo('body');
        
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 4000);
    }
});
</script>