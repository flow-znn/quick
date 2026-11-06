<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/config/database.php';
require __DIR__ . '/src/helpers.php';

$allowedPages = ['recursos', 'reserva', 'reservas', 'detalhe', 'novo-recurso'];
$page = (string) ($_GET['page'] ?? 'recursos');
if (!in_array($page, $allowedPages, true)) {
    $page = 'recursos';
}

$pdo = null;
$databaseError = '';
try {
    $pdo = database();
} catch (Throwable $error) {
    $databaseError = $error instanceof RuntimeException
        ? $error->getMessage()
        : 'Não foi possível conectar ao MySQL. Confira os dados do container e tente novamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    try {
        checkCsrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'reservar') {
            $resourceId = filter_input(INPUT_POST, 'id_recurso', FILTER_VALIDATE_INT);
            $userId = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
            $reservationDate = trim((string) ($_POST['data'] ?? ''));
            $startTime = trim((string) ($_POST['hora_inicio'] ?? ''));
            $endTime = trim((string) ($_POST['hora_fim'] ?? ''));
            $startValue = $reservationDate . ' ' . $startTime . ':00';
            $endValue = $reservationDate . ' ' . $endTime . ':00';

            if (!$resourceId || !$userId || $reservationDate === '' || $startTime === '' || $endTime === '') {
                throw new DomainException('Preencha todos os campos obrigatórios.');
            }
            $startDateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $startValue);
            $endDateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $endValue);
            if (!$startDateTime || !$endDateTime
                || $startDateTime->format('Y-m-d H:i:s') !== $startValue
                || $endDateTime->format('Y-m-d H:i:s') !== $endValue) {
                throw new DomainException('Informe uma data e horários válidos.');
            }
            if ($endDateTime <= $startDateTime) {
                throw new DomainException('O horário de término precisa ser depois do início.');
            }

            $pdo->beginTransaction();
            // O bloqueio da linha do recurso serializa as novas reservas desse recurso nesta aplicação.
            $lock = $pdo->prepare('SELECT id_recurso FROM RECURSO WHERE id_recurso = :id FOR UPDATE');
            $lock->execute(['id' => $resourceId]);
            if (!$lock->fetch()) {
                throw new DomainException('O recurso selecionado não existe.');
            }

            $userCheck = $pdo->prepare('SELECT id_usuario FROM USUARIO WHERE id_usuario = :id');
            $userCheck->execute(['id' => $userId]);
            if (!$userCheck->fetch()) {
                throw new DomainException('O responsável selecionado não existe.');
            }

            $conflict = $pdo->prepare(
                "SELECT id_reserva FROM RESERVA
                 WHERE id_recurso = :resource
                   AND status = 'ATIVA'
                   AND inicio < :requested_end
                   AND fim > :requested_start
                 LIMIT 1"
            );
            $conflict->execute([
                'resource' => $resourceId,
                'requested_end' => $endValue,
                'requested_start' => $startValue,
            ]);
            if ($conflict->fetch()) {
                throw new DomainException('Conflito: este recurso já possui uma reserva ativa nesse horário.');
            }

            $insert = $pdo->prepare(
                "INSERT INTO RESERVA (id_usuario, id_recurso, inicio, fim, status)
                 VALUES (:user, :resource, :start, :end, 'ATIVA')"
            );
            $insert->execute([
                'user' => $userId,
                'resource' => $resourceId,
                'start' => $startValue,
                'end' => $endValue,
            ]);
            $pdo->commit();
            setFlash('success', 'Reserva cadastrada com sucesso.');
            redirectTo('reservas');
        }

        if ($action === 'cancelar') {
            $bookingId = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
            if (!$bookingId) {
                throw new DomainException('Reserva inválida.');
            }
            $cancel = $pdo->prepare("UPDATE RESERVA SET status = 'CANCELADA' WHERE id_reserva = :id AND status = 'ATIVA'");
            $cancel->execute(['id' => $bookingId]);
            if ($cancel->rowCount() === 0) {
                throw new DomainException('A reserva não está ativa ou já foi cancelada.');
            }
            setFlash('success', 'Reserva cancelada.');
            redirectTo('reservas');
        }

        if ($action === 'criar-recurso') {
            $name = trim((string) ($_POST['nome'] ?? ''));
            $type = trim((string) ($_POST['tipo'] ?? ''));
            $description = trim((string) ($_POST['descricao'] ?? ''));
            if ($name === '' || !in_array($type, ['Sala', 'Laboratório', 'Equipamento'], true)) {
                throw new DomainException('Informe um nome e um tipo válido para o recurso.');
            }
            $insert = $pdo->prepare('INSERT INTO RECURSO (nome, tipo, descricao) VALUES (:name, :type, :description)');
            $insert->execute(['name' => $name, 'type' => $type, 'description' => $description]);
            setFlash('success', 'Recurso cadastrado com sucesso.');
            redirectTo('recursos');
        }
        throw new DomainException('Ação não reconhecida.');
    } catch (DomainException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', $error->getMessage());
        redirectTo($page, $_GET);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', 'Não foi possível concluir a operação. Confira a estrutura do banco e tente novamente.');
        redirectTo($page, $_GET);
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$resources = [];
$users = [];
$bookings = [];
$resource = null;
$detailBookings = [];
$queryError = '';

if ($pdo instanceof PDO) {
    try {
        $resourcesStatement = $pdo->query(
            "SELECT r.id_recurso, r.nome, r.tipo, r.descricao,
                    EXISTS (
                        SELECT 1 FROM RESERVA b
                        WHERE b.id_recurso = r.id_recurso
                          AND b.status = 'ATIVA'
                          AND NOW() >= b.inicio AND NOW() < b.fim
                    ) AS em_uso
             FROM RECURSO r ORDER BY r.nome"
        );
        $resources = $resourcesStatement->fetchAll();
        if ($page === 'reserva') {
            $users = $pdo->query('SELECT id_usuario, nome FROM USUARIO ORDER BY nome')->fetchAll();
        }
        if ($page === 'reservas') {
            $sql = 'SELECT b.id_reserva, b.inicio, b.fim, b.status, u.nome AS responsavel, r.nome AS recurso, r.id_recurso AS resource_id
                    FROM RESERVA b
                    INNER JOIN USUARIO u ON u.id_usuario = b.id_usuario
                    INNER JOIN RECURSO r ON r.id_recurso = b.id_recurso
                    WHERE 1 = 1';
            $parameters = [];
            $search = trim((string) ($_GET['q'] ?? ''));
            $status = strtoupper(trim((string) ($_GET['status'] ?? '')));
            if ($search !== '') {
                $sql .= ' AND (r.nome LIKE :resource_search OR u.nome LIKE :user_search)';
                $parameters['resource_search'] = '%' . $search . '%';
                $parameters['user_search'] = '%' . $search . '%';
            }
            if (in_array($status, ['ATIVA', 'CANCELADA', 'CONCLUIDA'], true)) {
                $sql .= ' AND b.status = :status';
                $parameters['status'] = $status;
            }
            $sql .= ' ORDER BY b.inicio DESC';
            $bookingStatement = $pdo->prepare($sql);
            $bookingStatement->execute($parameters);
            $bookings = $bookingStatement->fetchAll();
        }
        if ($page === 'detalhe') {
            $resourceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if ($resourceId) {
                $resourceStatement = $pdo->prepare('SELECT id_recurso, nome, tipo, descricao FROM RECURSO WHERE id_recurso = :id');
                $resourceStatement->execute(['id' => $resourceId]);
                $resource = $resourceStatement->fetch() ?: null;
                if ($resource) {
                    $monthValue = (string) ($_GET['mes'] ?? date('Y-m'));
                    $month = DateTimeImmutable::createFromFormat('!Y-m-d', $monthValue . '-01') ?: new DateTimeImmutable('first day of this month');
                    $monthStart = $month->format('Y-m-d 00:00:00');
                    $monthEnd = $month->modify('first day of next month')->format('Y-m-d 00:00:00');
                    $agenda = $pdo->prepare(
                        "SELECT inicio, fim FROM RESERVA
                         WHERE id_recurso = :resource AND status = 'ATIVA'
                           AND inicio < :month_end AND fim >= :month_start
                         ORDER BY inicio"
                    );
                    $agenda->execute(['resource' => $resourceId, 'month_end' => $monthEnd, 'month_start' => $monthStart]);
                    $detailBookings = $agenda->fetchAll();
                }
            }
        }
    } catch (Throwable $error) {
        $queryError = 'A conexão foi realizada, mas a consulta falhou. Confira se as tabelas e colunas seguem o modelo do projeto.';
    }
}

$pageTitles = [
    'recursos' => ['Recursos', 'Consulte salas, laboratórios e equipamentos da instituição.'],
    'reserva' => ['Nova reserva', 'Preencha os dados para solicitar a reserva de um recurso.'],
    'reservas' => ['Reservas', 'Consulte e gerencie todas as reservas cadastradas no sistema.'],
    'detalhe' => ['Detalhes do recurso', 'Visualize as informações e a agenda de utilização do recurso.'],
    'novo-recurso' => ['Cadastrar recurso', 'Adicione um recurso ao catálogo da instituição.'],
];
[$pageTitle, $pageSubtitle] = $pageTitles[$page];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f8f6">
    <title><?= h($pageTitle) ?> · Quick Reservation</title>
    <link rel="stylesheet" href="src/styles.css">
    <link rel="stylesheet" href="src/php.css">
    <script src="src/app.js" defer></script>
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand"><img src="public/logo.png" alt="Quick Reservation"></div>
        <span class="nav-caption">NAVEGAÇÃO</span>
        <nav class="nav-list">
            <a class="nav-item <?= in_array($page, ['recursos', 'detalhe', 'novo-recurso'], true) ? 'active' : '' ?>" href="<?= h(pageUrl('recursos')) ?>">▦ <span>Recursos</span></a>
            <a class="nav-item <?= $page === 'reserva' ? 'active' : '' ?>" href="<?= h(pageUrl('reserva')) ?>">＋ <span>Nova reserva</span></a>
            <a class="nav-item <?= $page === 'reservas' ? 'active' : '' ?>" href="<?= h(pageUrl('reservas')) ?>">▤ <span>Reservas</span></a>
        </nav>
        <div class="sidebar-bottom">
            <span class="nav-caption">PRECISA DE AJUDA?</span>
            <button class="nav-item help-button" type="button" data-help>◉ <span>Ajuda e suporte</span></button>
            <div class="access-label"><span class="online-dot"></span>Acesso administrativo</div>
        </div>
    </aside>

    <main class="main-area">
        <header class="topbar">
            <div class="institution">Instituição <span>·</span> Sistema de reservas</div>
            <div class="profile"><div class="avatar">A</div><div><strong>Administrador</strong><small>acesso administrativo</small></div></div>
        </header>
        <div class="page-wrap">
            <?php if ($databaseError !== ''): ?>
                <div class="database-alert" role="alert"><strong>Banco de dados desconectado</strong><span><?= h($databaseError) ?></span></div>
            <?php elseif ($queryError !== ''): ?>
                <div class="database-alert" role="alert"><strong>Não foi possível carregar os dados</strong><span><?= h($queryError) ?></span></div>
            <?php endif; ?>
            <?php if (is_array($flash)): ?>
                <div class="notice <?= h($flash['kind']) ?>" role="status"><?= h($flash['message']) ?></div>
            <?php endif; ?>

            <div class="page-heading">
                <div><h1><?= h($pageTitle) ?></h1><p><?= h($pageSubtitle) ?></p></div>
                <?php if ($page === 'recursos'): ?>
                    <a class="button primary" href="<?= h(pageUrl('novo-recurso')) ?>">＋ Cadastrar recurso</a>
                <?php elseif ($page === 'reservas'): ?>
                    <a class="button primary" href="<?= h(pageUrl('reserva')) ?>">＋ Nova reserva</a>
                <?php elseif ($page === 'detalhe' && $resource): ?>
                    <a class="button primary" href="<?= h(pageUrl('reserva', ['id_recurso' => $resource['id_recurso']])) ?>">＋ Reservar recurso</a>
                <?php endif; ?>
            </div>

            <?php if ($page === 'recursos'): ?>
                <div class="toolbar">
                    <label class="searchbox"><span aria-hidden="true">⌕</span><input type="search" placeholder="Buscar recurso pelo nome..." data-resource-search></label>
                    <select aria-label="Filtrar por tipo" data-resource-type><option value="">Todos os tipos</option><option>Sala</option><option>Laboratório</option><option>Equipamento</option></select>
                    <select aria-label="Filtrar por disponibilidade" data-resource-availability><option value="">Disponibilidade</option><option value="0">Disponíveis</option><option value="1">Em uso</option></select>
                    <span class="filter-label">Filtros</span>
                </div>
                <?php if ($resources): ?>
                    <div class="resource-grid" data-resource-grid>
                        <?php foreach ($resources as $item): $itemType = displayType((string) $item['tipo']); $busy = (bool) $item['em_uso']; ?>
                            <article class="resource-card" data-resource-card data-name="<?= h($item['nome']) ?>" data-type="<?= h($itemType) ?>" data-busy="<?= $busy ? '1' : '0' ?>">
                                <div class="card-top"><div class="resource-avatar"><?= h(initials((string) $item['nome'])) ?></div><span class="availability <?= $busy ? 'busy' : '' ?>"><i></i><?= $busy ? 'Em uso' : 'Disponível' ?></span></div>
                                <h3><?= h($item['nome']) ?></h3>
                                <div class="resource-meta"><?= h($itemType) ?></div>
                                <p class="resource-description"><?= h($item['descricao'] ?: 'Sem descrição cadastrada.') ?></p>
                                <div class="card-actions"><a class="details-link" href="<?= h(pageUrl('detalhe', ['id' => $item['id_recurso']])) ?>">Ver detalhes <span>→</span></a><a class="button small-button" href="<?= h(pageUrl('reserva', ['id_recurso' => $item['id_recurso']])) ?>">Reservar</a></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <p class="empty-state hidden" data-resource-empty>Nenhum recurso encontrado com esses filtros.</p>
                <?php else: ?>
                    <div class="empty-state"><strong>Nenhum recurso cadastrado.</strong><p>Quando houver registros na tabela RECURSO, eles aparecerão aqui.</p></div>
                <?php endif; ?>

            <?php elseif ($page === 'novo-recurso'): ?>
                <section class="panel form-panel narrow-panel"><div class="panel-title"><h2>Informações do recurso</h2><p>Os campos correspondem às colunas do modelo RECURSO.</p></div>
                    <form method="post" class="form-grid" data-validate-form>
                        <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="criar-recurso">
                        <label class="field full">Nome<input name="nome" maxlength="150" required placeholder="Nome do recurso"></label>
                        <label class="field full">Tipo<select name="tipo" required><option value="">Selecione o tipo</option><option>Sala</option><option>Laboratório</option><option>Equipamento</option></select></label>
                        <label class="field full">Descrição<textarea name="descricao" rows="4" placeholder="Descrição do recurso"></textarea></label>
                        <div class="form-actions full"><button class="button primary" type="submit">Cadastrar recurso</button><a class="button secondary" href="<?= h(pageUrl('recursos')) ?>">Cancelar</a></div>
                    </form>
                </section>

            <?php elseif ($page === 'reserva'): ?>
                <?php $requestedResource = (int) ($_GET['id_recurso'] ?? 0); ?>
                <div class="booking-layout">
                    <section class="panel form-panel"><div class="panel-title"><h2>Informações da reserva</h2><p>Selecione o recurso, o responsável e o período desejado.</p></div>
                        <?php if (!$users): ?><div class="notice warning">Cadastre ao menos um responsável na tabela USUARIO para criar uma reserva.</div><?php endif; ?>
                        <?php if (!$resources): ?><div class="notice warning">Cadastre um recurso antes de criar uma reserva.</div><?php endif; ?>
                        <form method="post" class="form-grid" data-reservation-form>
                            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="reservar">
                            <label class="field full">Recurso<select name="id_recurso" required data-summary-resource><option value="">Selecione um recurso</option><?php foreach ($resources as $item): ?><option value="<?= h($item['id_recurso']) ?>" <?= $requestedResource === (int) $item['id_recurso'] ? 'selected' : '' ?>><?= h($item['nome']) ?></option><?php endforeach; ?></select></label>
                            <label class="field">Data<input type="date" name="data" required data-date></label><span></span>
                            <label class="field">Horário de início<input type="time" name="hora_inicio" required data-start></label>
                            <label class="field">Horário de término<input type="time" name="hora_fim" required data-end></label>
                            <label class="field full">Responsável<select name="id_usuario" required><option value="">Selecione um responsável</option><?php foreach ($users as $person): ?><option value="<?= h($person['id_usuario']) ?>"><?= h($person['nome']) ?></option><?php endforeach; ?></select></label>
                            <div class="availability-note full"><strong>Verificação de disponibilidade</strong><span>O sistema verifica conflitos antes de salvar. A proteção definitiva também deve estar no backend/banco usado por todos os clientes.</span></div>
                            <div class="form-actions full"><button class="button primary" type="submit" <?= (!$users || !$resources) ? 'disabled' : '' ?>>Confirmar reserva</button><a class="button secondary" href="<?= h(pageUrl('recursos')) ?>">Cancelar</a></div>
                        </form>
                    </section>
                    <aside class="panel summary-panel"><h2>Resumo da solicitação</h2><div class="summary-item"><span>RECURSO</span><strong data-summary-resource-name>Nenhum selecionado</strong></div><div class="summary-item"><span>DATA</span><strong data-summary-date>Aguardando seleção</strong></div><div class="summary-item"><span>HORÁRIO</span><strong data-summary-time>--:-- até --:--</strong></div><div class="info-box"><strong>Atenção</strong><p>O responsável é selecionado entre os registros já cadastrados em USUARIO.</p></div></aside>
                </div>

            <?php elseif ($page === 'reservas'): ?>
                <div class="toolbar">
                    <label class="searchbox"><span aria-hidden="true">⌕</span><input type="search" placeholder="Buscar por recurso ou responsável..." data-booking-search></label>
                    <select aria-label="Filtrar por status" data-booking-status><option value="">Todos os status</option><option value="ATIVA">Ativa</option><option value="CANCELADA">Cancelada</option><option value="CONCLUIDA">Concluída</option></select>
                    <input class="period-select" type="month" aria-label="Filtrar por mês" data-booking-period>
                </div>
                <?php if ($bookings): ?>
                    <div class="panel table-panel"><div class="table-scroll"><table><thead><tr><th>RECURSO</th><th>RESPONSÁVEL</th><th>DATA</th><th>HORÁRIO</th><th>STATUS</th><th>AÇÕES</th></tr></thead><tbody>
                        <?php foreach ($bookings as $booking): $status = strtoupper((string) $booking['status']); $start = new DateTimeImmutable($booking['inicio']); $end = new DateTimeImmutable($booking['fim']); ?>
                            <tr data-booking-row data-search="<?= h($booking['recurso'] . ' ' . $booking['responsavel']) ?>" data-status="<?= h($status) ?>" data-period="<?= h($start->format('Y-m')) ?>">
                                <td><strong><?= h($booking['recurso']) ?></strong></td><td><?= h($booking['responsavel']) ?></td><td><?= h($start->format('d/m/Y')) ?></td><td><?= h($start->format('H:i') . ' – ' . $end->format('H:i')) ?></td>
                                <td><span class="badge <?= h(strtolower($status)) ?>"><?= h(displayStatus($status)) ?></span></td>
                                <td><a class="table-action" href="<?= h(pageUrl('detalhe', ['id' => $booking['resource_id']])) ?>">Ver recurso</a><?php if ($status === 'ATIVA'): ?><form method="post" class="inline-form" data-confirm="Cancelar esta reserva?"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="cancelar"><input type="hidden" name="id_reserva" value="<?= h($booking['id_reserva']) ?>"><button class="table-icon" type="submit">Cancelar</button></form><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody></table></div><p class="table-empty hidden" data-booking-empty>Nenhuma reserva encontrada.</p><div class="table-footer">Exibindo <?= count($bookings) ?> reservas cadastradas</div></div>
                <?php else: ?><div class="empty-state"><strong>Nenhuma reserva cadastrada.</strong><p>As reservas registradas na tabela RESERVA aparecerão aqui.</p></div><?php endif; ?>

            <?php elseif ($page === 'detalhe'): ?>
                <?php if (!$resource): ?><div class="empty-state"><strong>Recurso não encontrado.</strong><a class="text-button" href="<?= h(pageUrl('recursos')) ?>">Voltar para recursos</a></div>
                <?php else:
                    $monthValue = (string) ($_GET['mes'] ?? date('Y-m'));
                    $month = DateTimeImmutable::createFromFormat('!Y-m-d', $monthValue . '-01') ?: new DateTimeImmutable('first day of this month');
                    $daysInMonth = (int) $month->format('t');
                    $firstWeekday = ((int) $month->format('N')) - 1;
                    $reservedDays = [];
                    foreach ($detailBookings as $entry) { $reservedDays[(new DateTimeImmutable($entry['inicio']))->format('j')] = true; }
                    $monthNames = [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'];
                ?>
                    <a class="back-link" href="<?= h(pageUrl('recursos')) ?>">‹ Voltar para recursos</a>
                    <section class="panel detail-hero"><div class="detail-resource-icon"><?= h(initials($resource['nome'])) ?></div><div class="detail-info"><span class="eyebrow"><?= h(strtoupper(displayType($resource['tipo']))) ?></span><h2><?= h($resource['nome']) ?></h2><p><?= h(displayType($resource['tipo'])) ?></p></div><a class="button primary" href="<?= h(pageUrl('reserva', ['id_recurso' => $resource['id_recurso']])) ?>">＋ Reservar recurso</a><p class="hero-description"><?= h($resource['descricao'] ?: 'Sem descrição cadastrada.') ?></p></section>
                    <div class="detail-grid"><section class="panel detail-data"><h2>Informações do recurso</h2><div class="summary-item"><span>TIPO</span><strong><?= h(displayType($resource['tipo'])) ?></strong></div><div class="summary-item"><span>DESCRIÇÃO</span><strong><?= h($resource['descricao'] ?: 'Não informada') ?></strong></div></section>
                        <section class="panel calendar-panel"><div class="calendar-heading"><div><h2>Agenda de disponibilidade</h2><span class="calendar-month"><?= h($monthNames[(int) $month->format('n')] . ' ' . $month->format('Y')) ?></span></div><div class="month-controls"><a href="<?= h(pageUrl('detalhe', ['id' => $resource['id_recurso'], 'mes' => $month->modify('-1 month')->format('Y-m')])) ?>" aria-label="Mês anterior">‹</a><a href="<?= h(pageUrl('detalhe', ['id' => $resource['id_recurso'], 'mes' => $month->modify('+1 month')->format('Y-m')])) ?>" aria-label="Próximo mês">›</a></div></div>
                            <div class="calendar-grid"><?php foreach (['SEG','TER','QUA','QUI','SEX','SÁB','DOM'] as $weekday): ?><span class="weekday"><?= h($weekday) ?></span><?php endforeach; ?><?php for ($blank = 0; $blank < $firstWeekday; $blank++): ?><span class="calendar-day"></span><?php endfor; ?><?php for ($day = 1; $day <= $daysInMonth; $day++): ?><span class="calendar-day <?= isset($reservedDays[(string) $day]) ? 'has-booking' : '' ?>"><?= $day ?></span><?php endfor; ?></div>
                            <div class="calendar-legend"><i></i>Datas destacadas possuem reservas ativas.</div><div class="calendar-help"><strong>Como funciona a disponibilidade?</strong><p>As datas destacadas têm reservas ativas. Os horários são validados antes de salvar uma nova reserva.</p></div>
                        </section></div>
                <?php endif; ?>
            <?php endif; ?>
            <p class="demo-note">Quick Reservation · PHP e MySQL · A tela usa os dados existentes no banco conectado.</p>
        </div>
    </main>
</div>
</body>
</html>
