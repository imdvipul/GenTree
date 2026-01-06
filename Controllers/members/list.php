
<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../helpers/response.php";
require_once __DIR__ . "/../../helpers/auth_helper.php";

$user = getAuthUser();
$input = json_decode(file_get_contents("php://input"), true);

/* ================= INPUT ================= */
$familyId  = (int)($input['family_id'] ?? 0);
$page      = max(1, (int)($input['page'] ?? 1));
$limit     = max(1, (int)($input['limit'] ?? 20));
$search    = trim($input['search'] ?? '');
$sortBy    = $input['sort_by'] ?? 'name';
$sortOrder = strtolower($input['sort_order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

if (!$familyId) {
    response(false, "Family ID is required", null, 400);
}

$offset = ($page - 1) * $limit;

/* ================= FIND DEFAULT VIEWPOINT (ONCE) ================= */
$viewerId = null;
// Get viewer's member ID via user_member_links
$viewerId = null;

$stmt = $pdo->prepare("
    SELECT member_id
    FROM user_member_links
    WHERE user_id = ? AND family_id = ?
    LIMIT 1
");
$stmt->execute([$user['id'], $familyId]);

$viewerId = $stmt->fetchColumn();
$viewerId = $viewerId ? (int)$viewerId : null;

// $stmt = $pdo->prepare("SELECT id FROM family_members WHERE family_id = ? AND is_default_viewpoint = 1 AND iddelete = 0 LIMIT 1");
// $stmt->execute([$familyId]);
// $viewerResult = $stmt->fetchColumn();
// if ($viewerResult) {
//     $viewerId = (int)$viewerResult;
// }

/* ================= SORT MAP ================= */
$sortMap = [
    'name'       => "fm.first_name",
    'nickname'   => "fm.nickname",
    'gender'     => "fm.gender",
    'birth_date' => "fm.birth_date"
];
$orderBy = $sortMap[$sortBy] ?? "fm.first_name";

/* ================= SEARCH ================= */
$whereSearch = "";
$params = [$familyId];

if ($search !== "") {
    $whereSearch = "AND (
        fm.first_name LIKE ?
        OR fm.last_name LIKE ?
        OR fm.nickname LIKE ?
    )";
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}

/* ================= MAIN QUERY ================= */
$sql = "
SELECT
    fm.id,
    fm.first_name,
    fm.last_name,
    fm.nickname,
    fm.gender,
    fm.birth_date,

    -- Self (relative to default viewpoint)
    CASE WHEN fm.id = ? THEN 1 ELSE 0 END AS is_self,

    -- Spouse
    EXISTS (
        SELECT 1 FROM relationships r
        WHERE r.person_id = fm.id
          AND r.relation_type = 'spouse'
          AND r.is_active = 1
    ) AS is_married,

    -- Kids count
    (
        SELECT COUNT(*) FROM relationships r
        WHERE r.person_id = fm.id
          AND r.relation_type = 'parent'
          AND r.is_active = 1
    ) AS kids_count

FROM family_members fm
JOIN user_member_links uml ON uml.member_id = fm.id
WHERE uml.family_id = ?
  AND fm.iddelete = 0
  $whereSearch
ORDER BY $orderBy $sortOrder
LIMIT $limit OFFSET $offset
";

$paramsMain = array_merge([$viewerId ?? 0], $params);
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsMain);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= RELATION LABEL HELPER ================= */
function getRelationLabel($pdo, $familyId, $viewerId, $memberId) {
    if ($viewerId == $memberId) return "Self";

    // Level 1: Direct relationships
    $directRel = getDirectRelation($pdo, $familyId, $viewerId, $memberId);
    if ($directRel) return $directRel;

    // Level 2: Grandparents/Grandchildren
    $grandRel = getGrandRelation($pdo, $familyId, $viewerId, $memberId);
    if ($grandRel) return $grandRel;

    // Level 3: Siblings
    if (areSiblings($pdo, $familyId, $viewerId, $memberId)) {
        return getSiblingLabel($pdo, $familyId, $memberId);
    }

    // Level 4: Uncles/Aunts & Cousins (Parental side)
    $extendedRel = getExtendedRelation($pdo, $familyId, $viewerId, $memberId);
    if ($extendedRel) return $extendedRel;

    // Level 5: In-laws
    $inLawRel = getInLawRelation($pdo, $familyId, $viewerId, $memberId);
    if ($inLawRel) return $inLawRel;

    return "Extended Family";
}

/* ================= DIRECT RELATIONS ================= */
function getDirectRelation($pdo, $familyId, $viewerId, $memberId) {
    $stmt = $pdo->prepare("
        SELECT 
            r.relation_type,
            r.person_id as from_id,
            r.related_person_id as to_id,
            fm_member.gender as member_gender
        FROM relationships r
        JOIN family_members fm_member ON fm_member.id = ?
        WHERE r.family_id = ? AND r.is_active = 1
          AND (
            (r.person_id = ? AND r.related_person_id = ?) OR 
            (r.person_id = ? AND r.related_person_id = ?)
          )
        LIMIT 1
    ");
    $stmt->execute([$memberId, $familyId, $viewerId, $memberId, $memberId, $viewerId]);
    $rel = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rel) return null;

    $type = $rel['relation_type'];
    $memberGender = $rel['member_gender'];
    $isViewerParent = ($rel['from_id'] == $viewerId && $type == 'parent');  // Viewer → Member (parent-child)
    $isViewerChild = ($rel['from_id'] == $memberId && $type == 'parent');   // Member → Viewer (child-parent)

    // PARENT/CHILD LOGIC
    if ($type == 'parent' || $type == 'child') {
        if ($isViewerParent) {
            // Viewer is parent → Member is child
             return $memberGender === 'female' ? '👩‍👧 Daughter' : '👦 Son';
        } elseif ($isViewerChild) {
            // Viewer is child → Member is parent
            return $memberGender === 'female' ? '👩 Mother' : '👨 Father';
        }
    }

    // SPOUSE LOGIC (always member gender)
    if ($type == 'spouse') {
        return $memberGender === 'female' ? '💍 Wife' : '💍 Husband';
    }

    return null;
}

/* ================= GRAND RELATIONS ================= */
function getGrandRelation($pdo, $familyId, $viewerId, $memberId) {
    // Viewer -> Parent -> Grandparent
    $viewerParents = getParents($pdo, $familyId, $viewerId);
    foreach ($viewerParents as $parentId) {
        $grandparents = getParents($pdo, $familyId, $parentId);
        foreach ($grandparents as $gpId) {
            if ($gpId == $memberId) {
                $gender = getGender($pdo, $memberId);
                return $gender === 'female' ? '👵 Grandmother' : '👴 Grandfather';
            }
        }
    }

    // Viewer -> Child -> Grandchild
    $viewerChildren = getChildren($pdo, $familyId, $viewerId);
    foreach ($viewerChildren as $childId) {
        $grandchildren = getChildren($pdo, $familyId, $childId);
        foreach ($grandchildren as $gcId) {
            if ($gcId == $memberId) {
                $gender = getGender($pdo, $memberId);
                return $gender === 'female' ? '👧‍ Granddaughter' : '👦‍ Grandson';
            }
        }
    }

    return null;
}

/* ================= SIBLING DETECTION ================= */
function areSiblings($pdo, $familyId, $viewerId, $memberId) {
    $viewerParents = getParents($pdo, $familyId, $viewerId);
    $memberParents = getParents($pdo, $familyId, $memberId);
    
    return count(array_intersect($viewerParents, $memberParents)) > 0;
}

function getSiblingLabel($pdo, $familyId, $memberId) {
    $gender = getGender($pdo, $memberId);
    return $gender === 'female' ? '👭 Sister' : '👬 Brother';
}

/* ================= EXTENDED FAMILY ================= */
function getExtendedRelation($pdo, $familyId, $viewerId, $memberId) {
    $viewerParents = getParents($pdo, $familyId, $viewerId);
    
    foreach ($viewerParents as $parentId) {
        // Uncle/Aunt: Parent's siblings
        $parentSiblings = getSiblingsOfPerson($pdo, $familyId, $parentId);
        
        foreach ($parentSiblings as $siblingId) {  // Loop each sibling
            if ((int)$siblingId === (int)$memberId) {
                $gender = getGender($pdo, $memberId);
                error_log("FOUND AUNT/UNCLE: $memberId gender=$gender");
                return $gender === 'female' ? '👩 Aunt' : '👨 Uncle';
            }
        }

        // Uncle/Aunt-in-law
        foreach ($parentSiblings as $uncleAuntId) {
            $uncleAuntSpouse = getSpouse($pdo, $familyId, $uncleAuntId);
            if ((int)$uncleAuntSpouse === (int)$memberId) {
                $gender = getGender($pdo, $memberId);
                return $gender === 'female' ? '👩‍❤️‍👨 Aunt-in-law' : '👨‍❤️‍👩 Uncle-in-law';
            }
        }
        
        // Cousin: Parent's sibling's children
        foreach ($parentSiblings as $siblingId) {
            $cousins = getChildren($pdo, $familyId, $siblingId);
            if (in_array((int)$memberId, $cousins)) {
                return "👫 Cousin";
            }
        }
    }
    
    return null;
}



/* ================= IN-LAW RELATIONS ================= */
function getInLawRelation($pdo, $familyId, $viewerId, $memberId) {
    // 1. Viewer's spouse's parents/siblings
    $viewerSpouse = getSpouse($pdo, $familyId, $viewerId);
    if ($viewerSpouse) {
        $spouseParents = getParents($pdo, $familyId, $viewerSpouse);
        if (in_array((int)$memberId, $spouseParents)) {
            $gender = getGender($pdo, $memberId);
            return $gender === 'female' ? '👵 Mother-in-law' : '👴 Father-in-law';
        }
        
        $spouseSiblings = getSiblingsOfPerson($pdo, $familyId, $viewerSpouse);
        if (in_array((int)$memberId, $spouseSiblings)) {
            $gender = getGender($pdo, $memberId);
            return $gender === 'female' ? '👭 Sister-in-law' : '👬 Brother-in-law';
        }
    }
    
    // 2. Sibling's spouse = Brother/Sister-in-law
    $viewerSiblings = getSiblingsOfPerson($pdo, $familyId, $viewerId);
    foreach ($viewerSiblings as $siblingId) {
        $siblingSpouse = getSpouse($pdo, $familyId, $siblingId);
        if ((int)$siblingSpouse === (int)$memberId) {
            $gender = getGender($pdo, $memberId);
            return $gender === 'female' ? '👭 Sister-in-law' : '👬 Brother-in-law';
        }
    }
    
    // 3. COUSIN'S SPOUSE = Cousin-in-law (NEW!)
    $viewerParents = getParents($pdo, $familyId, $viewerId);
    foreach ($viewerParents as $parentId) {
        $parentSiblings = getSiblingsOfPerson($pdo, $familyId, $parentId);
        foreach ($parentSiblings as $uncleAuntId) {
            $cousins = getChildren($pdo, $familyId, $uncleAuntId);
            foreach ($cousins as $cousinId) {
                $cousinSpouse = getSpouse($pdo, $familyId, $cousinId);
                if ((int)$cousinSpouse === (int)$memberId) {
                    $gender = getGender($pdo, $memberId);
                    return $gender === 'female' ? '👫‍ Cousin-in-law' : '👫‍ Cousin-in-law';
                }
            }
        }
    }
    
    return null;
}


/* ================= HELPER FUNCTIONS ================= */
function getParents($pdo, $familyId, $personId) {
    $stmt = $pdo->prepare("
        SELECT person_id FROM relationships 
        WHERE family_id = ? AND related_person_id = ? AND relation_type = 'parent' AND is_active = 1
    ");
    $stmt->execute([$familyId, $personId]);
    $parents = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', $parents); // Clean integers
}

function getChildren($pdo, $familyId, $personId) {
    $stmt = $pdo->prepare("
        SELECT related_person_id FROM relationships 
        WHERE family_id = ? AND person_id = ? AND relation_type = 'parent' AND is_active = 1
    ");
    $stmt->execute([$familyId, $personId]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', $children); // Clean integers
}

function getSpouse($pdo, $familyId, $personId) {
    $stmt = $pdo->prepare("
        SELECT related_person_id FROM relationships 
        WHERE family_id = ? AND person_id = ? AND relation_type = 'spouse' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$familyId, $personId]);
    $spouse = $stmt->fetchColumn();
    return $spouse ? (int)$spouse : null;
}

function getSiblingsOfPerson($pdo, $familyId, $personId) {
    $parents = getParents($pdo, $familyId, $personId);
    $siblings = [];
    
    foreach ($parents as $parentId) {
        $children = getChildren($pdo, $familyId, $parentId);
        $siblings = array_merge($siblings, $children);
    }
    
    // Clean array + remove self
    $siblings = array_unique($siblings);
    $siblings = array_diff($siblings, [(int)$personId]);
    
    return array_values($siblings); // Re-index
}

function getGender($pdo, $memberId) {
    $stmt = $pdo->prepare("SELECT gender FROM family_members WHERE id = ?");
    $stmt->execute([(int)$memberId]);
    return $stmt->fetchColumn() ?: 'male';
}

/* ================= FORMAT DATA ================= */
$data = [];

foreach ($members as $m) {
    $name = trim($m['first_name'] . " " . $m['last_name']);

    $age = "N/A";
    // if ($m['birth_date']) {
    //     $age = floor((time() - strtotime($m['birth_date'])) / 31556926) . " yrs";
    // }
    $age = calculateAge($m['birth_date']);

    $data[] = [
        "id"            => (int)$m['id'],
        "name"          => $name,
        "age"           => $age,
        "relation"      => $viewerId ? getRelationLabel($pdo, $familyId, $viewerId, $m['id']) : "Family Member",
        "isSelf"        => (bool)$m['is_self'],
        "isMarried"     => (bool)$m['is_married'],
        "kidsCount"     => (int)$m['kids_count'],
        "gender"    => $m['gender'],
        "storiesCount"  => 0,
        "isDefaultViewpoint" => ($viewerId === (int)$m['id'])
    ];
}

function calculateAge($birthDate) {
    if (!$birthDate) return "N/A";
    
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    
    $interval = $today->diff($birth);
    
    $years = $interval->y;
    $months = $interval->m;
    $days = $interval->d;
    
    $ageStr = '';
    
    if ($years > 0) {
        $ageStr .= $years . 'y ~';
    }
    if ($months > 0) {
        $ageStr .= ($ageStr ? ' ' : '') . $months . 'm ~';
    }
    if ($days > 0 || ($years === 0 && $months === 0)) {
        $ageStr .= ($ageStr ? ' ' : '') . $days . 'd';
    }
    
    return $ageStr ?: '0d';
}


/* ================= TOTAL COUNT ================= */
$countSql = "
SELECT COUNT(*)
FROM family_members fm
JOIN user_member_links uml ON uml.member_id = fm.id
WHERE uml.family_id = ?
  AND fm.iddelete = 0
  $whereSearch
";

$countParams = [$familyId];
if ($search !== "") {
    $like = "%$search%";
    array_push($countParams, $like, $like, $like);
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total = (int)$countStmt->fetchColumn();

/* ================= RESPONSE ================= */
response(true, "Members fetched successfully", [
    "list" => $data,
    "pagination" => [
        "page"    => $page,
        "limit"   => $limit,
        "total"   => $total,
        "hasMore" => ($page * $limit) < $total
    ],
    "viewer_id" => $viewerId // Debug info
]);



// header("Content-Type: application/json");

// require_once __DIR__ . "/../../config/database.php";
// require_once __DIR__ . "/../../helpers/response.php";
// require_once __DIR__ . "/../../helpers/auth_helper.php";

// $user = getAuthUser();
// $input = json_decode(file_get_contents("php://input"), true);

// /* ================= INPUT ================= */
// $familyId  = (int)($input['family_id'] ?? 0);
// $page      = max(1, (int)($input['page'] ?? 1));
// $limit     = max(1, (int)($input['limit'] ?? 20));
// $search    = trim($input['search'] ?? '');
// $sortBy    = $input['sort_by'] ?? 'name';
// $sortOrder = strtolower($input['sort_order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

// if (!$familyId) {
//     response(false, "Family ID is required", null, 400);
// }

// $offset = ($page - 1) * $limit;

// /* ================= SORT MAP ================= */
// $sortMap = [
//     'name'       => "fm.first_name",
//     'nickname'   => "fm.nickname",
//     'gender'     => "fm.gender",
//     'birth_date' => "fm.birth_date"
// ];
// $orderBy = $sortMap[$sortBy] ?? "fm.first_name";

// /* ================= SEARCH ================= */
// $whereSearch = "";
// $params = [$user['id'], $familyId];

// if ($search !== "") {
//     $whereSearch = "AND (
//         fm.first_name LIKE ?
//         OR fm.last_name LIKE ?
//         OR fm.nickname LIKE ?
//     )";
//     $like = "%$search%";
//     array_push($params, $like, $like, $like);
// }

// /* ================= MAIN QUERY ================= */
// $sql = "
// SELECT
//     fm.id,
//     fm.first_name,
//     fm.last_name,
//     fm.nickname,
//     fm.gender,
//     fm.birth_date,

//     -- Self
//     CASE WHEN fm.id = ? THEN 1 ELSE 0 END AS is_self,

//     -- Spouse
//     EXISTS (
//         SELECT 1 FROM relationships r
//         WHERE r.person_id = fm.id
//           AND r.relation_type = 'spouse'
//           AND r.is_active = 1
//     ) AS is_married,

//     -- Kids count
//     (
//         SELECT COUNT(*) FROM relationships r
//         WHERE r.person_id = fm.id
//           AND r.relation_type = 'parent'
//           AND r.is_active = 1
//     ) AS kids_count

// FROM family_members fm
// WHERE fm.family_id = ?
//   AND fm.iddelete = 0
//   $whereSearch
// ORDER BY $orderBy $sortOrder
// LIMIT $limit OFFSET $offset
// ";

// $stmt = $pdo->prepare($sql);
// $stmt->execute($params);
// $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// /* ================= RELATION LABEL HELPER ================= */
// function getRelationLabel($pdo, $familyId, $viewerId, $memberId) {
//     if ($viewerId == $memberId) return "Self";

//     $stmt = $pdo->prepare("
//         SELECT relation_type
//         FROM relationships
//         WHERE family_id = ?
//           AND person_id = ?
//           AND related_person_id = ?
//           AND is_active = 1
//         LIMIT 1
//     ");
//     $stmt->execute([$familyId, $viewerId, $memberId]);
//     $rel = $stmt->fetchColumn();

//     return match ($rel) {
//         'parent' => 'Parent',
//         'child'  => 'Child',
//         'spouse' => 'Spouse',
//         default  => 'Family Member',
//     };
// }

// /* ================= FORMAT DATA ================= */
// $data = [];

// foreach ($members as $m) {
//     $name = trim($m['first_name'] . " " . $m['last_name']);

//     $age = "N/A";
//     if ($m['birth_date']) {
//         $age = floor((time() - strtotime($m['birth_date'])) / 31556926) . " yrs";
//     }

//     $data[] = [
//         "id"            => (int)$m['id'],
//         "name"          => $name,
//         "age"           => $age,
//         "relation"      => getRelationLabel($pdo, $familyId, $user['id'], $m['id']),
//         "isSelf"        => (bool)$m['is_self'],
//         "isMarried"     => (bool)$m['is_married'],
//         "kidsCount"     => (int)$m['kids_count'],
//         "storiesCount"  => 0
//     ];
// }

// /* ================= TOTAL COUNT ================= */
// $countSql = "
// SELECT COUNT(*)
// FROM family_members fm
// WHERE fm.family_id = ?
//   AND fm.iddelete = 0
//   $whereSearch
// ";

// $countParams = [$familyId];
// if ($search !== "") {
//     $like = "%$search%";
//     array_push($countParams, $like, $like, $like);
// }

// $countStmt = $pdo->prepare($countSql);
// $countStmt->execute($countParams);
// $total = (int)$countStmt->fetchColumn();

// /* ================= RESPONSE ================= */
// response(true, "Members fetched successfully", [
//     "list" => $data,
//     "pagination" => [
//         "page"    => $page,
//         "limit"   => $limit,
//         "total"   => $total,
//         "hasMore" => ($page * $limit) < $total
//     ]
// ]);
