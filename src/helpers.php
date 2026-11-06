<?php
declare(strict_types=1);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pageUrl(string $page, array $parameters = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $parameters);
}

function displayType(string $type): string
{
    $value = trim($type);
    if (in_array($value, ['Sala', 'SALA', 'sala'], true)) return 'Sala';
    if (in_array($value, ['Laboratório', 'LABORATÓRIO', 'laboratório', 'Laboratorio', 'LABORATORIO', 'laboratorio'], true)) return 'Laboratório';
    if (in_array($value, ['Equipamento', 'EQUIPAMENTO', 'equipamento'], true)) return 'Equipamento';
    return $value;
}

function displayStatus(string $status): string
{
    return match (strtoupper($status)) {
        'ATIVA' => 'Ativa',
        'CANCELADA' => 'Cancelada',
        'CONCLUIDA' => 'Concluída',
        default => $status,
    };
}

function dateTimeLabel(string $value): string
{
    $date = new DateTimeImmutable($value);
    return $date->format('d/m/Y') . ' às ' . $date->format('H:i');
}

function initials(string $name): string
{
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $letters = [];
    foreach (array_slice($words, 0, 2) as $word) {
        $characters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters[] = strtoupper($characters[0] ?? '');
    }
    return implode('', $letters);
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function checkCsrf(): void
{
    $sent = (string) ($_POST['csrf_token'] ?? '');
    $saved = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sent === '' || $saved === '' || !hash_equals($saved, $sent)) {
        throw new RuntimeException('Sua sessão expirou. Atualize a página e tente novamente.');
    }
}

function redirectTo(string $page, array $parameters = []): never
{
    header('Location: ' . pageUrl($page, $parameters), true, 303);
    exit;
}

function setFlash(string $kind, string $message): void
{
    $_SESSION['flash'] = ['kind' => $kind, 'message' => $message];
}
