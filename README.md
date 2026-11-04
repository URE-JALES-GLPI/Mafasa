# [URE] Mafasa — Relatórios de Ativos, Chamados, Quadros e Entidades

Plugin GLPI (compat 12.0.0–12.0.99, PHP >= 8.2) para relatórios de ativos por
categoria, de chamados GLPI e de quadros Tralla, com filtros totais, colunas
desmarcáveis, resumos agregados e impressão/PDF com cabeçalho institucional
(URE Jales).

## Instalação

1. Copiar a pasta `Mafasa` para `plugins/` ou `marketplace/` do GLPI.
2. Em Configurar → Plugins: Instalar → Ativar.
3. Acessar em Ferramentas → Relatórios (ou abrir diretamente
   `front/report.php`; as telas têm navegação interna entre Ativos, Chamados,
   Quadros e Diversos).

## Estrutura

```
mafasa.xml, setup.php, hook.php, composer.json
src/compat.php, src/Install.php, src/Report.php, src/Config.php
src/TicketsReport.php, src/TrallaReport.php, src/EntitiesReport.php
front/report.php, front/print.php, front/config.php
front/tickets.php, front/print-tickets.php
front/boards.php, front/print-boards.php
front/diversos.php
install/mysql/plugin_mafasa_empty.sql
public/css/mafasa.css, public/css/mafasa-print.css, public/js/app.js
public/img/logo-ure-jales.png
```

## Permissões

O acesso ao plugin é controlado por perfil, com uma única opção **Sim/Não**
(padrão **Não**):

1. Configurar → Perfis → abrir o perfil desejado → aba **Relatórios**.
2. Marcar **Sim** em "Acesso aos relatórios" e salvar.

O direito é gravado em `glpi_profilerights` com o nome
`plugin_mafasa_access`. Enquanto não existir a linha (ou o valor for 0), o menu
Ferramentas → Relatórios não aparece e as telas respondem "sem permissão".

## Relatórios

- **Ativos** (`front/report.php`): multi-tipo, filtros, colunas desmarcáveis.
- **Chamados** (`front/tickets.php`): lista detalhada + resumos agregados
  (status, tipo, prioridade, técnico, mês etc. com tempos médios).
- **Quadros Tralla** (`front/boards.php`): cartões por quadro/lista/membro,
  prazos, checklists e vínculo com chamados. Requer o plugin Tralla ativo.
- **Diversos** (`front/diversos.php`): lista os nomes das entidades do GLPI e
  permite baixar um CSV completo com ID, nome e nome completo. Respeita as
  entidades visíveis no contexto ativo do usuário; quem pode visualizar todas
  recebe todas as entidades cadastradas.

## Status

v1.3.1 — relatórios de Ativos, Chamados, Quadros Tralla e Diversos.
