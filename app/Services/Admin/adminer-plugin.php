<?php

/**
 * Adminer custom plugin for SQLite database without password
 */

function adminer_object() {

    class AdminerSoftware extends Adminer\Adminer {
        /**
         * Allow passwordless SQLite connection
         */
        function login($login, $password) {
            return true;
        }

        /**
         * Set database credentials
         */
        function credentials() {
            return array(
                $_GET['sqlite'] ?? '',
                $_GET['username'] ?? '',
                $_GET['password'] ?? ''
            );
        }

        /**
         * Disable brute force protection
         */
        function bruteForceKey() {
            return '';
        }
    }

    return new AdminerSoftware;
}

// Include Adminer
include __DIR__ . '/adminer-5.4.1.php';
