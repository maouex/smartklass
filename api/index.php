<?php
// ============================================================================
// SmartKlass - API Router
// ============================================================================

$config_file = file_exists(__DIR__ . '/config.php')
    ? __DIR__ . '/config.php'
    : '/home/tama0496/smartklass_config.php';
require_once $config_file;
setupHeaders();

// Parse la route : /api/index.php/resource/id
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$path = trim(str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH)), '/');
$parts = explode('/', $path);

// Retirer "index.php" si présent
if (isset($parts[0]) && $parts[0] === 'index.php') {
    array_shift($parts);
}

$resource = $parts[0] ?? '';
$id = $parts[1] ?? null;
$action = $parts[2] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    
    switch ($resource) {
        
        // =====================================================================
        // AUTH - Connexion
        // =====================================================================
        case 'auth':
            if ($method === 'POST') {
                $body = getJsonBody();
                $type = $body['type'] ?? '';
                
                if ($type === 'student') {
                    $identifier = trim($body['identifier'] ?? '');
                    $password = $body['password'] ?? '';
                    $stmt = $db->prepare('SELECT * FROM students WHERE LOWER(identifier) = LOWER(?)');
                    $stmt->execute([$identifier]);
                    $student = $stmt->fetch();
                    if ($student) {
                        // Sauvegarder la date de dernière connexion AVANT mise à jour (pour compter les nouveautés)
                        $previousLastActive = $student['last_active'];

                        // Calcul du streak basé sur les jours consécutifs
                        $today     = date('Y-m-d');
                        $yesterday = date('Y-m-d', strtotime('-1 day'));
                        $lastDate  = $previousLastActive ? date('Y-m-d', strtotime($previousLastActive)) : null;
                        if ($lastDate === null)             $newStreak = 1;
                        elseif ($lastDate === $today)       $newStreak = $student['streak']; // déjà connecté aujourd'hui
                        elseif ($lastDate === $yesterday)   $newStreak = $student['streak'] + 1;
                        else                               $newStreak = 1; // jour sauté

                        // Compter les nouveaux cours et activités depuis la dernière connexion
                        $newCourses = 0;
                        $newActivities = 0;
                        if ($previousLastActive) {
                            $stmtC = $db->prepare('SELECT COUNT(*) FROM courses c INNER JOIN course_classes cc ON c.id = cc.course_id WHERE cc.class_id = ? AND c.created_at > ?');
                            $stmtC->execute([$student['class_id'], $previousLastActive]);
                            $newCourses = (int)$stmtC->fetchColumn();
                            $stmtA = $db->prepare('SELECT COUNT(*) FROM activities a INNER JOIN activity_classes ac ON a.id = ac.activity_id WHERE ac.class_id = ? AND a.created_at > ?');
                            $stmtA->execute([$student['class_id'], $previousLastActive]);
                            $newActivities = (int)$stmtA->fetchColumn();
                        }
                        $newsData = ['newCourses' => $newCourses, 'newActivities' => $newActivities];

                        // Pas de mdp encore = première connexion
                        if (empty($student['password'])) {
                            $db->prepare('UPDATE students SET streak = ?, last_active = NOW() WHERE id = ?')->execute([$newStreak, $student['id']]);
                            $s2 = $db->prepare('SELECT * FROM students WHERE id = ?'); $s2->execute([$student['id']]); $student = $s2->fetch();
                            jsonResponse(['success' => true, 'type' => 'student', 'student' => formatStudent($student), 'needsPassword' => true, 'news' => $newsData]);
                        } else {
                            // Vérifier le mot de passe
                            if ($password === $student['password']) {
                                $db->prepare('UPDATE students SET streak = ?, last_active = NOW() WHERE id = ?')->execute([$newStreak, $student['id']]);
                                $s2 = $db->prepare('SELECT * FROM students WHERE id = ?'); $s2->execute([$student['id']]); $student = $s2->fetch();
                                jsonResponse(['success' => true, 'type' => 'student', 'student' => formatStudent($student), 'needsPassword' => false, 'news' => $newsData]);
                            } else {
                                jsonResponse(['success' => false, 'error' => 'Mot de passe incorrect.'], 401);
                            }
                        }
                    } else {
                        jsonResponse(['success' => false, 'error' => 'Identifiant introuvable'], 401);
                    }
                } elseif ($type === 'teacher') {
                    $password = $body['password'] ?? '';
                    $stmt = $db->prepare("SELECT config_value FROM config WHERE config_key = 'teacher_password'");
                    $stmt->execute();
                    $stored = $stmt->fetchColumn();
                    if ($password === $stored) {
                        jsonResponse(['success' => true, 'type' => 'teacher']);
                    } else {
                        jsonResponse(['success' => false, 'error' => 'Mot de passe incorrect'], 401);
                    }
                } elseif ($type === 'set-password') {
                    // Élève définit son mot de passe (première connexion)
                    $studentId = $body['studentId'] ?? '';
                    $newPassword = $body['newPassword'] ?? '';
                    if (strlen($newPassword) < 4) {
                        jsonResponse(['success' => false, 'error' => 'Le mot de passe doit faire au moins 4 caractères.'], 400);
                    }
                    $stmt = $db->prepare('UPDATE students SET password = ? WHERE id = ?');
                    $stmt->execute([$newPassword, $studentId]);
                    if ($stmt->rowCount() === 0) {
                        jsonResponse(['success' => false, 'error' => 'Élève introuvable.'], 404);
                    }
                    jsonResponse(['success' => true]);
                } elseif ($type === 'reset-password') {
                    // Prof réinitialise le mdp d'un élève
                    $studentId = $body['studentId'] ?? '';
                    $db->prepare('UPDATE students SET password = NULL WHERE id = ?')->execute([$studentId]);
                    jsonResponse(['success' => true]);
                } elseif ($type === 'change-teacher-password') {
                    // Prof change son propre mot de passe
                    $currentPassword = $body['currentPassword'] ?? '';
                    $newPassword = $body['newPassword'] ?? '';
                    if (strlen($newPassword) < 4) {
                        jsonResponse(['success' => false, 'error' => 'Le nouveau mot de passe doit faire au moins 4 caractères.'], 400);
                    }
                    $stmt = $db->prepare("SELECT config_value FROM config WHERE config_key = 'teacher_password'");
                    $stmt->execute();
                    $stored = $stmt->fetchColumn();
                    if ($currentPassword !== $stored) {
                        jsonResponse(['success' => false, 'error' => 'Mot de passe actuel incorrect.'], 401);
                    }
                    $db->prepare("UPDATE config SET config_value = ? WHERE config_key = 'teacher_password'")->execute([$newPassword]);
                    jsonResponse(['success' => true]);
                }
            }
            break;
        
        // =====================================================================
        // SUBJECTS - Matières
        // =====================================================================
        case 'subjects':
            if ($method === 'GET') {
                $stmt = $db->query('SELECT * FROM subjects ORDER BY name');
                jsonResponse($stmt->fetchAll());
            } elseif ($method === 'POST') {
                $body = getJsonBody();
                $newId = generateId();
                $stmt = $db->prepare('INSERT INTO subjects (id, name, color, icon) VALUES (?, ?, ?, ?)');
                $stmt->execute([$newId, $body['name'], $body['color'] ?? '#6C5CE7', $body['icon'] ?? '📊']);
                jsonResponse(['id' => $newId, 'name' => $body['name'], 'color' => $body['color'], 'icon' => $body['icon']], 201);
            } elseif ($method === 'PUT' && $id) {
                $body = getJsonBody();
                $stmt = $db->prepare('UPDATE subjects SET name = ?, color = ?, icon = ? WHERE id = ?');
                $stmt->execute([$body['name'], $body['color'], $body['icon'], $id]);
                jsonResponse(['success' => true]);
            } elseif ($method === 'DELETE' && $id) {
                $db->prepare('DELETE FROM subjects WHERE id = ?')->execute([$id]);
                jsonResponse(['success' => true]);
            }
            break;
        
        // =====================================================================
        // CLASSES
        // =====================================================================
        case 'classes':
            if ($method === 'GET') {
                $stmt = $db->query('SELECT * FROM classes ORDER BY name');
                jsonResponse($stmt->fetchAll());
            } elseif ($method === 'POST') {
                $body = getJsonBody();
                $newId = generateId();
                $stmt = $db->prepare('INSERT INTO classes (id, name, year) VALUES (?, ?, ?)');
                $stmt->execute([$newId, $body['name'], $body['year'] ?? '2025-2026']);
                jsonResponse(['id' => $newId, 'name' => $body['name'], 'year' => $body['year']], 201);
            } elseif ($method === 'PUT' && $id) {
                $body = getJsonBody();
                $stmt = $db->prepare('UPDATE classes SET name = ?, year = ? WHERE id = ?');
                $stmt->execute([$body['name'], $body['year'], $id]);
                jsonResponse(['success' => true]);
            } elseif ($method === 'DELETE' && $id) {
                $db->prepare('DELETE FROM students WHERE class_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM classes WHERE id = ?')->execute([$id]);
                jsonResponse(['success' => true]);
            }
            break;
        
        // =====================================================================
        // STUDENTS - Élèves
        // =====================================================================
        case 'students':
            if ($method === 'GET') {
                if ($id === 'results' && $action) {
                    // GET /students/results/{studentId}
                    $studentId = $action;
                    $stmt = $db->prepare('SELECT r.*, a.title as activity_title FROM results r LEFT JOIN activities a ON r.activity_id = a.id WHERE r.student_id = ? ORDER BY r.completed_at DESC');
                    $stmt->execute([$studentId]);
                    jsonResponse($stmt->fetchAll());
                } else {
                    $stmt = $db->query('SELECT * FROM students ORDER BY last_name, first_name');
                    $students = $stmt->fetchAll();
                    jsonResponse(array_map('formatStudent', $students));
                }
            } elseif ($method === 'POST') {
                $body = getJsonBody();
                $newId = generateId();
                // Générer l'identifiant
                $classStmt = $db->prepare('SELECT name FROM classes WHERE id = ?');
                $classStmt->execute([$body['classId']]);
                $className = $classStmt->fetchColumn() ?: 'STD';
                $tag = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $className), 0, 4));
                $countStmt = $db->prepare('SELECT COUNT(*) FROM students WHERE class_id = ?');
                $countStmt->execute([$body['classId']]);
                $count = $countStmt->fetchColumn() + 1;
                $identifier = 'STMG-' . $tag . '-' . strtoupper(substr($body['lastName'], 0, 4)) . $count;
                
                $stmt = $db->prepare('INSERT INTO students (id, first_name, last_name, class_id, identifier, xp, streak) VALUES (?, ?, ?, ?, ?, 0, 0)');
                $stmt->execute([$newId, $body['firstName'], $body['lastName'], $body['classId'], $identifier]);
                jsonResponse(['id' => $newId, 'identifier' => $identifier], 201);
            } elseif ($method === 'PUT' && $id) {
                $body = getJsonBody();
                $stmt = $db->prepare('UPDATE students SET first_name = ?, last_name = ?, class_id = ? WHERE id = ?');
                $stmt->execute([$body['firstName'], $body['lastName'], $body['classId'], $id]);
                jsonResponse(['success' => true]);
            } elseif ($method === 'DELETE' && $id) {
                $db->prepare('DELETE FROM results WHERE student_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM students WHERE id = ?')->execute([$id]);
                jsonResponse(['success' => true]);
            }
            break;
        
        // =====================================================================
        // COURSES - Cours
        // =====================================================================
        case 'courses':
            if ($method === 'GET') {
                $stmt = $db->query('SELECT c.*, GROUP_CONCAT(cc.class_id) as class_ids FROM courses c LEFT JOIN course_classes cc ON c.id = cc.course_id GROUP BY c.id ORDER BY c.subject_id, c.position ASC, c.created_at ASC');
                $courses = $stmt->fetchAll();
                foreach ($courses as &$course) {
                    $course['classIds'] = $course['class_ids'] ? explode(',', $course['class_ids']) : [];
                    $course['chapters'] = json_decode($course['chapters'], true) ?? [];
                    $course['youtubeUrl'] = $course['youtube_url'] ?? null;
                    $course['position'] = (int)($course['position'] ?? 0);
                    unset($course['class_ids'], $course['youtube_url']);
                }
                jsonResponse($courses);
            } elseif ($method === 'POST') {
                $body = getJsonBody();
                // Réordonnancement des cours (PATCH non supporté sur O2switch)
                if (isset($body['action']) && $body['action'] === 'reorder') {
                    $stmt = $db->prepare('UPDATE courses SET position=? WHERE id=?');
                    foreach ($body['orders'] as $item) {
                        $stmt->execute([(int)$item['position'], $item['id']]);
                    }
                    jsonResponse(['success' => true]);
                }
                $newId = generateId();
                $stmt = $db->prepare('INSERT INTO courses (id, subject_id, title, description, chapters, youtube_url) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$newId, $body['subjectId'], $body['title'], $body['description'] ?? '', json_encode($body['chapters'] ?? [], JSON_UNESCAPED_UNICODE), $body['youtubeUrl'] ?? null]);
                // Liaison classes
                if (!empty($body['classIds'])) {
                    $ins = $db->prepare('INSERT INTO course_classes (course_id, class_id) VALUES (?, ?)');
                    foreach ($body['classIds'] as $classId) {
                        $ins->execute([$newId, $classId]);
                    }
                }
                jsonResponse(['id' => $newId], 201);
            } elseif ($method === 'PUT' && $id) {
                $body = getJsonBody();
                $db->prepare('UPDATE courses SET subject_id=?, title=?, description=?, chapters=?, youtube_url=? WHERE id=?')
                   ->execute([$body['subjectId'], $body['title'], $body['description'] ?? '', json_encode($body['chapters'] ?? [], JSON_UNESCAPED_UNICODE), $body['youtubeUrl'] ?? null, $id]);
                $db->prepare('DELETE FROM course_classes WHERE course_id = ?')->execute([$id]);
                if (!empty($body['classIds'])) {
                    $ins = $db->prepare('INSERT INTO course_classes (course_id, class_id) VALUES (?, ?)');
                    foreach ($body['classIds'] as $classId) {
                        $ins->execute([$id, $classId]);
                    }
                }
                jsonResponse(['success' => true]);
            } elseif ($method === 'PATCH') {
                $body = getJsonBody();
                if (isset($body['orders'])) {
                    $stmt = $db->prepare('UPDATE courses SET position=? WHERE id=?');
                    foreach ($body['orders'] as $item) {
                        $stmt->execute([(int)$item['position'], $item['id']]);
                    }
                    jsonResponse(['success' => true]);
                }
            } elseif ($method === 'DELETE' && $id) {
                $db->prepare('DELETE FROM course_classes WHERE course_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM courses WHERE id = ?')->execute([$id]);
                jsonResponse(['success' => true]);
            }
            break;

        // =====================================================================
        // ACTIVITIES - Activités
        // =====================================================================
        case 'activities':
            if ($method === 'GET') {
                if ($id === 'stats' && $action) {
                    // GET /activities/stats/{activityId}
                    $actId = $action;
                    $stmt = $db->prepare('SELECT r.*, s.first_name, s.last_name FROM results r LEFT JOIN students s ON r.student_id = s.id WHERE r.activity_id = ? ORDER BY r.score DESC');
                    $stmt->execute([$actId]);
                    jsonResponse($stmt->fetchAll());
                } else {
                    $stmt = $db->query('SELECT a.*, GROUP_CONCAT(ac.class_id) as class_ids FROM activities a LEFT JOIN activity_classes ac ON a.id = ac.activity_id GROUP BY a.id ORDER BY a.created_at DESC');
                    $activities = $stmt->fetchAll();
                    foreach ($activities as &$act) {
                        $act['classIds'] = $act['class_ids'] ? explode(',', $act['class_ids']) : [];
                        $act['data'] = json_decode($act['data'], true);
                        unset($act['class_ids']);
                    }
                    jsonResponse($activities);
                }
            } elseif ($method === 'POST') {
                $body = getJsonBody();
                $newId = generateId();
                $courseId = !empty($body['courseId']) ? $body['courseId'] : null;
                $stmt = $db->prepare('INSERT INTO activities (id, subject_id, course_id, title, type, difficulty, xp_reward, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$newId, $body['subjectId'], $courseId, $body['title'], $body['type'], $body['difficulty'] ?? 2, $body['xpReward'] ?? 40, json_encode($body['data'], JSON_UNESCAPED_UNICODE)]);
                if (!empty($body['classIds'])) {
                    $ins = $db->prepare('INSERT INTO activity_classes (activity_id, class_id) VALUES (?, ?)');
                    foreach ($body['classIds'] as $classId) {
                        $ins->execute([$newId, $classId]);
                    }
                }
                jsonResponse(['id' => $newId], 201);
            } elseif ($method === 'PUT' && $id) {
                $body = getJsonBody();
                $courseId = !empty($body['courseId']) ? $body['courseId'] : null;
                $db->prepare('UPDATE activities SET subject_id=?, course_id=?, title=?, difficulty=?, xp_reward=?, data=? WHERE id=?')
                   ->execute([$body['subjectId'], $courseId, $body['title'], $body['difficulty'] ?? 2, $body['xpReward'] ?? 40, json_encode($body['data'], JSON_UNESCAPED_UNICODE), $id]);
                $db->prepare('DELETE FROM activity_classes WHERE activity_id = ?')->execute([$id]);
                if (!empty($body['classIds'])) {
                    $ins = $db->prepare('INSERT INTO activity_classes (activity_id, class_id) VALUES (?, ?)');
                    foreach ($body['classIds'] as $classId) {
                        $ins->execute([$id, $classId]);
                    }
                }
                jsonResponse(['success' => true]);
            } elseif ($method === 'DELETE' && $id) {
                $db->prepare('DELETE FROM results WHERE activity_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM activity_classes WHERE activity_id = ?')->execute([$id]);
                $db->prepare('DELETE FROM activities WHERE id = ?')->execute([$id]);
                jsonResponse(['success' => true]);
            }
            break;
        
        // =====================================================================
        // RESULTS - Résultats
        // =====================================================================
        case 'results':
            if ($method === 'GET') {
                $stmt = $db->query('SELECT * FROM results ORDER BY completed_at DESC');
                jsonResponse($stmt->fetchAll());
            } elseif ($method === 'POST') {
                $body = getJsonBody();
                $newId = generateId();
                $stmt = $db->prepare('INSERT INTO results (id, student_id, activity_id, score) VALUES (?, ?, ?, ?)');
                $stmt->execute([$newId, $body['studentId'], $body['activityId'], $body['score']]);
                
                // Mettre à jour XP et streak de l'élève
                $actStmt = $db->prepare('SELECT xp_reward FROM activities WHERE id = ?');
                $actStmt->execute([$body['activityId']]);
                $xpReward = $actStmt->fetchColumn() ?: 0;
                
                $db->prepare('UPDATE students SET xp = xp + ?, last_active = NOW() WHERE id = ?')
                   ->execute([$xpReward, $body['studentId']]);
                
                // Récupérer l'élève mis à jour
                $stuStmt = $db->prepare('SELECT * FROM students WHERE id = ?');
                $stuStmt->execute([$body['studentId']]);
                $student = $stuStmt->fetch();
                
                jsonResponse(['id' => $newId, 'student' => formatStudent($student)], 201);
            }
            break;
        
        // =====================================================================
        // DATA - Charger toutes les données d'un coup
        // =====================================================================
        case 'data':
            if ($method === 'GET') {
                $subjects = $db->query('SELECT * FROM subjects ORDER BY name')->fetchAll();
                $classes = $db->query('SELECT * FROM classes ORDER BY name')->fetchAll();
                $students = array_map('formatStudent', $db->query('SELECT * FROM students ORDER BY last_name')->fetchAll());
                
                // Courses avec classIds
                $coursesRaw = $db->query('SELECT c.*, GROUP_CONCAT(cc.class_id) as class_ids FROM courses c LEFT JOIN course_classes cc ON c.id = cc.course_id GROUP BY c.id ORDER BY c.subject_id, c.position ASC, c.created_at ASC')->fetchAll();
                $courses = [];
                foreach ($coursesRaw as $c) {
                    $c['classIds'] = $c['class_ids'] ? explode(',', $c['class_ids']) : [];
                    $c['chapters'] = json_decode($c['chapters'], true) ?? [];
                    $c['subjectId'] = $c['subject_id'];
                    $c['youtubeUrl'] = $c['youtube_url'] ?? null;
                    $c['position'] = (int)($c['position'] ?? 0);
                    unset($c['class_ids'], $c['subject_id'], $c['youtube_url']);
                    $courses[] = $c;
                }
                
                // Activities avec classIds
                $actsRaw = $db->query('SELECT a.*, GROUP_CONCAT(ac.class_id) as class_ids FROM activities a LEFT JOIN activity_classes ac ON a.id = ac.activity_id GROUP BY a.id')->fetchAll();
                $activities = [];
                foreach ($actsRaw as $a) {
                    $a['classIds'] = $a['class_ids'] ? explode(',', $a['class_ids']) : [];
                    $a['data'] = json_decode($a['data'], true);
                    $a['subjectId'] = $a['subject_id'];
                    $a['courseId'] = $a['course_id'] ?? null;
                    $a['xpReward'] = (int)$a['xp_reward'];
                    $a['difficulty'] = (int)$a['difficulty'];
                    unset($a['class_ids'], $a['subject_id'], $a['course_id'], $a['xp_reward']);
                    $activities[] = $a;
                }
                
                $results = $db->query('SELECT * FROM results ORDER BY completed_at DESC')->fetchAll();
                $formattedResults = [];
                foreach ($results as $r) {
                    $formattedResults[] = [
                        'id' => $r['id'],
                        'studentId' => $r['student_id'],
                        'activityId' => $r['activity_id'],
                        'score' => (int)$r['score'],
                        'completedAt' => $r['completed_at'],
                    ];
                }
                
                // Config
                $configStmt = $db->query("SELECT * FROM config");
                $config = [];
                foreach ($configStmt->fetchAll() as $row) {
                    $config[$row['config_key']] = $row['config_value'];
                }
                
                jsonResponse([
                    'teacher' => [
                        'name' => $config['teacher_name'] ?? 'Professeur',
                        'password' => $config['teacher_password'] ?? 'smartklass2024',
                    ],
                    'subjects' => $subjects,
                    'classes' => $classes,
                    'students' => $students,
                    'courses' => $courses,
                    'activities' => $activities,
                    'results' => $formattedResults,
                ]);
            }
            break;
        
        // =====================================================================
        // VAPID PUBLIC KEY — Génère les clés VAPID si absentes, retourne la publique
        // =====================================================================
        case 'vapid-key':
            if ($method === 'GET') {
                $pub = $db->query("SELECT config_value FROM config WHERE config_key='vapid_public_key'")->fetchColumn();
                if (!$pub) {
                    // Générer la paire de clés P-256
                    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
                    $d = openssl_pkey_get_details($key);
                    $pubRaw = "\x04"
                        . str_pad($d['ec']['x'], 32, "\x00", STR_PAD_LEFT)
                        . str_pad($d['ec']['y'], 32, "\x00", STR_PAD_LEFT);
                    openssl_pkey_export($key, $privPem);
                    // Extraire les octets DER bruts (SEC1) depuis le PEM
                    $privDer = base64_decode(preg_replace('/-----.*?-----|\s/s', '', $privPem));
                    $pubB64u = rtrim(strtr(base64_encode($pubRaw), '+/', '-_'), '=');
                    $privB64u = rtrim(strtr(base64_encode($privDer), '+/', '-_'), '=');
                    $db->prepare("INSERT INTO config (config_key, config_value) VALUES ('vapid_public_key',?) ON DUPLICATE KEY UPDATE config_value=?")->execute([$pubB64u, $pubB64u]);
                    $db->prepare("INSERT INTO config (config_key, config_value) VALUES ('vapid_private_key',?) ON DUPLICATE KEY UPDATE config_value=?")->execute([$privB64u, $privB64u]);
                    $pub = $pubB64u;
                }
                jsonResponse(['key' => $pub]);
            }
            break;

        // =====================================================================
        // PUSH SUBSCRIBE — Enregistre / supprime une souscription push
        // =====================================================================
        case 'push-subscribe':
            if ($method === 'POST') {
                $body = getJsonBody();
                $studentId = $body['studentId'] ?? '';
                $endpoint  = $body['subscription']['endpoint'] ?? '';
                $p256dh    = $body['subscription']['keys']['p256dh'] ?? '';
                $auth      = $body['subscription']['keys']['auth'] ?? '';
                if (!$studentId || !$endpoint || !$p256dh || !$auth) {
                    jsonResponse(['error' => 'Données manquantes'], 400);
                }
                $id = generateId();
                $db->prepare(
                    "INSERT INTO push_subscriptions (id, student_id, endpoint, p256dh, auth)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE student_id=?, p256dh=?, auth=?"
                )->execute([$id, $studentId, $endpoint, $p256dh, $auth, $studentId, $p256dh, $auth]);
                jsonResponse(['success' => true]);
            } elseif ($method === 'DELETE') {
                $studentId = $_GET['studentId'] ?? '';
                if ($studentId) {
                    $db->prepare("DELETE FROM push_subscriptions WHERE student_id=?")->execute([$studentId]);
                }
                jsonResponse(['success' => true]);
            }
            break;

        // =====================================================================
        // PUSH SEND — Envoie une notification push à tous les abonnés d'une classe
        // =====================================================================
        case 'push-send':
            if ($method === 'POST') {
                require_once __DIR__ . '/web-push.php';
                $body = getJsonBody();

                // Vérification mot de passe prof
                $stored = $db->query("SELECT config_value FROM config WHERE config_key='teacher_password'")->fetchColumn();
                if (($body['teacherPassword'] ?? '') !== $stored) {
                    jsonResponse(['error' => 'Non autorisé'], 401);
                }

                $classId = $body['classId'] ?? '';
                $title   = $body['title'] ?? 'SmartKlass';
                $msgBody = $body['body'] ?? '';
                $payload = json_encode(['title' => $title, 'body' => $msgBody]);

                // Récupérer les clés VAPID
                $pubB64u  = $db->query("SELECT config_value FROM config WHERE config_key='vapid_public_key'")->fetchColumn();
                $privB64u = $db->query("SELECT config_value FROM config WHERE config_key='vapid_private_key'")->fetchColumn();
                if (!$pubB64u || !$privB64u) {
                    jsonResponse(['error' => 'Clés VAPID manquantes — appelez /api/vapid-key d\'abord'], 500);
                }
                $pubRaw  = base64_decode(strtr($pubB64u, '-_', '+/'));
                $privDer = base64_decode(strtr($privB64u, '-_', '+/'));

                // Récupérer les souscriptions des élèves de la classe
                $stmt = $db->prepare(
                    "SELECT ps.* FROM push_subscriptions ps
                     JOIN students s ON ps.student_id = s.id
                     WHERE s.class_id = ?"
                );
                $stmt->execute([$classId]);
                $subs = $stmt->fetchAll();

                $sent = 0; $failed = [];
                foreach ($subs as $sub) {
                    if (send_push($sub, $payload, $privDer, $pubRaw)) {
                        $sent++;
                    } else {
                        $failed[] = $sub['id'];
                    }
                }

                // Nettoyer les souscriptions expirées (HTTP 404/410)
                if (!empty($failed)) {
                    $placeholders = implode(',', array_fill(0, count($failed), '?'));
                    $db->prepare("DELETE FROM push_subscriptions WHERE id IN ($placeholders)")->execute($failed);
                }

                // Sauvegarder dans l'historique
                $db->prepare('INSERT INTO notification_history (id, class_id, title, body, sent_count) VALUES (?, ?, ?, ?, ?)')
                   ->execute([generateId(), $classId, $title, $msgBody, $sent]);

                jsonResponse(['success' => true, 'sent' => $sent, 'total' => count($subs)]);
            }
            break;

        // =====================================================================
        // NOTIFICATION HISTORY — Historique des notifications envoyées
        // =====================================================================
        case 'notification-history':
            if ($method === 'GET') {
                $classId = $_GET['classId'] ?? null;
                if ($classId) {
                    $stmt = $db->prepare('SELECT * FROM notification_history WHERE class_id = ? ORDER BY sent_at DESC LIMIT 20');
                    $stmt->execute([$classId]);
                } else {
                    $stmt = $db->query('SELECT * FROM notification_history ORDER BY sent_at DESC LIMIT 50');
                }
                jsonResponse($stmt->fetchAll());
            }
            break;

        // =====================================================================
        // LIVE QUIZ v2 — Sessions en direct (timer, scoring vitesse, streaks, XP, équipes, multi-types)
        // =====================================================================
        case 'live-quiz':

            // ── GET /api/live-quiz?classId=X  (poll léger de détection élève) ──
            if ($method === 'GET' && $id === null) {
                $classId = $_GET['classId'] ?? null;
                if (!$classId) jsonResponse(['error' => 'classId requis'], 400);
                $stmt = $db->prepare(
                    "SELECT ls.id, ls.status, ls.current_q, ls.updated_at, ls.mode, a.title AS activity_title
                     FROM live_sessions ls
                     JOIN activities a ON a.id = ls.activity_id
                     WHERE ls.class_id = ? AND ls.status IN ('waiting','active','paused')
                     LIMIT 1"
                );
                $stmt->execute([$classId]);
                $row = $stmt->fetch();
                if ($row) {
                    jsonResponse(['session' => [
                        'id'            => $row['id'],
                        'status'        => $row['status'],
                        'currentQ'      => (int)$row['current_q'],
                        'activityTitle' => $row['activity_title'],
                        'updatedAt'     => $row['updated_at'],
                        'mode'          => $row['mode'],
                    ]]);
                } else {
                    jsonResponse(['session' => null]);
                }
            }

            // ── GET /api/live-quiz/{sessionId}  (état complet pour prof + élève) ──
            elseif ($method === 'GET' && $id !== null && $action === null) {
                $sessionId = $id;
                $studentId = $_GET['studentId'] ?? null;

                // Enregistre l'élève comme "présent dans le salon"
                if ($studentId) {
                    $db->prepare(
                        "INSERT IGNORE INTO live_joined (session_id, student_id) VALUES (?, ?)"
                    )->execute([$sessionId, $studentId]);
                }

                $stmt = $db->prepare(
                    "SELECT ls.*, a.data AS activity_data, a.title AS activity_title, a.type AS activity_type_real, a.xp_reward,
                            (SELECT COUNT(*) FROM students WHERE class_id = ls.class_id) AS total_students
                     FROM live_sessions ls
                     JOIN activities a ON a.id = ls.activity_id
                     WHERE ls.id = ?"
                );
                $stmt->execute([$sessionId]);
                $session = $stmt->fetch();
                if (!$session) jsonResponse(['error' => 'Session introuvable'], 404);

                $activityData = json_decode($session['activity_data'], true);
                $actType = $session['activity_type'] ?? $session['activity_type_real'] ?? 'qcm';

                // Construire la liste de questions selon le type
                $questions = [];
                if ($actType === 'matching') {
                    // Transformation matching → QCM-like
                    $pairs = $activityData['pairs'] ?? [];
                    foreach ($pairs as $pi => $pair) {
                        $wrongAnswers = [];
                        foreach ($pairs as $oi => $other) {
                            if ($oi !== $pi) $wrongAnswers[] = $other['right'];
                        }
                        shuffle($wrongAnswers);
                        $choices = array_slice($wrongAnswers, 0, 3);
                        $correctPos = rand(0, min(3, count($choices)));
                        array_splice($choices, $correctPos, 0, [$pair['right']]);
                        $choices = array_slice($choices, 0, 4);
                        $questions[] = [
                            'q' => $pair['left'],
                            'choices' => $choices,
                            'answer' => $correctPos,
                            'explanation' => $pair['left'] . ' → ' . $pair['right'],
                            'isMatching' => true,
                        ];
                    }
                } elseif ($actType === 'truefalse') {
                    $tfQuestions = $activityData['questions'] ?? [];
                    foreach ($tfQuestions as $tfq) {
                        $questions[] = [
                            'q' => $tfq['q'],
                            'choices' => ['Vrai', 'Faux'],
                            'answer' => $tfq['answer'] ? 0 : 1,
                            'explanation' => $tfq['explanation'] ?? '',
                            'isTrueFalse' => true,
                        ];
                    }
                } else {
                    $questions = $activityData['questions'] ?? [];
                }

                $totalQ    = count($questions);
                $currentQ  = (int)$session['current_q'];
                $status    = $session['status'];
                $timerSeconds = (int)($session['timer_seconds'] ?? 20);
                $questionStartedAt = $session['question_started_at'];
                $mode = $session['mode'] ?? 'individual';

                // Temps serveur pour synchro timer client
                $serverTime = date('Y-m-d H:i:s');

                // Question courante
                $isTeacher = ($studentId === null);
                $qData = null;
                if ($currentQ < $totalQ) {
                    $q = $questions[$currentQ];
                    $qData = [
                        'q' => $q['q'],
                        'choices' => $q['choices'],
                        'isTrueFalse' => !empty($q['isTrueFalse']),
                        'isMatching' => !empty($q['isMatching']),
                    ];
                    if ($isTeacher || $status === 'paused') {
                        $qData['answer']      = $q['answer'];
                        $qData['explanation'] = $q['explanation'] ?? '';
                    }
                }

                // Distribution des réponses
                $numChoices = ($qData && !empty($qData['isTrueFalse'])) ? 2 : 4;
                $distStmt = $db->prepare(
                    "SELECT answer_idx, COUNT(*) AS cnt
                     FROM live_responses
                     WHERE session_id = ? AND question_idx = ?
                     GROUP BY answer_idx"
                );
                $distStmt->execute([$sessionId, $currentQ]);
                $distribution = array_fill(0, $numChoices, 0);
                foreach ($distStmt->fetchAll() as $r) {
                    $idx = (int)$r['answer_idx'];
                    if ($idx < $numChoices) $distribution[$idx] = (int)$r['cnt'];
                }

                // Nb d'élèves ayant répondu
                $answeredStmt = $db->prepare(
                    "SELECT COUNT(DISTINCT student_id) FROM live_responses
                     WHERE session_id = ? AND question_idx = ?"
                );
                $answeredStmt->execute([$sessionId, $currentQ]);
                $answeredCount = (int)$answeredStmt->fetchColumn();

                // Classement par score (points vitesse)
                $lbStmt = $db->prepare(
                    "SELECT s.id, s.first_name, s.last_name,
                            SUM(lr.score) AS total_score,
                            SUM(lr.is_correct) AS correct_count
                     FROM live_responses lr
                     JOIN students s ON s.id = lr.student_id
                     WHERE lr.session_id = ?
                     GROUP BY s.id
                     ORDER BY total_score DESC
                     LIMIT 30"
                );
                $lbStmt->execute([$sessionId]);
                $leaderboard = array_map(function($r) {
                    return [
                        'studentId'    => $r['id'],
                        'firstName'    => $r['first_name'],
                        'lastName'     => $r['last_name'],
                        'score'        => (int)$r['total_score'],
                        'correctCount' => (int)$r['correct_count'],
                    ];
                }, $lbStmt->fetchAll());

                // L'élève a-t-il répondu ?
                $hasAnswered = false;
                $myLastScore = 0;
                $myStreak = 0;
                if ($studentId) {
                    $haStmt = $db->prepare(
                        "SELECT score FROM live_responses
                         WHERE session_id = ? AND student_id = ? AND question_idx = ?"
                    );
                    $haStmt->execute([$sessionId, $studentId, $currentQ]);
                    $haRow = $haStmt->fetch();
                    $hasAnswered = (bool)$haRow;
                    $myLastScore = $haRow ? (int)$haRow['score'] : 0;

                    // Calculer le streak actuel de l'élève
                    $streakStmt = $db->prepare(
                        "SELECT is_correct FROM live_responses
                         WHERE session_id = ? AND student_id = ?
                         ORDER BY question_idx DESC"
                    );
                    $streakStmt->execute([$sessionId, $studentId]);
                    $myStreak = 0;
                    foreach ($streakStmt->fetchAll() as $sr) {
                        if ((int)$sr['is_correct'] === 1) $myStreak++;
                        else break;
                    }
                }

                // Élèves présents
                $joinedStmt = $db->prepare(
                    "SELECT s.id, s.first_name, s.last_name
                     FROM live_joined lj
                     JOIN students s ON s.id = lj.student_id
                     WHERE lj.session_id = ?
                     ORDER BY lj.joined_at ASC"
                );
                $joinedStmt->execute([$sessionId]);
                $joinedStudents = array_map(function($r) {
                    return [
                        'id'        => $r['id'],
                        'firstName' => $r['first_name'],
                        'lastName'  => $r['last_name'],
                    ];
                }, $joinedStmt->fetchAll());
                $joinedCount = count($joinedStudents);

                // Données équipe si mode team
                $teams = null;
                $myTeamRow = null;
                if ($mode === 'team') {
                    try {
                        $teamStmt = $db->prepare(
                            "SELECT team, COUNT(*) AS cnt, SUM(sub.score) AS team_score
                             FROM live_teams lt
                             LEFT JOIN (
                                 SELECT student_id, SUM(score) AS score FROM live_responses WHERE session_id = ? GROUP BY student_id
                             ) sub ON sub.student_id = lt.student_id
                             WHERE lt.session_id = ?
                             GROUP BY team"
                        );
                        $teamStmt->execute([$sessionId, $sessionId]);
                        $teams = ['A' => ['count' => 0, 'score' => 0], 'B' => ['count' => 0, 'score' => 0]];
                        foreach ($teamStmt->fetchAll() as $tr) {
                            $teams[$tr['team']] = ['count' => (int)$tr['cnt'], 'score' => (int)($tr['team_score'] ?? 0)];
                        }

                        // Équipe de l'élève
                        if ($studentId) {
                            $myTeamStmt = $db->prepare("SELECT team FROM live_teams WHERE session_id = ? AND student_id = ?");
                            $myTeamStmt->execute([$sessionId, $studentId]);
                            $myTeamRow = $myTeamStmt->fetch();
                        }
                    } catch (PDOException $e) {
                        // Table live_teams manquante — fallback sans crash
                        $teams = ['A' => ['count' => 0, 'score' => 0], 'B' => ['count' => 0, 'score' => 0]];
                    }
                }

                // Temps moyen de réponse pour la question courante (prof)
                $avgTimeMs = null;
                if ($isTeacher) {
                    $avgStmt = $db->prepare(
                        "SELECT AVG(response_time_ms) AS avg_ms FROM live_responses
                         WHERE session_id = ? AND question_idx = ? AND response_time_ms IS NOT NULL"
                    );
                    $avgStmt->execute([$sessionId, $currentQ]);
                    $avgRow = $avgStmt->fetch();
                    $avgTimeMs = $avgRow ? (int)($avgRow['avg_ms'] ?? 0) : null;
                }

                $response = [
                    'status'             => $status,
                    'currentQ'           => $currentQ,
                    'totalQuestions'     => $totalQ,
                    'activityTitle'      => $session['activity_title'],
                    'activityType'       => $actType,
                    'question'           => $qData,
                    'answeredCount'      => $answeredCount,
                    'totalStudents'      => (int)$session['total_students'],
                    'joinedStudents'     => $joinedStudents,
                    'joinedCount'        => $joinedCount,
                    'distribution'       => $distribution,
                    'leaderboard'        => $leaderboard,
                    'hasAnswered'        => $hasAnswered,
                    'timerSeconds'       => $timerSeconds,
                    'questionStartedAt'  => $questionStartedAt,
                    'serverTime'         => $serverTime,
                    'mode'               => $mode,
                    'myLastScore'        => $myLastScore,
                    'myStreak'           => $myStreak,
                    'teams'              => $teams,
                    'myTeam'             => ($mode === 'team' && $studentId && isset($myTeamRow)) ? ($myTeamRow['team'] ?? null) : null,
                    'avgTimeMs'          => $avgTimeMs,
                ];
                jsonResponse($response);
            }

            // ── POST /api/live-quiz  (créer une session — prof) ──
            elseif ($method === 'POST' && $id === null) {
                $body         = getJsonBody();
                $activityId   = $body['activityId'] ?? '';
                $classId      = $body['classId'] ?? '';
                $timerSeconds = (int)($body['timerSeconds'] ?? 20);
                $mode         = ($body['mode'] ?? 'individual') === 'team' ? 'team' : 'individual';
                if (!$activityId || !$classId) jsonResponse(['error' => 'activityId et classId requis'], 400);
                if ($timerSeconds < 5 || $timerSeconds > 120) $timerSeconds = 20;

                // Vérifie le type d'activité
                $actStmt = $db->prepare("SELECT type, data, title, xp_reward FROM activities WHERE id = ?");
                $actStmt->execute([$activityId]);
                $act = $actStmt->fetch();
                if (!$act) jsonResponse(['error' => 'Activité introuvable'], 404);

                $allowedTypes = ['qcm', 'truefalse', 'matching'];
                if (!in_array($act['type'], $allowedTypes)) {
                    jsonResponse(['error' => 'Ce type d\'activité ne supporte pas le mode live'], 400);
                }

                // Déterminer le nombre de questions
                $data = json_decode($act['data'], true);
                if ($act['type'] === 'matching') {
                    $totalQ = count($data['pairs'] ?? []);
                } else {
                    $totalQ = count($data['questions'] ?? []);
                }

                // Clôture les sessions actives existantes pour cette classe
                $db->prepare(
                    "UPDATE live_sessions SET status = 'finished'
                     WHERE class_id = ? AND status IN ('waiting','active','paused')"
                )->execute([$classId]);

                $sessionId = generateId();
                $db->prepare(
                    "INSERT INTO live_sessions (id, activity_id, class_id, status, current_q, timer_seconds, mode, activity_type)
                     VALUES (?, ?, ?, 'waiting', 0, ?, ?, ?)"
                )->execute([$sessionId, $activityId, $classId, $timerSeconds, $mode, $act['type']]);

                jsonResponse([
                    'sessionId'      => $sessionId,
                    'status'         => 'waiting',
                    'currentQ'       => 0,
                    'totalQuestions' => $totalQ,
                    'activityTitle'  => $act['title'],
                    'mode'           => $mode,
                    'timerSeconds'   => $timerSeconds,
                    'activityType'   => $act['type'],
                ]);
            }

            // ── PUT /api/live-quiz/{sessionId}  (actions prof) ──
            elseif ($method === 'PUT' && $id !== null && $action === null) {
                $sessionId = $id;
                $body      = getJsonBody();
                $actionReq = $body['action'] ?? '';

                $sessStmt = $db->prepare("SELECT ls.*, a.data AS activity_data, a.type AS activity_type_real, a.xp_reward FROM live_sessions ls JOIN activities a ON a.id = ls.activity_id WHERE ls.id = ?");
                $sessStmt->execute([$sessionId]);
                $session = $sessStmt->fetch();
                if (!$session) jsonResponse(['error' => 'Session introuvable'], 404);

                $actType = $session['activity_type'] ?? $session['activity_type_real'] ?? 'qcm';
                $actData = json_decode($session['activity_data'], true);
                if ($actType === 'matching') {
                    $totalQ = count($actData['pairs'] ?? []);
                } else {
                    $totalQ = count($actData['questions'] ?? []);
                }
                $currentQ = (int)$session['current_q'];
                $status   = $session['status'];

                switch ($actionReq) {
                    case 'start':
                        if ($status !== 'waiting') jsonResponse(['error' => 'La session est déjà démarrée'], 400);
                        $db->prepare("UPDATE live_sessions SET status = 'active', question_started_at = NOW() WHERE id = ?")->execute([$sessionId]);
                        jsonResponse(['success' => true, 'status' => 'active', 'currentQ' => $currentQ]);
                        break;
                    case 'pause':
                        if ($status !== 'active') jsonResponse(['error' => 'La session n\'est pas active'], 400);
                        $db->prepare("UPDATE live_sessions SET status = 'paused' WHERE id = ?")->execute([$sessionId]);
                        jsonResponse(['success' => true, 'status' => 'paused']);
                        break;
                    case 'resume':
                        if ($status !== 'paused') jsonResponse(['error' => 'La session n\'est pas en pause'], 400);
                        $db->prepare("UPDATE live_sessions SET status = 'active', question_started_at = NOW() WHERE id = ?")->execute([$sessionId]);
                        jsonResponse(['success' => true, 'status' => 'active']);
                        break;
                    case 'next':
                        if (!in_array($status, ['active', 'paused'])) jsonResponse(['error' => 'Action impossible'], 400);
                        if ($currentQ + 1 >= $totalQ) jsonResponse(['error' => 'Déjà à la dernière question, utilisez finish'], 400);
                        $db->prepare("UPDATE live_sessions SET current_q = current_q + 1, status = 'active', question_started_at = NOW() WHERE id = ?")->execute([$sessionId]);
                        jsonResponse(['success' => true, 'status' => 'active', 'currentQ' => $currentQ + 1]);
                        break;
                    case 'finish':
                        // Attribuer XP aux élèves avant de terminer
                        $xpReward = (int)($session['xp_reward'] ?? 40);
                        $scoresStmt = $db->prepare(
                            "SELECT student_id, SUM(score) AS total_score, SUM(is_correct) AS correct_count
                             FROM live_responses WHERE session_id = ? GROUP BY student_id"
                        );
                        $scoresStmt->execute([$sessionId]);
                        $maxPossibleScore = $totalQ * 1000; // Score max théorique
                        foreach ($scoresStmt->fetchAll() as $sr) {
                            // XP proportionnel au score, plafonné à xp_reward
                            $xpGained = $maxPossibleScore > 0
                                ? (int)ceil(((int)$sr['total_score'] / $maxPossibleScore) * $xpReward)
                                : 0;
                            $xpGained = min($xpGained, $xpReward);
                            $xpGained = max($xpGained, 1); // Au minimum 1 XP pour avoir participé

                            // Ajouter XP au profil
                            $db->prepare("UPDATE students SET xp = xp + ? WHERE id = ?")->execute([$xpGained, $sr['student_id']]);

                            // Créer une entrée dans results pour l'historique
                            $scorePercent = $totalQ > 0 ? round(((int)$sr['correct_count'] / $totalQ) * 100) : 0;
                            try {
                                $db->prepare(
                                    "INSERT INTO results (id, student_id, activity_id, score) VALUES (?, ?, ?, ?)"
                                )->execute([generateId(), $sr['student_id'], $session['activity_id'], $scorePercent]);
                            } catch (PDOException $e) {
                                // Ignorer les doublons
                            }
                        }

                        $db->prepare("UPDATE live_sessions SET status = 'finished' WHERE id = ?")->execute([$sessionId]);
                        jsonResponse(['success' => true, 'status' => 'finished']);
                        break;
                    case 'skip':
                        // Sauter une question sans la compter
                        if (!in_array($status, ['active', 'paused'])) jsonResponse(['error' => 'Action impossible'], 400);
                        if ($currentQ + 1 >= $totalQ) jsonResponse(['error' => 'Dernière question'], 400);
                        // Supprimer les réponses de la question skippée
                        $db->prepare("DELETE FROM live_responses WHERE session_id = ? AND question_idx = ?")->execute([$sessionId, $currentQ]);
                        $db->prepare("UPDATE live_sessions SET current_q = current_q + 1, status = 'active', question_started_at = NOW() WHERE id = ?")->execute([$sessionId]);
                        jsonResponse(['success' => true, 'status' => 'active', 'currentQ' => $currentQ + 1]);
                        break;
                    case 'cancel':
                        // Le prof quitte sans terminer proprement — marquer comme finished
                        if ($status === 'finished') jsonResponse(['success' => true, 'status' => 'finished']);
                        $db->prepare("UPDATE live_sessions SET status = 'finished' WHERE id = ?")->execute([$sessionId]);
                        jsonResponse(['success' => true, 'status' => 'finished']);
                        break;
                    default:
                        jsonResponse(['error' => 'Action inconnue'], 400);
                }
            }

            // ── POST /api/live-quiz/{sessionId}/answer  (réponse élève) ──
            elseif ($method === 'POST' && $id !== null && $action === 'answer') {
                $sessionId   = $id;
                $body        = getJsonBody();
                $studentId   = $body['studentId'] ?? '';
                $questionIdx = $body['questionIdx'] ?? null;
                $answerIdx   = $body['answerIdx'] ?? null;
                if (!$studentId || $questionIdx === null || $answerIdx === null) {
                    jsonResponse(['error' => 'studentId, questionIdx et answerIdx requis'], 400);
                }

                $sessStmt = $db->prepare(
                    "SELECT ls.status, ls.timer_seconds, ls.question_started_at, ls.activity_type,
                            a.data AS activity_data, a.type AS activity_type_real
                     FROM live_sessions ls
                     JOIN activities a ON a.id = ls.activity_id
                     WHERE ls.id = ?"
                );
                $sessStmt->execute([$sessionId]);
                $session = $sessStmt->fetch();
                if (!$session) jsonResponse(['error' => 'Session introuvable'], 404);
                if ($session['status'] !== 'active') jsonResponse(['error' => 'La session n\'est pas active'], 400);

                $actType = $session['activity_type'] ?? $session['activity_type_real'] ?? 'qcm';
                $actData = json_decode($session['activity_data'], true);

                // Construire les questions selon le type
                if ($actType === 'matching') {
                    $pairs = $actData['pairs'] ?? [];
                    // On doit reconstruire les questions matching→QCM de façon déterministe
                    // Utiliser le session_id comme seed pour le shuffle
                    $questions = [];
                    foreach ($pairs as $pi => $pair) {
                        $wrongAnswers = [];
                        foreach ($pairs as $oi => $other) {
                            if ($oi !== $pi) $wrongAnswers[] = $other['right'];
                        }
                        // Seed déterministe pour que les choix soient stables
                        mt_srand(crc32($sessionId . '_' . $pi));
                        shuffle($wrongAnswers);
                        $choices = array_slice($wrongAnswers, 0, 3);
                        $correctPos = mt_rand(0, min(3, count($choices)));
                        array_splice($choices, $correctPos, 0, [$pair['right']]);
                        $choices = array_slice($choices, 0, 4);
                        mt_srand(); // Reset le seed
                        $questions[] = ['q' => $pair['left'], 'choices' => $choices, 'answer' => $correctPos];
                    }
                } elseif ($actType === 'truefalse') {
                    $tfQuestions = $actData['questions'] ?? [];
                    $questions = [];
                    foreach ($tfQuestions as $tfq) {
                        $questions[] = ['q' => $tfq['q'], 'choices' => ['Vrai', 'Faux'], 'answer' => $tfq['answer'] ? 0 : 1];
                    }
                } else {
                    $questions = $actData['questions'] ?? [];
                }

                $isCorrect = isset($questions[$questionIdx]) && (int)$questions[$questionIdx]['answer'] === (int)$answerIdx ? 1 : 0;

                // Calcul du temps de réponse et du score vitesse
                $responseTimeMs = null;
                $score = 0;
                $timerSeconds = (int)($session['timer_seconds'] ?? 20);
                $timerMs = $timerSeconds * 1000;

                if ($session['question_started_at']) {
                    // Temps en millisecondes depuis le début de la question
                    $startedAt = new DateTime($session['question_started_at']);
                    $now = new DateTime();
                    $diff = $now->getTimestamp() - $startedAt->getTimestamp();
                    $responseTimeMs = max(0, (int)($diff * 1000));

                    // Score basé sur la vitesse (seulement si correct)
                    if ($isCorrect) {
                        $speedRatio = min(1.0, $responseTimeMs / $timerMs);
                        $score = max(100, (int)(1000 - floor($speedRatio * 900)));

                        // Streak bonus
                        $streakStmt = $db->prepare(
                            "SELECT is_correct FROM live_responses
                             WHERE session_id = ? AND student_id = ?
                             ORDER BY question_idx DESC"
                        );
                        $streakStmt->execute([$sessionId, $studentId]);
                        $streak = 0;
                        foreach ($streakStmt->fetchAll() as $sr) {
                            if ((int)$sr['is_correct'] === 1) $streak++;
                            else break;
                        }
                        // Appliquer le multiplicateur de streak
                        if ($streak >= 5)      $score = (int)($score * 2.0);
                        elseif ($streak >= 3)  $score = (int)($score * 1.5);
                        elseif ($streak >= 2)  $score = (int)($score * 1.2);
                    }
                }

                try {
                    $db->prepare(
                        "INSERT INTO live_responses (id, session_id, student_id, question_idx, answer_idx, is_correct, response_time_ms, score)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    )->execute([generateId(), $sessionId, $studentId, (int)$questionIdx, (int)$answerIdx, $isCorrect, $responseTimeMs, $score]);
                    jsonResponse([
                        'success'        => true,
                        'isCorrect'      => (bool)$isCorrect,
                        'score'          => $score,
                        'responseTimeMs' => $responseTimeMs,
                        'streak'         => ($isCorrect ? ($streak ?? 0) + 1 : 0),
                    ]);
                } catch (PDOException $e) {
                    if (str_contains($e->getMessage(), 'Duplicate') || $e->getCode() == 23000) {
                        jsonResponse(['error' => 'already_answered'], 409);
                    }
                    throw $e;
                }
            }

            // ── POST /api/live-quiz/{sessionId}/join-team  (rejoindre une équipe) ──
            elseif ($method === 'POST' && $id !== null && $action === 'join-team') {
                $sessionId = $id;
                $body      = getJsonBody();
                $studentId = $body['studentId'] ?? '';
                $team      = $body['team'] ?? '';
                if (!$studentId || !in_array($team, ['A', 'B'])) {
                    jsonResponse(['error' => 'studentId et team (A ou B) requis'], 400);
                }

                // Vérifier que la session est en mode team
                $sessStmt = $db->prepare("SELECT mode FROM live_sessions WHERE id = ?");
                $sessStmt->execute([$sessionId]);
                $session = $sessStmt->fetch();
                if (!$session || $session['mode'] !== 'team') {
                    jsonResponse(['error' => 'Cette session n\'est pas en mode équipe'], 400);
                }

                // Vérifier l'équilibre : pas plus de 2 joueurs d'écart
                $countStmt = $db->prepare(
                    "SELECT team, COUNT(*) AS cnt FROM live_teams WHERE session_id = ? GROUP BY team"
                );
                $countStmt->execute([$sessionId]);
                $counts = ['A' => 0, 'B' => 0];
                foreach ($countStmt->fetchAll() as $r) $counts[$r['team']] = (int)$r['cnt'];

                $otherTeam = $team === 'A' ? 'B' : 'A';
                if ($counts[$team] - $counts[$otherTeam] >= 2) {
                    jsonResponse(['error' => 'Cette équipe est complète, rejoins l\'autre !'], 400);
                }

                try {
                    $db->prepare(
                        "INSERT INTO live_teams (session_id, student_id, team) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE team = VALUES(team)"
                    )->execute([$sessionId, $studentId, $team]);
                    jsonResponse(['success' => true, 'team' => $team]);
                } catch (PDOException $e) {
                    jsonResponse(['error' => 'Erreur lors de la sélection d\'équipe'], 500);
                }
            }

            else {
                jsonResponse(['error' => 'Route live-quiz non reconnue'], 404);
            }
            break;

        // =====================================================================
        // AI - Génération de contenu via Claude
        // =====================================================================
        case 'ai':
            if ($id === 'generate' && $method === 'POST') {
                if (!defined('ANTHROPIC_API_KEY') || ANTHROPIC_API_KEY === 'sk-ant-YOUR_KEY_HERE') {
                    jsonResponse(['error' => 'Clé API Anthropic non configurée dans config.php'], 503);
                }
                if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
                    jsonResponse(['error' => 'Fichier PDF manquant ou invalide'], 400);
                }
                if ($_FILES['pdf']['size'] > 10 * 1024 * 1024) {
                    jsonResponse(['error' => 'PDF trop volumineux (max 10 Mo)'], 400);
                }

                $generateCourse      = ($_POST['generateCourse'] ?? 'true') === 'true';
                $generateActivities  = ($_POST['generateActivities'] ?? 'true') === 'true';
                $activityTypes       = json_decode($_POST['activityTypes'] ?? '["qcm","flashcards","truefalse","fillblank","matching"]', true);

                set_time_limit(180);
                $pdfData = base64_encode(file_get_contents($_FILES['pdf']['tmp_name']));

                // Construire le prompt
                $parts = [];
                if ($generateCourse) {
                    $parts[] = <<<'EOT'
- Un objet "course" avec :
  - title (string) : titre du cours
  - description (string) : résumé du cours en 1-2 phrases
  - chapters (array) : minimum 3 chapitres, chaque chapitre = { title: string, content: string }
  RÈGLES COURS :
  * Chaque "content" doit faire au moins 200 mots
  * Utilise du markdown simple : **gras**, *italique*, ## titres de section, - listes, \n pour les retours à la ligne
  * Structure avec des paragraphes clairs, des exemples concrets du quotidien ou de l'entreprise
  * Adapte le niveau lycée 1ère STMG : langage accessible, exemples parlants
  * NE reproduis pas le contenu tel quel : synthétise, structure, pédagogise
EOT;
                }
                if ($generateActivities && !empty($activityTypes)) {
                    $typeDescs = [];
                    $typeRules = [
                        'qcm' => <<<'EOT'
  { title, type:"qcm", difficulty:2, xpReward:50, data:{ questions:[{ q, choices:[4 items], answer:0-based-index, explanation }] } }
  RÈGLES QCM :
  * Minimum 8 questions
  * OBLIGATOIRE : répartir les bonnes réponses aléatoirement entre les positions 0, 1, 2 et 3 (~2 par position sur 8 questions)
  * NE JAMAIS mettre toutes les bonnes réponses en position 0 ou 1
  * Chaque "explanation" explique pourquoi cette réponse est correcte (1-2 phrases)
  * Difficulté 2 = compréhension + application → XP : 50
EOT,
                        'flashcards' => <<<'EOT'
  { title, type:"flashcards", difficulty:1, xpReward:30, data:{ cards:[{ front, back }] } }
  RÈGLES FLASHCARDS :
  * Minimum 10 cartes
  * "front" = concept/terme court, "back" = définition claire en 1-2 phrases
  * Difficulté 1 = mémorisation → XP : 30
EOT,
                        'truefalse' => <<<'EOT'
  { title, type:"truefalse", difficulty:1, xpReward:35, data:{ questions:[{ q, answer:bool, explanation }] } }
  RÈGLES VRAI/FAUX :
  * Minimum 8 affirmations
  * OBLIGATOIRE : alterner équitablement true et false (~50/50), ne pas mettre toutes les réponses à true
  * "explanation" : explique pourquoi c'est vrai ou faux (1-2 phrases)
  * Difficulté 1 = révision → XP : 35
EOT,
                        'fillblank' => <<<'EOT'
  { title, type:"fillblank", difficulty:2, xpReward:45, data:{ sentences:[{ text:"phrase avec ___ à compléter", answer:"mot", hint:"indice" }] } }
  RÈGLES TEXTE À TROUS :
  * Minimum 8 phrases
  * Le trou est représenté par ___ (3 underscores exactement) dans le "text"
  * "answer" = le mot exact attendu (minuscule, sans accent optionnel)
  * "hint" = indice utile sans donner la réponse directement
  * Difficulté 2 = application → XP : 45
EOT,
                        'matching' => <<<'EOT'
  { title, type:"matching", difficulty:2, xpReward:45, data:{ pairs:[{ left, right }] } }
  RÈGLES ASSOCIATIONS :
  * Minimum 6 paires
  * "left" = concept/terme court (1-5 mots), "right" = définition ou exemple associé
  * Les paires doivent couvrir des notions clés variées du cours
  * Difficulté 2 = compréhension → XP : 45
EOT,
                    ];
                    foreach ($activityTypes as $t) {
                        if (isset($typeRules[$t])) $typeDescs[] = $typeRules[$t];
                    }
                    $parts[] = '- Un tableau "activities" contenant une activité par type demandé :' . "\n" . implode("\n", $typeDescs);
                }

                if (empty($parts)) {
                    jsonResponse(['error' => 'Rien à générer'], 400);
                }

                $instructions = implode("\n\n", $parts);
                $prompt = <<<EOT
Tu es un assistant pédagogique expert pour SmartKlass. Analyse ce document PDF et génère du contenu pédagogique en français pour des lycéens de 1ère STMG.

RÈGLES ABSOLUES :
1. Génère UNIQUEMENT un objet JSON valide (sans markdown, sans ```json, sans aucun texte autour)
2. Contenu entièrement en français, niveau lycée 1ère STMG
3. Langage clair et accessible, exemples concrets du quotidien ou du monde de l'entreprise
4. Respecte STRICTEMENT les règles spécifiques à chaque type de contenu ci-dessous

STRUCTURE JSON ATTENDUE :
{
$instructions
}

Si un élément n'est pas demandé, utilise null (pour course) ou [] (pour activities).
EOT;

                $payload = json_encode([
                    'model'      => 'claude-sonnet-4-6',
                    'max_tokens' => 16000,
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => [
                            [
                                'type'   => 'document',
                                'source' => [
                                    'type'       => 'base64',
                                    'media_type' => 'application/pdf',
                                    'data'       => $pdfData,
                                ],
                            ],
                            ['type' => 'text', 'text' => $prompt],
                        ],
                    ]],
                ], JSON_UNESCAPED_UNICODE);

                $ch = curl_init('https://api.anthropic.com/v1/messages');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'x-api-key: ' . ANTHROPIC_API_KEY,
                        'anthropic-version: 2023-06-01',
                        'anthropic-beta: pdfs-2024-09-25',
                    ],
                    CURLOPT_TIMEOUT => 180,
                ]);
                $raw      = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($raw === false) {
                    jsonResponse(['error' => 'Impossible de contacter l\'API Claude'], 503);
                }

                $apiResp = json_decode($raw, true);
                if ($httpCode !== 200 || empty($apiResp['content'][0]['text'])) {
                    $msg = $apiResp['error']['message'] ?? $raw;
                    jsonResponse(['error' => 'Erreur API Claude : ' . $msg], 502);
                }

                $text = trim($apiResp['content'][0]['text']);
                // Nettoyer si Claude a quand même mis des backticks
                $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
                $text = preg_replace('/\s*```$/', '', $text);

                $generated = json_decode($text, true);
                if ($generated === null) {
                    jsonResponse(['error' => 'Réponse Claude invalide (JSON mal formé)', 'raw' => $text], 502);
                }

                jsonResponse($generated);
            }
            break;

        default:
            jsonResponse(['error' => 'Route non trouvée', 'path' => $resource], 404);
    }
    
} catch (PDOException $e) {
    jsonResponse(['error' => 'Erreur serveur: ' . $e->getMessage()], 500);
}

// ============================================================================
// HELPERS
// ============================================================================
function formatStudent($s) {
    return [
        'id' => $s['id'],
        'firstName' => $s['first_name'],
        'lastName' => $s['last_name'],
        'classId' => $s['class_id'],
        'identifier' => $s['identifier'],
        'hasPassword' => !empty($s['password']),
        'xp' => (int)$s['xp'],
        'streak' => (int)$s['streak'],
        'badges' => [],
        'lastActive' => $s['last_active'],
    ];
}
