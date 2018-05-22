<?php
/*
Parser

22 May 2018

Поиск ведется по четырем группам
(A|B|C|D)

A: \[(.+)\] - находит то, что заключено в квадратные скобки
B: \"-\" - отсутствующее значение в кавычках
C: \"([^\"]+)\" - находит то, что заключено в кавычки
D: (\S+) - текст без пробелов

*/

class Parser 
{
	protected $filepath, $contents, $string_limit, $regex_rule, $last_spider;
	protected $views, $unique_ip_array, $traffic, $spiders, $status_array, $unique_url_array;
	
	const BRACKETS = '\[(.+)\]';
	const QUOTE_EMPTY = '\"-\"';
	const QUOTES = '\"(.+?)[^\134]\"';
	const NOWHITESPACE = '(\S+)';
		
	function __construct($path, $number_of_strings) 
	{
		$this->filepath = $path; // Получаем путь к файлу
		$this->contents = @fopen($this->filepath, "r"); // Открываем файл для чтения
		$this->string_limit = $number_of_strings; // Читать не весь файл (для проверки скрипта)
		$this->regex_rule = "/".self::BRACKETS."|".self::QUOTE_EMPTY."|".self::QUOTES."|".self::NOWHITESPACE."/"; // Шаблон поиска
		
		/* Инициализируем начальное состояние необходимых нам переменных */
		$this->views = 0;
		$this->unique_ip_array = array();
		$this->unique_url_array = array();
		$this->traffic = 0;
		$this->spiders = array("Google" => 0, "Bing" => 0, "Baidu" => 0, "Yandex" => 0);
		$this->status_array = array();
		
		/* Парсим файл, который нам скормили (защищаемся от несуществующего файла) */
		if ($this->contents)
		{
			$this->parse();
		}
	}
	
	/* Вспомогательная функция, для хранения IP в формате числа */
	protected function IP2Int ($ip)
	{
		$ip_parts = explode('.', $ip);
		return $ip_parts[0] * 16777216
			+ $ip_parts[1] * 65536
            + $ip_parts[2] * 256
            + $ip_parts[3];
	}
	
	/* Вспомогательная функция, для определения является ли посетитель поисковым ботом */
	protected function isSpider($user_agent)
	{
		$isBot = false;
		
		if (preg_match('/googlebot/i', $user_agent))
		{
			$this->last_spider = "Google";
			$isBot = true;
		}
		else if (preg_match('/yandex/i', $user_agent))
		{
			$this->last_spider = "Yandex";
			$isBot = true;
		}
		else if (preg_match('/baiduspider/i', $user_agent))
		{
			$this->last_spider = "Baidu";
			$isBot = true;
		}
		else if (preg_match('/bingbot/i', $user_agent))
		{
			$this->last_spider = "Bing";
			$isBot = true;
		}
		
		return $isBot;
	}
	
	/* Основной функционал парсера, запускается один раз */
	protected function parse() 
	{
		while (($current_string = fgets($this->contents)) !== FALSE && $this->views < $this->string_limit) 
		{
			preg_match_all($this->regex_rule, $current_string, $match_array); // Парсим файл по заданному ранее правилу
			$all_matches = $match_array[0]; // У нас несколько групп, нам нужны попадания по всем группам
			
			$this->unique_ip_array[] = $this->IP2Int($all_matches[0]); // Храним IP как числа, а не строки
			$this->unique_url_array[] = $all_matches[7]; // Записываем все url
			$this->views++; // Увеличиваем счетчик
			isset($this->status_array[$all_matches[5]]) ? $this->status_array[$all_matches[5]]++ : $this->status_array[$all_matches[5]] = 1; // Проверяем, что код ответа уже встречался, если нет, то создаем
			!$this->isSpider($all_matches[8]) ?: $this->spiders[$this->last_spider]++; // Если это бот, то записываем его
			$this->traffic += $all_matches[6]; // Суммируем трафик
		}
		
		/* Оставляем только уникальные значения IP и url */
		$this->unique_ip_array = array_unique($this->unique_ip_array);
		$this->unique_url_array = array_unique($this->unique_url_array);
		fclose($this->contents); 
	}
	
	/* Выводим все в формате JSON */
	public function jsonPrint()
	{
		$json_array = array();
		
		$json_array['views'] = $this->views;
		$json_array['urls'] = count($this->unique_url_array);
		$json_array['ips'] = count($this->unique_ip_array);
		$json_array['traffic'] = $this->traffic;
		$json_array['crawlers'] = $this->spiders;
		$json_array['statusCodes'] = $this->status_array;
		
		$json_array = json_encode($json_array);
		
		echo $json_array;
	}
}

// Для запуска из под командной строки
if (isset($argv[1])) 
{
	$limit = (isset($argv[2]) ?  $argv[2] : PHP_INT_MAX); // Если передан лимит на количество обрабатываемых строк
	$Parser = new Parser($argv[1], $limit); // Парсер отрабатывает сразу на старте
	$Parser->jsonPrint(); // Выводим в формате, который требовался, можно добавить, скажем, функцию с serialize выводом, вместо json
}

// Передача параметров через GET
if (isset($_GET['file'])) 
{
	$limit = (isset($_GET['limit']) ? $_GET['limit'] : PHP_INT_MAX); // Если передан лимит на количество обрабатываемых строк
	$Parser = new Parser($_GET['file'], $limit); // Парсер отрабатывает сразу на старте
	$Parser->jsonPrint(); // Выводим в формате, который требовался, можно добавить, скажем, функцию с serialize выводом, вместо json
}
