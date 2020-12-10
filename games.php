<?php

class Games
{

    private $database;

    public function __construct()
    {
        $this->database = new Database();
    }

    public function run($path)
    {
        $o = '
            <br>
            <a href="/games/wanted/">wanted</a>
            <a href="/games/console/">console</a>
            <a href="/games/developer/">developer</a>
            <a href="/games/publisher/">publisher</a>
            <a href="/games/series/">series</a>
            <a href="/games/genre/">genre</a>
            <a href="/games/release/">release</a>
            <br>
        ';
        $o .= $this->makePage($path);
        return $o;
    }

    private function makePage($path)
    {
        if(count($path) == 1) {
            return $this->listCategory('console');
        } else if(count($path) == 2) {
            switch($path[1]) {
                case "wanted":
                    return $this->listWanted();
                case "console":
                case "developer":
                case "publisher":
                case "series":
                case "genre":
                case "release":
                    return $this->listCategory($path[1]);
                default:
                    return $this->showGame($path[1]);
            }
        } else if(count($path) == 3) {
            switch($path[1]) {
                case "console":
                case "developer":
                case "publisher":
                case "series":
                case "genre":
                case "release":
                    return $this->listSubCategory($path[1], $path[2]);
            }
        }
        return 404;
    }

    private function listWanted()
    {
        // query
        $sql = $this->database->get()->query('
            SELECT
            console.name,
            game.name_nice
            FROM games.game
            INNER JOIN games.console ON console.id = game.console_id
            WHERE game.have_game = 0
            ORDER BY console.id ASC, game.name_sort ASC
        ');
        // check sql fail
        if($sql === false) return 401;
        $sql = $sql->fetchAll(PDO::FETCH_GROUP);
        // output
        $o = '<h1>Games: Wanted</h1>';
        if(count($sql) < 1) {
            return $o."<p>None!</p>";
        }
        foreach($sql as $console => $games) {
            $o .= '<h2>'.$console.'</h2>';
            foreach($games as $game) {
                $o .= $game['name_nice'].'<br>';
            }
        }
        return $o;
    }

    private function listCategory($category)
    {
        // check category is valid
        $safe = ["console", "developer", "publisher", "series", "genre", "release"];
        if(array_search($category, $safe, true) === false) return 401;
        // build query
        $sql = 'SELECT ';
        if($category === 'release') {
            $sql .= 'YEAR(game.release_date) AS `release_year`,';
        } else {
            $sql .= $category.'.name AS `'.$category.'`,';
        }
        $sql .= '
            game.id,
            game.name_sort,
            game.name_nice
        ';
        if($category === 'release') {
            $sql .= '
                FROM games.game
            ';
        } else if($category === 'genre') {
            $sql .= '
                FROM games.game_genre
                INNER JOIN games.game ON game.id = game_genre.game_id
                INNER JOIN games.genre ON genre.id = game_genre.genre_id
            ';
        } else {
            $sql .= '
                FROM games.game
                INNER JOIN games.'.$category.' ON '.$category.'.id = game.'.$category.'_id
            ';
        }
        $sql .= 'WHERE game.have_game = 1 ';
        if($category === 'release') {
            $sql .= '
                ORDER BY YEAR(game.release_date), game.release_date
            ';
        } else if($category === 'console') {
            $sql .= '
                ORDER BY console.id ASC, game.name_sort ASC
            ';
        } else {
            $sql .= '
                ORDER BY '.$category.'.name ASC, game.name_sort ASC
            ';
        }
        // run query
        $sql = $this->database->get()->query($sql);
        if($sql === false) return 401;
        $sql = $sql->fetchAll(PDO::FETCH_GROUP);
        if(count($sql) < 1) return 404;
        // output
        $o = '';
        foreach($sql as $console => $games) {
            $o .= '<h2>'.$console.'</h2>';
            foreach($games AS $game) {
                $o .= $this->gameTile($game);
            }
        }
        return $o;
    }

    private function listSubCategory($category, $subCategory)
    {
        // check category is valid
        $safe = ["console", "developer", "publisher", "series", "genre", "release"];
        if(array_search($category, $safe, true) === false) return 401;
        // build query
        $sql = '
            SELECT
            game.id,
            game.name_sort,
            game.name_nice
        ';
        if($category == 'release') {
            $sql .= '
                FROM games.game
                WHERE game.have_game = 1 AND YEAR(game.release_date) = :subCategory
            ';
        } else if($category == 'genre') {
            $sql .= '
                FROM games.game_genre
                INNER JOIN games.game ON game.id = game_genre.game_id
                INNER JOIN games.genre ON genre.id = game_genre.genre_id
                WHERE game.have_game = 1 AND '.$category.'.name = :subCategory
            ';
        } else {
            $sql .= '
                FROM games.game
                INNER JOIN games.'.$category.' ON '.$category.'.id = game.'.$category.'_id
                WHERE game.have_game = 1 AND '.$category.'.name = :subCategory
            ';
        }
        $sql .= 'ORDER BY game.name_sort ASC';
        // run query
        $sql = $this->database->get()->prepare($sql);
        $sql->execute(['subCategory' => $subCategory]);
        if($sql === false) return 401;
        $sql = $sql->fetchAll(PDO::FETCH_ASSOC);
        // output
        if(count($sql) < 1) return 404;
        $o = '<h1>Games: '.ucfirst($category).': '.$subCategory.'</h1>';
        foreach($sql as $game) {
            $o .= $this->gameTile($game);
        }
        return $o;
    }

    private function showGame($name)
    {
        // query game data
        $sql = $this->database->get()->prepare('
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
        $sql->execute(['name' => $name]);
        if($sql === false) return 401;
        $sql = $sql->fetchAll();
        if(count($sql) !== 1) return 404;
        $gameData = $sql[0];
        // query genre data
        $sql = $this->database->get()->prepare('
            SELECT 
            genre.name
            FROM games.game_genre
            LEFT JOIN games.genre ON genre.id = game_genre.genre_id
            WHERE game_genre.game_id = :game_id
            ORDER BY game_genre.id
        ');
        $sql->execute(['game_id' => $gameData['id']]);
        if($sql === false) return 401;
        $genreData = $sql->fetchAll();
        if(count($genreData) < 1) return 401;
        // create genre string
        $genreString = '';
        foreach($genreData as $genre) {
            $genreString .= '<a href="/games/genre/'.$genre['name'].'/">'.$genre['name'].'</a> ';
        }
        // create release date string
        $releaseDate = date_format(date_create($gameData['release_date']), 'j M');
        $releaseDate .= ' <a href="/games/release/'.date_format(date_create($gameData['release_date']), 'Y').'">';
        $releaseDate .= date_format(date_create($gameData['release_date']), 'Y');
        $releaseDate .= '</a>';
        // output
        $o = '<h1>'.$gameData['name_nice'].'</h1>';
        $o .= '<p><img src="/games/'.$gameData['id'].'/cover.jpg" height="280" width="280"></p>';
        $o .= '<table>';
        $o .= '<tr><td>Official Title</td><td>'.$gameData['name_official'].'</td></tr>';
        $o .= '<tr><td>Rating</td><td>'.$gameData['rating'].'</td></tr>';
        $o .= '<tr><td>Release Date</td><td>'.$releaseDate.'</td></tr>';
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