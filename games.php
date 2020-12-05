<?php

class Games {

    private $database;

    public function __construct() {
        $this->database = new Database();
    }

    public function show($path) {
        if(count($path)==2) {
            return $this->showGame($path[1]);
        }
        if(count($path)==3) {
            switch($path[1]) {
                case "series":
                case "developer":
                case "publisher":
                case "console":
                    return $this->listCategory($path[1], $path[2]);
                case "genre":
                    return $this->listGenre($path[2]);
            }
        }
        return $this->listAll();
    }

    private function showGame($name) {
        // query
        $stmt = $this->database->get()->prepare("
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
            games.console.name AS `console_name`,
            genre_pri.name1 AS `genre_pri_name1`,
            genre_pri.name2 AS `genre_pri_name2`,
            genre_sec.name1 AS `genre_sec_name1`,
            genre_sec.name2 AS `genre_sec_name2`
            FROM games.games
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
        // create genre string
        $genre = '<a href="/games/genre/'.$game['genre_pri_name1'].'/">'.$game['genre_pri_name1'].'</a>';
        if($game['genre_pri_name2']!==null) {
            $genre .= ' <a href="/games/genre/'.$game['genre_pri_name2'].'/">'.$game['genre_pri_name2'].'</a>';
        }
        if($game['genre_sec_name1']!==null) {
            $genre .= ' &amp; <a href="/games/genre/'.$game['genre_sec_name1'].'/">'.$game['genre_sec_name1'].'</a>';
            if($game['genre_sec_name2']!==null) {
                $genre .= ' <a href="/games/genre/'.$game['genre_sec_name2'].'/">'.$game['genre_sec_name2'].'</a>';
            }
        }
        // output
        $o = '';
        $o .= '<h1>'.$game['name_nice'].'</h1>';
        $o .= '<p><img src="/games/'.$game['id'].'/cover.jpg" height="280" width="280"></p>';
        $o .= '<table>';
        $o .= '<tr><td>Official Title</td><td>'.$game['name_official'].'</td></tr>';
        $o .= '<tr><td>Rating</td><td>'.$game['rating'].'</td></tr>';
        $o .= '<tr><td>Genre</td><td>'.$genre.'</td></tr>';
        $o .= '<tr><td>Series</td><td><a href="/games/series/'.$game['series_name'].'/">'.$game['series_name'].'</a></td></tr>';
        $o .= '<tr><td>Developer</td><td><a href="/games/developer/'.$game['developer_name'].'/">'.$game['developer_name'].'</td></tr>';
        $o .= '<tr><td>Publisher </td><td><a href="/games/publisher/'.$game['publisher_name'].'/">'.$game['publisher_name'].'</td></tr>';
        $o .= '<tr><td>Console</td><td><a href="/games/console/'.$game['console_name'].'/">'.$game['console_name'].'</td></tr>';
        $o .= '</table>';
        $o .= '<p>About: '.$game['description_public'].'</p>';
        return $o;
    }

    private function listCategory($table, $value) {
        // NOTE: cannot bind table name part of query
        // check table name is safe
        $safeTables = ["series", "developer", "publisher", "console"];
        if(array_search($table, $safeTables, true) === false) {
            return '404';
        }
        // query
        $stmt = $this->database->get()->prepare('
            SELECT
            games.id,
            games.name_sort,
            games.name_nice
            FROM games.games
            INNER JOIN games.'.$table.' ON '.$table.'.id = games.'.$table.'_id
            WHERE games.have_game = 1 AND '.$table.'.name = :value
            ORDER BY games.name_sort
        ');
        $stmt->execute(['value' => $value]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // output
        $o = '<h1>Games: '.ucfirst($table).': '.$value.'</h1>';
        foreach($data as $game) {
            $o .= $this->gameTile($game);
        }
        return $o;
    }

    private function listGenre($genre) {
        // query
        $stmt = $this->database->get()->prepare('
            SELECT
            console.name AS `console`,
            games.id,
            games.name_sort,
            games.name_nice
            FROM games.games
            JOIN games.console ON console.id = games.console_id
            INNER JOIN games.genre_joined AS `genre_pri` ON genre_pri.id1 = games.genre_id_pri
            LEFT JOIN games.genre_joined AS `genre_sec` ON genre_sec.id1 = games.genre_id_sec
            WHERE games.have_game = 1 AND (genre_pri.name1 = :genre OR genre_pri.name2 = :genre OR genre_sec.name1 = :genre OR genre_sec.name2 = :genre)
            ORDER BY console.id, games.name_sort
        ');
        $stmt->execute(['genre' => $genre]);
        $data = $stmt->fetchAll(PDO::FETCH_GROUP);
        // output
        $o = '';
        foreach($data as $console => $games) {
            $o .= '<h2>'.$console.'</h2>';
            foreach ($games AS $game) {
                $o .= $this->gameTile($game);
            }
        }
        return $o;
    }

    private function listAll() {
        // query
        $data = $this->database->get()->query("
            SELECT
            console.name AS `console`,
            games.id,
            games.name_sort,
            games.name_nice
            FROM games.games
            INNER JOIN games.console ON console.id = games.console_id
            WHERE games.have_game = 1
            ORDER BY console.id, games.name_sort
        ")->fetchAll(PDO::FETCH_GROUP);
        // output
        $o = '';
        foreach($data as $console => $games) {
            $o .= '<h2>'.$console.'</h2>';
            foreach ($games AS $game) {
                $o .= $this->gameTile($game);
            }
        }
        return $o;
    }

    private function gameTile($game) {
        $o = '';
        $o .= '<div>';
        $o .= '<a href="/games/'.$game['name_sort'].'/">';
        $o .= '<img src="/games/'.$game['id'].'/cover.jpg" height="280" width="280"><br>';
        $o .= $game['name_nice'];
        $o .= '</a>';
        $o .= "</div>\n";
        return $o;
    }
}