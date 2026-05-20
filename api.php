<?php
// =====================================================
//  api.php — Endpoints del juego XO Arena
//  Coloca este archivo junto a config.php en tu servidor
// =====================================================

require_once __DIR__ . '/config.php';

$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── Enrutar según ?action= ──────────────────────────
switch ($action) {

    // --------------------------------------------------
    //  GET ?action=login&name=Juan
    //  Crea el jugador si no existe y devuelve sus datos
    // --------------------------------------------------
    case 'login':
        if ($method !== 'GET') respond(['error' => 'Usa GET'], 405);

        $name = trim($_GET['name'] ?? '');
        if (!$name || mb_strlen($name) > 50)
            respond(['error' => 'Nombre inválido'], 400);

        // Insertar si no existe
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO players (name) VALUES (:name)'
        );
        $stmt->execute([':name' => $name]);

        // Obtener datos
        $stmt = $pdo->prepare(
            'SELECT id, name, wins, losses, ties FROM players WHERE name = :name'
        );
        $stmt->execute([':name' => $name]);
        $player = $stmt->fetch();

        respond(['success' => true, 'player' => $player]);


    // --------------------------------------------------
    //  POST ?action=save_game
    //  Body JSON: { player_id, result, difficulty }
    //  Guarda partida y actualiza contadores del jugador
    // --------------------------------------------------
    case 'save_game':
        if ($method !== 'POST') respond(['error' => 'Usa POST'], 405);

        $body = json_decode(file_get_contents('php://input'), true);
        $playerId   = (int)  ($body['player_id']  ?? 0);
        $result     = (string)($body['result']    ?? '');
        $difficulty = (string)($body['difficulty'] ?? '');

        if (!$playerId)
            respond(['error' => 'player_id requerido'], 400);
        if (!in_array($result, ['W','L','T'], true))
            respond(['error' => 'result debe ser W, L o T'], 400);
        if (!in_array($difficulty, ['easy','medium','hard'], true))
            respond(['error' => 'difficulty inválida'], 400);

        // Insertar partida
        $stmt = $pdo->prepare(
            'INSERT INTO games (player_id, result, difficulty) VALUES (:pid, :res, :diff)'
        );
        $stmt->execute([':pid' => $playerId, ':res' => $result, ':diff' => $difficulty]);

        // Actualizar contadores
        $col = match($result) { 'W' => 'wins', 'L' => 'losses', 'T' => 'ties' };
        $pdo->prepare("UPDATE players SET {$col} = {$col} + 1 WHERE id = :id")
            ->execute([':id' => $playerId]);

        // Devolver stats actualizadas
        $stmt = $pdo->prepare(
            'SELECT wins, losses, ties FROM players WHERE id = :id'
        );
        $stmt->execute([':id' => $playerId]);
        respond(['success' => true, 'stats' => $stmt->fetch()]);


    // --------------------------------------------------
    //  GET ?action=stats&player_id=1
    //  Estadísticas totales del jugador
    // --------------------------------------------------
    case 'stats':
        if ($method !== 'GET') respond(['error' => 'Usa GET'], 405);

        $playerId = (int)($_GET['player_id'] ?? 0);
        if (!$playerId) respond(['error' => 'player_id requerido'], 400);

        $stmt = $pdo->prepare(
            'SELECT wins, losses, ties,
                    (wins + losses + ties) AS total
             FROM players WHERE id = :id'
        );
        $stmt->execute([':id' => $playerId]);
        $stats = $stmt->fetch();

        if (!$stats) respond(['error' => 'Jugador no encontrado'], 404);
        respond(['success' => true, 'stats' => $stats]);


    // --------------------------------------------------
    //  GET ?action=history&player_id=1&limit=15
    //  Últimas N partidas del jugador
    // --------------------------------------------------
    case 'history':
        if ($method !== 'GET') respond(['error' => 'Usa GET'], 405);

        $playerId = (int)($_GET['player_id'] ?? 0);
        $limit    = min((int)($_GET['limit'] ?? 15), 50);
        if (!$playerId) respond(['error' => 'player_id requerido'], 400);

        $stmt = $pdo->prepare(
            'SELECT result, difficulty, played_at
             FROM games
             WHERE player_id = :id
             ORDER BY id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':id',  $playerId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit,    PDO::PARAM_INT);
        $stmt->execute();

        respond(['success' => true, 'history' => $stmt->fetchAll()]);


    // --------------------------------------------------
    //  GET ?action=ranking&limit=10
    //  Top jugadores por victorias
    // --------------------------------------------------
    case 'ranking':
        if ($method !== 'GET') respond(['error' => 'Usa GET'], 405);

        $limit = min((int)($_GET['limit'] ?? 10), 50);
        $stmt  = $pdo->prepare(
            'SELECT name, wins, losses, ties,
                    (wins + losses + ties) AS total,
                    CASE WHEN (wins+losses+ties)=0 THEN 0
                         ELSE ROUND(wins*100.0/(wins+losses+ties),1)
                    END AS win_rate
             FROM players
             ORDER BY wins DESC, losses ASC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        respond(['success' => true, 'ranking' => $stmt->fetchAll()]);


    // --------------------------------------------------
    //  Acción desconocida
    // --------------------------------------------------
    default:
        respond([
            'error'   => 'Acción no reconocida',
            'actions' => ['login','save_game','stats','history','ranking']
        ], 400);
}
