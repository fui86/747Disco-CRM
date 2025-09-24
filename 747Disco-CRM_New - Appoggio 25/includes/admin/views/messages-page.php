<?php
/**
 * Pagina Messaggi Automatici - 747 Disco CRM
 * VERSIONE 11.8.0 - Gestione 3 template Email + 3 template WhatsApp
 * 
 * @package Disco747_CRM
 * @version 11.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Salva impostazioni se form inviato
if (isset($_POST['save_message_templates']) && wp_verify_nonce($_POST['disco747_messages_nonce'], 'disco747_save_messages')) {
    
    // Salva Email Templates
    for ($i = 1; $i <= 3; $i++) {
        update_option('disco747_email_template_' . $i, wp_kses_post($_POST['email_template_' . $i] ?? ''));
        update_option('disco747_email_subject_' . $i, sanitize_text_field($_POST['email_subject_' . $i] ?? ''));
        update_option('disco747_email_enabled_' . $i, isset($_POST['email_enabled_' . $i]) ? 1 : 0);
    }
    
    // Salva WhatsApp Templates
    for ($i = 1; $i <= 3; $i++) {
        update_option('disco747_whatsapp_template_' . $i, sanitize_textarea_field($_POST['whatsapp_template_' . $i] ?? ''));
        update_option('disco747_whatsapp_enabled_' . $i, isset($_POST['whatsapp_enabled_' . $i]) ? 1 : 0);
    }
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Template salvati con successo!</strong></p></div>';
}

// Carica impostazioni esistenti
$email_templates = array();
$whatsapp_templates = array();

for ($i = 1; $i <= 3; $i++) {
    $email_templates[$i] = array(
        'subject' => get_option('disco747_email_subject_' . $i, ''),
        'body' => get_option('disco747_email_template_' . $i, ''),
        'enabled' => get_option('disco747_email_enabled_' . $i, 1)
    );
    
    $whatsapp_templates[$i] = array(
        'body' => get_option('disco747_whatsapp_template_' . $i, ''),
        'enabled' => get_option('disco747_whatsapp_enabled_' . $i, 1)
    );
}
?>

<div class="wrap disco747-wrap">
    <h1 class="disco747-page-title">
        <span class="disco747-icon">💬</span>
        Messaggi Automatici
    </h1>

    <div class="disco747-card" style="margin-bottom: 30px;">
        <div class="disco747-card-header">
            <h3>ℹ️ Informazioni sui Template</h3>
        </div>
        <div class="disco747-card-content">
            <p>Configura i template per email e WhatsApp che potrai inviare dopo la creazione del preventivo.</p>
            
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
                <strong>📝 Campi dinamici disponibili:</strong>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-top: 10px;">
                    <code>{{nome}}</code>
                    <code>{{cognome}}</code>
                    <code>{{nome_completo}}</code>
                    <code>{{email}}</code>
                    <code>{{telefono}}</code>
                    <code>{{data_evento}}</code>
                    <code>{{tipo_evento}}</code>
                    <code>{{menu}}</code>
                    <code>{{numero_invitati}}</code>
                    <code>{{importo}}</code>
                    <code>{{acconto}}</code>
                    <code>{{preventivo_id}}</code>
                </div>
            </div>
        </div>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('disco747_save_messages', 'disco747_messages_nonce'); ?>

        <!-- ========================================
             TEMPLATE EMAIL
        ======================================== -->
        <div class="disco747-card" style="margin-bottom: 30px;">
            <div class="disco747-card-header" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white;">
                <h2 style="margin: 0; color: white;">📧 Template Email</h2>
            </div>
            <div class="disco747-card-content">
                
                <?php for ($i = 1; $i <= 3; $i++): ?>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #007bff;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="margin: 0; color: #2b1e1a;">Template Email <?php echo $i; ?></h3>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="email_enabled_<?php echo $i; ?>" value="1" <?php checked($email_templates[$i]['enabled'], 1); ?>>
                            <span style="font-weight: 600;">Attivo</span>
                        </label>
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="email_subject_<?php echo $i; ?>">Oggetto Email</label>
                        <input type="text" 
                               id="email_subject_<?php echo $i; ?>" 
                               name="email_subject_<?php echo $i; ?>" 
                               value="<?php echo esc_attr($email_templates[$i]['subject']); ?>" 
                               placeholder="Es: Preventivo {{preventivo_id}} - {{nome_completo}}"
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div class="disco747-form-group" style="margin-top: 15px;">
                        <label for="email_template_<?php echo $i; ?>">Corpo Email (HTML)</label>
                        <textarea id="email_template_<?php echo $i; ?>" 
                                  name="email_template_<?php echo $i; ?>" 
                                  rows="12"
                                  placeholder="Scrivi il testo dell'email in HTML..."
                                  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;"><?php echo esc_textarea($email_templates[$i]['body']); ?></textarea>
                    </div>
                    
                    <div style="margin-top: 10px;">
                        <button type="button" class="disco747-button disco747-button-secondary" onclick="previewEmail(<?php echo $i; ?>)">
                            👁️ Anteprima
                        </button>
                    </div>
                </div>
                <?php endfor; ?>
                
            </div>
        </div>

        <!-- ========================================
             TEMPLATE WHATSAPP
        ======================================== -->
        <div class="disco747-card" style="margin-bottom: 30px;">
            <div class="disco747-card-header" style="background: linear-gradient(135deg, #25d366 0%, #128c7e 100%); color: white;">
                <h2 style="margin: 0; color: white;">💬 Template WhatsApp</h2>
            </div>
            <div class="disco747-card-content">
                
                <?php for ($i = 1; $i <= 3; $i++): ?>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #25d366;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 style="margin: 0; color: #2b1e1a;">Template WhatsApp <?php echo $i; ?></h3>
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="whatsapp_enabled_<?php echo $i; ?>" value="1" <?php checked($whatsapp_templates[$i]['enabled'], 1); ?>>
                            <span style="font-weight: 600;">Attivo</span>
                        </label>
                    </div>
                    
                    <div class="disco747-form-group">
                        <label for="whatsapp_template_<?php echo $i; ?>">Messaggio WhatsApp (Testo semplice)</label>
                        <textarea id="whatsapp_template_<?php echo $i; ?>" 
                                  name="whatsapp_template_<?php echo $i; ?>" 
                                  rows="8"
                                  placeholder="Scrivi il messaggio WhatsApp in testo semplice...&#10;&#10;Ciao {{nome}},&#10;grazie per averci scelto per il tuo {{tipo_evento}}!&#10;&#10;La tua festa si terrà il {{data_evento}} con il {{menu}}."
                                  style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;"><?php echo esc_textarea($whatsapp_templates[$i]['body']); ?></textarea>
                    </div>
                    
                    <div style="margin-top: 10px;">
                        <button type="button" class="disco747-button disco747-button-secondary" onclick="previewWhatsApp(<?php echo $i; ?>)">
                            👁️ Anteprima
                        </button>
                    </div>
                </div>
                <?php endfor; ?>
                
            </div>
        </div>

        <!-- Pulsante Salva -->
        <div class="disco747-form-actions">
            <button type="submit" name="save_message_templates" class="disco747-button disco747-button-primary" style="font-size: 18px; padding: 15px 30px;">
                💾 Salva Tutti i Template
            </button>
        </div>
    </form>
</div>

<!-- Modal Anteprima -->
<div id="preview-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; padding: 50px;">
    <div style="background: white; max-width: 800px; margin: 0 auto; border-radius: 12px; overflow: hidden; max-height: 90vh; display: flex; flex-direction: column;">
        <div style="background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: white;">👁️ Anteprima</h3>
            <button onclick="closePreview()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer;">×</button>
        </div>
        <div id="preview-content" style="padding: 30px; overflow-y: auto; flex: 1;">
            <!-- Contenuto anteprima -->
        </div>
    </div>
</div>

<script>
function previewEmail(templateNumber) {
    var subject = document.getElementById('email_subject_' + templateNumber).value;
    var body = document.getElementById('email_template_' + templateNumber).value;
    
    // Sostituisci placeholder con esempi
    var preview = replaceExamplePlaceholders(body);
    var previewSubject = replaceExamplePlaceholders(subject);
    
    document.getElementById('preview-content').innerHTML = 
        '<div style="margin-bottom: 20px;"><strong>Oggetto:</strong> ' + previewSubject + '</div>' +
        '<hr style="margin: 20px 0;">' +
        '<div>' + preview + '</div>';
    
    document.getElementById('preview-modal').style.display = 'block';
}

function previewWhatsApp(templateNumber) {
    var body = document.getElementById('whatsapp_template_' + templateNumber).value;
    
    // Sostituisci placeholder con esempi
    var preview = replaceExamplePlaceholders(body);
    
    // Simula aspetto WhatsApp
    document.getElementById('preview-content').innerHTML = 
        '<div style="background: #e5ddd5; padding: 20px; border-radius: 8px; font-family: system-ui, -apple-system, sans-serif;">' +
        '<div style="background: #dcf8c6; padding: 15px; border-radius: 8px; white-space: pre-wrap; line-height: 1.5;">' + 
        preview + 
        '</div>' +
        '</div>';
    
    document.getElementById('preview-modal').style.display = 'block';
}

function closePreview() {
    document.getElementById('preview-modal').style.display = 'none';
}

function replaceExamplePlaceholders(text) {
    var examples = {
        '{{nome}}': 'Mario',
        '{{cognome}}': 'Rossi',
        '{{nome_completo}}': 'Mario Rossi',
        '{{email}}': 'mario.rossi@email.com',
        '{{telefono}}': '333 1234567',
        '{{data_evento}}': '15/10/2025',
        '{{tipo_evento}}': 'Compleanno',
        '{{menu}}': 'Menu 747',
        '{{numero_invitati}}': '50',
        '{{importo}}': '€ 2.500,00',
        '{{acconto}}': '€ 500,00',
        '{{preventivo_id}}': '25001'
    };
    
    var result = text;
    for (var placeholder in examples) {
        result = result.replace(new RegExp(placeholder.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), examples[placeholder]);
    }
    
    return result;
}

// Chiudi modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePreview();
    }
});
</script>

<style>
.disco747-form-group {
    margin-bottom: 15px;
}

.disco747-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #2b1e1a;
}

.disco747-form-group input[type="text"],
.disco747-form-group textarea {
    width: 100%;
}

.disco747-button {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s;
}

.disco747-button-primary {
    background: linear-gradient(135deg, #c28a4d 0%, #b8b1b3 100%);
    color: white;
}

.disco747-button-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(194, 138, 77, 0.4);
}

.disco747-button-secondary {
    background: #6c757d;
    color: white;
}

.disco747-button-secondary:hover {
    background: #5a6268;
}
</style>