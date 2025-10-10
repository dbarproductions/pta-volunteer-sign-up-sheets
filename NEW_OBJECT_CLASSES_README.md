# New Object-Oriented Class Structure

## Overview

This implementation creates a modern, object-oriented structure for the three main objects in the plugin: **Sheets**, **Tasks**, and **Signups**. The new classes provide backward compatibility while allowing for future improvements and easier extensibility.

## What Was Created

### 1. Abstract Base Class: `PTA_SUS_Base_Object`
**Location:** `classes/models/class-pta-sus-base-object.php`

This is the foundation that all three object classes extend. It provides:

#### Core Features:
- **Property Management**: Stores all properties in a protected `$data` array
- **Magic Methods**: `__get()`, `__set()`, `__isset()`, `__unset()` for backward compatibility
- **CRUD Operations**: Create, Read, Update, Delete functionality
- **Database Interactions**: Uses WordPress `$wpdb` for all database operations
- **Extensibility**: Filter hooks throughout for extensions to modify behavior
- **Validation**: Checks required fields before saving
- **Sanitization**: Cleans data based on property types

#### Key Methods:
- `load($id)` - Load object from database by ID
- `save()` - Insert or update object in database
- `delete()` - Delete object from database
- `get_by_id($id)` - Static method to get an object instance
- `to_stdclass()` - Convert to stdClass for backward compatibility
- `to_array()` - Convert to array

#### Abstract Methods (must be implemented by child classes):
- `get_property_definitions()` - Define properties and their types
- `get_property_defaults()` - Define default values
- `get_table_name()` - Return database table name
- `get_required_fields()` - Return required field definitions

### 2. Sheet Class: `PTA_SUS_Sheet`
**Location:** `classes/models/class-pta-sus-sheet.php`

Represents a volunteer sign-up sheet/event.

#### Additional Methods:
- `get_tasks()` - Get all tasks for this sheet
- `get_signup_count()` - Get total signups for this sheet
- `is_active()` - Check if sheet is not expired
- `is_trashed()` - Check if in trash
- `is_visible()` - Check if visible to public
- `trash()` - Move to trash
- `restore()` - Restore from trash

### 3. Task Class: `PTA_SUS_Task`
**Location:** `classes/models/class-pta-sus-task.php`

Represents a task/item within a sheet.

#### Additional Methods:
- `get_sheet()` - Get parent sheet
- `get_signups($date)` - Get signups for this task
- `get_dates_array()` - Get dates as array
- `get_available_spots($date)` - Calculate available spots
- `has_available_spots($date)` - Check if spots available

### 4. Signup Class: `PTA_SUS_Signup`
**Location:** `classes/models/class-pta-sus-signup.php`

Represents a volunteer signup.

#### Additional Methods:
- `get_task()` - Get parent task
- `get_sheet()` - Get related sheet (through task)
- `get_full_name()` - Get volunteer's full name
- `is_validated()` - Check validation status
- `reminder1_sent()` / `reminder2_sent()` - Check reminder status
- `mark_reminder1_sent()` / `mark_reminder2_sent()` - Mark reminders as sent

## Backward Compatibility

### Magic Methods Ensure Compatibility
The magic `__get()` and `__set()` methods mean that existing code using stdClass object notation will continue to work:

```php
// OLD WAY (still works!)
$sheet->title = 'My Event';
echo $sheet->title; // Works perfectly

// NEW WAY (also works!)
$sheet = new PTA_SUS_Sheet($sheet_id);
$sheet->title = 'My Event';
echo $sheet->title;
```

### Filter Hooks for Extensibility
Properties can be extended by other plugins:

```php
// In an extension:
add_filter('pta_sus_sheet_properties', function($properties) {
    $properties['waitlist_id'] = 'int';
    return $properties;
});
```

### Action Hooks Throughout
The base class fires hooks at key points:
- `pta_sus_before_save_{object_type}`
- `pta_sus_created_{object_type}`
- `pta_sus_updated_{object_type}`
- `pta_sus_before_delete_{object_type}`
- `pta_sus_deleted_{object_type}`

## How to Use the New Classes

### Creating a New Object
```php
// Create a new sheet
$sheet = new PTA_SUS_Sheet();
$sheet->title = 'Bake Sale';
$sheet->type = 'Single';
$sheet->visible = true;
$id = $sheet->save(); // Returns the new ID
```

### Loading an Existing Object
```php
// Method 1: Using constructor
$sheet = new PTA_SUS_Sheet(5); // Loads sheet with ID 5

// Method 2: Using static method
$sheet = PTA_SUS_Sheet::get_by_id(5);

// Method 3: Load separately
$sheet = new PTA_SUS_Sheet();
$sheet->load(5);
```

### Updating an Object
```php
$sheet = new PTA_SUS_Sheet(5);
$sheet->title = 'Updated Title';
$sheet->save(); // Updates the existing record
```

### Deleting an Object
```php
$sheet = new PTA_SUS_Sheet(5);
$sheet->delete();
```

### Converting to stdClass (for backward compatibility)
```php
$sheet = new PTA_SUS_Sheet(5);
$stdclass_sheet = $sheet->to_stdclass();
// Now you have a stdClass object like the old code returned
```

## Property Type System

The system uses property types for both sanitization and database formatting:

### Supported Types:
- `text` - Text field (sanitize_text_field)
- `textarea` - Textarea (sanitize_textarea_field)
- `email` - Email address (sanitize_email)
- `int` - Integer (absint)
- `bool` - Boolean (true/false)
- `date` - Date string
- `time` - Time string
- `phone` - Phone number
- `names` - Multiple names
- `emails` - Multiple emails
- `array` - Array data
- `dates` - Multiple dates (comma-separated)
- `yesno` - YES/NO values

### How It Works:
1. Properties are defined with their types in `get_property_definitions()`
2. When saving, values are sanitized based on type
3. Database format is determined from type (%d for int, %s for string, etc.)

## WordPress Coding Standards

The classes follow WordPress coding standards:
- Uses WordPress database abstraction (`$wpdb`)
- Uses WordPress sanitization functions
- Uses WordPress hooks (actions and filters)
- Uses WordPress current_time() for timestamps
- Follows WordPress naming conventions
- Includes proper PHPDoc blocks

## Testing the New Classes

### Simple Test (in wp-admin)
Add this to your theme's functions.php temporarily:

```php
add_action('init', function() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    
    // Test creating a sheet
    $test_sheet = new PTA_SUS_Sheet();
    $test_sheet->title = 'Test Sheet - ' . date('Y-m-d H:i:s');
    $test_sheet->type = 'Single';
    $test_sheet->visible = false; // Hide from public
    $id = $test_sheet->save();
    
    if ($id) {
        error_log('Created test sheet with ID: ' . $id);
        
        // Test loading it back
        $loaded = PTA_SUS_Sheet::get_by_id($id);
        error_log('Loaded sheet title: ' . $loaded->title);
        
        // Test updating
        $loaded->title = 'Updated Test Sheet';
        $loaded->save();
        error_log('Updated sheet');
        
        // Clean up - delete it
        $loaded->delete();
        error_log('Deleted test sheet');
    }
}, 999);
```

Check your debug.log file to see the results.

## Migration Strategy

### Phase 1: ✅ COMPLETE
- Created abstract base class
- Created Sheet, Task, and Signup classes
- Loaded classes in main plugin file
- Backward compatibility via magic methods

### Phase 2: NEXT STEPS
1. Add global helper functions to `pta-sus-global-functions.php`:
   ```php
   function pta_sus_get_sheet($id) {
       return PTA_SUS_Sheet::get_by_id($id);
   }
   
   function pta_sus_get_task($id) {
       return PTA_SUS_Task::get_by_id($id);
   }
   
   function pta_sus_get_signup($id) {
       return PTA_SUS_Signup::get_by_id($id);
   }
   ```

2. Start migrating methods from `data.php` one at a time
3. Add deprecation notices to old methods
4. Test with extensions

### Phase 3: FUTURE
- Gradually migrate all data.php methods
- Update extensions to use new classes
- Eventually remove old data.php methods (after appropriate deprecation period)

## Benefits of This Approach

1. **Gradual Migration**: Can be done slowly, one method at a time
2. **Backward Compatible**: Existing code continues to work
3. **Extensible**: Easy for plugins to extend via filters and inheritance
4. **Type-Safe**: Property types ensure data integrity
5. **DRY Principle**: Common functionality in base class
6. **Modern OOP**: Proper class hierarchy and encapsulation
7. **Testable**: Easy to unit test individual classes
8. **Maintainable**: Clear structure and organization

## File Structure
```
classes/
├── models/
│   ├── class-pta-sus-base-object.php    (Abstract base class)
│   ├── class-pta-sus-sheet.php          (Sheet class)
│   ├── class-pta-sus-task.php           (Task class)
│   └── class-pta-sus-signup.php         (Signup class)
├── data.php                              (Existing - to be gradually deprecated)
├── class-pta_sus_admin.php              (Existing)
├── class-pta_sus_public.php             (Existing)
└── ... (other existing files)
```

## Notes for Extensions

### Extending the Classes
Extensions can create their own classes:

```php
// In Waitlist extension:
class PTA_SUS_Waitlist_Signup extends PTA_SUS_Signup {
    protected function get_table_name() {
        return 'pta_sus_waitlist_signups';
    }
    
    protected function get_property_definitions() {
        $properties = parent::get_property_definitions();
        $properties['waitlist_id'] = 'int';
        $properties['added_to_waitlist_date'] = 'date';
        return $properties;
    }
    
    protected function get_property_defaults() {
        $defaults = parent::get_property_defaults();
        $defaults['waitlist_id'] = 0;
        $defaults['added_to_waitlist_date'] = null;
        return $defaults;
    }
}
```

### Adding Properties via Filters
Extensions can add properties without extending:

```php
add_filter('pta_sus_sheet_properties', function($properties) {
    $properties['custom_field'] = 'text';
    return $properties;
});

add_filter('pta_sus_sheet_property_defaults', function($defaults) {
    $defaults['custom_field'] = '';
    return $defaults;
});
```

## Questions or Issues?

If you encounter any issues or have questions about the implementation, refer to:
1. This README
2. The PHPDoc blocks in each class
3. The REFACTORING_NOTES.md file
4. The WordPress Codex for wpdb usage

---

**Created:** October 9, 2025  
**Branch:** `feature/base-object-class`  
**Status:** Ready for testing and iteration

