<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const DATA_FILE = __DIR__ . '/../storage/data.json';

function loadData(): array
{
    if (!file_exists(DATA_FILE)) {
        seedData();
    }

    $content = file_get_contents(DATA_FILE);
    $data = json_decode((string)$content, true);

    if (!is_array($data)) {
        seedData();
        $data = json_decode((string)file_get_contents(DATA_FILE), true);
    }

    return $data;
}

function saveData(array $data): void
{
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function seedData(): void
{
    $today = date('Y-m-d');
    $data = [
        'users' => [
            [
                'id' => 1,
                'name' => 'Administrador RID',
                'email' => 'admin@rid.com',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'phone' => '(11) 90000-0000',
                'city' => 'Suzano - SP',
                'bio' => 'Administrador inicial do sistema RID.',
                'avatar' => null,
                'created_at' => $today,
            ],
            [
                'id' => 2,
                'name' => 'Carlos Mecânico',
                'email' => 'mecanico@rid.com',
                'password' => password_hash('mecanico123', PASSWORD_DEFAULT),
                'role' => 'mecanico',
                'phone' => '(11) 91111-1111',
                'city' => 'Poá - SP',
                'bio' => 'Profissional de manutenção veicular cadastrado na RID.',
                'avatar' => null,
                'created_at' => $today,
            ],
            [
                'id' => 3,
                'name' => 'João Cliente',
                'email' => 'cliente@rid.com',
                'password' => password_hash('cliente123', PASSWORD_DEFAULT),
                'role' => 'cliente',
                'phone' => '(11) 92222-2222',
                'city' => 'Suzano - SP',
                'bio' => 'Cliente inicial para demonstração do sistema RID.',
                'avatar' => null,
                'created_at' => $today,
            ],
        ],
        'vehicles' => [
            [
                'id' => 1,
                'user_id' => 3,
                'plate' => 'ABC1D23',
                'brand' => 'Ford',
                'model' => 'Courier',
                'year' => 2008,
                'color' => 'Prata',
                'notes' => 'Veículo de exemplo para a RID',
                'created_at' => $today,
            ],
        ],
        'service_categories' => [
            ['id' => 1, 'name' => 'Troca de óleo'],
            ['id' => 2, 'name' => 'Freios'],
            ['id' => 3, 'name' => 'Suspensão'],
            ['id' => 4, 'name' => 'Elétrica'],
            ['id' => 5, 'name' => 'Motor'],
            ['id' => 6, 'name' => 'Diagnóstico'],
            ['id' => 7, 'name' => 'Lavagem técnica'],
        ],
        'services' => [
            [
                'id' => 1,
                'vehicle_id' => 1,
                'category_id' => 1,
                'mechanic_id' => 2,
                'title' => 'Troca de óleo e filtro',
                'description' => 'Serviço executado com troca do óleo lubrificante e do filtro, com conferência visual de vazamentos.',
                'service_date' => $today,
                'status' => 'concluido',
                'parts_used' => 'Óleo 5W30, filtro de óleo',
                'cost' => 180,
                'created_at' => $today,
            ],
        ],
        'service_participants' => [
            ['id' => 1, 'service_id' => 1, 'mechanic_name' => 'Carlos Mecânico', 'role_in_service' => 'Responsável principal'],
        ],
        'service_photos' => [],
    ];

    saveData($data);
}

function nextId(array $items): int
{
    if ($items === []) {
        return 1;
    }
    return max(array_column($items, 'id')) + 1;
}

function findUserByEmail(string $email): ?array
{
    foreach (loadData()['users'] as $user) {
        if (strtolower($user['email']) === strtolower($email)) {
            return $user;
        }
    }
    return null;
}

function findUserById(int $id): ?array
{
    foreach (loadData()['users'] as $user) {
        if ((int)$user['id'] === $id) {
            return $user;
        }
    }
    return null;
}

function updateUser(int $id, array $fields): ?array
{
    $data = loadData();
    foreach ($data['users'] as &$user) {
        if ((int)$user['id'] === $id) {
            foreach ($fields as $key => $value) {
                if (array_key_exists($key, $user)) {
                    $user[$key] = $value;
                }
            }
            saveData($data);
            return $user;
        }
    }
    return null;
}

function createUser(array $payload): array
{
    $data = loadData();
    $payload['id'] = nextId($data['users']);
    $payload['created_at'] = date('Y-m-d');
    $data['users'][] = $payload;
    saveData($data);
    return $payload;
}

function createVehicle(array $payload): array
{
    $data = loadData();
    $payload['id'] = nextId($data['vehicles']);
    $payload['created_at'] = date('Y-m-d');
    $data['vehicles'][] = $payload;
    saveData($data);
    return $payload;
}

function createService(array $payload, array $participants, array $photos): int
{
    $data = loadData();
    $payload['id'] = nextId($data['services']);
    $payload['created_at'] = date('Y-m-d');
    $data['services'][] = $payload;

    foreach ($participants as $participant) {
        $participant['id'] = nextId($data['service_participants']);
        $participant['service_id'] = $payload['id'];
        $data['service_participants'][] = $participant;
    }

    foreach ($photos as $photo) {
        $photo['id'] = nextId($data['service_photos']);
        $photo['service_id'] = $payload['id'];
        $photo['uploaded_at'] = date('Y-m-d');
        $data['service_photos'][] = $photo;
    }

    saveData($data);
    return $payload['id'];
}


function deleteUserById(int $id): bool
{
    $data = loadData();
    $originalCount = count($data['users']);
    $data['users'] = array_values(array_filter($data['users'], fn($u) => (int)$u['id'] !== $id));

    if (count($data['users']) === $originalCount) {
        return false;
    }

    $vehicleIds = array_map(fn($v) => (int)$v['id'], array_filter($data['vehicles'], fn($v) => (int)$v['user_id'] === $id));
    $data['vehicles'] = array_values(array_filter($data['vehicles'], fn($v) => (int)$v['user_id'] !== $id));

    $serviceIds = array_map(fn($s) => (int)$s['id'], array_filter($data['services'], fn($s) => in_array((int)$s['vehicle_id'], $vehicleIds, true) || (int)$s['mechanic_id'] === $id));
    $data['services'] = array_values(array_filter($data['services'], fn($s) => !in_array((int)$s['id'], $serviceIds, true)));
    $data['service_participants'] = array_values(array_filter($data['service_participants'], fn($p) => !in_array((int)$p['service_id'], $serviceIds, true)));
    $data['service_photos'] = array_values(array_filter($data['service_photos'], fn($p) => !in_array((int)$p['service_id'], $serviceIds, true)));

    saveData($data);
    return true;
}

function adminUpdateUser(int $id, array $payload): ?array
{
    $fields = [
        'name' => trim($payload['name'] ?? ''),
        'phone' => trim($payload['phone'] ?? ''),
        'city' => trim($payload['city'] ?? ''),
        'bio' => trim($payload['bio'] ?? ''),
        'role' => in_array(($payload['role'] ?? ''), ['cliente', 'mecanico', 'admin'], true) ? $payload['role'] : 'cliente',
    ];

    return updateUser($id, $fields);
}

function allCategories(): array
{
    $categories = loadData()['service_categories'];
    usort($categories, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $categories;
}

function allUsers(?string $role = null): array
{
    $users = loadData()['users'];
    if ($role !== null) {
        $users = array_values(array_filter($users, fn($u) => $u['role'] === $role));
    }
    usort($users, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $users;
}

function allVehicles(): array
{
    $vehicles = loadData()['vehicles'];
    usort($vehicles, fn($a, $b) => $b['id'] <=> $a['id']);
    return $vehicles;
}

function vehiclesByUser(int $userId): array
{
    return array_values(array_filter(allVehicles(), fn($v) => (int)$v['user_id'] === $userId));
}

function findVehicleById(int $id): ?array
{
    foreach (loadData()['vehicles'] as $vehicle) {
        if ((int)$vehicle['id'] === $id) {
            return $vehicle;
        }
    }
    return null;
}

function countByRole(string $role): int
{
    return count(array_filter(loadData()['users'], fn($u) => $u['role'] === $role));
}

function countItems(string $key): int
{
    return count(loadData()[$key] ?? []);
}

function vehicleLabel(array $vehicle): string
{
    return $vehicle['brand'] . ' ' . $vehicle['model'] . ' (' . $vehicle['plate'] . ')';
}

function serviceWithRelations(array $service): array
{
    $vehicle = findVehicleById((int)$service['vehicle_id']);
    $mechanic = findUserById((int)$service['mechanic_id']);
    $owner = $vehicle ? findUserById((int)$vehicle['user_id']) : null;
    $category = null;
    foreach (allCategories() as $item) {
        if ((int)$item['id'] === (int)($service['category_id'] ?? 0)) {
            $category = $item;
            break;
        }
    }

    return array_merge($service, [
        'brand' => $vehicle['brand'] ?? '',
        'model' => $vehicle['model'] ?? '',
        'plate' => $vehicle['plate'] ?? '',
        'year' => $vehicle['year'] ?? '',
        'color' => $vehicle['color'] ?? '',
        'owner_id' => $vehicle['user_id'] ?? 0,
        'owner_name' => $owner['name'] ?? '',
        'mechanic_name' => $mechanic['name'] ?? '',
        'category_name' => $category['name'] ?? 'Sem categoria',
    ]);
}

function allServices(): array
{
    $services = loadData()['services'];
    usort($services, function ($a, $b) {
        return [$b['service_date'], $b['id']] <=> [$a['service_date'], $a['id']];
    });
    return array_map('serviceWithRelations', $services);
}

function servicesByClient(int $userId): array
{
    return array_values(array_filter(allServices(), fn($s) => (int)$s['owner_id'] === $userId));
}

function servicesByMechanic(int $mechanicId): array
{
    return array_values(array_filter(allServices(), fn($s) => (int)$s['mechanic_id'] === $mechanicId));
}

function findServiceById(int $id): ?array
{
    foreach (allServices() as $service) {
        if ((int)$service['id'] === $id) {
            return $service;
        }
    }
    return null;
}

function participantsByService(int $serviceId): array
{
    return array_values(array_filter(loadData()['service_participants'], fn($p) => (int)$p['service_id'] === $serviceId));
}

function photosByService(int $serviceId): array
{
    $photos = array_values(array_filter(loadData()['service_photos'], fn($p) => (int)$p['service_id'] === $serviceId));
    usort($photos, fn($a, $b) => $b['id'] <=> $a['id']);
    return $photos;
}
