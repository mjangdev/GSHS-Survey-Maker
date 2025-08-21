<?php
session_start();

// --- DB 연결 (단일 파일로 쓰되, 여기서 직접 PDO 생성 or require 'db.php'
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=gshs_survey;charset=utf8mb4',
        'webadmin',
        'DBjason0407!',
        [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
    );
} catch (PDOException $e) {
    exit("DB 연결 실패: " . $e->getMessage());
}

$surveyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($surveyId <= 0) {
    exit("잘못된 설문 ID.");
}

// 설문 불러오기
try {
    $stmt = $pdo->prepare("SELECT * FROM surveys WHERE id = ?");
    $stmt->execute([$surveyId]);
    $survey = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$survey) exit("존재하지 않는 설문입니다.");
    $isClosed = ($survey['status'] === 'closed');
} catch (PDOException $e) {
    exit("설문 조회 오류: ".$e->getMessage());
}

// 문항 목록
try {
    $stmtQ = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id=? ORDER BY id ASC");
    $stmtQ->execute([$surveyId]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    exit("문항 조회 오류: ".$e->getMessage());
}
$totalSteps = count($questions);

$step = $_GET['step'] ?? 0;
if ($step === 'complete') {
    // 완료 페이지
} else {
    $step = (int)$step;
}

$participationTime = date("Y-m-d H:i:s");
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';

// POST (문항 답변)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentStep = (int)($_POST['current_step'] ?? 0);
    if ($isClosed) {
        // 마감된 설문
        header("Location: ?id={$surveyId}&step=complete");
        exit;
    }
    if ($currentStep >= 1 && $currentStep <= $totalSteps) {
        $q = $questions[$currentStep - 1];
        $questionId = $q['id'];
        $userAnswer = $_POST['answer'] ?? '';
        $_SESSION['survey_answers'][$surveyId][$questionId] = $userAnswer;
    }
    $next = $currentStep + 1;
    if ($next > $totalSteps) {
        // 완료 → DB insert
        try {
            if (!empty($_SESSION['survey_answers'][$surveyId])) {
                $ins = $pdo->prepare("
                  INSERT INTO survey_answers (survey_id, question_id, user_ip, answer)
                  VALUES (:survey_id, :question_id, :user_ip, :answer)
                ");
                foreach ($_SESSION['survey_answers'][$surveyId] as $qId=>$ans) {
                    $ins->execute([
                        ':survey_id'=>$surveyId,
                        ':question_id'=>$qId,
                        ':user_ip'=>$ipAddress,
                        ':answer'=>$ans
                    ]);
                }
                unset($_SESSION['survey_answers'][$surveyId]);
            }
        } catch(PDOException $e) {
            //error_log($e->getMessage());
        }
        header("Location: ?id={$surveyId}&step=complete");
        exit;
    } else {
        header("Location: ?id={$surveyId}&step={$next}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($survey['title']??'설문'); ?></title>
  <meta name="viewport" content="width=device-width, maximum-scale=1.0, user-scalable=no">
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    html,body {
      margin:0; padding:0;
      width:100%; min-height:100vh;
      background:transparent;
    }
    .background-video {
      position:fixed; top:0; left:0;
      width:100%; height:100%; object-fit:cover;
      z-index:-1;
    }
    @keyframes openVertical {
      0% {transform: scaleY(0); opacity:0;}
      70%{transform: scaleY(1.05);opacity:1;}
      100%{transform: scaleY(1);opacity:1;}
    }
    .open-vertical {
      transform-origin:center;
      animation: openVertical 1.2s ease forwards;
    }
    @keyframes fadeIn {
      from{opacity:0;transform:translateY(20px);}
      to{opacity:1; transform:translateY(0);}
    }
    .fade-in{animation:fadeIn 0.5s ease-out;}
    .hover-scale:hover{
      transform:scale(1.05);
      transition: transform 0.3s;
    }
  </style>
</head>
<body>
<video class="background-video" autoplay loop muted playsinline preload="auto">
  <source src="background.mp4" type="video/mp4">
</video>

<div class="relative z-10 flex items-center justify-center min-h-screen">
<?php if ($step===0): ?>
  <!-- 인트로 -->
  <div class="bg-white bg-opacity-70 rounded-xl shadow-2xl w-11/12 max-w-md mx-auto p-8 open-vertical">
    <div class="w-full flex justify-center mb-4">
      <img src="logo.png" alt="로고" class="w-full max-w-xs">
    </div>
    <h1 class="text-3xl font-bold text-center mb-6"><?php echo htmlspecialchars($survey['title']); ?></h1>
    <?php if ($isClosed): ?>
      <p class="text-red-500 text-center text-xl font-semibold">마감된 설문입니다.</p>
    <?php else: ?>
      <div class="text-center text-gray-700 mb-8 text-base">
        <p>참여 시간: <?php echo htmlspecialchars($participationTime); ?></p>
        <p>IP: <?php echo htmlspecialchars($ipAddress); ?></p>
      </div>
      <form method="POST">
        <input type="hidden" name="current_step" value="0">
        <button type="submit"
          class="bg-blue-500 text-white w-full py-6 rounded-full text-2xl font-bold hover:bg-blue-600 hover-scale">
          시작하기
        </button>
      </form>
    <?php endif; ?>
  </div>

<?php elseif($step==='complete'): ?>
  <!-- 완료 -->
  <div class="bg-white bg-opacity-70 rounded-xl shadow-2xl w-11/12 max-w-md mx-auto p-8 fade-in">
    <h2 class="text-3xl font-bold mb-6 text-center">설문이 완료되었습니다!</h2>
    <p class="text-center text-gray-700 text-xl leading-relaxed">
      참여해 주셔서 감사합니다.
    </p>
  </div>

<?php else:
  $step = (int)$step;
  if ($step<1 || $step>$totalSteps) {
    header("Location: ?id={$surveyId}");
    exit;
  }
  // 해당 문항
  $q = $questions[$step-1];
  $progressPercent = round(($step/$totalSteps)*100);
?>
  <div class="bg-white bg-opacity-70 rounded-xl shadow-2xl w-11/12 max-w-md mx-auto p-8 fade-in">
    <div class="mb-6">
      <div class="text-lg text-gray-600 mb-2 text-center">
        진행도: <?php echo $step; ?>/<?php echo $totalSteps; ?>
      </div>
      <div class="w-full bg-gray-300 rounded-full h-4 overflow-hidden">
        <div class="h-4 bg-gradient-to-r from-green-400 via-blue-500 to-purple-500"
             style="width: <?php echo $progressPercent; ?>%;">
        </div>
      </div>
    </div>

    <?php if ($isClosed): ?>
      <p class="text-red-500 text-center text-xl font-semibold mb-6">이미 마감된 설문입니다.</p>
    <?php else: ?>
      <?php
        $qType = $q['question_type'];
        $qText = $q['question_text'];
        $qOpts = $q['options'];
      ?>
      <h2 class="text-2xl font-bold mb-6 text-center"><?php echo htmlspecialchars($qText); ?></h2>
      <form method="POST" class="flex flex-col space-y-6">
        <input type="hidden" name="current_step" value="<?php echo $step; ?>">

        <?php if($qType==='yesno'): ?>
          <!-- 찬반 -->
          <div class="flex space-x-4">
            <button name="answer" value="찬성" type="submit"
                    class="w-1/2 flex flex-col items-center justify-center bg-blue-500 text-white py-8
                           rounded-xl text-2xl font-bold hover:bg-blue-600 hover-scale">
              찬성
            </button>
            <button name="answer" value="반대" type="submit"
                    class="w-1/2 flex flex-col items-center justify-center bg-red-500 text-white py-8
                           rounded-xl text-2xl font-bold hover:bg-red-600 hover-scale">
              반대
            </button>
          </div>

        <?php elseif($qType==='objective'): ?>
          <!-- 객관식(라디오) -->
          <?php
            $optsArr = array_map('trim', explode(',', $qOpts));
            foreach($optsArr as $opt):
          ?>
            <label class="flex items-center bg-blue-50 p-6 rounded-xl hover:bg-blue-100 cursor-pointer text-2xl">
              <input type="radio" name="answer" value="<?php echo htmlspecialchars($opt); ?>" class="mr-4 scale-125">
              <span><?php echo htmlspecialchars($opt); ?></span>
            </label>
          <?php endforeach; ?>
          <button type="submit"
            class="bg-green-500 text-white w-full py-6 rounded-full text-2xl font-bold hover:bg-green-600 hover-scale">
            다음
          </button>

        <?php elseif($qType==='subjective'): ?>
          <!-- 주관식 -->
          <textarea name="answer" rows="4"
            placeholder="자유롭게 의견을 남겨주세요."
            class="w-full p-6 rounded-xl border-2 border-gray-300 focus:outline-none focus:ring-4 
                   focus:ring-blue-500 text-2xl"></textarea>
          <button type="submit"
            class="bg-purple-500 text-white w-full py-6 rounded-full text-2xl font-bold hover:bg-purple-600 hover-scale">
            제출하기
          </button>

        <?php else: ?>
          <!-- 기타 -->
          <input type="text" name="answer"
            class="w-full p-4 border border-gray-300 rounded-lg text-xl"
            placeholder="답변">
          <button type="submit"
            class="bg-blue-500 text-white w-full py-6 rounded-full text-2xl font-bold hover:bg-blue-600 hover-scale">
            다음
          </button>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>
</div>
</body>
</html>
