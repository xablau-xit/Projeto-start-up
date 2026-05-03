<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function loginUser(string $email, string $password): bool
{
    $user = findUserByEmail(trim(strtolower($email)));
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    unset($user['password']);
    $_SESSION['user'] = $user;
    return true;
}

function logoutUser(): void
{
    unset($_SESSION['user']);
}

function registerUser(array $data): array
{
    $name = trim($data['name'] ?? '');
    $email = trim(strtolower($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $role = $data['role'] ?? 'cliente';

    if ($name === '' || $email === '' || $password === '') {
        return [false, 'Preencha nome, e-mail e senha.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Informe um e-mail válido.'];
    }
    if (strlen($password) < 6) {
        return [false, 'A senha deve ter pelo menos 6 caracteres.'];
    }
    if (findUserByEmail($email)) {
        return [false, 'Este e-mail já está cadastrado.'];
    }

    createUser([
        'name' => $name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => in_array($role, ['cliente', 'mecanico'], true) ? $role : 'cliente',
        'phone' => trim($data['phone'] ?? ''),
        'city' => trim($data['city'] ?? ''),
        'bio' => trim($data['bio'] ?? ''),
        'avatar' => null,
    ]);

    return [true, 'Conta criada com sucesso. Faça seu login.'];
}
