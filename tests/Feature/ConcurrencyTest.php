<?php

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

function concurrencyPdo(): PDO
{
    $config = config('database.connections.mysql');

    return new PDO(
        "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}",
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
}

function concurrencyFixture(bool $withTransaction = false): array
{
    $pdo = concurrencyPdo();
    $suffix = strtolower(Str::random(12));
    $now = now()->format('Y-m-d H:i:s');

    $pdo->prepare('INSERT INTO tenants (nama_toko, slug, operational_status, allow_negative_stock, dead_stock_days, created_at, updated_at) VALUES (?, ?, ?, 0, 90, ?, ?)')
        ->execute(["Concurrency {$suffix}", "concurrency-{$suffix}", 'active', $now, $now]);
    $tenantId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO users (tenant_id, name, email, no_hp, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$tenantId, 'Concurrency Owner', "{$suffix}@test.local", '08'.random_int(1000000000, 9999999999), 'hash', 'owner', $now, $now]);
    $userId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO categories (tenant_id, kode, nama, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
        ->execute([$tenantId, "CAT-{$suffix}", 'Concurrency', $now, $now]);
    $categoryId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO items (tenant_id, category_id, kode, nama, satuan, harga_beli, average_cost, harga_jual, stok_saat_ini, stok_minimal, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 50, 50, 100, 10, 0, ?, ?)')
        ->execute([$tenantId, $categoryId, "ITM-{$suffix}", 'Concurrent Item', 'Pcs', $now, $now]);
    $itemId = (int) $pdo->lastInsertId();

    $transactionId = null;
    if ($withTransaction) {
        $pdo->prepare('INSERT INTO pos_transactions (tenant_id, cashier_id, invoice_number, status, subtotal_amount, discount_amount, total_amount, idempotency_key, request_hash, created_at, updated_at) VALUES (?, ?, ?, ?, 200, 0, 200, ?, ?, ?, ?)')
            ->execute([$tenantId, $userId, "POS-CONCURRENT-{$suffix}", 'pending_payment', "pay-{$suffix}", str_repeat('a', 64), $now, $now]);
        $transactionId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO pos_transaction_items (tenant_id, pos_transaction_id, item_id, qty, returned_qty, harga_saat_transaksi, discount_amount, subtotal_amount, created_at) VALUES (?, ?, ?, 2, 0, 100, 0, 200, ?)')
            ->execute([$tenantId, $transactionId, $itemId, $now]);
    }

    return compact('pdo', 'tenantId', 'userId', 'categoryId', 'itemId', 'transactionId', 'suffix', 'now');
}

function preferredConcurrencyFixture(): array
{
    $fixture = concurrencyFixture();
    $pdo = $fixture['pdo'];
    $linkIds = [];

    foreach (['A', 'B'] as $label) {
        $pdo->prepare('INSERT INTO suppliers (tenant_id, nama, created_at, updated_at) VALUES (?, ?, ?, ?)')
            ->execute([$fixture['tenantId'], "Supplier {$label}", $fixture['now'], $fixture['now']]);
        $supplierId = (int) $pdo->lastInsertId();

        $pdo->prepare('INSERT INTO item_suppliers (tenant_id, item_id, supplier_id, is_preferred, created_at, updated_at) VALUES (?, ?, ?, 0, ?, ?)')
            ->execute([$fixture['tenantId'], $fixture['itemId'], $supplierId, $fixture['now'], $fixture['now']]);
        $linkIds[] = (int) $pdo->lastInsertId();
    }

    return array_merge($fixture, compact('linkIds'));
}

function shoppingConcurrencyFixture(): array
{
    $fixture = concurrencyFixture();
    $pdo = $fixture['pdo'];

    $pdo->prepare('INSERT INTO suppliers (tenant_id, nama, created_at, updated_at) VALUES (?, ?, ?, ?)')
        ->execute([$fixture['tenantId'], 'Receive Supplier', $fixture['now'], $fixture['now']]);
    $supplierId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO shopping_lists (tenant_id, created_by, status, submitted_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$fixture['tenantId'], $fixture['userId'], 'purchased', $fixture['now'], $fixture['now'], $fixture['now']]);
    $shoppingListId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO shopping_list_items (tenant_id, shopping_list_id, item_id, supplier_id, qty_disarankan, qty_dibeli, qty_received, is_checked, created_at, updated_at) VALUES (?, ?, ?, ?, 3, 3, 0, 1, ?, ?)')
        ->execute([$fixture['tenantId'], $shoppingListId, $fixture['itemId'], $supplierId, $fixture['now'], $fixture['now']]);
    $shoppingListItemId = (int) $pdo->lastInsertId();

    return array_merge($fixture, compact('supplierId', 'shoppingListId', 'shoppingListItemId'));
}

function cleanupConcurrencyFixture(array $fixture): void
{
    $pdo = concurrencyPdo();
    foreach ([
        'audit_logs', 'pos_payments', 'item_stock_movements', 'pos_transaction_items',
        'pos_transactions', 'shopping_list_items', 'shopping_lists', 'item_suppliers',
        'suppliers', 'items', 'categories', 'users', 'tenants',
    ] as $table) {
        $column = $table === 'tenants' ? 'id' : 'tenant_id';
        $pdo->prepare("DELETE FROM {$table} WHERE {$column} = ?")->execute([$fixture['tenantId']]);
    }
}

function runConcurrentWorkers(array $commands): array
{
    $processes = array_map(fn (array $command) => new Process($command, base_path(), timeout: 30), $commands);
    foreach ($processes as $process) {
        $process->start();
    }
    foreach ($processes as $process) {
        $process->wait();
        expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    }

    return array_map(fn (Process $process) => trim($process->getOutput()), $processes);
}

it('atomically deduplicates two truly concurrent checkout processes', function () {
    $fixture = concurrencyFixture();
    $key = "concurrent-{$fixture['suffix']}";
    $command = [PHP_BINARY, base_path('tests/Support/concurrent-pos-worker.php'), 'checkout',
        (string) $fixture['tenantId'], (string) $fixture['userId'], (string) $fixture['itemId'], $key];

    try {
        $ids = runConcurrentWorkers([$command, $command]);
        expect($ids[0])->toBe($ids[1]);

        $statement = concurrencyPdo()->prepare('SELECT COUNT(*) FROM pos_transactions WHERE tenant_id = ? AND idempotency_key = ?');
        $statement->execute([$fixture['tenantId'], $key]);
        expect((int) $statement->fetchColumn())->toBe(1);
    } finally {
        cleanupConcurrencyFixture($fixture);
    }
});

it('allows only one of two truly concurrent cash payment processes', function () {
    $fixture = concurrencyFixture(true);
    $command = [PHP_BINARY, base_path('tests/Support/concurrent-pos-worker.php'), 'pay',
        (string) $fixture['tenantId'], (string) $fixture['userId'], (string) $fixture['transactionId'], '-'];

    try {
        $outcomes = runConcurrentWorkers([$command, $command]);
        sort($outcomes);
        expect($outcomes)->toBe(['TRANSACTION_ALREADY_PROCESSED', 'paid']);

        $pdo = concurrencyPdo();
        foreach ([
            ['SELECT COUNT(*) FROM pos_payments WHERE tenant_id = ?', $fixture['tenantId'], 1],
            ['SELECT COUNT(*) FROM item_stock_movements WHERE tenant_id = ? AND movement_type = "sale"', $fixture['tenantId'], 1],
            ['SELECT stok_saat_ini FROM items WHERE id = ?', $fixture['itemId'], 8],
        ] as [$sql, $parameter, $expected]) {
            $statement = $pdo->prepare($sql);
            $statement->execute([$parameter]);
            expect((int) $statement->fetchColumn())->toBe($expected);
        }
    } finally {
        cleanupConcurrencyFixture($fixture);
    }
});

it('serializes concurrent preferred supplier changes on the item row', function () {
    $fixture = preferredConcurrencyFixture();
    $commands = array_map(fn (int $linkId): array => [
        PHP_BINARY, base_path('tests/Support/concurrent-pos-worker.php'), 'preferred',
        (string) $fixture['tenantId'], (string) $fixture['userId'], (string) $linkId, '-',
    ], $fixture['linkIds']);

    try {
        expect(runConcurrentWorkers($commands))->toBe(['preferred', 'preferred']);

        $statement = concurrencyPdo()->prepare('SELECT COUNT(*) FROM item_suppliers WHERE tenant_id = ? AND is_preferred = 1');
        $statement->execute([$fixture['tenantId']]);
        expect((int) $statement->fetchColumn())->toBe(1);
    } finally {
        cleanupConcurrencyFixture($fixture);
    }
});

it('allows only one of two truly concurrent shopping list receives', function () {
    $fixture = shoppingConcurrencyFixture();
    $command = [
        PHP_BINARY, base_path('tests/Support/concurrent-pos-worker.php'), 'receive',
        (string) $fixture['tenantId'], (string) $fixture['userId'],
        (string) $fixture['shoppingListId'], (string) $fixture['shoppingListItemId'],
    ];

    try {
        $outcomes = runConcurrentWorkers([$command, $command]);
        sort($outcomes);
        expect($outcomes)->toBe(['VALIDATION_ERROR', 'completed']);

        $pdo = concurrencyPdo();
        foreach ([
            ['SELECT COUNT(*) FROM item_stock_movements WHERE tenant_id = ? AND movement_type = "stock_in"', $fixture['tenantId'], 1],
            ['SELECT stok_saat_ini FROM items WHERE id = ?', $fixture['itemId'], 13],
            ['SELECT qty_received FROM shopping_list_items WHERE id = ?', $fixture['shoppingListItemId'], 3],
        ] as [$sql, $parameter, $expected]) {
            $statement = $pdo->prepare($sql);
            $statement->execute([$parameter]);
            expect((int) $statement->fetchColumn())->toBe($expected);
        }
    } finally {
        cleanupConcurrencyFixture($fixture);
    }
});
