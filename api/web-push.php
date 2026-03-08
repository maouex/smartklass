<?php
// ============================================================================
// SmartKlass — Web Push Helper (VAPID + RFC 8291 encryption)
// Nécessite PHP 8.1+ (openssl_pkey_derive) et l'extension openssl + curl
// ============================================================================

function b64u(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function b64ud(string $s): string {
    return base64_decode(strtr($s, '-_', '+/'));
}

/**
 * Construit une SubjectPublicKeyInfo PEM à partir d'une clé P-256 brute (65 octets, format non compressé 0x04||x||y).
 * Nécessaire pour importer la clé publique du subscriber dans OpenSSL.
 */
function ec_raw_to_pem(string $raw65): string {
    // DER SubjectPublicKeyInfo pour P-256 :
    // SEQUENCE { SEQUENCE { OID id-ecPublicKey, OID prime256v1 }, BIT STRING { 00 || point } }
    $header = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
            . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";
    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($header . $raw65), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

/**
 * Crée le JWT VAPID signé ES256 (RFC 8292).
 * $privDer : octets DER bruts de la clé privée EC (SEC1)
 * $pubRaw  : clé publique EC non compressée (65 octets)
 * $contact : URI de contact (ex: "mailto:admin@smartklass.fr")
 */
function vapid_jwt(string $endpoint, string $privDer, string $pubRaw, string $contact): string {
    $aud = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
    $h = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $p = b64u(json_encode(['aud' => $aud, 'exp' => time() + 43200, 'sub' => $contact]));

    // Reconstruire le PEM SEC1 depuis les octets DER bruts
    $pem = "-----BEGIN EC PRIVATE KEY-----\n"
         . chunk_split(base64_encode($privDer), 64, "\n")
         . "-----END EC PRIVATE KEY-----\n";

    openssl_sign("$h.$p", $der_sig, openssl_pkey_get_private($pem), OPENSSL_ALGO_SHA256);

    // Convertir la signature DER (30 [len] 02 [rlen] r 02 [slen] s) → raw r||s (64 octets)
    $off = 3;
    $rLen = ord($der_sig[$off]);
    $r = substr($der_sig, $off + 1, $rLen);
    $off += 1 + $rLen + 1;
    $sLen = ord($der_sig[$off]);
    $s = substr($der_sig, $off + 1, $sLen);
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    return "$h.$p." . b64u($r . $s);
}

/**
 * Chiffre le payload selon RFC 8291 (aes128gcm content encoding).
 * Utilise ECDH éphémère + HKDF + AES-128-GCM.
 * $p256dhRaw : clé publique P-256 du subscriber (65 octets non compressés)
 * $authRaw   : secret d'authentification du subscriber (16 octets)
 * Retourne ['body' => string] — le contenu chiffré avec son en-tête aes128gcm.
 */
function encrypt_payload(string $payload, string $p256dhRaw, string $authRaw): array {
    // 1. Générer une paire de clés EC éphémère (sender)
    $eph = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $ephDetails = openssl_pkey_get_details($eph);
    $ephPub = "\x04"
        . str_pad($ephDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad($ephDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);

    // 2. Importer la clé publique du subscriber
    $subPem = ec_raw_to_pem($p256dhRaw);
    $subKey = openssl_pkey_get_public($subPem);

    // 3. Calculer le secret partagé ECDH (nécessite PHP 8.1+)
    $sharedSecret = openssl_pkey_derive($subKey, $eph, 32);

    // 4. Dérivation de clés selon RFC 8291 §3.3
    //    PRK_key = HKDF-Extract(salt=auth_secret, IKM=ecdh_secret)
    $PRK_key = hash_hmac('sha256', $sharedSecret, $authRaw, true);
    //    key_info = "WebPush: info" || 0x00 || ua_public (65B) || as_public (65B)
    $key_info = "WebPush: info\x00" . $p256dhRaw . $ephPub;
    //    IKM = HKDF-Expand(PRK_key, key_info || 0x01, 32)
    $IKM = substr(hash_hmac('sha256', $key_info . "\x01", $PRK_key, true), 0, 32);

    // 5. Sel aléatoire 16 octets
    $salt = random_bytes(16);
    //    PRK = HKDF-Extract(salt=salt, IKM=IKM)
    $PRK = hash_hmac('sha256', $IKM, $salt, true);
    //    CEK = HKDF-Expand(PRK, "Content-Encoding: aes128gcm" || 0x00 || 0x01, 16)
    $CEK = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $PRK, true), 0, 16);
    //    NONCE = HKDF-Expand(PRK, "Content-Encoding: nonce" || 0x00 || 0x01, 12)
    $NONCE = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $PRK, true), 0, 12);

    // 6. Chiffrement AES-128-GCM
    //    Plaintext = payload || 0x02 (délimiteur de dernier enregistrement)
    $padded = $payload . "\x02";
    $tag = '';
    $ct = openssl_encrypt($padded, 'aes-128-gcm', $CEK, OPENSSL_RAW_DATA, $NONCE, $tag, '', 16);

    // 7. En-tête aes128gcm : salt(16) + rs(4) + idlen(1) + server_key(65)
    //    rs = taille de l'enregistrement padded (sans tag AEAD)
    $rs = pack('N', strlen($padded));
    $header = $salt . $rs . chr(65) . $ephPub;

    return ['body' => $header . $ct . $tag];
}

/**
 * Envoie une notification push à une subscription.
 * $sub       : ligne de la table push_subscriptions (endpoint, p256dh, auth en base64url)
 * $jsonPayload : JSON string du payload (ex: {"title":"...", "body":"..."})
 * $privDer   : clé privée VAPID (octets DER SEC1)
 * $pubRaw    : clé publique VAPID (65 octets non compressés)
 * Retourne true si succès (HTTP 2xx), false sinon (subscription expirée → à supprimer).
 */
function send_push(array $sub, string $jsonPayload, string $privDer, string $pubRaw): bool {
    $enc = encrypt_payload($jsonPayload, b64ud($sub['p256dh']), b64ud($sub['auth']));
    $jwt = vapid_jwt($sub['endpoint'], $privDer, $pubRaw, 'mailto:admin@smartklass.fr');
    $pubB64u = b64u($pubRaw);

    $ch = curl_init($sub['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POSTFIELDS     => $enc['body'],
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
            'Urgency: normal',
            'Authorization: vapid t=' . $jwt . ',k=' . $pubB64u,
        ],
    ]);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 201 = Created (succès), 410 = Gone / 404 = subscription expirée → supprimer
    return $httpCode >= 200 && $httpCode < 300;
}
