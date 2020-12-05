<!DOCTYPE html>
<head>
    <title>Games</title>
    <style type="text/css">
        div {
            display: inline-block;
        }
    </style>
</head>
<?php

require_once("../config.php");

class db {
    private $pdo;

    function get() {
        return $this->pdo;
    }

    function __construct($db) {
        global $config;

        $host = "127.0.0.1";
        $user = $config['db_user'];
        $pass = $config['db_pass'];
        $charset = "utf8mb4";

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            echo 'mysql error';
            die;
        }
    }
}
$db = new db("games");

?>
<body>

<h1>Games</h1>

<?php

if(isset($_GET['game'])) {
    showGame($db, $_GET['game']);
} else {
    mainMenu($db);
}

function showGame($db, $name) {
    $stmt = $db->get()->prepare("
        SELECT 
        games.id,
        games.name_official,
        games.name_nice,
        games.rating,
        games.description_public,
        games.release_date,
        games.release_year,
        games.series.name AS `series_name`,
        games.developer.name AS `developer_name`,
        games.publisher.name AS `publisher_name`,
        games.console.name_short AS `console_name_short`,
        games.console.name_long AS `console_name_long`,
        genre_pri.name1 AS `genre_pri_name1`,
        genre_pri.name2 AS `genre_pri_name2`,
        genre_sec.name1 AS `genre_sec_name1`,
        genre_sec.name2 AS `genre_sec_name2`
        FROM games
        LEFT JOIN games.source ON source.id = games.purchase_source_id
        LEFT JOIN games.series ON games.series.id = games.series_id
        INNER JOIN games.developer ON games.developer.id = games.developer_id
        INNER JOIN games.publisher ON games.publisher.id = games.publisher_id
        INNER JOIN games.console ON games.console.id = games.console_id
        INNER JOIN games.genre_joined AS `genre_pri` ON genre_pri.id1 = games.genre_id_pri
        LEFT JOIN games.genre_joined AS `genre_sec` ON genre_sec.id1 = games.genre_id_sec
        WHERE games.name_sort = :name
        ORDER BY games.name_sort
    ");
    $stmt->execute(['name' => $name]);
    $game = $stmt->fetchAll()[0];

    $genre = '<a href="?genre='.$game['genre_pri_name1'].'">'.$game['genre_pri_name1'].'</a>';
    if($game['genre_pri_name2']!==null) {
        $genre .= ' <a href="?genre='.$game['genre_pri_name2'].'">'.$game['genre_pri_name2'].'</a>';
    }
    if($game['genre_sec_name1']!==null) {
        $genre .= ' &amp; <a href="?genre='.$game['genre_sec_name1'].'">'.$game['genre_sec_name1'].'</a>';
        if($game['genre_sec_name2']!==null) {
            $genre .= ' <a href="?genre='.$game['genre_sec_name2'].'">'.$game['genre_sec_name2'].'</a>';
        }
    }

    echo '<h1>'.$game['name_nice'].'</h1>';
    echo '<p><img src="./'.($game['id']%4).'.jpg" height="280" width="280"></p>';
    echo '<table>';
    echo '<tr><td>Official Title</td><td>'.$game['name_official'].'</td></tr>';
    echo '<tr><td>Rating</td><td>'.$game['rating'].'</td></tr>';
    echo '<tr><td>Genre</td><td>'.$genre.'</td></tr>';
    echo '<tr><td>Series</td><td><a href="?series='.$game['series_name'].'">'.$game['series_name'].'</a></td></tr>';
    echo '<tr><td>Developer</td><td><a href="?developer='.$game['developer_name'].'">'.$game['developer_name'].'</td></tr>';
    echo '<tr><td>Publisher </td><td><a href="?publisher='.$game['publisher_name'].'">'.$game['publisher_name'].'</td></tr>';
    echo '<tr><td>Console</td><td><a href="?console='.$game['console_name_short'].'">'.$game['console_name_long'].'</td></tr>';
    echo '</table>';
    echo '<p>About: '.$game['description_public'].'</p>';
}

function mainMenu($db) {
    $data = $db->get()->query("
        SELECT
        console.name_long AS `console`,
        games.id,
        games.name_sort,
        games.name_nice
        FROM games
        JOIN console ON console.id = games.console_id
        WHERE games.have_game = 1
        ORDER BY console.id, games.name_sort
    ")->fetchAll(PDO::FETCH_GROUP);

    foreach($data as $console => $games) {
        echo "<h2>".$console."</h2>";
        foreach ($games AS $game) {
            echo '<div>';
            echo '<a href="?game='.$game['name_sort'] . '">';
            echo '<img src="./'.($game['id']%4).'.jpg" height="280" width="280"><br>';
            echo $game['name_nice'];
            echo '</a>';
            echo "</div>\n";
        }
    }
}
?>