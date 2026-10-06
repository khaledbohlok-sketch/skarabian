<?php
/**
 * Optional: tell the importer where things are in the OLD database when it cannot find them by itself.
 * Copy to migration/mapping.php and keep only what you need. Run `php tools/import_old.php --schema` to see what
 * was found. Entity and field names are listed in app/Services/OldImport.php (ENTITIES).
 */
return [
    // 'horses' => [
    //     'table'   => 'tbl_horse',                 // old table name
    //     'columns' => ['name' => 'hname', 'dob' => 'birthday', 'dam' => 'mother_horse'],
    // ],
    // 'bills' => ['table' => 'finance_entries', 'columns' => ['amount' => 'amt', 'currency' => 'cur']],
];
