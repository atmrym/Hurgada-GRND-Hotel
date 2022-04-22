<?php

/**
 * Creates connection to database
 * 
 * @author  @Belal-Elsabbagh
 * @return  mysqli  Connection object to the database
 */
function db_connect(): mysqli
{
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "hurgada-grnd-hotel";
    return new mysqli($servername, $username, $password, $dbname);
}

/**
 * Connects database, runs the given query, and returns the result
 *
 * @param string $sql The sql query to run
 *
 * @return  bool|mysqli_result   The result of the query
 *
 * @var     mysqli_result|bool $result The result of the query
 *
 * @var     mysqli $conn The connection object to database
 * @author  @Belal-Elsabbagh
 */
function run_query(string $sql): bool|mysqli_result
{
    $conn = db_connect();
    $result = $conn->query($sql) or die("\nFAIL\n" . $conn->error);
    $conn->close();
    return $result;
}

/**
 * Logs action in activity Log
 *
 * @param string $action The action that the user made.
 * @param string $description The description of the action.
 * @param float|null $transaction Amount of money transferred.
 * @return  void
 * @var     string $sql
 * @author  @Belal-Elsabbagh
 *
 */
function activity_log(string $action, string $description, ?float $transaction): void
{
    $sql = "INSERT into activity_log
    (owner, actiontype, description, transaction) 
    values({$_SESSION['active_id']},'string$action', 'stringstring$description', $transaction)";
    run_query($sql);
}

 /**
  * Loads the room types from database and echoes them in html
  *
  * @author  @Belal-Elsabbagh
  *
  * @var     string $sql The query to get room types
  * @var     mysqli_result $result The room types
  * @var     string[] $row Each room type
  * @return  void
  */
function load_room_types(): void
{
    $sql = "select * from room_types";
    $result = run_query($sql);
    while ($row = mysqli_fetch_assoc($result))
        echo "<input class='options' type='radio' name='room_type' id='{$row['room_category']}' value='{$row['type_id']}' onchange='change_max_beds()'><label for='{$row['room_category']}'>{$row['room_category']}</label>\n";
}

/**
 * Loads the room views from database and echoes them in html
 *
 * @author  @Belal-Elsabbagh
 *
 * @var     string        $sql    The query to get room views
 * @var     mysqli_result $result The room views
 * @var     string[]      $row    Each room view
 * @return  void
 */
function load_room_views(): void
{
    $sql = "select * from room_views";
    $result = run_query($sql);
    while ($row = mysqli_fetch_assoc($result))
        echo "<input class='options' type='radio' name='room_view' id='{$row['room_view_title']}' value='{$row['room_view_id']}'><label for='{$row['room_view_title']}'>{$row['room_view_title']}</label>\n";
}

/**
 * Gets the difference between two dates in days
 *
 * @author @Belal-Elsabbagh
 *
 * @param DateTime $to_date   The ending date
 *
 * @param DateTime $from_date The starting date
 *
 * @return  int  The difference in days
 */
function get_numberof_days_between_dates(DateTime $from_date, DateTime $to_date): int
{
    return (int)round((strtotime($to_date->format('Y-m-d')) - strtotime($from_date->format('Y-m-d'))) / (60 * 60 * 24));
}

/**
 * Checks availability of room within a certain time period
 *
 * @author @Belal-Elsabbagh
 *
 * @param DateTime $start_date The start date of the booking
 * @param DateTime $end_date   The end date of the booking
 * @param int      $room_id    The room to be booked
 *
 * @return bool Returns true if room is available, false if room is unavailable
 *
 */
function room_isAvailable(int $room_id, DateTime $start_date, DateTime $end_date): bool
{
    $sql = "SELECT room_no FROM reservations
            WHERE ((start_date BETWEEN '{$start_date->format('Y-m-d')}' AND '{$end_date->format('Y-m-d')}') 
            OR (end_date BETWEEN '{$start_date->format('Y-m-d')}' AND '{$end_date->format('Y-m-d')}') 
            OR (start_date >= '{$start_date->format('Y-m-d')}' AND end_date <= '{$end_date->format('Y-m-d')}'))
            AND room_no = $room_id";
    $result = run_query($sql);
    if ($result && $result->num_rows == 0) return true;
    return false;
}

/**
 * Checks the constraints on reservation dates
 *
 * @author @Belal-Elsabbagh
 *
 * @param DateTime $end   End of date range
 * @param DateTime $start Start of date range
 *
 * @return bool             Returns true if date is not feasible, false if date works.
 */
function bad_date(DateTime $start, DateTime $end): bool
{
    $today = new DateTime();
    return $start > $end || $start < $today || $end < $today;
}

/**
 * Gets available rooms for reservation according to given options
 *
 * @author @Belal-Elsabbagh
 *
 * @param DateTime $start     The start date of reservation
 * @param DateTime $end       The end date of reservation
 * @param int      $nBeds     Number of beds in the room (single, double, triple)
 * @param int|null $room_type The room type requested by user
 * @param int|null $room_view The room view requested by user
 * @param int|null $patio     The outdoor setting requested by user
 *
 * @return array   An array with the data of the room
 */
function get_available_rooms(DateTime $start, DateTime $end, int $nBeds, ?int $room_type, ?int $room_view, ?int $patio): array
{
    $date_format = "Y-m-d";
    $start_date_str = $start->format($date_format);
    $end_date_str = $end->format($date_format);

    $get_rooms = "SELECT room_id FROM rooms 
        where room_id NOT IN 
        (
            SELECT room_no FROM reservations 
            WHERE (start_date BETWEEN '$start_date_str' AND '$end_date_str') 
            OR (end_date BETWEEN '$start_date_str' AND '$end_date_str') 
            OR (start_date >= '$start_date_str' AND end_date <= '$end_date_str')
        )
        AND room_beds_number = $nBeds
        AND room_type_id = $room_type 
        AND room_view = $room_view
        AND room_patio = $patio;";

// Check if a room with these options exist
    $result_rooms = run_query($get_rooms);
    if ($result_rooms->num_rows == 0) die("No room matches these options");
    return $result_rooms->fetch_assoc();
}

/**
 * Adds reservation for a room
 *
 * @author @Belal-Elsabbagh
 *
 * @param int      $client_id The client who wants to reserve the room
 * @param int      $room_no   The room number to be reserved
 * @param DateTime $start     The start date of reservation
 * @param DateTime $end       The end date of reservation
 * @param int      $nAdults   Number of adults included
 * @param int      $nChildren Number of children included
 * @param float    $price     The price of the room
 *
 * @return void
 */
function add_reservation(int $client_id, int $room_no, DateTime $start, DateTime $end, int $nAdults, int $nChildren, float $price): void
{
    $date_format = "Y-m-d";
    $start_date_str = $start->format($date_format);
    $end_date_str = $end->format($date_format);

    $book_query = "INSERT into reservations
    values(NULL, $client_id, $room_no, '$start_date_str', '$end_date_str', $nAdults, $nChildren, $price, 0);";
    run_query($book_query);
}

function get_room_price(float $base_price, DateTime $start, DateTime $end): float
{
    return $base_price * get_numberof_days_between_dates($start, $end);
}