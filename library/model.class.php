<?php 

defined('IN_APP') or die();

// TODO document hooks in TEMPLATE plugin
class Model {
    
    /**
     * The database table name this model is connected to
     */
    protected $_table = null;
    
    /**
     * The unique primary key of the table if it exists
     * 
     * If no primary key is needed for the table, then leave as null
     */
    protected $_key = null;
    
    /**
     * The table column names
     */
    protected $_properties = array();
    
    /**
     * Associative array of column names with the values for the current row
     */
    protected $_values = array();
    
    /**
     * Errors caught or added during method calls
     */
    protected $_errors = array();
    
    /**
     * Stores the properties this model is intialized with.
     * Used for clearing a model back to it's initialization state
     */
    protected static $_rawProperties = array();
    
    public function __construct() {
        // Store raw properties
        if(!isset(Model::$_rawProperties[$this->getTable()])) {
            Model::$_rawProperties[$this->getTable()] = $this->_properties;
        }
        // Set defaults properties
        $this->_setDefaultProperties();
        /**
         * Run initialization method
         * 
         * Your model can use this method to set data automatically
         * on every instance of your model. This can save on repetitive tasks.
         */
        $this->_runMethod('initialize');
    }
    
    /**
     * Returns a new instance of the specified model class
     * 
     * @param   String $model The name of the model to instantiate
     * @param   String $id The id of the specific object to load
     * @returns Object The model object
     */
    public static function getInstance($model, $params=null) {
        // Load model class file
        require_once(DIR_MODELS.DS.'model-'.$model.'.php');
        // Get class name
        $class = 'Model_' . $model;
        // Instantiate class
        $model = new $class;
        // If we have an id, try to load it
        if($params) {
            $model->load($params);
        }
        // Return object
        return $model;
    }
    
    /**
     * Returns the current models current rows column value 
     */
    public function __get($column) {
        if(isset($this->_values[$column])) return $this->_values[$column];
    }
    
    /**
     * Sets the current rows columns value to $val
     */
    public function __set($column, $val) {
        if(in_array($column, $this->_properties) || array_key_exists($column, $this->_properties)) $this->_values[$column] = $val;
    }
    
    public function __unset($key) {
        unset($this->_values[$key]);
    }
    
    public function load($params=null) {
        if(is_array($params) && $params !== null) {
            foreach($params as $k => $v) {
                $this->$k = $v;
            }
        } elseif($params !== null) {
            $key = $this->getKey();
            $this->$key = $params;
        }
        Plugins::action('onModelLoad', $this);
        
        /**
         * Run the afterLoad method
         * 
         * The afterLoad method is also called after onModelRefresh is run
         */
        $this->_runMethod('afterLoad');
    }
    
    function save() {
        // Does this object have a primary key set
        if(!$this->hasKey()) {
            /**
             * Run beforeCreate method
             * 
             * If your model has a beforeCreate method, it will be run here.
             * This only occurs for new objects that do not have a primary key set
             */
            $this->_runMethod('beforeCreate');
        }
        
        /**
         * Run the validate method
         * 
         * If your model has a validate method, it will be run and if it
         * returns anything other than true. Your save will be cancelled.
         */
        $this->_runMethod('validate');
        
        // Return if errors are present
        if(count($this->_errors)) return;
        
        // Run save
        Plugins::action('onModelSave', $this, $this->id);
        
        /**
         * Run the afterSave method
         * 
         * If your model has a afterSave method, This is run on creation
         * and on updates
         */
        $this->_runMethod('afterSave');
        
        // Return true if no errors and false otherwise
        return count($this->_errors) ? false : true;
    }
    
    function refresh() {
        
        // Make sure we have an id
        if(!$this->hasKey()) return;
        
        // Run refresh
        Plugins::action('onModelRefresh', $this);
        
        /**
         * Run the afterLoad method
         * 
         * The after load method is also called after onModelLoad is run
         */
        $this->_runMethod('afterLoad');
        
        // Return true if no errors and false otherwise
        return count($this->_errors) ? false : true;
    }
    
    function delete() {
        // Run delete
        Plugins::action('onModelDelete', $this);
    }
    
    function fill($data) {
        $data = (array)$data;
        foreach($data as $k => $v) {
            $this->$k = $v;
        }
    }
    
    function clear() {

        // Fill current model with empty raw properties
        $this->_setDefaultProperties($this->_getRawProperties());
        
        /**
         * Run initialization method
         * 
         * Your model can use this method to set data automatically
         * on every instance of your model. This can save on repetitive tasks.
         */
        $this->_runMethod('initialize');
    }
    
    function beforeSave() {}
    function afterSave() {}
    
    /**
     * The query method queries the table and returns the results as an array of model objects
     */
    static function query($model, $params=null) {
        $model = Model::getInstance($model);
        $results = Plugins::filter('onModelQuery', $model, $params);
        if(!$results) {
            return array();
        } else {
            $models = array();
            foreach($results as $result) {
                $model = Model::getInstance($model->getName());
                $model->clear();
                $model->fill($result);
                $models[] = $model;
            }
            return $models;
        }
    }
    
    /**
     * Checks if this model has a key defined and it is set
     */
    public function hasKey() {
        $key = $this->getKey();
        return !(!$this->$key);
    }
    
    public function hasValue($prop) {
        return isset($this->_values[$prop]);
    }
    
    /**
     * Returns the array of property names for this model
     * 
     * @returns Array Property names
     */
    public function getProperties() {
        return $this->_properties;
    }
    
    /**
     * Returns the current array of values for each property
     * 
     * @returns Array This model current data
     */
    public function getData() {
        return $this->_values;
    }
    
    /**
     * Gets the model's name
     */
    public function getName() {
        return str_replace('Model_', '', get_called_class());
    }
    
    /**
     * Returns the table name this model is associated with
     */
    public function getTable() {
        return $this->_table;
    }
    
    /**
     * Returns the primary key of the table
     */
    public function getKey() {
        return $this->_key;
    }
    
    /**
     * Returns the array of errors or false if no errors caught
     */
    public function getErrors() {
        if(count($this->_errors)) {
            return $this->_errors;
        } else {
            return false;
        }
    }
    
    /**
     * Adds an error message to the errors array
     */
    public function addError($message) {
        $this->_errors[] = $message;
    }
    
    /**
     * Sets default properties on the current model
     */
    protected function _setDefaultProperties($properties=null) {
        $properties = $properties ? $properties : $this->getProperties();
        foreach($properties as $k => $prop) {
            if(is_string($k)) {
                // Set the default
                $this->$k = $prop;
                // Reindex the defualt prop
                $this->_properties[$k] = $k;
            } else {
                unset($this->$prop);
            }
        }
        $this->_properties = array_values($this->_properties);
    }
    
    /**
     * Run a method if it exists on this instance
     */
    private function _runMethod($method) {
        if(method_exists($this, $method)) {
            call_user_func(array($this, $method));
        }
    }
    
    /**
     * Get's the defined properties that the current model was initialized with
     */
    protected function _getRawProperties() {
        return Model::$_rawProperties[$this->getTable()];
    }
}

?>