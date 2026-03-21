<?php
/**
 * Object Cache Registry
 *
 * Simple static per-type cache for Sheet, Task, Signup, and EmailTemplate objects.
 * Keeps caching entirely separate from the model objects so objects remain plain
 * data containers without internal cache references.
 *
 * Usage:
 *   PTA_SUS_Object_Cache::get( 'PTA_SUS_Task', 5 )
 *   PTA_SUS_Object_Cache::set( 'PTA_SUS_Task', 5, $task_object )
 *   PTA_SUS_Object_Cache::invalidate( 'PTA_SUS_Task', 5 )   // single entry
 *   PTA_SUS_Object_Cache::invalidate( 'PTA_SUS_Task' )      // all of that type
 *   PTA_SUS_Object_Cache::clear_all()                       // everything
 *
 * @package PTA_Volunteer_Sign_Up_Sheets
 * @since 6.4.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PTA_SUS_Object_Cache {

	/**
	 * Cache storage: [ 'ClassName' => [ id => object, ... ], ... ]
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Retrieve a cached object.
	 *
	 * @param string $class Fully-qualified class name (e.g. 'PTA_SUS_Task').
	 * @param int    $id    Object ID.
	 * @return object|null  The cached object, or null if not cached.
	 */
	public static function get( $class, $id ) {
		return isset( self::$cache[ $class ][ $id ] ) ? self::$cache[ $class ][ $id ] : null;
	}

	/**
	 * Store an object in the cache.
	 *
	 * @param string $class  Fully-qualified class name.
	 * @param int    $id     Object ID.
	 * @param object $object The object to cache.
	 */
	public static function set( $class, $id, $object ) {
		if ( ! isset( self::$cache[ $class ] ) ) {
			self::$cache[ $class ] = array();
		}
		self::$cache[ $class ][ $id ] = $object;
	}

	/**
	 * Remove one or all entries for a class from the cache.
	 *
	 * @param string   $class Fully-qualified class name.
	 * @param int|null $id    Specific ID to remove, or null to remove all for this class.
	 */
	public static function invalidate( $class, $id = null ) {
		if ( $id === null ) {
			unset( self::$cache[ $class ] );
		} else {
			unset( self::$cache[ $class ][ $id ] );
		}
	}

	/**
	 * Wipe the entire cache (all types).
	 * Useful in unit tests or after bulk DB operations.
	 */
	public static function clear_all() {
		self::$cache = array();
	}
}
