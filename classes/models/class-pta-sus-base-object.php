<?php
/**
 * Abstract Base Object Class
 * 
 * Provides base functionality for Sheet, Task, and Signup objects
 * Handles CRUD operations, property management, and database interactions
 * 
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

abstract class PTA_SUS_Base_Object {
	
	/**
	 * Object ID
	 *
	 * @var int
	 */
	protected $id = 0;
	
	/**
	 * Object data - stores all properties
	 *
	 * @var array
	 */
	protected $data = array();
	
	/**
	 * Database table name (without prefix)
	 *
	 * @var string
	 */
	protected $table_name = '';
	
	/**
	 * WordPress database object
	 *
	 * @var wpdb
	 */
	protected $wpdb;
	
	/**
	 * Whether this is a new object (not yet saved to database)
	 *
	 * @var bool
	 */
	protected $is_new = true;
	
	/**
	 * Static cache for object instances
	 * Prevents duplicate queries and ensures data consistency across extensions
	 * Format: 'ClassName:ID' => object_instance
	 *
	 * @var array
	 */
	protected static $instance_cache = array();
	
	/**
	 * Constructor
	 *
	 * @param int|object|array $data Object ID, stdClass object, or array of properties
	 */
	public function __construct( $data = null ) {
		global $wpdb;
		$this->wpdb = $wpdb;
		
		// Initialize properties with defaults
		$this->init_properties();
		
		// Load data if provided
		if ( ! empty( $data ) ) {
			if ( is_numeric( $data ) ) {
				// Check cache first
				$class = get_class( $this );
				$cache_key = $class . ':' . absint( $data );
				
				if ( isset( self::$instance_cache[$cache_key] ) ) {
					// Populate from cached instance
					$cached = self::$instance_cache[$cache_key];
					$this->data = $cached->data;
					$this->id = $cached->id;
					$this->is_new = $cached->is_new;
					return;
				}
				
				// Load from database by ID
				$this->load( $data );
				
				// Cache it if successfully loaded
				if ( ! $this->is_new && ! empty( $this->id ) ) {
					self::$instance_cache[$cache_key] = $this;
				}
			} elseif ( is_object( $data ) || is_array( $data ) ) {
				// Populate from existing data
				$this->populate( $data );
				
				// Cache it if it has an ID
				if ( ! $this->is_new && ! empty( $this->id ) ) {
					$class = get_class( $this );
					$cache_key = $class . ':' . $this->id;
					self::$instance_cache[$cache_key] = $this;
				}
			}
		}
	}
	
	/**
	 * Initialize properties with default values
	 * Sets up the data array with defaults based on property definitions
	 */
    protected function init_properties() {
        $properties = $this->get_properties();
        $defaults = $this->get_property_defaults();

        // Apply filter to allow extensions to add defaults
        $object_type = $this->get_object_type();
        $defaults = apply_filters( "pta_sus_{$object_type}_property_defaults", $defaults, $this );
        
        // Also check old filter format for backward compatibility
        // Extensions using old filters may not set defaults, so we use null as fallback
        // But we should check if there are any defaults defined in the old structure
        global $pta_sus;
        if ( isset( $pta_sus ) && isset( $pta_sus->data ) && isset( $pta_sus->data->tables[$object_type] ) ) {
            // Old format doesn't typically include defaults, but we've already handled that above
            // The new filter takes precedence, but old extensions can still add properties
        }

        foreach ( $properties as $property => $type ) {
            // Only set default if not already set (allows new filter to override)
            if ( ! isset( $this->data[$property] ) ) {
                $this->data[$property] = isset( $defaults[$property] ) ? $defaults[$property] : null;
            }
        }

        // ID is always initialized to 0 for new objects
        $this->data['id'] = 0;
    }
	
	/**
	 * Get property definitions
	 * Returns an associative array of property_name => type
	 * Must be implemented by child classes
	 *
	 * @return array
	 */
	abstract protected function get_property_definitions();
	
	/**
	 * Get property defaults
	 * Returns an associative array of property_name => default_value
	 * Must be implemented by child classes
	 *
	 * @return array
	 */
	abstract protected function get_property_defaults();
	
	/**
	 * Get the database table name (without prefix)
	 * Must be implemented by child classes
	 *
	 * @return string
	 */
	abstract protected function get_table_name();
	
	/**
	 * Get required fields
	 * Returns an array of required field names
	 * Can be overridden by child classes
	 *
	 * @return array
	 */
	protected function get_required_fields() {
		return array();
	}
	
	/**
	 * Get properties (with filter applied)
	 * Allows extensions to add custom properties
	 * Supports both new filter format (pta_sus_{type}_properties) and old format (pta_sus_{type}_fields)
	 * for backward compatibility with extensions like Waitlist that use the old filter structure
	 *
	 * @return array
	 */
	public function get_properties() {
		$properties = $this->get_property_definitions();
		$object_type = $this->get_object_type();
		
		// Apply new filter format (preferred)
		/**
		 * Filter the property definitions for this object type
		 *
		 * @param array $properties Array of property_name => type
		 * @param PTA_SUS_Base_Object $this The object instance
		 */
		$properties = apply_filters( "pta_sus_{$object_type}_properties", $properties, $this );
		
		// Also check old filter format for backward compatibility
		// Old format: pta_sus_{type}_fields returns array with 'allowed_fields' key
		// Extensions like Waitlist use this to add fields like 'waitlist_id'
		// We need to check the old filter structure to merge in any extension-added fields
		global $pta_sus;
		if ( isset( $pta_sus ) && isset( $pta_sus->data ) && isset( $pta_sus->data->tables[$object_type] ) ) {
			// Get the filtered table structure (which extensions may have modified via old filters)
			$table_structure = $pta_sus->data->tables[$object_type];
			if ( isset( $table_structure['allowed_fields'] ) && is_array( $table_structure['allowed_fields'] ) ) {
				// Merge in any additional fields from the old filter format
				// Use array_merge so new filter takes precedence, but old filter adds missing fields
				// This ensures extensions using old filters (like Waitlist) still work
				$properties = array_merge( $properties, $table_structure['allowed_fields'] );
			}
		}
		
		return $properties;
	}
	
	/**
	 * Get the object type (sheet, task, signup)
	 * Used for filters and hooks
	 *
	 * @return string
	 */
	protected function get_object_type() {
		// Default implementation - get from class name
		$class_name = get_class( $this );
		$parts = explode( '_', $class_name );
		return strtolower( end( $parts ) );
	}
	
	/**
	 * Get property type for a specific property
	 *
	 * @param string $property Property name
	 * @return string|false Property type or false if not found
	 */
	public function get_property_type( $property ) {
		$properties = $this->get_properties();
		return isset( $properties[$property] ) ? $properties[$property] : false;
	}
	
	/**
	 * Magic getter for properties
	 * Provides backward compatibility with stdClass object access
	 *
	 * @param string $property Property name
	 * @return mixed Property value or null if not found
	 */
	public function __get( $property ) {
		if ( array_key_exists( $property, $this->data ) ) {
			return $this->data[$property];
		}
		return null;
	}
	
	/**
	 * Magic setter for properties
	 * Provides backward compatibility with stdClass object access
	 *
	 * @param string $property Property name
	 * @param mixed $value Property value
	 */
	public function __set( $property, $value ) {
		$this->data[$property] = $value;
	}
	
	/**
	 * Magic isset for properties
	 *
	 * @param string $property Property name
	 * @return bool
	 */
	public function __isset( $property ) {
		return isset( $this->data[$property] );
	}
	
	/**
	 * Magic unset for properties
	 *
	 * @param string $property Property name
	 */
	public function __unset( $property ) {
		if ( isset( $this->data[$property] ) ) {
			unset( $this->data[$property] );
		}
	}
	
	/**
	 * Load object from database by ID
	 *
	 * @param int $id Object ID
	 * @return bool True if loaded successfully, false otherwise
	 */
	public function load( $id ) {
		$id = absint( $id );
		if ( empty( $id ) ) {
			return false;
		}
		
		$table_name = $this->wpdb->prefix . $this->get_table_name();
		$row = $this->wpdb->get_row( 
			$this->wpdb->prepare( 
				"SELECT * FROM {$table_name} WHERE id = %d", 
				$id 
			),
			ARRAY_A
		);
		
		if ( empty( $row ) ) {
			return false;
		}
		
		// Populate object with data
		$this->populate( $row );
		$this->is_new = false;
		
		return true;
	}
	
	/**
	 * Populate object with data
	 *
	 * @param object|array $data Object data
	 */
    protected function populate( $data ) {
        // Convert object to array if needed
        if ( is_object( $data ) ) {
            $data = get_object_vars( $data );
        }

        // Apply stripslashes
        $data = stripslashes_deep( $data );

        // Set properties
        $properties = $this->get_properties(); // This includes filtered properties
        foreach ( $data as $key => $value ) {
            // Only set if it's a defined property or if it's 'id'
            // Extension properties will be included via the filter
            if ( isset( $properties[$key] ) || $key === 'id' ) {
                // Unserialize array types from database
                if ( isset( $properties[$key] ) && $properties[$key] === 'array' ) {
                    $value = maybe_unserialize( $value );
                }
                // Convert boolean values from database (1/0) to true/false
                if ( isset( $properties[$key] ) && $properties[$key] === 'bool' ) {
                    $value = (bool) $value;
                }
                $this->data[$key] = $value;
            }
        }

        // Set ID separately
        if ( isset( $data['id'] ) ) {
            $this->id = absint( $data['id'] );
            $this->data['id'] = $this->id;
            $this->is_new = false;
        }
    }
	
	/**
	 * Save object to database
	 * Performs INSERT or UPDATE depending on whether object is new
	 *
	 * @return int|false Object ID on success, false on failure
	 */
	public function save() {
		$object_type = $this->get_object_type();
		
		/**
		 * Action before saving object
		 *
		 * @param PTA_SUS_Base_Object $this The object instance
		 */
		do_action( "pta_sus_before_save_{$object_type}", $this );
		
		// Validate required fields
		if ( ! $this->validate() ) {
			return false;
		}
		
		// Prepare data for database
		$db_data = $this->prepare_for_save();
		
		// Get table name
		$table_name = $this->wpdb->prefix . $this->get_table_name();
		
		// Get format array for wpdb
		$formats = $this->get_wpdb_formats( $db_data );
		
		if ( $this->is_new || empty( $this->id ) ) {
			// INSERT
			$result = $this->wpdb->insert( $table_name, $db_data, $formats );
			if ( $result ) {
				$this->id = $this->wpdb->insert_id;
				$this->data['id'] = $this->id;
				$this->is_new = false;
				
				// Add to cache
				$cache_key = get_class( $this ) . ':' . $this->id;
				self::$instance_cache[$cache_key] = $this;
				
				/**
				 * Action after creating new object
				 *
				 * @param int $id Object ID
				 * @param PTA_SUS_Base_Object $this The object instance
				 */
				do_action( "pta_sus_created_{$object_type}", $this->id, $this );
				
				return $this->id;
			}
		} else {
			// UPDATE
			$result = $this->wpdb->update( 
				$table_name, 
				$db_data, 
				array( 'id' => $this->id ),
				$formats,
				array( '%d' )
			);
			if ( false !== $result ) {
				// Update cache with current instance
				$cache_key = get_class( $this ) . ':' . $this->id;
				self::$instance_cache[$cache_key] = $this;
				
				/**
				 * Action after updating object
				 *
				 * @param int $id Object ID
				 * @param PTA_SUS_Base_Object $this The object instance
				 */
				do_action( "pta_sus_updated_{$object_type}", $this->id, $this );
				
				return $this->id;
			}
		}
		
		return false;
	}
	
	/**
	 * Prepare data for saving to database
	 * Sanitizes values and removes ID field
	 *
	 * @return array
	 */
	protected function prepare_for_save() {
		$db_data = array();
		$properties = $this->get_properties();
		
		foreach ( $properties as $property => $type ) {
			if ( isset( $this->data[$property] ) ) {
				// Sanitize value based on type
				$db_data[$property] = $this->sanitize_value( $this->data[$property], $type );
			}
		}
		
		// Remove ID if it's 0 (for new objects)
		if ( isset( $db_data['id'] ) && empty( $db_data['id'] ) ) {
			unset( $db_data['id'] );
		}
		
		return $db_data;
	}
	
	/**
	 * Sanitize a value based on its type
	 * Uses the global pta_sanitize_value function if available
	 *
	 * @param mixed $value Value to sanitize
	 * @param string $type Value type
	 * @return mixed Sanitized value
	 */
	protected function sanitize_value( $value, $type ) {
		// Use global function if available (should always be available in normal operation)
		if ( function_exists( 'pta_sanitize_value' ) ) {
			return pta_sanitize_value( $value, $type );
		}
		
		// Minimal fallback for edge cases (e.g., if global function not loaded)
		// This should rarely be used in practice, but provides a safety net
		// Note: This fallback is incomplete and doesn't handle all types (names, emails, dates, etc.)
		// The global function should always be available when the plugin is active
		if ( WP_DEBUG ) {
			error_log( 'PTA SUS: pta_sanitize_value() function not available - using fallback sanitization for type: ' . $type );
		}
		
		switch ( $type ) {
			case 'int':
				return absint( $value );
			case 'bool':
				return $value ? 1 : 0;
			case 'yesno':
				$value = strtoupper( sanitize_text_field( $value ) );
				return in_array( $value, array( 'YES', 'NO' ) ) ? $value : 'NO';
			case 'array':
				if ( is_array( $value ) ) {
					return maybe_serialize( $value );
				}
				return $value;
			case 'email':
				return sanitize_email( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'date':
			case 'time':
			case 'text':
			case 'names':
			case 'emails':
			case 'dates':
			default:
				// For unknown types or types not handled in fallback, use basic text sanitization
				// This is safer than leaving data unsanitized
				return sanitize_text_field( $value );
		}
	}
	
	/**
	 * Get wpdb format array for data
	 * Returns array of %d, %s, etc. for wpdb insert/update
	 *
	 * @param array $data Data to get formats for
	 * @return array
	 */
	protected function get_wpdb_formats( $data ) {
		$formats = array();
		$properties = $this->get_properties();
		
		foreach ( $data as $key => $value ) {
			$type = isset( $properties[$key] ) ? $properties[$key] : 'text';
			$formats[] = $this->get_wpdb_format( $type );
		}
		
		return $formats;
	}
	
	/**
	 * Get wpdb format string for a property type
	 *
	 * @param string $type Property type
	 * @return string wpdb format (%d, %s, %f)
	 */
	protected function get_wpdb_format( $type ) {
		// Integer types
		if ( in_array( $type, array( 'int', 'bool' ) ) ) {
			return '%d';
		}
		
		// Float types
		if ( in_array( $type, array( 'float', 'decimal' ) ) ) {
			return '%f';
		}
		
		// Everything else is a string
		return '%s';
	}
	
	/**
	 * Validate object data
	 * Checks that required fields are present and not empty
	 *
	 * @return bool True if valid, false otherwise
	 */
	protected function validate() {
		$required = $this->get_required_fields();
		
		foreach ( $required as $field => $label ) {
			if ( empty( $this->data[$field] ) ) {
				// Required field is missing or empty
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * Delete object from database
	 *
	 * @return bool True on success, false on failure
	 */
	public function delete() {
		if ( empty( $this->id ) ) {
			return false;
		}
		
		$object_type = $this->get_object_type();
		
		/**
		 * Action before deleting object
		 *
		 * @param int $id Object ID
		 * @param PTA_SUS_Base_Object $this The object instance
		 */
		do_action( "pta_sus_before_delete_{$object_type}", $this->id, $this );
		
		$table_name = $this->wpdb->prefix . $this->get_table_name();
		$result = $this->wpdb->delete( 
			$table_name, 
			array( 'id' => $this->id ),
			array( '%d' )
		);
		
		if ( $result ) {
			// Remove from cache
			$cache_key = get_class( $this ) . ':' . $this->id;
			unset( self::$instance_cache[$cache_key] );
			
			/**
			 * Action after deleting object
			 *
			 * @param int $id Object ID
			 */
			do_action( "pta_sus_deleted_{$object_type}", $this->id );
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * Get object ID
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}
	
	/**
	 * Check if object exists in database
	 *
	 * @return bool
	 */
	public function exists() {
		return ! $this->is_new && ! empty( $this->id );
	}
	
	/**
	 * Convert object to stdClass
	 * For backward compatibility with existing code
	 *
	 * @return stdClass
	 */
	public function to_stdclass() {
		return (object) $this->data;
	}
	
	/**
	 * Convert object to array
	 *
	 * @return array
	 */
	public function to_array() {
		return $this->data;
	}
	
	/**
	 * Static method to get object by ID with caching
	 * Returns cached instance if available, otherwise loads from database
	 *
	 * @param int $id Object ID
	 * @return static|false Object instance or false if not found
	 */
	public static function get_by_id( $id ) {
		$id = absint( $id );
		if ( empty( $id ) ) {
			return false;
		}
		
		$class = get_called_class(); // Gets the actual child class name
		$cache_key = $class . ':' . $id;
		
		// Return cached instance if exists
		if ( isset( self::$instance_cache[$cache_key] ) ) {
			return self::$instance_cache[$cache_key];
		}
		
		// Load from database
		$object = new static();
		if ( $object->load( $id ) ) {
			// Store in cache
			self::$instance_cache[$cache_key] = $object;
			return $object;
		}
		
		return false;
	}
	
	/**
	 * Clear object cache
	 * Useful for testing or when external database changes occur
	 *
	 * @param int|null $id Specific ID to clear, or null to clear all instances of this class
	 */
	public static function clear_cache( $id = null ) {
		$class = get_called_class();
		
		if ( $id !== null ) {
			// Clear specific instance
			$cache_key = $class . ':' . absint( $id );
			unset( self::$instance_cache[$cache_key] );
		} else {
			// Clear all instances of this class
			foreach ( self::$instance_cache as $key => $value ) {
				if ( strpos( $key, $class . ':' ) === 0 ) {
					unset( self::$instance_cache[$key] );
				}
			}
		}
	}
}

