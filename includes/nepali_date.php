<?php
/* =====================================================
   NEPALI DATE HELPER
   AD → BS conversion + Nepali month names
   ===================================================== */

// Nepali month names
define('NP_MONTHS', [
    1  => 'Baisakh',  // बैशाख
    2  => 'Jestha',   // जेठ
    3  => 'Ashadh',   // असाढ
    4  => 'Shrawan',  // श्रावण
    5  => 'Bhadra',   // भाद्र
    6  => 'Ashwin',   // आश्विन
    7  => 'Kartik',   // कार्तिक
    8  => 'Mangsir',  // मंसिर
    9  => 'Poush',    // पौष
    10 => 'Magh',     // माघ
    11 => 'Falgun',   // फाल्गुन
    12 => 'Chaitra',  // चैत्र
]);

// Nepali month names in Devanagari
define('NP_MONTHS_DEV', [
    1  => 'बैशाख',
    2  => 'जेठ',
    3  => 'असाढ',
    4  => 'श्रावण',
    5  => 'भाद्र',
    6  => 'आश्विन',
    7  => 'कार्तिक',
    8  => 'मंसिर',
    9  => 'पौष',
    10 => 'माघ',
    11 => 'फाल्गुन',
    12 => 'चैत्र',
]);

// Days in each Nepali month for years 2078-2110
// Format: [year => [month1_days, month2_days, ..., month12_days]]
define('NP_MONTH_DAYS', [
    2078 => [31,31,32,32,31,30,30,29,30,29,30,30],
    2079 => [31,31,32,32,31,30,30,29,30,29,30,30],
    2080 => [31,32,31,32,31,30,30,30,29,29,30,30],
    2081 => [31,31,32,32,31,30,30,29,30,29,30,30],
    2082 => [31,32,31,32,31,30,30,29,30,29,30,31],
    2083 => [30,32,31,32,31,30,30,30,29,29,30,31],
    2084 => [31,31,32,31,31,31,30,29,30,29,30,30],
    2085 => [31,31,32,32,31,30,30,29,30,29,30,30],
    2086 => [31,32,31,32,31,30,30,29,30,29,30,30],
    2087 => [31,32,31,32,31,30,30,29,30,29,30,30],
    2088 => [31,31,32,32,31,30,30,29,30,29,30,31],
    2089 => [30,32,31,32,31,30,30,30,29,29,30,30],
    2090 => [31,31,32,32,31,30,30,29,30,29,30,30],
    2091 => [31,31,32,32,31,30,30,29,30,29,30,30],
    2092 => [31,32,31,32,31,30,30,30,29,29,30,30],
    2093 => [31,31,32,32,31,30,30,29,30,29,30,30],
    2094 => [31,32,31,32,31,30,30,29,30,29,30,31],
    2095 => [30,32,31,32,31,30,30,30,29,29,30,31],
    2096 => [31,31,32,31,31,31,30,29,30,29,30,30],
    2097 => [31,31,32,32,31,30,30,29,30,29,30,30],
    2098 => [31,32,31,32,31,30,30,29,30,29,30,30],
    2099 => [31,31,32,32,31,30,30,29,30,30,29,31],
    2100 => [30,32,31,32,31,30,30,30,29,29,30,30],
    2101 => [31,31,32,32,31,30,30,29,30,29,30,30],
    2102 => [31,32,31,32,31,30,30,29,30,29,30,30],
    2103 => [31,32,31,32,31,30,30,30,29,29,30,30],
    2104 => [31,31,32,32,31,30,30,29,30,29,30,30],
    2105 => [31,32,31,32,31,30,30,29,30,29,30,31],
    2106 => [30,32,31,32,31,30,30,30,29,29,30,31],
    2107 => [31,31,32,31,31,31,30,29,30,29,30,30],
    2108 => [31,31,32,32,31,30,30,29,30,29,30,30],
    2109 => [31,32,31,32,31,30,30,29,30,29,30,30],
    2110 => [31,32,31,32,31,30,30,30,29,29,30,30],
]);

// Reference point: BS 2081-01-01 = AD 2024-04-13
define('BS_REF_YEAR',  2081);
define('BS_REF_MONTH', 1);
define('BS_REF_DAY',   1);
define('AD_REF_DATE',  '2024-04-13');

/**
 * Convert AD date (YYYY-MM-DD) to BS array [year, month, day]
 */
function adToBS($adDate) {
    $refTs = strtotime(AD_REF_DATE);
    $adTs  = strtotime($adDate);

    if ($adTs === false || $refTs === false) {
        return ['year' => 2081, 'month' => 1, 'day' => 1];
    }

    // Use integer date math to avoid DST fractional issues
    list($ry,$rm,$rd) = explode('-', AD_REF_DATE);
    list($ay,$am,$ad) = explode('-', $adDate);
    $refJd = gregoriantojd((int)$rm,(int)$rd,(int)$ry);
    $adJd  = gregoriantojd((int)$am,(int)$ad,(int)$ay);
    $diffDays = $adJd - $refJd;

    $bsYear  = BS_REF_YEAR;
    $bsMonth = BS_REF_MONTH;
    $bsDay   = BS_REF_DAY;

    if ($diffDays > 0) {
        for ($i = 0; $i < $diffDays; $i++) {
            $bsDay++;
            if ($bsDay > getNpMonthDays($bsYear, $bsMonth)) {
                $bsDay = 1;
                $bsMonth++;
                if ($bsMonth > 12) { $bsMonth = 1; $bsYear++; }
            }
        }
    } elseif ($diffDays < 0) {
        for ($i = 0; $i > $diffDays; $i--) {
            $bsDay--;
            if ($bsDay < 1) {
                $bsMonth--;
                if ($bsMonth < 1) { $bsMonth = 12; $bsYear--; }
                $bsDay = getNpMonthDays($bsYear, $bsMonth);
            }
        }
    }

    return ['year' => $bsYear, 'month' => $bsMonth, 'day' => $bsDay];
}

/**
 * Get days in a Nepali month
 */
function getNpMonthDays($year, $month) {
    $days = NP_MONTH_DAYS;
    if (isset($days[$year][$month - 1])) {
        return $days[$year][$month - 1];
    }
    // Default fallback
    return $month <= 6 ? 31 : 30;
}

/**
 * Convert BS [year, month, day] to AD date string YYYY-MM-DD
 */
function bsToAD($bsYear, $bsMonth, $bsDay) {
    // Use Julian Day Number arithmetic to avoid DST issues
    list($ry,$rm,$rd) = explode('-', AD_REF_DATE);
    $refJd = gregoriantojd((int)$rm,(int)$rd,(int)$ry);

    // Count BS days from reference to target
    $refCount    = bsDayCount(BS_REF_YEAR, BS_REF_MONTH, BS_REF_DAY);
    $targetCount = bsDayCount($bsYear, $bsMonth, $bsDay);
    $diff        = $targetCount - $refCount; // days to add to reference

    $targetJd = $refJd + $diff;
    list($m,$d,$y) = explode('/', jdtogregorian($targetJd));
    return sprintf('%04d-%02d-%02d', $y, $m, $d);
}

function bsDayCount($year, $month, $day) {
    $total = 0;
    for ($y = 2078; $y < $year; $y++) {
        for ($m = 1; $m <= 12; $m++) {
            $total += getNpMonthDays($y, $m);
        }
    }
    for ($m = 1; $m < $month; $m++) {
        $total += getNpMonthDays($year, $m);
    }
    $total += $day;
    return $total;
}

/**
 * Get current BS date
 */
function getCurrentBS() {
    return adToBS(date('Y-m-d'));
}

/**
 * Get month name
 */
function getNpMonthName($month, $devanagari = false) {
    if ($devanagari) {
        $names = NP_MONTHS_DEV;
    } else {
        $names = NP_MONTHS;
    }
    return $names[$month] ?? 'Unknown';
}

/**
 * Check if AD date is Friday or Saturday (Nepal weekend)
 * Returns true if holiday
 */
function isWeekend($adDate) {
    $dow = (int)date('w', strtotime($adDate)); // 0=Sun, 5=Fri, 6=Sat
    return ($dow === 5 || $dow === 6);
}

/**
 * Get day of week name
 */
function getDayName($adDate) {
    return date('D', strtotime($adDate)); // Mon, Tue, ...
}

/**
 * Get all days in a BS month including weekends
 * Fri & Sat marked as is_weekend=true
 * Returns array of AD dates
 */
function getSchoolDaysInBSMonth($bsYear, $bsMonth) {
    $daysInMonth = getNpMonthDays($bsYear, $bsMonth);
    $allDays     = [];

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $adDate = bsToAD($bsYear, $bsMonth, $day);
        $dow    = (int)date('w', strtotime($adDate));
        // Friday=5, Saturday=6 → weekend/holiday
        $isWeekend = ($dow === 5 || $dow === 6);
        $allDays[] = [
            'bs_day'     => $day,
            'ad_date'    => $adDate,
            'dow'        => $dow,
            'day_name'   => date('D', strtotime($adDate)),
            'is_weekend' => $isWeekend,
        ];
    }
    return $allDays;
}
