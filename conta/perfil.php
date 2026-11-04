<?php
/**
 * Perfil do usuário: dados pessoais e objetivo, senha, plano, preferências,
 * exportação dos dados (LGPD) e exclusão da conta.
 * Alunos veem também o resumo do estudo; administradores, só a parte de conta.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$usuario = exigir_login();
$uid = (int) $usuario['id'];
$eh_aluno = $usuario['tipo'] === 'aluno';
$pdo = db();

$erros = [];
$secao_com_erro = '';

/* ===================== Ações (POST) ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_csrf();
    $acao = $_POST['acao'] ?? '';

    /* ---------- Foto de perfil ---------- */
    if ($acao === 'foto') {
        try {
            $nova = salvar_foto_perfil($uid, $_FILES['foto'] ?? []);
            $pdo->prepare('UPDATE usuarios SET foto = ? WHERE id = ?')->execute([$nova, $uid]);
            apagar_foto_perfil($usuario['foto'] ?? null);
            toast('Foto de perfil atualizada.');
        } catch (RuntimeException $erro) {
            toast($erro->getMessage(), 'erro');
        }
        redirecionar('conta/perfil.php');
    }

    if ($acao === 'remover_foto') {
        apagar_foto_perfil($usuario['foto'] ?? null);
        $pdo->prepare('UPDATE usuarios SET foto = NULL WHERE id = ?')->execute([$uid]);
        toast('Foto removida.', 'info');
        redirecionar('conta/perfil.php');
    }

    /* ---------- Dados pessoais e objetivo ---------- */
    if ($acao === 'dados') {
        $nome   = trim($_POST['nome'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $serie  = $_POST['serie'] ?? '';
        $escola = trim($_POST['escola'] ?? '');
        $objetivo = $_POST['objetivo'] ?? '';
        $curso  = trim($_POST['curso_desejado'] ?? '');

        if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
            $erros['nome'] = 'Informe seu nome (3 a 100 caracteres).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $erros['email'] = 'Informe um e-mail válido.';
        } else {
            $existe = $pdo->prepare('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?');
            $existe->execute([$email, $uid]);
            if ($existe->fetchColumn()) {
                $erros['email'] = 'Esse e-mail já está em uso por outra conta.';
            }
        }
        if ($serie !== '' && !isset(SERIES[$serie])) {
            $erros['serie'] = 'Escolha uma opção da lista.';
        }
        if ($objetivo !== '' && !in_array($objetivo, OBJETIVOS, true)) {
            $erros['objetivo'] = 'Escolha uma opção da lista.';
        }
        if (mb_strlen($escola) > 120) {
            $erros['escola'] = 'Use no máximo 120 caracteres.';
        }
        if (mb_strlen($curso) > 80) {
            $erros['curso_desejado'] = 'Use no máximo 80 caracteres.';
        }

        if (!$erros) {
            $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, serie = ?, escola = ?, objetivo = ?, curso_desejado = ? WHERE id = ?')
                ->execute([$nome, $email, $serie ?: null, $escola ?: null, $objetivo ?: null, $curso ?: null, $uid]);
            toast('Perfil atualizado.');
            redirecionar('conta/perfil.php');
        }
        $secao_com_erro = 'dados';
        $usuario = array_merge($usuario, ['nome' => $nome, 'email' => $email, 'serie' => $serie,
                                          'escola' => $escola, 'objetivo' => $objetivo, 'curso_desejado' => $curso]);
        toast('Confira os campos destacados.', 'erro');
    }

    /* ---------- Troca de senha ---------- */
    if ($acao === 'senha') {
        $atual = $_POST['senha_atual'] ?? '';
        $nova  = $_POST['senha_nova'] ?? '';
        $conf  = $_POST['senha_confirmacao'] ?? '';

        $stmt = $pdo->prepare('SELECT senha FROM usuarios WHERE id = ?');
        $stmt->execute([$uid]);
        if (!password_verify($atual, (string) $stmt->fetchColumn())) {
            $erros['senha_atual'] = 'A senha atual não confere.';
        }
        if (mb_strlen($nova) < 6) {
            $erros['senha_nova'] = 'A nova senha precisa ter pelo menos 6 caracteres.';
        } elseif ($nova === $atual) {
            $erros['senha_nova'] = 'Escolha uma senha diferente da atual.';
        }
        if ($nova !== $conf) {
            $erros['senha_confirmacao'] = 'As senhas não conferem.';
        }

        if (!$erros) {
            $pdo->prepare('UPDATE usuarios SET senha = ? WHERE id = ?')->execute([password_hash($nova, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            toast('Senha alterada com sucesso.');
            redirecionar('conta/perfil.php');
        }
        $secao_com_erro = 'senha';
        toast('Não foi possível trocar a senha.', 'erro');
    }

    /* ---------- Exclusão da conta (só alunos) ---------- */
    if ($acao === 'excluir' && $eh_aluno) {
        $stmt = $pdo->prepare('SELECT senha FROM usuarios WHERE id = ?');
        $stmt->execute([$uid]);
        if (!password_verify($_POST['senha_exclusao'] ?? '', (string) $stmt->fetchColumn())) {
            $erros['senha_exclusao'] = 'Senha incorreta. A conta não foi excluída.';
            $secao_com_erro = 'excluir';
            toast('Senha incorreta. A conta não foi excluída.', 'erro');
        } else {
            // As chaves estrangeiras com ON DELETE CASCADE apagam todo o histórico do aluno
            apagar_foto_perfil($usuario['foto'] ?? null);
            $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND tipo = 'aluno'")->execute([$uid]);
            $_SESSION = [];
            session_regenerate_id(true);
            toast('Sua conta e todos os seus dados foram excluídos.', 'info');
            redirecionar('auth/login.php');
        }
    }
}

/* ===================== Dados para exibição ===================== */
$assinatura = $eh_aluno && eh_premium($usuario) ? assinatura_ativa($uid) : null;

if ($eh_aluno) {
    $stmt = $pdo->prepare(
        "SELECT
            (SELECT COUNT(*) FROM historico_simulados WHERE usuario_id = :u1) AS simulados,
            (SELECT COALESCE(SUM(pontuacao), 0) FROM historico_simulados WHERE usuario_id = :u2) AS acertos,
            (SELECT COALESCE(SUM(total_questoes), 0) FROM historico_simulados WHERE usuario_id = :u3) AS questoes,
            (SELECT COALESCE(SUM(minutos), 0) FROM sessoes_estudo WHERE usuario_id = :u4) AS minutos,
            (SELECT COUNT(*) FROM revisoes WHERE usuario_id = :u5 AND status = 'concluida') AS revisoes,
            (SELECT COUNT(*) FROM caderno_erros WHERE usuario_id = :u6 AND superada = 1) AS superados"
    );
    $stmt->execute(['u1' => $uid, 'u2' => $uid, 'u3' => $uid, 'u4' => $uid, 'u5' => $uid, 'u6' => $uid]);
    $estudo = $stmt->fetch();
    $aproveitamento = $estudo['questoes'] ? (int) round($estudo['acertos'] / $estudo['questoes'] * 100) : 0;
    $sequencia = sequencia_atual(dias_estudados($uid));

    // Matérias em que o aluno mais se sai bem
    $stmt = $pdo->prepare(
        'SELECT m.nome, m.cor, m.icone, ROUND(SUM(h.pontuacao) / SUM(h.total_questoes) * 100) AS acerto
           FROM historico_simulados h JOIN materias m ON m.id = h.materia_id
          WHERE h.usuario_id = ? GROUP BY m.id ORDER BY m.nome'
    );
    $stmt->execute([$uid]);
    $materias_feitas = $stmt->fetchAll();
    usort($materias_feitas, fn($a, $b) => (int) $b['acerto'] <=> (int) $a['acerto']);
    $destaques = array_slice($materias_feitas, 0, 3);
}

$erro_campo = fn(string $c) => isset($erros[$c]) ? ' campo--erro' : '';

$titulo_pagina  = 'Meu perfil';
$pagina_ativa   = 'perfil';
$scripts_extras = ['perfil.js'];
require RAIZ_APP . '/includes/layout_inicio.php';
?>

<!-- ===================== Cabeçalho do perfil ===================== -->
<section class="perfil-topo">
    <div class="perfil-foto">
        <?= avatar_html($usuario, 'perfil-topo__avatar') ?>
        <form method="post" enctype="multipart/form-data" id="form-foto">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="foto">
            <label class="perfil-foto__botao" title="<?= $usuario['foto'] ? 'Trocar foto' : 'Adicionar foto' ?>">
                <?= icone('camera', 'icone--sm') ?>
                <span class="visualmente-oculto"><?= $usuario['foto'] ? 'Trocar foto de perfil' : 'Adicionar foto de perfil' ?></span>
                <input type="file" name="foto" id="input-foto" accept="image/jpeg,image/png,image/webp" class="visualmente-oculto">
            </label>
        </form>
        <?php if ($usuario['foto']): ?>
            <form method="post" class="perfil-foto__remover" data-confirmar="Remover a foto de perfil?" data-confirmar-texto="Seu avatar volta a mostrar as iniciais." data-confirmar-botao="Remover">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="remover_foto">
                <button type="submit">Remover foto</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="perfil-topo__info">
        <h1><?= e($usuario['nome']) ?></h1>
        <p class="perfil-topo__email"><?= e($usuario['email']) ?></p>
        <div class="perfil-topo__selos">
            <?php if ($eh_aluno): ?>
                <span class="selo <?= eh_premium($usuario) ? 'selo--premium' : '' ?>"><?= icone(eh_premium($usuario) ? 'coroa' : 'livro', 'icone--xs') ?> Plano <?= e(rotulo($usuario['plano'])) ?></span>
                <?php if ($usuario['serie']): ?><span class="selo"><?= e(SERIES[$usuario['serie']] ?? '') ?></span><?php endif; ?>
                <?php if ($usuario['objetivo']): ?><span class="selo selo--accent"><?= icone('coluna', 'icone--xs') ?> <?= e($usuario['objetivo']) ?><?= $usuario['curso_desejado'] ? ', ' . e($usuario['curso_desejado']) : '' ?></span><?php endif; ?>
            <?php else: ?>
                <span class="selo selo--accent"><?= icone('configuracoes', 'icone--xs') ?> Administrador</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="perfil-topo__lateral">
        <span>Membro desde</span>
        <b><?= e(data_br($usuario['criado_em'])) ?></b>
        <?php if ($eh_aluno): ?>
            <span class="mt-4">Sequência atual</span>
            <b><?= $sequencia ?> <?= $sequencia === 1 ? 'dia' : 'dias' ?></b>
        <?php endif; ?>
    </div>
</section>

<?php if ($eh_aluno): ?>
<!-- ===================== Estudo em números ===================== -->
<div class="kpis">
    <article class="kpi">
        <div class="kpi__topo"><span>Simulados feitos</span><?= icone('simulado') ?></div>
        <div class="kpi__valor"><?= (int) $estudo['simulados'] ?></div>
        <div class="kpi__detalhe"><?= (int) $estudo['questoes'] ?> questões respondidas</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Aproveitamento</span><?= icone('check-circulo') ?></div>
        <div class="kpi__valor"><?= $estudo['questoes'] ? $aproveitamento . '%' : '-' ?></div>
        <div class="kpi__detalhe"><?= (int) $estudo['acertos'] ?> acertos no total</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Horas estudadas</span><?= icone('relogio') ?></div>
        <div class="kpi__valor"><?= e(formatar_minutos((int) $estudo['minutos'])) ?></div>
        <div class="kpi__detalhe">Somando todos os Pomodoros</div>
    </article>
    <article class="kpi">
        <div class="kpi__topo"><span>Revisões e erros</span><?= icone('revisoes') ?></div>
        <div class="kpi__valor"><?= (int) $estudo['revisoes'] ?></div>
        <div class="kpi__detalhe"><?= (int) $estudo['superados'] ?> erros superados</div>
    </article>
</div>
<?php endif; ?>

<div class="grade-lateral perfil-grade">
    <!-- ===================== Coluna principal ===================== -->
    <div class="pilha">
        <section class="card" id="dados">
            <div class="card__cabecalho">
                <div>
                    <h2 class="card__titulo">Dados pessoais<?= $eh_aluno ? ' e objetivo' : '' ?></h2>
                    <p class="card__subtitulo"><?= $eh_aluno ? 'Ajudam a personalizar seu estudo. Só nome e e-mail são obrigatórios.' : 'Nome e e-mail usados para entrar no sistema.' ?></p>
                </div>
            </div>
            <form method="post" data-validar>
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="dados">
                <div class="form-grid">
                    <div class="campo<?= $erro_campo('nome') ?>">
                        <label class="campo__rotulo" for="nome">Nome completo</label>
                        <input class="input" id="nome" name="nome" required minlength="3" maxlength="100" autocomplete="name" value="<?= e($usuario['nome']) ?>">
                        <span class="campo__erro"><?= e($erros['nome'] ?? '') ?></span>
                    </div>
                    <div class="campo<?= $erro_campo('email') ?>">
                        <label class="campo__rotulo" for="email">E-mail</label>
                        <input class="input" type="email" id="email" name="email" required maxlength="150" autocomplete="email" value="<?= e($usuario['email']) ?>">
                        <span class="campo__erro"><?= e($erros['email'] ?? '') ?></span>
                    </div>

                    <?php if ($eh_aluno): ?>
                        <div class="campo<?= $erro_campo('serie') ?>">
                            <label class="campo__rotulo" for="serie">Série</label>
                            <select class="select" id="serie" name="serie">
                                <option value="">Prefiro não informar</option>
                                <?php foreach (SERIES as $valor => $nome_serie): ?>
                                    <option value="<?= e($valor) ?>" <?= ($usuario['serie'] ?? '') === (string) $valor ? 'selected' : '' ?>><?= e($nome_serie) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="campo__erro"><?= e($erros['serie'] ?? '') ?></span>
                        </div>
                        <div class="campo<?= $erro_campo('escola') ?>">
                            <label class="campo__rotulo" for="escola">Escola <span class="texto-secundario">(opcional)</span></label>
                            <input class="input" id="escola" name="escola" maxlength="120" value="<?= e($usuario['escola'] ?? '') ?>">
                            <span class="campo__erro"><?= e($erros['escola'] ?? '') ?></span>
                        </div>
                        <div class="campo<?= $erro_campo('objetivo') ?>">
                            <label class="campo__rotulo" for="objetivo">Objetivo</label>
                            <select class="select" id="objetivo" name="objetivo">
                                <option value="">Ainda não decidi</option>
                                <?php foreach (OBJETIVOS as $opcao): ?>
                                    <option value="<?= e($opcao) ?>" <?= ($usuario['objetivo'] ?? '') === $opcao ? 'selected' : '' ?>><?= e($opcao) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="campo__erro"><?= e($erros['objetivo'] ?? '') ?></span>
                        </div>
                        <div class="campo<?= $erro_campo('curso_desejado') ?>">
                            <label class="campo__rotulo" for="curso_desejado">Curso desejado <span class="texto-secundario">(opcional)</span></label>
                            <input class="input" id="curso_desejado" name="curso_desejado" maxlength="80" placeholder="Ex.: Medicina, Engenharia, Direito"
                                   value="<?= e($usuario['curso_desejado'] ?? '') ?>">
                            <span class="campo__erro"><?= e($erros['curso_desejado'] ?? '') ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-acoes">
                    <button class="btn btn--primario" type="submit">Salvar alterações</button>
                </div>
            </form>
        </section>

        <section class="card" id="senha">
            <div class="card__cabecalho">
                <div>
                    <h2 class="card__titulo">Segurança</h2>
                    <p class="card__subtitulo">Troque sua senha. Por segurança, pedimos a senha atual.</p>
                </div>
                <span class="perfil-icone-secao"><?= icone('cadeado') ?></span>
            </div>
            <form method="post" data-validar>
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="senha">
                <div class="form-grid">
                    <div class="campo campo--inteiro<?= $erro_campo('senha_atual') ?>">
                        <label class="campo__rotulo" for="senha_atual">Senha atual</label>
                        <div class="campo-senha">
                            <input class="input" type="password" id="senha_atual" name="senha_atual" required autocomplete="current-password">
                            <button type="button" class="botao-icone" data-mostrar-senha="senha_atual" aria-label="Mostrar senha"><?= icone('olho', 'icone--sm') ?></button>
                        </div>
                        <span class="campo__erro"><?= e($erros['senha_atual'] ?? '') ?></span>
                    </div>
                    <div class="campo<?= $erro_campo('senha_nova') ?>">
                        <label class="campo__rotulo" for="senha_nova">Nova senha</label>
                        <input class="input" type="password" id="senha_nova" name="senha_nova" required minlength="6" autocomplete="new-password">
                        <div class="forca-senha" aria-live="polite"><span class="forca-senha__barra" id="forca-barra"></span></div>
                        <span class="campo__ajuda" id="forca-texto">Mínimo de 6 caracteres.</span>
                        <span class="campo__erro"><?= e($erros['senha_nova'] ?? '') ?></span>
                    </div>
                    <div class="campo<?= $erro_campo('senha_confirmacao') ?>">
                        <label class="campo__rotulo" for="senha_confirmacao">Confirmar nova senha</label>
                        <input class="input" type="password" id="senha_confirmacao" name="senha_confirmacao" required data-igual-a="senha_nova" autocomplete="new-password">
                        <span class="campo__erro"><?= e($erros['senha_confirmacao'] ?? '') ?></span>
                    </div>
                </div>
                <div class="form-acoes">
                    <button class="btn btn--secundario" type="submit">Alterar senha</button>
                </div>
            </form>
        </section>
    </div>

    <!-- ===================== Coluna lateral ===================== -->
    <aside class="pilha">
        <?php if ($eh_aluno): ?>
            <section class="card perfil-plano <?= eh_premium($usuario) ? 'perfil-plano--premium' : '' ?>">
                <div class="card__cabecalho">
                    <h2 class="card__titulo">Plano</h2>
                    <span class="selo <?= eh_premium($usuario) ? 'selo--premium' : '' ?>"><?= e(rotulo($usuario['plano'])) ?></span>
                </div>
                <?php if ($assinatura):
                    $ciclo = PLANOS_PREMIUM[$assinatura['ciclo']];
                    $renova = date('Y-m-d', strtotime($assinatura['criada_em'] . ' +' . $ciclo['meses'] . ' month'));
                ?>
                    <dl class="perfil-lista">
                        <div><dt>Ciclo</dt><dd><?= e($ciclo['nome']) ?></dd></div>
                        <div><dt>Valor</dt><dd class="numero"><?= e(moeda((float) $assinatura['valor'])) ?></dd></div>
                        <div><dt>Assinante desde</dt><dd><?= e(data_br($assinatura['criada_em'])) ?></dd></div>
                        <div><dt>Renova em</dt><dd><?= e(data_br($renova)) ?></dd></div>
                    </dl>
                    <a class="btn btn--contorno btn--bloco mt-4" href="<?= e(url('aluno/planos.php')) ?>">Gerenciar assinatura</a>
                <?php elseif (eh_premium($usuario)): ?>
                    <p class="texto-pequeno texto-secundario">Você tem acesso Premium.</p>
                <?php else:
                    $restantes = ia_geracoes_restantes($usuario);
                ?>
                    <p class="texto-pequeno texto-secundario">Você usa o plano gratuito. Restam <b class="numero" style="color: var(--text-primary);"><?= (int) $restantes ?> de <?= IA_LIMITE_GRATUITO ?></b> gerações de IA neste mês.</p>
                    <a class="btn btn--primario btn--bloco mt-4" href="<?= e(url('aluno/checkout.php')) ?>"><?= icone('coroa', 'icone--sm') ?> Assinar Premium</a>
                <?php endif; ?>
            </section>

            <?php if ($destaques): ?>
                <section class="card">
                    <div class="card__cabecalho">
                        <h2 class="card__titulo">Suas melhores matérias</h2>
                        <a class="link-ver-todos" href="<?= e(url('aluno/historico.php')) ?>">Histórico</a>
                    </div>
                    <div class="pilha pilha--sm">
                        <?php foreach ($destaques as $m): ?>
                            <div class="barra-materia" style="--cor: <?= cor_materia($m['cor']) ?>;">
                                <span><?= chip_materia($m['nome'], $m['cor'], $m['icone']) ?></span>
                                <span class="barra-materia__trilha"><span class="barra-materia__valor" style="display: block; width: <?= (int) $m['acerto'] ?>%;"></span></span>
                                <span class="barra-materia__numero"><?= (int) $m['acerto'] ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <section class="card">
            <div class="card__cabecalho">
                <h2 class="card__titulo">Preferências</h2>
            </div>
            <div class="preferencia">
                <div>
                    <b>Aparência</b>
                    <span>Escolha entre o modo escuro e o claro.</span>
                </div>
                <div class="segmento" role="radiogroup" aria-label="Tema">
                    <input type="radio" id="tema-escuro" name="tema-preferencia" value="escuro" data-escolher-tema>
                    <label for="tema-escuro"><?= icone('lua', 'icone--xs') ?> Escuro</label>
                    <input type="radio" id="tema-claro" name="tema-preferencia" value="claro" data-escolher-tema>
                    <label for="tema-claro"><?= icone('sol', 'icone--xs') ?> Claro</label>
                </div>
            </div>
        </section>

        <section class="card">
            <div class="card__cabecalho">
                <div>
                    <h2 class="card__titulo">Seus dados</h2>
                    <p class="card__subtitulo">Baixe uma cópia de tudo que está guardado na sua conta (LGPD).</p>
                </div>
            </div>
            <a class="btn btn--secundario btn--bloco" href="<?= e(url('conta/exportar.php')) ?>" download>
                <?= icone('baixar', 'icone--sm') ?> Baixar meus dados (JSON)
            </a>
        </section>

        <?php if ($eh_aluno): ?>
            <section class="card zona-perigo" id="excluir">
                <div class="card__cabecalho">
                    <div>
                        <h2 class="card__titulo">Excluir conta</h2>
                        <p class="card__subtitulo">Apaga seu perfil, simulados, cronograma, revisões e materiais de IA. Não dá para desfazer.</p>
                    </div>
                </div>
                <form method="post" data-validar data-confirmar="Excluir sua conta?"
                      data-confirmar-texto="Todos os seus dados serão apagados para sempre." data-confirmar-botao="Excluir conta">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="excluir">
                    <div class="campo<?= $erro_campo('senha_exclusao') ?>">
                        <label class="campo__rotulo" for="senha_exclusao">Confirme com sua senha</label>
                        <input class="input" type="password" id="senha_exclusao" name="senha_exclusao" required autocomplete="current-password">
                        <span class="campo__erro"><?= e($erros['senha_exclusao'] ?? '') ?></span>
                    </div>
                    <button class="btn btn--perigo btn--bloco mt-4" type="submit"><?= icone('lixeira', 'icone--sm') ?> Excluir minha conta</button>
                </form>
            </section>
        <?php endif; ?>
    </aside>
</div>

<?php if ($secao_com_erro): ?>
<script>document.getElementById(<?= json_encode($secao_com_erro) ?>)?.scrollIntoView({ block: 'center' });</script>
<?php endif; ?>

<?php require RAIZ_APP . '/includes/layout_fim.php'; ?>
