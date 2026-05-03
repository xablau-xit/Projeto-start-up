<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $route): never
{
    header('Location: ?route=' . urlencode($route));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        flash('error', 'Você precisa entrar para acessar esta página.');
        redirect('login');
    }
}

function requireRole(array $roles): void
{
    requireLogin();
    $user = currentUser();
    if (!in_array($user['role'], $roles, true)) {
        flash('error', 'Você não tem permissão para acessar esta área.');
        redirect('dashboard');
    }
}

function roleLabel(string $role): string
{
    return match ($role) {
        'cliente' => 'Cliente',
        'mecanico' => 'Mecânico',
        'admin' => 'Administrador',
        default => ucfirst($role),
    };
}

function formatDate(string $date): string
{
    if (!$date) {
        return '-';
    }
    return date('d/m/Y', strtotime($date));
}

function photoUrl(string $fileName): string
{
    return '../storage/uploads/' . rawurlencode($fileName);
}
