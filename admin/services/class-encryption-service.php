<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encryption Service
 * Handles encryption and decryption of sensitive data (API tokens)
 * 
 * @package ClinicQueue
 * @subpackage Admin\Services
 */
class Clinic_Queue_Encryption_Service {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return Clinic_Queue_Encryption_Service
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Private constructor for singleton
    }
    
    /**
     * Encrypt token using WordPress salts
     * 
     * @param string $token Plain token to encrypt
     * @return string Encrypted token (base64 encoded)
     */
    public function encrypt_token($token) {
        if (empty($token)) {
            return '';
        }
        
        if (!function_exists('openssl_encrypt')) {
            // Fallback: simple obfuscation (not secure, but better than plain text)
            return base64_encode($token);
        }
        
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        
        if ($encrypted === false) {
            error_log('[ClinicQueue Encryption] Failed to encrypt token');
            return base64_encode($token); // Fallback
        }
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt token using WordPress salts
     * 
     * @param string $encrypted_token Encrypted token (base64 encoded)
     * @return string|false Plain token or false on failure
     */
    public function decrypt_token($encrypted_token) {
        if (empty($encrypted_token)) {
            return false;
        }
        
        if (!function_exists('openssl_decrypt')) {
            // Fallback: simple deobfuscation
            return base64_decode($encrypted_token);
        }
        
        $data = base64_decode($encrypted_token);
        if ($data === false) {
            error_log('[ClinicQueue Encryption] Failed to decode base64 token');
            return false;
        }
        
        $key = $this->get_encryption_key();
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        
        if (strlen($data) < $iv_length) {
            error_log('[ClinicQueue Encryption] Invalid encrypted data length');
            return false;
        }
        
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
        
        if ($decrypted === false) {
            error_log('[ClinicQueue Encryption] Failed to decrypt token');
            return false;
        }
        
        return $decrypted;
    }
    
    /**
     * Get encryption key from WordPress salts
     * 
     * @return string Encryption key (32 bytes for AES-256)
     */
    private function get_encryption_key() {
        $salt = defined('AUTH_SALT') ? AUTH_SALT : 'default-salt-change-in-wp-config';
        $site_url = get_option('siteurl', '');
        return hash('sha256', $salt . $site_url, true);
    }
    
    /**
     * Check if encryption is available
     * 
     * @return bool True if OpenSSL is available
     */
    public function is_encryption_available() {
        return function_exists('openssl_encrypt') && function_exists('openssl_decrypt');
    }
}

