<?php if (empty($licitacoes_recentes)): ?>
    <div style="text-align: center; padding: 60px; color: #7f8c8d;">
        <i data-lucide="inbox" style="width: 64px; height: 64px; margin-bottom: 20px;"></i>
        <h3 style="margin: 0 0 10px 0;">Nenhuma licitação encontrada</h3>
        <p style="margin: 0;">Comece criando sua primeira licitação.</p>
        <?php if (temPermissao('licitacao_criar')): ?>
        <button onclick="abrirModalCriarLicitacao()" class="btn-primary" style="margin-top: 20px;">
            <i data-lucide="plus-circle"></i> Criar Primeira Licitação
        </button>
        <?php endif; ?>
    </div>
<?php else: ?>
    <table>
<thead>
<tr>
<th>NUP</th>
<th>Número da contratação</th>
<th>Modalidade</th>
<th>Objeto</th>
<th>Valor Estimado</th>
<th>Situação</th>
<th>Pregoeiro</th>
<th>Data Abertura</th>
<th>Andamentos</th>
<th>Ações</th>
</tr>
</thead>
<tbody>
<?php foreach ($licitacoes_recentes as $licitacao): ?>
<tr>
<td>
<strong><?php echo htmlspecialchars($licitacao['nup']); ?></strong>
</td>
<td><?php echo htmlspecialchars($licitacao['numero_contratacao_final'] ?? $licitacao['numero_contratacao'] ?? 'N/A'); ?></td>
 
                        <td><span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php echo htmlspecialchars($licitacao['modalidade']); ?></span></td>
<td title="<?php echo htmlspecialchars($licitacao['objeto'] ?? ''); ?>">
<?php 
                            $objeto = $licitacao['objeto'] ?? '';
                            echo htmlspecialchars(strlen($objeto) > 80 ? substr($objeto, 0, 80) . '...' : $objeto); 
                            ?>
</td>
<td style="font-weight: 600; color: #27ae60;"><?php echo formatarMoeda($licitacao['valor_estimado'] ?? 0); ?></td>
<td>
<span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $licitacao['situacao'])); ?>">
<?php echo str_replace('_', ' ', $licitacao['situacao']); ?>
</span>
</td>
<td><?php echo htmlspecialchars($licitacao['pregoeiro'] ?? 'Não definido'); ?></td>
<td><?php echo $licitacao['data_abertura'] ? formatarData($licitacao['data_abertura']) : '-'; ?></td>
<td style="text-align: center;">
    <?php if (isset($licitacao['total_andamentos']) && $licitacao['total_andamentos'] > 0): ?>
        <span style="background: #e8f5e8; color: #2e7d32; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
            <?php echo $licitacao['total_andamentos']; ?>
        </span>
    <?php else: ?>
        <span style="color: #bbb; font-size: 12px;">-</span>
    <?php endif; ?>
</td>
<td>
<div style="display: flex; gap: 5px; flex-wrap: wrap;">
<!-- Botão Ver Detalhes (sempre visível) -->
<button onclick="verDetalhes(<?php echo $licitacao['id']; ?>)" title="Ver Detalhes" style="background: #6c757d; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="eye" style="width: 14px; height: 14px;"></i>
</button>

<?php if (temPermissao('licitacao_editar')): ?>
<button onclick="editarLicitacao(<?php echo $licitacao['id']; ?>)" title="Editar" style="background: #f39c12; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="edit" style="width: 14px; height: 14px;"></i>
</button>
<?php if (temPermissao('licitacao_excluir')): ?>
<button onclick="excluirLicitacao(<?php echo $licitacao['id']; ?>, '<?php echo htmlspecialchars($licitacao['nup']); ?>')" title="Excluir" style="background: #e74c3c; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
</button>
<?php endif; ?>
<button onclick="abrirModalImportarAndamentos('<?php echo htmlspecialchars($licitacao['nup']); ?>')" title="Importar Andamentos" style="background: #3498db; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="upload" style="width: 14px; height: 14px;"></i>
</button>
<button onclick="consultarAndamentos('<?php echo htmlspecialchars($licitacao['nup']); ?>')" title="Ver Andamentos" style="background: #27ae60; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="clock" style="width: 14px; height: 14px;"></i>
</button>
<?php else: ?>
<button onclick="consultarAndamentos('<?php echo htmlspecialchars($licitacao['nup']); ?>')" title="Ver Andamentos" style="background: #27ae60; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer;">
<i data-lucide="clock" style="width: 14px; height: 14px;"></i>
</button>
<span style="color: #7f8c8d; font-size: 12px; font-style: italic;">Somente leitura</span>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

            <!-- Informações de Paginação -->
            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <div style="color: #7f8c8d; font-size: 14px;">
                        <?php 
                        $inicio = ($pagina_atual - 1) * $licitacoes_por_pagina + 1;
                        $fim = min($pagina_atual * $licitacoes_por_pagina, $total_licitacoes);
                        ?>
                        Mostrando <?php echo $inicio; ?> a <?php echo $fim; ?> de <?php echo $total_licitacoes; ?> licitações<br>
                        Valor total estimado (página atual): <?php echo formatarMoeda(array_sum(array_column($licitacoes_recentes, 'valor_estimado'))); ?>
                    </div>
                    
                    <!-- Seletor de itens por página -->
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label style="font-size: 14px; color: #495057; font-weight: 600;">Itens por página:</label>
                        <select onchange="alterarItensPorPaginaAjax(this.value)" style="padding: 6px 8px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px;">
                            <option value="10" <?php echo $licitacoes_por_pagina == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $licitacoes_por_pagina == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $licitacoes_por_pagina == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $licitacoes_por_pagina == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>
                
                <?php if ($total_paginas > 1): ?>
                <div class="pagination">
                    <?php
                    $url_base = "?ajax=filtrar_licitacoes&";
                    if (!empty($filtro_busca)) $url_base .= "busca=" . urlencode($filtro_busca) . "&";
                    if (!empty($filtro_situacao)) $url_base .= "situacao_filtro=" . urlencode($filtro_situacao) . "&";
                    if (!empty($filtro_ano)) $url_base .= "ano_filtro=" . urlencode($filtro_ano) . "&";
                    $url_base .= "por_pagina=$licitacoes_por_pagina&";
                    ?>
                    
                    <!-- Primeira página -->
                    <?php if ($pagina_atual > 1): ?>
                        <a href="#" class="page-link ajax-link" data-url="<?php echo $url_base; ?>pagina=1">
                            <i data-lucide="chevrons-left"></i>
                        </a>
                        <a href="#" class="page-link ajax-link" data-url="<?php echo $url_base; ?>pagina=<?php echo $pagina_atual - 1; ?>">
                            <i data-lucide="chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Páginas numeradas -->
                    <?php
                    $inicio_pag = max(1, $pagina_atual - 2);
                    $fim_pag = min($total_paginas, $pagina_atual + 2);
                    
                    for ($i = $inicio_pag; $i <= $fim_pag; $i++):
                    ?>
                        <a href="#" class="page-link ajax-link <?php echo $i == $pagina_atual ? 'active' : ''; ?>" 
                           data-url="<?php echo $url_base; ?>pagina=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Última página -->
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="#" class="page-link ajax-link" data-url="<?php echo $url_base; ?>pagina=<?php echo $pagina_atual + 1; ?>">
                            <i data-lucide="chevron-right"></i>
                        </a>
                        <a href="#" class="page-link ajax-link" data-url="<?php echo $url_base; ?>pagina=<?php echo $total_paginas; ?>">
                            <i data-lucide="chevrons-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
<?php endif; ?>