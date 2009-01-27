<?php
/*******************************************************************************
 * Copyright (c) 2007-2009 Intalio, Inc.
 * All rights reserved. This program and the accompanying materials
 * are made available under the terms of the Eclipse Public License v1.0
 * which accompanies this distribution, and is available at
 * http://www.eclipse.org/legal/epl-v10.html
 *
 * Contributors:
 *    Antoine Toulme, Intalio Inc.
*******************************************************************************/

// Use a class to define the hooks to avoid bugs with already defined functions.
class Reference_backend {
    /*
     * Authenticate a user.
     * Returns the User object if the user is found, or false
     */
    function authenticate($User, $email, $password) {
        $User->userid = 5;
    }
}

function __register_backend_ref($addon) {
    $addon->register('user_authentication', array('Reference_backend', 'authenticate'));
}

global $register_function_backend;
$register_function_backend = '__register_backend_ref';

?>