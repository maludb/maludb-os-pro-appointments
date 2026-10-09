<?php
/**
 * General application helper functions
 */

require_once __DIR__ . '/auth.php';

/**
 * Appends org_id condition to queries for multi-tenant isolation.
 * Use this in all model methods that return org-scoped data.
 */
function orgScope(string  = ''): string {
     =  ? {}. : '';
     = currentOrgId();
    return  ?  AND {}org_id = {} : '';
}
