<?php

class Disco747FunnelScheduler {
    
    // Check if a new preventivo can be handled based on existing tracking
    public function handle_new_preventivo($preventivo_id) {
        if ($this->is_tracking_active($preventivo_id)) {
            error_log('Preventivo already has active tracking. Cannot start new funnel.');
            return;
        }
        if ($this->get_preventivo_stato($preventivo_id) != 'attivo') {
            error_log('Preventivo is not in active state. Funnel will not be started.');
            return;
        }
        $tracking_id = $this->start_funnel($preventivo_id);
        return $tracking_id;
    }

    // Verify if tracking is active for the given preventivo
    private function is_tracking_active($preventivo_id) {
        // Implementation to check active/pending tracking...
    }

    // Get the current state of the preventivo
    private function get_preventivo_stato($preventivo_id) {
        // Implementation to retrieve preventivo state...
    }

    // Start funnel process
    private function start_funnel($preventivo_id) {
        // Implementation to start the funnel and return tracking ID...
        return $tracking_id;
    }

    // Improved logging and state checking can be added here...
}

?>