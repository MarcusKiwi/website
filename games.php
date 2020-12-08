<?php

class Games
{

    private $database;

    public function __construct()
    {
        $this->database = new Database();
    }

    public function makePage($path)
    {
        $o = '
            <br>
            <a href="/games/wanted/">wanted</a>
            <a href="/games/console/">console</a>
            <a href="/games/genre/">genre</a>
            <a href="/games/series/">series</a>
            <a href="/games/developer/">developer</a>
            <a href="/games/publisher/">publisher</a>
            <br>
        ';
        $o .= $this->route($path);
        return $o;
    }

    private function route($path)
    {
        if(count($path) == 1) {
            return $this->listCategory('console');
        }
        if(count($path) == 2) {
            switch($path[1]) {
                case "wanted":
                    return $this->listWanted();
                case "series":
                case "developer":
                case "publisher":
                case "console":
                case "genre":
                    return $this->listCategory($path[1]);
                default:
                    return $this->showGame($path[1]);
            }
        }
        if(count($path) == 3) {
            switch($path[1]) {
                case "series":
                case "developer":
                case "publisher":
                case "console":
                case "genre":
                    return $this->showCategory($path[1], $path[2]);
            }
        }
        return 404;
    }

    private function listWanted()
    {
        // query
        $stmt = $this->database->get()->query('
            SELECT
            console.name,
            game.name_nice
            FROM games.game
            INNER JOIN games.console ON console.id = game.console_id
            WHERE game.have_game = 0
            ORDER BY console.id, game.name_sort
        ');
        // check sql fail
        if($stmt === false) {
            return 401;
        }
        $data = $stmt->fetchAll(PDO::FETCH_GROUP);
        // check result count: not applicable
        // output
        $o = '<h1>Games: Wanted</h1>';
        foreach($data as $console => $games) {
            $o .= '<h2>'.$console.'</h2>';
            foreach($games as $game) {
                $o .= $game['name_nice'].'<br>';
            }
        }
        return $o;
    }

    private function listCategory($table)
    {
        // NOTE: cannot bind table name part of query
        // check table name is safe
        $safeTables = ["series", "developer", "publisher", "console", "genre"];
        if(array_search($table, $safeTables, true) === false) {
            return 401;
        }
        // query
        $stmtColumns = '
            SELECT
            '.$table.'.name AS `'.$table.'`,
            game.id,
            game.name_sort,
            game.name_nice
        ';
        if($table == 'genre') {
            $stmtTables = '
                FROM games.game_genre
                INNER JOIN games.game ON game.id = game_genre.game_id
                INNER JOIN games.genre ON genre.id = game_genre.genre_id
            ';
        } else {
            $stmtTables = '
                FROM games.game
                INNER JOIN games.'.$table.' ON '.$table.'.id = game.'.$table.'_id
            ';
        }
        $stmtWhere = 'WHERE game.have_game = 1 ';
        if($table === 'console') {
            $stmtOrder = 'ORDER BY console.id, game.name_sort';
        } else {
            $stmtOrder = 'ORDER BY '.$table.'.name, game.name_sort';
        }
        $stmt = $stmtColumns.$stmtTables.$stmtWhere.$stmtOrder;
        $stmt = $this->database->get()->query($stmt);
        // check sql statement fail
        if($stmt === false) {
            return 401;
        }
        $data = $stmt->fetchAll(PDO::FETCH_GROUP);
        // check result count
        if(count($data) < 1) {
            return 404;
        }
        // output
        $o = '';
        foreach($data as $console => $games) {
            $o .= '<h2>'.$console.'</h2>';
            foreach($games AS $game) {
                $o .= $this->gameTile($game);
            }
        }
        return $o;
    }

    private function showCategory($table, $value)
    {
        // NOTE: cannot bind table name part of query
        // check table name is safe
        $safeTables = ["series", "developer", "publisher", "console", "genre"];
        if(array_search($table, $safeTables, true) === false) {
            return 401;
        }
        // query
        $stmtColumns = '
            SELECT
            game.id,
            game.name_sort,
            game.name_nice
        ';
        if($table == 'genre') {
            $stmtTables = '
                FROM games.game_genre
                INNER JOIN games.game ON game.id = game_genre.game_id
                INNER JOIN games.genre ON genre.id = game_genre.genre_id
            ';
        } else {
            $stmtTables = '
                FROM games.game
                INNER JOIN games.'.$table.' ON '.$table.'.id = game.'.$table.'_id
            ';
        }
        $stmtWhereOrder = '
            WHERE game.have_game = 1 AND '.$table.'.name = :value
            ORDER BY game.name_sort
        ';
        $stmt = $stmtColumns.$stmtTables.$stmtWhereOrder;
        $stmt = $this->database->get()->prepare($stmt);
        $stmt->execute(['value' => $value]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // check result count
        if(count($data) < 1) {
            return 404;
        }
        // output
        $o = '<h1>Games: '.ucfirst($table).': '.$value.'</h1>';
        foreach($data as $game) {
            $o .= $this->gameTile($game);
        }
        return $o;
    }

    private function showGame($name)
    {
        // query game data
        $stmt = $this->database->get()->prepare('
            SELECT 
            game.id,
            game.name_official,
            game.name_nice,
            game.rating,
            game.about,
            game.release_date,
            series.name AS `series_name`,
            developer.name AS `developer_name`,
            publisher.name AS `publisher_name`,
            console.name AS `console_name`
            FROM games.game
            LEFT JOIN games.series ON series.id = game.series_id
            INNER JOIN games.developer ON developer.id = game.developer_id
            INNER JOIN games.publisher ON publisher.id = game.publisher_id
            INNER JOIN games.console ON console.id = game.console_id
            WHERE game.name_sort = :name
            LIMIT 1
        ');
        $stmt->execute(['name' => $name]);
        $gameData = $stmt->fetchAll();
        // check result count
        if(count($gameData) !== 1) {
            return 404;
        }
        $gameData = $gameData[0];
        // query genre data
        $stmt = $this->database->get()->prepare('
            SELECT 
            genre.name
            FROM games.game_genre
            LEFT JOIN games.genre ON genre.id = game_genre.genre_id
            WHERE game_genre.game_id = :game_id
            ORDER BY game_genre.id
        ');
        $stmt->execute(['game_id' => $gameData['id']]);
        $genreData = $stmt->fetchAll();
        // check result count
        if(count($genreData) < 1) {
            return 401;
        }
        // create genre string
        $genreString = "";
        foreach($genreData as $genre) {
            $genreString .= '<a href="/games/genre/'.$genre['name'].'/">'.$genre['name'].'</a> ';
        }
        // output
        $o = '<h1>'.$gameData['name_nice'].'</h1>';
        $o .= '<p><img src="/games/'.$gameData['id'].'/cover.jpg" height="280" width="280"></p>';
        $o .= '<table>';
        $o .= '<tr><td>Official Title</td><td>'.$gameData['name_official'].'</td></tr>';
        $o .= '<tr><td>Rating</td><td>'.$gameData['rating'].'</td></tr>';
        $o .= '<tr><td>Release Date</td><td>'.date_format(date_create($gameData['release_date']), 'j M Y').'</td></tr>';
        $o .= '<tr><td>Genre</td><td>'.$genreString.'</td></tr>';
        $o .= '<tr><td>Series</td><td><a href="/games/series/'.$gameData['series_name'].'/">'.$gameData['series_name'].'</a></td></tr>';
        $o .= '<tr><td>Developer</td><td><a href="/games/developer/'.$gameData['developer_name'].'/">'.$gameData['developer_name'].'</td></tr>';
        $o .= '<tr><td>Publisher </td><td><a href="/games/publisher/'.$gameData['publisher_name'].'/">'.$gameData['publisher_name'].'</td></tr>';
        $o .= '<tr><td>Console</td><td><a href="/games/console/'.$gameData['console_name'].'/">'.$gameData['console_name'].'</td></tr>';
        $o .= '</table>';
        $o .= '<p>About: '.$gameData['about'].'</p>';
        return $o;
    }

    private function gameTile($game)
    {
        $o = '';
        $o .= '<div><a href="/games/'.$game['name_sort'].'/">';
        $o .= '<img src="/games/'.$game['id'].'/cover.jpg" height="280" width="280"><br>';
        $o .= $game['name_nice'].'</a></div>';
        return $o;
    }
}