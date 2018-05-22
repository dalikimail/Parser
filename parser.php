<?php
/**
* Parser for access.log 
* ver. 0.1 (22 May 2018)
*
* Поиск ведется по четырем группам
* (A|B|C|D)
*
* A: \[(.+)\] - находит то, что заключено в квадратные скобки
* B: \"-\" - отсутствующее значение в кавычках
* C: \"([^\"]+)\" - находит то, что заключено в кавычки
* D: (\S+) - текст без пробелов
*
*/

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

	// Забираем данные в формате JSON
	/**
	* @return json array
	*/
	public function makeJsonOutput()
	{
		$jsonResult = [];
		
		$jsonResult['views'] = $this->viewsCount;
		$jsonResult['urls'] = count($this->uniqueUrlList);
		$jsonResult['ips'] = count($this->uniqueIpList);
		$jsonResult['traffic'] = $this->trafficCount;
		$jsonResult['crawlers'] = $this->spidersList;
		$jsonResult['statusCodes'] = $this->statusList;
		
		$jsonResult = json_encode($jsonResult);
		
		return $jsonResult;
	}
	
	/**
	* @param string $filePath
	* @param int $numberOfStrings
	*/
	function __construct($filePath, $numberOfStrings) 
	{
		$this->filePath = $filePath; // Получаем путь к файлу
		$this->openedFile = @fopen($this->filePath, "r"); // Открываем файл для чтения
		$this->stringsLimit = $numberOfStrings; // Читать не весь файл (для проверки скрипта)
		$this->regexRule = "/".self::BRACKETS."|".self::QUOTE_EMPTY."|".self::QUOTES."|".self::NOWHITESPACE."/"; // Шаблон поиска
		
		// Инициализируем начальное состояние необходимых нам переменных
		$this->viewsCount = 0;
		$this->uniqueIpList = [];
		$this->uniqueUrlList = [];
		$this->trafficCount = 0;
		$this->spidersList = array("Google" => 0, "Bing" => 0, "Baidu" => 0, "Yandex" => 0);
		$this->statusList = [];
		
		// Парсим файл, который нам скормили (защищаемся от несуществующего файла) */
		if ($this->openedFile)
		{
			$this->parseFile();
		}
	}
	
	// Добавить статус в массив статусов
	/**
	* @param int $status
	*/
	private function addStatusToList($status)
	{
		if (array_key_exists($status, $this->statusList))
		{
			$this->statusList[$status]++;
		}
		else
		{
			$this->statusList[$status] = 1;
		}
	}
	
	// Попытка добавить поискового бота в массив ботов
	/**
	* @param string $userAgent
	*/
	private function tryToAddSpider($userAgent)
	{
		if ($this->isSpider($userAgent))
		{
			$this->spidersList[$this->lastSpider]++;
		}
	}
	
	// Парсер строки
	/**
	* @param string $stringFromFile
	* @return mixed
	*/
	private function parseString($stringFromFile)
	{
		$result = [];
		
		preg_match_all($this->regexRule, $stringFromFile, $pregmatchedString); 
		$allMatchedData = $pregmatchedString[0]; 
		
		$result['ip'] = $allMatchedData[0];
		$result['status'] = $allMatchedData[5];
		$result['traffic'] = $allMatchedData[6];
		$result['url'] = $allMatchedData[7];
		$result['userAgent'] = $allMatchedData[8];
		
		return $result;
	}
	
	// Парсер файла
	private function parseFile() 
	{
		$currentString = fgets($this->openedFile);
		
		while ($currentString && $this->viewsCount < $this->stringsLimit) 
		{
			$parsedString = $this->parseString($currentString);
			
			$this->uniqueIpList[] = $this->convertIpToInt($parsedString['ip']);
			$this->uniqueUrlList[] = $parsedString['url'];
			$this->viewsCount++;
			$this->addStatusToList($parsedString['status']);
			$this->tryToAddSpider($parsedString['userAgent']);
			$this->trafficCount += $parsedString['traffic']; 
			
			$currentString = fgets($this->openedFile);
		}
		
		$this->uniqueIpList = array_unique($this->uniqueIpList);
		$this->uniqueUrlList = array_unique($this->uniqueUrlList);
		
		fclose($this->openedFile); 
	}
	
	// Вспомогательная функция, для хранения IP в формате числа
	/**
	* @param string $ip
	* @return int
	*/
	private function convertIpToInt($ip)
	{
		$ipPart = explode('.', $ip);
		$result = $ipPart[0] * 16777216
			+ $ipPart[1] * 65536
            + $ipPart[2] * 256
            + $ipPart[3];
		return $result;
	}
	
	// Вспомогательная функция, для определения является ли посетитель поисковым ботом
	/**
	* @param string $userAgent
	* @return bool
	*/
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
	$filePath = $argv[1];
	$limit = (count($argv) > 2 ?  $argv[2] : PHP_INT_MAX);
	$parser = new Parser($filePath, $limit); 
	$output = $parser->makeJsonOutput();
	
	echo $output;
}

// Передача параметров через GET
if (array_key_exists('file', $_GET)) 
{
	$filePath = $_GET['file'];
	$limit = (array_key_exists('limit', $_GET) ? $_GET['limit'] : PHP_INT_MAX);
	$parser = new Parser($filePath, $limit); 
	$output = $parser->makeJsonOutput();
	
	echo $output;
}
