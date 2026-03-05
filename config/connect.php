
<?php

$servername = "localhost";
$username = "root";
$password = "Mbspro30";
$dbname = "myfood";
$conn = mysqli_connect($servername, $username, $password, $dbname);
mysqli_query($conn,"SET NAMES utf8");

// ประกาศตัวแปรค่าคงที่สำหรับ Gemini API Key
define('GEMINI_API_KEY', 'AIzaSyCOYyxP0y_3_8bz_hZexiBGzQRvW-XRViw');
define('N8N_SECRET', 'f8a3c9b2-1e4d-4a7f-9c2b-8d6e5a4f3b1c');

?>