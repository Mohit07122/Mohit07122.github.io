<?php 
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$host = 'localhost';  // Database host
$db = 'vashumar_Festival';  // Database name
$user = 'vashumar_Festival';  // Database username
$pass = 'Mohit@Chunar123';  // Database password
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include PHPMailer classes
require '/home/vashumar/festivalgram.factuallearning.com/PHPMailer-master/src/PHPMailer.php';
require '/home/vashumar/festivalgram.factuallearning.com/PHPMailer-master/src/SMTP.php';
require '/home/vashumar/festivalgram.factuallearning.com/PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to send festival notification or reminder email
function sendFestivalEmail($to, $name, $festival_name, $description, $date, $is_reminder = false) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();                                            // Set mailer to use SMTP
        $mail->Host       = 'festivalgram.factuallearning.com';      // Specify main SMTP server
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = 'admin@festivalgram.factuallearning.com';  // SMTP username
        $mail->Password   = 'Mohit@0712';                      // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;          // Enable SSL encryption
        $mail->Port       = 465;                                    // TCP port for SSL connection
        $mail->SMTPDebug  = 0;                                     // Set to 2 for detailed debug output

        // Recipients
        $mail->setFrom('admin@festivalgram.factuallearning.com', 'Festivalgram');
        $mail->addAddress($to, $name);  // Add recipient

        // Format the date as Day Month Year
        $formatted_date = date('d F Y', strtotime($date));

        // Set subject and message based on whether it's a reminder or not
        $subject_prefix = $is_reminder ? "Reminder: " : "Upcoming Festival: ";
        $body_message = $is_reminder 
                        ? "<b>This is a reminder</b> for the upcoming festival happening tomorrow:<br><br>"
                        : "<b>Here is the upcoming festival happening soon:</b><br><br>";

        // Content
        $mail->isHTML(true);                                       // Set email format to HTML
        $mail->Subject = $subject_prefix . "$festival_name";
        $mail->Body    = "<center><img src='https://festivalgram.factuallearning.com/pic.png'></center>
                          <hr>
                          <div style='font-size: 115%;'>
                             <b>Dear</b> <strong>$name</strong>,<br><br>
                             $body_message
                             <b>▹ Festival Name:</b> $festival_name<br>
                             <b>▹ Festival Date:</b> $formatted_date<br><br>
                             <b>▹ About this Festival:</b> $description<br><br>
                             <b>▹ Know More About the Festival: <a href='https://www.google.com/search?q=" . urlencode($festival_name) . "'>Click Here</a></b><br><br>
                             <hr>
                             <p style='font-size: 90%;'><b>▹ My Creator: <a href='https://www.instagram.com/mohit_coder'>Mohit_Coder</a></center></p></b> 
                             <p style='font-size: 90%;'>▹ Not interested? <a href='https://festivalgram.factuallearning.com/unsubscribe.html'> Unsubscribe here </a>.</p>
                          </div>";

        $mail->AltBody = "Dear $name,\nEvent Description:\nFestival Name: $festival_name\nDescription: $description\nDate: $formatted_date\n\nNot interested? Unsubscribe here: https://fivestarcasino.online/work/unsubscribe.html";

        $mail->send();
        echo "Email sent successfully to $name!<br>";
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}<br>";
    }
}

// Fetch upcoming festivals from Calendarific API and send emails
$api_key = '4cuVhvQuhLQjFkzumLGUltpsxevgpJnq';
$country = 'IN';
$year = date('Y');
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$seven_days_later = date('Y-m-d', strtotime('+7 days'));

// Call Calendarific API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://calendarific.com/api/v2/holidays?api_key=$api_key&country=$country&year=$year");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    die("cURL Error: " . curl_error($ch));
}
curl_close($ch);

$festivals = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("JSON decode error: " . json_last_error_msg());
}

// Check if the festival is within the next 7 days (but NOT today)
foreach ($festivals['response']['holidays'] as $holiday) {
    $event_date = $holiday['date']['iso'];

    // Skip festivals occurring today
    if ($event_date == $today) {
        continue;  // Skip today's festivals
    }

    // Email 1 day before the event (send as reminder)
    if ($event_date == $tomorrow) {
        $festival_name = $holiday['name'];
        $description = isset($holiday['description']) ? $holiday['description'] : 'No description available';

        // Fetch all subscriber emails from the database and send festival notifications
        $result = $conn->query("SELECT full_name, email FROM subscribers");
        if (!$result) {
            die("Database query failed: " . $conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            $email = $row['email'];
            $name = $row['full_name'];

            // Check if the email has already been sent for this festival
            $check_query = $conn->prepare("SELECT COUNT(*) FROM sent_emails WHERE email = ? AND festival_name = ? AND event_date = ?");
            $check_query->bind_param("sss", $email, $festival_name, $event_date);
            $check_query->execute();
            $check_query->bind_result($count);
            $check_query->fetch();
            $check_query->close();

            if ($count == 0) { // If no record found, send email
                sendFestivalEmail($email, $name, $festival_name, $description, $event_date, true);  // Send reminder email

                // Record the sent email in the database
                $insert_query = $conn->prepare("INSERT INTO sent_emails (email, festival_name, event_date, created_at) VALUES (?, ?, ?, NOW())");
                $insert_query->bind_param("sss", $email, $festival_name, $event_date);
                if (!$insert_query->execute()) {
                    die("Insert query failed: " . $insert_query->error);
                }
                $insert_query->close();
            } else {
                echo "Email already sent to $name for $festival_name on $event_date.<br>";
            }
        }
    }

    // Email for upcoming festivals (2-7 days in advance)
    elseif ($event_date > $tomorrow && $event_date <= $seven_days_later) {
        $festival_name = $holiday['name'];
        $description = isset($holiday['description']) ? $holiday['description'] : 'No description available';

        // Fetch all subscriber emails from the database and send festival notifications
        $result = $conn->query("SELECT full_name, email FROM subscribers");
        if (!$result) {
            die("Database query failed: " . $conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            $email = $row['email'];
            $name = $row['full_name'];

            // Check if the email has already been sent for this festival
            $check_query = $conn->prepare("SELECT COUNT(*) FROM sent_emails WHERE email = ? AND festival_name = ? AND event_date = ?");
            $check_query->bind_param("sss", $email, $festival_name, $event_date);
            $check_query->execute();
            $check_query->bind_result($count);
            $check_query->fetch();
            $check_query->close();

            if ($count == 0) { // If no record found, send email
                sendFestivalEmail($email, $name, $festival_name, $description, $event_date);  // Send normal email

                // Record the sent email in the database
                $insert_query = $conn->prepare("INSERT INTO sent_emails (email, festival_name, event_date, created_at) VALUES (?, ?, ?, NOW())");
                $insert_query->bind_param("sss", $email, $festival_name, $event_date);
                if (!$insert_query->execute()) {
                    die("Insert query failed: " . $insert_query->error);
                }
                $insert_query->close();
            } else {
                echo "Email already sent to $name for $festival_name on $event_date.<br>";
            }
        }
    }
}

// Close database connection
$conn->close();
?>
