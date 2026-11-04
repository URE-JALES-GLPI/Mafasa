<?php
/** Relatórios diversos: nomes de entidades cadastradas no GLPI. */

include('../../../inc/includes.php');
require_once __DIR__ . '/../src/compat.php';

use GlpiPlugin\Mafasa\EntitiesReport;
use GlpiPlugin\Mafasa\Report;
use GlpiPlugin\Mafasa\TrallaReport;

Session::checkLoginUser();

if (!EntitiesReport::canView()) {
    mafasa_display_right_error(__('Você não tem permissão para acessar os relatórios.', 'mafasa'));
    return;
}

function mf_div_esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

try {
    $entities = EntitiesReport::getRows();
    $error = null;
} catch (Throwable $e) {
    $entities = [];
    $error = __('Não foi possível consultar as entidades do GLPI.', 'mafasa');
}

if (($_GET['download'] ?? '') === 'csv') {
    if ($error !== null) {
        http_response_code(500);
        echo mf_div_esc($error);
        return;
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="entidades-glpi.csv"');
    header('Cache-Control: private, no-store');
    $stream = fopen('php://output', 'wb');
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, ['ID', 'Nome', 'Nome completo'], ';');
    // Evita que planilhas interpretem nomes cadastrados como fórmulas.
    $safe = static function (string $value): string {
        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'" . $value : $value;
    };
    foreach ($entities as $entity) {
        fputcsv($stream, [
            $entity['id'],
            $safe($entity['name']),
            $safe($entity['completename']),
        ], ';');
    }
    fclose($stream);
    return;
}

Html::header(
    __('[URE] Mafasa — Diversos', 'mafasa'),
    $_SERVER['PHP_SELF'],
    'tools',
    Report::class
);

$webdir = mafasa_web_dir('mafasa');
echo "<link rel='stylesheet' href='" . mf_div_esc($webdir . '/public/css/mafasa.css?v=' . PLUGIN_MAFASA_VERSION) . "'>";
echo "<div class='mf-page'>";
echo "<div class='mf-page-header'><h2>" . mf_div_esc(__('Relatórios Diversos', 'mafasa')) . "</h2></div>";

echo "<div class='mf-actions mf-nav'>";
if (Report::canView()) {
    echo "<a class='mf-btn' href='" . mf_div_esc($webdir . '/front/report.php') . "'>" . mf_div_esc(__('Ativos', 'mafasa')) . "</a>";
}
if (class_exists('Ticket') && Ticket::canView()) {
    echo "<a class='mf-btn' href='" . mf_div_esc($webdir . '/front/tickets.php') . "'>" . mf_div_esc(__('Chamados', 'mafasa')) . "</a>";
}
if (TrallaReport::isReady()) {
    echo "<a class='mf-btn' href='" . mf_div_esc($webdir . '/front/boards.php') . "'>" . mf_div_esc(__('Quadros Tralla', 'mafasa')) . "</a>";
}
echo "<a class='mf-btn mf-btn-primary' href='" . mf_div_esc($webdir . '/front/diversos.php') . "'>" . mf_div_esc(__('Diversos', 'mafasa')) . "</a>";
echo "</div>";

echo "<div class='mf-card'>";
echo "<h3>" . mf_div_esc(__('Entidades cadastradas no GLPI', 'mafasa')) . "</h3>";
echo "<p>" . mf_div_esc(\mafasa_can_view_all_entities()
    ? __('Lista de todas as entidades cadastradas.', 'mafasa')
    : __('Lista das entidades visíveis no seu contexto ativo.', 'mafasa')) . "</p>";
if ($error !== null) {
    echo "<div class='mf-warning' role='alert'>" . mf_div_esc($error) . "</div>";
} else {
    echo "<div class='mf-actions'><a class='mf-btn mf-btn-primary' href='diversos.php?download=csv'>"
        . mf_div_esc(__('Baixar relatório CSV', 'mafasa')) . "</a></div>";
    echo "<div class='mf-result-bar'><span class='mf-page-info'>"
        . mf_div_esc(sprintf(__('%d entidade(s)', 'mafasa'), count($entities))) . "</span></div>";
    if ($entities === []) {
        echo "<div class='mf-empty'>" . mf_div_esc(__('Nenhuma entidade encontrada.', 'mafasa')) . "</div>";
    } else {
        echo "<div class='mf-table-wrap'><table class='mf-table'><thead><tr><th>ID</th><th>"
            . mf_div_esc(__('Nome', 'mafasa')) . "</th><th>"
            . mf_div_esc(__('Nome completo', 'mafasa')) . "</th></tr></thead><tbody>";
        foreach ($entities as $entity) {
            echo '<tr><td>' . $entity['id'] . '</td><td>' . mf_div_esc($entity['name'])
                . '</td><td>' . mf_div_esc($entity['completename']) . '</td></tr>';
        }
        echo "</tbody></table></div>";
    }
}
echo "</div></div>";
Html::footer();
