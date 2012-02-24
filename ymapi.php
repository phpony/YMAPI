<?php

define('YMAPI_EMPTY_RESPONSE', 	100010);
define('YMAPI_ERROR_RESPONSE', 	100015);
define('YMAPI_ACCESS_DENIED', 	100020);
define('YMAPI_WRONG_METHOD', 	100030);
define('YMAPI_BROKEN_XML',		100040);
define('YMAPI_WRONG_DOMAIN',	100050);
define('YMAPI_INVALID_REQUEST',	100060);
define('YMAPI_INVALID_REQUEST_NAME',	100061);
define('YMAPI_INVALID_REQUEST_PASS',	100062);

/*
 * @title:		Минимальный класс для управления почтовыми ящиками pdd.yandex.ru
 * 
 * Функционал намерено ограничен только базовым управлением ящиками на одном домене, 
 * у API больше возможностей: 
 * http://api.yandex.ru/pdd/doc/api-for-domain/concepts/general.xml
 *
 * @ver:		1.0
 * @license: 	GNU GPL v3+
 * @author: 	Ritsuka 
 * @url:		http://ritsuka.ru
 */
class YMAPI
{
	// @param string $error		Текст последней ошибки
	public $error = '';

	/**
	 * Конструктор класса
	 * 
	 * @param string $token		Ключ API
	 * @param string $domain	Домен, почтой которого мы управляем
	 * @param string $lib		Предпочтительная библиотека для работы с сервером: curl, sockets, fgc
	 * @param string $cnv		Конвертер кодировки ответа сервера: none, mbstring, iconv
	 * @return null
	 */
	public function __construct($token = '123', $domain = 'example.com', $lib = '', $cnv = 'none') {
		$this->token 	= preg_replace("/[^0-9a-f]/iu", "", $token);
		$this->domain 	= urlencode($domain);
		if(in_array($lib, array('curl','sockets','fgc'))) $this->lib = $lib;
		if(in_array($cnv, array('mbstring','iconv','none'))) $this->cnv = $cnv;				
	}

	/**
	 * Получить список почтовых ящиков
	 * 
	 * @return array		Массив из адресов email
	 */
	public function get_users() {
		$users = array();
		$xml = $this->query("get_domain_users.xml?token={$this->token}&on_page=100&page=0");
		if($xml->domains->domain->name != $this->domain) throw new Exception(YMAPI_WRONG_DOMAIN);
		$total = $xml->domains->domain->emails->total;
		$users = $this->users_to_array($xml);
		if($total > 100) {
			$i = 1;
			while($i*100 < $total) {
				$xml = $this->query("get_domain_users.xml?token={$this->token}&on_page=100&page={$i}");
				$users = array_merge($users, $this->users_to_array($xml));
			}
		}
		return $users;
	}

	/**
	 * Проверить существование аккаунта
	 * 
	 * @param string $name		Логин пользователя (часть почтового адреса до "@")
	 * @return boolean			true - если существует, false - если нет
	 */
	public function is_user($name) {
		if(!(strpos($name, '@')===false)) { $name = explode("@", $name); $name = $name[0]; }
		if(empty($name)) throw new Exception(YMAPI_INVALID_REQUEST_NAME); 
		$name = urlencode($name);
		$xml = $this->query("check_user.xml?token={$this->token}&login=".$name);
		return ($xml->result == 'exists');
	}

	/**
	 * Создать почтовый ящик
	 * 
	 * @param string $name		Логин пользователя (часть почтового адреса до "@")
	 * @param string $pass		Пароль, минимум 6 символов
	 * @return boolean			true - если успешно, false - если нет
	 */
	public function add_user($name, $pass) {
		if(!(strpos($name, '@')===false)) { $name = explode("@", $name); $name = $name[0]; }
		if(empty($name)) throw new Exception(YMAPI_INVALID_REQUEST_NAME); 
		if(empty($pass) || strlen($pass) < 6) throw new Exception(YMAPI_INVALID_REQUEST_PASS); 
		$name = urlencode($name); $passwd = urlencode($pass);
		$xml = $this->query("api/reg_user.xml?token={$this->token}&domain={$this->domain}&login={$name}&passwd={$passwd}");
		if(!empty($xml->status->error)) {
			$this->error = $xml->status->error;
			throw new Exception(YMAPI_ERROR_RESPONSE);
		}
		return (isset($xml->status->success));
	}

	/**
	 * Удалить почтовый ящик
	 * 
	 * @param string $name		Логин пользователя (часть почтового адреса до "@")
	 * @return boolean			true - если успешно, false - если нет
	 */
	public function delete_user($name) {
		if(!(strpos($name, '@')===false)) { $name = explode("@", $name); $name = $name[0]; }
		if(empty($name)) throw new Exception(YMAPI_INVALID_REQUEST_NAME); 
		$xml = $this->query("delete_user.xml?token={$this->token}&login={$name}");
		return (isset($xml->ok));
	}

	/**
	 * Получить данные профиля
	 * 
	 * @param string $name		Логин пользователя (часть почтового адреса до "@")
	 * @return array			Массив данных профиля
	 * 				login		Логин пользователя.
	 * 				birth_date	Дата рождения в формате YYYY-MM-DD.
	 * 				fname		Фамилия пользователя.
	 * 				iname		Имя пользователя.
	 * 				hinta		Ответ на секретный вопрос.
	 * 				hintq		Секретный вопрос.
	 * 				mail_format	Формат почты, предпочтительный при создании письма.
	 * 				charset		Кодировка.
	 * 				nickname	Псевдоним пользователя.
	 * 				sex			Пол пользователя: 0 – не указан; 1 – мужской; 2 – женский.
	 * 				enabled		Состояние почтового ящика: 1 – включен и почта принимается; 0 – заблокирован, почта не принимается.
	 * 				signed_eula	Признак того, что пользователь принял условия публичной оферты. 1 – да; 0 – нет.
	 */
	public function get_user_info($name) {
		if(!(strpos($name, '@')===false)) { $name = explode("@", $name); $name = $name[0]; }
		if(empty($name)) throw new Exception(YMAPI_INVALID_REQUEST_NAME);
		$xml = $this->query("get_user_info.xml?token={$this->token}&login={$name}");
		if($xml->domain->name != $this->domain) throw new Exception(YMAPI_WRONG_DOMAIN);
		$info = (array)$xml->domain->user;
		foreach($info as $key=>$val) {
			if(is_object($val)) $info[$key] = (string)$val;
		}
		return $info;
	}

	/**
	 * Установить данные профиля
	 * 
	 * @param string $name		Логин пользователя (часть почтового адреса до "@")
	 * @param array $info		Массив данных профиля
	 * 				password	Пароль пользователя.
	 * 				iname		Имя пользователя.
	 * 				fname		Фамилия пользователя.
	 * 				sex			Пол пользователя: 0 – не указан; 1 – мужской; 2 – женский.
	 * 				hintq		Секретный вопрос.
	 * 				hinta		Ответ на секретный вопрос.
	 * @return boolean			true - если успешно, false - если нет
	 */
	public function set_user_info($name, $info) {
		if(!(strpos($name, '@')===false)) { $name = explode("@", $name); $name = $name[0]; }
		if(empty($name)) throw new Exception(YMAPI_INVALID_REQUEST_NAME);
		$editable = array('password', 'iname', 'fname', 'sex', 'hintq', 'hinta');
		$send = "";
		foreach($info as $key=>$val) {
			if(!empty($val) && in_array($key, $editable)) {
				$send .= "&{$key}=".urlencode($val);
			}
		}
		if(empty($send)) throw new Exception(YMAPI_INVALID_REQUEST);
		$xml = $this->query("edit_user.xml?token={$this->token}&login={$name}{$send}");
		return (isset($xml->ok));
	}

	/**
	 * Количество непрочитанных сообщений
	 * 
	 * @param string $name		Логин пользователя (часть почтового адреса до "@")
	 * @return int				Количество непрочитанных писем
	 */
	public function unread_count($name) {
		if(!(strpos($name, '@')===false)) { $name = explode("@", $name); $name = $name[0]; }
		if(empty($name)) throw new Exception(YMAPI_INVALID_REQUEST_NAME);
		$xml = $this->query("get_mail_info.xml?token={$this->token}&login={$name}");
		$count = 0;
		if(isset($xml->ok)) {
			$count = $xml->ok->attributes()->new_messages;
		}
		return $count;
	}

	// ---------------------------------------------------------------------------------------------------
	// Интимная часть процесса
	// ---------------------------------------------------------------------------------------------------

	// @param string 			Набор предустановленных значений
	private $token = '', $domain = '', $api_domain = 'pddimp.yandex.ru', $lib = 'curl', $cnv = 'mbstring';

	/**
	 * Извлечение списка адресов из XML-объекта. Вспомогательная функция для get_users,
	 * 
	 * @param string $xml		SimpleXML-объект
	 * @return array			Массив адресов почтовых ящиков
	 */
	private function users_to_array($xml) {
		$result = array();
		foreach($xml->domains->domain->emails->email as $user) {
			$result[] = $user->name."@".$this->domain;
		}
		return $result;
	}

	/**
	 * Запрос к серверам Yandex с использованием библиотеки curl
	 * 
	 * @param string $url		Адрес запроса
	 * @return string			Ответ сервера
	 */
	private function query_curl($url) {
		if(!function_exists('curl_init')) throw new Exception(YMAPI_WRONG_METHOD);
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_USERAGENT, "PHP");
		curl_setopt ($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}

	/**
	 * Запрос к серверам Yandex с использованием sockets
	 * 
	 * @param string $uri		URI-часть заспроса (без начального "/")
	 * @param string $domain	домен сервера
	 * @return string			Ответ сервера
	 */
	private function query_sockets($uri, $domain) {
		$response = '';
		if ( $fp = fsockopen("ssl://{$domain}", 443, $errno, $errstr, 30) ) {
			$msg  = "GET /{$uri} HTTP/1.1\r\n";
			$msg .= "Host: {$domain}\r\n";
			$msg .= "Connection: close\r\n\r\n";
			if ( fwrite($fp, $msg) ) while ( !feof($fp) ) $response .= fgets($fp, 1024);
			fclose($fp);
			$response = substr($response, strpos($response, "\r\n\r\n") + 4); 
			$_response = explode("\r\n", $response);
			$i = 1; $response = '';
			while(!empty($_response[$i])) { 
				$response .= $_response[$i]."\r\n";
				$i += 1;
			} 
		} else {
			throw new Exception(YMAPI_WRONG_METHOD);
		}
		return $response;
	}

	/**
	 * Запрос к серверам Yandex с использованием file_get_contents
	 * 
	 * @param string $url		Адрес запроса
	 * @return string			Ответ сервера
	 */
	private function query_fgc($url)  {
		return file_get_contents($url);
	}

	/**
	 * Конвертер ответа сервера из windows-1251 в UTF-8 
	 * (Яндекс поначалу напугал, что будет в этой кодировке все отдавать, но по факту отдает UTF-8)
	 * 
	 * @param string $text		Текст для конвертирования
	 * @return string			Результат конвертирования
	 */
	private function convert($text) {
		switch($this->cnv) {
			case 'iconv':
				return iconv('cp1251', 'utf-8', $text);
			case 'mbstring': 
				return mb_convert_encoding($text, "UTF-8", "CP1251");
			case 'none': default:
				return $text;
		}		
	}

	/**
	 * Запрос к серверам Yandex
	 * 
	 * @param string $url		Ключ запроса
	 * @return simplexml_object	Распарсенный в SimpleXML ответ сервера
	 */
	private function query($uri) {
		switch($this->lib) {
			case 'sockets':
				$result = $this->query_sockets($uri, $this->api_domain);
				break;
			case 'fgc':
				$result = $this->query_fgc("https://{$this->api_domain}/{$uri}");
				break;
			case 'curl': default:
				$result = $this->query_curl("https://{$this->api_domain}/{$uri}");
				break;
		}
		if(empty($result)) throw new Exception(YMAPI_EMPTY_RESPONSE);
		$result = $this->convert($result);
		$result = simplexml_load_string($result);
		if(!$result) throw new Exception(YMAPI_BROKEN_XML);
		if(isset($result->error)) {
			$this->error = $result->error->attributes()->reason;
			throw new Exception(YMAPI_ERROR_RESPONSE);
		} 
		return $result;
	}
}

?>