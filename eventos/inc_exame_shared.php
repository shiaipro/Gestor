<?php
/**
 * Regras compartilhadas de cálculo de faixa pretendida e valor de inscrição
 * usadas pelo fluxo público de inscrição em exame de faixa (aluno já cadastrado
 * ou visitante que acabou de ser criado como aluno).
 */

function calcularFaixaPretendidaExame($pdo, $unidade_id, $faixa_atual)
{
    $stmt_grads = $pdo->prepare("SELECT nome, grau FROM unidade_graduacoes WHERE unidade_id = ? ORDER BY ordem ASC, nome ASC");
    $stmt_grads->execute([$unidade_id]);
    $graduacoes_list = $stmt_grads->fetchAll();

    if ($graduacoes_list) {
        $ordem_faixas = array_map(function ($g) {
            return $g['nome'] . (!empty($g['grau']) ? ' - ' . $g['grau'] : '');
        }, $graduacoes_list);
    } else {
        $ordem_faixas = ['Branca', 'Cinza', 'Amarela', 'Laranja', 'Verde', 'Azul', 'Roxa', 'Marrom', 'Preta'];
    }

    $idx = array_search($faixa_atual, $ordem_faixas);
    return ($idx !== false && isset($ordem_faixas[$idx + 1])) ? $ordem_faixas[$idx + 1] : $faixa_atual;
}

/**
 * Retorna ['valor' => float, 'lote_label' => string|null], recalculado sempre no servidor.
 */
function calcularValorInscricaoExame($pdo, $evento, $turma_id)
{
    $hoje = date('Y-m-d');
    $valor = 0.00;
    $lote_label = null;

    if (empty($evento['todas_turmas'])) {
        if ($turma_id) {
            $stmt_valor = $pdo->prepare("SELECT valor_lote1, valor_lote1_data_limite, valor_lote2
                                          FROM eventos_graduacao_datas_turma
                                          WHERE evento_id = ? AND turma_id = ?");
            $stmt_valor->execute([$evento['id'], $turma_id]);
            $vt = $stmt_valor->fetch();
            if ($vt) {
                $lote1_expirado = !empty($vt['valor_lote1_data_limite']) && $hoje > $vt['valor_lote1_data_limite'];
                if ($lote1_expirado && $vt['valor_lote2'] !== null) {
                    $valor = (float) $vt['valor_lote2'];
                    $lote_label = '2º lote';
                } elseif ($vt['valor_lote1'] !== null) {
                    $valor = (float) $vt['valor_lote1'];
                    $lote_label = !empty($vt['valor_lote1_data_limite']) ? '1º lote' : null;
                }
            }
        }
    } else {
        $lote2_vigente = !empty($evento['lote2_data_inicio']) && $hoje >= $evento['lote2_data_inicio'];
        if ($lote2_vigente && $evento['taxa_lote2'] !== null && $evento['taxa_lote2'] !== '') {
            $valor = (float) $evento['taxa_lote2'];
            $lote_label = '2º lote';
        } else {
            $valor = (float) $evento['taxa'];
            $lote_label = !empty($evento['lote2_data_inicio']) ? '1º lote' : null;
        }
    }

    return ['valor' => $valor, 'lote_label' => $lote_label];
}

/**
 * Retorna os ids das turmas liberadas para o exame: as vinculadas em
 * eventos_graduacao_turmas quando o exame não é "todas as turmas", ou todas
 * as turmas ativas da unidade quando é.
 */
function turmasLiberadasParaExame($pdo, $evento)
{
    try {
        $pdo->query("SELECT liberado_visitante FROM eventos_graduacao_turmas LIMIT 1");
    } catch (Exception $e) {
        try { $pdo->exec("ALTER TABLE eventos_graduacao_turmas ADD COLUMN liberado_visitante TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $ex) {}
    }

    if (!empty($evento['todas_turmas'])) {
        $stmt = $pdo->prepare("SELECT id, nome FROM turmas WHERE unidade_id = ? AND status = 'ativo' ORDER BY nome ASC");
        $stmt->execute([$evento['unidade_id']]);
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare("SELECT t.id, t.nome FROM eventos_graduacao_turmas egt
                            JOIN turmas t ON t.id = egt.turma_id
                            WHERE egt.evento_id = ? AND egt.liberado_visitante = 1 AND t.status = 'ativo'
                            ORDER BY t.nome ASC");
    $stmt->execute([$evento['id']]);
    return $stmt->fetchAll();
}
