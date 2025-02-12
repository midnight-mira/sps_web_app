<?php
// Include database connection
include('../config/connection.php');
session_start();

// Establish database connection
$conn = mysqli_connect(LOCALHOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Retrieve session variables
$batchYear = $_SESSION["batch_year"];
$semNumber = $_SESSION["sem_number"];
$year = $_SESSION["year"];

$sem = "sem" . $semNumber;
$table = "{$batchYear}_{$year}_{$sem}";
$tableDse = "{$batchYear}_dse";
$fullTable = "{$batchYear}_table";

if ($semNumber % 2 === 0) {
    $semPrev = $semNumber - 1;
    $tableOdd = "{$batchYear}_{$year}_sem{$semPrev}";

    // Check if table exists
    $tableExistsQuery = "SELECT 1 FROM `$table` LIMIT 1";
    $tableExists = mysqli_query($conn, $tableExistsQuery);

    if (!$tableExists) {
        $intakeTotal = $intakeFe = $intakeDse = 0;

        if ($sem === "sem2") {
            processSem2($conn, $fullTable, $batchYear);
        } elseif ($sem === "sem4") {
            processSem4($conn, $fullTable, $tableDse, $batchYear);
        } elseif ($year === "TE") {
            processTE($conn, $fullTable, $tableDse, $batchYear);
        } elseif ($year === "BE") {
            processBE($conn, $fullTable, $tableDse, $batchYear);
        }
    }

    session_destroy();
    header("Location: t_success.php");
} else {
    header("Location: dashboard.php");
}
exit();

// Function to process Sem2
function processSem2($conn, $fullTable, $batchYear) {
    $countPass = getCount($conn, $fullTable, "sem1='P' AND sem2='P'");
    $countKt = getCount($conn, $fullTable, "sem1='F' OR sem2='F'");
    $intakeTotal = getCount($conn, $fullTable);

    insertData($conn, 'without_kt', [$batchYear, $intakeTotal, $intakeTotal, $countPass]);
    insertData($conn, 'with_kt', [$batchYear, $intakeTotal, $intakeTotal, $countKt]);
}

// Function to process Sem4
function processSem4($conn, $fullTable, $tableDse, $batchYear) {
    $countPass = getCount($conn, $fullTable, "sem1='P' AND sem2='P' AND sem3='P' AND sem4='P'");
    $countKt = getCount($conn, $fullTable, "sem1='F' OR sem2='F' OR sem3='F' OR sem4='F'");
    $countPassDse = getCount($conn, $tableDse, "sem3='P' AND sem4='P'");
    $countKtDse = getCount($conn, $tableDse, "sem3='F' OR sem4='F'");

    $intakeTotalDse = getCount($conn, $tableDse);
    $intakeTotal = getSumFromTable($conn, 'without_kt', 'intake_total', $batchYear) + $intakeTotalDse;

    updateData($conn, 'without_kt', ['intake_total' => $intakeTotal, 'intake_dse' => $intakeTotalDse, 'year2' => "$countPass + $countPassDse"], $batchYear);
    updateData($conn, 'with_kt', ['intake_total' => $intakeTotal, 'intake_dse' => $intakeTotalDse, 'year2' => "$countKtDse + $countKt"], $batchYear);
}

// Function to process TE
function processTE($conn, $fullTable, $tableDse, $batchYear) {
    $countPass = getCount($conn, $fullTable, "sem1='P' AND sem2='P' AND sem3='P' AND sem4='P' AND sem5='P' AND sem6='P'");
    $countKt = getCount($conn, $fullTable, "sem1='F' OR sem2='F' OR sem3='F' OR sem4='F' OR sem5='F' OR sem6='F'");
    $countPassDse = getCount($conn, $tableDse, "sem3='P' AND sem4='P' AND sem5='P' AND sem6='P'");
    $countKtDse = getCount($conn, $tableDse, "sem3='F' OR sem4='F' OR sem5='F' OR sem6='F'");

    updateData($conn, 'without_kt', ['year3' => "$countPass + $countPassDse"], $batchYear);
    updateData($conn, 'with_kt', ['year3' => "$countKtDse + $countKt"], $batchYear);
}

// Function to process BE
function processBE($conn, $fullTable, $tableDse, $batchYear) {
    $countPass = getCount($conn, $fullTable, "sem1='P' AND sem2='P' AND sem3='P' AND sem4='P' AND sem5='P' AND sem6='P' AND sem7='P' AND sem8='P'");
    $countKt = getCount($conn, $fullTable, "sem1='F' OR sem2='F' OR sem3='F' OR sem4='F' OR sem5='F' OR sem6='F' OR sem7='F' OR sem8='F'");
    $countPassDse = getCount($conn, $tableDse, "sem3='P' AND sem4='P' AND sem5='P' AND sem6='P' AND sem7='P' AND sem8='P'");
    $countKtDse = getCount($conn, $tableDse, "sem3='F' OR sem4='F' OR sem5='F' OR sem6='F' OR sem7='F' OR sem8='F'");

    updateData($conn, 'without_kt', ['year4' => "$countPass + $countPassDse"], $batchYear);
    updateData($conn, 'with_kt', ['year4' => "$countKtDse + $countKt"], $batchYear);
}

// Function to get count from a table
function getCount($conn, $table, $condition = "1=1") {
    $query = "SELECT COUNT(*) as count FROM `$table` WHERE $condition";
    $result = mysqli_query($conn, $query);
    $data = mysqli_fetch_assoc($result);
    return $data['count'] ?? 0;
}

// Function to get sum from a table
function getSumFromTable($conn, $table, $column, $year) {
    $query = "SELECT $column FROM `$table` WHERE year = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $year);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    return $data[$column] ?? 0;
}

// Function to insert data
function insertData($conn, $table, $values) {
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $query = "INSERT INTO `$table` VALUES ($placeholders)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, str_repeat('s', count($values)), ...$values);
    mysqli_stmt_execute($stmt);
}

// Function to update data
function updateData($conn, $table, $updates, $year) {
    $setClause = implode(', ', array_map(fn($col) => "$col = ?", array_keys($updates)));
    $query = "UPDATE `$table` SET $setClause WHERE year = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, str_repeat('s', count($updates)) . 's', ...array_values($updates), $year);
    mysqli_stmt_execute($stmt);
}
?>
