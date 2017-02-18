<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Helpers {

    //sync type
    const SYNC_TYPE_MANUAL = 0;
    const SYNC_TYPE_AUTOMATIC = 1;

    protected $syncTypes;

    //sync direction
    const SYNC_DIRECTION_WOO_TO_SQUARE = 0;
    const SYNC_DIRECTION_SQUARE_TO_WOO = 1;

    protected $syncDirections;

    //target type
    const TARGET_TYPE_PRODUCT = 0;
    const TARGET_TYPE_CATEGORY = 1;

    protected $targetTypes;

    //target status
    const TARGET_STATUS_FAILURE = 0;
    const TARGET_STATUS_SUCCESS = 1;

    protected $targetStatuses;

    //actions
    const ACTION_SYNC_START = 0;
    const ACTION_ADD = 1;
    const ACTION_UPDATE = 2;
    const ACTION_DELETE = 3;
    

    protected $actions;

    /**
     * Set class variables
     */
    function __construct() {

        $this->syncTypes = [
            self::SYNC_TYPE_MANUAL => __('Manual'),
            self::SYNC_TYPE_AUTOMATIC => __('Automatic'),
        ];

        $this->syncDirections = [
            self::SYNC_DIRECTION_WOO_TO_SQUARE => __('Woo to Square'),
            self::SYNC_DIRECTION_SQUARE_TO_WOO => __('Square to Woo')
        ];
        $this->targetTypes = [
            self::TARGET_TYPE_PRODUCT => __('Product'),
            self::TARGET_TYPE_CATEGORY => __('Category')
        ];
        $this->targetStatuses = [
            self::TARGET_STATUS_FAILURE => __('Failure'),
            self::TARGET_STATUS_SUCCESS => __('Success')
        ];

        $this->actions = [
            self::ACTION_SYNC_START => __('Sync start'),
            self::ACTION_ADD => __('add'),
            self::ACTION_UPDATE => __('update'),
            self::ACTION_DELETE => __('delete'),

        ];
    }

    public function getSyncTypes() {
        return $this->syncTypes;
    }

    public function getSyncDirections() {
        return $this->syncDirections;
    }

    public function getTargetTypes() {
        return $this->targetTypes;
    }

    public function getTargetStatuses() {
        return $this->targetStatuses;
    }

    public function getActions() {
        return $this->actions;
    }

    /**
     * Get synchronization type message for specific key
     * @param string $key
     * @return string|NULL message corresponding to the given key, NULL if not found
     */
    public function getSyncType($key) {
        return isset($this->syncTypes[$key]) ? $this->syncTypes[$key] : NULL;
    }

    /**
     * Get synchronization direction message for specific key
     * @param string $key
     * @return string|NULL message corresponding to the given key, NULL if not found
     */
    public function getSyncDirection($key) {
        return isset($this->syncDirections[$key]) ? $this->syncDirections[$key] : NULL;
    }

    /**
     * Get synchronization synchronization target type message for specific key
     * @param string $key
     * @return string|NULL message corresponding to the given key, NULL if not found
     */
    public function getTargetType($key) {
        return isset($this->targetTypes[$key]) ? $this->targetTypes[$key] : NULL;
    }

    /**
     * Get synchronization target status message for specific key
     * @param string $key
     * @return string|NULL message corresponding to the given key, NULL if not found
     */
    public function getTargetStatus($key) {
        return isset($this->targetStatuses[$key]) ? $this->targetStatuses[$key] : NULL;
    }

    /**
     * Get synchronization action message for specific key
     * @param string $key
     * @return string|NULL message corresponding to the given key, NULL if not found
     */
    public function getAction($key) {
        return isset($this->actions[$key]) ? $this->actions[$key] : NULL;
    }

    /*
     *  Helper function to search in multi dimensional arrays
     */

    public static function searchInMultiDimensionArray($array, $attribute, $serchvalue) {
        $objectNeeded = false;
        for ($i = 0; $i < count($array); $i++) {
            $object = $array[$i];
            if ($object[$attribute] == $serchvalue) {
                $objectNeeded = $object;
                break;
            }
        }
        return $objectNeeded;
    }

    /**
     * @param type $data
     */
    static function debug_log($type, $data) {
        error_log("[$type] [" . date("Y-m-d H:i:s") . "] " . print_r($data, true) . "\n", 3, dirname(__FILE__) . '/../logs.log');
    }

 /**
  * 
  * @global object $wpdb
  * @param integer $action
  * @param string $date
  * @param integer $sync_type
  * @param integer $sync_direction
  * @param integer $target_id
  * @param integer $target_type
  * @param integer $target_status
  * @param integer $parent_id log parent id, 0 if none
  * @param string $name name of the target synchronized object
  * @param string $square_id
  * @param string $message
  * @return integer inserted row id
  */
    static function sync_db_log($action, $date, $sync_type, $sync_direction, $target_id = NULL, $target_type = NULL, $target_status = NULL, $parent_id = 0, $name = NULL, $square_id = NULL, $message = NULL) {


        global $wpdb;

        $wpdb->insert($wpdb->prefix . WOO_SQUARE_TABLE_SYNC_LOGS, array(
            'action' => $action,
            'date' => $date,
            'sync_type' => $sync_type,
            'sync_direction' => $sync_direction,
            'name' => $name,
            'target_id' => $target_id,
            'target_type' => $target_type,
            'target_status' => $target_status,
            'parent_id' => $parent_id,
            'square_id' => $square_id,
            'message' => $message
        ));
        return $wpdb->insert_id;
    }

}
