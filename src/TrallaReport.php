<?php
/**
 * Relatórios dos quadros do Tralla — cards por quadro/lista/técnico, situação,
 * prioridade, SLA e vínculo com chamados GLPI.
 *
 * Usado pelas telas boards.php e print-boards.php. Somente SELECT; mostra só os quadros
 * que o usuário vê no Tralla e respeita o filtro de entidades do Mafasa.
 *
 * Compat: GLPI 12.0.0–12.0.99, PHP >= 8.2.
 */

namespace GlpiPlugin\Mafasa;

class TrallaReport
{
    public const PAGE_SIZE = 50;
    public const PRINT_LIMIT = 5000;

    private const STATUS_LABELS = [
        'pending'          => 'Pendente',
        'in_progress'      => 'Em andamento',
        'waiting_approval' => 'Aguardando aprovação',
        'approved'         => 'Aprovado (aguardando família)',
        'withdrawal'       => 'Aguardando retirada',
        'done'             => 'Concluído',
        'deletion_pending' => 'Exclusão pendente',
        'normal'           => 'Aberto',
    ];

    private const SLA_LABELS = [
        'running'  => 'No prazo',
        'overdue'  => 'Atrasado',
        'met'      => 'Cumprido',
        'late_met' => 'Cumprido com atraso',
        'paused'   => 'Pausado',
        'waiting'  => 'Aguardando liberação',
    ];

    private static ?bool $available_cache = null;

    // ------------------------------------------------------------------
    // Disponibilidade e permissão
    // ------------------------------------------------------------------

    /** Tralla ativo, com as tabelas de quadros e cards. */
    public static function available(): bool
    {
        if (self::$available_cache !== null) {
            return self::$available_cache;
        }
        global $DB;
        try {
            return self::$available_cache = class_exists('Plugin') && \Plugin::isPluginActive('tralla')
                && isset($DB) && $DB->tableExists('glpi_plugin_tralla_boards')
                && $DB->tableExists('glpi_plugin_tralla_cards')
                && $DB->tableExists('glpi_plugin_tralla_lists');
        } catch (\Throwable $e) {
            return self::$available_cache = false;
        }
    }

    public static function canView(): bool
    {
        if (!function_exists('mafasa_can_access') || !mafasa_can_access()) {
            return false;
        }
        try {
            return (bool) \Session::haveRight('plugin_tralla', defined('READ') ? (int) READ : 1);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isReady(): bool
    {
        return self::available() && self::canView();
    }

    // ------------------------------------------------------------------
    // Filtros e colunas
    // ------------------------------------------------------------------

    public static function getFilters(): array
    {
        return [
            'boards_id'          => ['label' => 'Quadro', 'kind' => 'choices', 'choices' => self::boardChoices()],
            'lists_id'           => ['label' => 'Lista', 'kind' => 'choices', 'choices' => self::listChoices()],
            'technician_id'      => ['label' => 'Técnico', 'kind' => 'choices', 'choices' => self::technicianChoices()],
            'status'             => ['label' => 'Situação', 'kind' => 'choices', 'choices' => self::STATUS_LABELS],
            'workflow_type'      => ['label' => 'Tipo', 'kind' => 'choices', 'choices' => ['maintenance' => 'Manutenção', 'call' => 'Chamado']],
            'priority'           => ['label' => 'Prioridade', 'kind' => 'choices', 'choices' => ['P1' => 'P1', 'P2' => 'P2', 'P3' => 'P3', 'P4' => 'P4']],
            'is_paused'          => ['label' => 'Pausado', 'kind' => 'choices', 'choices' => ['1' => 'Sim', '0' => 'Não']],
            'has_ticket'         => ['label' => 'Vinculado a chamado', 'kind' => 'choices', 'choices' => ['1' => 'Sim', '0' => 'Não']],
            'name'               => ['label' => 'Escola / nome do card', 'kind' => 'text'],
            'date_creation_from' => ['label' => 'Criação (de)', 'kind' => 'date'],
            'date_creation_to'   => ['label' => 'Criação (até)', 'kind' => 'date'],
        ];
    }

    public static function getColumns(): array
    {
        return [
            'code'           => 'Código',
            'name'           => 'Escola / card',
            'board'          => 'Quadro',
            'list'           => 'Lista',
            'status'         => 'Situação',
            'workflow_type'  => 'Tipo',
            'priority'       => 'Prioridade',
            'sla'            => 'SLA',
            'technician'     => 'Técnico',
            'creator'        => 'Criado por',
            'machines'       => 'Máquinas',
            'tickets_id'     => 'Chamado',
            'is_paused'      => 'Pausado',
            'entities_id'    => 'Entidade',
            'date_creation'  => 'Criação',
            'date_taken'     => 'Pego em',
            'date_finalized' => 'Finalizado em',
            'date_withdrawn' => 'Retirado em',
        ];
    }

    public static function getColumnKind(string $key): string
    {
        if (in_array($key, ['date_creation', 'date_taken', 'date_finalized', 'date_withdrawn'], true)) {
            return 'date';
        }
        if (in_array($key, ['machines', 'tickets_id'], true)) {
            return 'int';
        }
        return $key === 'is_paused' ? 'bool' : 'text';
    }

    public static function getDefaultColumns(): array
    {
        return ['code', 'name', 'list', 'status', 'priority', 'sla', 'technician', 'machines', 'date_creation'];
    }

    // ------------------------------------------------------------------
    // Lista paginada
    // ------------------------------------------------------------------

    public static function buildList(array $params, int $page, int $limit, string $sort, string $order): array
    {
        $page = max(1, $page);
        $limit = max(1, min($limit, self::PRINT_LIMIT));
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        if (!self::available()) {
            return self::errorResult('O plugin Tralla não está ativo.');
        }
        if (!array_key_exists($sort, self::getColumns())) {
            $sort = 'code';
        }

        global $DB;
        $from = self::fromSql() . ' ' . self::buildWhere($params);
        try {
            $row = $DB->doQuery('SELECT COUNT(*) AS total' . $from)->fetch_assoc();
            $total = (int) ($row['total'] ?? 0);
        } catch (\Throwable $e) {
            return self::errorResult(self::sqlFailMessage($e));
        }
        $maxPage = max(1, (int) ceil($total / $limit));
        $page = min($page, $maxPage);

        // SLA é calculado em PHP (dias úteis do Tralla): a ordenação por SLA é feita na página.
        $sql = 'SELECT ' . self::selectSql() . $from
            . ' ORDER BY ' . self::sortExpr($sort) . ' ' . $order . ', c.`id` ASC'
            . ' LIMIT ' . $limit . ' OFFSET ' . (($page - 1) * $limit);
        $rows = [];
        try {
            $res = $DB->doQuery($sql);
            while (($raw = $res->fetch_assoc()) !== null) {
                $rows[] = self::formatRow($raw);
            }
        } catch (\Throwable $e) {
            return self::errorResult(self::sqlFailMessage($e), $total, $page, $maxPage);
        }
        if ($sort === 'sla') {
            usort($rows, static fn (array $a, array $b): int => $order === 'ASC'
                ? strcmp((string) $a['mf_sla'], (string) $b['mf_sla']) : strcmp((string) $b['mf_sla'], (string) $a['mf_sla']));
        }

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'max_page' => $maxPage, 'error' => null];
    }

    // ------------------------------------------------------------------
    // Resumos
    // ------------------------------------------------------------------

    public static function getSummaryModes(): array
    {
        return [
            'board'      => 'Cards por quadro',
            'list'       => 'Cards por lista',
            'technician' => 'Cards por técnico',
            'status'     => 'Cards por situação',
            'sla'        => 'Cards por situação do SLA',
            'priority'   => 'Cards por prioridade',
            'type'       => 'Manutenção x Chamado',
            'month'      => 'Cards por mês de criação',
        ];
    }

    public static function buildSummary(string $mode, array $params): array
    {
        if (!isset(self::getSummaryModes()[$mode])) {
            $mode = 'board';
        }
        if (!self::available()) {
            return ['rows' => [], 'totals' => [], 'error' => 'O plugin Tralla não está ativo.'];
        }

        global $DB;
        $from = self::fromSql() . ' ' . self::buildWhere($params);
        $groups = [];
        $grand = 0;
        $done = 0;
        try {
            // Poucos milhares de cards no máximo: agrupa em PHP para usar os mesmos rótulos e o SLA do Tralla.
            $res = $DB->doQuery('SELECT ' . self::selectSql() . $from . ' LIMIT ' . self::PRINT_LIMIT);
            while (($raw = $res->fetch_assoc()) !== null) {
                $row = self::formatRow($raw);
                $grand++;
                if (in_array((string) $raw['status'], ['withdrawal', 'done'], true)) {
                    $done++;
                }
                $key = match ($mode) {
                    'list'       => (string) $row['mf_board'] . ' › ' . (string) $row['mf_list'],
                    'technician' => (string) ($row['mf_technician'] ?: 'Sem técnico'),
                    'status'     => (string) $row['mf_status'],
                    'sla'        => (string) ($row['mf_sla'] ?: 'Sem prioridade'),
                    'priority'   => (string) ($row['mf_priority'] ?: 'Sem prioridade'),
                    'type'       => (string) $row['mf_workflow_type'],
                    'month'      => substr((string) $raw['date_creation'], 0, 7),
                    default      => (string) $row['mf_board'],
                };
                $groups[$key] = ($groups[$key] ?? 0) + 1;
            }
        } catch (\Throwable $e) {
            return ['rows' => [], 'totals' => [], 'error' => self::sqlFailMessage($e)];
        }
        if (in_array($mode, ['month', 'priority'], true)) {
            ksort($groups);
        } else {
            arsort($groups);
        }
        $rows = [];
        foreach ($groups as $label => $count) {
            $rows[] = ['mf_key' => (string) $label, 'mf_label' => (string) ($label !== '' ? $label : '—'),
                'mf_total' => (string) $count, 'mf_checked' => null];
        }

        return ['rows' => $rows, 'totals' => ['grand' => $grand, 'done' => $done], 'error' => null];
    }

    public static function describeFilterValue(string $key, string $value): string
    {
        $def = self::getFilters()[$key] ?? null;
        if (is_array($def) && isset($def['choices'][$value])) {
            return (string) $def['choices'][$value];
        }
        return $value;
    }

    // ------------------------------------------------------------------
    // Cards por entidade e mês (finalizados pelos técnicos da URE)
    // ------------------------------------------------------------------

    /** Só contam os cards finalizados por estes usuários (e-mail do GLPI). */
    public const FINALIZER_EMAILS = [
        'gabriel.nsiqueira@apoiofde.sp.gov.br',
        'leonardo.facao@apoiofde.sp.gov.br',
        'marco.masson@apoiofde.sp.gov.br',
        'aryan.ferrari@apoiofde.sp.gov.br',
    ];
    public const MAX_MONTHS = 24;
    private const MONTH_NAMES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    /** Usuários (id => nome) dos e-mails acima. */
    public static function finalizers(): array
    {
        global $DB;
        $out = [];
        try {
            foreach ($DB->request([
                'SELECT'     => ['u.id', 'u.name', 'u.firstname', 'u.realname'],
                'DISTINCT'   => true,
                'FROM'       => 'glpi_useremails AS e',
                'INNER JOIN' => ['glpi_users AS u' => ['ON' => ['u' => 'id', 'e' => 'users_id']]],
                'WHERE'      => ['e.email' => self::FINALIZER_EMAILS],
            ]) as $row) {
                $name = trim((string) $row['firstname'] . ' ' . (string) $row['realname']);
                $out[(int) $row['id']] = $name !== '' ? $name : (string) $row['name'];
            }
        } catch (\Throwable $e) {
            return [];
        }
        asort($out);
        return $out;
    }

    /** "2026-11" -> "nov/2026". */
    public static function monthLabel(string $ym): string
    {
        [$y, $m] = array_map('intval', explode('-', $ym) + [0, 0]);
        return (self::MONTH_NAMES[$m - 1] ?? (string) $m) . '/' . $y;
    }

    /** Período pedido (mês inicial e final, YYYY-MM); padrão: mês atual. */
    public static function monthRange(array $params): array
    {
        $read = static function (string $key) use ($params): ?string {
            $v = is_scalar($params[$key] ?? null) ? trim((string) $params[$key]) : '';
            return preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $v) ? $v : null;
        };
        $from = $read('month_from') ?? date('Y-m');
        $to = $read('month_to') ?? $from;
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        $months = [];
        $cursor = new \DateTime($from . '-01');
        while ($cursor->format('Y-m') <= $to && count($months) < self::MAX_MONTHS) {
            $months[] = $cursor->format('Y-m');
            $cursor->modify('+1 month');
        }
        return ['from' => $from, 'to' => end($months), 'months' => $months, 'capped' => end($months) !== $to];
    }

    /**
     * Tabela entidade x mês: cards ligados a um dos FINALIZER_EMAILS — finalizados por ele
     * (última "Manutenção finalizada" do histórico) ou ainda com ele como técnico, em qualquer
     * situação (em andamento, aguardando aprovação, retirada, pausado...).
     * Mês do card: data da finalização; se ainda não foi finalizado por um deles, data em que
     * o técnico pegou o card (ou a criação, se ainda não pegou).
     */
    public static function buildEntityMonthly(array $params): array
    {
        $range = self::monthRange($params);
        $empty = ['months' => $range['months'], 'rows' => [], 'totals' => [], 'grand' => 0,
            'range' => $range, 'finalizers' => [], 'error' => null];
        if (!self::available()) {
            return ['error' => 'O plugin Tralla não está ativo.'] + $empty;
        }
        $finalizers = self::finalizers();
        $empty['finalizers'] = $finalizers;
        if ($finalizers === []) {
            return ['error' => 'Nenhum dos usuários do relatório foi encontrado pelo e-mail no GLPI.'] + $empty;
        }

        global $DB;
        $boards = array_keys(self::boardChoices());
        if ($boards === []) {
            return $empty;
        }
        $value = static fn (string $key): string => is_scalar($params[$key] ?? null) ? trim((string) $params[$key]) : '';
        $start = $range['from'] . '-01 00:00:00';
        $end = (new \DateTime($range['to'] . '-01'))->modify('last day of this month')->format('Y-m-d') . ' 23:59:59';

        $ids = implode(',', array_map('intval', array_keys($finalizers)));
        // Finalizado por um dos técnicos? Senão vale quem está com o card agora.
        $byTeam = 'h.`users_id` IN (' . $ids . ')';
        $refDate = 'CASE WHEN ' . $byTeam . ' THEN h.`date_creation` ELSE COALESCE(c.`date_taken`, c.`date_creation`) END';
        $person = 'CASE WHEN ' . $byTeam . ' THEN h.`users_id` ELSE c.`technician_id` END';
        $sql = 'SELECT c.`id`, ce.`name` AS entity_name, ce.`completename` AS entity_full,'
            . ' DATE_FORMAT(' . $refDate . ', \'%Y-%m\') AS ym, (' . $byTeam . ') AS finalized'
            . ' FROM `glpi_plugin_tralla_cards` c'
            . ' INNER JOIN `glpi_plugin_tralla_boards` b ON b.`id` = c.`plugin_tralla_boards_id`'
            . ' LEFT JOIN (SELECT `plugin_tralla_cards_id` AS cid, MAX(`id`) AS mid FROM `glpi_plugin_tralla_history`'
            . "     WHERE `action` = 'card_finalized' GROUP BY `plugin_tralla_cards_id`) lh ON lh.cid = c.`id`"
            . ' LEFT JOIN `glpi_plugin_tralla_history` h ON h.`id` = lh.mid'
            . ' LEFT JOIN `glpi_entities` ce ON ce.`id` = c.`entities_id`'
            // Arquivados entram: cards de uma mesma família são arquivados quando as máquinas são reunidas.
            . ' WHERE c.`stage` = 1 AND c.`is_deleted` = 0'
            . ' AND c.`plugin_tralla_boards_id` IN (' . implode(',', array_map('intval', $boards)) . ')'
            . ' AND (' . $byTeam . ' OR c.`technician_id` IN (' . $ids . '))'
            . ' AND ' . $refDate . ' BETWEEN ' . self::quote($start) . ' AND ' . self::quote($end);
        if ($DB->fieldExists('glpi_plugin_tralla_cards', 'is_draft')) {
            $sql .= ' AND c.`is_draft` = 0';
        }
        if (function_exists('mafasa_entity_sql')) {
            $sql .= (string) \mafasa_entity_sql('b');
        }
        if ((int) $value('boards_id') > 0) {
            $sql .= ' AND c.`plugin_tralla_boards_id` = ' . (int) $value('boards_id');
        }
        if (in_array($value('workflow_type'), ['maintenance', 'call'], true)) {
            $sql .= ' AND c.`workflow_type` = ' . self::quote($value('workflow_type'));
        }
        if ((int) $value('finalizer_id') > 0 && isset($finalizers[(int) $value('finalizer_id')])) {
            $sql .= ' AND ' . $person . ' = ' . (int) $value('finalizer_id');
        }
        if ($value('scope') === 'finalized') {
            $sql .= ' AND ' . $byTeam;
        } elseif ($value('scope') === 'open') {
            $sql .= ' AND NOT (' . $byTeam . ' AND h.`users_id` IS NOT NULL)';
        }

        $rows = [];
        $totals = array_fill_keys($range['months'], 0);
        $grand = 0;
        try {
            $res = $DB->doQuery($sql);
            while (($raw = $res->fetch_assoc()) !== null) {
                $entity = trim((string) ($raw['entity_name'] ?? '')) ?: 'Sem entidade';
                $ym = (string) $raw['ym'];
                if (!isset($totals[$ym])) {
                    continue;
                }
                if (!isset($rows[$entity])) {
                    $rows[$entity] = ['entity' => $entity, 'full' => (string) ($raw['entity_full'] ?? ''),
                        'counts' => array_fill_keys($range['months'], 0), 'total' => 0];
                }
                $rows[$entity]['counts'][$ym]++;
                $rows[$entity]['total']++;
                $totals[$ym]++;
                $grand++;
            }
        } catch (\Throwable $e) {
            return ['error' => self::sqlFailMessage($e)] + $empty;
        }
        $order = $value('order_by') === 'name' ? 'name' : 'total';
        uasort($rows, static fn (array $a, array $b): int => $order === 'name'
            ? strnatcasecmp($a['entity'], $b['entity'])
            : ([$b['total'], $a['entity']] <=> [$a['total'], $b['entity']]));

        return ['months' => $range['months'], 'rows' => array_values($rows), 'totals' => $totals, 'grand' => $grand,
            'range' => $range, 'finalizers' => $finalizers, 'error' => null];
    }

    /** Opções de quadro para os filtros da visão por entidade. */
    public static function boardOptions(): array
    {
        return self::boardChoices();
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    private static function fromSql(): string
    {
        return ' FROM `glpi_plugin_tralla_cards` c'
            . ' INNER JOIN `glpi_plugin_tralla_boards` b ON b.`id` = c.`plugin_tralla_boards_id`'
            . ' LEFT JOIN `glpi_plugin_tralla_lists` l ON l.`id` = c.`plugin_tralla_lists_id`'
            . ' LEFT JOIN `glpi_plugin_tralla_cards` r ON r.`id` = IF(c.`code_root_cards_id` > 0, c.`code_root_cards_id`, c.`id`)'
            . ' LEFT JOIN `glpi_users` tu ON tu.`id` = c.`technician_id`'
            . ' LEFT JOIN `glpi_users` cu ON cu.`id` = c.`users_id`'
            . ' LEFT JOIN `glpi_entities` ce ON ce.`id` = c.`entities_id`';
    }

    private static function selectSql(): string
    {
        return 'c.`id`, c.`name`, c.`status`, c.`workflow_type`, c.`priority`, c.`stage`, c.`is_paused`, c.`paused_at`,'
            . ' c.`paused_seconds`, c.`date_sla_start`, c.`date_taken`, c.`date_finalized`, c.`date_approved`,'
            . ' c.`date_withdrawn`, c.`date_creation`, c.`machines_total`, c.`tickets_id`, c.`family_seq`,'
            . ' r.`code_number` AS root_number, b.`name` AS board_name, l.`name` AS list_name,'
            . ' TRIM(CONCAT(COALESCE(tu.`firstname`, \'\'), \' \', COALESCE(tu.`realname`, \'\'))) AS technician_name, tu.`name` AS technician_login,'
            . ' TRIM(CONCAT(COALESCE(cu.`firstname`, \'\'), \' \', COALESCE(cu.`realname`, \'\'))) AS creator_name, cu.`name` AS creator_login,'
            . ' ce.`completename` AS entity_name';
    }

    private static function formatRow(array $raw): array
    {
        $number = (int) ($raw['root_number'] ?? 0) ?: (int) $raw['id'];
        $seq = (int) ($raw['family_seq'] ?? 1);
        $sla = '';
        if (function_exists('tralla_card_sla') && (string) ($raw['priority'] ?? '') !== '') {
            try {
                $info = tralla_card_sla($raw);
                $next = is_array($info) ? ($info['next'] ?? null) : null;
                if (is_array($next)) {
                    $sla = (self::SLA_LABELS[(string) $next['state']] ?? (string) $next['state']) . ' — ' . (string) $next['title'];
                } elseif (is_array($info)) {
                    $last = end($info['steps']);
                    $sla = self::SLA_LABELS[(string) ($last['state'] ?? '')] ?? '';
                }
            } catch (\Throwable $e) {
                $sla = '';
            }
        }

        return [
            'mf_code'           => '#' . $number . ($seq > 1 ? '/' . $seq : ''),
            'mf_name'           => (string) $raw['name'],
            'mf_board'          => (string) ($raw['board_name'] ?? ''),
            'mf_list'           => (string) ($raw['list_name'] ?? ''),
            'mf_status'         => !empty($raw['is_paused']) ? 'Pausado'
                : (self::STATUS_LABELS[(string) $raw['status']] ?? (string) $raw['status']),
            'mf_workflow_type'  => (string) $raw['workflow_type'] === 'call' ? 'Chamado' : 'Manutenção',
            'mf_priority'       => (string) ($raw['priority'] ?? ''),
            'mf_sla'            => $sla,
            'mf_technician'     => (string) ($raw['technician_name'] ?: ($raw['technician_login'] ?? '')),
            'mf_creator'        => (string) ($raw['creator_name'] ?: ($raw['creator_login'] ?? '')),
            'mf_machines'       => (string) (int) ($raw['machines_total'] ?? 0),
            'mf_tickets_id'     => (int) ($raw['tickets_id'] ?? 0) > 0 ? (string) (int) $raw['tickets_id'] : null,
            'mf_is_paused'      => !empty($raw['is_paused']) ? '1' : '0',
            'mf_entities_id'    => (string) ($raw['entity_name'] ?? ''),
            'mf_date_creation'  => $raw['date_creation'],
            'mf_date_taken'     => $raw['date_taken'],
            'mf_date_finalized' => $raw['date_finalized'],
            'mf_date_withdrawn' => $raw['date_withdrawn'],
        ];
    }

    private static function buildWhere(array $params): string
    {
        $sql = 'WHERE c.`stage` = 1 AND c.`is_deleted` = 0 AND c.`is_archived` = 0';
        global $DB;
        if ($DB->fieldExists('glpi_plugin_tralla_cards', 'is_draft')) {
            // Rascunhos do Tralla ainda não foram publicados: ficam fora do relatório.
            $sql .= ' AND c.`is_draft` = 0';
        }
        $boards = array_keys(self::boardChoices());
        $sql .= $boards === [] ? ' AND 1=0' : ' AND c.`plugin_tralla_boards_id` IN (' . implode(',', array_map('intval', $boards)) . ')';
        if (function_exists('mafasa_entity_sql')) {
            $sql .= (string) \mafasa_entity_sql('b');
        }

        $value = static fn (string $key): string => is_scalar($params[$key] ?? null) ? trim((string) $params[$key]) : '';
        foreach (['boards_id' => 'plugin_tralla_boards_id', 'lists_id' => 'plugin_tralla_lists_id', 'technician_id' => 'technician_id'] as $key => $column) {
            if ((int) $value($key) > 0) {
                $sql .= ' AND c.`' . $column . '` = ' . (int) $value($key);
            }
        }
        if (isset(self::STATUS_LABELS[$value('status')])) {
            $sql .= ' AND c.`status` = ' . self::quote($value('status'));
        }
        if (in_array($value('workflow_type'), ['maintenance', 'call'], true)) {
            $sql .= ' AND c.`workflow_type` = ' . self::quote($value('workflow_type'));
        }
        if (in_array($value('priority'), ['P1', 'P2', 'P3', 'P4'], true)) {
            $sql .= ' AND c.`priority` = ' . self::quote($value('priority'));
        }
        if (in_array($value('is_paused'), ['0', '1'], true)) {
            $sql .= ' AND c.`is_paused` = ' . (int) $value('is_paused');
        }
        if ($value('has_ticket') === '1') {
            $sql .= ' AND c.`tickets_id` > 0';
        } elseif ($value('has_ticket') === '0') {
            $sql .= ' AND c.`tickets_id` = 0';
        }
        if ($value('name') !== '') {
            $sql .= ' AND c.`name` LIKE ' . self::quote('%' . mb_substr($value('name'), 0, 255) . '%');
        }
        foreach (['date_creation_from' => ['>=', ' 00:00:00'], 'date_creation_to' => ['<=', ' 23:59:59']] as $key => [$op, $time]) {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value($key), $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                $sql .= ' AND c.`date_creation` ' . $op . ' ' . self::quote($value($key) . $time);
            }
        }

        return $sql;
    }

    private static function sortExpr(string $sort): string
    {
        return [
            'code' => 'r.`code_number`', 'name' => 'c.`name`', 'board' => 'b.`name`', 'list' => 'l.`name`',
            'status' => 'c.`status`', 'workflow_type' => 'c.`workflow_type`', 'priority' => 'c.`priority`',
            'technician' => 'technician_name', 'creator' => 'creator_name', 'machines' => 'c.`machines_total`',
            'tickets_id' => 'c.`tickets_id`', 'is_paused' => 'c.`is_paused`', 'entities_id' => 'ce.`completename`',
            'date_creation' => 'c.`date_creation`', 'date_taken' => 'c.`date_taken`',
            'date_finalized' => 'c.`date_finalized`', 'date_withdrawn' => 'c.`date_withdrawn`',
        ][$sort] ?? 'r.`code_number`';
    }

    /** Quadros do Tralla que o usuário vê (a mesma regra do próprio Tralla). */
    private static function boardChoices(): array
    {
        if (!self::available() || !class_exists('PluginTrallaBoard')) {
            return [];
        }
        $out = [];
        try {
            foreach (\PluginTrallaBoard::getVisibleBoards(true) as $board) {
                $out[(int) $board['id']] = (string) $board['name'];
            }
        } catch (\Throwable $e) {
            return [];
        }
        asort($out);
        return $out;
    }

    private static function listChoices(): array
    {
        global $DB;
        $boards = self::boardChoices();
        if ($boards === []) {
            return [];
        }
        $out = [];
        try {
            foreach ($DB->request([
                'SELECT' => ['id', 'name', 'plugin_tralla_boards_id'],
                'FROM'   => 'glpi_plugin_tralla_lists',
                'WHERE'  => ['plugin_tralla_boards_id' => array_keys($boards), 'is_archived' => 0],
                'ORDER'  => ['plugin_tralla_boards_id ASC', 'rank ASC'],
            ]) as $row) {
                $out[(int) $row['id']] = ($boards[(int) $row['plugin_tralla_boards_id']] ?? '') . ' › ' . (string) $row['name'];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    private static function technicianChoices(): array
    {
        global $DB;
        $boards = self::boardChoices();
        if ($boards === []) {
            return [];
        }
        $out = [];
        try {
            foreach ($DB->request([
                'SELECT'     => ['u.id', 'u.name', 'u.firstname', 'u.realname'],
                'DISTINCT'   => true,
                'FROM'       => 'glpi_plugin_tralla_boards_users AS bu',
                'INNER JOIN' => ['glpi_users AS u' => ['ON' => ['u' => 'id', 'bu' => 'users_id']]],
                'WHERE'      => ['bu.plugin_tralla_boards_id' => array_keys($boards), 'NOT' => ['bu.role' => 'tablet']],
            ]) as $row) {
                $name = trim((string) $row['firstname'] . ' ' . (string) $row['realname']);
                $out[(int) $row['id']] = $name !== '' ? $name : (string) $row['name'];
            }
        } catch (\Throwable $e) {
            return [];
        }
        asort($out);
        return $out;
    }

    private static function quote(string $value): string
    {
        global $DB;
        return (string) $DB->quoteValue($value);
    }

    private static function errorResult(string $message, int $total = 0, int $page = 1, int $maxPage = 1): array
    {
        return ['rows' => [], 'total' => $total, 'page' => $page, 'max_page' => $maxPage, 'error' => $message];
    }

    private static function sqlFailMessage(\Throwable $e): string
    {
        $raw = trim((string) $e->getMessage());
        if (class_exists('Toolbox')) {
            try {
                \Toolbox::logInFile('mafasa-sql', $raw . "\n", true);
            } catch (\Throwable $ignored) {
            }
        }
        foreach ([' in SQL query', ' in query'] as $needle) {
            if (($pos = strpos($raw, $needle)) !== false) {
                $raw = substr($raw, 0, $pos);
                break;
            }
        }
        return 'Não foi possível executar a consulta do relatório. [' . mb_substr($raw, 0, 400) . ']';
    }
}
