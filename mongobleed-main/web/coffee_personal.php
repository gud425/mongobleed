<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>🏇 RAON COFFEE - 개인전</title>
  <link href="https://fonts.googleapis.com/css2?family=Black+Han+Sans&family=Noto+Sans+KR:wght@400;700;900&display=swap" rel="stylesheet">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    :root {
      --primary:#FF3366; --secondary:#FFD700; --accent:#00D9FF;
      --dark:#1a0033; --light:#FFF5E6; --track-bg:#2a5c3f; --track-border:#8B4513;
      --hill-color:#8B7355; --downhill-color:#5C8A7D;
    }
    body {
      font-family:'Noto Sans KR', sans-serif;
      background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height:100vh; display:flex; justify-content:center; align-items:center; overflow-x:hidden;
    }
    .container {
      width:95%; max-width:1600px; background:rgba(255,255,255,0.95);
      border-radius:30px; box-shadow:0 30px 80px rgba(0,0,0,0.3); padding:40px; position:relative; overflow:hidden;
    }
    .container::before {
      content:''; position:absolute; top:0; left:0; right:0; height:8px;
      background:linear-gradient(90deg, var(--primary), var(--accent), var(--secondary), var(--primary));
      background-size:200% 100%; animation:shimmer 3s linear infinite;
    }
    @keyframes shimmer { 0%{background-position:200% 0;} 100%{background-position:-200% 0;} }
    h1 {
      font-family:'Black Han Sans', sans-serif; font-size:3.0em; text-align:center;
      background:linear-gradient(135deg, var(--primary), var(--accent));
      -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-bottom:24px; animation:pulse 2s ease-in-out infinite;
    }
    @keyframes pulse { 0%,100%{transform:scale(1);} 50%{transform:scale(1.02);} }
    .setup-screen, .game-screen, .result-screen { display:none; }
    .setup-screen.active, .game-screen.active, .result-screen.active { display:block; }
    .form-row { display:flex; gap:12px; align-items:center; justify-content:center; margin:14px 0; }
    .input {
      width: 280px; padding:14px 16px; border:3px solid var(--accent); border-radius:14px;
      font-size:1em; transition:all .3s; background:#fff;
    }
    .input:focus { outline:none; border-color:var(--primary); transform:translateY(-2px); box-shadow:0 5px 20px rgba(255,51,102,.25); }
    .btn {
      background:linear-gradient(135deg, var(--primary), #FF6B9D); color:#fff; border:0; padding:14px 24px;
      font-size:1.1em; font-weight:900; border-radius:14px; cursor:pointer; font-family:'Black Han Sans', sans-serif;
      box-shadow:0 10px 30px rgba(255, 51, 102, 0.35); transition:all .25s;
    }
    .btn:hover { transform:translateY(-2px) scale(1.03); box-shadow:0 15px 40px rgba(255, 51, 102, 0.5); }
    .btn-ghost {
      background:#eef1f7; color:#223; border:0; padding:12px 18px; border-radius:12px; font-weight:800; cursor:pointer;
    }
    .speed-box {
      display:inline-flex; align-items:center; gap:14px; background:rgba(255,255,255,0.9);
      padding:12px 20px; border-radius:16px; box-shadow:0 5px 20px rgba(0,0,0,0.08);
    }
    .speed-indicator {
      position:fixed; top:20px; right:20px; background:rgba(255,51,102,0.95); color:#fff; padding:12px 20px;
      border-radius:16px; font-weight:900; font-size:1.1em; z-index:1000; box-shadow:0 5px 20px rgba(0,0,0,0.3); animation:pulse 2s ease-in-out infinite;
    }
    .track-container {
      position:relative; width:100%; height:750px; margin: 24px auto;
      background:var(--track-bg); border:10px solid var(--track-border); border-radius:20px; box-shadow:inset 0 0 50px rgba(0,0,0,0.5); overflow:hidden;
    }
    .finish-line {
      position:absolute; top:10px; left:50%; transform:translateX(-50%); width:120px; height:10px;
      background:repeating-linear-gradient(90deg, white 0px, white 10px, black 10px, black 20px);
      border-radius:5px; z-index:10; box-shadow:0 0 30px rgba(255,215,0,0.9);
    }
    .track {
      position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width:90%; height:85%;
      border:8px dashed rgba(255,255,255,0.5); border-radius:10px; z-index:2;
    }
    .terrain { position:absolute; z-index:1; opacity:.7; }
    .terrain.hill {
      background:repeating-linear-gradient(45deg, var(--hill-color), var(--hill-color) 10px, #A0826D 10px, #A0826D 20px);
      border:3px solid #6B5444; border-radius:10px;
    }
    .terrain.downhill {
      background:repeating-linear-gradient(-45deg, var(--downhill-color), var(--downhill-color) 10px, #4A7C70 10px, #4A7C70 20px);
      border:3px solid #3A5C50; border-radius:10px;
    }
    .terrain-label {
      position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); font-weight:900; font-size:1.1em; color:white; text-shadow:2px 2px 4px rgba(0,0,0,0.5); pointer-events:none;
    }
    .horse { position:absolute; font-size:2.5em; transition:all .2s ease-out; z-index:5; filter:drop-shadow(3px 3px 5px rgba(0,0,0,0.3)); }
    .horse-info {
      position:absolute; background:rgba(255,255,255,0.95); padding:5px 12px; border-radius:20px; font-weight:700; font-size:.35em;
      white-space:nowrap; top:-20px; left:50%; transform:translateX(-50%); box-shadow:0 3px 10px rgba(0,0,0,0.2); border:2px solid var(--accent);
    }
    .stamina-bar { position:absolute; top:-35px; left:50%; transform:translateX(-50%); width:80px; height:6px; background:rgba(0,0,0,0.3); border-radius:3px; overflow:hidden; font-size:.3em; }
    .stamina-fill { height:100%; background:linear-gradient(90deg, #4CAF50, #8BC34A); transition:width .3s, background .3s; }
    .stamina-fill.low { background:linear-gradient(90deg, #FF9800, #FFC107); }
    .stamina-fill.critical { background:linear-gradient(90deg, #F44336, #E91E63); animation:pulse-stamina .5s ease-in-out infinite; }
    @keyframes pulse-stamina { 0%,100%{opacity:1;} 50%{opacity:.6;} }
    .strategy-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:.8em; margin-left:5px; }
    .strategy-도주 { background:linear-gradient(135deg, #FF6B6B, #FF8E53); color:#fff; }
    .strategy-선행 { background:linear-gradient(135deg, #FFB347, #FFCC33); color:#fff; }
    .strategy-선입 { background:linear-gradient(135deg, #4ECDC4, #44A08D); color:#fff; }
    .strategy-추입 { background:linear-gradient(135deg, #F38181, #AA076B); color:#fff; }
    .rank-indicator {
      position:absolute; bottom:-25px; left:50%; transform:translateX(-50%); background:var(--secondary); color:var(--dark);
      padding:3px 10px; border-radius:15px; font-size:.4em; font-weight:900; box-shadow:0 2px 8px rgba(0,0,0,0.3);
    }
    .effect-popup {
      position:fixed; top:50%; left:50%; transform:translate(-50%, -50%);
      background:linear-gradient(135deg, var(--primary), var(--accent)); color:#fff; padding:18px 30px; border-radius:16px; font-size:1.4em; font-weight:900;
      font-family:'Black Han Sans', sans-serif; z-index:1000; animation:effectPopup 1.5s ease-out forwards; box-shadow:0 20px 60px rgba(0,0,0,0.4);
    }
    @keyframes effectPopup {
      0%{ transform:translate(-50%,-50%) scale(0) rotate(-180deg); opacity:0; }
      50%{ transform:translate(-50%,-50%) scale(1.2) rotate(10deg); opacity:1; }
      100%{ transform:translate(-50%,-50%) scale(0) rotate(180deg); opacity:0; }
    }
    .ranking-item {
      display:flex; align-items:center; gap:12px; padding:10px; border-radius:12px; background:linear-gradient(135deg, #f8f9fa, #e9ecef); margin:10px 0; font-weight:700;
    }
    .rank-number-result { width:52px; text-align:center; font-weight:900; color:#FF3366; font-size:1.2em; }
    .overlay { position:fixed; inset:0; background:rgba(0,0,0,0.55); display:none; align-items:center; justify-content:center; }
    .overlay .panel { width:100%; max-width:640px; background:#fff; border-radius:16px; padding:16px; }
    .pager { display:flex; align-items:center; gap:8px; justify-content:space-between; margin-top:6px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="setup-screen active">
      <h1>🏇 RAON COFFEE 개인전</h1>
      <div class="form-row">
        <input id="userName" class="input" placeholder="이름 입력 후 Enter" maxlength="20" />
        <button class="btn" onclick="startSolo()">솔로 시작</button>
      </div>
      <div class="form-row" style="justify-content:center;">
        <span>배속</span>
        <div class="speed-box">
          <span>🐌</span>
          <input id="speedControl" type="hidden" value="3" />
          <span id="speedValue">3.0x</span>
          <span>🚀</span>
        </div>
        <div id="speedIndicator" class="speed-indicator">속도: 3.0x</div>
      </div>
    </div>

    <div class="game-screen">
      <h1>🏁 경주 진행중...</h1>
      <div class="track-container">
        <div class="finish-line"></div>
        <div id="terrains"></div>
        <div class="track"></div>
        <div id="horses"></div>
      </div>
    </div>

    <div class="result-screen">
      <h1>🏆 경기 결과</h1>
      <div class="ranking-item" style="margin-top:8px;">
        <div id="loserEmoji" style="font-size:2em;">😭</div>
        <div style="flex:1;">
          <div style="font-weight:800;">최하위</div>
          <div id="loserNameResult" style="color:#333;">-</div>
        </div>
      </div>
      <div id="rankingList" style="margin-top:12px;"></div>
      <div class="form-row" style="justify-content:flex-end;">
        <button class="btn" onclick="resetGame()">다시하기</button>
        <a class="btn-ghost" href="/rankings">랭킹 보드</a>
      </div>
    </div>
  </div>

  <div id="rankingOverlay" class="overlay">
    <div class="panel">
      <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
        <div style="font-weight:900;">글로벌 랭킹</div>
        <button id="rankingOverlayClose" class="btn-ghost">닫기</button>
      </div>
      <div id="rankingOverlayList"></div>
      <div class="pager">
        <button id="rankingPrev" class="btn-ghost">이전</button>
        <div id="rankingOverlayPageInfo" style="color:#666;">1 / 1</div>
        <button id="rankingNext" class="btn">다음</button>
      </div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.2.0/crypto-js.min.js"></script>
  <script src="/js/coffee_personal.js"></script>
  <script>
    try { document.getElementById('rankingOverlay').style.display = 'none'; } catch (e) {}
  </script>
</body>
</html>

