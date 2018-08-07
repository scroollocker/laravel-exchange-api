<?php
class MySQLiWorker {

    protected static $instance;  // object instance
    public $dbName;
    public $dbHost;
    public $dbUser;
    public $dbPassword;
    public $connectLink = null;

    //Чтобы нельзя было создать через вызов new MySQLiWorker
    private function __construct() { /* ... */
    }

    //Чтобы нельзя было создать через клонирование
    private function __clone() { /* ... */
    }<?php
2
class MySQLiWorker {
3
​
4
    protected static $instance;  // object instance
5
    public $dbName;
6
    public $dbHost;
7
    public $dbUser;
8
    public $dbPassword;
9
    public $connectLink = null;
10
​
11
    //Чтобы нельзя было создать через вызов new MySQLiWorker
12
    private function __construct() { /* ... */
13
    }
14
​
15
    //Чтобы нельзя было создать через клонирование
16
    private function __clone() { /* ... */
17
    }
18
​
19
    //Чтобы нельзя было создать через unserialize
20
    private function __wakeup() { /* ... */
21
    }
22
​
23
    //Получаем объект синглтона
24
    public static function getInstance($dbName, $dbHost, $dbUser, $dbPassword) {
25
        if (is_null(self::$instance)) {
26
            self::$instance = new MySQLiWorker();
27
            self::$instance->dbName = $dbName;
28
            self::$instance->dbHost = $dbHost;
29
            self::$instance->dbUser = $dbUser;
30
            self::$instance->dbPassword = $dbPassword;
31
            self::$instance->openConnection();
32
        }
33
        return self::$instance;
34
    }
35
​
36
    //Определяем типы параметров запроса к базе и возвращаем строку для привязки через ->bind
37
    function prepareParams($params) {
38
        $retSTMTString = '';
39
        foreach ($params as $value) {
40
            if (is_int($value) || is_double($value)) {
41
                $retSTMTString.='d';
42
            }
43
            if (is_string($value)) {
44
                $retSTMTString.='s';
45
            }
46
        }
47
        return $retSTMTString;
48
    }
49


    //Чтобы нельзя было создать через unserialize
    private function __wakeup() { /* ... */
    }

    //Получаем объект синглтона
    public static function getInstance($dbName, $dbHost, $dbUser, $dbPassword) {
        if (is_null(self::$instance)) {
            self::$instance = new MySQLiWorker();
            self::$instance->dbName = $dbName;
            self::$instance->dbHost = $dbHost;
            self::$instance->dbUser = $dbUser;
            self::$instance->dbPassword = $dbPassword;
            self::$instance->openConnection();
        }
        return self::$instance;
    }

    //Определяем типы параметров запроса к базе и возвращаем строку для привязки через ->bind
    function prepareParams($params) {
        $retSTMTString = '';
        foreach ($params as $value) {
            if (is_int($value) || is_double($value)) {
                $retSTMTString.='d';
            }
            if (is_string($value)) {
                $retSTMTString.='s';
            }
        }
        return $retSTMTString;
    }

    //Соединяемся с базой
    public function openConnection() {
        if (is_null($this->connectLink)) {
            $this->connectLink = new mysqli($this->dbHost, $this->dbUser, $this->dbPassword, $this->dbName);
            $this->connectLink->query("SET NAMES utf8");
            if (mysqli_connect_errno()) {
                printf("Подключение невозможно: %s\n", mysqli_connect_error());
                $this->connectLink = null;
            } else {
                mysqli_report(MYSQLI_REPORT_ERROR);
            }
        }
        return $this->connectLink;
    }

    //Закрываем соединение с базой
    public function closeConnection() {
        if (!is_null($this->connectLink)) {
            $this->connectLink->close();
        }
    }

    //Преобразуем ответ в ассоциативный массив
    public function stmt_bind_assoc(&$stmt, &$out) {
        $data = mysqli_stmt_result_metadata($stmt);
        $fields = array();
        $out = array();
        $fields[0] = $stmt;
        $count = 1;
        $currentTable = '';
        while ($field = mysqli_fetch_field($data)) {
            if (strlen($currentTable) == 0) {
                $currentTable = $field->table;
            }
            $fields[$count] = &$out[$field->name];
            $count++;
        }
        call_user_func_array('mysqli_stmt_bind_result', $fields);
    }
	
	// Получить данные через postgres
	static public function get_data_from_pgsql($query) {
	
		$conn = pg_connect("host=localhost port=5432 dbname=postgres user=postgres password=123");
		
		$result = pg_query($conn, $query);
		echo pg_last_error($conn);
		pg_close($conn);
		
		return $result;
		
	}

}

?>
