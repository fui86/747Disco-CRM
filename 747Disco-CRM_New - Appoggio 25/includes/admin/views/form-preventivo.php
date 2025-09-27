<?php
/**
 * Template per il form preventivo
 * Supporta precompilazione da Excel analysis
 * 
 * @package    Disco747_CRM
 * @subpackage Admin/Views
 * @version    11.5.9-EXCEL-SCAN
 */

if (!defined('ABSPATH')) {
    exit('Accesso diretto non consentito');
}

// Determina se stiamo modificando o creando
$is_edit = isset($preventivo) && !empty($preventivo->id);
$from_excel = isset($_GET['source']) && $_GET['source'] === 'excel_analysis';

// Helper function per ottenere valore campo
function get_field_value($preventivo, $field, $default = '') {
    if (is_object($preventivo) && isset($preventivo->$field)) {
        return $preventivo->$field;
    } elseif (is_array($preventivo) && isset($preventivo[$field])) {
        return $preventivo[$field];
    }
    return $default;
}
?>

<div class="wrap disco747-form-preventivo">
    <h1>
        <?php echo $is_edit ? '✏️ Modifica Preventivo' : '➕ Nuovo Preventivo'; ?>
        <?php if ($from_excel): ?>
            <span class="badge badge-info" style="margin-left: 10px; font-size: 0.6em;">
                Precompilato da Excel
            </span>
        <?php endif; ?>
    </h1>
    
    <?php if ($from_excel && isset($preventivo->source_filename)): ?>
        <div class="notice notice-info">
            <p>
                <strong>📁 Dati precompilati dal file Excel:</strong> 
                <?php echo esc_html($preventivo->source_filename); ?>
            </p>
        </div>
    <?php endif; ?>
    
    <form id="form-preventivo" method="post" action="" class="disco747-form">
        <?php wp_nonce_field('disco747_save_preventivo', 'disco747_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="preventivo_id" value="<?php echo esc_attr($preventivo->id); ?>">
        <?php endif; ?>
        
        <div class="form-container" style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 20px;">
            
            <!-- SEZIONE: Dati Cliente -->
            <fieldset class="form-section">
                <legend style="font-size: 1.2em; font-weight: bold; color: #d4af37; margin-bottom: 20px;">
                    👤 Dati Cliente
                </legend>
                
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="nome_referente">Nome *</label>
                        <input type="text" 
                               id="nome_referente" 
                               name="nome_referente" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'nome_referente')); ?>" 
                               required
                               class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="cognome_referente">Cognome *</label>
                        <input type="text" 
                               id="cognome_referente" 
                               name="cognome_referente" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'cognome_referente')); ?>" 
                               required
                               class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="cellulare">Cellulare</label>
                        <input type="tel" 
                               id="cellulare" 
                               name="cellulare" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'cellulare')); ?>"
                               class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'email')); ?>"
                               class="regular-text">
                    </div>
                </div>
            </fieldset>
            
            <!-- SEZIONE: Dettagli Evento -->
            <fieldset class="form-section" style="margin-top: 30px;">
                <legend style="font-size: 1.2em; font-weight: bold; color: #d4af37; margin-bottom: 20px;">
                    🎉 Dettagli Evento
                </legend>
                
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="tipo_evento">Tipo Evento</label>
                        <input type="text" 
                               id="tipo_evento" 
                               name="tipo_evento" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'tipo_evento')); ?>"
                               placeholder="es. Compleanno, Matrimonio..."
                               class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="data_evento">Data Evento *</label>
                        <input type="date" 
                               id="data_evento" 
                               name="data_evento" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'data_evento')); ?>"
                               required
                               class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="orario">Orario</label>
                        <select id="orario" name="orario" class="regular-text">
                            <option value="">Seleziona orario...</option>
                            <?php 
                            $orari = ['Pranzo', 'Cena', 'Aperitivo', 'Brunch', 'Altro'];
                            $selected_orario = get_field_value($preventivo, 'orario');
                            foreach ($orari as $orario): 
                            ?>
                                <option value="<?php echo esc_attr($orario); ?>" 
                                        <?php selected($selected_orario, $orario); ?>>
                                    <?php echo esc_html($orario); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="numero_invitati">Numero Invitati</label>
                        <input type="number" 
                               id="numero_invitati" 
                               name="numero_invitati" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'numero_invitati', 0)); ?>"
                               min="0"
                               class="small-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="tipo_menu">Tipo Menu *</label>
                        <select id="tipo_menu" name="tipo_menu" required class="regular-text">
                            <option value="">Seleziona menu...</option>
                            <?php 
                            $menus = ['Menu 7', 'Menu 74', 'Menu 747'];
                            $selected_menu = get_field_value($preventivo, 'tipo_menu');
                            foreach ($menus as $menu): 
                            ?>
                                <option value="<?php echo esc_attr($menu); ?>" 
                                        <?php selected($selected_menu, $menu); ?>>
                                    <?php echo esc_html($menu); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </fieldset>
            
            <!-- SEZIONE: Dettagli Economici -->
            <fieldset class="form-section" style="margin-top: 30px;">
                <legend style="font-size: 1.2em; font-weight: bold; color: #d4af37; margin-bottom: 20px;">
                    💰 Dettagli Economici
                </legend>
                
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="importo">Importo Totale (€)</label>
                        <input type="number" 
                               id="importo" 
                               name="importo" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'importo', 0)); ?>"
                               min="0"
                               step="0.01"
                               class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="acconto">Acconto Versato (€)</label>
                        <input type="number" 
                               id="acconto" 
                               name="acconto" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'acconto', 0)); ?>"
                               min="0"
                               step="0.01"
                               class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="saldo">Saldo (€)</label>
                        <input type="number" 
                               id="saldo" 
                               name="saldo" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'saldo', 0)); ?>"
                               min="0"
                               step="0.01"
                               readonly
                               class="regular-text"
                               style="background-color: #f0f0f0;">
                    </div>
                </div>
            </fieldset>
            
            <!-- SEZIONE: Omaggi -->
            <fieldset class="form-section" style="margin-top: 30px;">
                <legend style="font-size: 1.2em; font-weight: bold; color: #d4af37; margin-bottom: 20px;">
                    🎁 Omaggi
                </legend>
                
                <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div class="form-group">
                        <label for="omaggio1">Omaggio 1</label>
                        <input type="text" 
                               id="omaggio1" 
                               name="omaggio1" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'omaggio1')); ?>"
                               class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="omaggio2">Omaggio 2</label>
                        <input type="text" 
                               id="omaggio2" 
                               name="omaggio2" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'omaggio2')); ?>"
                               class="regular-text">
                    </div>
                    
                    <div class="form-group">
                        <label for="omaggio3">Omaggio 3</label>
                        <input type="text" 
                               id="omaggio3" 
                               name="omaggio3" 
                               value="<?php echo esc_attr(get_field_value($preventivo, 'omaggio3')); ?>"
                               class="regular-text">
                    </div>
                </div>
            </fieldset>
            
            <!-- SEZIONE: Extra a Pagamento -->
            <fieldset class="form-section" style="margin-top: 30px;">
                <legend style="font-size: 1.2em; font-weight: bold; color: #d4af37; margin-bottom: 20px;">
                    💎 Extra a Pagamento
                </legend>
                
                <div class="extra-items">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="extra-item" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                            <div class="form-group">
                                <label for="extra<?php echo $i; ?>_nome">Extra <?php echo $i; ?> - Descrizione</label>
                                <input type="text" 
                                       id="extra<?php echo $i; ?>_nome" 
                                       name="extra<?php echo $i; ?>_nome" 
                                       value="<?php echo esc_attr(get_field_value($preventivo, 'extra' . $i . '_nome')); ?>"
                                       class="regular-text">
                            </div>
                            
                            <div class="form-group">
                                <label for="extra<?php echo $i; ?>_prezzo">Prezzo (€)</label>
                                <input type="number" 
                                       id="extra<?php echo $i; ?>_prezzo" 
                                       name="extra<?php echo $i; ?>_prezzo" 
                                       value="<?php echo esc_attr(get_field_value($preventivo, 'extra' . $i . '_prezzo', 0)); ?>"
                                       min="0"
                                       step="0.01"
                                       class="regular-text">
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </fieldset>
            
            <!-- SEZIONE: Azioni -->
            <div class="form-actions" style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #dee2e6; display: flex; gap: 10px; justify-content: space-between;">
                <div>
                    <button type="submit" class="button button-primary button-large">
                        💾 Salva Preventivo
                    </button>
                    
                    <button type="button" id="generate-pdf" class="button button-secondary button-large">
                        📄 Genera PDF
                    </button>
                    
                    <button type="button" id="generate-excel" class="button button-secondary button-large">
                        📊 Genera Excel
                    </button>
                </div>
                
                <div>
                    <a href="<?php echo admin_url('admin.php?page=disco747-crm&action=dashboard_preventivi'); ?>" 
                       class="button button-secondary">
                        ← Torna alla Dashboard
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.disco747-form-preventivo .form-group {
    display: flex;
    flex-direction: column;
}

.disco747-form-preventivo label {
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.disco747-form-preventivo input,
.disco747-form-preventivo select,
.disco747-form-preventivo textarea {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    width: 100%;
}

.disco747-form-preventivo input:focus,
.disco747-form-preventivo select:focus,
.disco747-form-preventivo textarea:focus {
    outline: none;
    border-color: #d4af37;
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
}

.disco747-form-preventivo .regular-text {
    width: 100%;
}

.badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 600;
}

.badge-info {
    background: #17a2b8;
    color: white;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Auto-calcolo saldo
    function calculateSaldo() {
        const importo = parseFloat($('#importo').val()) || 0;
        const acconto = parseFloat($('#acconto').val()) || 0;
        const saldo = importo - acconto;
        $('#saldo').val(saldo.toFixed(2));
    }
    
    $('#importo, #acconto').on('input', calculateSaldo);
    
    // Calcolo iniziale
    calculateSaldo();
    
    // Form submission
    $('#form-preventivo').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        
        // Disable submit button
        $submitBtn.prop('disabled', true).text('Salvataggio in corso...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $form.serialize() + '&action=disco747_save_preventivo',
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p>✅ Preventivo salvato con successo!</p></div>')
                        .insertAfter('.wrap h1')
                        .delay(3000)
                        .fadeOut();
                    
                    // If new preventivo, redirect to edit mode
                    if (!$('input[name="preventivo_id"]').val() && response.data.preventivo_id) {
                        window.location.href = '<?php echo admin_url('admin.php?page=disco747-crm&action=edit_preventivo&id='); ?>' + response.data.preventivo_id;
                    }
                } else {
                    alert('Errore: ' + (response.data.message || 'Errore sconosciuto'));
                }
            },
            error: function() {
                alert('Errore di comunicazione con il server');
            },
            complete: function() {
                $submitBtn.prop('disabled', false).text('💾 Salva Preventivo');
            }
        });
    });
    
    // Generate PDF
    $('#generate-pdf').on('click', function() {
        const preventivoId = $('input[name="preventivo_id"]').val();
        
        if (!preventivoId) {
            alert('Salva prima il preventivo per generare il PDF');
            return;
        }
        
        // Trigger PDF generation
        window.open('<?php echo admin_url('admin-ajax.php?action=disco747_generate_pdf&preventivo_id='); ?>' + preventivoId, '_blank');
    });
    
    // Generate Excel
    $('#generate-excel').on('click', function() {
        const preventivoId = $('input[name="preventivo_id"]').val();
        
        if (!preventivoId) {
            alert('Salva prima il preventivo per generare l\'Excel');
            return;
        }
        
        // Trigger Excel generation
        window.open('<?php echo admin_url('admin-ajax.php?action=disco747_generate_excel&preventivo_id='); ?>' + preventivoId, '_blank');
    });
});
</script>