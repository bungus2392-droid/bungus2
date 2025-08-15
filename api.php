<?php
// api.php

// --| تضمين ملف الاتصال بقاعدة البيانات |--
require 'db.php';

// --| السماح بالوصول من أي مصدر (CORS) وتحديد نوع المحتوى |--
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --| التعامل مع طلبات OPTIONS (preflight) |--
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// --| تحديد الإجراء المطلوب من خلال متغير 'action' في الرابط |--
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    // --| حالة جلب جميع البيانات |--
    case 'getData':
        fetchAllData($conn);
        break;
    // --| حالة حفظ جميع البيانات |--
    case 'saveData':
        saveAllData($conn);
        break;
    // --| حالة الإجراء غير معروف |--
    default:
        echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
        break;
}

// --| دالة لجلب جميع البيانات من قاعدة البيانات |--
function fetchAllData($conn) {
    $data = [
        'testsData' => ['كمي' => [], 'لفظي' => []],
        'usersData' => []
    ];

    // جلب الاختبارات
    $testsResult = $conn->query("SELECT * FROM tests");
    while ($row = $testsResult->fetch_assoc()) {
        $row['questions'] = json_decode($row['questions'], true);
        // تحويل القيم الرقمية من نصوص إلى أرقام
        $row['id'] = (int)$row['test_id'];
        $row['duration'] = (int)$row['duration'];
        $row['maxAttempts'] = (int)$row['max_attempts'];
        $row['prerequisitePercentage'] = (int)$row['prerequisite_percentage'];
        unset($row['test_id']); // إزالة المفتاح المكرر
        $data['testsData'][$row['category']][] = $row;
    }

    // جلب المستخدمين
    $usersResult = $conn->query("SELECT * FROM users");
    $usersMap = [];
    while ($row = $usersResult->fetch_assoc()) {
        $row['id'] = (int)$row['id'];
        $row['scores'] = [];
        $row['attemptsLeft'] = [];
        $data['usersData'][] = $row;
        $usersMap[$row['id']] = count($data['usersData']) - 1;
    }

    // جلب الدرجات
    $scoresResult = $conn->query("SELECT * FROM scores");
    while ($row = $scoresResult->fetch_assoc()) {
        if (isset($usersMap[$row['user_id']])) {
            $userIndex = $usersMap[$row['user_id']];
            $testId = $row['test_id'];
            if (!isset($data['usersData'][$userIndex]['scores'][$testId])) {
                $data['usersData'][$userIndex]['scores'][$testId] = [];
            }
            $data['usersData'][$userIndex]['scores'][$testId][] = [
                'score' => (int)$row['score'],
                'total' => (int)$row['total'],
                'date' => $row['date'],
                'time' => $row['time'],
                'timeTaken' => (int)$row['time_taken']
            ];
        }
    }
    
    // جلب المحاولات المتبقية
    $attemptsResult = $conn->query("SELECT * FROM attempts");
    while($row = $attemptsResult->fetch_assoc()){
        if (isset($usersMap[$row['user_id']])) {
            $userIndex = $usersMap[$row['user_id']];
            $testId = $row['test_id'];
            $data['usersData'][$userIndex]['attemptsLeft'][$testId] = (int)$row['attempts_left'];
        }
    }


    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
}

// --| دالة لحفظ البيانات المستلمة في قاعدة البيانات |--
function saveAllData($conn) {
    // استقبال البيانات من جسم الطلب
    $input = json_decode(file_get_contents('php://input'), true);

    $testsData = $input['testsData'];
    $usersData = $input['usersData'];

    // بدء معاملة لضمان تنفيذ جميع الاستعلامات بنجاح
    $conn->begin_transaction();

    try {
        // -- حفظ الاختبارات --
        $stmtTest = $conn->prepare("INSERT INTO tests (test_id, title, category, duration, max_attempts, prerequisite_percentage, questions) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title), category=VALUES(category), duration=VALUES(duration), max_attempts=VALUES(max_attempts), prerequisite_percentage=VALUES(prerequisite_percentage), questions=VALUES(questions)");
        
        // حذف الاختبارات التي لم تعد موجودة
        $allTestIds = [];
        foreach ($testsData as $category => $tests) {
            foreach ($tests as $test) {
                $allTestIds[] = $test['id'];
                $questionsJson = json_encode($test['questions'], JSON_UNESCAPED_UNICODE);
                $stmtTest->bind_param("isssiis", $test['id'], $test['title'], $category, $test['duration'], $test['maxAttempts'], $test['prerequisitePercentage'], $questionsJson);
                $stmtTest->execute();
            }
        }
        if(!empty($allTestIds)){
            $ids_placeholder = implode(',', array_fill(0, count($allTestIds), '?'));
            $types = str_repeat('i', count($allTestIds));
            $conn->execute_query("DELETE FROM tests WHERE test_id NOT IN ($ids_placeholder)", $allTestIds);
        } else {
             $conn->query("DELETE FROM tests");
        }


        // -- حفظ المستخدمين --
        $stmtUser = $conn->prepare("INSERT INTO users (username, access_code) VALUES (?, ?) ON DUPLICATE KEY UPDATE access_code=VALUES(access_code)");
        $allUsernames = [];
        foreach ($usersData as $user) {
            $allUsernames[] = $user['username'];
            $stmtUser->bind_param("ss", $user['username'], $user['accessCode']);
            $stmtUser->execute();
        }
        if(!empty($allUsernames)){
            $usernames_placeholder = implode(',', array_fill(0, count($allUsernames), '?'));
            $types = str_repeat('s', count($allUsernames));
            $conn->execute_query("DELETE FROM users WHERE username NOT IN ($usernames_placeholder)", $allUsernames);
        } else {
            $conn->query("DELETE FROM users");
        }


        // -- مسح الدرجات والمحاولات القديمة --
        $conn->query("DELETE FROM scores");
        $conn->query("DELETE FROM attempts");

        // -- حفظ الدرجات والمحاولات الجديدة --
        $stmtScore = $conn->prepare("INSERT INTO scores (user_id, test_id, score, total, date, time, time_taken) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtAttempt = $conn->prepare("INSERT INTO attempts (user_id, test_id, attempts_left) VALUES (?, ?, ?)");

        foreach ($usersData as $user) {
            // جلب id المستخدم بناءً على username
            $userResult = $conn->execute_query("SELECT id FROM users WHERE username = ?", [$user['username']]);
            if ($userRow = $userResult->fetch_assoc()) {
                $userId = $userRow['id'];

                // حفظ الدرجات
                if (isset($user['scores'])) {
                    foreach ($user['scores'] as $testId => $scores) {
                        foreach($scores as $scoreData){
                             $stmtScore->bind_param("iiisssi", $userId, $testId, $scoreData['score'], $scoreData['total'], $scoreData['date'], $scoreData['time'], $scoreData['timeTaken']);
                             $stmtScore->execute();
                        }
                    }
                }

                // حفظ المحاولات
                if (isset($user['attemptsLeft'])) {
                    foreach ($user['attemptsLeft'] as $testId => $attempts) {
                        $stmtAttempt->bind_param("iii", $userId, $testId, $attempts);
                        $stmtAttempt->execute();
                    }
                }
            }
        }

        // إتمام المعاملة بنجاح
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'تم حفظ البيانات بنجاح']);

    } catch (mysqli_sql_exception $exception) {
        // التراجع عن المعاملة في حال حدوث خطأ
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء الحفظ: ' . $exception->getMessage()]);
    }

    // إغلاق الاتصالات المحضرة
    $stmtTest->close();
    $stmtUser->close();
    $stmtScore->close();
    $stmtAttempt->close();
}

// --| إغلاق اتصال قاعدة البيانات |--
$conn->close();

?>
