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
                        // Pas de mdp encore = première connexion
                        if (empty($student['password'])) {
                            $db->prepare('UPDATE students SET last_active = NOW() WHERE id = ?')->execute([$student['id']]);
                            jsonResponse(['success' => true, 'type' => 'student', 'student' => formatStudent($student), 'needsPassword' => true]);
                        } else {
                            // Vérifier le mot de passe
                            if ($password === $student['password']) {
                                $db->prepare('UPDATE students SET last_active = NOW() WHERE id = ?')->execute([$student['id']]);
                                jsonResponse(['success' => true, 'type' => 'student', 'student' => formatStudent($student), 'needsPassword' => false]);
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
                    $db->prepare('UPDATE students SET password = ? WHERE id = ?')->execute([$newPassword, $studentId]);
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
                $stmt = $db->query('SELECT c.*, GROUP_CONCAT(cc.class_id) as class_ids FROM courses c LEFT JOIN course_classes cc ON c.id = cc.course_id GROUP BY c.id ORDER BY c.created_at DESC');
                $courses = $stmt->fetchAll();
                foreach ($courses as &$course) {
                    $course['classIds'] = $course['class_ids'] ? explode(',', $course['class_ids']) : [];
                    $course['chapters'] = json_decode($course['chapters'], true) ?? [];
                    $course['youtubeUrl'] = $course['youtube_url'] ?? null;
                    unset($course['class_ids'], $course['youtube_url']);
                }
                jsonResponse($courses);
            } elseif ($method === 'POST') {
                $body = getJsonBody();
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
                
                $db->prepare('UPDATE students SET xp = xp + ?, streak = streak + 1, last_active = NOW() WHERE id = ?')
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
                $coursesRaw = $db->query('SELECT c.*, GROUP_CONCAT(cc.class_id) as class_ids FROM courses c LEFT JOIN course_classes cc ON c.id = cc.course_id GROUP BY c.id')->fetchAll();
                $courses = [];
                foreach ($coursesRaw as $c) {
                    $c['classIds'] = $c['class_ids'] ? explode(',', $c['class_ids']) : [];
                    $c['chapters'] = json_decode($c['chapters'], true) ?? [];
                    $c['subjectId'] = $c['subject_id'];
                    $c['youtubeUrl'] = $c['youtube_url'] ?? null;
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
