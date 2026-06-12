<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

define('TILE_SIZE', 512);
define('TITLE', 'JourneyMap Standalone Viewer');
define('INIT_CENTER_X', 800);
define('INIT_CENTER_Z', 2170);
define('INIT_ZOOM', 2);

$config = loadConfig(__DIR__ . DIRECTORY_SEPARATOR . 'config.properties');

$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$scriptDir = rtrim(str_replace('\\', '/', __DIR__), '/');
$basePath = '';
if ($docRoot !== '' && strpos($scriptDir, $docRoot) === 0) {
    $basePath = substr($scriptDir, strlen($docRoot));
}

$dataDirRaw = $config['data_dir'] ?? 'java版' . DIRECTORY_SEPARATOR . 'mp';
$dataPath = resolvePath($dataDirRaw);
$tileDir = resolveTileDir($dataPath);
$waypointsDir = resolveWaypointsDir($dataPath, $tileDir);

$requestUri = $_SERVER['REQUEST_URI'];
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = '/' . trim($path, '/');

if ($pathInfo !== '' && strpos($path, $pathInfo) !== false) {
    $path = '/' . trim($pathInfo, '/');
}

if ($basePath !== '' && strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
    $path = '/' . trim($path, '/');
}

$api = $_GET['api'] ?? '';
if ($api === 'info') {
    serveInfo($tileDir);
    exit;
} elseif ($api === 'waypoints') {
    serveWaypoints($waypointsDir);
    exit;
} elseif ($api === 'tiles') {
    serveTile($tileDir, $_GET['tile'] ?? '');
    exit;
}

if ($path === '/' || $path === '') {
    serveHtmlPage($tileDir, $waypointsDir);
    exit;
} elseif (strpos($path, '/api/tiles/') === 0) {
    $tileName = substr($path, strlen('/api/tiles/'));
    serveTile($tileDir, $tileName);
    exit;
} elseif ($path === '/api/waypoints') {
    serveWaypoints($waypointsDir);
    exit;
} elseif ($path === '/api/info') {
    serveInfo($tileDir);
    exit;
} else {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not Found';
}

function loadConfig($configFile) {
    $config = [
        'data_dir' => null,
        'listen_address' => 'localhost',
        'port' => '8080'
    ];

    if (file_exists($configFile)) {
        $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $eqPos = strpos($line, '=');
            if ($eqPos !== false) {
                $key = trim(substr($line, 0, $eqPos));
                $value = trim(substr($line, $eqPos + 1));
                $config[$key] = $value;
            }
        }
    }

    return $config;
}

function resolvePath($relativePath) {
    $path = $relativePath;
    if (!isAbsolutePath($path)) {
        $path = __DIR__ . DIRECTORY_SEPARATOR . $path;
    }
    $real = realpath($path);
    if ($real !== false && is_dir($real)) {
        return $real;
    }
    return $path;
}

function isAbsolutePath($path) {
    if ($path === '') return false;
    if (DIRECTORY_SEPARATOR === '\\') {
        if (strlen($path) >= 2 && $path[1] === ':') return true;
        if (strlen($path) >= 1 && $path[0] === '\\') return true;
    }
    return $path[0] === '/';
}

function resolveTileDir($input) {
    $day = $input . DIRECTORY_SEPARATOR . 'overworld' . DIRECTORY_SEPARATOR . 'day';
    if (is_dir($day)) {
        return $input . DIRECTORY_SEPARATOR . 'overworld';
    }

    $day = $input . DIRECTORY_SEPARATOR . 'day';
    if (is_dir($day)) {
        return $input;
    }

    $children = @scandir($input);
    if ($children !== false) {
        foreach ($children as $child) {
            if ($child === '.' || $child === '..') continue;
            $childPath = $input . DIRECTORY_SEPARATOR . $child;
            if (!is_dir($childPath)) continue;
            $day = $childPath . DIRECTORY_SEPARATOR . 'overworld' . DIRECTORY_SEPARATOR . 'day';
            if (is_dir($day)) {
                return $childPath . DIRECTORY_SEPARATOR . 'overworld';
            }
            $day = $childPath . DIRECTORY_SEPARATOR . 'day';
            if (is_dir($day)) {
                return $childPath;
            }
        }
    }

    return $input . DIRECTORY_SEPARATOR . 'overworld';
}

function resolveWaypointsDir($input, $tileDir) {
    $wp = $input . DIRECTORY_SEPARATOR . 'waypoints';
    if (is_dir($wp)) {
        return $wp;
    }

    $parent = dirname($tileDir);
    if ($parent !== false && $parent !== $tileDir) {
        $wp = $parent . DIRECTORY_SEPARATOR . 'waypoints';
        if (is_dir($wp)) {
            return $wp;
        }
        $grandparent = dirname($parent);
        if ($grandparent !== false && $grandparent !== $parent) {
            $wp = $grandparent . DIRECTORY_SEPARATOR . 'waypoints';
            if (is_dir($wp)) {
                return $wp;
            }
        }
    }

    return null;
}

function scanTiles($tileDir) {
    $availableTiles = [];
    $dayDir = $tileDir . DIRECTORY_SEPARATOR . 'day';
    if (is_dir($dayDir)) {
        $files = @scandir($dayDir);
        if ($files !== false) {
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'png') {
                    $availableTiles[] = $file;
                }
            }
        }
    }
    return $availableTiles;
}

function scanWaypoints($waypointsDir) {
    $waypoints = [];
    if ($waypointsDir === null || !is_dir($waypointsDir)) {
        return $waypoints;
    }
    $files = @scandir($waypointsDir);
    if ($files === false) {
        return $waypoints;
    }
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'json') continue;
        $filePath = $waypointsDir . DIRECTORY_SEPARATOR . $file;
        $content = @file_get_contents($filePath);
        if ($content === false) continue;
        $wp = parseWaypointJson($content);
        if ($wp !== null) {
            $waypoints[] = $wp;
        }
    }
    return $waypoints;
}

function parseWaypointJson($json) {
    $data = json_decode($json, true);
    if ($data === null || !isset($data['name'])) {
        return null;
    }
    return [
        'name' => $data['name'],
        'x' => (int)($data['x'] ?? 0),
        'y' => (int)($data['y'] ?? 0),
        'z' => (int)($data['z'] ?? 0),
        'r' => (int)($data['r'] ?? 255),
        'g' => (int)($data['g'] ?? 255),
        'b' => (int)($data['b'] ?? 255),
        'type' => $data['type'] ?? 'Normal',
        'enabled' => !isset($data['enable']) || $data['enable'] !== false
    ];
}

function serveTile($tileDir, $tileName) {
    $tileName = urldecode($tileName);
    $safeName = basename($tileName);

    if (pathinfo($safeName, PATHINFO_EXTENSION) !== 'png') {
        http_response_code(404);
        return;
    }

    $tileFile = $tileDir . DIRECTORY_SEPARATOR . 'day' . DIRECTORY_SEPARATOR . $safeName;

    if (!file_exists($tileFile) || !is_file($tileFile)) {
        http_response_code(404);
        return;
    }

    $lastModified = filemtime($tileFile);
    $etag = '"' . dechex($lastModified) . '"';
    $fileSize = filesize($tileFile);

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400, immutable');
    header('ETag: ' . $etag);
    header('Content-Length: ' . $fileSize);

    $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    if ($ifNoneMatch === $etag) {
        http_response_code(304);
        return;
    }

    http_response_code(200);
    readfile($tileFile);
}

function serveWaypoints($waypointsDir) {
    $waypoints = scanWaypoints($waypointsDir);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($waypoints, JSON_UNESCAPED_UNICODE);
}

function serveInfo($tileDir) {
    $tiles = scanTiles($tileDir);
    $info = [
        'tileSize' => TILE_SIZE,
        'tiles' => $tiles
    ];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($info, JSON_UNESCAPED_UNICODE);
}

function serveHtmlPage($tileDir, $waypointsDir) {
    $tiles = scanTiles($tileDir);
    $waypoints = scanWaypoints($waypointsDir);

    $initCenterX = INIT_CENTER_X;
    $initCenterZ = INIT_CENTER_Z;
    $initZoom = INIT_ZOOM;

    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title><?php echo TITLE; ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { background: #1a1a2e; overflow: hidden; font-family: 'Microsoft YaHei', sans-serif; }
canvas { display: block; cursor: grab; }
canvas:active { cursor: grabbing; }
#info-bar { position: fixed; bottom: 8px; left: 8px; background: rgba(0,0,0,0.7); color: #ccc; padding: 6px 12px; border-radius: 4px; font-size: 12px; pointer-events: none; z-index: 10; }
#tooltip { position: fixed; background: rgba(0,0,0,0.85); color: #fff; padding: 6px 10px; border-radius: 4px; font-size: 12px; pointer-events: none; display: none; z-index: 20; white-space: nowrap; }
#panel { position: fixed; top: 8px; right: 8px; background: rgba(0,0,0,0.8); color: #ccc; border-radius: 6px; font-size: 12px; z-index: 30; max-height: calc(100vh - 60px); overflow-y: auto; width: 170px; }
#panel-header { padding: 8px 10px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; user-select: none; }
#panel-header:hover { background: rgba(255,255,255,0.05); }
#panel-body { padding: 6px 10px 10px; }
#panel-body label { display: flex; align-items: center; padding: 3px 0; cursor: pointer; user-select: none; }
#panel-body label:hover { color: #fff; }
#panel-body input[type=checkbox] { margin-right: 6px; accent-color: #e74c3c; }
.toggle-row { display: flex; align-items: center; padding: 4px 0 8px; border-bottom: 1px solid #333; margin-bottom: 6px; }
.toggle-sw { position: relative; width: 36px; height: 20px; margin-right: 8px; cursor: pointer; flex-shrink: 0; }
.toggle-sw input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; inset: 0; background: #555; border-radius: 10px; transition: .2s; }
.toggle-slider:before { content: ''; position: absolute; width: 16px; height: 16px; left: 2px; bottom: 2px; background: #fff; border-radius: 50%; transition: .2s; }
.toggle-sw input:checked + .toggle-slider { background: #e74c3c; }
.toggle-sw input:checked + .toggle-slider:before { transform: translateX(16px); }
.cat-dot { display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 6px; flex-shrink: 0; }
.cat-count { color: #888; margin-left: auto; font-size: 10px; }
#panel-body label .cat-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.arrow { transition: transform .2s; font-size: 10px; }
.arrow.open { transform: rotate(90deg); }
.opacity-row { display: flex; align-items: center; padding: 4px 0 8px; border-bottom: 1px solid #333; margin-bottom: 6px; }
.opacity-row span { flex-shrink: 0; margin-right: 6px; font-size: 11px; }
.opacity-row input[type=range] { -webkit-appearance: none; appearance: none; width: 100%; height: 4px; background: #444; border-radius: 2px; outline: none; cursor: pointer; }
.opacity-row input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; width: 14px; height: 14px; background: #e74c3c; border-radius: 50%; cursor: pointer; }
.opacity-row input[type=range]::-moz-range-thumb { width: 14px; height: 14px; background: #e74c3c; border-radius: 50%; border: none; cursor: pointer; }
.opacity-val { color: #888; font-size: 10px; margin-left: 6px; flex-shrink: 0; min-width: 24px; text-align: right; }
.color-row { display: flex; align-items: center; padding: 4px 0 8px; border-bottom: 1px solid #333; margin-bottom: 6px; }
.color-row span { flex-shrink: 0; margin-right: 6px; font-size: 11px; }
.color-row input[type=color] { -webkit-appearance: none; border: 1px solid #444; border-radius: 3px; width: 28px; height: 20px; cursor: pointer; background: none; padding: 0; }
.color-row input[type=color]::-webkit-color-swatch-wrapper { padding: 0; }
.color-row input[type=color]::-webkit-color-swatch { border: none; }
.color-swatch { display: inline-block; width: 14px; height: 14px; border-radius: 2px; margin-left: 6px; flex-shrink: 0; }
</style>
</head>
<body>
<canvas id="map"></canvas>
<div id="info-bar">正在初始化...</div>
<div id="tooltip"></div>
<div id="panel">
  <div id="panel-header" onclick="togglePanel()">
    <span>路径点筛选</span><span class="arrow open" id="arrow">&#9654;</span>
  </div>
  <div id="panel-body">
    <div class="toggle-row">
      <label class="toggle-sw">
        <input type="checkbox" id="showAll" checked onchange="toggleAllWaypoints()">
        <span class="toggle-slider"></span>
      </label>
      <span>显示路径点</span>
    </div>
    <div class="opacity-row">
      <span>不透明度</span>
      <input type="range" id="opacitySlider" min="10" max="100" value="100" oninput="updateOpacity()">
      <span class="opacity-val" id="opacityVal">100%</span>
    </div>
    <div class="color-row">
      <span>标签颜色</span>
      <input type="color" id="labelColorPicker" value="#ffeb3b" onchange="updateLabelColor()">
      <span class="color-swatch" id="colorSwatch" style="background:#ffeb3b"></span>
    </div>
    <div id="cat-list"></div>
  </div>
</div>
<script>
var SCRIPT_URL = <?php echo json_encode($_SERVER['SCRIPT_NAME'] ?? 'index.php'); ?>;
var TILE_SIZE = <?php echo TILE_SIZE; ?>;
var API_BASE = SCRIPT_URL + '?api=';
var TILE_API = API_BASE + 'tiles&tile=';
var INIT_CENTER_X = <?php echo $initCenterX; ?>;
var INIT_CENTER_Z = <?php echo $initCenterZ; ?>;
var INIT_ZOOM = <?php echo $initZoom; ?>;

var canvas = document.getElementById('map');
var ctx = canvas.getContext('2d');
var infoBar = document.getElementById('info-bar');
var tooltip = document.getElementById('tooltip');

var tileCache = {};
var MAX_CACHE = 256;
var imageCache = {};
var failedTiles = {};
var availableTiles = new Set();
var waypoints = [];
var initialized = false;

var offsetX = -INIT_CENTER_X;
var offsetY = -INIT_CENTER_Z;
var zoom = INIT_ZOOM;
var minZoom = 0.125;
var maxZoom = 8;

var isDragging = false, dragStartX=0, dragStartY=0, dragOffsetX=0, dragOffsetY=0;
var showWaypoints = true;
var waypointOpacity = 1.0;
var labelColor = '#ffeb3b';
var catFilters = {};

function categorize(name) {
  var s = name.replace(/_-?\d+-?\d+-?\d+$/,'').replace(/x\d+$/,'');
  return s.replace(/^-/,'').replace(/-+$/,'') || '其它';
}

function buildCategoryPanel() {
  var categoryMap = {};
  for (var i=0; i<waypoints.length; i++) {
    var cat = categorize(waypoints[i].name);
    if (!categoryMap[cat]) categoryMap[cat] = [];
    categoryMap[cat].push(i);
  }
  var sortedCats = Object.keys(categoryMap).sort(function(a,b) {
    return categoryMap[b].length - categoryMap[a].length;
  });
  var catList = document.getElementById('cat-list');
  catList.innerHTML = '';
  var catColors = ['#e74c3c','#e67e22','#f1c40f','#2ecc71','#3498db','#9b59b6','#1abc9c','#e91e63','#795548','#607d8b'];
  for (var ci=0; ci<sortedCats.length; ci++) {
    var cat = sortedCats[ci];
    var count = categoryMap[cat].length;
    catFilters[cat] = true;
    var color = catColors[ci % catColors.length];
    var label = document.createElement('label');
    label.innerHTML = '<input type="checkbox" checked onchange="toggleCat(\'' + cat.replace(/'/g,"\\'") + '\')">' +
      '<span class="cat-dot" style="background:' + color + '"></span>' +
      '<span class="cat-label">' + cat + '</span>' +
      '<span class="cat-count">' + count + '</span>';
    catList.appendChild(label);
  }
}

function init() {
  Promise.all([
    fetch(API_BASE + 'info').then(function(r){ return r.json(); }),
    fetch(API_BASE + 'waypoints').then(function(r){ return r.json(); })
  ]).then(function(results) {
    waypoints = results[1];
    availableTiles = new Set(results[0].tiles);
    buildCategoryPanel();
    initialized = true;
  }).catch(function(err) {
    infoBar.textContent = '初始化失败: ' + err.message + ' | 3秒后重试...';
    setTimeout(init, 3000);
  });
}

function toggleCat(cat) { catFilters[cat] = !catFilters[cat]; }
function toggleAllWaypoints() { showWaypoints = document.getElementById('showAll').checked; }
function updateOpacity() {
  var v = document.getElementById('opacitySlider').value;
  waypointOpacity = v / 100;
  document.getElementById('opacityVal').textContent = v + '%';
}
function updateLabelColor() {
  labelColor = document.getElementById('labelColorPicker').value;
  document.getElementById('colorSwatch').style.background = labelColor;
}
function togglePanel() {
  var body = document.getElementById('panel-body');
  var arrow = document.getElementById('arrow');
  if (body.style.display === 'none') { body.style.display = ''; arrow.classList.add('open'); }
  else { body.style.display = 'none'; arrow.classList.remove('open'); }
}

function isWaypointVisible(wp) { return showWaypoints && catFilters[categorize(wp.name)]; }

function resize() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
window.addEventListener('resize', resize); resize();
function worldToScreen(wx,wz){ return { x:(wx+offsetX)*zoom+canvas.width/2, y:(wz+offsetY)*zoom+canvas.height/2 }; }
function screenToWorld(sx,sy){ return { x:(sx-canvas.width/2)/zoom-offsetX, z:(sy-canvas.height/2)/zoom-offsetY }; }
function getTileKey(tx,tz){ return tx+','+tz; }
function loadTile(tx,tz){
  if(!initialized)return;
  var key=getTileKey(tx,tz);
  if(!availableTiles.has(key+'.png')||failedTiles[key])return;
  if(imageCache[key]||tileCache[key]==='loading')return;
  tileCache[key]='loading';
  var img=new Image();
  img.onload=function(){ imageCache[key]=img;delete tileCache[key];
    var keys=Object.keys(imageCache); while(keys.length>MAX_CACHE){delete imageCache[keys[0]];keys.shift();} };
  img.onerror=function(){delete tileCache[key];failedTiles[key]=true;};
  img.src=TILE_API+key+'.png';
}
function draw(){
  ctx.clearRect(0,0,canvas.width,canvas.height);
  if(!initialized) { ctx.fillStyle='#ccc';ctx.font='16px "Microsoft YaHei",sans-serif';
    ctx.textAlign='center';ctx.fillText('正在加载数据...',canvas.width/2,canvas.height/2); return; }
  var tl=screenToWorld(0,0),br=screenToWorld(canvas.width,canvas.height);
  var sts=TILE_SIZE*zoom;
  var sTx=Math.floor(tl.x/TILE_SIZE)-1,sTz=Math.floor(tl.z/TILE_SIZE)-1;
  var eTx=Math.floor(br.x/TILE_SIZE)+1,eTz=Math.floor(br.z/TILE_SIZE)+1;
  var loaded=0;
  for(var tx=sTx;tx<=eTx;tx++){ for(var tz=sTz;tz<=eTz;tz++){
    var key=getTileKey(tx,tz),img=imageCache[key],sp=worldToScreen(tx*TILE_SIZE,tz*TILE_SIZE);
    if(img){ctx.drawImage(img,sp.x,sp.y,sts,sts);loaded++;}
    else{ctx.fillStyle='#2a2a3e';ctx.fillRect(sp.x,sp.y,sts,sts);
      ctx.strokeStyle='#3a3a4e';ctx.strokeRect(sp.x,sp.y,sts,sts);loadTile(tx,tz);}
  }}
  drawWaypoints();
  var cw=screenToWorld(canvas.width/2,canvas.height/2);
  infoBar.textContent='中心: '+Math.round(cw.x)+', '+Math.round(cw.z)+' | 缩放: '+(zoom*100).toFixed(0)+'% | 切片: '+loaded;
}
function drawWaypoints(){
  if(!showWaypoints)return;
  for(var i=0;i<waypoints.length;i++){
    var wp=waypoints[i],pos=worldToScreen(wp.x,wp.z);
    if(!isWaypointVisible(wp))continue;
    if(pos.x<-50||pos.x>canvas.width+50||pos.y<-50||pos.y>canvas.height+50)continue;
    var s=Math.max(6,Math.min(22,10*Math.sqrt(zoom)));
    var alpha=(wp.enabled?1:0.45)*waypointOpacity;
    var color='rgb('+wp.r+','+wp.g+','+wp.b+')';
    ctx.save();ctx.translate(pos.x,pos.y);
    ctx.fillStyle='rgba(0,0,0,'+(0.4*alpha)+')';
    drawDiamond(0,s*0.2,s*0.75);
    ctx.fill();
    ctx.fillStyle='rgb(255,255,255)';
    ctx.globalAlpha=alpha;
    drawDiamond(0,0,s*0.75);
    ctx.fill();
    ctx.fillStyle=color;
    drawDiamond(0,0,s*0.55);
    ctx.fill();
    ctx.strokeStyle='rgba(255,255,255,'+(0.8*alpha)+')';
    ctx.lineWidth=2;
    drawDiamond(0,0,s*0.75);
    ctx.stroke();
    ctx.globalAlpha=1;
    if(zoom>=0.4){
      ctx.shadowColor='rgba(0,0,0,'+(0.7*alpha)+')';
      ctx.shadowBlur=3;
      ctx.fillStyle=labelColor;
      ctx.globalAlpha=alpha;
      ctx.font='bold '+(11*Math.min(1.5,zoom))+'px "Microsoft YaHei",sans-serif';
      ctx.textAlign='center';ctx.fillText(wp.name,0,-s*0.95);
      ctx.globalAlpha=1;
      ctx.shadowBlur=0;
    }
    ctx.restore();
  }
}
function drawDiamond(dx,dy,size){
  ctx.beginPath();
  ctx.moveTo(dx,dy-size);
  ctx.lineTo(dx+size,dy);
  ctx.lineTo(dx,dy+size);
  ctx.lineTo(dx-size,dy);
  ctx.closePath();
}
canvas.addEventListener('mousedown',function(e){
  isDragging=true;dragStartX=e.clientX;dragStartY=e.clientY;
  dragOffsetX=offsetX;dragOffsetY=offsetY;
});
window.addEventListener('mousemove',function(e){
  if(isDragging){offsetX=dragOffsetX+(e.clientX-dragStartX)/zoom;offsetY=dragOffsetY+(e.clientY-dragStartY)/zoom;}
  var hovered=null;
  if(showWaypoints){ for(var i=0;i<waypoints.length;i++){
    var wp=waypoints[i],pos=worldToScreen(wp.x,wp.z);
    if(!isWaypointVisible(wp))continue;
    if(Math.hypot(e.clientX-pos.x,e.clientY-pos.y)<15){hovered=wp;break;}
  }}
  if(hovered){tooltip.style.display='block';tooltip.style.left=(e.clientX+15)+'px';
    tooltip.style.top=(e.clientY-10)+'px';
    tooltip.innerHTML='<b>'+hovered.name+'</b>'+(hovered.enabled?'':' <span style="color:#f66">(禁用)</span>')+'<br>X:'+hovered.x+' Y:'+hovered.y+' Z:'+hovered.z;
  }else{tooltip.style.display='none';}
});
window.addEventListener('mouseup',function(){isDragging=false;});
canvas.addEventListener('wheel',function(e){
  e.preventDefault();
  var wb=screenToWorld(e.clientX,e.clientY);
  var factor=e.deltaY<0?1.15:1/1.15;
  var nz=Math.max(minZoom,Math.min(maxZoom,zoom*factor));
  factor=nz/zoom;zoom=nz;
  var wa=screenToWorld(e.clientX,e.clientY);
  offsetX+=wa.x-wb.x;offsetY+=wa.z-wb.z;
});
canvas.addEventListener('touchstart',function(e){
  if(e.touches.length===1){isDragging=true;dragStartX=e.touches[0].clientX;dragStartY=e.touches[0].clientY;
    dragOffsetX=offsetX;dragOffsetY=offsetY;}
});
canvas.addEventListener('touchmove',function(e){
  e.preventDefault();
  if(isDragging&&e.touches.length===1){offsetX=dragOffsetX+(e.touches[0].clientX-dragStartX)/zoom;
    offsetY=dragOffsetY+(e.touches[0].clientY-dragStartY)/zoom;}
});
canvas.addEventListener('touchend',function(){isDragging=false;});
init();
function gameLoop(){draw();requestAnimationFrame(gameLoop);}
gameLoop();
</script>
</body>
</html>
<?php
}
