<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>글로벌 랭킹 보드</title>
  <style>
    body { margin:0; font-family: system-ui, -apple-system, 'Noto Sans KR', sans-serif; background:#f7f7fb; color:#222; }
    .wrap { min-height: 100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
    .card { width:100%; max-width:720px; background:#fff; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,0.08); padding:24px; }
    h1 { margin:0 0 8px; font-size:22px; }
    .list { margin-top:12px; }
    .row { display:flex; gap:16px; align-items:center; padding:12px; border-radius:12px; background:linear-gradient(135deg,#f8f9fa,#e9ecef); margin-bottom:10px; }
    .rank { width:52px; text-align:center; font-weight:900; }
    .info { flex:1; }
    .name { font-size:1.1em; font-weight:700; }
    .meta { font-size:0.9em; color:#666; margin-top:4px; }
    .footer { display:flex; align-items:center; justify-content:space-between; margin-top:12px; gap:12px; }
    .btn { appearance:none; border:0; border-radius:10px; padding:10px 14px; font-weight:800; cursor:pointer; }
    .btn-ghost { background:#eef1f7; color:#223; }
    .btn-primary { background:#4f46e5; color:#fff; }
    .pager { display:flex; gap:8px; }
    .page-info { color:#666; font-size:12px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>🏅 글로벌 랭킹 보드</h1>
      <div id="pageInfo" class="page-info">1 / 1</div>
      <div id="list" class="list"></div>
      <div class="footer">
        <a href="/" class="btn btn-ghost">처음으로</a>
        <div class="pager">
          <button id="prev" class="btn btn-ghost">이전</button>
          <button id="next" class="btn btn-primary">다음</button>
        </div>
      </div>
    </div>
  </div>
  <script>
    const listEl = document.getElementById('list');
    const pageInfoEl = document.getElementById('pageInfo');
    const prevBtn = document.getElementById('prev');
    const nextBtn = document.getElementById('next');
    let page = Math.max(1, parseInt(new URLSearchParams(location.search).get('page')) || 1);
    const pageSize = 10;
    function render(items, totalPages) {
      listEl.innerHTML = '';
      items.forEach(item => {
        const row = document.createElement('div');
        row.className = 'row';
        const rank = document.createElement('div');
        rank.className = 'rank';
        rank.textContent = (item.rank || '-') + '위';
        const info = document.createElement('div');
        info.className = 'info';
        const name = document.createElement('div');
        name.className = 'name';
        name.textContent = item.name || '';
        const meta = document.createElement('div');
        meta.className = 'meta';
        const wins = typeof item.wins === 'number' ? item.wins : 0;
        meta.textContent = '우승 ' + wins + '회' + (item.lastWinAt ? ' | 최근: ' + item.lastWinAt : '');
        info.appendChild(name);
        info.appendChild(meta);
        row.appendChild(rank);
        row.appendChild(info);
        listEl.appendChild(row);
      });
      pageInfoEl.textContent = page + ' / ' + totalPages;
      prevBtn.disabled = page <= 1;
      nextBtn.disabled = page >= totalPages;
    }
    function fetchPage() {
      fetch('/api/rankings/list.php?page=' + page + '&pageSize=' + pageSize)
        .then(r => r.json())
        .then(d => {
          if (!d || !d.ok) { listEl.innerHTML = '데이터가 없습니다.'; return; }
          render(d.items || [], d.totalPages || 1);
        })
        .catch(() => { listEl.innerHTML = '오류가 발생했습니다.'; });
    }
    prevBtn.onclick = () => { if (page > 1) { page -= 1; history.replaceState(null, '', '?page=' + page); fetchPage(); } };
    nextBtn.onclick = () => { page += 1; history.replaceState(null, '', '?page=' + page); fetchPage(); };
    fetchPage();
  </script>
</body>
</html>


