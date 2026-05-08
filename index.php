<?php
session_start();
require_once __DIR__ . '/inc/auth.php';

init_db();
generate_recurring_transactions();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    handle_post();
}

$page = $_GET['page'] ?? 'dashboard';
if (!is_logged_in() && !in_array($page, ['login', 'register'])) {
    $page = 'login';
}
if (is_logged_in() && in_array($page, ['login', 'register'])) {
    $page = 'dashboard';
}

function handle_post()
{
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'login':
            handle_login();
            break;
        case 'register':
            handle_register();
            break;
        case 'logout':
            handle_logout();
            break;
        case 'update_profile':
            handle_update_profile();
            break;
        case 'delete_account':
            handle_delete_account();
            break;
        case 'create_house':
            handle_create_house();
            break;
        case 'add_member':
            handle_add_member();
            break;
        case 'change_permission':
            handle_change_permission();
            break;
        case 'remove_member':
            handle_remove_member();
            break;
        case 'delete_house':
            handle_delete_house();
            break;
        case 'create_transaction':
            handle_create_transaction();
            break;
        case 'update_transaction':
            handle_update_transaction();
            break;
        case 'delete_transaction':
            handle_delete_transaction();
            break;
        case 'toggle_transaction':
            handle_toggle_transaction();
            break;
        default:
            redirect('dashboard');
    }
}

function redirect($page = 'dashboard', $params = [])
{
    $query = '';
    if (!empty($params)) {
        $query = '&' . http_build_query($params);
    }
    header('Location: ?page=' . $page . $query);
    exit;
}

function handle_login()
{
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        flash_set('error', 'Informe email e senha.');
        redirect('login');
    }
    $user = fetch_user_by_email($email);
    if (!$user || !password_verify($password, $user['password'])) {
        flash_set('error', 'Email ou senha inválidos.');
        redirect('login');
    }
    login_user($user);
    flash_set('success', 'Bem-vindo de volta, ' . h($user['name']) . '!');
    redirect('dashboard');
}

function handle_register()
{
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$name || !$email || !$password) {
        flash_set('error', 'Preencha todos os campos para cadastrar.');
        redirect('register');
    }
    if (fetch_user_by_email($email)) {
        flash_set('error', 'Este email já está registrado.');
        redirect('register');
    }
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_BCRYPT), date('c')]);
    $userId = $pdo->lastInsertId();
    login_user(['id' => $userId]);
    flash_set('success', 'Cadastro realizado com sucesso.');
    redirect('dashboard');
}

function handle_logout()
{
    session_destroy();
    header('Location: ?page=login');
    exit;
}

function handle_update_profile()
{
    ensure_logged_in();
    $user = current_user();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (!$name || !$email) {
        flash_set('error', 'Nome e email são obrigatórios.');
        redirect('profile');
    }
    $existing = fetch_user_by_email($email);
    if ($existing && $existing['id'] != $user['id']) {
        flash_set('error', 'Email já em uso por outro usuário.');
        redirect('profile');
    }
    global $pdo;
    $sql = 'UPDATE users SET name = ?, email = ?';
    $params = [$name, $email];
    if ($password) {
        $sql .= ', password = ?';
        $params[] = password_hash($password, PASSWORD_BCRYPT);
    }
    $sql .= ' WHERE id = ?';
    $params[] = $user['id'];
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    flash_set('success', 'Perfil atualizado com sucesso.');
    redirect('profile');
}

function handle_delete_account()
{
    ensure_logged_in();
    $user = current_user();
    global $pdo;
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    session_destroy();
    header('Location: ?page=login');
    exit;
}

function handle_create_house()
{
    ensure_logged_in();
    $user = current_user();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (!$name) {
        flash_set('error', 'Nome da casa é obrigatório.');
        redirect('dashboard');
    }
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO houses (name, description, created_by, created_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $description, $user['id'], date('c')]);
    $houseId = $pdo->lastInsertId();
    $stmt2 = $pdo->prepare('INSERT INTO house_members (house_id, user_id, role) VALUES (?, ?, ?)');
    $stmt2->execute([$houseId, $user['id'], 'admin']);
    flash_set('success', 'Casa criada com sucesso.');
    redirect('house', ['house_id' => $houseId]);
}

function handle_add_member()
{
    ensure_logged_in();
    $user = current_user();
    $houseId = intval($_POST['house_id'] ?? 0);
    $email = trim($_POST['member_email'] ?? '');
    $role = $_POST['role'] ?? 'viewer';
    if (!is_admin_of($houseId)) {
        flash_set('error', 'Somente administrador pode adicionar membros.');
        redirect('dashboard');
    }
    if (!$email) {
        flash_set('error', 'Email do membro é obrigatório.');
        redirect('house', ['house_id' => $houseId]);
    }
    $candidate = fetch_user_by_email($email);
    if (!$candidate) {
        flash_set('error', 'Usuário não encontrado. Ele precisa se cadastrar primeiro.');
        redirect('house', ['house_id' => $houseId]);
    }
    if (get_house_member($houseId, $candidate['id'])) {
        flash_set('error', 'Esse usuário já faz parte da casa.');
        redirect('house', ['house_id' => $houseId]);
    }
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO house_members (house_id, user_id, role) VALUES (?, ?, ?)');
    $stmt->execute([$houseId, $candidate['id'], $role]);
    flash_set('success', 'Membro adicionado com sucesso.');
    redirect('house', ['house_id' => $houseId]);
}

function handle_change_permission()
{
    ensure_logged_in();
    $houseId = intval($_POST['house_id'] ?? 0);
    $memberId = intval($_POST['member_id'] ?? 0);
    $role = $_POST['role'] ?? 'viewer';
    if (!is_admin_of($houseId)) {
        flash_set('error', 'Somente administrador pode alterar permissões.');
        redirect('dashboard');
    }
    if ($role !== 'admin' && $role !== 'editor' && $role !== 'viewer') {
        flash_set('error', 'Permissão inválida.');
        redirect('house', ['house_id' => $houseId]);
    }
    global $pdo;
    $stmt = $pdo->prepare('UPDATE house_members SET role = ? WHERE house_id = ? AND id = ?');
    $stmt->execute([$role, $houseId, $memberId]);
    flash_set('success', 'Permissão alterada.');
    redirect('house', ['house_id' => $houseId]);
}

function handle_remove_member()
{
    ensure_logged_in();
    $houseId = intval($_POST['house_id'] ?? 0);
    $memberId = intval($_POST['member_id'] ?? 0);
    if (!is_admin_of($houseId)) {
        flash_set('error', 'Somente administrador pode remover membros.');
        redirect('dashboard');
    }
    global $pdo;
    $stmt = $pdo->prepare('DELETE FROM house_members WHERE house_id = ? AND id = ?');
    $stmt->execute([$houseId, $memberId]);
    flash_set('success', 'Membro removido.');
    redirect('house', ['house_id' => $houseId]);
}

function handle_delete_house()
{
    ensure_logged_in();
    $houseId = intval($_POST['house_id'] ?? 0);
    if (!is_admin_of($houseId)) {
        flash_set('error', 'Somente administrador pode excluir a casa.');
        redirect('dashboard');
    }
    global $pdo;
    $stmt = $pdo->prepare('DELETE FROM houses WHERE id = ?');
    $stmt->execute([$houseId]);
    flash_set('success', 'Casa excluída.');
    redirect('dashboard');
}

function handle_create_transaction()
{
    ensure_logged_in();
    $houseId = intval($_POST['house_id'] ?? 0);
    if (!in_array(user_house_role($houseId), ['admin', 'editor'])) {
        flash_set('error', 'Você não tem permissão para criar transações.');
        redirect('house', ['house_id' => $houseId]);
    }
    $type = $_POST['type'] ?? 'expense';
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $dueDate = $_POST['due_date'] ?? null;
    $status = $_POST['status'] ?? 'pending';
    $recurrenceInterval = $_POST['recurrence_interval'] ?? 'none';
    $recurrenceCount = intval($_POST['recurrence_count'] ?? 1);
    if ($recurrenceCount < 1) $recurrenceCount = 1;
    if (!$category || !$amount || !$date) {
        flash_set('error', 'Categoria, valor e data são obrigatórios.');
        redirect('house', ['house_id' => $houseId]);
    }
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO transactions (house_id, created_by, type, category, description, amount, status, date, due_date, recurrence_interval, recurrence_count, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$houseId, current_user()['id'], $type, $category, $description, $amount, $status, $date, $dueDate ?: null, $recurrenceInterval, $recurrenceCount, date('c')]);
    generate_recurring_transactions();
    flash_set('success', 'Transação criada.');
    redirect('house', ['house_id' => $houseId]);
}

function handle_update_transaction()
{
    ensure_logged_in();
    $houseId = intval($_POST['house_id'] ?? 0);
    $transactionId = intval($_POST['transaction_id'] ?? 0);
    if (!in_array(user_house_role($houseId), ['admin', 'editor'])) {
        flash_set('error', 'Você não tem permissão para editar transações.');
        redirect('house', ['house_id' => $houseId]);
    }
    $type = $_POST['type'] ?? 'expense';
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $dueDate = $_POST['due_date'] ?? null;
    $status = $_POST['status'] ?? 'pending';
    $recurrenceInterval = $_POST['recurrence_interval'] ?? 'none';
    $recurrenceCount = intval($_POST['recurrence_count'] ?? 1);
    if ($recurrenceCount < 1) $recurrenceCount = 1;
    if (!$category || !$amount || !$date) {
        flash_set('error', 'Categoria, valor e data são obrigatórios.');
        redirect('house', ['house_id' => $houseId]);
    }
    global $pdo;
    $stmt = $pdo->prepare('UPDATE transactions SET type = ?, category = ?, description = ?, amount = ?, status = ?, date = ?, due_date = ?, recurrence_interval = ?, recurrence_count = ? WHERE id = ? AND house_id = ?');
    $stmt->execute([$type, $category, $description, $amount, $status, $date, $dueDate ?: null, $recurrenceInterval, $recurrenceCount, $transactionId, $houseId]);
    flash_set('success', 'Transação atualizada.');
    redirect('house', ['house_id' => $houseId]);
}

function handle_delete_transaction()
{
    ensure_logged_in();
    $houseId = intval($_POST['house_id'] ?? 0);
    $transactionId = intval($_POST['transaction_id'] ?? 0);
    if (!in_array(user_house_role($houseId), ['admin', 'editor'])) {
        flash_set('error', 'Você não tem permissão para excluir transações.');
        redirect('house', ['house_id' => $houseId]);
    }
    global $pdo;
    $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ? AND house_id = ?');
    $stmt->execute([$transactionId, $houseId]);
    flash_set('success', 'Transação excluída.');
    redirect('house', ['house_id' => $houseId]);
}

function handle_toggle_transaction()
{
    ensure_logged_in();
    $houseId = intval($_POST['house_id'] ?? 0);
    $transactionId = intval($_POST['transaction_id'] ?? 0);
    if (!in_array(user_house_role($houseId), ['admin', 'editor'])) {
        flash_set('error', 'Você não tem permissão para alterar status.');
        redirect('house', ['house_id' => $houseId]);
    }
    global $pdo;
    $transaction = fetch_transaction($transactionId);
    if (!$transaction || $transaction['house_id'] != $houseId) {
        flash_set('error', 'Transação não encontrada.');
        redirect('house', ['house_id' => $houseId]);
    }
    $newStatus = $transaction['status'] === 'paid' ? 'pending' : 'paid';
    $stmt = $pdo->prepare('UPDATE transactions SET status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $transactionId]);
    $statusLabel = $newStatus === 'paid' ? 'Pago' : 'Pendente';
    flash_set('success', 'Status atualizado para ' . $statusLabel . '.');
    redirect('house', ['house_id' => $houseId]);
}

function render_header()
{
    $user = current_user();
    echo '<div class="header">';
    echo '<div><h1>Dinero</h1><p>Gerencie casas, transações e permissões em um só lugar.</p></div>';
    echo '<div class="nav-links">';
    if ($user) {
        echo '<a href="?page=dashboard">Dashboard</a>';
        echo '<a href="?page=profile">Meu Perfil</a>';
        echo '<form method="post" style="display:inline;"><input type="hidden" name="action" value="logout"><button type="submit" class="btn-secondary2">Sair</button></form>';
    } else {
        echo '<a href="?page=login">Login</a>';
        echo '<a href="?page=register">Cadastrar</a>';
    }
    echo '</div></div>';
}

function render_flash()
{
    $flash = flash_get();
    if (!$flash) {
        return;
    }
    $class = $flash['type'] === 'success' ? 'alert-success' : 'alert-error';
    echo '<div class="alert ' . $class . '">' . h($flash['message']) . '</div>';
}

function render_page()
{
    global $page;
    render_header();
    render_flash();
    switch ($page) {
        case 'login':
            render_login();
            break;
        case 'register':
            render_register();
            break;
        case 'dashboard':
            render_dashboard();
            break;
        case 'profile':
            render_profile();
            break;
        case 'house':
            render_house();
            break;
        case 'house_details':
            render_house_details();
            break;
        default:
            echo '<div class="card"><h2>Página não encontrada</h2><p>Use a navegação para continuar.</p></div>';
    }
}

function render_login()
{
    echo '<div class="container"><div class="card"><h2>Entrar</h2><form method="post">';
    echo '<input type="hidden" name="action" value="login">';
    echo '<label>Email<input type="email" name="email" required></label>';
    echo '<label>Senha<input type="password" name="password" required></label>';
    echo '<button type="submit">Entrar</button>';
    echo '</form></div></div>';
}

function render_register()
{
    echo '<div class="container"><div class="card"><h2>Cadastrar</h2><form method="post">';
    echo '<input type="hidden" name="action" value="register">';
    echo '<label>Nome<input type="text" name="name" required></label>';
    echo '<label>Email<input type="email" name="email" required></label>';
    echo '<label>Senha<input type="password" name="password" required></label>';
    echo '<button type="submit">Criar conta</button>';
    echo '</form></div></div>';
}

function render_dashboard()
{
    ensure_logged_in();
    $user = current_user();
    $houses = user_houses($user['id']);
    $house_summaries = user_aggregate_summary($user['id']);
    echo '<div class="container"><div class="card"><h2>Bem-vindo, ' . h($user['name']) . '</h2>';
    echo '<p>Veja suas casas e controle financeiro em um só painel.</p>';
    echo '<div class="grid-2"><div class="card">';
    echo '<div class="card-list">';
    if (!$houses) {
        echo '<div class="card-list-item"><p>Você ainda não faz parte de nenhuma casa.</p></div>';
    }
    foreach ($houses as $house) {
        $summary = array_filter($house_summaries, fn($s) => $s['house_id'] == $house['id']);
        $summary = reset($summary) ?: ['balance' => 0, 'income' => 0, 'expense' => 0, 'caixinha' => 0, 'pending_income' => 0, 'pending_expense' => 0, 'pending_caixinha' => 0];
        echo '<div class="card-list-item">';
        echo '<h3>' . h($house['name']) . '</h3>';
        echo '<small>' . h($house['description']) . '</small>';
        echo '</div>';
        echo '<div class="actions" style="margin-top:12px;"><span class="badge">' . h(strtoupper($house['role'])) . '</span>';
        echo '<a class="action-link" href="?page=house&house_id=' . $house['id'] . '">Abrir</a></div>';
        echo '</div>';
        echo '<div class="summary-grid" style="margin-top:12px;">';
        echo '<div class="summary-card"><small>Saldo</small><strong>R$ ' . number_format($summary['balance'], 2, ',', '.') . '</strong></div>';
        echo '<div class="summary-card"><small>Receita</small><strong>R$ ' . number_format($summary['income'], 2, ',', '.') . '</strong></div>';
        echo '<div class="summary-card"><small>Despesa</small><strong>R$ ' . number_format($summary['expense_display'] ?? $summary['expense'], 2, ',', '.') . '</strong></div>';
        echo '<div class="summary-card"><small>Caixinha</small><strong>R$ ' . number_format($summary['caixinha'], 2, ',', '.') . '</strong></div>';
        echo '<div class="summary-card"><small>A receber</small><strong>R$ ' . number_format($summary['pending_income'], 2, ',', '.') . '</strong></div>';
        echo '<div class="summary-card"><small>A pagar</small><strong>R$ ' . number_format($summary['pending_expense'], 2, ',', '.') . '</strong></div>';
        echo '</div>';
    }
    echo '</div></div>';
    echo '<div class="card"><h2>Criar nova casa</h2><form method="post">';
    echo '<input type="hidden" name="action" value="create_house">';
    echo '<label>Nome da casa<input type="text" name="name" required></label>';
    echo '<label>Descrição<textarea name="description" rows="4"></textarea></label>';
    echo '<button type="submit">Criar casa</button>';
    echo '</form></div></div></div>';
}

function render_house_details()
{
    ensure_logged_in();
    $houseId = intval($_GET['house_id'] ?? 0);
    $house = get_house($houseId);
    if (!$house) {
        echo '<div class="container"><div class="card"><h2>Casa não encontrada</h2></div></div>';
        return;
    }
    $role = user_house_role($houseId);
    if (!$role) {
        flash_set('error', 'Você não tem acesso a esta casa.');
        redirect('dashboard');
    }
    $members = house_members($houseId);
    echo '<div class="container"><div class="card">';
    echo '<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">';
    echo '<div><h2>Detalhes da casa</h2><p style="margin:6px 0 0;">' . h($house['description']) . '</p></div>';
    echo '<a class="action-link" href="?page=house&house_id=' . $houseId . '">Voltar</a>';
    echo '</div>';
    echo '<p><strong>Seu papel:</strong> ' . h(ucfirst($role)) . '</p>';
    echo '<div class="panel"><h2>Membros</h2>';
    echo '<div class="table-responsive"><table class="table-list"><thead><tr><th>Nome</th><th>Email</th><th>Papel</th><th>Ações</th></tr></thead><tbody>';
    foreach ($members as $member) {
        echo '<tr>';
        echo '<td>' . h($member['name']) . '</td>';
        echo '<td>' . h($member['email']) . '</td>';
        echo '<td>' . h(ucfirst($member['role'])) . '</td>';
        echo '<td>';
        if ($role === 'admin' && $member['user_id'] !== current_user()['id']) {
            echo '<form method="post" style="display:inline; margin-right:10px;">';
            echo '<input type="hidden" name="action" value="change_permission">';
            echo '<input type="hidden" name="house_id" value="' . $houseId . '">';
            echo '<input type="hidden" name="member_id" value="' . $member['id'] . '">';
            echo '<select name="role" onchange="this.form.submit()">';
            echo '<option value="admin"' . ($member['role'] === 'admin' ? ' selected' : '') . '>Admin</option>';
            echo '<option value="editor"' . ($member['role'] === 'editor' ? ' selected' : '') . '>Editor</option>';
            echo '<option value="viewer"' . ($member['role'] === 'viewer' ? ' selected' : '') . '>Visualizador</option>';
            echo '</select></form>';
            echo '<form method="post" style="display:inline;">';
            echo '<input type="hidden" name="action" value="remove_member">';
            echo '<input type="hidden" name="house_id" value="' . $houseId . '">';
            echo '<input type="hidden" name="member_id" value="' . $member['id'] . '">';
            echo '<button type="button" class="btn-secondary confirm-action" data-confirm="Remover este membro da casa?">Remover</button>';
            echo '</form>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    if ($role === 'admin') {
        echo '<form method="post" style="margin-top:18px;">';
        echo '<input type="hidden" name="action" value="add_member">';
        echo '<input type="hidden" name="house_id" value="' . $houseId . '">';
        echo '<label>Email do membro<input type="email" name="member_email" required></label>';
        echo '<label>Papel<select name="role"><option value="viewer">Visualizador</option><option value="editor">Editor</option><option value="admin">Admin</option></select></label>';
        echo '<button type="submit">Adicionar membro</button>';
        echo '</form>';
        echo '<div style="margin-top:18px; padding:14px; background:#f8fafc; border-radius:14px;">';
        echo '<form method="post">';
        echo '<input type="hidden" name="action" value="delete_house">';
        echo '<input type="hidden" name="house_id" value="' . $houseId . '">';
        echo '<button type="button" class="btn-primary confirm-action" data-confirm="Excluir esta casa e todos os dados dela?" style="width:100%;">Excluir casa</button>';
        echo '</form>';
        echo '</div>';
    }
    echo '</div></div></div>';
}

function render_profile()
{
    ensure_logged_in();
    $user = current_user();
    echo '<div class="container"><div class="card"><h2>Meu perfil</h2><form method="post">';
    echo '<input type="hidden" name="action" value="update_profile">';
    echo '<label>Nome<input type="text" name="name" value="' . h($user['name']) . '" required></label>';
    echo '<label>Email<input type="email" name="email" value="' . h($user['email']) . '" required></label>';
    echo '<label>Nova senha<input type="password" name="password" placeholder="Deixe vazio para manter"></label>';
    echo '<button type="submit">Salvar perfil</button>';
    echo '</form></div>';
    echo '<div class="card" style="margin-top:18px;"><h2>Excluir conta</h2><p>Essa ação remove sua conta e sua participação em todas as casas.</p>';
    echo '<form method="post"><input type="hidden" name="action" value="delete_account">';
    echo '<button type="button" class="btn-primary confirm-action" data-confirm="Excluir minha conta permanentemente?">Excluir conta</button>';
    echo '</form></div></div>';
}

function render_house()
{
    ensure_logged_in();
    $houseId = intval($_GET['house_id'] ?? 0);
    $house = get_house($houseId);
    if (!$house) {
        echo '<div class="container"><div class="card"><h2>Casa não encontrada</h2></div></div>';
        return;
    }
    $role = user_house_role($houseId);
    if (!$role) {
        flash_set('error', 'Você não tem acesso a esta casa.');
        redirect('dashboard');
    }
    $filters = [
        'type' => $_GET['type'] ?? '',
        'status' => $_GET['status'] ?? '',
        'month' => $_GET['month'] ?? date('Y-m'),
        'query' => trim($_GET['query'] ?? ''),
    ];
    $transactions = house_transactions($houseId, $filters);
    $summary = transaction_summary($houseId, $filters);
    $members = house_members($houseId);
    $totalCaixinha = total_caixinha($houseId);
    echo '<div class="container"><div class="grid-2 house-grid"><div class="card">';
    echo '<div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;"><h2 style="margin:0;">' . h($house['name']) . '</h2>';
    echo '<small style="color:#64748b;">Caixinha total: R$ ' . number_format($totalCaixinha, 2, ',', '.') . '</small>';
    echo '<a class="action-link" href="?page=house_details&house_id=' . $houseId . '" style="font-size:0.95rem;">Detalhes</a></div>';
    echo '<p>' . h($house['description']) . '</p>';
    echo '<div class="summary-grid">';
    echo '<div class="summary-card"><small>Receitas</small><strong>R$ ' . number_format($summary['income'], 2, ',', '.') . '</strong></div>';
    echo '<div class="summary-card"><small>Despesas</small><strong>R$ ' . number_format($summary['expense_display'] ?? $summary['expense'], 2, ',', '.') . '</strong></div>';
    echo '<div class="summary-card"><small>Caixinha</small><strong>R$ ' . number_format($summary['caixinha'], 2, ',', '.') . '</strong></div>';
    echo '<div class="summary-card"><small>Saldo</small><strong>R$ ' . number_format($summary['balance'], 2, ',', '.') . '</strong></div>';
    echo '<div class="summary-card"><small>Receitas pendentes</small><strong>R$ ' . number_format($summary['pending_income'], 2, ',', '.') . '</strong></div>';
    echo '<div class="summary-card"><small>Despesas pendentes</small><strong>R$ ' . number_format($summary['pending_expense'], 2, ',', '.') . '</strong></div>';
    echo '</div>';
    echo '<div class="panel" style="margin-top:18px;"><h2>Filtros</h2><form method="get" class="filters">';
    echo '<input type="hidden" name="page" value="house">';
    echo '<input type="hidden" name="house_id" value="' . $houseId . '">';
    echo '<label>Tipo<select name="type"><option value="">Todos</option><option value="income"' . ($filters['type'] === 'income' ? ' selected' : '') . '>Receita</option><option value="expense"' . ($filters['type'] === 'expense' ? ' selected' : '') . '>Despesa</option><option value="caixinha"' . ($filters['type'] === 'caixinha' ? ' selected' : '') . '>Caixinha - Entrada</option><option value="caixinha_retirada"' . ($filters['type'] === 'caixinha_retirada' ? ' selected' : '') . '>Caixinha - Retirada</option></select></label>';
    echo '<label>Status<select name="status"><option value="">Todos</option><option value="paid"' . ($filters['status'] === 'paid' ? ' selected' : '') . '>Pago</option><option value="pending"' . ($filters['status'] === 'pending' ? ' selected' : '') . '>Pendente</option></select></label>';
    echo '<label>Mês<input type="month" name="month" value="' . h($filters['month']) . '"></label>';
    echo '<label>Busca<input type="text" name="query" value="' . h($filters['query']) . '" placeholder="Descrição ou categoria"></label>';
    echo '<div class="actions"><button type="submit">Aplicar</button><a class="action-link" href="?page=house&house_id=' . $houseId . '">Limpar</a></div>';
    echo '</form></div>';
    echo '<div class="panel" style="margin-top:18px;"><h2>Transações</h2>'; 
    if ($transactions) {
        echo '<div class="table-responsive"><table class="table-list"><thead><tr><th>Categoria</th><th>Valor</th><th>Status</th><th>Data</th><th>Tipo</th><th>Ações</th></tr></thead><tbody>';
        foreach ($transactions as $transaction) {
            echo '<tr>';
            $displayDate = date('d/m/Y', strtotime($transaction['date']));
            $statusLabel = $transaction['status'] === 'paid' ? 'Pago' : 'Pendente';
            $typeLabel = $transaction['type'] === 'income' ? 'Receita' : ($transaction['type'] === 'expense' ? 'Despesa' : ($transaction['type'] === 'caixinha' ? 'Caixinha - Entrada' : 'Caixinha - Retirada'));
            echo '<td>' . h($transaction['category']) . '<br><small>' . h($transaction['description']) . '</small></td>';
            echo '<td>R$ ' . number_format($transaction['amount'], 2, ',', '.') . '</td>';
            echo '<td><span class="status-' . h($transaction['status']) . '">' . h($statusLabel) . '</span></td>';
            echo '<td>' . h($displayDate) . '</td>';
            echo '<td>' . h($typeLabel) . '</td>';
            echo '<td>';
            echo '<form method="post" style="display:inline; margin-right:6px;">
            <input type="hidden" name="action" value="toggle_transaction">
            <input type="hidden" name="house_id" value="' . $houseId . '">
            <input type="hidden" name="transaction_id" value="' . $transaction['id'] . '">
            <button type="submit" class="btn-secondary" title="' . ($transaction['status'] === 'paid' ? 'Marcar como pendente' : 'Marcar como pago') . '">' . ($transaction['status'] === 'paid' ? '⏳' : '✔️') . '</button>
            </form>';
            if (in_array($role, ['admin', 'editor'])) {
                echo '<a class="action-link" href="?page=house&house_id=' . $houseId . '&edit_transaction=' . $transaction['id'] . '" title="Editar">✏️</a>';
                echo '<form method="post" style="display:inline; margin-left:6px;">
                <input type="hidden" name="action" value="delete_transaction">
                <input type="hidden" name="house_id" value="' . $houseId . '">
                <input type="hidden" name="transaction_id" value="' . $transaction['id'] . '">
                <button type="button" class="btn-secondary confirm-action" data-confirm="Excluir esta transação?" title="Excluir">🗑️</button>
                </form>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    } else {
        echo '<p>Nenhuma transação encontrada com os filtros atuais.</p>';
    }
    echo '</div></div>';
    $editTransaction = null;
    if (!empty($_GET['edit_transaction'])) {
        $transactionId = intval($_GET['edit_transaction']);
        $candidate = fetch_transaction($transactionId);
        if ($candidate && $candidate['house_id'] === $houseId) {
            $editTransaction = $candidate;
        } else {
            flash_set('error', 'Transação não encontrada.');
            redirect('house', ['house_id' => $houseId]);
        }
    }

    echo '<div class="panel"><h2>' . ($editTransaction ? 'Editar transação' : 'Nova transação') . '</h2><form method="post">';
    if (in_array($role, ['admin', 'editor'])) {
        if ($editTransaction) {
            echo '<input type="hidden" name="action" value="update_transaction">';
            echo '<input type="hidden" name="transaction_id" value="' . $editTransaction['id'] . '">';
        } else {
            echo '<input type="hidden" name="action" value="create_transaction">';
        }
        echo '<input type="hidden" name="house_id" value="' . $houseId . '">';
        echo '<label>Tipo<select name="type"><option value="income"' . ($editTransaction && $editTransaction['type'] === 'income' ? ' selected' : '') . '>Receita</option><option value="expense"' . ($editTransaction ? ($editTransaction['type'] === 'expense' ? ' selected' : '') : ' selected') . '>Despesa</option><option value="caixinha"' . ($editTransaction && $editTransaction['type'] === 'caixinha' ? ' selected' : '') . '>Caixinha - Entrada</option><option value="caixinha_retirada"' . ($editTransaction && $editTransaction['type'] === 'caixinha_retirada' ? ' selected' : '') . '>Caixinha - Retirada</option></select></label>';
        echo '<label>Categoria<input type="text" name="category" value="' . ($editTransaction ? h($editTransaction['category']) : '') . '" required></label>';
        echo '<label>Descrição<textarea name="description" rows="3">' . ($editTransaction ? h($editTransaction['description']) : '') . '</textarea></label>';
        echo '<label>Valor<input type="number" step="0.01" name="amount" value="' . ($editTransaction ? h($editTransaction['amount']) : '') . '" required></label>';
        echo '<label>Data<input type="date" name="date" value="' . ($editTransaction ? h($editTransaction['date']) : date('Y-m-d')) . '" required></label>';
        echo '<label>Vencimento<input type="date" name="due_date" value="' . ($editTransaction && $editTransaction['due_date'] ? h($editTransaction['due_date']) : '') . '"></label>';
        echo '<label>Intervalo de recorrência<select name="recurrence_interval"><option value="none"' . ($editTransaction && $editTransaction['recurrence_interval'] === 'none' ? ' selected' : '') . '>Sem recorrência</option><option value="daily"' . ($editTransaction && $editTransaction['recurrence_interval'] === 'daily' ? ' selected' : '') . '>Diário</option><option value="weekly"' . ($editTransaction && $editTransaction['recurrence_interval'] === 'weekly' ? ' selected' : '') . '>Semanal</option><option value="monthly"' . ($editTransaction && $editTransaction['recurrence_interval'] === 'monthly' ? ' selected' : '') . '>Mensal</option><option value="yearly"' . ($editTransaction && $editTransaction['recurrence_interval'] === 'yearly' ? ' selected' : '') . '>Anual</option></select></label>';
        echo '<label>Quantidade de repetições<input type="number" name="recurrence_count" min="1" value="' . ($editTransaction ? h($editTransaction['recurrence_count']) : '1') . '"></label>';
        echo '<label>Status<select name="status"><option value="pending"' . ($editTransaction && $editTransaction['status'] === 'pending' ? ' selected' : '') . '>Pendente</option><option value="paid"' . ($editTransaction && $editTransaction['status'] === 'paid' ? ' selected' : '') . '>Pago</option></select></label>';
        echo '<div class="actions">';
        echo '<button type="submit">' . ($editTransaction ? 'Atualizar transação' : 'Salvar transação') . '</button>';
        if ($editTransaction) {
            echo '<a class="action-link" href="?page=house&house_id=' . $houseId . '">Cancelar</a>';
        }
        echo '</div>';
    } else {
        echo '<p>Você não tem permissão para criar transações nesta casa.</p>';
    }
    echo '</form></div>';
    echo '</div></div></div>';
}

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dinero | Controle Financeiro</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="icon" type="image/png" href="assets/salary 2.png">
    <link rel="apple-touch-icon" href="assets/salary 2.png">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#007bff">
</head>
<body>
<?php render_page(); ?>
<div class="modal-backdrop confirm-modal" role="dialog" aria-modal="true">
    <div class="modal">
        <p class="confirm-modal-message">Tem certeza?</p>
        <div class="actions" style="margin-top:16px; gap:10px; display:flex; flex-wrap:wrap;">
            <button type="button" class="confirm-modal-submit">Confirmar</button>
            <button type="button" class="confirm-modal-cancel">Cancelar</button>
        </div>
    </div>
</div>
<script src="assets/script.js"></script>
</body>
</html>
