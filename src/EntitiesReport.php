<?php
/** Lista de entidades do GLPI para o relatório Diversos. */

namespace GlpiPlugin\Mafasa;

class EntitiesReport
{
    public static function canView(): bool
    {
        return function_exists('mafasa_can_access') && mafasa_can_access();
    }

    /**
     * Retorna todas as entidades permitidas pelo contexto ativo do GLPI.
     *
     * @return array<int,array{id:int,name:string,completename:string}>
     */
    public static function getRows(): array
    {
        global $DB;

        if (!self::canView()) {
            return [];
        }

        $where = [];
        if (!\mafasa_can_view_all_entities()) {
            $ids = \mafasa_visible_entities();
            if ($ids === []) {
                return [];
            }
            $where['id'] = $ids;
        }

        $query = [
            'SELECT' => ['id', 'name', 'completename'],
            'FROM'   => 'glpi_entities',
            'ORDER'  => ['completename ASC', 'id ASC'],
        ];
        if ($where !== []) {
            $query['WHERE'] = $where;
        }

        $rows = [];
        foreach ($DB->request($query) as $row) {
            $rows[] = [
                'id'           => (int) $row['id'],
                'name'         => (string) $row['name'],
                'completename' => (string) $row['completename'],
            ];
        }
        return $rows;
    }
}
