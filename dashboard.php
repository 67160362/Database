<?php
// ===== Session & DB =====
require __DIR__ . '/config_mysqli.php';
$mysqli->query("USE s67160362");

// ===== Auth guard =====
if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
$hello_name = htmlspecialchars($_SESSION['user_name'] ?? 'ผู้ใช้', ENT_QUOTES, 'UTF-8');

// ===== Helpers =====
function q(mysqli $db, string $sql): array {
  try { $rs = $db->query($sql); $out=[]; while($r=$rs->fetch_assoc()) $out[]=$r; $rs?->free(); return $out; }
  catch(Throwable $e){ return []; }
}
function s(mysqli $db, string $sql) {
  try { $rs=$db->query($sql); $row=$rs->fetch_row(); $rs?->free(); return $row?($row[0]??0):0; }
  catch(Throwable $e){ return 0; }
}
function nf($n){ return number_format((float)$n, 2); }
function baht($n){ return '฿'.number_format((float)$n, 2); }

// ===== Data (KPI 30 วัน) =====
$kpi = [
  'sales_30d'  => (float)s($mysqli, "SELECT COALESCE(SUM(net_amount),0) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"),
  'qty_30d'    => (int)  s($mysqli, "SELECT COALESCE(SUM(quantity),0)    FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"),
  'buyers_30d' => (int)  s($mysqli, "SELECT COALESCE(COUNT(DISTINCT customer_id),0) FROM fact_sales WHERE date_key >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"),
];

// ===== Monthly (Zero-fill 12 เดือนล่าสุด) =====
$monthly = q($mysqli, "
WITH RECURSIVE m AS (
  SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01') d
  UNION ALL
  SELECT DATE_ADD(d, INTERVAL 1 MONTH) FROM m WHERE d < DATE_FORMAT(CURDATE(),'%Y-%m-01')
),
agg AS (
  SELECT DATE_FORMAT(date_key,'%Y-%m') ym, SUM(net_amount) net_sales
  FROM fact_sales
  GROUP BY 1
)
SELECT DATE_FORMAT(m.d,'%Y-%m') ym, COALESCE(a.net_sales,0) net_sales
FROM m
LEFT JOIN agg a ON a.ym = DATE_FORMAT(m.d,'%Y-%m')
ORDER BY ym
");

// ===== Category share & Top products =====
$category = q($mysqli,"SELECT p.category, SUM(f.net_amount) net_sales
                       FROM fact_sales f JOIN dim_product p ON p.product_id=f.product_id
                       GROUP BY p.category ORDER BY net_sales DESC");

$top = q($mysqli,"SELECT p.product_name, SUM(f.quantity) qty_sold, SUM(f.net_amount) net_sales
                  FROM fact_sales f JOIN dim_product p ON p.product_id=f.product_id
                  GROUP BY p.product_name
                  ORDER BY qty_sold DESC, net_sales DESC LIMIT 10");
$top5 = array_slice($top, 0, 5);

// ===== Hourly (Zero-fill 0–23) =====
$hourly = q($mysqli,"
WITH RECURSIVE h AS (
  SELECT 0 h
  UNION ALL SELECT h+1 FROM h WHERE h < 23
),
agg AS (
  SELECT hour_of_day h, SUM(net_amount) net_sales
  FROM fact_sales GROUP BY hour_of_day
)
SELECT h.h AS hour_of_day, COALESCE(a.net_sales,0) net_sales
FROM h
LEFT JOIN agg a ON a.h = h.h
ORDER BY h.h
");
?>
<!doctype html>
<html lang="th" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Retail DW Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#060712; --panel:#0b0f20; --text:#eef2ff; --muted:#a7b0cc; --ring:rgba(255,255,255,.10);
  --primary:#8b5cf6; --primary-2:#a78bfa; --accent:#22d3ee; --glow:#5eead4;
  --card-grad:linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,.02));
}
:root[data-theme="light"]{
  --bg:#f6f7ff; --panel:#ffffff; --text:#0b1020; --muted:#4b556b; --ring:rgba(0,0,0,.08);
  --primary:#7c3aed; --primary-2:#a78bfa; --accent:#06b6d4; --glow:#22d3ee;
  --card-grad:linear-gradient(180deg, rgba(0,0,0,.03), rgba(0,0,0,.02));
}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:'Poppins',sans-serif;}

/* ===== Neo Aurora Background ===== */
.aurora, .aurora:before, .aurora:after{
  position:fixed; content:""; inset:-10%;
  background:
    radial-gradient(60vmax 40vmax at 10% -10%, rgba(124,58,237,.15), transparent 60%),
    radial-gradient(50vmax 35vmax at 110% 10%, rgba(34,211,238,.12), transparent 60%),
    radial-gradient(70vmax 50vmax at 50% 120%, rgba(167,139,250,.12), transparent 60%);
  filter: blur(40px); z-index:-2; animation:floaty 18s ease-in-out infinite alternate;
}
.aurora:before{animation-duration:22s; mix-blend-mode:screen;}
.aurora:after{animation-duration:26s; mix-blend-mode:overlay;}
@keyframes floaty{from{transform:translateY(0)}to{transform:translateY(30px)}}

/* ===== Grid shimmer layer ===== */
.gridbg{
  position: fixed; inset:0; z-index:-1; pointer-events:none; opacity:.35;
  background-image:
    linear-gradient(var(--ring) 1px, transparent 1px),
    linear-gradient(90deg, var(--ring) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: radial-gradient(60vmax 60vmax at 60% 20%, #000 20%, transparent 60%);
}

/* ===== Glass Nav ===== */
.nav-glass{backdrop-filter:saturate(170%) blur(10px);
  background:linear-gradient(135deg, rgba(139,92,246,.16), rgba(34,211,238,.10));border-bottom:1px solid var(--ring);}
.brand{font-weight:800;letter-spacing:.3px}
.chip{display:inline-flex;align-items:center;gap:.5rem;padding:.35rem .7rem;border-radius:999px;border:1px solid var(--ring);color:var(--muted);font-size:.9rem}
.chip .dot{width:8px;height:8px;border-radius:999px;background:linear-gradient(135deg,var(--primary),var(--accent)); box-shadow:0 0 12px var(--glow);}
.btn-neo{border:1px solid var(--ring); color:var(--text); background:linear-gradient(135deg, rgba(34,211,238,.12), rgba(139,92,246,.08));}
.btn-neo:hover{border-color:rgba(139,92,246,.35);}

/* ===== Layout ===== */
.content{max-width:1240px;margin-inline:auto;padding:28px 16px 80px}
.grid{display:grid;gap:1rem;grid-template-columns:repeat(12,1fr)}
.c-12{grid-column:span 12}.c-6{grid-column:span 6}.c-4{grid-column:span 4}.c-8{grid-column:span 8}
@media(max-width:992px){.c-6,.c-4,.c-8{grid-column:span 12}}

/* ===== Cards ===== */
.cardx{
  background:var(--card-grad); border:1px solid var(--ring); border-radius:18px;
  box-shadow: 0 20px 60px rgba(0,0,0,.25), inset 0 0 0 1px rgba(255,255,255,.02);
  transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
  position:relative; overflow:hidden;
}
.cardx:before{
  content:""; position:absolute; inset:-2px; border-radius:18px;
  background: conic-gradient(from 180deg at 50% 50%, rgba(139,92,246,.28), rgba(34,211,238,.22), rgba(139,92,246,.28));
  filter: blur(24px); opacity:.25; z-index:-1;
}
.cardx:hover{transform:translateY(-3px) rotateX(1deg); border-color:rgba(139,92,246,.35); box-shadow:0 26px 70px rgba(0,0,0,.34)}
.grad{background:linear-gradient(135deg, rgba(139,92,246,.14), rgba(34,211,238,.10)); border:1px solid rgba(139,92,246,.28)}
.section-title{font-weight:700;margin:0}
.kpi{font-size:clamp(1.7rem,2.8vw,2.4rem);font-weight:800}
.kpi-sub{color:var(--muted);font-size:.9rem}
canvas{max-height:360px}
.empty{color:var(--muted);padding:18px}

/* ===== Top5 Table ===== */
.table.top5{
  background: linear-gradient(135deg, rgba(139,92,246,.12), rgba(34,211,238,.08));
  color: var(--text); border-radius: 12px; border: 1px solid rgba(255,255,255,.08); overflow: hidden;
}
.table.top5 th{
  color:#cfd7f3;font-weight:700;letter-spacing:.2px;border-bottom:1px solid rgba(255,255,255,.10);
}
.table.top5 td{border-bottom:1px solid rgba(255,255,255,.06);}
.table.top5 tbody tr:hover{background:rgba(139,92,246,.16);transition:background .18s ease;}
.badge.text-bg-light{background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;border:none;}

/* ===== Micro-interactions ===== */
.tilt:hover{transform:perspective(800px) rotateY(.8deg) translateY(-2px);}
.pulse-glow{box-shadow:0 0 0 0 rgba(94,234,212,.6); animation:pulse 2.2s infinite;}
@keyframes pulse{to{box-shadow:0 0 0 24px rgba(94,234,212,0);}}

/* ===== Toggle Switch ===== */
.theme-toggle{display:inline-flex;align-items:center;gap:.5rem;padding:.35rem .6rem;border-radius:999px;border:1px solid var(--ring);cursor:pointer;}
.theme-toggle i{transition:transform .25s ease;}
</style>
</head>
<body>
<div class="aurora"></div>
<div class="gridbg"></div>

<nav class="navbar navbar-expand-lg nav-glass sticky-top">
  <div class="container-fluid px-3">
    <a class="navbar-brand text-light brand" href="#"><i class="bi bi-graph-up-arrow me-2"></i>Retail DW</a>
    <div class="d-flex align-items-center gap-2">
      <span class="chip"><span class="dot"></span> Live Update</span>
      <span class="chip"><i class="bi bi-shield-lock"></i> Secured</span>
      <span class="chip"><i class="bi bi-person-circle"></i> สวัสดี, <?= $hello_name; ?></span>
      <button id="btnTheme" class="theme-toggle btn-neo"><i class="bi bi-moon-stars"></i><span class="d-none d-md-inline">Theme</span></button>
      <a class="btn btn-sm btn-outline-light btn-neo" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a>
    </div>
  </div>
</nav>

<main class="content">
  <!-- Hero -->
  <div class="cardx p-4 mb-3 grad tilt">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
      <div>
        <h2 class="mb-1">ภาพรวมร้านค้าของคุณ</h2>
        <div style="color:var(--muted)">แดชบอร์ดสรุปยอดขายและประสิทธิภาพล่าสุด </div>
      </div>
      <div class="d-flex gap-2">
        <span class="chip pulse-glow"><i class="bi bi-activity"></i> Uptime 99.9%</span>
        <span class="chip"><i class="bi bi-hdd-network"></i> DW: Online</span>
      </div>
    </div>
  </div>

  <!-- KPI -->
  <div class="grid mb-3">
    <div class="cardx p-3 c-4 grad tilt">
      <span class="text-uppercase small">ยอดขาย 30 วัน</span>
      <div class="kpi" data-count="<?= (float)$kpi['sales_30d'] ?>">฿<?= nf($kpi['sales_30d']) ?></div>
      <div class="kpi-sub"><i class="bi bi-cash-coin me-1"></i>รวมยอดสุทธิ</div>
    </div>
    <div class="cardx p-3 c-4 grad tilt">
      <span class="text-uppercase small">จำนวนชิ้นขาย 30 วัน</span>
      <div class="kpi" data-count="<?= (int)$kpi['qty_30d'] ?>"><?= number_format($kpi['qty_30d']) ?> ชิ้น</div>
      <div class="kpi-sub"><i class="bi bi-box-seam me-1"></i>จำนวนสินค้าทั้งหมด</div>
    </div>
    <div class="cardx p-3 c-4 grad tilt">
      <span class="text-uppercase small">จำนวนผู้ซื้อ 30 วัน</span>
      <div class="kpi" data-count="<?= (int)$kpi['buyers_30d'] ?>"><?= number_format($kpi['buyers_30d']) ?> คน</div>
      <div class="kpi-sub"><i class="bi bi-people me-1"></i>ลูกค้าซื้อซ้ำ</div>
    </div>
  </div>

  <!-- Charts + Table -->
  <div class="grid">
    <div class="cardx p-3 c-8 tilt">
      <h5 class="section-title mb-2">ยอดขายรายเดือน (12 เดือนล่าสุด)</h5>
      <canvas id="chartMonthly"></canvas>
    </div>

    <div class="cardx p-3 c-4 tilt">
      <h5 class="section-title mb-2">สัดส่วนยอดขายตามหมวด</h5>
      <canvas id="chartCategory"></canvas>
    </div>

    <div class="cardx p-3 c-6 tilt">
      <h5 class="section-title mb-2">Top 10 สินค้าขายดี</h5>
      <canvas id="chartTop"></canvas>
    </div>

    <div class="cardx p-3 c-6 tilt">
      <h5 class="section-title mb-2">🔥 สินค้าขายดี อันดับ 1–5</h5>
      <div class="table-responsive">
        <table class="table table-sm table-borderless align-middle mb-0 top5">
          <thead><tr><th>อันดับ</th><th>สินค้า</th><th class="text-end">ชิ้น</th><th class="text-end">ยอดขาย</th></tr></thead>
          <tbody>
            <?php foreach($top5 as $i=>$r): ?>
              <tr>
                <td><span class="badge text-bg-light fw-bold"><?= $i+1 ?></span></td>
                <td><?= htmlspecialchars($r['product_name'],ENT_QUOTES,'UTF-8') ?></td>
                <td class="text-end"><?= number_format($r['qty_sold']) ?></td>
                <td class="text-end"><?= baht($r['net_sales']) ?></td>
              </tr>
            <?php endforeach; if(empty($top5)): ?>
              <tr><td colspan="4" class="empty">ยังไม่มีข้อมูล</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="cardx p-3 c-12 tilt">
      <h5 class="section-title mb-2">ยอดขายรายชั่วโมง (0–23)</h5>
      <canvas id="chartHourly"></canvas>
    </div>
  </div>
</main>
<script>
// ===== Data from PHP =====
const monthlyLabels = <?= json_encode(array_column($monthly,'ym')) ?>;
const monthlyData   = <?= json_encode(array_map('floatval',array_column($monthly,'net_sales'))) ?>;
const catLabels     = <?= json_encode(array_column($category,'category')) ?>;
const catData       = <?= json_encode(array_map('floatval',array_column($category,'net_sales'))) ?>;
const topLabels     = <?= json_encode(array_column($top,'product_name')) ?>;
const topQty        = <?= json_encode(array_map('intval',array_column($top,'qty_sold'))) ?>;
const hrLabels      = <?= json_encode(array_column($hourly,'hour_of_day')) ?>;
const hrData        = <?= json_encode(array_map('floatval',array_column($hourly,'net_sales'))) ?>;

const css = getComputedStyle(document.documentElement);
const clr1     = css.getPropertyValue('--primary').trim() || '#8b5cf6';
const clr2     = css.getPropertyValue('--accent').trim()  || '#22d3ee';
const gridColor= css.getPropertyValue('--ring').trim()    || 'rgba(255,255,255,.1)';
const lblColor = css.getPropertyValue('--text').trim()    || '#eef2ff';
const tickColor= css.getPropertyValue('--muted').trim()   || '#a7b0cc';

// ===== Chart helpers (neon style) =====
function mkLine(ctx,lbl,data){
  new Chart(ctx,{type:'line',
    data:{labels:lbl,datasets:[{label:'ยอดขาย',data,fill:true,
      borderColor:clr1, borderWidth:2,
      backgroundColor:(c)=> {
        const {chart:{ctx,chartArea}}=c;
        if(!chartArea) return 'rgba(139,92,246,.20)';
        const g=ctx.createLinearGradient(0,chartArea.top,0,chartArea.bottom);
        g.addColorStop(0, clr1+'33'); g.addColorStop(1, clr2+'11'); return g;
      },
      tension:.35, pointRadius:0, hoverRadius:4}]},
    options:{plugins:{legend:{labels:{color:lblColor}}},
      scales:{x:{grid:{color:gridColor},ticks:{color:tickColor}},
              y:{beginAtZero:true,grid:{color:gridColor},ticks:{color:tickColor}}}}
  });
}
function mkBar(ctx,lbl,data,label,h=false){
  new Chart(ctx,{type:'bar',
    data:{labels:lbl,datasets:[{label,data,
      borderColor:clr1, backgroundColor:(c)=>{
        const {chart:{ctx,chartArea}}=c; if(!chartArea) return clr1+'55';
        const g=ctx.createLinearGradient(0,0, h?chartArea.right:0, h?0:chartArea.bottom);
        g.addColorStop(0, clr1+'99'); g.addColorStop(1, clr2+'66'); return g;}}]},
    options:{indexAxis:h?'y':'x',plugins:{legend:{labels:{color:lblColor}}},
      scales:{x:{grid:{color:gridColor},ticks:{color:tickColor}},
              y:{beginAtZero:true,grid:{color:gridColor},ticks:{color:tickColor}}}}
  });
}
function mkDoughnut(ctx,lbl,data){
  new Chart(ctx,{type:'doughnut',
    data:{labels:lbl,datasets:[{data,borderWidth:1,borderColor:'#00000020',
      backgroundColor:[clr1,clr2,'#c084fc','#60a5fa','#34d399','#f59e0b']}]},
    options:{plugins:{legend:{position:'bottom',labels:{color:lblColor}}}, cutout:'62%'}
  });
}

// ===== Create charts if data exists =====
if(monthlyLabels.length) mkLine(document.getElementById('chartMonthly'),monthlyLabels,monthlyData);
if(catLabels.length)     mkDoughnut(document.getElementById('chartCategory'),catLabels,catData);
if(topLabels.length)     mkBar(document.getElementById('chartTop'),topLabels,topQty,'ชิ้น',true);
if(hrLabels.length)      mkBar(document.getElementById('chartHourly'),hrLabels,hrData,'ยอดขาย (บาท)');

// ===== KPI Count-up (smooth) =====
(function(){
  const els = document.querySelectorAll('.kpi[data-count]');
  els.forEach(el=>{
    const raw = +el.getAttribute('data-count');
    const isMoney = el.textContent.trim().startsWith('฿');
    const target = Math.max(0, raw);
    const dur = 900, start=performance.now();
    const fmt = (n)=> isMoney ? '฿'+n.toLocaleString(undefined,{maximumFractionDigits:2}) : n.toLocaleString();
    function step(t){
      const k=Math.min(1,(t-start)/dur);
      const ease = 1 - Math.pow(1-k,3);
      el.textContent = fmt(Math.round(target*ease));
      if(k<1) requestAnimationFrame(step);
      else el.textContent = isMoney
        ? '฿'+(raw).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})
        : target.toLocaleString();
    }
    requestAnimationFrame(step);
  });
})();

// ===== Theme Toggle (single source of truth + remember) =====
(function(){
  const key = 'theme';
  const root = document.documentElement;

  // ใช้ธีมที่เคยเลือก (ถ้ามี)
  const saved = localStorage.getItem(key);
  if (saved) root.setAttribute('data-theme', saved);

  const btn = document.getElementById('btnTheme');
  if (!btn) return;

  btn.addEventListener('click', ()=>{
    const next = (root.getAttribute('data-theme') === 'light') ? 'dark' : 'light';
    root.setAttribute('data-theme', next);
    localStorage.setItem(key, next);
    // รีเฟรชเพื่อให้กราฟอ่านค่าสีใหม่ (ง่ายสุดและเสถียร)
    location.reload();
  });
})();

// ===== Reduce motion =====
if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
  document.querySelectorAll('.tilt').forEach(el=>el.style.transition='none');
}
</script>
</body>
</html>
