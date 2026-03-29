<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ChannelEncryptionService
{
    /**
     * Decrypt AES-GCM encrypted credentials from Supabase/Deno format.
     */
    public function decryptCredential(string $encryptedValue): string
    {
        if (! str_starts_with($encryptedValue, 'enc:')) {
            return $encryptedValue;
        }

        $ciphertext = substr($encryptedValue, 4);
        $keyString = config('services.channels.encryption_key', env('CHANNEL_ENCRYPTION_KEY'));

        if (! $keyString) {
            Log::warning('CHANNEL_ENCRYPTION_KEY not set, returning value as-is');

            return $encryptedValue;
        }

        // Deno hashes the key with SHA-256 before importing it as AES key
        $keyHash = hash('sha256', $keyString, true);

        // Ciphertext is base64 encoded byte array
        $combined = base64_decode($ciphertext);
        if ($combined === false) {
            return $encryptedValue;
        }

        if (strlen($combined) < 28) { // 12 bytes IV + 16 bytes Tag
            return $encryptedValue;
        }

        $iv = substr($combined, 0, 12);
        $encrypted = substr($combined, 12);

        $tagLength = 16;
        $ciphertextLength = strlen($encrypted) - $tagLength;

        if ($ciphertextLength < 0) {
            return $encryptedValue;
        }

        $actualCiphertext = substr($encrypted, 0, $ciphertextLength);
        $tag = substr($encrypted, $ciphertextLength);

        // AES-256-GCM decryption
        $decrypted = openssl_decrypt($actualCiphertext, 'aes-256-gcm', $keyHash, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            Log::error('Channel decryption failed: '.openssl_error_string());

            // Fall back or throw depending on strictness
            return $encryptedValue;
        }

        return $decrypted;
    }
}
