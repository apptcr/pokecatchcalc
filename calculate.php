<?php
header('Content-Type: application/json');

// รับข้อมูล JSON ที่ส่งมาจากหน้าบ้าน
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['probability' => 0, 'error' => 'No data received']);
    exit;
}

// ดึงตัวแปรต่างๆ ออกมา
$baseRate = floatval($input['baseRate']);
$hpPercent = intval($input['hp']);
$ball = $input['ball'];
$statusMult = floatval($input['status']);
$weight = floatval($input['weight']);
$speed = intval($input['speed']);
$types = $input['types']; // เป็น Array

// ตัวแปรจาก Context Panel
$turn = intval($input['turn']);
$isDark = $input['isDark'];
$isTurnOne = $input['isTurnOne'];
$isLove = $input['isLove'];
$myLvl = intval($input['myLvl']);
$enLvl = intval($input['enLvl']);

// --- ส่วน Logic การคำนวณ (ย้ายมาจาก JS) ---

// 1. เช็ค Master Ball
if ($ball === 'master') {
    echo json_encode(['probability' => 100]);
    exit;
}

// 2. หาตัวคูณบอล (Ball Multiplier)
$ballMult = 1;

switch ($ball) {
    case 'poke': $ballMult = 1; break;
    case 'great': $ballMult = 1.5; break;
    case 'ultra': $ballMult = 2; break;
    
    case 'quick': 
        $ballMult = ($isTurnOne) ? 5 : 1; 
        break;
        
    case 'dusk': 
        $ballMult = ($isDark) ? 3 : 1; 
        break;
        
    case 'timer':
        // สูตร: min(4, 1 + turn * 0.3)
        $ballMult = min(4, 1 + ($turn * 0.3));
        break;
        
    case 'net':
        // เช็คธาตุ Water หรือ Bug
        if (in_array('water', $types) || in_array('bug', $types)) {
            $ballMult = 3.5;
        }
        break;
        
    case 'level':
        if ($myLvl <= $enLvl) $ballMult = 1;
        else if ($myLvl > $enLvl * 4) $ballMult = 8;
        else if ($myLvl > $enLvl * 2) $ballMult = 4;
        else $ballMult = 2;
        break;
        
    case 'love':
        $ballMult = ($isLove) ? 8 : 1;
        break;
        
    case 'fast':
        $ballMult = ($speed >= 100) ? 4 : 1;
        break;
        
    // กลุ่ม Cosmetic (Luxury, Premier, etc.) = 1
    default: $ballMult = 1; break;
}

// 3. คำนวณโอกาสจับ (Formula)
$maxHP = 100;
$curHP = $hpPercent;
$catchValue = 0;

if ($ball === 'heavy') {
    // สูตร Heavy Ball (Additive)
    $heavyMod = 0;
    if ($weight < 100) $heavyMod = -20;
    else if ($weight < 200) $heavyMod = 0;
    else if ($weight < 300) $heavyMod = 20;
    else $heavyMod = 30;

    $modifiedRate = $baseRate + $heavyMod;
    if ($modifiedRate < 1) $modifiedRate = 1;
    
    // สูตรคำนวณ Heavy Ball จะต่างจากปกติเล็กน้อย
    $catchValue = ((3 * $maxHP - 2 * $curHP) * $modifiedRate * 1) / (3 * $maxHP) * $statusMult;
} else {
    // สูตรปกติ (Multiplicative)
    $catchValue = ((3 * $maxHP - 2 * $curHP) * $baseRate * $ballMult) / (3 * $maxHP) * $statusMult;
}

// แปลงค่า Catch Value (0-255) เป็น %
$probability = ($catchValue / 255) * 100;

// จำกัดขอบเขต 0-100%
if ($probability > 100) $probability = 100;
if ($probability < 0) $probability = 0;

// ส่งค่ากลับไปให้หน้าเว็บ
echo json_encode(['probability' => $probability]);
?>