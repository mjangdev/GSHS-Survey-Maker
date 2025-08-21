<?php
/***********************************************************
 * admin.php - 단일 파일
 * 1) 로그인 화면
 * 2) 관리자 페이지(설문 목록/생성/응답조회 + 카카오톡 공유)
 ***********************************************************/
session_start();

/** DB 연결 - 한 파일에 모두 포함 **/
$dbHost = 'localhost';
$dbName = 'DBNAME'; 
$dbUser = 'DBUSERNAME';
$dbPass = 'DBUSERPASSWORD';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
    );
} catch (PDOException $e) {
    echo "DB 연결 실패: " . $e->getMessage();
    exit;
}

// 관리자 비번
$ADMIN_PASSWORD = 'gshs2025!';
$action = $_GET['action'] ?? '';

// 로그아웃
if ($action==='logout') {
    unset($_SESSION['admin_logged_in']);
    header('Location: admin.php');
    exit;
}

// 로그인 여부 체크
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in']!==true) {
    // 로그인 처리
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        $inputPassword = $_POST['admin_password'] ?? '';
        if ($inputPassword===$ADMIN_PASSWORD) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $error_message = "비밀번호가 올바르지 않습니다.";
        }
    }
    // 로그인 폼 표시
    ?>
    <!DOCTYPE html>
    <html lang="ko">
    <head>
      <meta charset="UTF-8">
      <title>관리자 로그인</title>
      <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
      <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap');
        body { font-family: 'Noto Sans KR', sans-serif; }
      </style>
    </head>
    <body class="bg-gray-100 flex flex-col min-h-screen">
      <div class="bg-white shadow z-50">
        <div class="container mx-auto px-4 py-3">
          <h1 class="text-lg font-semibold text-gray-800">경기과학고 설문 관리</h1>
        </div>
      </div>
      <div class="flex-grow flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md mt-5">
          <!-- 로고 -->
          <div class="flex justify-center mb-4">
              <img src="logo.png" alt="Logo" class="h-16">
          </div>

          <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 text-red-600 p-2 rounded mb-4">
              <?php echo $error_message; ?>
            </div>
          <?php endif; ?>

          <form method="POST" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">관리자 비밀번호</label>
              <input type="password" name="admin_password"
                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm 
                       focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                required>
            </div>
            <button type="submit"
              class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 
                     focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
              로그인
            </button>
          </form>
        </div>
      </div>
      <footer class="mt-auto text-center text-sm text-gray-500 py-2">
        © 2025 경기과학고등학교 학생회<br/>
        Developed by 전교부회장 장민서
      </footer>
    </body>
    </html>
    <?php
    exit;
}

// 여기부터 관리자 페이지
if (!$action) $action='manage';

// toggle/delete
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($action==='toggle') {
        // open <-> closed
        $st = $pdo->prepare("SELECT status FROM surveys WHERE id=?");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $newStatus = ($row['status']==='open') ? 'closed' : 'open';
            $upd = $pdo->prepare("UPDATE surveys SET status=? WHERE id=?");
            $upd->execute([$newStatus, $id]);
        }
        header('Location: admin.php?action=manage');
        exit;
    } elseif($action==='delete'){
        $del = $pdo->prepare("DELETE FROM surveys WHERE id=?");
        $del->execute([$id]);
        header('Location: admin.php?action=manage');
        exit;
    }
}

// 새 설문 생성
$createError = null;
$createSuccess = null;
if ($action==='create' && $_SERVER['REQUEST_METHOD']==='POST') {
    $surveyTitle = trim($_POST['survey_title'] ?? '');
    $qTexts = $_POST['question_text'] ?? [];
    $qTypes = $_POST['question_type'] ?? [];
    $qOpts  = $_POST['question_options'] ?? [];

    if ($surveyTitle === '' || count($qTexts) === 0) {
        $createError = "설문 제목과 최소 한 개의 문항은 필수입니다.";
    } else {
        try {
            $st = $pdo->prepare("INSERT INTO surveys (title) VALUES (:title)");
            $st->execute([':title' => $surveyTitle]);
            $surveyId = $pdo->lastInsertId();

            $stQ = $pdo->prepare("
              INSERT INTO survey_questions (survey_id, question_text, question_type, options)
              VALUES (:sid, :qtext, :qtype, :opts)
            ");
            for ($i = 0; $i < count($qTexts); $i++) {
                $txt = trim($qTexts[$i]);
                $typ = $qTypes[$i];
                $opt = null;
                if ($typ === 'objective') {
                    $opt = trim($qOpts[$i]);
                }
                if ($txt !== '') {
                    $stQ->execute([
                        ':sid' => $surveyId,
                        ':qtext' => $txt,
                        ':qtype' => $typ,
                        ':opts' => $opt
                    ]);
                }
            }
            $createSuccess = "새 설문이 생성되었습니다.";
        } catch(PDOException $e) {
            $createError = "설문 생성 오류: " . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>설문 관리자 페이지</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen p-4">
<div class="max-w-5xl mx-auto bg-white shadow-xl rounded-xl p-6">

  <!-- 상단 네비게이션 -->
  <div class="flex justify-between items-center mb-6">
    <div class="flex space-x-4">
      <a href="admin.php?action=manage" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 <?php echo ($action==='manage') ? 'font-bold' : ''; ?>">설문 목록</a>
      <a href="admin.php?action=create" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 <?php echo ($action==='create') ? 'font-bold' : ''; ?>">새 설문 생성</a>
      <a href="admin.php?action=view"   class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600 <?php echo ($action==='view') ? 'font-bold' : ''; ?>">응답 조회</a>
    </div>
    <div>
      <a href="admin.php?action=logout" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">로그아웃</a>
    </div>
  </div>

  <?php if ($action === 'manage'): ?>
    <!-- 설문 목록 + 카카오톡 공유 -->
    <h2 class="text-xl font-bold mb-4">진행중인 설문</h2>
    <?php
      try {
          $openSt = $pdo->query("SELECT * FROM surveys WHERE status='open' ORDER BY created_at DESC");
          $openList = $openSt->fetchAll(PDO::FETCH_ASSOC);
      } catch(PDOException $e) {
          echo "<p class='text-red-500'>DB 오류: " . $e->getMessage() . "</p>";
          exit;
      }
    ?>
    <?php if (count($openList) === 0): ?>
      <p class="text-gray-600 mb-6">진행중인 설문 없음.</p>
    <?php else: ?>
      <div class="overflow-x-auto mb-6">
        <table class="w-full border-collapse">
          <thead class="bg-gray-200">
            <tr>
              <th class="border p-2 text-sm font-semibold">ID</th>
              <th class="border p-2 text-sm font-semibold">제목</th>
              <th class="border p-2 text-sm font-semibold">생성일</th>
              <th class="border p-2 text-sm font-semibold">상태</th>
              <th class="border p-2 text-sm font-semibold">링크</th>
              <th class="border p-2 text-sm font-semibold">작업</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($openList as $sv): ?>
            <tr class="hover:bg-gray-50">
              <td class="border p-2 text-sm text-center"><?php echo $sv['id']; ?></td>
              <td class="border p-2 text-sm"><?php echo htmlspecialchars($sv['title']); ?></td>
              <td class="border p-2 text-sm text-center"><?php echo $sv['created_at']; ?></td>
              <td class="border p-2 text-sm text-center text-green-600">진행중</td>
              <td class="border p-2 text-sm text-center">
                <!-- 설문 링크 -->
                <a href="../survey.php?id=<?php echo $sv['id']; ?>" target="_blank" class="text-blue-500 underline">
                  링크
                </a>
                <br/>
                <!-- 카카오톡 공유 버튼 -->
                <a id="kakao-share-<?php echo $sv['id']; ?>" href="javascript:;">
                  <img src="https://developers.kakao.com/assets/img/about/logos/kakaotalksharing/kakaotalk_sharing_btn_medium.png"
                       alt="카카오톡 공유" />
                </a>
              </td>
              <td class="border p-2 text-sm text-center">
                <a href="admin.php?action=toggle&id=<?php echo $sv['id']; ?>" class="text-blue-500 hover:underline">닫기</a>
                |
                <a href="admin.php?action=delete&id=<?php echo $sv['id']; ?>"
                   onclick="return confirm('정말 삭제?');"
                   class="text-red-500 hover:underline">삭제</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <h2 class="text-xl font-bold mb-4">마감된 설문</h2>
    <?php
      try {
          $closedSt = $pdo->query("SELECT * FROM surveys WHERE status='closed' ORDER BY created_at DESC");
          $closedList = $closedSt->fetchAll(PDO::FETCH_ASSOC);
      } catch(PDOException $e){
          echo "<p class='text-red-500'>DB 오류: " . $e->getMessage() . "</p>";
          exit;
      }
    ?>
    <?php if (count($closedList) === 0): ?>
      <p class="text-gray-600">마감된 설문 없음.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full border-collapse">
          <thead class="bg-gray-200">
            <tr>
              <th class="border p-2 text-sm font-semibold">ID</th>
              <th class="border p-2 text-sm font-semibold">제목</th>
              <th class="border p-2 text-sm font-semibold">생성일</th>
              <th class="border p-2 text-sm font-semibold">상태</th>
              <th class="border p-2 text-sm font-semibold">링크</th>
              <th class="border p-2 text-sm font-semibold">작업</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($closedList as $sv): ?>
            <tr class="hover:bg-gray-50">
              <td class="border p-2 text-sm text-center"><?php echo $sv['id']; ?></td>
              <td class="border p-2 text-sm"><?php echo htmlspecialchars($sv['title']); ?></td>
              <td class="border p-2 text-sm text-center"><?php echo $sv['created_at']; ?></td>
              <td class="border p-2 text-sm text-center text-red-500">마감</td>
              <td class="border p-2 text-sm text-center">
                <a href="../survey.php?id=<?php echo $sv['id']; ?>" target="_blank" class="text-blue-500 underline">
                  링크
                </a>
                <br/>
                <a id="kakao-share-<?php echo $sv['id']; ?>" href="javascript:;">
                  <img src="https://developers.kakao.com/assets/img/about/logos/kakaotalksharing/kakaotalk_sharing_btn_medium.png"
                       alt="카카오톡 공유" style="height: 15px; margin-top:5px;"/>
                </a>
              </td>
              <td class="border p-2 text-sm text-center">
                <a href="admin.php?action=toggle&id=<?php echo $sv['id']; ?>" class="text-blue-500 hover:underline">열기</a>
                |
                <a href="admin.php?action=delete&id=<?php echo $sv['id']; ?>"
                   onclick="return confirm('정말 삭제?');"
                   class="text-red-500 hover:underline">삭제</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- 카카오 SDK 및 공유 스크립트 -->
    <script src="https://t1.kakaocdn.net/kakao_js_sdk/2.7.4/kakao.min.js"></script>
    <script>
      Kakao.init('카카오 js 키'); // 카카오 JS 키
      // 진행중 설문 공유 버튼
      <?php foreach($openList as $sv): ?>
      Kakao.Share.createDefaultButton({
        container: '#kakao-share-<?php echo $sv['id']; ?>',
        objectType: 'feed',
        content: {
          title: '<?php echo addslashes($sv['title']); ?>',
          description: '학생회 주관 설문조사입니다. 많은 참여 부탁드립니다 ⭐',
          imageUrl: 'http://survey.gshs.my/src.jpg',
          link: {
            mobileWebUrl: 'http://survey.gshs.my/survey.php?id=<?php echo $sv['id']; ?>',
            webUrl:       'http://survey.gshs.my/survey.php?id=<?php echo $sv['id']; ?>'
          }
        },
        itemContent: {
          profileText: '경기과학고 학생회 설문조사',
          profileImageUrl: 'http://survey.gshs.my/round_logo.jpg',
        },
        buttons: [
          {
            title: '설문 참여하기',
            link: {
              mobileWebUrl: 'http://survey.gshs.my/survey.php?id=<?php echo $sv['id']; ?>',
              webUrl:       'http://survey.gshs.my/survey.php?id=<?php echo $sv['id']; ?>'
            }
          }
        ]
      });
      <?php endforeach; ?>
      // 마감된 설문 공유 버튼
      <?php foreach($closedList as $sv): ?>
      Kakao.Share.createDefaultButton({
        container: '#kakao-share-<?php echo $sv['id']; ?>',
        objectType: 'feed',
        content: {
          title: '<?php echo addslashes($sv['title']); ?>',
          description: '이미 마감된 설문입니다.',
          imageUrl: 'http://survey.gshs.my/src.jpg',
          link: {
            mobileWebUrl: 'http://survey.gshs.my/survey.php?id=<?php echo $sv['id']; ?>',
            webUrl:       'http://survey.gshs.my/survey.php?id=<?php echo $sv['id']; ?>'
          }
        },
        buttons: [
          {
            title: '설문 상세보기',
            link: {
              mobileWebUrl: 'http://survey.gshs.my/survey.php?id=<?php echo $sv['id']; ?>',
              webUrl:       'http://survey.gshs.my/survey.php?id=<?php echo $sv['id']; ?>'
            }
          }
        ]
      });
      <?php endforeach; ?>
    </script>

  <?php elseif ($action === 'create'): ?>
    <!-- 새 설문 생성 -->
    <h2 class="text-xl font-bold mb-4">새 설문 생성</h2>
    <?php if ($createError): ?>
      <div class="text-red-500 mb-4 font-semibold"><?php echo $createError; ?></div>
    <?php elseif ($createSuccess): ?>
      <div class="text-green-500 mb-4 font-semibold"><?php echo $createSuccess; ?></div>
    <?php endif; ?>
    <form method="POST" action="admin.php?action=create" id="createSurveyForm">
      <div class="mb-4">
        <label class="block text-gray-700 font-medium mb-2">설문 제목</label>
        <input type="text" name="survey_title" class="w-full p-2 border border-gray-300 rounded" required>
      </div>
      <div id="questions-container"></div>

      <button type="button" id="add-question" class="mb-4 bg-indigo-500 text-white py-2 px-4 rounded hover:bg-indigo-600">
        문항 추가
      </button>
      <button type="submit" class="bg-blue-500 text-white w-full py-3 rounded font-bold hover:bg-blue-600">
        설문 생성
      </button>
    </form>

    <template id="question-template">
      <div class="question-item mb-4 p-4 border border-gray-300 rounded">
        <div class="mb-2">
          <label class="block text-gray-700 font-medium">문항 내용</label>
          <input type="text" name="question_text[]" class="w-full p-2 border border-gray-300 rounded" required>
        </div>
        <div class="mb-2">
          <label class="block text-gray-700 font-medium">문항 유형</label>
          <select name="question_type[]" class="w-full p-2 border border-gray-300 rounded question-type">
            <option value="subjective">주관식</option>
            <option value="objective">객관식</option>
            <option value="yesno">찬반</option>
          </select>
        </div>
        <div class="mb-2 options-field hidden">
          <label class="block text-gray-700 font-medium">옵션 (쉼표로 구분)</label>
          <input type="text" name="question_options[]" class="w-full p-2 border border-gray-300 rounded" 
                 placeholder="예) 선택지1, 선택지2, 선택지3">
        </div>
        <button type="button" class="remove-question bg-red-500 text-white py-1 px-3 rounded hover:bg-red-600">
          문항 삭제
        </button>
      </div>
    </template>

    <script>
      const addQBtn = document.getElementById('add-question');
      const qContainer = document.getElementById('questions-container');
      const qTemplate = document.getElementById('question-template').content;

      addQBtn.addEventListener('click', () => {
        const clone = document.importNode(qTemplate, true);
        clone.querySelector('.remove-question').addEventListener('click', function(){
          this.parentNode.remove();
        });
        // 유형 변경 => 옵션필드
        const sel = clone.querySelector('.question-type');
        const optField = clone.querySelector('.options-field');
        sel.addEventListener('change', function(){
          if (this.value === 'objective') {
            optField.classList.remove('hidden');
          } else {
            optField.classList.add('hidden');
          }
        });
        qContainer.appendChild(clone);
      });
    </script>

  <?php elseif ($action === 'view'): ?>
      <!-- 응답 조회 -->
      <h2 class="text-xl font-bold mb-4">응답 조회</h2>
      <?php
      try {
          // 응답이 존재하는 설문만 가져옴 (작성일 기준으로 내림차순)
          $surveys = $pdo->query("
              SELECT DISTINCT s.id, s.title, s.created_at 
              FROM surveys s
              JOIN survey_answers a ON s.id = a.survey_id
              ORDER BY s.created_at DESC
          ")->fetchAll(PDO::FETCH_ASSOC);

          foreach ($surveys as $survey) {
              echo "<div class='mb-8 p-4 border rounded-lg bg-gray-50'>";
              echo "<h3 class='text-lg font-semibold mb-4'>📋 설문: " . htmlspecialchars($survey['title']) . "</h3>";

              // 한 설문에 해당하는 모든 응답을 가져와서, 제출(응답 세트)별로 그룹화함  
              $answersStmt = $pdo->prepare("
                  SELECT sa.*, q.question_text, q.id as question_order 
                  FROM survey_answers sa
                  JOIN survey_questions q ON sa.question_id = q.id
                  WHERE sa.survey_id = ?
                  ORDER BY sa.created_at DESC
              ");
              $answersStmt->execute([$survey['id']]);
              $submissions = [];
              while ($row = $answersStmt->fetch(PDO::FETCH_ASSOC)) {
                  // user_ip와 created_at을 조합하여 한 제출로 간주 (제출 구분 식별자)
                  $key = $row['user_ip'] . '|' . $row['created_at'];
                  $submissions[$key][] = $row;
              }
              
              if (count($submissions) === 0) {
                  echo "<p class='text-gray-600'>응답 없음</p>";
              } else {
                  // 각 제출별로 출력
                  foreach ($submissions as $key => $submission) {
                      // 제출 내의 답변을 문항 순서(question_order) 기준으로 정렬
                      usort($submission, function($a, $b) {
                          return $a['question_order'] <=> $b['question_order'];
                      });
                      list($user_ip, $submission_time) = explode('|', $key);
                      echo "<div class='mb-6 p-4 bg-white rounded shadow'>";
                      echo "<h4 class='font-medium mb-2'>제출 정보: <strong>IP:</strong> " . htmlspecialchars($user_ip) . " | <strong>제출일:</strong> " . $submission_time . "</h4>";
                      echo "<ul class='list-disc pl-5'>";
                      foreach ($submission as $answer) {
                          echo "<li class='mb-1'><strong>문항:</strong> " . htmlspecialchars($answer['question_text']) . " - <strong>응답:</strong> <span style='color:blue;font-weight:bold;'>" . nl2br(htmlspecialchars($answer['answer'])) . "</span></li>";
                      }
                      echo "</ul>";
                      echo "</div>";
                  }
              }
              echo "</div>"; // 설문 끝
          }

          if (count($surveys) === 0) {
              echo "<p class='text-gray-600'>아직 응답이 없습니다.</p>";
          }
      } catch (PDOException $e) {
          echo "<p class='text-red-500'>DB 오류: " . $e->getMessage() . "</p>";
          exit;
      }
      ?>
  <?php else: ?>
    <p>잘못된 action 파라미터입니다.</p>
  <?php endif; ?>

</div>
</body>
</html>
