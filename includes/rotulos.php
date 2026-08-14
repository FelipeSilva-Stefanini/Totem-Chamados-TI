<?php
/**
 * Rótulos de exibição e formatadores compartilhados.
 * Os valores do banco são gravados sem acento (limitação prática de ENUM);
 * a tradução para exibição acontece aqui, num lugar só.
 */
declare(strict_types=1);

$rotuloStatus = [
    'Aberto'       => 'Aberto',
    'Em tratativa' => 'Em tratativa',
    'Resolvido'    => 'Resolvido',
    'Concluido'    => 'Concluído',
];

$rotuloTipo = [
    'Duvida'     => 'Dúvida',
    'Incidente'  => 'Incidente',
    'Requisicao' => 'Requisição',
];

$rotuloPrioridade = [
    'Baixa'   => 'Baixa',
    'Media'   => 'Média',
    'Alta'    => 'Alta',
    'Critica' => 'Crítica',
];

$rotuloEvento = [
    'Abertura'             => 'Chamado aberto',
    'Atribuicao'           => 'Atribuído',
    'Transferencia'        => 'Transferido',
    'Mudanca_Status'       => 'Status alterado',
    'Alteracao_Prioridade' => 'Prioridade alterada',
];

$classeStatus = [
    'Aberto'       => 'st-aberto',
    'Em tratativa' => 'st-tratativa',
    'Resolvido'    => 'st-resolvido',
    'Concluido'    => 'st-concluido',
];

$classePrioridade = [
    'Baixa'   => 'pr-baixa',
    'Media'   => 'pr-media',
    'Alta'    => 'pr-alta',
    'Critica' => 'pr-critica',
];

if (!function_exists('formatarData')) {
    function formatarData(?string $data, bool $comAno = false): string
    {
        if (!$data) {
            return '—';
        }
        $ts = strtotime($data);
        if (!$ts) {
            return '—';
        }
        return date($comAno ? 'd/m/Y H:i' : 'd/m H:i', $ts);
    }
}
