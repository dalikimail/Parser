<?php
/**
* Parser
*
* 22 May 2018
*
* Поиск ведется по четырем группам
* (A|B|C|D)
*
* A: \[(.+)\] - находит то, что заключено в квадратные скобки
* B: \"-\" - отсутствующее значение в кавычках
* C: \"([^\"]+)\" - находит то, что заключено в кавычки
* D: (\S+) - текст без пробелов
*
**/

class Parser
{
	private $filePath;
	private $openedFile;
	private $stringsLimit;
	private $regexRule;
	private $lastSpider;
	
	private $viewsCount;
	private $uniqueIpList;
	private $trafficCount;
	private $spidersList;
	private $statusList;
	private $uniqueUrlList;
	
	const BRACKETS = '\[(.+)\]';
	const QUOTE_EMPTY = '\"-\"';
	const QUOTES = '\"(.+?)[^\134]\"';
	const NOWHITESPACE = '(\S+)';

	/* Выводим все в формате JSON */
	public function jsonPrint()
	{
		$json_array = array();
		
		$json_array['viewsCount'] = $this->viewsCount;
		$json_array['urls'] = count($this->uniqueUrlList);
		$json_array['ips'] = count($this->uniqueIpList);
		$json_array['trafficCount'] = $this->trafficCount;
		$json_array['crawlers'] = $this->spidersList;
		$json_array['statusCodes'] = $this->statusList;
		
		$json_array = json_encode($json_array);
		
		echo $json_array;
	}
	
	function __construct($path, $number_of_strings) 
	{
		$this->filePath = $path; // Получаем путь к файлу
		$this->openedFile = @fopen($this->filePath, "r"); // Открываем файл для чтения
		$this->stringsLimit = $number_of_strings; // Читать не весь файл (для проверки скрипта)
		$this->regexRule = "/".self::BRACKETS."|".self::QUOTE_EMPTY."|".self::QUOTES."|".self::NOWHITESPACE."/"; // Шаблон поиска
		
		/* Инициализируем начальное состояние необходимых нам переменных */
		$this->viewsCount = 0;
		$this->uniqueIpList = [];
		$this->uniqueUrlList = [];
		$this->trafficCount = 0;
		$this->spidersList = array("Google" => 0, "Bing" => 0, "Baidu" => 0, "Yandex" => 0);
		$this->statusList = [];
		
		/* Парсим файл, который нам скормили (защищаемся от несуществующего файла) */
		if ($this->openedFile)
		{
			$this->parseFile();
		}
	}
	
	/* Основной функционал парсера, запускается один раз */
	private function parseFile() 
	{
		$currentString = fgets($this->openedFile);
		while ($currentString !== FALSE && $this->viewsCount < $this->stringsLimit) 
		{
			preg_match_all($this->regexRule, $currentString, $pregmatchedString); // Парсим файл по заданному ранее правилу
			$allMatchedData = $pregmatchedString[0]; // У нас несколько групп, нам нужны попадания по всем группам
			
			$this->uniqueIpList[] = $this->convertIpToInt($allMatchedData[0]); // Храним IP как числа, а не строки
			$this->uniqueUrlList[] = $allMatchedData[7]; // Записываем все url
			$this->viewsCount++; // Увеличиваем счетчик
			isset($this->statusList[$allMatchedData[5]]) ? $this->statusList[$allMatchedData[5]]++ : $this->statusList[$allMatchedData[5]] = 1; // Проверяем, что код ответа уже встречался, если нет, то создаем
			!$this->isSpider($allMatchedData[8]) ?: $this->spidersList[$this->lastSpider]++; // Если это бот, то записываем его
			$this->trafficCount += $allMatchedData[6]; // Суммируем трафик
			
			$currentString = fgets($this->openedFile); // Получаем следующую строку
		}
		
		/* Оставляем только уникальные значения IP и url */
		$this->uniqueIpList = array_unique($this->uniqueIpList);
		$this->uniqueUrlList = array_unique($this->uniqueUrlList);
		fclose($this->openedFile); 
	}
	
	/* Вспомогательная функция, для хранения IP в формате числа */
	private function convertIpToInt ($ip)
	{
		$ipPart = explode('.', $ip);
		return $ipPart[0] * 16777216
			+ $ipPart[1] * 65536
            + $ipPart[2] * 256
            + $ipPart[3];
	}
	
	/* Вспомогательная функция, для определения является ли посетитель поисковым ботом */
	private function isSpider($userAgent)
	{
		$isBot = false;
		
		if (preg_match('/googlebot/i', $userAgent))
		{
			$this->lastSpider = "Google";
			$isBot = true;
		}
		else if (preg_match('/yandex/i', $userAgent))
		{
			$this->lastSpider = "Yandex";
			$isBot = true;
		}
		else if (preg_match('/baiduspider/i', $userAgent))
		{
			$this->lastSpider = "Baidu";
			$isBot = true;
		}
		else if (preg_match('/bingbot/i', $userAgent))
		{
			$this->lastSpider = "Bing";
			$isBot = true;
		}
		
		return $isBot;
	}
}

// Для запуска из под командной строки
if (isset($argv[1])) 
{
	$limit = (isset($argv[2]) ?  $argv[2] : PHP_INT_MAX); // Если передан лимит на количество обрабатываемых строк
	$parser = new Parser($argv[1], $limit); // Парсер отрабатывает сразу на старте
	$parser->jsonPrint(); // Выводим в формате, который требовался, можно добавить, скажем, функцию с serialize выводом, вместо json
}

// Передача параметров через GET
if (isset($_GET['file'])) 
{
	$limit = (isset($_GET['limit']) ? $_GET['limit'] : PHP_INT_MAX); // Если передан лимит на количество обрабатываемых строк
	$parser = new Parser($_GET['file'], $limit); // Парсер отрабатывает сразу на старте
	$parser->jsonPrint(); // Выводим в формате, который требовался, можно добавить, скажем, функцию с serialize выводом, вместо json
}
