<?php
/**
 * RPG Maps Plugin - MyAlerts Formatter
 * Formats the "build request approved" alert notification
 *
 * @package rpgmaps
 */

// Prevent direct access
if (!defined('IN_MYBB')) {
    exit;
}

class RPGMaps_Formatter_BuildApproved extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
{
    /**
     * Load language file before formatting alerts.
     */
    public function init()
    {
        if (empty($this->lang->rpgmaps_alert_build_approved)) {
            $this->lang->load('rpgmaps');
        }
    }

    /**
     * Format the alert into a display string.
     *
     * @param MybbStuff_MyAlerts_Entity_Alert $alert
     * @param array $outputAlert
     * @return string
     */
    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
    {
        $details = $alert->getExtraDetails();

        $plot_key  = isset($details['plot_key'])  ? htmlspecialchars_uni($details['plot_key'])  : '';
        $map_title = isset($details['map_title']) ? htmlspecialchars_uni($details['map_title']) : '';

        return $this->lang->sprintf(
            $this->lang->rpgmaps_alert_build_approved,
            $plot_key,
            $map_title
        );
    }

    /**
     * Build the link that the alert redirects to when clicked.
     *
     * @param MybbStuff_MyAlerts_Entity_Alert $alert
     * @return string
     */
    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
    {
        $details = $alert->getExtraDetails();
        $map_id  = isset($details['map_id']) ? (int)$details['map_id'] : 0;

        return $this->mybb->settings['bburl'] . '/rpgmaps.php?map_id=' . $map_id;
    }
}
