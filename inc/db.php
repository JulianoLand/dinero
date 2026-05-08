<?php
$dbFile = __DIR__ . '/../data/dinero.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

function init_db()
{
    global $pdo;
    $sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS houses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS house_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    house_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('admin','editor','viewer')),
    UNIQUE(house_id, user_id),
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    house_id INTEGER NOT NULL,
    created_by INTEGER,
    type TEXT NOT NULL,
    category TEXT NOT NULL,
    description TEXT,
    amount REAL NOT NULL,
    status TEXT NOT NULL,
    date TEXT NOT NULL,
    due_date TEXT,
    recurrence_interval TEXT DEFAULT 'none',
    recurrence_count INTEGER DEFAULT 1,
    is_recurring_instance INTEGER DEFAULT 0,
    original_transaction_id INTEGER,
    created_at TEXT NOT NULL,
    FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (original_transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
);
SQL;
    $pdo->exec($sql);
    repair_transactions_table();
}

function repair_transactions_table()
{
    global $pdo;
    try {
        // Verificar se a tabela transactions existe
        $tableExists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='transactions'")->fetch();
        if (!$tableExists) {
            return; // Tabela não existe, não precisa reparar
        }
        
        // Obter o schema atual da tabela
        $schema = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='transactions'")->fetchColumn();
        
        // Se a tabela tem CHECK constraint, precisa recriar
        if ($schema && strpos($schema, 'CHECK') !== false) {
            // Desabilitar foreign keys temporariamente
            $pdo->exec('PRAGMA foreign_keys = OFF');
            
            // Rename tabela antiga
            $pdo->exec('ALTER TABLE transactions RENAME TO transactions_backup');
            
            // Criar nova tabela sem constraints
            $pdo->exec('CREATE TABLE transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                house_id INTEGER NOT NULL,
                created_by INTEGER,
                type TEXT NOT NULL,
                category TEXT NOT NULL,
                description TEXT,
                amount REAL NOT NULL,
                status TEXT NOT NULL,
                date TEXT NOT NULL,
                due_date TEXT,
                recurrence_interval TEXT DEFAULT "none",
                recurrence_count INTEGER DEFAULT 1,
                is_recurring_instance INTEGER DEFAULT 0,
                original_transaction_id INTEGER,
                created_at TEXT NOT NULL,
                FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (original_transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
            )');
            
            // Copiar dados da tabela antiga
            $pdo->exec('INSERT INTO transactions SELECT * FROM transactions_backup');
            
            // Deletar tabela de backup
            $pdo->exec('DROP TABLE transactions_backup');
            
            // Reabilitar foreign keys
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    } catch (Exception $e) {
        // Se algo der errado, apenas registrar (não quebra a aplicação)
        error_log('Database repair failed: ' . $e->getMessage());
    }
}

function fetch_user_by_email($email)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetch_user_by_id($id)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function user_houses($userId)
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT h.*, m.role FROM houses h
         JOIN house_members m ON h.id = m.house_id
         WHERE m.user_id = ?
         ORDER BY h.name'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_house($houseId)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM houses WHERE id = ?');
    $stmt->execute([$houseId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_house_member($houseId, $userId)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM house_members WHERE house_id = ? AND user_id = ?');
    $stmt->execute([$houseId, $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function house_members($houseId)
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT m.*, u.name, u.email FROM house_members m
         JOIN users u ON m.user_id = u.id
         WHERE m.house_id = ?
         ORDER BY u.name'
    );
    $stmt->execute([$houseId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function house_transactions($houseId, $filters = [])
{
    global $pdo;
    $where = ['house_id = :house_id'];
    $params = ['house_id' => $houseId];
    if (!empty($filters['type'])) {
        $where[] = 'type = :type';
        $params['type'] = $filters['type'];
    }
    if (!empty($filters['status'])) {
        $where[] = 'status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['month'])) {
        $where[] = "strftime('%Y-%m', date) = :month";
        $params['month'] = $filters['month'];
    }
    if (!empty($filters['query'])) {
        $where[] = '(description LIKE :query OR category LIKE :query)';
        $params['query'] = '%' . $filters['query'] . '%';
    }
    $sql = 'SELECT t.*, u.name AS creator_name FROM transactions t
            JOIN users u ON t.created_by = u.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY date DESC, created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function transaction_summary($houseId, $filters = [])
{
    global $pdo;
    $where = ['house_id = :house_id'];
    $params = ['house_id' => $houseId];
    if (!empty($filters['type'])) {
        $where[] = 'type = :type';
        $params['type'] = $filters['type'];
    }
    if (!empty($filters['status'])) {
        $where[] = 'status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['month'])) {
        $where[] = "strftime('%Y-%m', date) = :month";
        $params['month'] = $filters['month'];
    } else {
        // Default to current month for summary
        $where[] = "strftime('%Y-%m', date) = :month";
        $params['month'] = date('Y-m');
    }
    if (!empty($filters['query'])) {
        $where[] = '(description LIKE :query OR category LIKE :query)';
        $params['query'] = '%' . $filters['query'] . '%';
    }
    $stmt = $pdo->prepare('SELECT
            SUM(CASE WHEN type = "income" AND status = "paid" THEN amount ELSE 0 END) AS paid_income,
            SUM(CASE WHEN type = "expense" AND status = "paid" THEN amount ELSE 0 END) AS paid_expense,
            SUM(CASE WHEN type = "caixinha" AND status = "paid" THEN amount ELSE 0 END) AS paid_caixinha,
            SUM(CASE WHEN type = "caixinha_retirada" AND status = "paid" THEN amount ELSE 0 END) AS paid_caixinha_retirada,
            SUM(CASE WHEN status = "pending" AND type = "expense" THEN amount ELSE 0 END) AS pending_expense,
            SUM(CASE WHEN status = "pending" AND type = "income" THEN amount ELSE 0 END) AS pending_income,
            SUM(CASE WHEN status = "pending" AND type IN ("caixinha", "caixinha_retirada") THEN 
                CASE WHEN type = "caixinha" THEN amount ELSE -amount END 
            ELSE 0 END) AS pending_caixinha
        FROM transactions WHERE ' . implode(' AND ', $where)
    );
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    $caixinha_total = ($summary['paid_caixinha'] ?: 0) - ($summary['paid_caixinha_retirada'] ?: 0);
    return [
        'income' => $summary['paid_income'] ?: 0,
        'expense' => $summary['paid_expense'] ?: 0,
        'caixinha' => $caixinha_total,
        'pending_income' => $summary['pending_income'] ?: 0,
        'pending_expense' => $summary['pending_expense'] ?: 0,
        'pending_caixinha' => $summary['pending_caixinha'] ?: 0,
        'balance' => ($summary['paid_income'] ?: 0) - ($summary['paid_expense'] ?: 0) - $caixinha_total,
    ];
}

function user_aggregate_summary($userId)
{
    global $pdo;
    $currentMonth = date('Y-m');
    $stmt = $pdo->prepare('SELECT
            h.id, h.name,
            SUM(CASE WHEN t.type = "income" AND t.status = "paid" AND strftime(\'%Y-%m\', t.date) = :month THEN t.amount ELSE 0 END) AS paid_income,
            SUM(CASE WHEN t.type = "expense" AND t.status = "paid" AND strftime(\'%Y-%m\', t.date) = :month THEN t.amount ELSE 0 END) AS paid_expense,
            SUM(CASE WHEN t.type = "caixinha" AND t.status = "paid" AND strftime(\'%Y-%m\', t.date) = :month THEN t.amount ELSE 0 END) AS paid_caixinha,
            SUM(CASE WHEN t.type = "caixinha_retirada" AND t.status = "paid" AND strftime(\'%Y-%m\', t.date) = :month THEN t.amount ELSE 0 END) AS paid_caixinha_retirada,
            SUM(CASE WHEN t.status = "pending" AND t.type = "expense" AND strftime(\'%Y-%m\', t.date) = :month THEN t.amount ELSE 0 END) AS pending_expense,
            SUM(CASE WHEN t.status = "pending" AND t.type = "income" AND strftime(\'%Y-%m\', t.date) = :month THEN t.amount ELSE 0 END) AS pending_income,
            SUM(CASE WHEN t.status = "pending" AND t.type IN ("caixinha", "caixinha_retirada") AND strftime(\'%Y-%m\', t.date) = :month THEN 
                CASE WHEN t.type = "caixinha" THEN t.amount ELSE -t.amount END 
            ELSE 0 END) AS pending_caixinha
        FROM houses h
        JOIN house_members m ON h.id = m.house_id
        LEFT JOIN transactions t ON h.id = t.house_id
        WHERE m.user_id = :user_id
        GROUP BY h.id, h.name
        ORDER BY h.name'
    );
    $stmt->execute(['user_id' => $userId, 'month' => $currentMonth]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $summaries = [];
    foreach ($results as $row) {
        $caixinha_total = ($row['paid_caixinha'] ?: 0) - ($row['paid_caixinha_retirada'] ?: 0);
        $summaries[] = [
            'house_id' => $row['id'],
            'house_name' => $row['name'],
            'income' => $row['paid_income'] ?: 0,
            'expense' => $row['paid_expense'] ?: 0,
            'caixinha' => $caixinha_total,
            'pending_income' => $row['pending_income'] ?: 0,
            'pending_expense' => $row['pending_expense'] ?: 0,
            'pending_caixinha' => $row['pending_caixinha'] ?: 0,
            'balance' => ($row['paid_income'] ?: 0) - ($row['paid_expense'] ?: 0) - $caixinha_total,
        ];
    }
    return $summaries;
}

function fetch_transaction($transactionId)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
    $stmt->execute([$transactionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function total_caixinha($houseId)
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT 
            SUM(CASE WHEN type = "caixinha" AND status = "paid" THEN amount ELSE 0 END) AS entrada,
            SUM(CASE WHEN type = "caixinha_retirada" AND status = "paid" THEN amount ELSE 0 END) AS retirada
        FROM transactions WHERE house_id = ?');
    $stmt->execute([$houseId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $entrada = $result['entrada'] ?: 0;
    $retirada = $result['retirada'] ?: 0;
    return $entrada - $retirada;
}

function generate_recurring_transactions()
{
    global $pdo;
    
    // Buscar transações recorrentes que ainda não foram totalmente expandidas
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE recurrence_interval != "none" AND recurrence_count > 0 AND is_recurring_instance = 0');
    $stmt->execute();
    $recurring = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($recurring as $trans) {
        $currentDate = new DateTime($trans['date']);
        $dueDate = $trans['due_date'] ? new DateTime($trans['due_date']) : null;
        $interval = $trans['recurrence_interval'];
        $count = $trans['recurrence_count'];
        
        // Gerar as instâncias seguintes
        for ($i = 1; $i < $count; $i++) {
            $newDate = clone $currentDate;
            $newDueDate = null;
            
            switch ($interval) {
                case 'daily':
                    $newDate->modify('+' . $i . ' days');
                    break;
                case 'weekly':
                    $newDate->modify('+' . $i . ' weeks');
                    break;
                case 'monthly':
                    $newDate->modify('+' . $i . ' months');
                    break;
                case 'yearly':
                    $newDate->modify('+' . $i . ' years');
                    break;
            }
            
            if ($dueDate) {
                $newDueDate = clone $dueDate;
                switch ($interval) {
                    case 'daily':
                        $newDueDate->modify('+' . $i . ' days');
                        break;
                    case 'weekly':
                        $newDueDate->modify('+' . $i . ' weeks');
                        break;
                    case 'monthly':
                        $newDueDate->modify('+' . $i . ' months');
                        break;
                    case 'yearly':
                        $newDueDate->modify('+' . $i . ' years');
                        break;
                }
            }
            
            $insertStmt = $pdo->prepare('INSERT INTO transactions (house_id, created_by, type, category, description, amount, status, date, due_date, recurrence_interval, recurrence_count, is_recurring_instance, original_transaction_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insertStmt->execute([
                $trans['house_id'],
                $trans['created_by'],
                $trans['type'],
                $trans['category'],
                $trans['description'],
                $trans['amount'],
                'pending',
                $newDate->format('Y-m-d'),
                $newDueDate ? $newDueDate->format('Y-m-d') : null,
                'none',
                1,
                1,
                $trans['id'],
                date('c')
            ]);
        }
        
        // Marcar a transação original como não recorrente após gerar as instâncias
        $updateStmt = $pdo->prepare('UPDATE transactions SET recurrence_interval = "none", recurrence_count = 1 WHERE id = ?');
        $updateStmt->execute([$trans['id']]);
    }
}