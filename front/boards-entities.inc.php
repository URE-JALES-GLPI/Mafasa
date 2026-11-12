<?php
/**
 * Visão "Por entidade e mês" do relatório de Quadros Tralla (incluída por boards.php).
 * Conta os cards por entidade em cada mês, só os finalizados pelos técnicos de
 * TrallaReport::FINALIZER_EMAILS. Período: um mês, vários, ou de um mês até outro.
 */

use GlpiPlugin\Mafasa\TrallaReport;

if (!isset($result) || !is_array($result)) {
    return;
}

$range = $result['range'];
$months = $result['months'];
$val = static fn (string $k): string => is_scalar($_GET[$k] ?? null) ? trim((string) $_GET[$k]) : '';

// Atalhos de período
$now = new DateTime('first day of this month');
$shortcut = static function (int $back, int $count) use ($now): array {
    $to = (clone $now)->modify('-' . $back . ' month');
    $from = (clone $to)->modify('-' . ($count - 1) . ' month');
    return ['month_from' => $from->format('Y-m'), 'month_to' => $to->format('Y-m')];
};
$shortcuts = [
    'Este mês'          => $shortcut(0, 1),
    'Mês passado'       => $shortcut(1, 1),
    'Últimos 2 meses'   => $shortcut(0, 2),
    'Últimos 3 meses'   => $shortcut(0, 3),
    'Últimos 6 meses'   => $shortcut(0, 6),
    'Este ano'          => ['month_from' => date('Y') . '-01', 'month_to' => date('Y-m')],
];

echo "<form id='mf-form' method='get'>";
echo "<input type='hidden' name='view' value='entidades'>";
echo "<fieldset class='mf-filter-group'><legend>" . mf_brd_esc(__('Período e filtros', 'mafasa')) . "</legend>";
echo "<div class='mf-grid'>";
echo "<div class='mf-field'><label for='mf-f-month_from'>" . mf_brd_esc(__('Do mês', 'mafasa')) . "</label>"
    . "<input type='month' id='mf-f-month_from' name='month_from' value='" . mf_brd_esc($range['from']) . "'></div>";
echo "<div class='mf-field'><label for='mf-f-month_to'>" . mf_brd_esc(__('Até o mês', 'mafasa')) . "</label>"
    . "<input type='month' id='mf-f-month_to' name='month_to' value='" . mf_brd_esc($range['to']) . "'></div>";

echo "<div class='mf-field'><label for='mf-f-boards_id'>" . mf_brd_esc(__('Quadro', 'mafasa')) . "</label><select id='mf-f-boards_id' name='boards_id'>"
    . "<option value=''>" . mf_brd_esc(__('Todos', 'mafasa')) . "</option>";
foreach (TrallaReport::boardOptions() as $id => $name) {
    echo "<option value='" . (int) $id . "'" . ((string) $id === $val('boards_id') ? ' selected' : '') . ">" . mf_brd_esc($name) . "</option>";
}
echo "</select></div>";

echo "<div class='mf-field'><label for='mf-f-workflow_type'>" . mf_brd_esc(__('Tipo', 'mafasa')) . "</label><select id='mf-f-workflow_type' name='workflow_type'>";
foreach (['' => __('Todos', 'mafasa'), 'maintenance' => 'Manutenção', 'call' => 'Chamado'] as $k => $label) {
    echo "<option value='" . mf_brd_esc($k) . "'" . ($k === $val('workflow_type') ? ' selected' : '') . ">" . mf_brd_esc($label) . "</option>";
}
echo "</select></div>";

echo "<div class='mf-field'><label for='mf-f-finalizer_id'>" . mf_brd_esc(__('Técnico', 'mafasa')) . "</label><select id='mf-f-finalizer_id' name='finalizer_id'>"
    . "<option value=''>" . mf_brd_esc(__('Todos os técnicos do relatório', 'mafasa')) . "</option>";
foreach ($result['finalizers'] as $id => $name) {
    echo "<option value='" . (int) $id . "'" . ((string) $id === $val('finalizer_id') ? ' selected' : '') . ">" . mf_brd_esc($name) . "</option>";
}
echo "</select></div>";

// Seletor de entidades com busca pelo nome
$chosenEntities = array_map('intval', (array) ($result['entities'] ?? []));
$entityOptions = TrallaReport::entityChoices();
$pickerLabel = $chosenEntities === [] ? __('Todas as entidades', 'mafasa')
    : sprintf(_n('%d entidade selecionada', '%d entidades selecionadas', count($chosenEntities), 'mafasa'), count($chosenEntities));
echo "<div class='mf-field mf-field-wide'><label>" . mf_brd_esc(__('Entidades', 'mafasa')) . "</label>"
    . "<details class='mf-entity-picker' data-entity-picker><summary class='mf-btn'>" . mf_brd_esc($pickerLabel) . "</summary>"
    . "<div class='mf-entity-panel'>"
    . "<input type='search' class='mf-entity-search' placeholder='" . mf_brd_esc(__('Digite para filtrar pelo nome…', 'mafasa')) . "' data-entity-search>"
    . "<div class='mf-entity-tools'>"
    . "<button type='button' class='mf-btn mf-btn-sm' data-entity-mark='1'>" . mf_brd_esc(__('Marcar as visíveis', 'mafasa')) . "</button>"
    . "<button type='button' class='mf-btn mf-btn-sm' data-entity-mark='0'>" . mf_brd_esc(__('Desmarcar todas', 'mafasa')) . "</button>"
    . "<span class='mf-entity-count' data-entity-count></span></div>"
    . "<div class='mf-entity-list'>";
foreach ($entityOptions as $id => $entity) {
    echo "<label class='mf-entity-item' data-entity-name='" . mf_brd_esc(mb_strtolower($entity['full'] ?: $entity['name'])) . "' title='" . mf_brd_esc($entity['full']) . "'>"
        . "<input type='checkbox' name='entities[]' value='" . (int) $id . "'" . (in_array((int) $id, $chosenEntities, true) ? ' checked' : '') . "> "
        . mf_brd_esc($entity['name']) . "</label>";
}
echo "</div><small>" . mf_brd_esc(__('Nenhuma marcada = todas as escolas. Clique em "Gerar relatório" para aplicar.', 'mafasa')) . "</small>"
    . "</div></details></div>";

echo "<div class='mf-field'><label for='mf-f-scope'>" . mf_brd_esc(__('Situação', 'mafasa')) . "</label><select id='mf-f-scope' name='scope'>";
foreach (['' => 'Todos (finalizados e com o técnico)', 'finalized' => 'Só finalizados', 'open' => 'Só em aberto (com o técnico)'] as $k => $label) {
    echo "<option value='" . mf_brd_esc($k) . "'" . ($k === $val('scope') ? ' selected' : '') . ">" . mf_brd_esc($label) . "</option>";
}
echo "</select></div>";

echo "<div class='mf-field'><label for='mf-f-order_by'>" . mf_brd_esc(__('Ordenar', 'mafasa')) . "</label><select id='mf-f-order_by' name='order_by'>";
foreach (['total' => 'Mais cards primeiro', 'name' => 'Nome da entidade'] as $k => $label) {
    echo "<option value='" . mf_brd_esc($k) . "'" . ($k === ($val('order_by') ?: 'total') ? ' selected' : '') . ">" . mf_brd_esc($label) . "</option>";
}
echo "</select></div>";
echo "</div>";

echo "<div class='mf-actions mf-shortcuts'>";
foreach ($shortcuts as $label => $q) {
    $active = $q['month_from'] === $range['from'] && $q['month_to'] === $range['to'];
    echo "<a class='mf-btn mf-btn-sm" . ($active ? ' mf-btn-primary' : '') . "' href='boards.php?"
        . mf_brd_esc(mf_brd_qs($q + ['view' => 'entidades'])) . "'>" . mf_brd_esc($label) . "</a>";
}
echo "</div></fieldset>";

echo "<div class='mf-actions'>";
echo "<button type='submit' class='mf-btn mf-btn-primary'>" . mf_brd_esc(__('Gerar relatório', 'mafasa')) . "</button>";
echo "<a class='mf-btn' href='boards.php?view=entidades'>" . mf_brd_esc(__('Limpar filtros', 'mafasa')) . "</a>";
echo "<button type='button' id='mf-copy-link' class='mf-btn'>" . mf_brd_esc(__('Copiar link', 'mafasa')) . "</button>";
echo "<a id='mf-print-link' class='mf-btn' target='_blank' data-print='print-boards.php' href='print-boards.php?"
    . mf_brd_esc(mf_brd_qs()) . "'>" . mf_brd_esc(__('Imprimir', 'mafasa')) . "</a>";
echo "<a id='mf-pdf-link' class='mf-btn' data-print='print-boards.php' href='print-boards.php?"
    . mf_brd_esc(mf_brd_qs(['format' => 'pdf'])) . "'>" . mf_brd_esc(__('Baixar PDF', 'mafasa')) . "</a>";
echo "</div>";
echo "</form>";

$names = array_values($result['finalizers']);
echo "<div class='mf-card'><strong>" . mf_brd_esc(__('Período', 'mafasa')) . ":</strong> "
    . mf_brd_esc(TrallaReport::monthLabel($range['from']) . ($range['from'] !== $range['to'] ? ' a ' . TrallaReport::monthLabel($range['to']) : ''))
    . " &nbsp;|&nbsp; <strong>" . mf_brd_esc(__('Cards', 'mafasa')) . ":</strong> " . (int) $result['grand']
    . " &nbsp;|&nbsp; <strong>" . mf_brd_esc(__('Entidades', 'mafasa')) . ":</strong> " . count($result['rows'])
    . " (" . (int) ($result['with_cards'] ?? 0) . " " . mf_brd_esc(__('com cards', 'mafasa')) . ", "
    . (count($result['rows']) - (int) ($result['with_cards'] ?? 0)) . " " . mf_brd_esc(__('sem', 'mafasa')) . ")"
    . "<br><small>" . mf_brd_esc(__('Conta os cards finalizados por estes técnicos (mês da finalização) ou que estão com eles em qualquer situação — em andamento, aguardando aprovação, retirada, pausado (mês em que pegaram o card)', 'mafasa') . ': '
        . ($names !== [] ? implode(', ', $names) : '—') . '.') . "</small>";
if (!empty($range['capped'])) {
    echo "<br><small>" . mf_brd_esc(sprintf(__('Período limitado a %d meses.', 'mafasa'), TrallaReport::MAX_MONTHS)) . "</small>";
}
echo "</div>";

if (!empty($result['error'])) {
    echo "<div class='mf-warning' role='alert'>" . mf_brd_esc((string) $result['error']) . "</div>";
} elseif ($result['rows'] === []) {
    echo "<div class='mf-empty'>" . mf_brd_esc(__('Nenhum card nesse período.', 'mafasa')) . "</div>";
} else {
    echo "<div class='mf-table-wrap'><table class='mf-table mf-table-pivot' id='mf-table'><thead><tr>";
    echo "<th>" . mf_brd_esc(__('Entidade', 'mafasa')) . "</th>";
    foreach ($months as $ym) {
        echo "<th class='mf-num'>" . mf_brd_esc(TrallaReport::monthLabel($ym)) . "</th>";
    }
    if (count($months) > 1) {
        echo "<th class='mf-num'>" . mf_brd_esc(__('Total', 'mafasa')) . "</th>";
    }
    echo "</tr></thead><tbody>";
    foreach ($result['rows'] as $row) {
        echo "<tr" . ((int) $row['total'] === 0 ? " class='mf-row-empty'" : '') . "><td title='" . mf_brd_esc($row['full']) . "'>" . mf_brd_esc($row['entity']) . "</td>";
        if ((int) $row['total'] === 0) {
            echo "<td colspan='" . (count($months) + (count($months) > 1 ? 1 : 0)) . "' class='mf-none'>" . mf_brd_esc(__('Não teve chamados', 'mafasa')) . "</td></tr>";
            continue;
        }
        foreach ($months as $ym) {
            $n = (int) ($row['counts'][$ym] ?? 0);
            echo "<td class='mf-num" . ($n === 0 ? ' mf-zero' : '') . "'>" . $n . "</td>";
        }
        if (count($months) > 1) {
            echo "<td class='mf-num'><strong>" . (int) $row['total'] . "</strong></td>";
        }
        echo "</tr>";
    }
    echo "</tbody><tfoot><tr><th>" . mf_brd_esc(__('Total', 'mafasa')) . "</th>";
    foreach ($months as $ym) {
        echo "<th class='mf-num'>" . (int) ($result['totals'][$ym] ?? 0) . "</th>";
    }
    if (count($months) > 1) {
        echo "<th class='mf-num'>" . (int) $result['grand'] . "</th>";
    }
    echo "</tr></tfoot></table></div>";
}
