<?php
$old = file_get_contents('127_0_0_1old.sql');
$new = file('127_0_0_1new.sql', FILE_IGNORE_NEW_LINES);

preg_match_all('/CREATE TABLE `([^`]+)`/', implode("\n", $new), $tables);
$tableList = $tables[1];

$tableStarts = [];
foreach($tableList as $tbl) {
    $pos = strpos($old, "INSERT INTO `$tbl`");
    if($pos !== false) $tableStarts[$tbl] = $pos;
}
asort($tableStarts);

$tableData = [];
$prevPos = 0;
$prevTbl = '';
foreach($tableStarts as $tbl => $pos) {
    if($prevTbl) {
        $data = substr($old, $prevPos, $pos - $prevPos);
        if(preg_match('/INSERT INTO[^;]+;/', $data, $m)) {
            $tableData[$prevTbl] = $m[0];
        }
    }
    $prevPos = $pos;
    $prevTbl = $tbl;
}
$data = substr($old, $prevPos);
if(preg_match('/INSERT INTO[^;]+;/', $data, $m)) {
    $tableData[$prevTbl] = $m[0];
}

echo "Tables with data: " . count($tableData) . "\n";
if(isset($tableData['requests'])) {
    echo "requests length: " . strlen($tableData['requests']) . "\n";
    echo "has 140: " . (strpos($tableData['requests'], '(140,') !== false ? 'yes' : 'no') . "\n";
}
