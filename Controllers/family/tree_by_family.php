<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();

$input = json_decode(file_get_contents("php://input"), true);
$familyId = (int)($input['family_id'] ?? 0);

if (!$familyId) {
    response(false, "Family ID required", null, 400);
}

/*
|--------------------------------------------------------------------------
| Load all family members
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, gender, avatar
    FROM family_members
    WHERE family_id = ?
      AND iddelete = 0
");
$stmt->execute([$familyId]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$members) {
    response(true, "No members found", []);
}

/*
|--------------------------------------------------------------------------
| Index members
|--------------------------------------------------------------------------
*/
$memberMap = [];
foreach ($members as $m) {
    $memberMap[$m['id']] = $m;
}

/*
|--------------------------------------------------------------------------
| Load all relationships
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT person_id, related_person_id, relation_type
    FROM relationships
    WHERE family_id = ?
      AND is_active = 1
");
$stmt->execute([$familyId]);
$relations = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| Relationship helpers
|--------------------------------------------------------------------------
*/
$parents = [];
$children = [];
$spouses = [];

foreach ($relations as $r) {
    if ($r['relation_type'] === 'parent') {
        $parents[$r['related_person_id']][] = $r['person_id'];
        $children[$r['person_id']][] = $r['related_person_id'];
    }

    if ($r['relation_type'] === 'spouse') {
        // FIX: Only add if not already present (prevents duplicates)
        $personA = $r['person_id'];
        $personB = $r['related_person_id'];
        
        if (!isset($spouses[$personA]) || !in_array($personB, $spouses[$personA])) {
            $spouses[$personA][] = $personB;
        }
        if (!isset($spouses[$personB]) || !in_array($personA, $spouses[$personB])) {
            $spouses[$personB][] = $personA;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Find roots (members without parents)
|--------------------------------------------------------------------------
*/
$rootIds = [];

foreach ($memberMap as $id => $m) {
    if (!isset($parents[$id])) {
        $rootIds[] = $id;
    }
}

/*
|--------------------------------------------------------------------------
| Build node
|--------------------------------------------------------------------------
*/
function buildNode($id, $memberMap, $parents, $children, $spouses)
{
    $m = $memberMap[$id];

    return [
        "id" => "P$id",
        "data" => [
            "fn" => $m['first_name'],
            "ln" => $m['last_name'],
            "label" => trim($m['first_name'] . " " . $m['last_name']),
            "gender" => strtoupper(substr($m['gender'], 0, 1)),
            "avatar" => $m['avatar'],
        ],
        "rels" => [
            "father" => findParentByGender($id, $parents, $memberMap, 'male'),
            "mother" => findParentByGender($id, $parents, $memberMap, 'female'),
            // "spouses" => array_map(fn($x) => "P$x", $spouses[$id] ?? []),
            "spouses" => array_unique(array_map(fn($x) => "P$x", $spouses[$id] ?? [])),
            "children" => array_map(fn($x) => "P$x", $children[$id] ?? []),
        ],
        "main" => false,
    ];
}

/*
|--------------------------------------------------------------------------
| Parent by gender
|--------------------------------------------------------------------------
*/
function findParentByGender($childId, $parents, $memberMap, $gender)
{
    if (!isset($parents[$childId])) return null;

    foreach ($parents[$childId] as $pid) {
        if (
            isset($memberMap[$pid]) &&
            strtolower($memberMap[$pid]['gender']) === $gender
        ) {
            return "P$pid";
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| Build nodes list
|--------------------------------------------------------------------------
*/
$nodes = [];

foreach ($memberMap as $id => $m) {
    $nodes[] = buildNode($id, $memberMap, $parents, $children, $spouses);
}

/*
|--------------------------------------------------------------------------
| Mark main root
|--------------------------------------------------------------------------
*/
$mainRoot = $rootIds[0] ?? array_key_first($memberMap);

foreach ($nodes as &$node) {
    if ($node['id'] === "P$mainRoot") {
        $node['main'] = true;
    }
}

response(true, "Family tree loaded", $nodes);
