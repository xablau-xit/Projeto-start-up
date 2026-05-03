<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

$route = $_GET['route'] ?? 'home';
$user = currentUser();

function renderHeader(string $title, string $currentRoute): void {
    $user = currentUser();
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> - RID</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
    <header class="topbar">
        <div class="container nav">
            <div class="brand">
                <div class="brand-badge">RID</div>
                <div>
                    <h1>RID</h1>
                    <small>Registro Inteligente de Serviços</small>
                </div>
            </div>
            <nav class="nav-links">
                <a class="<?= $currentRoute === 'home' ? 'active' : '' ?>" href="?route=home">Início</a>
                <?php if ($user): ?>
                    <a class="<?= $currentRoute === 'dashboard' ? 'active' : '' ?>" href="?route=dashboard">Dashboard</a>
                    <a class="<?= $currentRoute === 'vehicles' ? 'active' : '' ?>" href="?route=vehicles">Veículos</a>
                    <a class="<?= $currentRoute === 'services' ? 'active' : '' ?>" href="?route=services">Serviços</a>
                    <a class="<?= $currentRoute === 'profile' ? 'active' : '' ?>" href="?route=profile">Perfil</a>
                    <?php if ($user['role'] === 'admin'): ?>
                        <a class="<?= $currentRoute === 'admin' ? 'active' : '' ?>" href="?route=admin">Admin</a>
                    <?php endif; ?>
                    <a href="?route=logout">Sair</a>
                <?php else: ?>
                    <a class="<?= $currentRoute === 'login' ? 'active' : '' ?>" href="?route=login">Entrar</a>
                    <a class="<?= $currentRoute === 'register' ? 'active' : '' ?>" href="?route=register">Criar conta</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container">
        <?php foreach (getFlashes() as $flash): ?>
            <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
    <?php
}

function renderFooter(): void {
    ?>
    </main>
    <footer class="container footer">
        <p><strong>RID</strong> — Sistema web em PHP para gestão de serviços mecânicos com histórico, fotos e rastreabilidade do profissional.</p>
    </footer>
    <script>
    const inputPhotos = document.getElementById('photosInput');
    const previewBox = document.getElementById('photoPreview');
    if (inputPhotos && previewBox) {
        inputPhotos.addEventListener('change', () => {
            previewBox.innerHTML = '';
            [...inputPhotos.files].forEach(file => {
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = file.name;
                previewBox.appendChild(img);
            });
        });
    }
    </script>
    </body>
    </html>
    <?php
}

if ($route === 'logout') {
    logoutUser();
    flash('success', 'Sessão encerrada com sucesso.');
    redirect('home');
}

if ($route === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (loginUser($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        flash('success', 'Bem-vindo à RID.');
        redirect('dashboard');
    }
    flash('error', 'E-mail ou senha inválidos.');
    redirect('login');
}

if ($route === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    [$ok, $message] = registerUser($_POST);
    flash($ok ? 'success' : 'error', $message);
    redirect($ok ? 'login' : 'register');
}

if ($route === 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    $updated = updateUser((int)$user['id'], [
        'name' => trim($_POST['name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'bio' => trim($_POST['bio'] ?? ''),
    ]);
    if ($updated) {
        unset($updated['password']);
        $_SESSION['user'] = $updated;
    }
    flash('success', 'Perfil atualizado com sucesso.');
    redirect('profile');
}

if ($route === 'admin_update_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['admin']);
    $targetId = (int)($_POST['user_id'] ?? 0);
    if ($targetId <= 0) {
        flash('error', 'Usuário inválido.');
        redirect('admin');
    }
    $updated = adminUpdateUser($targetId, $_POST);
    if ($updated && (int)$user['id'] === $targetId) {
        unset($updated['password']);
        $_SESSION['user'] = $updated;
    }
    flash($updated ? 'success' : 'error', $updated ? 'Usuário atualizado com sucesso.' : 'Não foi possível atualizar o usuário.');
    redirect('admin');
}

if ($route === 'admin_delete_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['admin']);
    $targetId = (int)($_POST['user_id'] ?? 0);
    if ($targetId === (int)$user['id']) {
        flash('error', 'Você não pode excluir a própria conta enquanto está logado.');
        redirect('admin');
    }
    $deleted = $targetId > 0 ? deleteUserById($targetId) : false;
    flash($deleted ? 'success' : 'error', $deleted ? 'Usuário excluído com sucesso.' : 'Não foi possível excluir o usuário.');
    redirect('admin');
}

if ($route === 'vehicles' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['cliente', 'admin']);
    $plate = strtoupper(trim($_POST['plate'] ?? ''));
    foreach (allVehicles() as $vehicle) {
        if ($vehicle['plate'] === $plate) {
            flash('error', 'Já existe um veículo com esta placa.');
            redirect('vehicles');
        }
    }

    createVehicle([
        'user_id' => $user['role'] === 'admin' ? (int)($_POST['owner_id'] ?? $user['id']) : (int)$user['id'],
        'plate' => $plate,
        'brand' => trim($_POST['brand'] ?? ''),
        'model' => trim($_POST['model'] ?? ''),
        'year' => (int)($_POST['year'] ?? date('Y')),
        'color' => trim($_POST['color'] ?? ''),
        'notes' => trim($_POST['notes'] ?? ''),
    ]);
    flash('success', 'Veículo cadastrado com sucesso.');
    redirect('vehicles');
}

if ($route === 'services' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['mecanico', 'admin']);
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($vehicleId <= 0 || $title === '' || $description === '') {
        flash('error', 'Preencha os campos obrigatórios do serviço.');
        redirect('services');
    }

    $participants = [[
        'mechanic_name' => $user['name'],
        'role_in_service' => 'Responsável principal',
    ]];

    $participantsRaw = trim($_POST['participants'] ?? '');
    if ($participantsRaw !== '') {
        foreach (preg_split('/\r\n|\r|\n/', $participantsRaw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            [$name, $roleText] = array_pad(array_map('trim', explode('-', $line, 2)), 2, 'Auxiliar');
            $participants[] = ['mechanic_name' => $name, 'role_in_service' => $roleText ?: 'Auxiliar'];
        }
    }

    $photos = [];
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }
    if (!empty($_FILES['photos']['name'][0])) {
        $count = count($_FILES['photos']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmp = $_FILES['photos']['tmp_name'][$i];
            $original = $_FILES['photos']['name'][$i];
            $size = (int)$_FILES['photos']['size'][$i];
            $mime = mime_content_type($tmp);
            if ($size > MAX_UPLOAD_SIZE || !in_array($mime, ALLOWED_IMAGE_TYPES, true)) continue;
            $ext = pathinfo($original, PATHINFO_EXTENSION) ?: 'jpg';
            $safeName = uniqid('rid_', true) . '.' . strtolower($ext);
            if (move_uploaded_file($tmp, UPLOAD_DIR . $safeName)) {
                $photos[] = [
                    'file_name' => $safeName,
                    'original_name' => $original,
                    'caption' => 'Foto do serviço ' . $title,
                ];
            }
        }
    }

    $serviceId = createService([
        'vehicle_id' => $vehicleId,
        'category_id' => $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null,
        'mechanic_id' => (int)$user['id'],
        'title' => $title,
        'description' => $description,
        'service_date' => $_POST['service_date'] ?? date('Y-m-d'),
        'status' => in_array($_POST['status'] ?? '', ['aberto', 'em_andamento', 'concluido'], true) ? $_POST['status'] : 'concluido',
        'parts_used' => trim($_POST['parts_used'] ?? ''),
        'cost' => (float)($_POST['cost'] ?? 0),
    ], $participants, $photos);

    flash('success', 'Serviço registrado com sucesso.');
    redirect('service_view&id=' . $serviceId);
}

if ($route === 'home') {
    renderHeader('Início', 'home');
    ?>
    <section class="hero">
        <div class="card hero-intro">
            <span class="badge blue">Plataforma RID</span>
            <h2>Controle serviços mecânicos com histórico, fotos e rastreabilidade.</h2>
            <p>
                A RID foi pensada para registrar manutenção veicular de forma clara, rápida e confiável.
                O cliente acompanha o histórico do veículo e o mecânico documenta o serviço com fotos,
                descrição, peças usadas e profissionais envolvidos.
            </p>
            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:20px;">
                <a class="button" href="?route=register">Criar conta</a>
                <a class="button secondary" href="?route=login">Acessar sistema</a>
            </div>
            <div class="kpis">
                <div class="stat"><strong><?= countItems('services') ?></strong><span>Serviços registrados</span></div>
                <div class="stat"><strong><?= countItems('vehicles') ?></strong><span>Veículos cadastrados</span></div>
                <div class="stat"><strong><?= countByRole('mecanico') ?></strong><span>Mecânicos ativos</span></div>
                <div class="stat"><strong>100%</strong><span>Foco em histórico visual</span></div>
            </div>
        </div>
        <div class="card">
            <h3>Como a RID funciona</h3>
            <div class="list-item"><div><strong>1. Cadastro</strong><div class="meta">Cliente ou mecânico criam sua conta.</div></div></div>
            <div class="list-item"><div><strong>2. Veículos</strong><div class="meta">O cliente registra seus veículos e dados básicos.</div></div></div>
            <div class="list-item"><div><strong>3. Serviços</strong><div class="meta">O mecânico registra o serviço, peças, custo e status.</div></div></div>
            <div class="list-item"><div><strong>4. Fotos</strong><div class="meta">As fotos do serviço ficam associadas ao histórico do veículo.</div></div></div>
            <div class="list-item"><div><strong>5. Consulta</strong><div class="meta">O cliente consulta tudo de forma simples e intuitiva.</div></div></div>
        </div>
    </section>
    <?php
    renderFooter();
    exit;
}

if ($route === 'login') {
    renderHeader('Entrar', 'login');
    ?>
    <section class="grid-2" style="padding:30px 0; align-items:start;">
        <div class="card">
            <h2>Entrar na RID</h2>
            <form method="post" action="?route=login">
                <div class="form-group"><label>E-mail</label><input type="email" name="email" required></div>
                <div class="form-group"><label>Senha</label><input type="password" name="password" required></div>
                <button type="submit">Entrar</button>
            </form>
        </div>
        <div class="card">
            <h3>Contas de demonstração</h3>
            <p><strong>Cliente:</strong> cliente@rid.com / cliente123</p>
            <p><strong>Mecânico:</strong> mecanico@rid.com / mecanico123</p>
            <p><strong>Admin:</strong> admin@rid.com / admin123</p>
        </div>
    </section>
    <?php
    renderFooter();
    exit;
}

if ($route === 'register') {
    renderHeader('Criar conta', 'register');
    ?>
    <section style="padding:30px 0;">
        <div class="card">
            <h2>Criar conta na RID</h2>
            <form method="post" action="?route=register">
                <div class="form-grid">
                    <div class="form-group"><label>Nome</label><input type="text" name="name" required></div>
                    <div class="form-group"><label>E-mail</label><input type="email" name="email" required></div>
                    <div class="form-group"><label>Senha</label><input type="password" name="password" required></div>
                    <div class="form-group"><label>Perfil</label>
                        <select name="role"><option value="cliente">Cliente</option><option value="mecanico">Mecânico</option></select>
                    </div>
                    <div class="form-group"><label>Telefone</label><input type="text" name="phone"></div>
                    <div class="form-group"><label>Cidade</label><input type="text" name="city"></div>
                </div>
                <div class="form-group" style="margin-top:16px;"><label>Descrição</label><textarea name="bio" placeholder="Fale brevemente sobre você ou sua oficina"></textarea></div>
                <button type="submit">Criar conta</button>
            </form>
        </div>
    </section>
    <?php
    renderFooter();
    exit;
}

if ($route === 'dashboard') {
    requireLogin();
    $recentServices = $user['role'] === 'cliente' ? array_slice(servicesByClient((int)$user['id']), 0, 5)
        : ($user['role'] === 'mecanico' ? array_slice(servicesByMechanic((int)$user['id']), 0, 5) : array_slice(allServices(), 0, 5));
    renderHeader('Dashboard', 'dashboard');
    ?>
    <section style="padding:30px 0 14px;">
        <div class="section-title">
            <div><h2 style="margin:0;">Olá, <?= e($user['name']) ?></h2><div class="meta">Perfil: <?= e(roleLabel($user['role'])) ?></div></div>
        </div>
        <div class="kpis">
            <?php if ($user['role'] === 'cliente'): ?>
                <div class="stat"><strong><?= count(vehiclesByUser((int)$user['id'])) ?></strong><span>Veículos</span></div>
                <div class="stat"><strong><?= count(servicesByClient((int)$user['id'])) ?></strong><span>Serviços no histórico</span></div>
                <div class="stat"><strong><?= e($user['city'] ?: '-') ?></strong><span>Cidade</span></div>
                <div class="stat"><strong>RID</strong><span>Histórico visual</span></div>
            <?php elseif ($user['role'] === 'mecanico'): ?>
                <div class="stat"><strong><?= count(servicesByMechanic((int)$user['id'])) ?></strong><span>Serviços feitos</span></div>
                <div class="stat"><strong><?= array_sum(array_map(fn($s) => count(photosByService((int)$s['id'])), servicesByMechanic((int)$user['id']))) ?></strong><span>Fotos enviadas</span></div>
                <div class="stat"><strong><?= formatDate(date('Y-m-d')) ?></strong><span>Hoje</span></div>
                <div class="stat"><strong><?= e($user['city'] ?: '-') ?></strong><span>Base</span></div>
            <?php else: ?>
                <div class="stat"><strong><?= countItems('vehicles') ?></strong><span>Veículos</span></div>
                <div class="stat"><strong><?= countItems('services') ?></strong><span>Serviços</span></div>
                <div class="stat"><strong><?= countItems('service_photos') ?></strong><span>Fotos</span></div>
                <div class="stat"><strong><?= countItems('users') ?></strong><span>Usuários</span></div>
            <?php endif; ?>
        </div>
    </section>
    <section class="grid-2" style="padding-bottom:30px; align-items:start;">
        <div class="card">
            <h3>Ações rápidas</h3>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <?php if ($user['role'] !== 'mecanico'): ?><a class="button" href="?route=vehicles">Gerenciar veículos</a><?php endif; ?>
                <?php if ($user['role'] !== 'cliente'): ?><a class="button" href="?route=services">Registrar serviço</a><?php endif; ?>
                <a class="button secondary" href="?route=profile">Editar perfil</a>
            </div>
        </div>
        <div class="card">
            <h3>Últimos serviços</h3>
            <?php if (!$recentServices): ?><p class="meta">Ainda não há serviços registrados.</p><?php else: foreach ($recentServices as $service): ?>
                <div class="list-item">
                    <div><strong><?= e($service['title']) ?></strong><div class="meta"><?= e($service['brand'] . ' ' . $service['model']) ?> • <?= e($service['plate']) ?> • <?= formatDate($service['service_date']) ?></div></div>
                    <a href="?route=service_view&id=<?= (int)$service['id'] ?>">Ver</a>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </section>
    <?php renderFooter(); exit; }

if ($route === 'vehicles') {
    requireRole(['cliente', 'admin']);
    $owners = $user['role'] === 'admin' ? allUsers('cliente') : [];
    $vehicles = $user['role'] === 'admin' ? allVehicles() : vehiclesByUser((int)$user['id']);
    renderHeader('Veículos', 'vehicles');
    ?>
    <section class="grid-2" style="padding:30px 0; align-items:start;">
        <div class="card">
            <h2>Cadastrar veículo</h2>
            <form method="post" action="?route=vehicles">
                <div class="form-grid">
                    <?php if ($user['role'] === 'admin'): ?>
                    <div class="form-group"><label>Cliente</label><select name="owner_id"><?php foreach ($owners as $owner): ?><option value="<?= (int)$owner['id'] ?>"><?= e($owner['name']) ?> — <?= e($owner['email']) ?></option><?php endforeach; ?></select></div>
                    <?php endif; ?>
                    <div class="form-group"><label>Placa</label><input type="text" name="plate" required maxlength="8"></div>
                    <div class="form-group"><label>Marca</label><input type="text" name="brand" required></div>
                    <div class="form-group"><label>Modelo</label><input type="text" name="model" required></div>
                    <div class="form-group"><label>Ano</label><input type="number" name="year" min="1950" max="2100" required></div>
                    <div class="form-group"><label>Cor</label><input type="text" name="color"></div>
                </div>
                <div class="form-group" style="margin-top:16px;"><label>Observações</label><textarea name="notes"></textarea></div>
                <button type="submit">Salvar veículo</button>
            </form>
        </div>
        <div class="card">
            <h2>Veículos cadastrados</h2>
            <?php if (!$vehicles): ?><p class="meta">Nenhum veículo cadastrado até o momento.</p><?php else: foreach ($vehicles as $vehicle): $owner = findUserById((int)$vehicle['user_id']); ?>
                <div class="list-item"><div><strong><?= e($vehicle['brand'] . ' ' . $vehicle['model']) ?></strong><div class="meta">Placa: <?= e($vehicle['plate']) ?> • Ano: <?= (int)$vehicle['year'] ?><?php if ($owner): ?> • Cliente: <?= e($owner['name']) ?><?php endif; ?></div></div></div>
            <?php endforeach; endif; ?>
        </div>
    </section>
    <?php renderFooter(); exit; }

if ($route === 'services') {
    requireRole(['cliente', 'mecanico', 'admin']);
    if ($user['role'] === 'cliente') {
        $services = servicesByClient((int)$user['id']);
        renderHeader('Serviços', 'services'); ?>
        <section style="padding:30px 0;"><div class="card"><h2>Histórico dos seus veículos</h2><div class="table-wrap"><table><thead><tr><th>Data</th><th>Veículo</th><th>Serviço</th><th>Mecânico</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($services as $service): ?><tr><td><?= formatDate($service['service_date']) ?></td><td><?= e($service['brand'] . ' ' . $service['model']) ?><br><span class="meta"><?= e($service['plate']) ?></span></td><td><?= e($service['title']) ?><br><span class="meta"><?= e($service['category_name']) ?></span></td><td><?= e($service['mechanic_name']) ?></td><td><span class="badge <?= $service['status'] === 'concluido' ? 'green' : ($service['status'] === 'em_andamento' ? 'yellow' : 'red') ?>"><?= e(str_replace('_', ' ', $service['status'])) ?></span></td><td><a href="?route=service_view&id=<?= (int)$service['id'] ?>">Detalhes</a></td></tr><?php endforeach; ?>
        </tbody></table></div></div></section>
        <?php renderFooter(); exit; }

    $vehicles = allVehicles();
    $categories = allCategories();
    $services = $user['role'] === 'admin' ? array_slice(allServices(), 0, 12) : array_slice(servicesByMechanic((int)$user['id']), 0, 12);
    renderHeader('Serviços', 'services'); ?>
    <section class="grid-2" style="padding:30px 0; align-items:start;">
        <div class="card">
            <h2>Registrar serviço</h2>
            <form method="post" action="?route=services" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group"><label>Veículo</label><select name="vehicle_id" required><option value="">Selecione</option><?php foreach ($vehicles as $vehicle): $owner = findUserById((int)$vehicle['user_id']); ?><option value="<?= (int)$vehicle['id'] ?>"><?= e(($owner['name'] ?? 'Cliente') . ' — ' . vehicleLabel($vehicle)) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Categoria</label><select name="category_id"><option value="">Sem categoria</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Título do serviço</label><input type="text" name="title" required></div>
                    <div class="form-group"><label>Data</label><input type="date" name="service_date" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group"><label>Status</label><select name="status"><option value="aberto">Aberto</option><option value="em_andamento">Em andamento</option><option value="concluido" selected>Concluído</option></select></div>
                    <div class="form-group"><label>Custo estimado (R$)</label><input type="number" name="cost" min="0" step="0.01"></div>
                </div>
                <div class="form-group" style="margin-top:16px;"><label>Descrição do serviço</label><textarea name="description" required></textarea></div>
                <div class="form-group"><label>Peças utilizadas</label><textarea name="parts_used"></textarea></div>
                <div class="form-group"><label>Participantes adicionais</label><textarea name="participants" placeholder="Um por linha. Ex.: José da Silva - Auxiliar"></textarea></div>
                <div class="form-group"><label>Fotos do serviço</label><input class="file-input" id="photosInput" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple><small class="meta">Formatos aceitos: JPG, PNG ou WEBP. As imagens aparecem na tela de detalhes após salvar.</small><div id="photoPreview" class="photo-preview"></div></div>
                <button type="submit">Salvar serviço</button>
            </form>
        </div>
        <div class="card">
            <h2>Últimos serviços lançados</h2>
            <?php if (!$services): ?><p class="meta">Nenhum serviço registrado ainda.</p><?php else: foreach ($services as $service): ?>
                <div class="list-item"><div><strong><?= e($service['title']) ?></strong><div class="meta"><?= e($service['brand'] . ' ' . $service['model']) ?> • <?= e($service['plate']) ?> • <?= formatDate($service['service_date']) ?></div></div><a href="?route=service_view&id=<?= (int)$service['id'] ?>">Ver</a></div>
            <?php endforeach; endif; ?>
        </div>
    </section>
    <?php renderFooter(); exit; }

if ($route === 'service_view') {
    requireLogin();
    $service = findServiceById((int)($_GET['id'] ?? 0));
    if (!$service) { flash('error', 'Serviço não encontrado.'); redirect('services'); }
    if ($user['role'] === 'cliente' && (int)$service['owner_id'] !== (int)$user['id']) { flash('error', 'Você não pode visualizar este serviço.'); redirect('services'); }
    if ($user['role'] === 'mecanico' && (int)$service['mechanic_id'] !== (int)$user['id']) { flash('error', 'Você não pode visualizar este serviço.'); redirect('services'); }
    $participants = participantsByService((int)$service['id']);
    $photos = photosByService((int)$service['id']);
    renderHeader('Detalhes do serviço', 'services'); ?>
    <section style="padding:30px 0;"><div class="card"><div class="section-title"><div><span class="badge blue"><?= e($service['category_name']) ?></span><h2 style="margin:10px 0 0;"><?= e($service['title']) ?></h2><div class="meta"><?= e($service['brand'] . ' ' . $service['model']) ?> • <?= e($service['plate']) ?> • <?= formatDate($service['service_date']) ?></div></div><span class="badge <?= $service['status'] === 'concluido' ? 'green' : ($service['status'] === 'em_andamento' ? 'yellow' : 'red') ?>"><?= e(str_replace('_', ' ', $service['status'])) ?></span></div>
    <div class="grid-2" style="margin-top:20px; align-items:start;"><div><h3>Resumo</h3><p><?= nl2br(e($service['description'])) ?></p><p><strong>Peças utilizadas:</strong> <?= e($service['parts_used'] ?: 'Não informado') ?></p><p><strong>Custo:</strong> R$ <?= number_format((float)$service['cost'], 2, ',', '.') ?></p><p><strong>Cliente:</strong> <?= e($service['owner_name']) ?></p><p><strong>Mecânico responsável:</strong> <?= e($service['mechanic_name']) ?></p></div><div><h3>Participantes</h3><?php if (!$participants): ?><p class="meta">Nenhum participante adicional informado.</p><?php else: foreach ($participants as $participant): ?><div class="list-item"><div><strong><?= e($participant['mechanic_name']) ?></strong><div class="meta"><?= e($participant['role_in_service']) ?></div></div></div><?php endforeach; endif; ?></div></div>
    <div style="margin-top:24px;"><h3>Fotos do serviço</h3><?php if (!$photos): ?><p class="meta">Nenhuma foto enviada para este serviço.</p><?php else: ?><div class="photos"><?php foreach ($photos as $photo): ?><figure><img src="<?= e(photoUrl($photo['file_name'])) ?>" alt="<?= e($photo['original_name']) ?>"><figcaption><strong><?= e($photo['original_name']) ?></strong><br><?= e($photo['caption'] ?: 'Foto do serviço') ?></figcaption></figure><?php endforeach; ?></div><?php endif; ?></div>
    </div></section>
    <?php renderFooter(); exit; }

if ($route === 'admin') {
    requireRole(['admin']);
    $users = allUsers();
    renderHeader('Administração', 'admin'); ?>
    <section style="padding:30px 0;">
        <div class="section-title"><div><h2 style="margin:0;">Painel do administrador</h2><div class="meta">Edite dados dos usuários ou remova contas que não devem permanecer no sistema.</div></div></div>
        <div class="card">
            <h3>Usuários cadastrados</h3>
            <div class="admin-users">
                <?php foreach ($users as $account): ?>
                    <form class="admin-user-card" method="post" action="?route=admin_update_user">
                        <input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>">
                        <div class="admin-user-head">
                            <div class="avatar-circle"><?= e(strtoupper(substr($account['name'], 0, 1))) ?></div>
                            <div><strong><?= e($account['name']) ?></strong><div class="meta"><?= e($account['email']) ?> • <?= e(roleLabel($account['role'])) ?></div></div>
                        </div>
                        <div class="form-grid compact">
                            <div class="form-group"><label>Nome</label><input type="text" name="name" value="<?= e($account['name']) ?>" required></div>
                            <div class="form-group"><label>Perfil</label><select name="role"><option value="cliente" <?= $account['role'] === 'cliente' ? 'selected' : '' ?>>Cliente</option><option value="mecanico" <?= $account['role'] === 'mecanico' ? 'selected' : '' ?>>Mecânico</option><option value="admin" <?= $account['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option></select></div>
                            <div class="form-group"><label>Telefone</label><input type="text" name="phone" value="<?= e($account['phone'] ?? '') ?>"></div>
                            <div class="form-group"><label>Cidade</label><input type="text" name="city" value="<?= e($account['city'] ?? '') ?>"></div>
                        </div>
                        <div class="form-group"><label>Bio</label><textarea name="bio"><?= e($account['bio'] ?? '') ?></textarea></div>
                        <div class="admin-actions">
                            <button type="submit">Salvar alterações</button>
                        </div>
                    </form>
                    <?php if ((int)$account['id'] !== (int)$user['id']): ?>
                        <form method="post" action="?route=admin_delete_user" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');" class="delete-row">
                            <input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>">
                            <button class="danger" type="submit">Excluir usuário</button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php renderFooter(); exit; }

if ($route === 'profile') {
    requireLogin();
    $profile = findUserById((int)$user['id']);
    renderHeader('Perfil', 'profile'); ?>
    <section class="grid-2" style="padding:30px 0; align-items:start;"><div class="card"><h2>Meu perfil</h2><form method="post" action="?route=profile"><div class="form-grid"><div class="form-group"><label>Nome</label><input type="text" name="name" value="<?= e($profile['name']) ?>" required></div><div class="form-group"><label>E-mail</label><input type="email" value="<?= e($profile['email']) ?>" disabled></div><div class="form-group"><label>Telefone</label><input type="text" name="phone" value="<?= e($profile['phone']) ?>"></div><div class="form-group"><label>Cidade</label><input type="text" name="city" value="<?= e($profile['city']) ?>"></div></div><div class="form-group" style="margin-top:16px;"><label>Bio</label><textarea name="bio"><?= e($profile['bio']) ?></textarea></div><button type="submit">Salvar alterações</button></form></div><div class="card"><h3>Resumo da conta</h3><p><strong>Perfil:</strong> <?= e(roleLabel($profile['role'])) ?></p><p><strong>Cadastrado em:</strong> <?= formatDate($profile['created_at']) ?></p><p><strong>Objetivo da RID:</strong> registrar serviços mecânicos com clareza, imagens e rastreabilidade.</p></div></section>
    <?php renderFooter(); exit; }

flash('error', 'Página não encontrada.');
redirect('home');
