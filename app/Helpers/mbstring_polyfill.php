<?php

/**
 * Polyfills for missing functions in PHP 8.3.33
 * - mb_split() was deprecated in PHP 8.0 and removed in PHP 8.3
 * - openssl_cipher_iv_length() missing in some PHP 8.3 builds
 */

// ========================================
// MB String Functions Polyfill
// ========================================

if (!function_exists('mb_split')) {
    function mb_split($pattern, $string, $limit = -1)
    {
        $pattern = '/' . $pattern . '/u';
        
        if ($limit == -1) {
            $result = preg_split($pattern, $string);
        } else {
            $result = preg_split($pattern, $string, $limit);
        }
        
        return $result !== false ? $result : false;
    }
}

if (!function_exists('mb_ereg_replace')) {
    function mb_ereg_replace($pattern, $replacement, $string, $options = null)
    {
        $pattern = '/' . $pattern . '/u';
        
        if ($options !== null && strpos($options, 'i') !== false) {
            $pattern .= 'i';
        }
        
        return preg_replace($pattern, $replacement, $string);
    }
}

if (!function_exists('mb_eregi_replace')) {
    function mb_eregi_replace($pattern, $replacement, $string, $options = null)
    {
        return mb_ereg_replace($pattern, $replacement, $string, 'i');
    }
}

// ========================================
// OpenSSL Functions Polyfill
// ========================================

if (!function_exists('openssl_cipher_iv_length')) {
    /**
     * Gets the cipher IV length
     * Polyfill for missing openssl_cipher_iv_length() in PHP 8.3.33
     *
     * @param string $cipher_algo The cipher method
     * @return int|false The cipher length on success, or false on failure
     */
    function openssl_cipher_iv_length($cipher_algo)
    {
        // Standard IV lengths for common ciphers used by Laravel
        $iv_lengths = [
            'aes-128-cbc' => 16,
            'aes-192-cbc' => 16,
            'aes-256-cbc' => 16,
            'aes-128-gcm' => 12,
            'aes-192-gcm' => 12,
            'aes-256-gcm' => 12,
            'des-ede3-cbc' => 8,
            'bf-cbc' => 8,
            'cast5-cbc' => 8,
        ];
        
        $cipher_algo = strtolower($cipher_algo);
        
        if (isset($iv_lengths[$cipher_algo])) {
            return $iv_lengths[$cipher_algo];
        }
        
        // Default for AES (most common)
        if (strpos($cipher_algo, 'aes') !== false) {
            if (strpos($cipher_algo, 'gcm') !== false) {
                return 12;
            }
            return 16;
        }
        
        // Fallback: try to use openssl_encrypt to determine IV length
        // This is a workaround for unknown ciphers
        try {
            $test_data = 'test';
            $test_key = str_repeat('0', 32);
            
            // Try with different IV lengths
            foreach ([16, 12, 8] as $iv_length) {
                $iv = str_repeat('0', $iv_length);
                $encrypted = @openssl_encrypt($test_data, $cipher_algo, $test_key, OPENSSL_RAW_DATA, $iv);
                if ($encrypted !== false) {
                    return $iv_length;
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
        
        // Default fallback
        return 16;
    }
}
