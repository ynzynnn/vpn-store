<?php

namespace App\Services;

class WireguardKeyService
{
    /**
     * Generate WireGuard Curve25519 Keypair (Private Key, Public Key)
     * Menggunakan PHP sodium bawaan
     */
    public function generateKeypair(): array
    {
        // WireGuard menggunakan X25519 / Curve25519 keypair
        $keypair = sodium_crypto_box_keypair();
        $privateKeyRaw = sodium_crypto_box_secretkey($keypair);
        $publicKeyRaw = sodium_crypto_box_publickey($keypair);

        // Clamp private key sesuai spesifikasi WireGuard / Curve25519
        $privateKeyBytes = unpack('C*', $privateKeyRaw);
        $privateKeyBytes[1] &= 248;
        $privateKeyBytes[32] &= 127;
        $privateKeyBytes[32] |= 64;
        $clampedPrivateKey = pack('C*', ...$privateKeyBytes);

        // Recompute public key dari clamped private key
        $publicKey = sodium_crypto_scalarmult_base($clampedPrivateKey);

        return [
            'private_key' => base64_encode($clampedPrivateKey),
            'public_key' => base64_encode($publicKey),
            'preshared_key' => base64_encode(random_bytes(32)),
        ];
    }
}
