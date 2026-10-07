<?php
/**
 * Valida um CPF pelos dígitos verificadores (aceita com ou sem máscara).
 * Rejeita sequências repetidas como 111.111.111-11.
 */
function cpfValido($cpf)
{
    $cpf = preg_replace('/\D/', '', (string) $cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cpf[$i] * (($t + 1) - $i);
        }
        $dv = ((10 * $soma) % 11) % 10;
        if ((int) $cpf[$t] !== $dv) {
            return false;
        }
    }
    return true;
}
