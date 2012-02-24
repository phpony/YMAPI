<?php

include("ymapi.php");
$test = new YMAPI('1234567890abcdef', 'example.com');

function exception_handler($e) {
	echo "Error #{$e->getMessage()}: {$test->error}\n";
}
set_exception_handler('exception_handler');

echo "=== Получаем список пользователей:\n";
print_r($test->get_users());

echo "=== Проверяем существование аккаунта test:\n";
echo ($is = $test->is_user('test')) ? "Существует!\n" : "Не существует!\n";

if(!$is) {
	echo "=== test не существует, создаем его с паролем testtest:\n";
	echo ($test->add_user('test', 'testtest')) ? "Успешно!\n" : "Неудача!\n";
}

echo "=== Читаем личные данные test:\n";
print_r($test->get_user_info('test'));

echo "=== Меняем пароль и задаем личные данные для test:\n";
$set = $test->set_user_info('test', array(
	'password' 	=> 'blablabla',
	'iname' 	=> 'Иванна',
	'fname' 	=> 'Иванова',
	'sex' 		=> '2',
)); 
echo ($set) ? "Успешно!\n" : "Неудача!\n";

echo "=== Читаем личные данные test:\n";
print_r($test->get_user_info('test'));

echo "=== Непрочитанных сообщений у test:\n";
echo $test->unread_count('test')."\n";

echo "=== Удаляем test:\n";
echo ($test->delete_user('test')) ? "Успешно!\n" : "Неудача!\n";

echo "=== Спасибо за внимание!\n";

?>